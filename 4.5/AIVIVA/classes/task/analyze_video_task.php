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
 * Adhoc task: analyse a submitted presentation recording.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\task;

/**
 * Runs the presentation transcription and analysis from cron when it could
 * not be completed during the upload request.
 *
 * Custom data keys:
 *  - submissionid (int) - aiviva_submissions.id
 *  - cmid         (int) - course_modules.id
 */
class analyze_video_task extends \core\task\adhoc_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_analyze_video', 'mod_aiviva');
    }

    /**
     * Executes the video analysis task.
     */
    public function execute(): void {
        global $DB;

        $data       = $this->get_custom_data();
        $submission = $DB->get_record('aiviva_submissions', ['id' => (int)($data->submissionid ?? 0)]);
        $cm         = get_coursemodule_from_id('aiviva', (int)($data->cmid ?? 0));

        // Nothing to do if the attempt was deleted or has already moved on.
        if (!$submission || !$cm || $submission->status !== 'step2') {
            return;
        }

        $aiviva  = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $update = (object)['id' => $submission->id, 'status' => 'step3', 'timemodified' => time()];
        try {
            $result = (new \mod_aiviva\api\video_analyzer())->analyse($context, $submission, $aiviva);
            $update->video_transcript = $result['transcript'];
            $update->video_analysis   = $result['analysis'];

            // While the student is still waiting, get the tribunal ready so that it starts at once.
            $submission->video_transcript = $result['transcript'];
            $submission->video_analysis   = $result['analysis'];
            \mod_aiviva\api\tribunal_conductor::prepare_ahead($aiviva, $submission, $context);
        } catch (\Throwable $e) {
            // The student is not held back; a teacher can regenerate the analysis later.
            mtrace('aiviva analyze_video_task: analysis failed for submission ' . $submission->id . ': ' . $e->getMessage());
        }

        $DB->update_record('aiviva_submissions', $update);
    }
}
