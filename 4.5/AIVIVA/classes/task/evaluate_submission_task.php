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
 * Adhoc task: generates the final AI evaluation for a completed submission.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Background task that calls the evaluator to produce the final grade
 * and feedback after the tribunal session ends.
 *
 * Custom data keys:
 *  - submissionid (int) — aiviva_submissions.id
 *  - cmid         (int) — course_modules.id
 */
class evaluate_submission_task extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_evaluate_submission', 'mod_aiviva');
    }

    /**
     * Executes the evaluation task.
     */
    public function execute(): void {
        global $DB;

        $data         = $this->get_custom_data();
        $submissionid = (int)($data->submissionid ?? 0);
        $cmid         = (int)($data->cmid ?? 0);

        if (!$submissionid || !$cmid) {
            mtrace('aiviva evaluate_submission_task: missing submissionid or cmid');
            return;
        }

        $submission = $DB->get_record('aiviva_submissions', ['id' => $submissionid]);
        if (!$submission) {
            mtrace('aiviva evaluate_submission_task: submission not found: ' . $submissionid);
            return;
        }

        $cm     = get_coursemodule_from_id('aiviva', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

        // Set status to 'grading' so the student sees the pending state.
        $DB->set_field('aiviva_submissions', 'status', 'grading', ['id' => $submissionid]);

        try {
            $evaluator = new \mod_aiviva\api\evaluator();
            $evaluator->evaluate($submission, $aiviva, $course, $cm);

            mtrace('aiviva evaluate_submission_task: completed for submission ' . $submissionid);
        } catch (\moodle_exception $e) {
            mtrace('aiviva evaluate_submission_task error: ' . $e->getMessage());
            // Revert to submitted so the teacher can manually grade.
            $DB->set_field('aiviva_submissions', 'status', 'submitted', ['id' => $submissionid]);
        }
    }
}
