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
 * Scheduled task: close tribunal sessions that students walked away from.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

use mod_aiviva\local\manager;

/**
 * A tribunal whose time ran out without the browser sending the closing request
 * (tab closed, connection lost) would otherwise stay open and ungraded for ever.
 * This task submits such attempts and queues their evaluation.
 */
class close_abandoned_tribunals extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_close_abandoned_tribunals', 'mod_aiviva');
    }

    /**
     * Executes the task.
     */
    public function execute(): void {
        global $DB;

        $sql = "SELECT s.*
                  FROM {aiviva_submissions} s
                  JOIN {aiviva} a ON a.id = s.aiviva
                 WHERE s.status = :status
                   AND s.tribunal_timestart IS NOT NULL
                   AND s.tribunal_timestart + a.step3_duration * 60 + :grace < :now";
        $params = ['status' => 'step3', 'grace' => manager::TRIBUNAL_ABANDON_SECS, 'now' => time()];

        foreach ($DB->get_records_sql($sql, $params) as $submission) {
            $cm = get_coursemodule_from_instance('aiviva', $submission->aiviva);
            if (!$cm) {
                continue;
            }
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

            if (manager::mark_submitted($submission, $course, $cm)) {
                $task = new evaluate_submission_task();
                $task->set_custom_data(['submissionid' => $submission->id, 'cmid' => $cm->id]);
                \core\task\manager::queue_adhoc_task($task, true);
                mtrace('aiviva: closed abandoned tribunal for submission ' . $submission->id);
            }
        }
    }
}
