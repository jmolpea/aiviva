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
 * Scheduled task: purges old video/audio files for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Daily task that deletes recordings older than the activity's retention
 * period, while preserving the database records (transcripts, grades).
 *
 * Purged: the screen recording, its audio track and screenshots, and the
 * recorded tribunal answers. The PDF is kept, as it is the work being graded.
 *
 * Files are NOT purged if:
 *  - the attempt is finished but its grade has not been released yet;
 *  - the activity's retention period is 0 (purge disabled).
 *
 * Attempts that were abandoned before being submitted are purged too, counting
 * from their last modification.
 */
class purge_old_files extends \core\task\scheduled_task {
    /** @var string[] File areas that are emptied. */
    private const FILEAREAS = ['submission_video', 'submission_audio', 'submission_frames', 'tribunal_audio'];

    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purge_old_files', 'mod_aiviva');
    }

    /**
     * Executes the purge task.
     */
    public function execute(): void {
        global $DB;

        $fs     = get_file_storage();
        $now    = time();
        $purged = 0;

        foreach ($DB->get_records('aiviva', null, '', 'id, video_purge_days') as $aiviva) {
            $days = (int)$aiviva->video_purge_days;
            if ($days <= 0) {
                continue; // Purge disabled for this activity.
            }
            $cm = get_coursemodule_from_instance('aiviva', $aiviva->id);
            if (!$cm) {
                continue;
            }
            $context = \context_module::instance($cm->id);

            $sql = "SELECT s.id
                      FROM {aiviva_submissions} s
                     WHERE s.aiviva = :aiviva
                       AND s.video_fileid > 0
                       AND COALESCE(s.timesubmitted, s.timemodified) < :threshold
                       AND (s.workflow_state = 'released' OR s.timesubmitted IS NULL)";
            $params = ['aiviva' => $aiviva->id, 'threshold' => $now - $days * DAYSECS];

            foreach ($DB->get_records_sql($sql, $params) as $submission) {
                foreach (self::FILEAREAS as $filearea) {
                    $files = $fs->get_area_files($context->id, 'mod_aiviva', $filearea, $submission->id, 'id', false);
                    $purged += count($files);
                    $fs->delete_area_files($context->id, 'mod_aiviva', $filearea, $submission->id);
                }
                // Mark as purged in DB (-1 = purged).
                $DB->set_field('aiviva_submissions', 'video_fileid', -1, ['id' => $submission->id]);
            }
        }

        mtrace("aiviva purge_old_files: purged {$purged} file(s).");
    }
}
