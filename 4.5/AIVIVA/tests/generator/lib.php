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
 * Test data generator for mod_aiviva.
 *
 * @package    mod_aiviva
 * @category   test
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_aiviva_generator extends testing_module_generator {
    /**
     * Creates an AI Viva activity.
     *
     * @param array|stdClass|null $record  Activity settings.
     * @param array|null          $options Course module options.
     * @return stdClass The activity record, with cmid.
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array)$record + [
            'max_attempts'            => 2,
            'timeopen'                => 0,
            'timeclose'               => 0,
            'step1_maxfilesize'       => 20,
            'step2_duration'          => 10,
            'step2_maxfilesize'       => 500,
            'step3_duration'          => 10,
            'grade'                   => 100,
            'weight_pdf'              => 33,
            'weight_video'            => 33,
            'weight_tribunal'         => 34,
            'grading_workflow'        => 0,
            'notify_student'          => 0,
            'video_purge_days'        => 15,
            'tribunal_member_1_name'  => 'Dr. Smith',
            'tribunal_member_2_name'  => 'Prof. Johnson',
            'tribunal_member_3_name'  => 'Dr. Williams',
        ];
        return parent::create_instance($record, $options);
    }

    /**
     * Creates an attempt directly in the database.
     *
     * @param array $record Must contain aiviva and userid; other fields override the defaults.
     * @return stdClass The submission record.
     */
    public function create_submission(array $record): stdClass {
        global $DB;

        $now = time();
        $record += [
            'groupid'           => 0,
            'status'            => 'draft',
            'attempt'           => 1,
            'gdpr_consent'      => 1,
            'gdpr_consent_time' => $now,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ];
        $id = $DB->insert_record('aiviva_submissions', (object)$record);
        return $DB->get_record('aiviva_submissions', ['id' => $id], '*', MUST_EXIST);
    }
}
