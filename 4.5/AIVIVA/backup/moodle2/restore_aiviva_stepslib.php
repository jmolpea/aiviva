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
 * @copyright  2024 AI Viva Project
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

        // Ensure the API key is blank after restore (for security — must be re-entered).
        $data->openai_apikey = '';

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated  = $this->apply_date_offset($data->timecreated);

        $newid = $DB->insert_record('aiviva', $data);
        $this->apply_activity_instance($newid);
        $this->set_mapping('aiviva', $oldid, $newid);
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
        $data->grader_userid = $data->grader_userid
            ? $this->get_mappingid('user', $data->grader_userid)
            : null;

        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        if ($data->timesubmitted) {
            $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);
        }
        if ($data->timegraded) {
            $data->timegraded = $this->apply_date_offset($data->timegraded);
        }

        // Clear file IDs — they will be re-linked via annotated files.
        $data->pdf_fileid   = null;
        $data->video_fileid = null;

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
    }
}
