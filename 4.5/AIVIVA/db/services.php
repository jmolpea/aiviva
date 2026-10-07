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
 * External functions of mod_aiviva, called from its own pages through core/ajax.
 *
 * File uploads and the streamed examiner audio are not here: see ajax.php.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_aiviva_get_attempt_status' => [
        'classname'       => \mod_aiviva\external\get_attempt_status::class,
        'description'     => 'Returns the status of the current user\'s attempt.',
        'type'            => 'read',
        'ajax'            => true,
        'readonlysession' => true,
        'capabilities'    => 'mod/aiviva:submit',
    ],
    'mod_aiviva_prepare_tribunal' => [
        'classname'    => \mod_aiviva\external\prepare_tribunal::class,
        'description'  => 'Prepares the tribunal session of the current user\'s attempt before it starts.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/aiviva:submit',
    ],
    'mod_aiviva_start_tribunal' => [
        'classname'    => \mod_aiviva\external\start_tribunal::class,
        'description'  => 'Starts or resumes the tribunal session of the current user\'s attempt.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/aiviva:submit',
    ],
    'mod_aiviva_get_next_question' => [
        'classname'    => \mod_aiviva\external\get_next_question::class,
        'description'  => 'Returns the examiner\'s reply to the answer the current user has just recorded.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/aiviva:submit',
    ],
    'mod_aiviva_close_tribunal' => [
        'classname'    => \mod_aiviva\external\close_tribunal::class,
        'description'  => 'Closes the tribunal session of the current user\'s attempt and queues its evaluation.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/aiviva:submit',
    ],
    'mod_aiviva_regenerate_analysis' => [
        'classname'    => \mod_aiviva\external\regenerate_analysis::class,
        'description'  => 'Regenerates the AI analyses and the evaluation of a finished attempt.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'mod/aiviva:grade',
    ],
];
