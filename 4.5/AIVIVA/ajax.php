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
 * AJAX endpoint for all mod_aiviva frontend requests.
 *
 * All responses are JSON. File uploads (PDF, recordings, spoken answers) are
 * the reason this is a script rather than a set of external functions.
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

    // Everything except the read-only status poll changes state or costs an API call.
    if ($action !== 'status') {
        require_sesskey();
    }

    switch ($action) {
        // Poll the state of the student's own attempt.
        case 'status':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid);
            echo json_encode(['success' => true, 'status' => $submission->status]);
            break;

        // Step 1: PDF upload.
        case 'upload_pdf':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid, ['draft', 'step1'], true);
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
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid, ['step2'], true);
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

        // Step 3: get the briefing and the opening words ready while the student is on the ready screen.
        case 'tribunal_prepare':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid, ['step3'], true);
            \core\session\manager::write_close();
            core_php_time_limit::raise(300);
            if (empty($submission->tribunal_timestart)) {
                (new \mod_aiviva\api\tribunal_conductor($aiviva, $submission, $context))->prepare();
            }
            echo json_encode(['success' => true]);
            break;

        // Step 3: start the tribunal, or resume it after a reload. The clock never restarts.
        case 'tribunal_opening':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid, ['step3']);
            if (empty($submission->tribunal_timestart)) {
                // A session that has begun may always be finished; a new one needs the activity to be open.
                aiviva_ajax_own_submission($aiviva, $submissionid, ['step3'], true);
            }
            \core\session\manager::write_close();
            core_php_time_limit::raise(300);

            $conductor = new \mod_aiviva\api\tribunal_conductor($aiviva, $submission, $context);
            echo json_encode(['success' => true] + $conductor->start_or_resume());
            break;

        // Step 3: the student's recorded answer; returns its transcript and who speaks next.
        case 'tribunal_turn':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid, ['step3']);
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

        // Step 3: the examiner's reply to the answer just recorded.
        case 'tribunal_next':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid, ['step3']);
            if (empty($submission->tribunal_timestart)) {
                throw new moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }
            \core\session\manager::write_close();
            core_php_time_limit::raise(300);

            $conductor = new \mod_aiviva\api\tribunal_conductor($aiviva, $submission, $context);
            echo json_encode([
                'success'   => true,
                'turn'      => $conductor->next_question(),
                'remaining' => manager::tribunal_remaining($aiviva, $submission),
            ]);
            break;

        // Step 3: an examiner's turn as streamed audio (the source of an audio element).
        case 'tribunal_speech':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid);
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

        // Step 3: closing statement. The final evaluation runs once the response has been sent.
        case 'tribunal_closing':
            require_capability('mod/aiviva:submit', $context);
            $submission = aiviva_ajax_own_submission($aiviva, $submissionid);
            if (in_array($submission->status, ['submitted', 'grading', 'graded'])) {
                // Already closed (second tab, cron, repeated request): nothing left to do.
                echo json_encode(['success' => true, 'turn' => null, 'status' => $submission->status]);
                break;
            }
            if ($submission->status !== 'step3') {
                throw new moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }
            if (empty($submission->tribunal_timestart) || manager::tribunal_remaining($aiviva, $submission) > 15) {
                throw new moodle_exception('error_tribunal_not_finished', 'mod_aiviva');
            }
            \core\session\manager::write_close();
            core_php_time_limit::raise(300);

            $turn = null;
            try {
                $turn = (new \mod_aiviva\api\tribunal_conductor($aiviva, $submission, $context))->closing_statement();
            } catch (\Throwable $e) {
                debugging('aiviva: closing statement failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            $task = new \mod_aiviva\task\evaluate_submission_task();
            $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cm->id]);
            if (!manager::mark_submitted($submission, $course, $cm)) {
                echo json_encode(['success' => true, 'turn' => $turn, 'status' => 'submitted']);
                break;
            }
            aiviva_ajax_respond_then(
                ['success' => true, 'turn' => $turn, 'status' => 'submitted'],
                $task,
                function () use ($submission, $aiviva, $course, $cm) {
                    (new \mod_aiviva\api\evaluator())->evaluate($submission, $aiviva, $course, $cm);
                }
            );
            break;

        // Teacher: regenerate the AI analyses and/or the evaluation of a finished attempt.
        case 'regen_pdf':
        case 'regen_video':
        case 'regen_evaluation':
        case 'regen_all':
            require_capability('mod/aiviva:grade', $context);
            $submission = $DB->get_record(
                'aiviva_submissions',
                ['id' => $submissionid, 'aiviva' => $aiviva->id],
                '*',
                MUST_EXIST
            );
            if (!manager::can_review_user($cm, $context, (int)$submission->userid)) {
                throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
            }
            if (!in_array($submission->status, ['submitted', 'grading', 'graded'])) {
                throw new moodle_exception('error_regen_not_finished', 'mod_aiviva');
            }
            aiviva_regen_rate_check($USER->id, $submission->id, $action, $action === 'regen_all' ? 120 : 60);
            \core\session\manager::write_close();
            core_php_time_limit::raise(900);

            $update = (object)['id' => $submission->id, 'timemodified' => time()];
            $fs = get_file_storage();

            if ($action === 'regen_pdf' || $action === 'regen_all') {
                $pdffiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_pdf', $submission->id, 'id', false);
                if ($pdffiles) {
                    $analyzer = new \mod_aiviva\api\pdf_analyzer();
                    $update->pdf_analysis = $analyzer->analyse(reset($pdffiles), $aiviva, (int)$submission->userid);
                } else if ($action === 'regen_pdf') {
                    throw new moodle_exception('error_no_pdf', 'mod_aiviva');
                }
            }

            if ($action === 'regen_video' || $action === 'regen_all') {
                try {
                    $result = (new \mod_aiviva\api\video_analyzer())->analyse($context, $submission, $aiviva);
                    $update->video_transcript = $result['transcript'];
                    $update->video_analysis   = $result['analysis'];
                } catch (moodle_exception $e) {
                    // With "regenerate all" a purged recording is not an error: the stored analysis is kept.
                    if ($action === 'regen_video' || $e->errorcode !== 'error_no_recording') {
                        throw $e;
                    }
                }
            }

            $DB->update_record('aiviva_submissions', $update);
            $submission = $DB->get_record('aiviva_submissions', ['id' => $submission->id], '*', MUST_EXIST);
            (new \mod_aiviva\api\evaluator())->evaluate($submission, $aiviva, $course, $cm);
            echo json_encode(['success' => true]);
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
 * Returns the current user's own attempt, enforcing every rule that applies
 * to a student action.
 *
 * @param stdClass $aiviva        Aiviva instance.
 * @param int      $submissionid  Submission id sent by the browser.
 * @param string[] $statuses      Statuses in which the action is allowed (empty = any).
 * @param bool     $requireopen   Whether the activity must be within its availability window.
 * @return stdClass Submission record.
 * @throws moodle_exception if the attempt is not the user's latest, lacks consent,
 *                          is in the wrong state, or the activity is closed.
 */
function aiviva_ajax_own_submission(
    stdClass $aiviva,
    int $submissionid,
    array $statuses = [],
    bool $requireopen = false
): stdClass {
    global $USER;

    $submission = manager::get_latest_submission($aiviva->id, $USER->id);
    if (!$submission || (int)$submission->id !== $submissionid) {
        throw new moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
    }
    if (!$submission->gdpr_consent) {
        throw new moodle_exception('gdpr_consent_required', 'mod_aiviva');
    }
    if ($statuses && !in_array($submission->status, $statuses, true)) {
        throw new moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
    }
    if ($requireopen) {
        $availability = manager::availability(manager::get_effective_settings($aiviva, $USER->id));
        if ($availability !== '') {
            throw new moodle_exception('error_' . $availability, 'mod_aiviva');
        }
    }
    return $submission;
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

/**
 * Enforces a per-teacher, per-submission cooldown for expensive regen operations.
 *
 * @param int    $userid       Teacher's user id.
 * @param int    $submissionid Submission being regenerated.
 * @param string $operation    Operation name, for cache key namespacing.
 * @param int    $cooldownsecs Minimum seconds between calls.
 * @throws \moodle_exception if the cooldown has not yet expired.
 */
function aiviva_regen_rate_check(int $userid, int $submissionid, string $operation, int $cooldownsecs = 60): void {
    $cache = \cache::make('mod_aiviva', 'ratelimit');
    $key   = $operation . '_' . $userid . '_' . $submissionid;
    $last  = (int)($cache->get($key) ?: 0);
    $now   = time();
    if ($last > 0 && ($now - $last) < $cooldownsecs) {
        throw new \moodle_exception('regen_cooldown', 'mod_aiviva');
    }
    $cache->set($key, $now);
}
