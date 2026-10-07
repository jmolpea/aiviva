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
use mod_aiviva\local\manager;

/**
 * Returns the state of the student's own attempt, for the waiting screens.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_attempt_status extends base {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return self::attempt_parameters();
    }

    /**
     * Returns the status of the attempt.
     *
     * @param int $cmid         Course module id.
     * @param int $submissionid Attempt id.
     * @return array {string status}
     */
    public static function execute(int $cmid, int $submissionid): array {
        ['cmid' => $cmid, 'submissionid' => $submissionid] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'submissionid' => $submissionid]
        );
        [$aiviva] = self::require_activity($cmid, 'mod/aiviva:submit');

        return ['status' => manager::require_own_submission($aiviva, $submissionid)->status];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Attempt status'),
        ]);
    }
}
