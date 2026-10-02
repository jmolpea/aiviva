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
 * Backup structure step for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the XML structure for a mod_aiviva backup.
 *
 * API keys are intentionally excluded from the backup for security reasons.
 */
class backup_aiviva_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure.
     *
     * @return backup_nested_element The root element.
     */
    protected function define_structure(): backup_nested_element {
        $includesubmissions = $this->get_setting_value('userinfo');

        // Root element — activity settings (no API keys).
        $aiviva = new backup_nested_element('aiviva', ['id'], [
            'name', 'intro', 'introformat',
            'openai_model_pdf', 'openai_model_tribunal', 'openai_model_eval',
            'max_attempts', 'timeopen', 'timeclose', 'step1_maxfilesize',
            'step1_description', 'step1_descriptionformat', 'step1_prompt',
            'step2_description', 'step2_descriptionformat', 'step2_prompt',
            'step2_duration', 'step2_maxfilesize',
            'step3_duration', 'step3_prompt_eval',
            'tribunal_member_1_name', 'tribunal_member_1_role', 'tribunal_member_1_prompt',
            'tribunal_member_1_voice', 'tribunal_member_1_avatar',
            'tribunal_member_2_name', 'tribunal_member_2_role', 'tribunal_member_2_prompt',
            'tribunal_member_2_voice', 'tribunal_member_2_avatar',
            'tribunal_member_3_name', 'tribunal_member_3_role', 'tribunal_member_3_prompt',
            'tribunal_member_3_voice', 'tribunal_member_3_avatar',
            'grading_workflow',
            'notify_student', 'video_purge_days',
            'safety_max_tokens', 'safety_content_filter', 'safety_extra_prompt',
            'grade', 'weight_pdf', 'weight_video', 'weight_tribunal', 'completionsubmit',
            'timecreated', 'timemodified',
        ]);

        // Student submissions (optional, requires userinfo setting).
        $submissions = new backup_nested_element('submissions');
        $submission  = new backup_nested_element('submission', ['id'], [
            'userid', 'groupid', 'status', 'attempt',
            'gdpr_consent', 'gdpr_consent_time',
            'pdf_fileid', 'pdf_analysis',
            'video_fileid', 'video_transcript', 'video_analysis',
            'tribunal_transcript', 'tribunal_analysis', 'tribunal_briefing', 'tribunal_timestart',
            'ai_grade', 'final_grade', 'final_feedback', 'grade_breakdown',
            'grader_userid', 'workflow_state',
            'timecreated', 'timemodified', 'timesubmitted', 'timegraded',
        ]);

        $messages  = new backup_nested_element('tribunal_messages');
        $message   = new backup_nested_element('tribunal_message', ['id'], [
            'turn_number', 'speaker', 'message_text', 'audio_fileid', 'timestamp',
        ]);

        // User and group overrides (extra attempts, extended open/close dates).
        // These are typically accessibility adjustments, so losing them on restore
        // is a real data loss, not a cosmetic one.
        $overrides = new backup_nested_element('overrides');
        $override  = new backup_nested_element('override', ['id'], [
            'userid', 'groupid', 'max_attempts',
            'timeopen', 'timeclose',
            'timecreated', 'timemodified',
        ]);

        // Build the tree.
        $aiviva->add_child($submissions);
        $submissions->add_child($submission);
        $submission->add_child($messages);
        $messages->add_child($message);
        $aiviva->add_child($overrides);
        $overrides->add_child($override);

        // Data sources.
        $aiviva->set_source_table('aiviva', ['id' => backup::VAR_ACTIVITYID]);

        // Group overrides are not user data, so they travel even when the user
        // information setting is off. User overrides only travel with user data.
        // Same approach as mod_quiz.
        if ($includesubmissions) {
            $override->set_source_table('aiviva_overrides', ['aiviva' => backup::VAR_PARENTID]);
        } else {
            $override->set_source_sql(
                'SELECT * FROM {aiviva_overrides} WHERE aiviva = ? AND userid IS NULL',
                [backup::VAR_PARENTID]
            );
        }

        $override->annotate_ids('user', 'userid');
        $override->annotate_ids('group', 'groupid');

        if ($includesubmissions) {
            $submission->set_source_table('aiviva_submissions', ['aiviva' => backup::VAR_PARENTID]);
            $message->set_source_table('aiviva_tribunal_messages', ['submission_id' => backup::VAR_PARENTID]);
            $submission->annotate_ids('user', 'userid');
            $submission->annotate_ids('user', 'grader_userid');
        }

        // File annotations for the activity.
        $aiviva->annotate_files('mod_aiviva', 'intro', null);
        $aiviva->annotate_files('mod_aiviva', 'avatar_custom', null);

        if ($includesubmissions) {
            $submission->annotate_files('mod_aiviva', 'submission_pdf', 'id');
            $submission->annotate_files('mod_aiviva', 'submission_video', 'id');
            $submission->annotate_files('mod_aiviva', 'submission_audio', 'id');
            $submission->annotate_files('mod_aiviva', 'submission_frames', 'id');
            $submission->annotate_files('mod_aiviva', 'tribunal_audio', 'id');
        }

        return $this->prepare_activity_structure($aiviva);
    }
}
