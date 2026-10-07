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

namespace mod_aiviva\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_aiviva\api\tribunal_conductor;
use mod_aiviva\local\manager;

/**
 * Closes the tribunal session once its time is up and queues the final evaluation.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class close_tribunal extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return self::attempt_parameters();
    }

    /**
     * Closes the tribunal session of the student's own attempt.
     *
     * @param int $cmid         Course module id.
     * @param int $submissionid Attempt id.
     * @return array {array turn (the closing statement, if one could be generated); string status}
     */
    public static function execute(int $cmid, int $submissionid): array {
        ['cmid' => $cmid, 'submissionid' => $submissionid] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'submissionid' => $submissionid]
        );
        [$aiviva, $course, $cm, $context] = self::require_activity($cmid, 'mod/aiviva:submit');
        $submission = manager::require_own_submission($aiviva, $submissionid);

        if (in_array($submission->status, ['submitted', 'grading', 'graded'])) {
            // Already closed (second tab, cron, repeated request): nothing left to do.
            return ['status' => $submission->status];
        }
        if ($submission->status !== 'step3') {
            throw new \moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
        }
        if (empty($submission->tribunal_timestart) || manager::tribunal_remaining($aiviva, $submission) > 15) {
            throw new \moodle_exception('error_tribunal_not_finished', 'mod_aiviva');
        }

        self::allow_long_work(300);
        $result = ['status' => 'submitted'];
        try {
            $result['turn'] = (new tribunal_conductor($aiviva, $submission, $context))->closing_statement();
        } catch (\Throwable $e) {
            debugging('aiviva: closing statement failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        if (manager::mark_submitted($submission, $course, $cm)) {
            $task = new \mod_aiviva\task\evaluate_submission_task();
            $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cm->id]);
            \core\task\manager::queue_adhoc_task($task, true);
        }

        return $result;
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'turn'   => self::turn_structure(VALUE_OPTIONAL),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Attempt status'),
        ]);
    }
}
