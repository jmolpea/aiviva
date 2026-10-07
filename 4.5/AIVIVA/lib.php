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
 * Library functions for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Course module API.

/**
 * Adds a new instance of aiviva to the database.
 *
 * @param stdClass $data Form data.
 * @param mod_aiviva_mod_form|null $mform The form object (unused, but required by API).
 * @return int The new instance id.
 */
function aiviva_add_instance(stdClass $data, ?mod_aiviva_mod_form $mform = null): int {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = time();

    aiviva_process_form_data($data);

    $id      = $DB->insert_record('aiviva', $data);
    $data->id = $id;

    aiviva_save_avatar_files($data);
    aiviva_grade_item_update($data);

    return $id;
}

/**
 * Updates an existing instance of aiviva.
 *
 * @param stdClass $data Form data.
 * @param mod_aiviva_mod_form|null $mform The form object.
 * @return bool True on success.
 */
function aiviva_update_instance(stdClass $data, ?mod_aiviva_mod_form $mform = null): bool {
    global $DB;

    $data->id           = $data->instance;
    $data->timemodified = time();

    aiviva_process_form_data($data);

    $DB->update_record('aiviva', $data);

    aiviva_save_avatar_files($data);
    aiviva_grade_item_update($data);
    aiviva_update_grades($DB->get_record('aiviva', ['id' => $data->id], '*', MUST_EXIST));

    return true;
}

/**
 * Deletes an instance of aiviva and all associated data.
 *
 * @param int $id The instance id.
 * @return bool True on success.
 */
function aiviva_delete_instance(int $id): bool {
    global $DB;

    if (!$aiviva = $DB->get_record('aiviva', ['id' => $id])) {
        return false;
    }

    // Delete overrides.
    $DB->delete_records('aiviva_overrides', ['aiviva' => $id]);

    // Delete all submissions and their messages.
    $submissions = $DB->get_records('aiviva_submissions', ['aiviva' => $id]);
    foreach ($submissions as $submission) {
        $DB->delete_records('aiviva_tribunal_messages', ['submission_id' => $submission->id]);
    }
    $DB->delete_records('aiviva_submissions', ['aiviva' => $id]);

    // Delete grade items.
    aiviva_grade_item_delete($aiviva);

    // Delete files.
    $cm = get_coursemodule_from_instance('aiviva', $id);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_aiviva');
    }

    // Finally delete the instance.
    $DB->delete_records('aiviva', ['id' => $id]);

    return true;
}

/**
 * Pre-processes form data before saving to database.
 *
 * @param stdClass $data Form data (modified in-place).
 */
function aiviva_process_form_data(stdClass $data): void {
    // Convert editor fields to plain text storage.
    if (isset($data->step1_description_editor)) {
        $data->step1_description       = $data->step1_description_editor['text'];
        $data->step1_descriptionformat = $data->step1_description_editor['format'];
    }
    if (isset($data->step2_description_editor)) {
        $data->step2_description       = $data->step2_description_editor['text'];
        $data->step2_descriptionformat = $data->step2_description_editor['format'];
    }

    // Ensure integer defaults.
    $data->max_attempts     = isset($data->max_attempts) ? (int)$data->max_attempts : 2;
    $data->grading_workflow = isset($data->grading_workflow) ? (int)$data->grading_workflow : 1;
    $data->notify_student   = isset($data->notify_student) ? (int)$data->notify_student : 1;
    $data->video_purge_days = isset($data->video_purge_days) ? max(0, (int)$data->video_purge_days) : 15;
    $data->timeopen         = (int)($data->timeopen ?? 0);
    $data->timeclose        = (int)($data->timeclose ?? 0);
    $data->completionsubmit = (int)!empty($data->completionsubmit);

    // The draft item ids of the avatar file managers are not activity data.
    for ($member = 1; $member <= 3; $member++) {
        $field = "tribunal_member_{$member}_avatar_custom";
        if (isset($data->$field)) {
            $data->{"{$field}_draft"} = (int)$data->$field;
            $data->$field = null;
        }
    }
}

/**
 * Stores the custom avatar images uploaded in the activity form.
 *
 * @param stdClass $data Form data, after {@see aiviva_process_form_data()}.
 */
function aiviva_save_avatar_files(stdClass $data): void {
    if (empty($data->coursemodule)) {
        return;
    }
    $context = context_module::instance($data->coursemodule);
    for ($member = 1; $member <= 3; $member++) {
        $draftfield = "tribunal_member_{$member}_avatar_custom_draft";
        if (!empty($data->$draftfield)) {
            file_save_draft_area_files(
                $data->$draftfield,
                $context->id,
                'mod_aiviva',
                'avatar_custom',
                $member,
                ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
            );
        }
    }
}

/**
 * Returns the URL of a tribunal member's custom avatar image, if one was uploaded.
 *
 * @param context $context The module context.
 * @param int     $member  Member number (1-3).
 * @return moodle_url|null
 */
function aiviva_get_custom_avatar_url(context $context, int $member): ?moodle_url {
    $files = get_file_storage()->get_area_files($context->id, 'mod_aiviva', 'avatar_custom', $member, 'id', false);
    if (!$files) {
        return null;
    }
    $file = reset($files);
    return moodle_url::make_pluginfile_url(
        $context->id,
        'mod_aiviva',
        'avatar_custom',
        $member,
        $file->get_filepath(),
        $file->get_filename()
    );
}

// Gradebook integration.

/**
 * Creates or updates the grade item for an aiviva instance.
 *
 * @param stdClass $aiviva The aiviva record.
 * @param mixed    $grades Optional grades array; pass GRADE_UPDATE_ITEM_ONLY to create only.
 * @return int GRADE_UPDATE_OK on success.
 */
function aiviva_grade_item_update(stdClass $aiviva, mixed $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname'   => $aiviva->name,
        'gradetype'  => GRADE_TYPE_VALUE,
        'grademax'   => $aiviva->grade ?? 100,
        'grademin'   => 0,
    ];

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/aiviva',
        $aiviva->course,
        'mod',
        'aiviva',
        $aiviva->id,
        0,
        $grades,
        $params
    );
}

/**
 * Updates grades in the gradebook for one or all users.
 *
 * @param stdClass $aiviva  The aiviva instance.
 * @param int      $userid  User id, 0 = all.
 * @param bool     $nullifnone If true, insert null grade if none found.
 * @return void
 */
function aiviva_update_grades(stdClass $aiviva, int $userid = 0, bool $nullifnone = true): void {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($aiviva->grade == 0) {
        aiviva_grade_item_update($aiviva);
        return;
    }

    // With several attempts, the best released grade is the one that counts.
    $sql = "SELECT s.userid,
                   MAX(s.final_grade) AS rawgrade,
                   MAX(s.timegraded)  AS dategraded
              FROM {aiviva_submissions} s
             WHERE s.aiviva = :aiviva
               AND s.status = 'graded'
               AND s.workflow_state = 'released'";
    $params = ['aiviva' => $aiviva->id];

    if ($userid) {
        $sql    .= ' AND s.userid = :userid';
        $params['userid'] = $userid;
    }
    $sql .= ' GROUP BY s.userid';

    $grades = [];
    foreach ($DB->get_records_sql($sql, $params) as $row) {
        $grade             = new stdClass();
        $grade->userid     = $row->userid;
        $grade->rawgrade   = $row->rawgrade;
        $grade->dategraded = $row->dategraded;
        $grades[$row->userid] = $grade;
    }

    if (!$grades) {
        if ($nullifnone && $userid) {
            $grade           = new stdClass();
            $grade->userid   = $userid;
            $grade->rawgrade = null;
            $grades[$userid] = $grade;
        } else {
            aiviva_grade_item_update($aiviva);
            return;
        }
    }

    aiviva_grade_item_update($aiviva, $grades);
}

/**
 * Deletes the grade item from the gradebook.
 *
 * @param stdClass $aiviva The aiviva record.
 * @return int GRADE_UPDATE_OK on success.
 */
function aiviva_grade_item_delete(stdClass $aiviva): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/aiviva', $aiviva->course, 'mod', 'aiviva', $aiviva->id, 0, null, ['deleted' => 1]);
}

// Course-level listing.

/**
 * Returns course module info for display in the course listing.
 *
 * @param stdClass $coursemodule The course module object.
 * @return cached_cm_info|null The populated info object, or null on failure.
 */
function aiviva_get_coursemodule_info(stdClass $coursemodule): ?cached_cm_info {
    global $DB;

    $fields = 'id, name, intro, introformat, completionsubmit';
    if (!$aiviva = $DB->get_record('aiviva', ['id' => $coursemodule->instance], $fields)) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $aiviva->name;

    if ($coursemodule->showdescription) {
        // Show the description in the course listing.
        $info->content = format_module_intro('aiviva', $aiviva, $coursemodule->id, false);
    }

    // Populate the custom completion rules, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsubmit'] = $aiviva->completionsubmit;
    }

    return $info;
}

// File serving.

/**
 * Serves files from the mod_aiviva file areas.
 *
 * @param stdClass $course        The course record.
 * @param stdClass $cm            The course module record.
 * @param context  $context       The module context.
 * @param string   $filearea      The name of the file area.
 * @param array    $args          Extra arguments (itemid, path).
 * @param bool     $forcedownload Whether or not force download.
 * @param array    $options       Additional options affecting the file serving.
 * @return bool False if file not found; does not return if successful.
 */
function aiviva_pluginfile(
    stdClass $course,
    stdClass $cm,
    context $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
) {
    global $DB, $USER;

    require_login($course, true, $cm);

    $allowedareas = [
        'intro',
        'submission_pdf',
        'submission_video',
        'submission_audio',
        'tribunal_audio',
        'avatar_custom',
    ];

    if (!in_array($filearea, $allowedareas)) {
        return false;
    }

    // For student-uploaded files, verify capability.
    if ($filearea !== 'intro' && $filearea !== 'avatar_custom') {
        $itemid = (int)array_shift($args);

        // Check the submission belongs to this module and user has access.
        $submission = $DB->get_record('aiviva_submissions', ['id' => $itemid]);
        if (!$submission || $submission->aiviva != $cm->instance) {
            return false;
        }

        // Students can only access their own files; teachers can access all.
        if ($submission->userid != $USER->id) {
            require_capability('mod/aiviva:viewallsubmissions', $context);
            if (!\mod_aiviva\local\manager::can_review_user($cm, $context, (int)$submission->userid)) {
                return false;
            }
        }
    } else {
        $itemid = (int)array_shift($args);
    }

    $fs       = get_file_storage();
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = $fs->get_file($context->id, 'mod_aiviva', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

// Activity completion.

/**
 * Returns the custom completion rule descriptions for display in the activity settings.
 *
 * @param stdClass $cm The course module object with customdata.
 * @return array Array of completion rule descriptions keyed by rule name.
 */
function aiviva_get_completion_active_rule_descriptions(stdClass $cm): array {
    $descriptions = [];
    $aiviva = $cm->customdata['customcompletionrules'] ?? null;

    if (empty($aiviva)) {
        return $descriptions;
    }

    if (!empty($aiviva['completionsubmit'])) {
        $descriptions['completionsubmit'] = get_string('completionsubmit', 'mod_aiviva');
    }

    return $descriptions;
}

// Search support.

/**
 * Returns a list of features supported by this activity module.
 *
 * @param string $feature FEATURE_xx constant.
 * @return mixed True if feature is supported, null if unknown.
 */
function aiviva_supports(string $feature): mixed {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

// Notification helpers.

/**
 * Sends a notification to the student when their grade is published.
 *
 * @param stdClass $aiviva      The aiviva instance.
 * @param stdClass $submission  The submission record.
 * @param stdClass $course      The course record.
 * @param stdClass $cm          The course module record.
 */
function aiviva_notify_student_grade_released(
    stdClass $aiviva,
    stdClass $submission,
    stdClass $course,
    stdClass $cm
): void {
    global $DB;

    if (!$aiviva->notify_student) {
        return;
    }

    $student = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    $a                  = new stdClass();
    $a->activityname    = format_string($aiviva->name, true, ['context' => $context]);
    $a->coursename      = format_string($course->fullname, true, ['context' => $context]);
    $a->grade           = format_float($submission->final_grade, 2);
    $a->link            = new moodle_url('/mod/aiviva/view.php', ['id' => $cm->id]);

    $message                     = new \core\message\message();
    $message->component          = 'mod_aiviva';
    $message->name               = 'gradenotification';
    $message->userfrom           = core_user::get_noreply_user();
    $message->userto             = $student;
    $message->subject            = get_string('gradenotification_subject', 'mod_aiviva', $a);
    $message->fullmessage        = get_string('gradenotification_body', 'mod_aiviva', $a);
    $message->fullmessageformat  = FORMAT_PLAIN;
    $message->fullmessagehtml    = get_string('gradenotification_bodyhtml', 'mod_aiviva', $a);
    $message->smallmessage       = get_string('gradenotification_small', 'mod_aiviva', $a);
    $message->notification       = 1;
    $message->contexturl         = $a->link->out(false);
    $message->contexturlname     = $a->activityname;

    message_send($message);
}

// Navigation.

/**
 * Adds extra items to the activity's settings navigation.
 *
 * @param settings_navigation $settingsnav The settings nav tree.
 * @param navigation_node     $navref      The module navigation node.
 */
function aiviva_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $navref): void {
    global $PAGE;

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = context_module::instance($cm->id);

    $node = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$node) {
        return;
    }

    if (has_capability('mod/aiviva:viewallsubmissions', $context)) {
        $node->add(
            get_string('submissions_heading', 'mod_aiviva'),
            new moodle_url('/mod/aiviva/submissions.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'aiviva_submissions',
            new pix_icon('i/grades', '')
        );
    }

    if (has_capability('mod/aiviva:manageoverrides', $context)) {
        $node->add(
            get_string('overrides_heading', 'mod_aiviva'),
            new moodle_url('/mod/aiviva/overrides.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'aiviva_overrides',
            new pix_icon('i/user', '')
        );
    }
}

/**
 * Notifies the graders who may review the student that a submission is ready for review.
 *
 * In separate groups mode a grader is only told about students of their own groups.
 *
 * @param stdClass $aiviva     The aiviva activity instance.
 * @param stdClass $submission The student submission record.
 * @param stdClass $course     The course record.
 * @param stdClass $cm         The course module record.
 */
function aiviva_notify_teacher_submission_ready(
    stdClass $aiviva,
    stdClass $submission,
    stdClass $course,
    stdClass $cm
): void {
    global $DB;

    $context = context_module::instance($cm->id);
    $teachers = array_filter(
        get_enrolled_users($context, 'mod/aiviva:grade'),
        static fn($teacher) => \mod_aiviva\local\manager::can_review_user(
            $cm,
            $context,
            (int)$submission->userid,
            (int)$teacher->id
        )
    );

    if (!$teachers) {
        return;
    }

    $student = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);

    $a               = new stdClass();
    $a->activityname = format_string($aiviva->name, true, ['context' => $context]);
    $a->coursename   = format_string($course->fullname, true, ['context' => $context]);
    $a->studentname  = fullname($student);
    $a->link         = new moodle_url('/mod/aiviva/submissions.php', ['id' => $cm->id]);

    foreach ($teachers as $teacher) {
        $message                     = new \core\message\message();
        $message->component          = 'mod_aiviva';
        $message->name               = 'submissionnotification';
        $message->userfrom           = core_user::get_noreply_user();
        $message->userto             = $teacher;
        $message->subject            = get_string('submissionnotification_subject', 'mod_aiviva', $a);
        $message->fullmessage        = get_string('submissionnotification_body', 'mod_aiviva', $a);
        $message->fullmessageformat  = FORMAT_PLAIN;
        $message->fullmessagehtml    = get_string('submissionnotification_bodyhtml', 'mod_aiviva', $a);
        $message->smallmessage       = get_string('submissionnotification_small', 'mod_aiviva', $a);
        $message->notification       = 1;
        $message->contexturl         = $a->link->out(false);
        $message->contexturlname     = $a->activityname;

        message_send($message);
    }
}
