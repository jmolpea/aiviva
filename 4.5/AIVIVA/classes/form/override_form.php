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
 * Form for adding/editing user or group overrides.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for user/group overrides.
 */
class override_form extends \moodleform {
    /**
     * Defines the form fields.
     */
    public function definition(): void {
        global $DB;

        $mform   = $this->_form;
        $cmid    = $this->_customdata['cmid'];
        $aiviva  = $this->_customdata['aiviva'];
        $context = $this->_customdata['context'];

        $mform->addElement('hidden', 'id', 0);
        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('id', PARAM_INT);
        $mform->setType('cmid', PARAM_INT);

        // Override type.
        $types = [
            'user'  => get_string('override_type_user', 'mod_aiviva'),
            'group' => get_string('override_type_group', 'mod_aiviva'),
        ];
        $mform->addElement('select', 'overridetype', get_string('override_type', 'mod_aiviva'), $types);
        $mform->setDefault('overridetype', 'user');

        // User selector.
        $enrolledusers = get_enrolled_users($context, 'mod/aiviva:submit');
        $useroptions   = [0 => get_string('choosedots')];
        foreach ($enrolledusers as $u) {
            $useroptions[$u->id] = fullname($u) . ' (' . $u->email . ')';
        }
        $mform->addElement('select', 'userid', get_string('override_user', 'mod_aiviva'), $useroptions);
        $mform->hideIf('userid', 'overridetype', 'eq', 'group');

        // Group selector.
        $groups        = groups_get_all_groups($aiviva->course);
        $groupoptions  = [0 => get_string('choosedots')];
        foreach ($groups as $g) {
            $groupoptions[$g->id] = format_string($g->name);
        }
        $mform->addElement('select', 'groupid', get_string('override_group', 'mod_aiviva'), $groupoptions);
        $mform->hideIf('groupid', 'overridetype', 'eq', 'user');

        // Maximum attempts.
        $attemptsoptions = ['' => get_string('default')] + array_combine(range(1, 20), range(1, 20))
            + [0 => get_string('unlimited', 'mod_aiviva')];
        $mform->addElement('select', 'max_attempts', get_string('override_maxattempts', 'mod_aiviva'), $attemptsoptions);

        // Open date.
        $mform->addElement(
            'date_time_selector',
            'timeopen',
            get_string('override_timeopen', 'mod_aiviva'),
            ['optional' => true]
        );

        // Close date.
        $mform->addElement(
            'date_time_selector',
            'timeclose',
            get_string('override_timeclose', 'mod_aiviva'),
            ['optional' => true]
        );

        $this->add_action_buttons();
    }

    /**
     * Validates the submitted form data.
     *
     * @param array $data  Submitted data.
     * @param array $files Uploaded files (unused).
     * @return array Validation errors keyed by field name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if ($data['overridetype'] === 'user' && empty($data['userid'])) {
            $errors['userid'] = get_string('required');
        }
        if ($data['overridetype'] === 'group' && empty($data['groupid'])) {
            $errors['groupid'] = get_string('required');
        }

        // At least one override field must be set.
        if (($data['max_attempts'] ?? '') === '' && empty($data['timeopen']) && empty($data['timeclose'])) {
            $errors['max_attempts'] = get_string('required');
        }

        return $errors;
    }
}
