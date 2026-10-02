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
 * Adhoc task: analyse a submitted PDF.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Runs the PDF analysis from cron when it could not be completed during the
 * upload request.
 *
 * Custom data keys:
 *  - submissionid (int) - aiviva_submissions.id
 *  - cmid         (int) - course_modules.id
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
        global $DB;

        $data       = $this->get_custom_data();
        $submission = $DB->get_record('aiviva_submissions', ['id' => (int)($data->submissionid ?? 0)]);
        $cm         = get_coursemodule_from_id('aiviva', (int)($data->cmid ?? 0));

        // Nothing to do if the attempt was deleted or has already moved on.
        if (!$submission || !$cm || $submission->status !== 'step1') {
            return;
        }

        $aiviva  = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $files   = get_file_storage()->get_area_files(
            $context->id,
            'mod_aiviva',
            'submission_pdf',
            $submission->id,
            'id',
            false
        );

        $analysis = null;
        if ($files) {
            try {
                $analysis = (new \mod_aiviva\api\pdf_analyzer())->analyse(reset($files), $aiviva, (int)$submission->userid);
            } catch (\Throwable $e) {
                // The student is not held back: the evaluator still reads the PDF itself,
                // and a teacher can regenerate the analysis later.
                mtrace('aiviva analyze_pdf_task: analysis failed for submission ' . $submission->id . ': ' . $e->getMessage());
            }
        }

        $DB->update_record('aiviva_submissions', (object)[
            'id'           => $submission->id,
            'pdf_analysis' => $analysis,
            'status'       => 'step2',
            'timemodified' => time(),
        ]);
    }
}
