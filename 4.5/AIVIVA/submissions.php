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
 * Teacher submissions review page for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

use mod_aiviva\local\manager;

$id           = required_param('id', PARAM_INT);  // Course module id.
$submissionid = optional_param('submissionid', 0, PARAM_INT);
$action       = optional_param('action', '', PARAM_ALPHA);

$cm     = get_coursemodule_from_id('aiviva', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aiviva:viewallsubmissions', $context);

$listurl = new moodle_url('/mod/aiviva/submissions.php', ['id' => $id]);
$cangrade = has_capability('mod/aiviva:grade', $context);

$submission = null;
if ($submissionid) {
    $submission = $DB->get_record(
        'aiviva_submissions',
        ['id' => $submissionid, 'aiviva' => $aiviva->id],
        '*',
        MUST_EXIST
    );
    if (!manager::can_review_user($cm, $context, $submission->userid)) {
        throw new required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
    }
}
$detailurl = new moodle_url($listurl, ['submissionid' => $submissionid]);

if ($action && $submission) {
    require_sesskey();
    require_capability('mod/aiviva:grade', $context);

    if ($action === 'delete') {
        $userid = $submission->userid;
        manager::delete_submission($submission, $context);
        aiviva_update_grades($aiviva, $userid);
        redirect($listurl, get_string('submission_deleted', 'mod_aiviva'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Grading actions need a finished attempt.
    if (!in_array($submission->status, ['submitted', 'grading', 'graded'])) {
        throw new moodle_exception('error_regen_not_finished', 'mod_aiviva');
    }

    if ($action === 'save' || $action === 'publish') {
        $grade    = unformat_float(required_param('grade', PARAM_RAW_TRIMMED), true);
        $feedback = required_param('feedback', PARAM_TEXT);
        if ($grade === false || $grade === null || $grade < 0 || $grade > (int)$aiviva->grade) {
            redirect(
                $detailurl,
                get_string('error_grade_range', 'mod_aiviva', (int)$aiviva->grade),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $update = (object)['id' => $submission->id, 'status' => 'graded', 'timemodified' => time()];
        $edited = $submission->final_grade === null
            || abs((float)$submission->final_grade - $grade) > 0.000005
            || trim((string)$submission->final_feedback) !== trim($feedback);
        if ($edited) {
            $update->final_grade    = $grade;
            $update->final_feedback = $feedback;
            $update->grader_userid  = $USER->id;
            $update->timegraded     = time();
        }
        $releasing = $action === 'publish' && $submission->workflow_state !== 'released';
        if ($releasing) {
            $update->workflow_state = 'released';
            $update->timegraded     = time();
        } else if (empty($submission->workflow_state)) {
            $update->workflow_state = 'inreview';
        }
        $DB->update_record('aiviva_submissions', $update);
        $submission = $DB->get_record('aiviva_submissions', ['id' => $submission->id], '*', MUST_EXIST);

        if ($submission->workflow_state === 'released') {
            aiviva_update_grades($aiviva, $submission->userid);
        }
        if ($releasing) {
            \mod_aiviva\event\grade_issued::create([
                'context'       => $context,
                'objectid'      => $submission->id,
                'relateduserid' => $submission->userid,
            ])->trigger();
            aiviva_notify_student_grade_released($aiviva, $submission, $course, $cm);
        }
        redirect(
            $detailurl,
            get_string($releasing ? 'grade_published' : 'grade_override_saved', 'mod_aiviva'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($action === 'unpublish' && $submission->workflow_state === 'released') {
        $DB->update_record('aiviva_submissions', (object)[
            'id' => $submission->id, 'workflow_state' => 'inreview', 'timemodified' => time(),
        ]);
        aiviva_update_grades($aiviva, $submission->userid);
        redirect($detailurl, get_string('grade_unpublished', 'mod_aiviva'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect($detailurl);
}

$PAGE->set_url($submission ? $detailurl : $listurl);
$PAGE->set_title(get_string('submissions_heading', 'mod_aiviva') . ': ' . format_string($aiviva->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$renderer = $PAGE->get_renderer('mod_aiviva');

/**
 * Builds a delete link that asks for confirmation in a Moodle modal.
 *
 * @param moodle_url $listurl      Base URL of this page.
 * @param int        $submissionid Submission to delete.
 * @param string     $class        CSS classes for the link.
 * @return string HTML link.
 */
function aiviva_delete_link(moodle_url $listurl, int $submissionid, string $class): string {
    $url = new moodle_url($listurl, ['submissionid' => $submissionid, 'action' => 'delete', 'sesskey' => sesskey()]);
    return html_writer::link($url, get_string('delete'), [
        'class'                                => $class,
        'data-confirmation'                    => 'modal',
        'data-confirmation-type'               => 'delete',
        'data-confirmation-title-str'          => json_encode(['delete', 'core']),
        'data-confirmation-content-str'        => json_encode(['confirm_delete_submission', 'mod_aiviva']),
        'data-confirmation-yes-button-str'     => json_encode(['delete', 'core']),
        'data-confirmation-destination'        => $url->out(false),
    ]);
}

// List of all attempts.
if (!$submission) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($aiviva->name) . ': ' . get_string('submissions_heading', 'mod_aiviva'));

    $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $sql = "SELECT s.id, s.userid, s.attempt, s.status, s.timesubmitted, s.ai_grade, s.final_grade,
                   s.workflow_state, s.grader_userid, $userfields
              FROM {aiviva_submissions} s
              JOIN {user} u ON u.id = s.userid
             WHERE s.aiviva = :aiviva
          ORDER BY u.lastname ASC, u.firstname ASC, s.attempt ASC";
    $submissions = $DB->get_records_sql($sql, ['aiviva' => $aiviva->id]);

    // Honour separate groups: staff without access to all groups only see their own groups' students.
    $reviewable = [];
    foreach ($submissions as $key => $sub) {
        $reviewable[$sub->userid] ??= manager::can_review_user($cm, $context, $sub->userid);
        if (!$reviewable[$sub->userid]) {
            unset($submissions[$key]);
        }
    }

    if (!$submissions) {
        echo $OUTPUT->notification(get_string('no_submissions_yet', 'mod_aiviva'), \core\output\notification::NOTIFY_INFO);
        echo $OUTPUT->footer();
        exit;
    }

    $table = new html_table();
    $table->head = [
        get_string('col_student', 'mod_aiviva'),
        get_string('col_attempt', 'mod_aiviva'),
        get_string('col_status', 'mod_aiviva'),
        get_string('col_submitted', 'mod_aiviva'),
        get_string('col_aigrade', 'mod_aiviva'),
        get_string('col_grade', 'mod_aiviva'),
        get_string('col_workflow', 'mod_aiviva'),
        get_string('col_actions', 'mod_aiviva'),
    ];
    $table->attributes['class'] = 'generaltable aiviva-submissions-table';

    foreach ($submissions as $sub) {
        $viewurl = new moodle_url($listurl, ['submissionid' => $sub->id]);
        $actions = html_writer::link($viewurl, get_string('view'), ['class' => 'btn btn-sm btn-outline-primary aiviva-me-1']);
        if ($cangrade) {
            $actions .= aiviva_delete_link($listurl, $sub->id, 'btn btn-sm btn-outline-danger');
        }
        $finalgrade = $sub->final_grade !== null ? format_float($sub->final_grade, 2) : '-';
        if ($sub->final_grade !== null && manager::grade_was_edited($sub)) {
            $finalgrade .= ' ' . html_writer::span(get_string('grade_edited', 'mod_aiviva'), 'badge aiviva-badge-info text-dark');
        }

        $table->data[] = [
            html_writer::link($viewurl, fullname($sub)),
            (int)$sub->attempt,
            $renderer->render_status_badge($sub->status),
            $sub->timesubmitted ? userdate($sub->timesubmitted) : '-',
            $sub->ai_grade !== null ? format_float($sub->ai_grade, 2) : '-',
            $finalgrade,
            $sub->workflow_state ? get_string('workflow_' . $sub->workflow_state, 'mod_aiviva') : '-',
            $actions,
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->footer();
    exit;
}

// Detail of one attempt.
$student = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);
if ($cangrade) {
    $PAGE->requires->js_call_amd('mod_aiviva/submissions', 'init', [[
        'cmid' => (int)$cm->id, 'submissionid' => (int)$submission->id,
    ]]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(
    fullname($student) . ' - ' . get_string('attempt_number', 'mod_aiviva', (int)$submission->attempt),
    3
);

echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo html_writer::link($listurl, get_string('back_to_submissions', 'mod_aiviva'), ['class' => 'btn btn-sm btn-outline-secondary']);
echo html_writer::div(
    $renderer->render_status_badge($submission->status) .
    ($cangrade ? ' ' . aiviva_delete_link($listurl, $submission->id, 'btn btn-sm btn-danger aiviva-ms-2') : '')
);
echo html_writer::end_div();

$fs = get_file_storage();

/**
 * Returns the pluginfile URL of a stored submission file.
 *
 * @param stored_file $file The file.
 * @return moodle_url
 */
function aiviva_stored_file_url(stored_file $file): moodle_url {
    return moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        'mod_aiviva',
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    );
}

// Step 1: document.
echo html_writer::start_div('card card-body mb-3');
echo html_writer::tag('h5', get_string('step1_title', 'mod_aiviva'));
$pdffiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_pdf', $submission->id, 'id', false);
if ($pdffiles) {
    $file = reset($pdffiles);
    echo html_writer::div(html_writer::link(
        aiviva_stored_file_url($file),
        s($file->get_filename()),
        ['target' => '_blank', 'rel' => 'noopener', 'class' => 'btn btn-outline-primary btn-sm']
    ));
} else {
    echo html_writer::div(get_string('nothing_submitted', 'mod_aiviva'), 'text-muted');
}
if (trim((string)$submission->pdf_analysis) !== '') {
    echo html_writer::tag(
        'details',
        html_writer::tag('summary', get_string('ai_analysis', 'mod_aiviva')) .
        html_writer::tag('pre', s($submission->pdf_analysis), ['class' => 'aiviva-pre']),
        ['class' => 'mt-2']
    );
}
echo html_writer::end_div();

// Step 2: presentation.
echo html_writer::start_div('card card-body mb-3');
echo html_writer::tag('h5', get_string('step2_title', 'mod_aiviva'));
$videofiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_video', $submission->id, 'id', false);
if ($videofiles) {
    echo html_writer::tag('video', '', [
        'controls' => 'controls',
        'preload'  => 'metadata',
        'src'      => aiviva_stored_file_url(reset($videofiles))->out(false),
        'class'    => 'aiviva-review-video',
    ]);
} else if ((int)$submission->video_fileid === -1) {
    echo html_writer::div(get_string('video_purged', 'mod_aiviva'), 'text-muted');
} else {
    echo html_writer::div(get_string('nothing_submitted', 'mod_aiviva'), 'text-muted');
}
if (trim((string)$submission->video_transcript) !== '') {
    echo html_writer::tag(
        'details',
        html_writer::tag('summary', get_string('presentation_transcript', 'mod_aiviva')) .
        html_writer::div(format_text($submission->video_transcript, FORMAT_PLAIN), 'aiviva-transcript-text'),
        ['class' => 'mt-2']
    );
}
if (trim((string)$submission->video_analysis) !== '') {
    echo html_writer::tag(
        'details',
        html_writer::tag('summary', get_string('ai_analysis', 'mod_aiviva')) .
        html_writer::tag('pre', s($submission->video_analysis), ['class' => 'aiviva-pre']),
        ['class' => 'mt-2']
    );
}
echo html_writer::end_div();

// Step 3: tribunal conversation, with the student's recorded answers.
$messages = $DB->get_records('aiviva_tribunal_messages', ['submission_id' => $submission->id], 'turn_number ASC, id ASC');
echo html_writer::start_div('card card-body mb-3');
echo html_writer::tag('h5', get_string('step3_title', 'mod_aiviva'));
if ($messages) {
    $audiofiles = [];
    foreach ($fs->get_area_files($context->id, 'mod_aiviva', 'tribunal_audio', $submission->id, 'id', false) as $file) {
        // Answers are named after their turn, which survives backup and restore (file ids do not).
        if (preg_match('/^answer_(\d+)\./', $file->get_filename(), $matches)) {
            $audiofiles[(int)$matches[1]] = $file;
        }
    }
    echo html_writer::start_div('tribunal-log-detail');
    foreach ($messages as $message) {
        $isstudent = $message->speaker === 'participant';
        $speaker   = $isstudent
            ? fullname($student)
            : \mod_aiviva\api\tribunal_conductor::speaker_name($aiviva, $message->speaker);
        $line = html_writer::tag('strong', s($speaker) . ': ') . s($message->message_text);
        if ($isstudent && isset($audiofiles[(int)$message->turn_number])) {
            $line .= html_writer::tag('audio', '', [
                'controls' => 'controls',
                'preload'  => 'none',
                'src'      => aiviva_stored_file_url($audiofiles[(int)$message->turn_number])->out(false),
                'class'    => 'd-block mt-1',
            ]);
        }
        echo html_writer::div($line, 'transcript-item ' . ($isstudent ? 'participant-turn' : 'tribunal-turn'));
    }
    echo html_writer::end_div();
} else {
    echo html_writer::div(get_string('nothing_submitted', 'mod_aiviva'), 'text-muted');
}
echo html_writer::end_div();

// AI evaluation.
$breakdown  = json_decode((string)$submission->grade_breakdown, true);
$evaluation = json_decode((string)$submission->tribunal_analysis, true);
if (is_array($breakdown) && $breakdown) {
    echo html_writer::start_div('card card-body mb-3');
    echo html_writer::tag('h5', get_string('ai_evaluation', 'mod_aiviva'));
    echo $renderer->render_grade_breakdown($breakdown);
    $flags = is_array($evaluation) && is_array($evaluation['academic_integrity_flags'] ?? null)
        ? array_filter(array_map('strval', $evaluation['academic_integrity_flags']))
        : [];
    if ($flags) {
        echo html_writer::div(
            html_writer::tag('strong', get_string('integrity_flags', 'mod_aiviva')) .
            html_writer::alist(array_map('s', $flags)),
            'alert alert-warning mb-0'
        );
    }
    echo html_writer::end_div();
}

if (!$cangrade) {
    echo $OUTPUT->footer();
    exit;
}

$finished = in_array($submission->status, ['submitted', 'grading', 'graded']);
if (!$finished) {
    echo $OUTPUT->notification(get_string('attempt_in_progress', 'mod_aiviva'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// Work the AI could not read: the grade is held until a teacher has looked at it.
$nodocument     = $pdffiles && trim((string)$submission->pdf_analysis) === '';
$nopresentation = !empty($submission->video_fileid) && trim((string)$submission->video_transcript) === '';
if ($nodocument || $nopresentation) {
    echo $OUTPUT->notification(get_string('evidence_missing', 'mod_aiviva'), \core\output\notification::NOTIFY_WARNING);
}

// Regenerate AI analysis.
echo html_writer::start_div('card card-body mb-3 border-warning');
echo html_writer::tag('h5', get_string('regen_heading', 'mod_aiviva'));
echo html_writer::tag('p', get_string('regen_explanation', 'mod_aiviva'), ['class' => 'text-muted small']);
echo html_writer::start_div('d-flex flex-wrap aiviva-gap-2 mb-2');
foreach (['regen_pdf', 'regen_video', 'regen_evaluation', 'regen_all'] as $regenaction) {
    echo html_writer::tag('button', get_string($regenaction, 'mod_aiviva'), [
        'type'        => 'button',
        'class'       => 'btn btn-sm aiviva-regen-btn ' . ($regenaction === 'regen_all' ? 'btn-warning' : 'btn-outline-secondary'),
        'data-action' => $regenaction,
    ]);
}
echo html_writer::end_div();
echo html_writer::div('', 'mt-2', ['id' => 'aiviva-regen-status', 'role' => 'status']);
echo html_writer::end_div();

// Grade and feedback: the AI's proposal next to the grade that counts.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $detailurl]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::start_div('card card-body mb-3');
echo html_writer::tag('h5', get_string('grading_header', 'mod_aiviva'));

$workflowlabel = $submission->workflow_state ? get_string('workflow_' . $submission->workflow_state, 'mod_aiviva') : '-';
echo html_writer::start_div('row mb-3');
echo html_writer::div(
    html_writer::div(get_string('col_aigrade', 'mod_aiviva'), 'text-muted small') .
    html_writer::div($submission->ai_grade !== null ? format_float($submission->ai_grade, 2) : '-', 'h4'),
    'col-sm-3'
);
echo html_writer::div(
    html_writer::label(get_string('final_grade', 'mod_aiviva', (int)$aiviva->grade), 'aiviva_grade_input', true, [
        'class' => 'text-muted small',
    ]) .
    html_writer::empty_tag('input', [
        'type'     => 'text',
        'id'       => 'aiviva_grade_input',
        'name'     => 'grade',
        'value'    => $submission->final_grade !== null ? format_float($submission->final_grade, 2) : '',
        'class'    => 'form-control',
        'required' => 'required',
        'inputmode' => 'decimal',
    ]),
    'col-sm-3'
);
echo html_writer::div(
    html_writer::div(get_string('col_workflow', 'mod_aiviva'), 'text-muted small') .
    html_writer::div($workflowlabel, 'h5'),
    'col-sm-3'
);
echo html_writer::end_div();

if ($submission->grader_userid) {
    $grader = $DB->get_record('user', ['id' => $submission->grader_userid]);
    if ($grader) {
        echo html_writer::div(
            get_string('grade_edited_by', 'mod_aiviva', (object)[
                'name' => fullname($grader),
                'date' => userdate($submission->timegraded),
            ]),
            'text-muted small mb-2'
        );
    }
}

echo html_writer::div(
    html_writer::label(get_string('feedback', 'mod_aiviva'), 'aiviva_feedback_input') .
    html_writer::tag('textarea', s($submission->final_feedback ?? ''), [
        'id'    => 'aiviva_feedback_input',
        'name'  => 'feedback',
        'class' => 'form-control',
        'rows'  => 10,
    ]),
    'mb-3'
);

echo html_writer::start_div('d-flex flex-wrap aiviva-gap-2');
echo html_writer::tag('button', get_string('savechanges'), [
    'type' => 'submit', 'name' => 'action', 'value' => 'save', 'class' => 'btn btn-primary',
]);
if ($submission->workflow_state !== 'released') {
    echo html_writer::tag('button', get_string('publish_grade', 'mod_aiviva'), [
        'type' => 'submit', 'name' => 'action', 'value' => 'publish', 'class' => 'btn btn-success',
    ]);
} else {
    echo html_writer::tag('button', get_string('unpublish_grade', 'mod_aiviva'), [
        'type' => 'submit', 'name' => 'action', 'value' => 'unpublish', 'class' => 'btn btn-outline-secondary',
        'formnovalidate' => 'formnovalidate',
    ]);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
