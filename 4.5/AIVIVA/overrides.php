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
 * User/group overrides management page for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

$id         = required_param('id', PARAM_INT);  // Course module id.
$overrideid = optional_param('overrideid', 0, PARAM_INT);
$action     = optional_param('action', '', PARAM_ALPHA);

$cm     = get_coursemodule_from_id('aiviva', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aiviva:manageoverrides', $context);

$baseurl = new moodle_url('/mod/aiviva/overrides.php', ['id' => $id]);

// Delete action.
if ($action === 'delete' && $overrideid) {
    require_sesskey();
    $DB->delete_records('aiviva_overrides', ['id' => $overrideid, 'aiviva' => $aiviva->id]);
    redirect(
        $baseurl,
        get_string('override_deleted', 'mod_aiviva'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Add/Edit form.
if ($action === 'add' || ($action === 'edit' && $overrideid)) {
    require_once($CFG->dirroot . '/mod/aiviva/classes/form/override_form.php');

    $formurl = new moodle_url(
        '/mod/aiviva/overrides.php',
        ['id' => $id, 'action' => $action, 'overrideid' => $overrideid]
    );
    $PAGE->set_url($formurl);
    $PAGE->set_title(get_string('overrides_heading', 'mod_aiviva') . ': ' . format_string($aiviva->name));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);

    $override = null;
    if ($action === 'edit' && $overrideid) {
        $override = $DB->get_record(
            'aiviva_overrides',
            ['id' => $overrideid, 'aiviva' => $aiviva->id],
            '*',
            MUST_EXIST
        );
    }

    $form = new \mod_aiviva\form\override_form($formurl, [
        'cmid'    => $cm->id,
        'aiviva'  => $aiviva,
        'context' => $context,
    ]);

    if ($override) {
        $formdata = clone $override;
        unset($formdata->id);
        $formdata->overridetype = $override->userid ? 'user' : 'group';
        $form->set_data($formdata);
    }

    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        $record               = new stdClass();
        $record->aiviva       = $aiviva->id;
        $record->userid       = ($data->overridetype === 'user') ? (int)$data->userid : null;
        $record->groupid      = ($data->overridetype === 'group') ? (int)$data->groupid : null;
        $record->max_attempts = ($data->max_attempts !== '') ? (int)$data->max_attempts : null;
        $record->timeopen     = !empty($data->timeopen) ? (int)$data->timeopen : null;
        $record->timeclose    = !empty($data->timeclose) ? (int)$data->timeclose : null;
        $record->timemodified = time();

        if ($action === 'edit' && $overrideid) {
            $record->id = $overrideid;
            $DB->update_record('aiviva_overrides', $record);
        } else {
            // One override per user or group: adding a second one replaces the first.
            $target   = $record->userid ? ['userid' => $record->userid] : ['groupid' => $record->groupid];
            $existing = $DB->get_record('aiviva_overrides', ['aiviva' => $aiviva->id] + $target, 'id', IGNORE_MULTIPLE);
            if ($existing) {
                $record->id = $existing->id;
                $DB->update_record('aiviva_overrides', $record);
            } else {
                $record->timecreated = time();
                $DB->insert_record('aiviva_overrides', $record);
            }
        }

        redirect(
            $baseurl,
            get_string('override_saved', 'mod_aiviva'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($aiviva->name) . ': ' .
        get_string($action === 'edit' ? 'override_edit' : 'override_add', 'mod_aiviva'));
    echo html_writer::link(
        $baseurl,
        get_string('overrides_heading', 'mod_aiviva'),
        ['class' => 'btn btn-sm btn-outline-secondary mb-3']
    );
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// List all overrides.
$PAGE->set_url('/mod/aiviva/overrides.php', ['id' => $id]);
$PAGE->set_title(get_string('overrides_heading', 'mod_aiviva') . ': ' . format_string($aiviva->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($aiviva->name) . ': ' . get_string('overrides_heading', 'mod_aiviva'));

echo html_writer::link(
    new moodle_url('/mod/aiviva/overrides.php', ['id' => $id, 'action' => 'add']),
    get_string('override_add', 'mod_aiviva'),
    ['class' => 'btn btn-primary mb-3']
);

$overrides = $DB->get_records('aiviva_overrides', ['aiviva' => $aiviva->id], 'timecreated ASC');

if (empty($overrides)) {
    echo $OUTPUT->notification(get_string('no_overrides_yet', 'mod_aiviva'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table             = new html_table();
$table->head       = [
    get_string('override_type', 'mod_aiviva'),
    get_string('override_user', 'mod_aiviva') . ' / ' . get_string('override_group', 'mod_aiviva'),
    get_string('override_maxattempts', 'mod_aiviva'),
    get_string('override_timeopen', 'mod_aiviva'),
    get_string('override_timeclose', 'mod_aiviva'),
    get_string('col_actions', 'mod_aiviva'),
];
$table->attributes['class'] = 'generaltable aiviva-overrides-table';

foreach ($overrides as $ov) {
    if ($ov->userid) {
        $typestr = get_string('override_type_user', 'mod_aiviva');
        $who     = '';
        $user    = $DB->get_record('user', ['id' => $ov->userid]);
        if ($user) {
            $who = fullname($user);
        }
    } else {
        $typestr = get_string('override_type_group', 'mod_aiviva');
        $who     = '';
        $group   = $DB->get_record('groups', ['id' => $ov->groupid]);
        if ($group) {
            $who = format_string($group->name);
        }
    }

    $maxattempts = $ov->max_attempts !== null ? $ov->max_attempts : get_string('default');
    $timeopen    = $ov->timeopen ? userdate($ov->timeopen) : '—';
    $timeclose   = $ov->timeclose ? userdate($ov->timeclose) : '—';

    $editurl   = new moodle_url(
        '/mod/aiviva/overrides.php',
        ['id' => $id, 'action' => 'edit', 'overrideid' => $ov->id]
    );
    $deleteurl = new moodle_url(
        '/mod/aiviva/overrides.php',
        ['id' => $id, 'action' => 'delete', 'overrideid' => $ov->id, 'sesskey' => sesskey()]
    );

    $actions = html_writer::link($editurl, get_string('edit'), ['class' => 'btn btn-sm btn-outline-primary aiviva-me-1']) .
               html_writer::link($deleteurl, get_string('delete'), [
                   'class'                            => 'btn btn-sm btn-outline-danger',
                   'data-confirmation'                => 'modal',
                   'data-confirmation-type'           => 'delete',
                   'data-confirmation-title-str'      => json_encode(['delete', 'core']),
                   'data-confirmation-content-str'    => json_encode(['override_confirm_delete', 'mod_aiviva']),
                   'data-confirmation-yes-button-str' => json_encode(['delete', 'core']),
                   'data-confirmation-destination'    => $deleteurl->out(false),
               ]);

    $table->data[] = [$typestr, $who, $maxattempts, $timeopen, $timeclose, $actions];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
