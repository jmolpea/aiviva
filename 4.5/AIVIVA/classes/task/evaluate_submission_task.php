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
 * Adhoc task: produce the final evaluation of a submission.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Calls the evaluator from cron when the evaluation could not be completed
 * during the request that closed the tribunal.
 *
 * Custom data keys:
 *  - submissionid (int) - aiviva_submissions.id
 *  - cmid         (int) - course_modules.id
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
     *
     * A failure is re-thrown so that Moodle retries the task later; meanwhile
     * the attempt stays in "submitted" and a teacher can grade it by hand.
     */
    public function execute(): void {
        global $DB;

        $data       = $this->get_custom_data();
        $submission = $DB->get_record('aiviva_submissions', ['id' => (int)($data->submissionid ?? 0)]);
        $cm         = get_coursemodule_from_id('aiviva', (int)($data->cmid ?? 0));

        // Nothing to do if the attempt was deleted or has already been evaluated.
        if (!$submission || !$cm || !in_array($submission->status, ['submitted', 'grading'])) {
            return;
        }

        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

        $DB->set_field('aiviva_submissions', 'status', 'grading', ['id' => $submission->id]);
        try {
            (new \mod_aiviva\api\evaluator())->evaluate($submission, $aiviva, $course, $cm);
        } catch (\Throwable $e) {
            $DB->set_field('aiviva_submissions', 'status', 'submitted', ['id' => $submission->id]);
            throw $e;
        }
    }
}
