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
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

$id           = required_param('id', PARAM_INT);  // Course module id.
$userid       = optional_param('userid', 0, PARAM_INT);
$action       = optional_param('action', '', PARAM_ALPHA);
$submissionid = optional_param('submissionid', 0, PARAM_INT);

$cm     = get_coursemodule_from_id('aiviva', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$aiviva = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/aiviva:viewallsubmissions', $context);

// Handle delete action (uses submissionid, not userid).
if ($action === 'delete' && $submissionid) {
    require_sesskey();
    require_capability('mod/aiviva:grade', $context);
    $sub = $DB->get_record(
        'aiviva_submissions',
        ['id' => $submissionid, 'aiviva' => $aiviva->id],
        '*',
        MUST_EXIST
    );
    $DB->delete_records('aiviva_tribunal_messages', ['submission_id' => $sub->id]);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_aiviva', 'submission_pdf', $sub->id);
    $fs->delete_area_files($context->id, 'mod_aiviva', 'submission_video', $sub->id);
    $fs->delete_area_files($context->id, 'mod_aiviva', 'submission_audio', $sub->id);
    $DB->delete_records('aiviva_submissions', ['id' => $sub->id]);
    redirect(
        new moodle_url('/mod/aiviva/submissions.php', ['id' => $id]),
        get_string('submission_deleted', 'mod_aiviva'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Handle workflow/grade actions.
if ($action && $userid) {
    require_sesskey();
    require_capability('mod/aiviva:grade', $context);

    $submission = $DB->get_record(
        'aiviva_submissions',
        ['aiviva' => $aiviva->id, 'userid' => $userid],
        '*',
        MUST_EXIST
    );

    if ($action === 'publish') {
        $DB->set_field('aiviva_submissions', 'workflow_state', 'released', ['id' => $submission->id]);
        $DB->set_field('aiviva_submissions', 'grader_userid', $USER->id, ['id' => $submission->id]);
        $DB->set_field('aiviva_submissions', 'timegraded', time(), ['id' => $submission->id]);
        $submission->workflow_state = 'released';
        aiviva_update_grades($aiviva, $userid);
        aiviva_notify_student_grade_released($aiviva, $submission, $course, $cm);
    } else if ($action === 'return') {
        $DB->set_field('aiviva_submissions', 'workflow_state', 'inreview', ['id' => $submission->id]);
    } else if ($action === 'savegarde') {
        $newgrade    = required_param('grade', PARAM_FLOAT);
        $newfeedback = optional_param('feedback', '', PARAM_RAW);
        $DB->set_field('aiviva_submissions', 'final_grade', $newgrade, ['id' => $submission->id]);
        $DB->set_field('aiviva_submissions', 'final_feedback', $newfeedback, ['id' => $submission->id]);
        $DB->set_field('aiviva_submissions', 'grader_userid', $USER->id, ['id' => $submission->id]);
        $DB->set_field('aiviva_submissions', 'timemodified', time(), ['id' => $submission->id]);
        redirect(
            new moodle_url('/mod/aiviva/submissions.php', ['id' => $id, 'userid' => $userid]),
            get_string('grade_override_saved', 'mod_aiviva'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    redirect(new moodle_url('/mod/aiviva/submissions.php', ['id' => $id, 'userid' => $userid]));
}

$PAGE->set_url('/mod/aiviva/submissions.php', ['id' => $id]);
$PAGE->set_title(get_string('submissions_heading', 'mod_aiviva') . ': ' . format_string($aiviva->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/aiviva/styles.css');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($aiviva->name) . ' — ' . get_string('submissions_heading', 'mod_aiviva'));

// Detail view for a single submission.
if ($userid) {
    render_submission_detail($aiviva, $userid, $id, $context, $cm, $course);
    echo $OUTPUT->footer();
    exit;
}

// Summary table of all submissions.
$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$sql = "SELECT s.*, $userfields, u.email
          FROM {aiviva_submissions} s
          JOIN {user} u ON u.id = s.userid
         WHERE s.aiviva = :aiviva
      ORDER BY u.lastname ASC, u.firstname ASC, s.attempt ASC";
$submissions = $DB->get_records_sql($sql, ['aiviva' => $aiviva->id]);

if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('no_submissions_yet', 'mod_aiviva'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Table output.
$table = new html_table();
$table->head = [
    get_string('col_student', 'mod_aiviva'),
    get_string('col_status', 'mod_aiviva'),
    get_string('col_submitted', 'mod_aiviva'),
    get_string('col_grade', 'mod_aiviva'),
    get_string('col_workflow', 'mod_aiviva'),
    get_string('col_actions', 'mod_aiviva'),
];
$table->attributes['class'] = 'generaltable aiviva-submissions-table';

foreach ($submissions as $sub) {
    $studentlink = html_writer::link(
        new moodle_url('/mod/aiviva/submissions.php', ['id' => $id, 'userid' => $sub->userid]),
        fullname($sub)
    );
    $submitted  = $sub->timesubmitted ? userdate($sub->timesubmitted) : '—';
    $grade      = isset($sub->final_grade) ? format_float($sub->final_grade, 2) : '—';
    $workflowbadge = '';
    if ($sub->workflow_state) {
        $workflowbadge = html_writer::span(
            get_string('workflow_' . $sub->workflow_state, 'mod_aiviva'),
            'badge badge-' . $sub->workflow_state
        );
    }

    $deleteurl = new moodle_url('/mod/aiviva/submissions.php', [
        'id' => $id, 'submissionid' => $sub->id, 'action' => 'delete', 'sesskey' => sesskey(),
    ]);
    $actions = html_writer::link(
        new moodle_url('/mod/aiviva/submissions.php', ['id' => $id, 'userid' => $sub->userid]),
        get_string('view'),
        ['class' => 'btn btn-sm btn-outline-primary me-1']
    );
    if (has_capability('mod/aiviva:grade', $context)) {
        $actions .= html_writer::link(
            $deleteurl,
            get_string('delete'),
            [
                'class'   => 'btn btn-sm btn-outline-danger',
                'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete_submission', 'mod_aiviva')) . ');',
            ]
        );
    }

    $table->data[] = [
        $studentlink,
        s($sub->status),
        $submitted,
        $grade,
        $workflowbadge,
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();

/**
 * Renders the detailed view for a single student submission.
 *
 * @param stdClass $aiviva     Aiviva instance.
 * @param int      $userid     Student user id.
 * @param int      $cmid       Course module id.
 * @param context  $context    Module context.
 * @param stdClass $cm         Course module record.
 * @param stdClass $course     Course record.
 */
function render_submission_detail(
    stdClass $aiviva,
    int $userid,
    int $cmid,
    context $context,
    stdClass $cm,
    stdClass $course
): void {
    global $DB, $OUTPUT, $USER;

    $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $submission = $DB->get_record(
        'aiviva_submissions',
        ['aiviva' => $aiviva->id, 'userid' => $userid],
        '*',
        MUST_EXIST
    );

    echo $OUTPUT->heading(fullname($student), 3);

    // Breadcrumb + delete button.
    echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
    echo html_writer::link(
        new moodle_url('/mod/aiviva/submissions.php', ['id' => $cmid]),
        '← ' . get_string('submissions_heading', 'mod_aiviva'),
        ['class' => 'btn btn-sm btn-outline-secondary']
    );
    if (has_capability('mod/aiviva:grade', $context)) {
        $deleteurl = new moodle_url('/mod/aiviva/submissions.php', [
            'id'           => $cmid,
            'submissionid' => $submission->id,
            'action'       => 'delete',
            'sesskey'      => sesskey(),
        ]);
        echo html_writer::link(
            $deleteurl,
            get_string('delete_submission', 'mod_aiviva'),
            [
                'class'   => 'btn btn-sm btn-danger',
                'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete_submission', 'mod_aiviva')) . ');',
            ]
        );
    }
    echo html_writer::end_div();

    // PDF.
    if ($submission->pdf_fileid) {
        $fs   = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_pdf', $submission->id, '', false);
        if ($files) {
            $file = reset($files);
            $url  = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_aiviva',
                'submission_pdf',
                $submission->id,
                '/',
                $file->get_filename()
            );
            echo html_writer::div(
                html_writer::tag('h5', get_string('step1_title', 'mod_aiviva')) .
                html_writer::link($url, $file->get_filename(), ['target' => '_blank', 'class' => 'btn btn-outline-primary btn-sm']),
                'card card-body mb-3'
            );
        }
    }

    // Video player.
    if ($submission->video_fileid && $submission->video_fileid > 0) {
        $fs    = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_video', $submission->id, '', false);
        if ($files) {
            $file = reset($files);
            $url  = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_aiviva',
                'submission_video',
                $submission->id,
                '/',
                $file->get_filename()
            );
            echo html_writer::div(
                html_writer::tag('h5', get_string('step2_title', 'mod_aiviva')) .
                html_writer::tag('video', '', ['controls' => 'controls', 'src' => $url->out(false), 'style' => 'max-width:100%']),
                'card card-body mb-3'
            );
        }
    } else if ($submission->video_fileid === -1) {
        echo html_writer::div(
            html_writer::tag('h5', get_string('step2_title', 'mod_aiviva')) .
            html_writer::div(html_writer::tag('em', 'Video has been automatically purged.'), 'text-muted'),
            'card card-body mb-3'
        );
    }

    // Tribunal transcript.
    if ($submission->tribunal_transcript) {
        $transcript = json_decode($submission->tribunal_transcript, true);
        if ($transcript) {
            echo html_writer::start_div('card card-body mb-3');
            echo html_writer::tag('h5', get_string('step3_title', 'mod_aiviva'));
            echo html_writer::start_div('tribunal-log-detail');
            foreach ($transcript as $turn) {
                $speaker  = s($turn['speaker'] ?? '');
                $text     = s($turn['text'] ?? '');
                $cssclass = ($turn['speaker'] === 'participant') ? 'participant-turn' : 'tribunal-turn';
                echo html_writer::div(
                    html_writer::tag('strong', $speaker . ': ') . $text,
                    'transcript-item ' . $cssclass
                );
            }
            echo html_writer::end_div();
            echo html_writer::end_div();
        }
    }

    // Grade breakdown.
    if ($submission->grade_breakdown) {
        $breakdown = json_decode($submission->grade_breakdown, true);
        if ($breakdown) {
            echo html_writer::start_div('card card-body mb-3');
            echo html_writer::tag('h5', 'AI Evaluation Breakdown');
            foreach ($breakdown as $step => $data) {
                echo html_writer::div(
                    html_writer::tag('strong', ucfirst(str_replace('_', ' ', $step))) .
                    ' — Score: ' . (int)($data['score'] ?? 0) . '/100' .
                    ' (weight: ' . (float)($data['weight'] ?? 0) . ')<br/>' .
                    s($data['feedback'] ?? ''),
                    'mb-2'
                );
            }
            echo html_writer::end_div();
        }
    }

    // Regenerate AI analysis section.
    if (has_capability('mod/aiviva:grade', $context)) {
        $ajaxurl  = (new moodle_url('/mod/aiviva/ajax.php'))->out(false);
        $sesskey  = sesskey();
        $confirmmsg  = get_string('regen_confirm', 'mod_aiviva');
        $runningmsg  = get_string('regen_running', 'mod_aiviva');
        $successmsg  = get_string('regen_success', 'mod_aiviva');

        echo html_writer::start_div('card card-body mb-3 border-warning');
        echo html_writer::tag('h5', get_string('regen_heading', 'mod_aiviva'));
        echo html_writer::start_div('d-flex flex-wrap gap-2 mb-2');

        $buttons = [
            ['action' => 'regen_pdf', 'label' => get_string('regen_pdf', 'mod_aiviva'), 'class' => 'btn-outline-secondary'],
            ['action' => 'regen_video', 'label' => get_string('regen_video', 'mod_aiviva'), 'class' => 'btn-outline-secondary'],
            ['action' => 'regen_evaluation',
                'label' => get_string('regen_evaluation', 'mod_aiviva'), 'class' => 'btn-outline-secondary'],
            ['action' => 'regen_all', 'label' => get_string('regen_all', 'mod_aiviva'), 'class' => 'btn-warning'],
        ];
        foreach ($buttons as $btn) {
            echo html_writer::tag('button', $btn['label'], [
                'type'             => 'button',
                'class'            => 'btn btn-sm ' . $btn['class'] . ' aiviva-regen-btn',
                'data-action'      => $btn['action'],
                'data-submissionid' => $submission->id,
                'data-cmid'        => $cmid,
                'data-sesskey'     => $sesskey,
                'data-ajaxurl'     => $ajaxurl,
                'data-confirm'     => $confirmmsg,
                'data-running'     => $runningmsg,
                'data-success'     => $successmsg,
            ]);
        }

        echo html_writer::end_div(); // End of .d-flex.
        echo html_writer::tag('div', '', ['id' => 'aiviva-regen-status', 'class' => 'mt-2']);
        echo html_writer::end_div(); // End of .card.

        // Inline JS — no AMD needed for this teacher-only page.
        echo html_writer::script(<<<JS
(function() {
    /**
     * Creates a Bootstrap alert div with safely set text content (no XSS risk).
     * @param {string} level   Bootstrap colour: 'info', 'success', 'danger'.
     * @param {string} message Plain-text message.
     * @returns {HTMLElement}
     */
    function makeAlert(level, message) {
        var div = document.createElement('div');
        div.className = 'alert alert-' + level;
        div.textContent = message;
        return div;
    }

    document.querySelectorAll('.aiviva-regen-btn').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!window.confirm(btn.dataset.confirm)) return;

            var statusEl = document.getElementById('aiviva-regen-status');
            document.querySelectorAll('.aiviva-regen-btn').forEach(function(b) { b.disabled = true; });
            statusEl.replaceChildren(makeAlert('info', btn.dataset.running));

            try {
                var resp = await fetch(btn.dataset.ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action:       btn.dataset.action,
                        cmid:         parseInt(btn.dataset.cmid),
                        submissionid: parseInt(btn.dataset.submissionid),
                        sesskey:      btn.dataset.sesskey
                    })
                });
                var data = await resp.json();
                if (data.success) {
                    statusEl.replaceChildren(makeAlert('success', btn.dataset.success));
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    statusEl.replaceChildren(makeAlert('danger', data.error || 'Unknown error'));
                    document.querySelectorAll('.aiviva-regen-btn').forEach(function(b) { b.disabled = false; });
                }
            } catch (e) {
                statusEl.replaceChildren(makeAlert('danger', e.message));
                document.querySelectorAll('.aiviva-regen-btn').forEach(function(b) { b.disabled = false; });
            }
        });
    });
})();
JS);
    }

    // Grade & feedback override form (grading workflow or any teacher).
    if (has_capability('mod/aiviva:grade', $context)) {
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/mod/aiviva/submissions.php'),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cmid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savegarde']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::start_div('card card-body mb-3');
        echo html_writer::tag('h5', get_string('grading_header', 'mod_aiviva'));
        echo html_writer::div(
            html_writer::label(get_string('maximumgrade', 'mod_aiviva'), 'grade_input') .
            html_writer::empty_tag('input', [
                'type'  => 'number',
                'id'    => 'grade_input',
                'name'  => 'grade',
                'value' => format_float($submission->final_grade ?? 0, 2),
                'class' => 'form-control d-inline-block w-auto ms-2',
                'min'   => 0,
                'max'   => $aiviva->grade,
                'step'  => '0.01',
            ]),
            'mb-2'
        );
        echo html_writer::div(
            html_writer::label(get_string('feedback', 'mod_aiviva'), 'feedback_input') .
            html_writer::tag('textarea', s($submission->final_feedback ?? ''), [
                'id'    => 'feedback_input',
                'name'  => 'feedback',
                'class' => 'form-control mt-2',
                'rows'  => 5,
            ]),
            'mb-2'
        );

        // Workflow action buttons.
        if ($aiviva->grading_workflow && $submission->workflow_state !== 'released') {
            echo html_writer::tag(
                'button',
                get_string('publish_grade', 'mod_aiviva'),
                ['type' => 'submit', 'class' => 'btn btn-success me-2',
                    'formaction' => new moodle_url('/mod/aiviva/submissions.php', [
                        'id' => $cmid, 'userid' => $userid, 'action' => 'publish', 'sesskey' => sesskey(),
                    ])]
            );
        }
        echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo html_writer::end_div();
        echo html_writer::end_tag('form');
    }
}
