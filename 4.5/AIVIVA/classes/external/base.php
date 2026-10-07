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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * What the external functions of mod_aiviva have in common.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base extends external_api {
    /**
     * Loads the activity and checks that the current user may act on it.
     *
     * @param int    $cmid       Course module id.
     * @param string $capability Capability the action needs.
     * @return array [activity record, course record, course module record, module context]
     * @throws \moodle_exception if the user has no access or the plugin has no valid licence.
     */
    protected static function require_activity(int $cmid, string $capability): array {
        global $DB;

        $cm      = get_coursemodule_from_id('aiviva', $cmid, 0, false, MUST_EXIST);
        $course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $aiviva  = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability($capability, $context);

        // License gate: block every AI action when no valid key is bound to this site.
        if (!\mod_aiviva\license\validator::is_valid()) {
            throw new \moodle_exception('error_nolicense', 'mod_aiviva');
        }

        return [$aiviva, $course, $cm, $context];
    }

    /**
     * Gets the request ready for AI work that can take a long time: other requests
     * of the same user (the status poll, the examiner's audio) must not wait for it.
     *
     * @param int $seconds Time the work may take.
     */
    protected static function allow_long_work(int $seconds): void {
        \core\session\manager::write_close();
        \core_php_time_limit::raise($seconds);
    }

    /**
     * Parameters of every function that acts on one attempt.
     *
     * @param array $extra Further parameters.
     * @return external_function_parameters
     */
    protected static function attempt_parameters(array $extra = []): external_function_parameters {
        return new external_function_parameters([
            'cmid'         => new external_value(PARAM_INT, 'Course module id'),
            'submissionid' => new external_value(PARAM_INT, 'Attempt id'),
        ] + $extra);
    }

    /**
     * Describes an examiner's turn.
     *
     * @param int $required VALUE_REQUIRED, or VALUE_OPTIONAL if there may be no turn.
     * @return external_single_structure
     */
    protected static function turn_structure(int $required = VALUE_REQUIRED): external_single_structure {
        return new external_single_structure([
            'member' => new external_value(PARAM_INT, 'Examiner number (1-3)'),
            'name'   => new external_value(PARAM_RAW, 'Examiner name'),
            'text'   => new external_value(PARAM_RAW, 'What the examiner says'),
            'turn'   => new external_value(PARAM_INT, 'Turn number, used to request the audio'),
        ], 'An examiner\'s turn', $required);
    }
}
