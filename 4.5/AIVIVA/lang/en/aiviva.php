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
 * English language strings for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activityname']       = 'Activity name';

$string['aiviva:addinstance']         = 'Add an AI Viva activity';

$string['aiviva:grade']               = 'Grade submissions';

$string['aiviva:manageoverrides']     = 'Manage user and group overrides';

$string['aiviva:manageplugin']        = 'Manage plugin settings';

$string['aiviva:submit']              = 'Submit a presentation';

$string['aiviva:view']                = 'View AI Viva activity';

$string['aiviva:viewallsubmissions']  = 'View all submissions';

$string['attemptsinfo']       = 'Attempts used: {$a->used} / {$a->max} ({$a->remaining} remaining)';

$string['avatar_1']      = 'Avatar 1 (neutral)';

$string['avatar_2']      = 'Avatar 2 (feminine)';

$string['avatar_3']      = 'Avatar 3 (masculine)';

$string['avatar_custom'] = 'Custom image';

$string['backup_files']          = 'Include video/audio files (may be large)';

$string['backup_settings']       = 'Include AI Viva activity settings';

$string['backup_submissions']    = 'Include student submissions';

$string['col_actions']           = 'Actions';

$string['col_grade']             = 'Grade';

$string['col_status']            = 'Status';

$string['col_student']           = 'Student';

$string['col_submitted']         = 'Submitted';

$string['col_workflow']          = 'Workflow';

$string['completiongrade']            = 'Student must receive a grade';

$string['completionsubmit']           = 'Student must submit the activity';

$string['confirm_delete_submission'] = 'Are you sure you want to delete this submission? This cannot be undone.';

$string['confirm_pdf_upload']    = 'Are you sure your document is ready? Once submitted, it cannot be changed for this attempt.';

$string['confirm_video_submit']  = 'Submit your recording? This attempt will be final.';

$string['content_flagged']           = 'Content was flagged by the AI safety filter. Please review your submission.';

$string['continue_to_step2']    = 'Continue to Step 2 →';

$string['continue_to_step3']    = 'Continue to Step 3 →';

$string['conversation_log']      = 'Session Log';

$string['delete_submission']         = 'Delete submission';

$string['error_analysis_timeout']    = 'Analysis is taking longer than expected. Please refresh the page to check progress.';

$string['error_duration_invalid']    = 'Duration must be at least 1 minute.';

$string['error_file_too_large']      = 'File exceeds the maximum allowed size of {$a} MB.';

$string['error_maxfilesize_exceeds_global'] = 'Cannot exceed the global maximum of {$a} MB set by your site administrator.';

$string['error_maxfilesize_toosmall'] = 'Maximum file size must be at least 1 MB.';

$string['error_not_pdf']             = 'Only PDF files are accepted.';

$string['error_screen_permission']   = 'Screen recording permission was denied. Please allow screen capture and try again.';

$string['error_video_too_large']     = 'Video exceeds the maximum allowed size of {$a} MB.';

$string['evaluation_complete']   = '✅ Evaluation complete. Redirecting…';

$string['evaluation_pending']    = 'The AI panel is evaluating your performance. This may take a moment…';

$string['evaluator_invalid_response'] = 'The AI evaluator returned an invalid response. Please contact your instructor.';

$string['event_assessment_completed']  = 'AI assessment completed';

$string['event_grade_issued']          = 'Grade issued';

$string['event_submission_created']    = 'Submission created';

$string['feedback']              = 'Feedback';

$string['gdpr_consent_label']    = 'I understand and agree that my PDF, video, and audio will be processed by OpenAI\'s API.';

$string['gdpr_consent_required']      = 'You must provide GDPR consent on the activity page before uploading files.';

$string['gdpr_default_notice']   = '<p>To complete this activity, your submitted PDF document, screen recording, and spoken responses will be sent to <strong>OpenAI\'s API</strong> for analysis and evaluation.</p><p>Your personal name will be replaced with an anonymous identifier before any data is sent. Data is not retained by OpenAI beyond the immediate request. Files are automatically deleted from this server after {$a} days.</p><p>By proceeding, you consent to this processing in accordance with our privacy policy.</p>';

$string['gdpr_notice_title']     = 'Privacy Notice — AI Processing';

$string['grade_override_saved']  = 'Grade saved successfully.';

$string['grade_pending_review']  = 'Your grade is being reviewed by your instructor. You will be notified when it is released.';

$string['gradenotification_body']      = <<<'EOT'
Your grade for '{$a->activityname}' in '{$a->coursename}' has been released.

Grade: {$a->grade}

View your results: {$a->link}
EOT;

$string['gradenotification_bodyhtml']  = '<p>Your grade for <strong>{$a->activityname}</strong> in <em>{$a->coursename}</em> has been released.</p><p>Grade: <strong>{$a->grade}</strong></p><p><a href="{$a->link}">View your results</a></p>';

$string['gradenotification_small']     = 'Grade released for {$a->activityname}';

$string['gradenotification_subject']   = 'Your grade is ready: {$a->activityname}';

$string['grading_header']    = 'Grading & Workflow';

$string['grading_workflow']  = 'Enable grading workflow';

$string['grading_workflow_help'] = 'If enabled, grades are held for teacher review before being released to students.';

$string['groupsubmission']    = 'Group submission';

$string['groupsubmission_help'] = 'Allow groups to submit together. Requires groups to be configured in the course.';

$string['invalidsubmissionstatus']    = 'This action is not allowed in the current submission state.';

$string['maxattempts']        = 'Maximum attempts';

$string['maxattempts_help']   = 'Maximum number of times a student may attempt this activity. Set to 0 for unlimited.';

$string['maximumgrade']      = 'Maximum grade';

$string['model_economical']      = '(economical)';

$string['model_recommended']     = '(recommended)';

$string['modulename']        = 'AI Viva';

$string['modulenameplural']  = 'AI Vivas';

$string['no_overrides_yet']        = 'No overrides have been configured.';

$string['no_submissions_yet']    = 'No submissions yet.';

$string['noinstances']       = 'No AI Viva activities in this course.';

$string['notify_student']    = 'Notify student when grade is published';

$string['openai_api_error']          = 'AI service error: {$a}';

$string['openai_model_eval']     = 'AI model for final evaluation';

$string['openai_model_pdf']      = 'AI model for PDF analysis';

$string['openai_model_tribunal'] = 'AI model for tribunal';

$string['override_add']            = 'Add override';

$string['override_confirm_delete'] = 'Are you sure you want to delete this override?';

$string['override_delete']         = 'Delete override';

$string['override_deleted']        = 'Override deleted.';

$string['override_edit']           = 'Edit override';

$string['override_group']          = 'Group';

$string['override_maxattempts']    = 'Maximum attempts';

$string['override_saved']          = 'Override saved.';

$string['override_timeclose']      = 'Close';

$string['override_timeopen']       = 'Open';

$string['override_type']           = 'Override type';

$string['override_type_group']     = 'Group override';

$string['override_type_user']      = 'User override';

$string['override_user']           = 'User';

$string['overrides_heading']       = 'User/Group Overrides';

$string['pdf_analysis_done']     = '✅ Analysis complete. Your document is ready!';

$string['pdf_dropzone_label']    = 'Drag and drop your PDF here, or click to browse';

$string['pdf_selected']          = 'Selected: {$a->name} ({$a->size})';

$string['pdf_uploaded_analysing'] = '✅ Document received. The AI is analysing your work…';

$string['pluginadministration'] = 'AI Viva administration';

$string['pluginname']        = 'AI Viva';

$string['privacy:metadata:aiviva_submissions']                        = 'Information about each student\'s submission, including AI-generated analyses and grades.';

$string['privacy:metadata:aiviva_submissions:final_feedback']         = 'The final feedback text provided to the student.';

$string['privacy:metadata:aiviva_submissions:final_grade']            = 'The final grade awarded to the student.';

$string['privacy:metadata:aiviva_submissions:gdpr_consent']           = 'Whether the student gave GDPR consent.';

$string['privacy:metadata:aiviva_submissions:gdpr_consent_time']      = 'When the student gave GDPR consent.';

$string['privacy:metadata:aiviva_submissions:pdf_analysis']           = 'AI-generated analysis of the student\'s submitted PDF.';

$string['privacy:metadata:aiviva_submissions:status']                 = 'Current status of the submission.';

$string['privacy:metadata:aiviva_submissions:timecreated']            = 'When the submission was created.';

$string['privacy:metadata:aiviva_submissions:timesubmitted']          = 'When the submission was completed.';

$string['privacy:metadata:aiviva_submissions:tribunal_transcript']    = 'Full transcript of the AI viva tribunal session.';

$string['privacy:metadata:aiviva_submissions:userid']                 = 'The ID of the student who made the submission.';

$string['privacy:metadata:aiviva_submissions:video_analysis']         = 'AI-generated analysis of the student\'s video presentation.';

$string['privacy:metadata:aiviva_submissions:video_transcript']       = 'Whisper transcript of the student\'s video presentation.';

$string['privacy:metadata:aiviva_tribunal_messages']                  = 'Detailed log of each turn in the viva tribunal session.';

$string['privacy:metadata:aiviva_tribunal_messages:message_text']     = 'The text of what was said.';

$string['privacy:metadata:aiviva_tribunal_messages:speaker']          = 'Who spoke in this turn (tribunal member or participant).';

$string['privacy:metadata:aiviva_tribunal_messages:timestamp']        = 'When this turn occurred.';

$string['privacy:metadata:core_files']                               = 'PDF submissions, screen recordings, and tribunal audio responses are stored in the Moodle file system.';

$string['privacy:metadata:openai']                                    = 'Content is sent to OpenAI\'s API for AI analysis. Student names are anonymised before sending. Data is not retained by OpenAI beyond the immediate API request.';

$string['privacy:metadata:openai:anonymised_content']                 = 'Document or presentation content with the student\'s name replaced by an anonymised identifier.';

$string['privacy:metadata:openai:audio_transcript']                   = 'Transcript of the student\'s spoken audio, used for evaluation.';

$string['privacy:metadata:openai:conversation_turns']                 = 'The text of the student\'s spoken responses during the tribunal session.';

$string['privacy:metadata:openai:video_frames']                       = 'Still frames extracted from the student\'s screen recording, sent for visual analysis.';

$string['publish_grade']         = 'Publish grade';

$string['push_to_talk']          = 'Hold to respond';

$string['rate_limit_exceeded']       = 'You have made too many requests. Please wait a moment before trying again.';

$string['recording_started']     = '🔴 Recording — begin your presentation!';

$string['recording_time_up']     = '⏱ Time is up. Saving your presentation…';

$string['regen_all']        = 'Re-analyse everything';

$string['regen_confirm']    = 'This will replace the current AI analysis with a new one. It may take several minutes. Continue?';

$string['regen_cooldown']             = 'Please wait before regenerating again. This operation has a cooldown to prevent excessive API usage.';

$string['regen_evaluation'] = 'Recalculate final evaluation';

$string['regen_heading']    = 'Regenerate AI Analysis';

$string['regen_pdf']        = 'Re-analyse PDF (Step 1)';

$string['regen_running']    = 'Processing… please wait (may take 1–3 minutes)';

$string['regen_success']    = 'Done! Reloading…';

$string['regen_video']      = 'Re-analyse video (Step 2)';

$string['results_title']         = 'Your Results';

$string['retry_recording']       = 'Record again';

$string['return_to_student']     = 'Return for revision';

$string['safety_extra_prompt']   = 'Additional content restrictions (optional)';

$string['safety_extra_prompt_help'] = 'Any extra safety instructions appended to every API call for this activity.';

$string['security_header']       = 'Activity Security';

$string['settings_advanced_heading']        = 'Advanced';

$string['settings_anonymize_desc']          = 'Student real names are <strong>always</strong> replaced by a SHA-256 hash before sending to OpenAI. This cannot be disabled per activity.';

$string['settings_anonymize_heading']       = 'Student Anonymisation';

$string['settings_anonymize_salt']          = 'Anonymisation salt';

$string['settings_anonymize_salt_desc']     = 'Random string added to the hash. Change this to invalidate all existing anonymised IDs (do this only if required for compliance).';

$string['settings_api_rate_limit']          = 'Max API calls per user per minute';

$string['settings_api_rate_limit_desc']     = 'Rate limit per Moodle user to prevent API abuse.';

$string['settings_api_timeout']             = 'API request timeout (seconds)';

$string['settings_api_timeout_desc']        = 'Maximum time to wait for a response from OpenAI. Increase for slow connections or long transcriptions.';

$string['settings_apikeys_heading']         = 'OpenAI API Keys';

$string['settings_apikeys_heading_desc']    = 'These keys are stored encrypted. They will be used for all AI Viva activities unless overridden at the activity level.';

$string['settings_cost_estimate_desc']      = 'Estimated cost per complete student session (PDF + 10 min video + 10 min tribunal):<br/>GPT-4o: ~$0.15–$0.40 USD &nbsp;|&nbsp; GPT-4o mini: ~$0.03–$0.08 USD<br/>Whisper: ~$0.01 per minute &nbsp;|&nbsp; TTS: ~$0.01 per response';

$string['settings_cost_estimate_heading']   = 'Cost Estimates';

$string['settings_disk_warning_threshold']  = 'Disk space warning threshold (GB)';

$string['settings_disk_warning_threshold_desc'] = 'Show an admin warning when free disk space drops below this value.';

$string['settings_enable_gpt4o']            = 'Enable GPT-4o';

$string['settings_enable_gpt4o_desc']       = 'GPT-4o — highest quality, higher cost.';

$string['settings_enable_gpt4o_mini']       = 'Enable GPT-4o mini';

$string['settings_enable_gpt4o_mini_desc']  = 'GPT-4o mini — good quality, lower cost.';

$string['settings_ffmpeg_path']             = 'FFmpeg binary path';

$string['settings_ffmpeg_path_desc']        = 'Absolute path to the FFmpeg binary for server-side video frame extraction (e.g. /usr/bin/ffmpeg). Leave blank to skip server-side extraction and rely on client-side frames only. Must be an absolute path — relative paths and shell commands are rejected for security reasons.';

$string['settings_gdpr_heading']            = 'GDPR Notice';

$string['settings_gdpr_heading_desc']       = 'This notice is shown to students before they begin. They must accept it to proceed.';

$string['settings_gdpr_notice_text']        = 'GDPR notice text';

$string['settings_gdpr_notice_text_desc']   = 'HTML text shown to students. You may include links to your privacy policy.';

$string['settings_global_max_video_size']   = 'Global maximum video size (MB)';

$string['settings_global_max_video_size_desc'] = 'Individual activities cannot set a limit higher than this.';

$string['settings_models_heading']          = 'Available AI Models';

$string['settings_models_heading_desc']     = 'Select which models teachers can choose from when configuring an activity.';

$string['settings_openai_apikey']           = 'Primary OpenAI API Key';

$string['settings_openai_apikey_desc']      = 'Your OpenAI API key. Used for GPT-4o (PDF/video/tribunal), Whisper (transcription), and TTS.';

$string['settings_openai_apikey_secondary'] = 'Secondary OpenAI API Key (optional)';

$string['settings_openai_apikey_secondary_desc'] = 'If set, Whisper transcription and TTS calls will use this key.';

$string['settings_safety_content_filter']   = 'Enable OpenAI content moderation';

$string['settings_safety_content_filter_desc'] = 'Runs all user content through the OpenAI Moderation API before sending to GPT. Blocks flagged content.';

$string['settings_safety_max_tokens']       = 'Maximum tokens per API call';

$string['settings_safety_max_tokens_desc']  = 'Hard limit on output tokens for all API calls. Increase for longer analyses.';

$string['settings_security_heading']        = 'Security & Safety';

$string['settings_security_heading_desc']   = 'Configure safety filters applied to all AI calls.';

$string['settings_servertools_heading']      = 'Server tools';

$string['settings_servertools_heading_desc'] = 'Optional server-side binaries used to improve video processing.';

$string['settings_storage_heading']         = 'Storage & Retention';

$string['settings_storage_heading_desc']    = 'Configure file storage limits and automatic purge schedules.';

$string['settings_video_purge_days']        = 'Default video retention period (days)';

$string['settings_video_purge_days_desc']   = 'Videos and audio files older than this are deleted automatically. Set to 0 to disable. Individual activities can override this.';

$string['start_activity']     = 'I\'m ready to begin';

$string['start_recording']       = 'Start screen recording';

$string['step1_description']  = 'Instructions for the student';

$string['step1_description_help'] = 'Describe what PDF document the student should upload.';

$string['step1_header']       = 'Step 1 — PDF Document';

$string['step1_prompt']       = 'AI analysis prompt';

$string['step1_prompt_help']  = 'Prompt sent to the AI to analyse the student\'s PDF. The student\'s name is automatically anonymised.';

$string['step1_title']        = 'PDF Document';

$string['step2_description']  = 'Instructions for the student';

$string['step2_description_help'] = 'Describe what the student should present in their screen recording.';

$string['step2_duration']     = 'Maximum presentation duration';

$string['step2_header']       = 'Step 2 — Video Presentation';

$string['step2_maxfilesize']  = 'Maximum video file size (MB)';

$string['step2_maxfilesize_help'] = 'Cannot exceed the global maximum configured by the site administrator.';

$string['step2_prompt']       = 'AI video analysis prompt';

$string['step2_prompt_help']  = 'Prompt sent to the AI to analyse the video presentation (frames + transcript).';

$string['step2_title']        = 'Video Presentation';

$string['step3_duration']         = 'Tribunal session duration (minutes)';

$string['step3_header']           = 'Step 3 — Viva Tribunal';

$string['step3_prompt_eval']      = 'Final evaluation prompt';

$string['step3_prompt_eval_help'] = 'Instructions to the AI for generating the final grade and feedback.';

$string['step3_title']            = 'Viva Tribunal';

$string['stop_recording']        = 'Finish presentation';

$string['submission']            = 'Submission';

$string['submission_deleted']        = 'Submission deleted.';

$string['submissionnotification_body']    = <<<'EOT'
A student ({$a->studentname}) has completed '{$a->activityname}' in '{$a->coursename}' and their submission is ready for your review.

View submissions: {$a->link}
EOT;

$string['submissionnotification_bodyhtml'] = '<p>Student <strong>{$a->studentname}</strong> has completed <em>{$a->activityname}</em> and their submission is ready for review.</p><p><a href="{$a->link}">View submissions</a></p>';

$string['submissionnotification_small']   = 'New submission: {$a->activityname}';

$string['submissionnotification_subject'] = 'New submission for review: {$a->activityname}';

$string['submissions_heading']   = 'Submissions';

$string['submit_video']          = 'Submit Presentation';

$string['task_analyze_pdf']            = 'AI Viva: Analyse submitted PDF';

$string['task_analyze_video']          = 'AI Viva: Analyse video presentation';

$string['task_evaluate_submission']    = 'AI Viva: Generate final evaluation';

$string['task_purge_old_files']        = 'AI Viva: Purge old video/audio files';

$string['tribunal_ending']       = 'The session is concluding…';

$string['tribunal_finished']     = 'Tribunal session ended. Your evaluation is being prepared…';

$string['tribunal_loading']      = 'Connecting to the tribunal…';

$string['tribunal_log']          = 'Tribunal Log';

$string['tribunal_member_avatar']        = 'Avatar';

$string['tribunal_member_avatar_custom'] = 'Upload custom avatar image';

$string['tribunal_member_header']        = 'Tribunal Member {$a}';

$string['tribunal_member_name']          = 'Name';

$string['tribunal_member_prompt']        = 'Personality & examination style';

$string['tribunal_member_prompt_help']   = 'Describe how this examiner approaches questioning — tone, specialisation, rigour level.';

$string['tribunal_member_role']          = 'Role / Title';

$string['tribunal_member_voice']         = 'TTS Voice';

$string['tribunal_ready_notice'] = 'You are about to start your oral defence session. Once you press the button below, the timer will start and the panel will begin questioning you. You will not be able to go back or pause the session.';

$string['tribunal_ready_title']  = 'Defence Room — Ready to Begin?';

$string['tribunal_room_title']   = 'Defence Room — {$a}';

$string['tribunal_start_btn']    = 'Start Tribunal Session';

$string['tribunal_thinking']     = 'The panel is deliberating…';

$string['unlimited']          = 'Unlimited';

$string['upload_pdf']            = 'Upload Document';

$string['uploading_video']       = 'Uploading your recording…';

$string['video_analysis_done']   = '✅ Presentation analysed. Ready for the tribunal!';

$string['video_uploaded_analysing'] = '✅ Recording uploaded. Analysing your presentation…';

$string['voice_alloy']   = 'Alloy — versatile, neutral';

$string['voice_echo']    = 'Echo — resonant, male';

$string['voice_fable']   = 'Fable — expressive, British';

$string['voice_nova']    = 'Nova — warm, female';

$string['voice_onyx']    = 'Onyx — deep, authoritative';

$string['voice_shimmer'] = 'Shimmer — soft, clear';

$string['warning_1min']          = '⚠️ 1 minute remaining';

$string['warning_2min']          = '⚠️ 2 minutes remaining';

$string['workflow_inreview']     = 'In review';

$string['workflow_readyforrelease'] = 'Ready for release';

$string['workflow_released']     = 'Released';

$string['your_grade']            = 'Your grade:';
