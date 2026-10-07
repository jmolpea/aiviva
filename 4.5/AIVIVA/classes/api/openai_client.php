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
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
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

    /** @var string Reasoning effort sent with every generation request (lowest level the models accept). */
    private const REASONING_EFFORT = 'low';

    /** @var int Extra output tokens allowed on top of the visible answer, for the model's reasoning. */
    private const REASONING_TOKEN_RESERVE = 2048;

    /** @var int Largest audio file, in MB, the speech-to-text service accepts in one request. */
    public const MAX_AUDIO_MB = 25;

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
        global $CFG;

        // The curl class lives in filelib.php, which not every entry point loads.
        require_once($CFG->libdir . '/filelib.php');

        // License backstop: no AI call may proceed without a valid key bound to
        // this site, guaranteeing the block holds on every call path (including
        // background tasks) even if an entry-point check is ever bypassed.
        if (!\mod_aiviva\license\validator::is_valid()) {
            throw new \moodle_exception('error_nolicense', 'mod_aiviva');
        }

        $config = get_config('mod_aiviva');

        // Decrypt primary key.
        $encrypted = $config->openai_apikey ?? '';
        $this->apikey = $this->decrypt_key($encrypted);

        // Decrypt secondary key.
        $encryptedsecondary = $config->openai_apikey_secondary ?? '';
        $this->apikeysecondary = $encryptedsecondary ? $this->decrypt_key($encryptedsecondary) : null;

        $this->timeout       = max(30, (int)($config->api_timeout ?? 300));
        $this->maxtokens     = max(256, (int)($config->safety_max_tokens ?? 16000));
        $this->contentfilter = !empty($config->safety_content_filter);
        $this->ratelimit     = max(1, (int)($config->api_rate_limit ?? 30));
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
     * @param int    $userid       Moodle user id for rate limiting.
     * @param bool   $moderate     Whether to run the content filter on the text parts of the input first.
     * @return array Decoded API response.
     * @throws \moodle_exception on API error, flagged content or rate limit exceeded.
     */
    public function responses_completion(
        array $input,
        string $model,
        string $instructions = '',
        array $options = [],
        int $userid = 0,
        bool $moderate = true
    ): array {
        $this->check_rate_limit($userid);

        if ($moderate && $this->contentfilter) {
            $this->moderate_messages($input);
        }

        $body = array_merge([
            'model'             => $model,
            'input'             => $input,
            'max_output_tokens' => $this->maxtokens + self::REASONING_TOKEN_RESERVE,
            'reasoning'         => ['effort' => self::REASONING_EFFORT],
        ], $options);
        if ($instructions !== '') {
            $body['instructions'] = $instructions;
        }
        return $this->request_complete('/responses', $body, 'max_output_tokens');
    }

    /**
     * Sends a generation request and makes sure the answer was not cut short.
     *
     * A model stops mid-sentence when it reaches its output limit. Such an answer
     * must never be stored as if it were whole, so the request is repeated once
     * with twice the room, and if the answer is still cut an error is raised.
     *
     * @param string $path       URL path (relative to BASE_URL).
     * @param array  $body       Request body.
     * @param string $limitfield Name of the body field holding the output token limit.
     * @return array Decoded response.
     * @throws \moodle_exception on API error or if the answer is still incomplete.
     */
    private function request_complete(string $path, array $body, string $limitfield): array {
        $response = $this->request('POST', $path, $body);
        if (!self::is_truncated($response)) {
            return $response;
        }

        $body[$limitfield] = 2 * (int)$body[$limitfield];
        $response = $this->request('POST', $path, $body);
        if (self::is_truncated($response)) {
            throw new \moodle_exception('error_ai_truncated', 'mod_aiviva');
        }
        return $response;
    }

    /**
     * Tells whether the model ran out of output tokens before finishing its answer.
     *
     * @param array $response Decoded Responses API or Chat Completions result.
     * @return bool
     */
    public static function is_truncated(array $response): bool {
        if (($response['status'] ?? '') === 'incomplete') {
            return ($response['incomplete_details']['reason'] ?? '') === 'max_output_tokens';
        }
        return ($response['choices'][0]['finish_reason'] ?? '') === 'length';
    }

    /**
     * Tells whether a failed call was refused by this site's own safeguards (the content
     * filter or the per-user call limit) rather than by the AI service. Such a call must
     * not be attempted again by another route.
     *
     * @param \Throwable $e The failure.
     * @return bool
     */
    public static function is_refusal(\Throwable $e): bool {
        return $e instanceof \moodle_exception
            && in_array($e->errorcode, ['content_flagged', 'rate_limit_exceeded'], true);
    }

    /**
     * Extracts the generated text from a Responses API result.
     *
     * Reasoning models return a "reasoning" item before the "message" item, so
     * the text cannot be assumed to live in the first output element.
     *
     * @param array $response Decoded Responses API result.
     * @return string Concatenated output text ('' if the model produced none).
     */
    public static function responses_output_text(array $response): string {
        $text = '';
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    $text .= $part['text'] ?? '';
                }
            }
        }
        return $text;
    }

    /**
     * Sends a chat completion request.
     *
     * @param array  $messages   Array of role/content pairs.
     * @param string $model      Model ID (e.g. 'gpt-6.1-sol').
     * @param array  $options    Extra parameters (temperature, response_format, etc.).
     * @param int    $userid     Moodle user id for rate limiting.
     * @param bool   $moderate   Whether to run the content filter on the user messages first. Pass
     *                           false when the caller has already filtered the only new text.
     * @return array Decoded response array.
     * @throws \moodle_exception on API error or rate limit exceeded.
     */
    public function chat_completion(
        array $messages,
        string $model,
        array $options = [],
        int $userid = 0,
        bool $moderate = true
    ): array {
        $this->check_rate_limit($userid);

        if ($moderate && $this->contentfilter) {
            $this->moderate_messages($messages);
        }

        // Callers still pass the historical 'max_tokens' name; current models only
        // accept 'max_completion_tokens', which also has to cover reasoning tokens.
        if (isset($options['max_tokens'])) {
            $options['max_completion_tokens'] = (int)$options['max_tokens'] + self::REASONING_TOKEN_RESERVE;
            unset($options['max_tokens']);
        }

        $body = array_merge([
            'model'                 => $model,
            'messages'              => $messages,
            'max_completion_tokens' => $this->maxtokens + self::REASONING_TOKEN_RESERVE,
            'reasoning_effort'      => self::REASONING_EFFORT,
        ], $options);

        return $this->request_complete('/chat/completions', $body, 'max_completion_tokens');
    }

    /**
     * Transcribes audio with the speech-to-text model.
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
        $data     = ['model' => \mod_aiviva\form\mod_form_helper::TRANSCRIPTION_MODEL, 'file' => $curlfile];
        if ($language) {
            $data['language'] = $language;
        }

        $response = $this->request_multipart('POST', '/audio/transcriptions', $data, $this->apikeysecondary ?? $this->apikey);
        return $response['text'] ?? '';
    }

    /**
     * Streams TTS audio (MP3) straight to the browser as it is produced.
     *
     * The first audio arrives in under a second instead of after the whole clip
     * has been synthesised, which is what makes a spoken conversation feel live.
     * Nothing is sent to the browser unless the API answers with audio, so the
     * caller can still report an error when this returns false.
     *
     * @param string $text   Text to synthesise.
     * @param string $voice  Voice id (see mod_form_helper::get_voice_options()).
     * @param int    $userid Moodle user id for rate limiting.
     * @return bool True if audio was streamed, false if the API call failed before any output.
     */
    public function stream_speech(string $text, string $voice, int $userid = 0): bool {
        $this->check_rate_limit($userid);

        $body = json_encode([
            'model' => \mod_aiviva\form\mod_form_helper::TTS_MODEL,
            'input' => $text,
            'voice' => \mod_aiviva\form\mod_form_helper::resolve_voice($voice),
        ]);

        $started = false;
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . ($this->apikeysecondary ?? $this->apikey),
            'Content-Type: application/json',
        ]);
        $curl->post(self::BASE_URL . '/audio/speech', $body, [
            'CURLOPT_TIMEOUT'        => $this->timeout,
            'CURLOPT_RETURNTRANSFER' => false,
            'CURLOPT_WRITEFUNCTION'  => static function ($handle, $chunk) use (&$started) {
                if (!$started) {
                    if ((int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE) !== 200) {
                        return 0; // Not audio: abort the transfer without sending anything.
                    }
                    $started = true;
                    header('Content-Type: audio/mpeg');
                    header('Cache-Control: no-store');
                    header('X-Accel-Buffering: no');
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                }
                echo $chunk;
                flush();
                return strlen($chunk);
            },
        ]);

        return $started;
    }

    /**
     * Runs the content filter on a piece of text, if the filter is enabled.
     *
     * @param string $text Text written or spoken by a student.
     * @throws \moodle_exception if the content is flagged.
     */
    public function moderate_text(string $text): void {
        if ($this->contentfilter) {
            $this->moderate_messages([['role' => 'user', 'content' => $text]]);
        }
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
                    throw $this->api_error($path, 'Failed to encode request as JSON: ' . json_last_error_msg());
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

        throw $this->api_error($path, $lasterr);
    }

    /**
     * Makes a multipart/form-data request (used for file uploads / transcription).
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
            throw $this->api_error($path, $curl->error);
        }

        $info = $curl->get_info();
        $status = (int)($info['http_code'] ?? 0);
        $decoded = json_decode($raw, true);

        if ($status >= 200 && $status < 300) {
            return $decoded ?? [];
        }

        throw $this->api_error($path, $decoded['error']['message'] ?? "HTTP {$status}");
    }

    /**
     * Builds the error for a failed request to the AI service.
     *
     * What the service says (quotas, billing, keys, models) is for administrators only:
     * it goes to the server log and to the exception's debug information, while users
     * get a fixed message.
     *
     * @param string $path   URL path of the request.
     * @param string $detail The service's own error text.
     * @return \moodle_exception
     */
    private function api_error(string $path, string $detail): \moodle_exception {
        debugging("aiviva: request to the AI service failed ({$path}): {$detail}", DEBUG_NORMAL);
        return new \moodle_exception('openai_api_error', 'mod_aiviva', '', null, $detail);
    }

    /**
     * Checks the OpenAI moderation endpoint for each user message.
     * Throws if content is flagged.
     *
     * @param array $messages Chat messages, or Responses API input items.
     * @throws \moodle_exception if content is flagged.
     */
    private function moderate_messages(array $messages): void {
        // Only text is moderated: multimodal messages carry their text in 'text' parts
        // (Chat Completions) or 'input_text' parts (Responses API).
        $inputs = [];
        foreach ($messages as $message) {
            if ($message['role'] !== 'user') {
                continue;
            }
            $parts = is_array($message['content']) ? $message['content'] : [['type' => 'text', 'text' => $message['content']]];
            foreach ($parts as $part) {
                if (in_array($part['type'] ?? '', ['text', 'input_text'], true) && trim($part['text'] ?? '') !== '') {
                    $inputs[] = $part['text'];
                }
            }
        }

        if (empty($inputs)) {
            return;
        }

        try {
            $response = $this->request('POST', '/moderations', ['model' => 'omni-moderation-latest', 'input' => $inputs]);
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
        // Only in command-line runs (cron, tasks), where a trace helps and no user sees it.
        if (CLI_SCRIPT && !PHPUNIT_TEST && debugging('', DEBUG_DEVELOPER)) {
            mtrace(sprintf('aiviva openai: %s %s -> %d', $method, $path, $status));
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
        // The admin settings store the key as entered (admin_setting_configpasswordunmask
        // does not encrypt), so only values carrying an encryption prefix are decrypted.
        if (!preg_match('/^(sodium|openssl-aes-256-ctr):/', $encrypted)) {
            return $encrypted;
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
