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
 * Restore structure step for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the XML structure mapping for restoring a mod_aiviva backup.
 */
class restore_aiviva_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the structure to be restored.
     *
     * @return array Array of restore_path_element objects.
     */
    protected function define_structure(): array {
        $paths   = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('aiviva', '/activity/aiviva');

        // Overrides are always restored: group overrides travel even when user
        // information is excluded from the backup. The handler drops any user
        // override whose user did not come across.
        $paths[] = new restore_path_element(
            'aiviva_override',
            '/activity/aiviva/overrides/override'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'aiviva_submission',
                '/activity/aiviva/submissions/submission'
            );
            $paths[] = new restore_path_element(
                'aiviva_tribunal_message',
                '/activity/aiviva/submissions/submission/tribunal_messages/tribunal_message'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processes a restored aiviva element.
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_aiviva(array $data): void {
        global $DB;

        $data          = (object)$data;
        $oldid         = $data->id;
        $data->course  = $this->get_courseid();

        foreach (['timeopen', 'timeclose'] as $field) {
            if (!empty($data->$field)) {
                $data->$field = $this->apply_date_offset($data->$field);
            }
        }
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated  = $this->apply_date_offset($data->timecreated);

        $newid = $DB->insert_record('aiviva', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('aiviva', $oldid, $newid);
    }

    /**
     * Processes a restored user or group override element.
     *
     * An override is dropped rather than restored when the user or group it
     * points at did not come across in this restore — writing it with a dangling
     * id would silently grant the adjustment to whoever later occupies that id.
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_aiviva_override(array $data): void {
        global $DB;

        $data         = (object)$data;
        $data->aiviva = $this->get_new_parentid('aiviva');

        if (!empty($data->userid)) {
            $newuserid = $this->get_mappingid('user', $data->userid);
            if (!$newuserid) {
                return; // User not restored — drop this override.
            }
            $data->userid = $newuserid;
        } else {
            $data->userid = null;
        }

        if (!empty($data->groupid)) {
            $newgroupid = $this->get_mappingid('group', $data->groupid);
            if (!$newgroupid) {
                return; // Group not restored — drop this override.
            }
            $data->groupid = $newgroupid;
        } else {
            $data->groupid = null;
        }

        // An override with neither a user nor a group targets nobody.
        if ($data->userid === null && $data->groupid === null) {
            return;
        }

        if (!empty($data->timeopen)) {
            $data->timeopen = $this->apply_date_offset($data->timeopen);
        }
        if (!empty($data->timeclose)) {
            $data->timeclose = $this->apply_date_offset($data->timeclose);
        }
        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        unset($data->id);
        $DB->insert_record('aiviva_overrides', $data);
    }

    /**
     * Processes a restored submission element.
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_aiviva_submission(array $data): void {
        global $DB;

        $data             = (object)$data;
        $oldid            = $data->id;
        $data->aiviva     = $this->get_new_parentid('aiviva');
        $data->userid     = $this->get_mappingid('user', $data->userid);
        // A grader of 0 means "edited by a teacher whose data has been deleted", so it is kept as 0.
        $data->grader_userid = $data->grader_userid !== null
            ? (int)$this->get_mappingid('user', $data->grader_userid)
            : null;

        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if ($data->timesubmitted) {
            $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        }
        if ($data->timegraded) {
            $data->timegraded = $this->apply_date_offset($data->timegraded);
        }

        if (!empty($data->tribunal_timestart)) {
            $data->tribunal_timestart = $this->apply_date_offset($data->tribunal_timestart);
        }

        // File ids are only used as "has a file" flags (-1 = purged); the files themselves
        // are restored by item id. The PDF id is informational and is not carried over.
        $data->pdf_fileid = null;

        $newid = $DB->insert_record('aiviva_submissions', $data);
        $this->set_mapping('aiviva_submission', $oldid, $newid, true);
    }

    /**
     * Processes a restored tribunal message element.
     *
     * @param array $data Data from the backup XML.
     */
    protected function process_aiviva_tribunal_message(array $data): void {
        global $DB;

        $data                = (object)$data;
        $data->submission_id = $this->get_new_parentid('aiviva_submission');
        $data->audio_fileid  = null;
        $data->timestamp     = $this->apply_date_offset($data->timestamp);

        $DB->insert_record('aiviva_tribunal_messages', $data);
    }

    /**
     * Adds any required post-processing steps after the structure is restored.
     */
    protected function after_execute(): void {
        // Restore files for the activity (intro, avatars).
        $this->add_related_files('mod_aiviva', 'intro', null);
        $this->add_related_files('mod_aiviva', 'avatar_custom', null);

        // Restore submission files.
        $this->add_related_files('mod_aiviva', 'submission_pdf', 'aiviva_submission');
        $this->add_related_files('mod_aiviva', 'submission_video', 'aiviva_submission');
        $this->add_related_files('mod_aiviva', 'submission_audio', 'aiviva_submission');
        $this->add_related_files('mod_aiviva', 'submission_frames', 'aiviva_submission');
        $this->add_related_files('mod_aiviva', 'tribunal_audio', 'aiviva_submission');
    }
}
