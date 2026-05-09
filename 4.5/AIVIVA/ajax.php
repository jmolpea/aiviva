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
 * All responses are JSON.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

// Read action from POST body (JSON) or query string.
$rawbody  = file_get_contents('php://input');
$jsonbody = json_decode($rawbody, true) ?? [];

$action       = $jsonbody['action'] ?? required_param('action', PARAM_ALPHANUMEXT);
$cmid         = (int)($jsonbody['cmid'] ?? required_param('cmid', PARAM_INT));
$submissionid = (int)($jsonbody['submissionid'] ?? optional_param('submissionid', 0, PARAM_INT));

header('Content-Type: application/json');

try {
    // Common setup — inside try so any DB/login exception returns JSON.
    if ($cmid <= 0) {
        json_error('Missing or invalid cmid (' . $cmid . ')');
    }
    $cm     = get_coursemodule_from_id('aiviva', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);

    // POST-mutating actions require sesskey.
    $mutatings = ['upload_pdf', 'upload_video', 'tribunal_opening', 'tribunal_turn', 'tribunal_closing',
                  'regen_pdf', 'regen_video', 'regen_evaluation', 'regen_all'];
    if (in_array($action, $mutatings)) {
        $sesskey = $jsonbody['sesskey'] ?? required_param('sesskey', PARAM_RAW);
        if (!confirm_sesskey($sesskey)) {
            json_error('Invalid session key');
        }
    }

    switch ($action) {
        // Step 1: PDF upload.
        case 'upload_pdf':
            require_capability('mod/aiviva:submit', $context);
            $submission = get_or_create_submission($aiviva, $cm, $context);

            // Validate uploaded file.
            $fileinfo = $_FILES['pdffile'] ?? null;
            if (!$fileinfo || $fileinfo['error'] !== UPLOAD_ERR_OK) {
                json_error('No file uploaded or upload error.');
            }

            // MIME type validation.
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $fileinfo['tmp_name']);
            finfo_close($finfo);
            if ($mime !== 'application/pdf') {
                json_error(get_string('error_not_pdf', 'mod_aiviva'));
            }

            // Size validation.
            $maxbytes = (int)$aiviva->step2_maxfilesize * 1024 * 1024;
            if ($fileinfo['size'] > $maxbytes) {
                json_error(get_string('error_file_too_large', 'mod_aiviva', $aiviva->step2_maxfilesize));
            }

            // Store file.
            $fs = get_file_storage();
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_aiviva',
                'filearea'  => 'submission_pdf',
                'itemid'    => $submission->id,
                'filepath'  => '/',
                'filename'  => clean_filename($fileinfo['name']),
            ];
            $fs->delete_area_files($context->id, 'mod_aiviva', 'submission_pdf', $submission->id);
            $storedfile = $fs->create_file_from_pathname($filerecord, $fileinfo['tmp_name']);

            // Update submission status.
            $DB->set_field('aiviva_submissions', 'status', 'step1', ['id' => $submission->id]);
            $DB->set_field('aiviva_submissions', 'pdf_fileid', $storedfile->get_id(), ['id' => $submission->id]);
            $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $submission->id]);

            // Fire event.
            \mod_aiviva\event\submission_created::create([
                'context'  => $context,
                'objectid' => $submission->id,
                'userid'   => $USER->id,
            ])->trigger();

            // Send response immediately, then analyse in background.
            echo json_encode(['success' => true, 'submissionid' => $submission->id]);
            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            \core_php_time_limit::raise(300);
            try {
                $analyzer = new \mod_aiviva\api\pdf_analyzer();
                $analysis = $analyzer->analyse(
                    $storedfile,
                    $aiviva->step1_prompt ?? '',
                    $aiviva->openai_model_pdf ?? 'gpt-4o',
                    0
                );
                $DB->set_field('aiviva_submissions', 'pdf_analysis', $analysis, ['id' => $submission->id]);
                $DB->set_field('aiviva_submissions', 'status', 'step2', ['id' => $submission->id]);
                $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $submission->id]);
            } catch (\Throwable $analysiserr) {
                $DB->set_field(
                    'aiviva_submissions',
                    'pdf_analysis',
                    'Analysis unavailable: ' . $analysiserr->getMessage(),
                    ['id' => $submission->id]
                );
                $task = new \mod_aiviva\task\analyze_pdf_task();
                $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cmid]);
                \core\task\manager::queue_adhoc_task($task);
            }
            break;

        // Step 1: Poll PDF analysis status.
        case 'check_pdf_status':
            require_capability('mod/aiviva:submit', $context);
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid, 'aiviva' => $aiviva->id], '*', MUST_EXIST);
            if ($sub->userid !== $USER->id) {
                json_error('Access denied');
            }
            echo json_encode(['status' => $sub->status, 'submissionid' => $sub->id]);
            break;

        // Step 2: Video upload.
        case 'upload_video':
            require_capability('mod/aiviva:submit', $context);
            $submission = $DB->get_record(
                'aiviva_submissions',
                ['id' => $submissionid, 'aiviva' => $aiviva->id, 'userid' => $USER->id],
                '*',
                MUST_EXIST
            );

            $fileinfo = $_FILES['videofile'] ?? null;
            if (!$fileinfo || $fileinfo['error'] !== UPLOAD_ERR_OK) {
                json_error('No video file uploaded or upload error.');
            }

            // MIME validation.
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $fileinfo['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ['video/webm', 'video/mp4', 'video/ogg'])) {
                json_error('Invalid video file type.');
            }

            // Size validation.
            $maxbytes = (int)$aiviva->step2_maxfilesize * 1024 * 1024;
            if ($fileinfo['size'] > $maxbytes) {
                json_error(get_string('error_video_too_large', 'mod_aiviva', $aiviva->step2_maxfilesize));
            }

            // Store video.
            $fs = get_file_storage();
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_aiviva',
                'filearea'  => 'submission_video',
                'itemid'    => $submission->id,
                'filepath'  => '/',
                'filename'  => clean_filename($fileinfo['name'] ?? 'recording.webm'),
            ];
            $fs->delete_area_files($context->id, 'mod_aiviva', 'submission_video', $submission->id);
            $storedfile = $fs->create_file_from_pathname($filerecord, $fileinfo['tmp_name']);

            // Store client-extracted frames for analysis.
            // Validate each frame: must be valid base64, ≤ 20 frames, ≤ 700 KB each.
            // (≈ 500 KB JPEG). Rejects oversized payloads and non-image data.
            $framesraw = $_POST['frames'] ?? '[]';
            $rawframes = json_decode($framesraw, true);
            $frames    = [];
            if (is_array($rawframes)) {
                foreach (array_slice($rawframes, 0, 20) as $frame) {
                    if (!is_string($frame)) {
                        continue;
                    }
                    // Reject frames exceeding ~500 KB uncompressed (700 KB base64).
                    if (strlen($frame) > 716800) {
                        continue;
                    }
                    // Must consist only of valid base64 characters.
                    if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', $frame)) {
                        continue;
                    }
                    $frames[] = $frame;
                }
            }

            $DB->set_field('aiviva_submissions', 'status', 'step2', ['id' => $submission->id]);
            $DB->set_field('aiviva_submissions', 'video_fileid', $storedfile->get_id(), ['id' => $submission->id]);
            $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $submission->id]);

            // Send response immediately, then analyse in background.
            echo json_encode(['success' => true, 'submissionid' => $submission->id]);
            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            \core_php_time_limit::raise(300);
            try {
                $analyzer = new \mod_aiviva\api\video_analyzer();
                $result   = $analyzer->analyse(
                    $storedfile,
                    $aiviva->step2_prompt ?? '',
                    $aiviva->openai_model_tribunal ?? 'gpt-4o',
                    0,
                    $frames
                );
                $DB->set_field('aiviva_submissions', 'video_transcript', $result['transcript'], ['id' => $submission->id]);
                $DB->set_field('aiviva_submissions', 'video_analysis', $result['analysis'], ['id' => $submission->id]);
                $DB->set_field('aiviva_submissions', 'status', 'step3', ['id' => $submission->id]);
                $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $submission->id]);
            } catch (\Throwable $analysiserr) {
                $DB->set_field(
                    'aiviva_submissions',
                    'video_analysis',
                    'Analysis unavailable: ' . $analysiserr->getMessage(),
                    ['id' => $submission->id]
                );
                $task = new \mod_aiviva\task\analyze_video_task();
                $task->set_custom_data([
                    'submissionid' => $submission->id,
                    'cmid'         => $cmid,
                    'frames'       => $frames,
                ]);
                \core\task\manager::queue_adhoc_task($task);
            }
            break;

        // Step 2: Poll video analysis status.
        case 'check_video_status':
            require_capability('mod/aiviva:submit', $context);
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid, 'aiviva' => $aiviva->id], '*', MUST_EXIST);
            if ($sub->userid !== $USER->id) {
                json_error('Access denied');
            }
            echo json_encode(['status' => $sub->status]);
            break;

        // Step 3: Tribunal opening.
        case 'tribunal_opening':
            require_capability('mod/aiviva:submit', $context);
            $submission = $DB->get_record(
                'aiviva_submissions',
                ['id' => $submissionid, 'aiviva' => $aiviva->id, 'userid' => $USER->id],
                '*',
                MUST_EXIST
            );

            // Tribunal can only begin once both PDF and video steps are complete.
            if (!in_array($submission->status, ['step2', 'step3'])) {
                throw new \moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }

            $conductor = new \mod_aiviva\api\tribunal_conductor($aiviva, $submission);
            $result    = $conductor->opening_statement();
            echo json_encode(array_merge(['success' => true], $result));
            break;

        // Step 3: Next tribunal question.
        case 'tribunal_turn':
            require_capability('mod/aiviva:submit', $context);
            $submission = $DB->get_record(
                'aiviva_submissions',
                ['id' => $submissionid, 'aiviva' => $aiviva->id, 'userid' => $USER->id],
                '*',
                MUST_EXIST
            );

            // Tribunal turns only valid during an active tribunal session.
            if ($submission->status !== 'step3') {
                throw new \moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }

            $response   = substr(trim($jsonbody['response'] ?? ''), 0, 5000);
            $nextmember = max(1, min(3, (int)($jsonbody['next_member'] ?? 1)));
            $turn       = max(1, (int)($jsonbody['turn'] ?? 1));

            $conductor = new \mod_aiviva\api\tribunal_conductor($aiviva, $submission);
            $result    = $conductor->next_question($nextmember, $response, $turn);
            echo json_encode(array_merge(['success' => true], $result));
            break;

        // Step 3: Closing statement.
        case 'tribunal_closing':
            require_capability('mod/aiviva:submit', $context);
            $submission = $DB->get_record(
                'aiviva_submissions',
                ['id' => $submissionid, 'aiviva' => $aiviva->id, 'userid' => $USER->id],
                '*',
                MUST_EXIST
            );

            // Closing statement only valid during an active tribunal session.
            if ($submission->status !== 'step3') {
                throw new \moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
            }

            $turn = (int)($jsonbody['turn'] ?? 0);

            // Mark as submitted immediately — even if closing_statement fails,
            // The user is done and the page should not revert to the ready screen.
            $DB->set_field('aiviva_submissions', 'status', 'submitted', ['id' => $submission->id]);
            $DB->set_field('aiviva_submissions', 'timesubmitted', time(), ['id' => $submission->id]);

            $conductor = new \mod_aiviva\api\tribunal_conductor($aiviva, $submission);
            $result    = $conductor->closing_statement($turn);

            // Run evaluation synchronously — raise PHP time limit so the API call can complete.
            \core_php_time_limit::raise(300);
            $evalstatus = 'submitted';
            $evalerrmsg = '';
            try {
                $evaluator = new \mod_aiviva\api\evaluator();
                $evaluator->evaluate($submission, $aiviva, $course, $cm);
                $evalstatus = 'graded';
            } catch (\Throwable $evalerr) {
                $errmsg = 'aiviva: evaluation failed for submission ' . $submission->id . ': ' . $evalerr->getMessage();
                debugging($errmsg, DEBUG_DEVELOPER);
                // Evaluation failed — queue as background task fallback.
                $task = new \mod_aiviva\task\evaluate_submission_task();
                $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cmid]);
                \core\task\manager::queue_adhoc_task($task);
            }

            $result['evaluation_status'] = $evalstatus;
            echo json_encode(array_merge(['success' => true], $result));
            break;

        // Poll evaluation status.
        case 'check_evaluation':
            require_capability('mod/aiviva:submit', $context);
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid, 'aiviva' => $aiviva->id], '*', MUST_EXIST);
            if ($sub->userid !== $USER->id) {
                json_error('Access denied');
            }
            echo json_encode(['status' => $sub->status]);
            break;

        // Teacher: Regenerate PDF analysis (Step 1).
        case 'regen_pdf':
            require_capability('mod/aiviva:grade', $context);
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid, 'aiviva' => $aiviva->id], '*', MUST_EXIST);
            regen_rate_check($USER->id, $sub->id, 'pdf');
            $fs  = get_file_storage();
            $pdffiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_pdf', $sub->id, '', false);
            if (!$pdffiles) {
                json_error('No PDF file found for this submission.');
            }
            \core_php_time_limit::raise(600);
            $pdffile  = reset($pdffiles);
            $analyzer = new \mod_aiviva\api\pdf_analyzer();
            $analysis = $analyzer->analyse($pdffile, $aiviva->step1_prompt ?? '', $aiviva->openai_model_pdf ?? 'gpt-4o', 0);
            $DB->set_field('aiviva_submissions', 'pdf_analysis', $analysis, ['id' => $sub->id]);
            $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $sub->id]);
            if (in_array($sub->status, ['step1', 'uploaded', ''])) {
                $DB->set_field('aiviva_submissions', 'status', 'step2', ['id' => $sub->id]);
            }
            // Re-fetch so evaluator sees updated pdf_analysis.
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid], '*', MUST_EXIST);
            $evaluator = new \mod_aiviva\api\evaluator();
            $evaluator->evaluate($sub, $aiviva, $course, $cm);
            echo json_encode(['success' => true]);
            break;

        // Teacher: Regenerate video analysis (Step 2).
        case 'regen_video':
            require_capability('mod/aiviva:grade', $context);
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid, 'aiviva' => $aiviva->id], '*', MUST_EXIST);
            regen_rate_check($USER->id, $sub->id, 'video');
            $fs  = get_file_storage();
            $vidfiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_video', $sub->id, '', false);
            if (!$vidfiles) {
                json_error('No video file found for this submission.');
            }
            \core_php_time_limit::raise(600);
            $vidfile    = reset($vidfiles);
            $analyzer   = new \mod_aiviva\api\video_analyzer();
            $vidmodel   = $aiviva->openai_model_tribunal ?? 'gpt-4o';
            $vidresult  = $analyzer->analyse($vidfile, $aiviva->step2_prompt ?? '', $vidmodel, 0, []);
            $DB->set_field('aiviva_submissions', 'video_transcript', $vidresult['transcript'], ['id' => $sub->id]);
            $DB->set_field('aiviva_submissions', 'video_analysis', $vidresult['analysis'], ['id' => $sub->id]);
            $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $sub->id]);
            if (in_array($sub->status, ['step2'])) {
                $DB->set_field('aiviva_submissions', 'status', 'step3', ['id' => $sub->id]);
            }
            // Re-fetch so evaluator sees updated video fields.
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid], '*', MUST_EXIST);
            $evaluator = new \mod_aiviva\api\evaluator();
            $evaluator->evaluate($sub, $aiviva, $course, $cm);
            echo json_encode(['success' => true]);
            break;

        // Teacher: Regenerate final evaluation.
        case 'regen_evaluation':
            require_capability('mod/aiviva:grade', $context);
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid, 'aiviva' => $aiviva->id], '*', MUST_EXIST);
            regen_rate_check($USER->id, $sub->id, 'evaluation');
            \core_php_time_limit::raise(600);
            $evaluator = new \mod_aiviva\api\evaluator();
            $evaluator->evaluate($sub, $aiviva, $course, $cm);
            echo json_encode(['success' => true]);
            break;

        // Teacher: Regenerate everything (PDF + video + evaluation).
        case 'regen_all':
            require_capability('mod/aiviva:grade', $context);
            $sub = $DB->get_record('aiviva_submissions', ['id' => $submissionid, 'aiviva' => $aiviva->id], '*', MUST_EXIST);
            regen_rate_check($USER->id, $sub->id, 'all', 120); // 2-minute cooldown for full regen.
            \core_php_time_limit::raise(900);
            $fs = get_file_storage();

            // Re-analyse PDF if stored.
            $pdffiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_pdf', $sub->id, '', false);
            if ($pdffiles) {
                $pdffile     = reset($pdffiles);
                $pdfanalyzer = new \mod_aiviva\api\pdf_analyzer();
                $pdfmodel    = $aiviva->openai_model_pdf ?? 'gpt-4o';
                $pdfanalysis = $pdfanalyzer->analyse($pdffile, $aiviva->step1_prompt ?? '', $pdfmodel, 0);
                $DB->set_field('aiviva_submissions', 'pdf_analysis', $pdfanalysis, ['id' => $sub->id]);
                $sub->pdf_analysis = $pdfanalysis;
            }

            // Re-analyse video if stored.
            $vidfiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_video', $sub->id, '', false);
            if ($vidfiles) {
                $vidfile     = reset($vidfiles);
                $vidanalyzer = new \mod_aiviva\api\video_analyzer();
                $vidmodel    = $aiviva->openai_model_tribunal ?? 'gpt-4o';
                $vidresult   = $vidanalyzer->analyse($vidfile, $aiviva->step2_prompt ?? '', $vidmodel, 0, []);
                $DB->set_field('aiviva_submissions', 'video_transcript', $vidresult['transcript'], ['id' => $sub->id]);
                $DB->set_field('aiviva_submissions', 'video_analysis', $vidresult['analysis'], ['id' => $sub->id]);
                $sub->video_transcript = $vidresult['transcript'];
                $sub->video_analysis   = $vidresult['analysis'];
            }

            // Re-run final evaluation with updated analysis data.
            $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $sub->id]);
            $evaluator = new \mod_aiviva\api\evaluator();
            $evaluator->evaluate($sub, $aiviva, $course, $cm);
            echo json_encode(['success' => true]);
            break;

        default:
            json_error('Unknown action: ' . s($action));
    }
} catch (\moodle_exception $e) {
    json_error($e->getMessage());
} catch (\Throwable $e) {
    // Log full details for admins; never expose internal paths or stack traces to users.
    debugging('aiviva ajax error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
    json_error(get_string('unexpectederror', 'error'));
}

/**
 * Outputs a JSON error response and exits.
 *
 * @param string $message Error message.
 */
function json_error(string $message): never {
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Enforces a per-teacher, per-submission cooldown for expensive regen operations.
 *
 * Prevents a teacher from triggering multiple costly API calls (GPT-4o, Whisper)
 * in rapid succession for the same submission.
 *
 * @param int    $userid       Teacher's user id.
 * @param int    $submissionid Submission being regenerated.
 * @param string $operation    Short operation name for cache key namespacing.
 * @param int    $cooldownsecs Minimum seconds between calls (default 60).
 * @throws \moodle_exception if the cooldown has not yet expired.
 */
function regen_rate_check(int $userid, int $submissionid, string $operation, int $cooldownsecs = 60): void {
    $cache = \cache::make('mod_aiviva', 'ratelimit');
    $key   = 'regen_' . $operation . '_' . $userid . '_' . $submissionid;
    $last  = (int)($cache->get($key) ?: 0);
    $now   = time();
    if ($last > 0 && ($now - $last) < $cooldownsecs) {
        throw new \moodle_exception('regen_cooldown', 'mod_aiviva');
    }
    $cache->set($key, $now);
}

/**
 * Fetches or creates the current user's active submission.
 *
 * @param stdClass $aiviva  Aiviva instance.
 * @param stdClass $cm      Course module.
 * @param context  $context Module context.
 * @return stdClass Submission record.
 */
function get_or_create_submission(stdClass $aiviva, stdClass $cm, context $context): stdClass {
    global $DB, $USER;

    // Get most recent attempt.
    $attempt = (int)$DB->get_field_sql(
        'SELECT COALESCE(MAX(attempt), 0) FROM {aiviva_submissions} WHERE aiviva = ? AND userid = ?',
        [$aiviva->id, $USER->id]
    );

    if ($attempt > 0) {
        $sub = $DB->get_record('aiviva_submissions', [
            'aiviva'  => $aiviva->id,
            'userid'  => $USER->id,
            'attempt' => $attempt,
        ]);
        if ($sub && $sub->gdpr_consent) {
            return $sub;
        }
    }

    // No valid consented submission found — the user must give explicit GDPR consent.
    // On the view.php page before any AJAX upload or processing is allowed.
    throw new \moodle_exception('gdpr_consent_required', 'mod_aiviva');
}
