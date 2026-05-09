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
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Daily task that deletes video and audio files older than the configured
 * purge threshold, while preserving the database records.
 *
 * Files are NOT purged if:
 *  - The submission has a pending grading workflow (workflow_state != 'released').
 *  - The activity-level video_purge_days is set to 0 (purge disabled).
 */
class purge_old_files extends \core\task\scheduled_task {
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

        $fs          = get_file_storage();
        $globaldays  = (int)get_config('mod_aiviva', 'video_purge_days');
        $now         = time();
        $purged      = 0;
        $errors      = 0;

        // Iterate over all aiviva instances.
        $instances = $DB->get_records('aiviva', null, '', 'id, video_purge_days');

        foreach ($instances as $aiviva) {
            $days = (int)$aiviva->video_purge_days ?: $globaldays;
            if ($days <= 0) {
                continue; // Purge disabled for this activity.
            }
            $threshold = $now - ($days * DAYSECS);

            // Find eligible submissions: graded, released, and submitted before threshold.
            $sql = "SELECT s.id, s.video_fileid, s.aiviva
                      FROM {aiviva_submissions} s
                     WHERE s.aiviva       = :aiviva
                       AND s.video_fileid > 0
                       AND s.timesubmitted < :threshold
                       AND (s.workflow_state = 'released' OR s.workflow_state IS NULL)";
            $params = ['aiviva' => $aiviva->id, 'threshold' => $threshold];

            $submissions = $DB->get_records_sql($sql, $params);

            foreach ($submissions as $submission) {
                try {
                    $context = $this->get_context_for_submission($submission);
                    if (!$context) {
                        continue;
                    }

                    // Delete video file.
                    foreach (['submission_video', 'submission_audio'] as $filearea) {
                        $files = $fs->get_area_files($context->id, 'mod_aiviva', $filearea, $submission->id, '', false);
                        foreach ($files as $file) {
                            $file->delete();
                            $purged++;
                        }
                    }

                    // Mark as purged in DB (-1 = purged).
                    $DB->set_field('aiviva_submissions', 'video_fileid', -1, ['id' => $submission->id]);
                    $DB->set_field('aiviva_submissions', 'timemodified', $now, ['id' => $submission->id]);
                } catch (\Throwable $e) {
                    $errors++;
                    mtrace('aiviva purge error for submission ' . $submission->id . ': ' . $e->getMessage());
                }
            }
        }

        mtrace("aiviva purge_old_files: purged {$purged} file(s), {$errors} error(s).");
    }

    /**
     * Retrieves the context_module for a submission's aiviva instance.
     *
     * @param \stdClass $submission Minimal submission record (needs aiviva field).
     * @return \context_module|null Null if no course module found.
     */
    private function get_context_for_submission(\stdClass $submission): ?\context_module {
        $cm = get_coursemodule_from_instance('aiviva', $submission->aiviva);
        if (!$cm) {
            return null;
        }
        return \context_module::instance($cm->id);
    }
}
