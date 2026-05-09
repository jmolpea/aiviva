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
 * Redirect handler used by the gradebook to navigate to a student submission.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

$id     = required_param('id', PARAM_INT);  // Course module id.
$userid = optional_param('userid', 0, PARAM_INT);

$cm     = get_coursemodule_from_id('aiviva', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

if ($userid && $userid != $USER->id) {
    // Teacher accessing a student submission — redirect to submissions.php.
    require_capability('mod/aiviva:grade', $context);
    redirect(new moodle_url('/mod/aiviva/submissions.php', [
        'id'     => $id,
        'userid' => $userid,
    ]));
} else {
    redirect(new moodle_url('/mod/aiviva/view.php', ['id' => $id]));
}
