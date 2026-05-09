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
 * Adhoc task: transcribes and analyses a student video submission.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Background task that runs Whisper transcription + GPT-4o Vision analysis
 * on the student's recorded video presentation.
 *
 * Custom data keys:
 *  - submissionid (int)   — aiviva_submissions.id
 *  - cmid         (int)   — course_modules.id
 *  - frames       (array) — base64 JPEG frames extracted client-side
 */
class analyze_video_task extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_analyze_video', 'mod_aiviva');
    }

    /**
     * Executes the video analysis task.
     */
    public function execute(): void {
        global $DB;

        $data         = $this->get_custom_data();
        $submissionid = (int)($data->submissionid ?? 0);
        $cmid         = (int)($data->cmid ?? 0);
        $frames       = (array)($data->frames ?? []);

        if (!$submissionid || !$cmid) {
            mtrace('aiviva analyze_video_task: missing submissionid or cmid');
            return;
        }

        $submission = $DB->get_record('aiviva_submissions', ['id' => $submissionid]);
        if (!$submission) {
            mtrace('aiviva analyze_video_task: submission not found: ' . $submissionid);
            return;
        }

        $cm     = get_coursemodule_from_id('aiviva', $cmid, 0, false, MUST_EXIST);
        $aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Fetch the stored video file.
        $fs    = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_video', $submission->id, '', false);

        if (empty($files)) {
            mtrace('aiviva analyze_video_task: no video file found for submission ' . $submissionid);
            $DB->set_field('aiviva_submissions', 'status', 'step3', ['id' => $submissionid]);
            return;
        }

        $file = reset($files);

        try {
            $analyzer = new \mod_aiviva\api\video_analyzer();
            $result   = $analyzer->analyse(
                $file,
                $aiviva->step2_prompt ?? '',
                $aiviva->openai_model_tribunal ?? 'gpt-4o',
                $submission->userid,
                $frames
            );

            $DB->set_field('aiviva_submissions', 'video_transcript', $result['transcript'], ['id' => $submissionid]);
            $DB->set_field('aiviva_submissions', 'video_analysis', $result['analysis'], ['id' => $submissionid]);
            $DB->set_field('aiviva_submissions', 'status', 'step3', ['id' => $submissionid]);
            $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $submissionid]);

            mtrace('aiviva analyze_video_task: completed for submission ' . $submissionid);
        } catch (\moodle_exception $e) {
            mtrace('aiviva analyze_video_task error: ' . $e->getMessage());
            // Still advance to step3 so the student is not blocked.
            $DB->set_field('aiviva_submissions', 'status', 'step3', ['id' => $submissionid]);
            $DB->set_field(
                'aiviva_submissions',
                'video_analysis',
                'Analysis unavailable: ' . $e->getMessage(),
                ['id' => $submissionid]
            );
        }
    }
}
