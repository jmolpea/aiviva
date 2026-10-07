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
 * Endpoint for the requests of mod_aiviva that cannot be external functions:
 * the file uploads (PDF, recordings, spoken answers), which answer in JSON, and
 * the examiner's audio, which is streamed. Everything else is in classes/external.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

use mod_aiviva\local\manager;

$action       = required_param('action', PARAM_ALPHANUMEXT);
$cmid         = required_param('cmid', PARAM_INT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

header('Content-Type: application/json; charset=utf-8');

try {
    $cm     = get_coursemodule_from_id('aiviva', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);

    // License gate: block every AI action when no valid key is bound to this site.
    if (!\mod_aiviva\license\validator::is_valid()) {
        aiviva_json_error(\mod_aiviva\license\validator::get_banner());
    }

    // Every action here stores a file or costs an API call.
    require_sesskey();

    switch ($action) {
        // Step 1: PDF upload.
        case 'upload_pdf':
            require_capability('mod/aiviva:submit', $context);
            $submission = manager::require_own_submission($aiviva, $submissionid, ['draft', 'step1'], true);
            // A re-upload while the analysis is running is only allowed once it has clearly stalled.
            if ($submission->status === 'step1' && $submission->timemodified > time() - 10 * MINSECS) {
                throw new moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }

            $upload   = aiviva_ajax_uploaded_file('pdffile', ['application/pdf'], (int)$aiviva->step1_maxfilesize);
            $filename = clean_filename($upload['name']) ?: 'document.pdf';

            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_aiviva', 'submission_pdf', $submission->id);
            $storedfile = $fs->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => 'mod_aiviva',
                'filearea'  => 'submission_pdf',
                'itemid'    => $submission->id,
                'filepath'  => '/',
                'filename'  => $filename,
                'userid'    => $USER->id,
            ], $upload['tmp_name']);

            $DB->update_record('aiviva_submissions', (object)[
                'id'           => $submission->id,
                'status'       => 'step1',
                'pdf_fileid'   => $storedfile->get_id(),
                'pdf_analysis' => null,
                'timemodified' => time(),
            ]);

            \mod_aiviva\event\submission_created::create([
                'context'  => $context,
                'objectid' => $submission->id,
                'userid'   => $USER->id,
            ])->trigger();

            $task = new \mod_aiviva\task\analyze_pdf_task();
            $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cm->id]);
            aiviva_ajax_respond_then(
                ['success' => true, 'submissionid' => $submission->id],
                $task,
                function () use ($storedfile, $aiviva, $submission) {
                    global $DB;
                    $analysis = (new \mod_aiviva\api\pdf_analyzer())->analyse($storedfile, $aiviva, (int)$submission->userid);
                    $DB->update_record('aiviva_submissions', (object)[
                        'id'           => $submission->id,
                        'pdf_analysis' => $analysis,
                        'status'       => 'step2',
                        'timemodified' => time(),
                    ]);
                }
            );
            break;

        // Step 2: presentation upload (screen recording + audio track + screenshots).
        case 'upload_video':
            require_capability('mod/aiviva:submit', $context);
            $submission = manager::require_own_submission($aiviva, $submissionid, ['step2'], true);
            if ((int)$submission->video_fileid > 0 && $submission->timemodified > time() - 10 * MINSECS) {
                throw new moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }

            $videotypes = ['video/webm', 'video/mp4', 'video/ogg'];
            $audiotypes = ['audio/webm', 'video/webm', 'audio/ogg', 'audio/mp4', 'video/mp4', 'audio/x-m4a'];
            $video = aiviva_ajax_uploaded_file('videofile', $videotypes, (int)$aiviva->step2_maxfilesize);
            // The audio track arrives in short consecutive parts, each within the transcription size limit.
            $audios = [];
            for ($part = 0; $part < \mod_aiviva\api\video_analyzer::MAX_AUDIO_PARTS; $part++) {
                $audio = aiviva_ajax_uploaded_file(
                    'audiofile' . $part,
                    $audiotypes,
                    \mod_aiviva\api\openai_client::MAX_AUDIO_MB,
                    false
                );
                if (!$audio) {
                    break;
                }
                $audios[] = $audio;
            }

            $fs = get_file_storage();
            foreach (['submission_video', 'submission_audio', 'submission_frames'] as $filearea) {
                $fs->delete_area_files($context->id, 'mod_aiviva', $filearea, $submission->id);
            }
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_aiviva',
                'itemid'    => $submission->id,
                'filepath'  => '/',
                'userid'    => $USER->id,
            ];
            $videofile = $fs->create_file_from_pathname(
                ['filearea' => 'submission_video', 'filename' => 'recording.' . $video['extension']] + $filerecord,
                $video['tmp_name']
            );
            foreach ($audios as $part => $audio) {
                $fs->create_file_from_pathname(
                    [
                        'filearea' => 'submission_audio',
                        'filename' => sprintf('audio_%03d.%s', $part, $audio['extension']),
                    ] + $filerecord,
                    $audio['tmp_name']
                );
            }

            // Screenshots taken by the browser during the recording: base64 JPEGs.
            $frames = json_decode(optional_param('frames', '[]', PARAM_RAW), true);
            $stored = 0;
            foreach (is_array($frames) ? $frames : [] as $frame) {
                if ($stored >= \mod_aiviva\api\video_analyzer::MAX_FRAMES) {
                    break;
                }
                if (!is_string($frame) || strlen($frame) > 1024 * 1024) {
                    continue;
                }
                $binary = base64_decode($frame, true);
                if ($binary === false || (new finfo(FILEINFO_MIME_TYPE))->buffer($binary) !== 'image/jpeg') {
                    continue;
                }
                $fs->create_file_from_string(
                    ['filearea' => 'submission_frames', 'filename' => sprintf('frame_%03d.jpg', $stored)] + $filerecord,
                    $binary
                );
                $stored++;
            }

            $DB->update_record('aiviva_submissions', (object)[
                'id'               => $submission->id,
                'video_fileid'     => $videofile->get_id(),
                'video_transcript' => null,
                'video_analysis'   => null,
                'timemodified'     => time(),
            ]);

            $task = new \mod_aiviva\task\analyze_video_task();
            $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cm->id]);
            aiviva_ajax_respond_then(
                ['success' => true, 'submissionid' => $submission->id],
                $task,
                function () use ($context, $aiviva, $submission) {
                    global $DB;
                    $result = (new \mod_aiviva\api\video_analyzer())->analyse($context, $submission, $aiviva);
                    // While the student is still waiting, get the tribunal ready so that it starts at once.
                    $submission->video_transcript = $result['transcript'];
                    $submission->video_analysis   = $result['analysis'];
                    \mod_aiviva\api\tribunal_conductor::prepare_ahead($aiviva, $submission, $context);
                    $DB->update_record('aiviva_submissions', (object)[
                        'id'               => $submission->id,
                        'video_transcript' => $result['transcript'],
                        'video_analysis'   => $result['analysis'],
                        'status'           => 'step3',
                        'timemodified'     => time(),
                    ]);
                }
            );
            break;

        // Step 3: the student's recorded answer; returns its transcript and who speaks next.
        case 'tribunal_turn':
            require_capability('mod/aiviva:submit', $context);
            $submission = manager::require_own_submission($aiviva, $submissionid, ['step3']);
            if (empty($submission->tribunal_timestart)) {
                throw new moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }
            if (manager::tribunal_remaining($aiviva, $submission) < -manager::TRIBUNAL_GRACE_SECS) {
                echo json_encode(['success' => true, 'expired' => true, 'remaining' => 0]);
                break;
            }

            $audiotypes = ['audio/webm', 'video/webm', 'audio/ogg', 'audio/mp4', 'video/mp4', 'audio/x-m4a'];
            $audio = aiviva_ajax_uploaded_file('audiofile', $audiotypes, \mod_aiviva\api\openai_client::MAX_AUDIO_MB);
            \core\session\manager::write_close();
            core_php_time_limit::raise(300);

            $conductor = new \mod_aiviva\api\tribunal_conductor($aiviva, $submission, $context);
            $result    = $conductor->answer($audio['tmp_name'], $audio['extension']);
            echo json_encode([
                'success'   => true,
                'expired'   => false,
                'answer'    => $result['answer'],
                'next'      => ['member' => $result['next_member'], 'name' => $result['next_name']],
                'remaining' => manager::tribunal_remaining($aiviva, $submission),
            ]);
            break;

        // Step 3: an examiner's turn as streamed audio (the source of an audio element).
        case 'tribunal_speech':
            require_capability('mod/aiviva:submit', $context);
            $submission = manager::require_own_submission($aiviva, $submissionid);
            $turn = required_param('turn', PARAM_INT);
            \core\session\manager::write_close();

            $conductor = new \mod_aiviva\api\tribunal_conductor($aiviva, $submission, $context);
            $speech    = $conductor->speech_for_turn($turn);
            $streamed  = $speech && \mod_aiviva\api\openai_client::get_instance()->stream_speech(
                $speech['text'],
                $speech['voice'],
                (int)$submission->userid
            );
            if (!$streamed) {
                // No audio: the browser falls back to showing the text for a reading pause.
                http_response_code(404);
            }
            break;

        default:
            throw new moodle_exception('invalidparameter', 'debug');
    }
} catch (\moodle_exception $e) {
    aiviva_json_error($e->getMessage());
} catch (\Throwable $e) {
    // Log full details for admins; never expose internal paths or stack traces to users.
    debugging('aiviva ajax error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
    aiviva_json_error(get_string('unexpectederror', 'error'));
}

/**
 * Outputs a JSON error response and exits.
 *
 * @param string $message Error message.
 */
function aiviva_json_error(string $message): never {
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Validates an uploaded file by real content type and size.
 *
 * @param string   $field     Name of the upload field.
 * @param string[] $mimetypes Accepted content types, as detected from the file itself.
 * @param int      $maxmb     Maximum size in MB.
 * @param bool     $required  Whether a missing file is an error.
 * @return array|null {string tmp_name; string name; string extension}, or null if optional and absent.
 * @throws moodle_exception on a missing, oversized or wrongly typed file.
 */
function aiviva_ajax_uploaded_file(string $field, array $mimetypes, int $maxmb, bool $required = true): ?array {
    $upload = $_FILES[$field] ?? null;
    if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            throw new moodle_exception('error_upload_failed', 'mod_aiviva');
        }
        return null;
    }
    if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new moodle_exception('error_file_too_large', 'mod_aiviva', '', $maxmb);
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        throw new moodle_exception('error_upload_failed', 'mod_aiviva');
    }
    if ($upload['size'] > max(1, $maxmb) * 1024 * 1024) {
        throw new moodle_exception('error_file_too_large', 'mod_aiviva', '', $maxmb);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!in_array($mime, $mimetypes, true)) {
        throw new moodle_exception('error_invalid_filetype', 'mod_aiviva');
    }

    $extensions = ['pdf' => 'pdf', 'webm' => 'webm', 'mp4' => 'mp4', 'ogg' => 'ogg', 'x-m4a' => 'm4a'];
    return [
        'tmp_name'  => $upload['tmp_name'],
        'name'      => $upload['name'],
        'extension' => $extensions[explode('/', $mime)[1]] ?? 'bin',
    ];
}

/**
 * Sends the JSON response, then carries on with slow AI work.
 *
 * Under PHP-FPM the connection is closed first and the work runs in this same
 * request; if it fails, or if the server cannot close the connection early, the
 * work is handed to the given adhoc task so that cron completes it.
 *
 * @param array                  $payload  JSON response.
 * @param \core\task\adhoc_task  $fallback Task that performs the same work.
 * @param callable               $work     The slow work.
 */
function aiviva_ajax_respond_then(array $payload, \core\task\adhoc_task $fallback, callable $work): void {
    echo json_encode($payload);
    \core\session\manager::write_close();

    if (!function_exists('fastcgi_finish_request')) {
        \core\task\manager::queue_adhoc_task($fallback, true);
        return;
    }

    ignore_user_abort(true);
    fastcgi_finish_request();
    core_php_time_limit::raise(900);
    try {
        $work();
    } catch (\Throwable $e) {
        debugging('aiviva: inline processing failed, queued for cron: ' . $e->getMessage(), DEBUG_DEVELOPER);
        \core\task\manager::queue_adhoc_task($fallback, true);
    }
}
