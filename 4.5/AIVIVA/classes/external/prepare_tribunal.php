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
 * Gets the examiners' briefing and opening words ready while the student is on the ready screen.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prepare_tribunal extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return self::attempt_parameters();
    }

    /**
     * Prepares the tribunal session of the student's own attempt.
     *
     * @param int $cmid         Course module id.
     * @param int $submissionid Attempt id.
     * @return array {bool prepared}
     */
    public static function execute(int $cmid, int $submissionid): array {
        ['cmid' => $cmid, 'submissionid' => $submissionid] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'submissionid' => $submissionid]
        );
        [$aiviva, , , $context] = self::require_activity($cmid, 'mod/aiviva:submit');
        $submission = manager::require_own_submission($aiviva, $submissionid, ['step3'], true);

        self::allow_long_work(300);
        if (empty($submission->tribunal_timestart)) {
            (new tribunal_conductor($aiviva, $submission, $context))->prepare();
        }

        return ['prepared' => true];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'prepared' => new external_value(PARAM_BOOL, 'Whether the session is ready to start'),
        ]);
    }
}
