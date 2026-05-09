<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Centralised OpenAI API client for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\api;

/**
 * Singleton HTTP client for all OpenAI API interactions.
 *
 * Features:
 *  - Centralised API key management (primary + secondary)
 *  - Retry with exponential back-off (up to 3 attempts)
 *  - Configurable timeout
 *  - Per-user rate limiting via Moodle cache
 *  - Optional content moderation before every call
 *  - Developer-mode request logging (no sensitive content)
 */
class openai_client {
    /** @var self|null Singleton instance. */
    private static ?self $instance = null;

    /** @var string Base URL for the OpenAI API. */
    private const BASE_URL = 'https://api.openai.com/v1';

    /** @var int Maximum retry attempts. */
    private const MAX_RETRIES = 3;

    /** @var string Primary API key (decrypted). */
    private string $apikey;

    /** @var string|null Secondary API key (decrypted, optional). */
    private ?string $apikeysecondary;

    /** @var int Request timeout in seconds. */
    private int $timeout;

    /** @var int Max tokens per call. */
    private int $maxtokens;

    /** @var bool Whether to run input through the moderation endpoint. */
    private bool $contentfilter;

    /** @var int Max calls per minute per user. */
    private int $ratelimit;

    /**
     * Private constructor — use {@see self::get_instance()}.
     */
    private function __construct() {
        $config = get_config('mod_aiviva');

        // Decrypt primary key.
        $encrypted = $config->openai_apikey ?? '';
        $this->apikey = $this->decrypt_key($encrypted);

        // Decrypt secondary key.
        $encryptedsecondary = $config->openai_apikey_secondary ?? '';
        $this->apikeysecondary = $encryptedsecondary ? $this->decrypt_key($encryptedsecondary) : null;

        $this->timeout       = max(30, (int)($config->api_timeout ?? 120));
        $this->maxtokens     = max(256, (int)($config->safety_max_tokens ?? 4096));
        $this->contentfilter = !empty($config->safety_content_filter);
        $this->ratelimit     = max(1, (int)($config->api_rate_limit ?? 10));
    }

    /**
     * Returns (and lazily creates) the singleton.
     *
     * @return self
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Calls the OpenAI Responses API (/v1/responses).
     *
     * Supports native file inputs (PDFs, images) via base64 file_data.
     * The response text is found at output[0].content[0].text.
     *
     * @param array  $input        Array of input message objects.
     * @param string $model        Model ID.
     * @param string $instructions System-level instructions (optional).
     * @param array  $options      Extra body parameters.
     * @return array Decoded API response.
     * @throws \moodle_exception on API error.
     */
    public function responses_completion(array $input, string $model, string $instructions = '', array $options = []): array {
        $body = array_merge(['model' => $model, 'input' => $input], $options);
        if ($instructions !== '') {
            $body['instructions'] = $instructions;
        }
        return $this->request('POST', '/responses', $body);
    }

    /**
     * Sends a chat completion request.
     *
     * @param array  $messages   Array of role/content pairs.
     * @param string $model      Model ID (e.g. 'gpt-4o').
     * @param array  $options    Extra parameters (temperature, response_format, etc.).
     * @param int    $userid     Moodle user id for rate limiting.
     * @return array Decoded response array.
     * @throws \moodle_exception on API error or rate limit exceeded.
     */
    public function chat_completion(array $messages, string $model, array $options = [], int $userid = 0): array {
        $this->check_rate_limit($userid);

        if ($this->contentfilter) {
            $this->moderate_messages($messages);
        }

        $body = array_merge([
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => $this->maxtokens,
        ], $options);

        return $this->request('POST', '/chat/completions', $body);
    }

    /**
     * Transcribes audio using Whisper.
     *
     * @param string $filepath Absolute path to audio file.
     * @param string $language ISO-639-1 language code (optional).
     * @param int    $userid   Moodle user id for rate limiting.
     * @return string Transcription text.
     * @throws \moodle_exception on API error.
     */
    public function transcribe_audio(string $filepath, string $language = '', int $userid = 0): string {
        $this->check_rate_limit($userid);

        $curlfile = new \CURLFile($filepath, mime_content_type($filepath), basename($filepath));
        $data     = ['model' => 'whisper-1', 'file' => $curlfile];
        if ($language) {
            $data['language'] = $language;
        }

        $response = $this->request_multipart('POST', '/audio/transcriptions', $data, $this->apikeysecondary ?? $this->apikey);
        return $response['text'] ?? '';
    }

    /**
     * Generates TTS audio via the OpenAI TTS endpoint.
     *
     * @param string $text   Text to synthesise.
     * @param string $voice  Voice id (alloy, echo, fable, onyx, nova, shimmer).
     * @param int    $userid Moodle user id for rate limiting.
     * @return string Raw MP3 audio bytes.
     * @throws \moodle_exception on API error.
     */
    public function text_to_speech(string $text, string $voice = 'onyx', int $userid = 0): string {
        $this->check_rate_limit($userid);

        $body = [
            'model' => 'tts-1',
            'input' => $text,
            'voice' => $voice,
        ];

        return $this->request_raw('POST', '/audio/speech', $body, $this->apikeysecondary ?? $this->apikey);
    }

    /**
     * Uploads a file to the OpenAI Files API.
     *
     * @param string $filepath  Absolute path to file.
     * @param string $purpose   Purpose string (e.g. 'assistants').
     * @return string OpenAI file id.
     * @throws \moodle_exception on API error.
     */
    public function upload_file(string $filepath, string $purpose = 'assistants'): string {
        $curlfile = new \CURLFile($filepath, mime_content_type($filepath), basename($filepath));
        $data     = ['purpose' => $purpose, 'file' => $curlfile];
        $response = $this->request_multipart('POST', '/files', $data);
        return $response['id'] ?? '';
    }

    /**
     * Deletes a file from the OpenAI Files API.
     *
     * @param string $fileid OpenAI file id.
     * @return bool True on success.
     */
    public function delete_file(string $fileid): bool {
        try {
            $this->request('DELETE', '/files/' . urlencode($fileid), []);
            return true;
        } catch (\moodle_exception $e) {
            debugging('aiviva: could not delete OpenAI file ' . $fileid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    // Internal helpers.

    /**
     * Makes a JSON API request with retry / back-off.
     *
     * @param string $method HTTP method.
     * @param string $path   URL path (relative to BASE_URL).
     * @param array  $body   Request body.
     * @param string|null $key Override API key.
     * @return array Decoded response.
     * @throws \moodle_exception on failure after all retries.
     */
    private function request(string $method, string $path, array $body, ?string $key = null): array {
        $key = $key ?? $this->apikey;
        $url = self::BASE_URL . $path;
        $attempt = 0;
        $lasterr = '';

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;
            $curl = new \curl();
            $curl->setHeader([
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
            ]);
            $options = ['CURLOPT_TIMEOUT' => $this->timeout];

            if ($method === 'POST') {
                $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
                if ($encoded === false) {
                    throw new \moodle_exception(
                        'openai_api_error',
                        'mod_aiviva',
                        '',
                        'Failed to encode request as JSON: ' . json_last_error_msg()
                    );
                }
                $raw = $curl->post($url, $encoded, $options);
            } else {
                $raw = $curl->delete($url, [], $options);
            }

            if ($curl->get_errno()) {
                $lasterr = $curl->error;
                $this->sleep_backoff($attempt);
                continue;
            }

            $info = $curl->get_info();
            $status = (int)($info['http_code'] ?? 0);
            $decoded = json_decode($raw, true);

            if ($status >= 200 && $status < 300) {
                $this->log_request($method, $path, $status);
                return $decoded ?? [];
            }

            $lasterr = $decoded['error']['message'] ?? "HTTP {$status}";

            // 429 = rate limited by OpenAI; 5xx = server error — both retry.
            if ($status === 429 || $status >= 500) {
                $this->sleep_backoff($attempt);
                continue;
            }

            // 4xx (except 429) = client error, no point retrying.
            break;
        }

        throw new \moodle_exception('openai_api_error', 'mod_aiviva', '', $lasterr);
    }

    /**
     * Makes a multipart/form-data request (used for file uploads / Whisper).
     *
     * @param string      $method HTTP method.
     * @param string      $path   URL path.
     * @param array       $data   Form data (may contain CURLFile objects).
     * @param string|null $key    Override API key.
     * @return array Decoded response.
     * @throws \moodle_exception on failure.
     */
    private function request_multipart(string $method, string $path, array $data, ?string $key = null): array {
        $key = $key ?? $this->apikey;
        $url = self::BASE_URL . $path;

        $curl = new \curl();
        $curl->setHeader(['Authorization: Bearer ' . $key]);
        $raw = $curl->post($url, $data, ['CURLOPT_TIMEOUT' => $this->timeout]);

        if ($curl->get_errno()) {
            throw new \moodle_exception('openai_api_error', 'mod_aiviva', '', $curl->error);
        }

        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        $decoded = json_decode($raw, true);

        if ($status >= 200 && $status < 300) {
            return $decoded ?? [];
        }

        throw new \moodle_exception(
            'openai_api_error',
            'mod_aiviva',
            '',
            $decoded['error']['message'] ?? "HTTP {$status}"
        );
    }

    /**
     * Makes a request and returns raw binary response (used for TTS).
     *
     * @param string      $method HTTP method.
     * @param string      $path   URL path.
     * @param array       $body   Request body.
     * @param string|null $key    Override API key.
     * @return string Raw binary response.
     * @throws \moodle_exception on failure.
     */
    private function request_raw(string $method, string $path, array $body, ?string $key = null): string {
        $key = $key ?? $this->apikey;
        $url = self::BASE_URL . $path;

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ]);
        $raw = $curl->post($url, json_encode($body), ['CURLOPT_TIMEOUT' => $this->timeout]);

        if ($curl->get_errno()) {
            throw new \moodle_exception('openai_api_error', 'mod_aiviva', '', $curl->error);
        }

        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);

        if ($status < 200 || $status >= 300) {
            $decoded = json_decode($raw, true);
            throw new \moodle_exception(
                'openai_api_error',
                'mod_aiviva',
                '',
                $decoded['error']['message'] ?? "HTTP {$status}"
            );
        }

        return $raw;
    }

    /**
     * Checks the OpenAI moderation endpoint for each user message.
     * Throws if content is flagged.
     *
     * @param array $messages Chat messages array.
     * @throws \moodle_exception if content is flagged.
     */
    private function moderate_messages(array $messages): void {
        $inputs = array_filter(
            array_column(array_filter($messages, fn($m) => $m['role'] === 'user'), 'content')
        );

        if (empty($inputs)) {
            return;
        }

        try {
            $response = $this->request('POST', '/moderations', ['input' => array_values($inputs)]);
            foreach ($response['results'] ?? [] as $result) {
                if (!empty($result['flagged'])) {
                    throw new \moodle_exception('content_flagged', 'mod_aiviva');
                }
            }
        } catch (\moodle_exception $e) {
            if ($e->errorcode === 'content_flagged') {
                throw $e;
            }
            // Moderation endpoint itself failed — log and continue to not block the user.
            debugging('aiviva: moderation endpoint failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Checks per-user rate limit using Moodle application cache.
     *
     * @param int $userid Moodle user id (0 = skip).
     * @throws \moodle_exception if rate limit exceeded.
     */
    private function check_rate_limit(int $userid): void {
        if (!$userid) {
            return;
        }

        $cache  = \cache::make('mod_aiviva', 'ratelimit');
        $key    = 'user_' . $userid . '_' . floor(time() / 60);
        $count  = (int)($cache->get($key) ?? 0);

        if ($count >= $this->ratelimit) {
            throw new \moodle_exception('rate_limit_exceeded', 'mod_aiviva');
        }

        $cache->set($key, $count + 1);
    }

    /**
     * Sleeps for an exponentially increasing duration between retries.
     *
     * @param int $attempt Current attempt number (1-based).
     */
    private function sleep_backoff(int $attempt): void {
        $seconds = min(30, 2 ** ($attempt - 1));
        sleep($seconds);
    }

    /**
     * Logs an API request in developer debug mode (no content).
     *
     * @param string $method HTTP method.
     * @param string $path   URL path.
     * @param int    $status HTTP status code.
     */
    private function log_request(string $method, string $path, int $status): void {
        if (debugging('', DEBUG_DEVELOPER)) {
            debugging(
                sprintf('aiviva openai: %s %s -> %d', $method, $path, $status),
                DEBUG_DEVELOPER
            );
        }
    }

    /**
     * Decrypts a stored API key.
     *
     * @param string $encrypted Encrypted value.
     * @return string Decrypted plaintext key.
     */
    private function decrypt_key(string $encrypted): string {
        if (empty($encrypted)) {
            return '';
        }
        try {
            return \core\encryption::decrypt($encrypted);
        } catch (\Throwable $e) {
            // Decryption failed — return empty string to avoid leaking raw data.
            debugging('aiviva: API key decryption failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }
}
