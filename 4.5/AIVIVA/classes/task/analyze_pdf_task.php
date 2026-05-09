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
 * Adhoc task: analyses a student PDF submission in the background.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Runs PDF analysis via OpenAI in a background Moodle adhoc task,
 * avoiding HTTP request timeouts during upload.
 *
 * Custom data keys:
 *  - submissionid (int) — aiviva_submissions.id
 *  - cmid         (int) — course_modules.id
 */
class analyze_pdf_task extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_analyze_pdf', 'mod_aiviva');
    }

    /**
     * Executes the PDF analysis task.
     */
    public function execute(): void {
        global $DB, $CFG;

        $data         = $this->get_custom_data();
        $submissionid = (int)($data->submissionid ?? 0);
        $cmid         = (int)($data->cmid ?? 0);

        if (!$submissionid || !$cmid) {
            mtrace('aiviva analyze_pdf_task: missing submissionid or cmid');
            return;
        }

        $submission = $DB->get_record('aiviva_submissions', ['id' => $submissionid]);
        if (!$submission) {
            mtrace('aiviva analyze_pdf_task: submission not found: ' . $submissionid);
            return;
        }

        $cm     = get_coursemodule_from_id('aiviva', $cmid, 0, false, MUST_EXIST);
        $aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Fetch the stored PDF file.
        $fs    = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_pdf', $submission->id, '', false);

        if (empty($files)) {
            mtrace('aiviva analyze_pdf_task: no PDF file found for submission ' . $submissionid);
            $DB->set_field('aiviva_submissions', 'status', 'step2', ['id' => $submissionid]);
            return;
        }

        $file = reset($files);

        try {
            $analyzer = new \mod_aiviva\api\pdf_analyzer();
            $analysis = $analyzer->analyse(
                $file,
                $aiviva->step1_prompt ?? '',
                $aiviva->openai_model_pdf ?? 'gpt-4o',
                $submission->userid
            );

            $DB->set_field('aiviva_submissions', 'pdf_analysis', $analysis, ['id' => $submissionid]);
            $DB->set_field('aiviva_submissions', 'status', 'step2', ['id' => $submissionid]);
            $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $submissionid]);

            mtrace('aiviva analyze_pdf_task: completed for submission ' . $submissionid);
        } catch (\moodle_exception $e) {
            mtrace('aiviva analyze_pdf_task error: ' . $e->getMessage());
            // Still advance to step2 so the student is not blocked.
            $DB->set_field('aiviva_submissions', 'status', 'step2', ['id' => $submissionid]);
            $DB->set_field(
                'aiviva_submissions',
                'pdf_analysis',
                'Analysis unavailable: ' . $e->getMessage(),
                ['id' => $submissionid]
            );
        }
    }
}
