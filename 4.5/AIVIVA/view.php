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
 * Main participant view for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

use mod_aiviva\local\manager;

$id     = required_param('id', PARAM_INT);     // Course module id.
$action = optional_param('action', '', PARAM_ALPHANUMEXT);

$cm      = get_coursemodule_from_id('aiviva', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$aiviva  = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiviva:view', $context);

$pageurl = new moodle_url('/mod/aiviva/view.php', ['id' => $id]);
$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($aiviva->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// License gate. Without a valid key bound to this site the activity is blocked
// (a message is shown instead of the activity). Done before firing events,
// completion or submission creation so no side effects occur while unlicensed.
// A site administrator can still reach the plugin settings to paste a key.
if (!\mod_aiviva\license\validator::is_valid()) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        \mod_aiviva\license\validator::get_banner(),
        \core\output\notification::NOTIFY_ERROR
    );
    echo $OUTPUT->footer();
    exit;
}

$cansubmit    = has_capability('mod/aiviva:submit', $context);
$settings     = manager::get_effective_settings($aiviva, $USER->id);
$availability = manager::availability($settings);
$submission   = $cansubmit ? manager::get_latest_submission($aiviva->id, $USER->id) : null;
$canstart     = $cansubmit && manager::can_start_attempt($settings, $submission);

// Giving consent starts an attempt: the first one, or a further one once the previous is graded.
if ($action === 'gdpr_consent') {
    require_sesskey();
    if (required_param('consent', PARAM_INT) == 1) {
        if ($submission && !$submission->gdpr_consent && in_array($submission->status, manager::OPEN_STATUSES)) {
            $DB->update_record('aiviva_submissions', (object)[
                'id' => $submission->id, 'gdpr_consent' => 1, 'gdpr_consent_time' => time(),
            ]);
        } else if ($canstart) {
            manager::create_attempt($aiviva, $USER->id, $submission);
        }
    }
    redirect($pageurl);
}

\mod_aiviva\event\course_module_viewed::create_from_cm($cm, $course, $aiviva)->trigger();
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Work out which panel the student sees.
$status = $submission->status ?? '';
$inprogress = $submission && $submission->gdpr_consent && in_array($status, manager::OPEN_STATUSES);
$panel = 'none';
if ($inprogress) {
    if ($status === 'draft') {
        $panel = 'step1';
    } else if ($status === 'step1') {
        $panel = 'analysing_pdf';
    } else if ($status === 'step2') {
        $panel = (int)$submission->video_fileid > 0 ? 'analysing_video' : 'step2';
    } else {
        $panel = 'step3';
    }
    // Once the activity has closed, only a tribunal that is already running may continue.
    if ($availability !== '' && !($panel === 'step3' && !empty($submission->tribunal_timestart))) {
        $panel = 'unavailable';
    }
} else if ($submission && in_array($status, ['submitted', 'grading'])) {
    $panel = 'evaluating';
} else if ($submission && $status === 'graded') {
    $panel = 'results';
} else if ($cansubmit && $availability !== '') {
    $panel = 'unavailable';
}
$currentstep = ['step1' => 0, 'analysing_pdf' => 0, 'step2' => 1, 'analysing_video' => 1, 'step3' => 2][$panel] ?? 3;

// JavaScript for the active panel only.
$jsconfig = ['cmid' => (int)$cm->id, 'submissionid' => (int)($submission->id ?? 0)];
if ($panel === 'step1') {
    $PAGE->requires->js_call_amd('mod_aiviva/step1_upload', 'init', [$jsconfig + [
        'maxfilesize' => (int)$aiviva->step1_maxfilesize,
    ]]);
} else if ($panel === 'step2') {
    $PAGE->requires->js_call_amd('mod_aiviva/step2_recording', 'init', [$jsconfig + [
        'durationmins' => (int)$aiviva->step2_duration,
        'maxsizemb'    => (int)$aiviva->step2_maxfilesize,
    ]]);
} else if ($panel === 'step3') {
    $PAGE->requires->js_call_amd('mod_aiviva/step3_tribunal', 'init', [$jsconfig + [
        'started' => !empty($submission->tribunal_timestart),
    ]]);
} else if (in_array($panel, ['analysing_pdf', 'analysing_video', 'evaluating'])) {
    $PAGE->requires->js_call_amd('mod_aiviva/status_poll', 'init', [$jsconfig + ['status' => $status]]);
}

echo $OUTPUT->header();

// Non-blocking evaluation-period notice. Shown to users who can configure the
// plugin, so students are not bothered with a licensing message.
$licensenotice = \mod_aiviva\license\validator::get_notice();
if ($licensenotice !== null && has_capability('mod/aiviva:manageplugin', context_system::instance())) {
    echo $OUTPUT->notification($licensenotice, \core\output\notification::NOTIFY_INFO);
}

echo html_writer::start_div('aiviva-container');

// Staff see a pointer to the submissions page rather than an empty student view.
if (has_capability('mod/aiviva:viewallsubmissions', $context)) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/mod/aiviva/submissions.php', ['id' => $cm->id]),
            get_string('view_submissions', 'mod_aiviva'),
            ['class' => 'btn btn-primary']
        ),
        'mb-3'
    );
}

if ($cansubmit) {
    // Stepper.
    $stepnames = [
        get_string('step1_title', 'mod_aiviva'),
        get_string('step2_title', 'mod_aiviva'),
        get_string('step3_title', 'mod_aiviva'),
    ];
    echo html_writer::start_div('aiviva-stepper');
    foreach ($stepnames as $i => $stepname) {
        $stepclass = 'aiviva-step';
        if ($submission && $i < $currentstep) {
            $stepclass .= ' completed';
        } else if ($i === $currentstep) {
            $stepclass .= ' active';
        }
        echo html_writer::start_div($stepclass);
        echo html_writer::span($i + 1, 'step-number');
        echo html_writer::span($stepname, 'step-label');
        echo html_writer::end_div();
        if ($i < 2) {
            echo html_writer::span('', 'step-connector');
        }
    }
    echo html_writer::end_div();

    // Attempts and availability.
    $info = [get_string('attemptsinfo', 'mod_aiviva', [
        'used' => $submission ? (int)$submission->attempt : 0,
        'max'  => $settings->max_attempts === 0 ? get_string('unlimited', 'mod_aiviva') : $settings->max_attempts,
    ])];
    if ($settings->timeopen) {
        $info[] = get_string('availability_opens', 'mod_aiviva', userdate($settings->timeopen));
    }
    if ($settings->timeclose) {
        $info[] = get_string('availability_closes', 'mod_aiviva', userdate($settings->timeclose));
    }
    echo html_writer::div(implode(' · ', $info), 'aiviva-attempts-info');
}

if ($panel === 'unavailable') {
    echo $OUTPUT->notification(
        get_string('error_' . $availability, 'mod_aiviva'),
        \core\output\notification::NOTIFY_WARNING
    );
}

// Results of the latest finished attempt.
if ($panel === 'results') {
    echo html_writer::start_div('aiviva-results card mb-4');
    echo html_writer::start_div('card-body');
    echo $OUTPUT->heading(get_string('results_title', 'mod_aiviva'), 3);

    if ($submission->workflow_state === 'released') {
        echo html_writer::div(
            get_string('your_grade', 'mod_aiviva') . ' ' .
            html_writer::tag('strong', format_float($submission->final_grade, 2) . ' / ' . (int)$aiviva->grade),
            'aiviva-final-grade alert alert-success'
        );
        if (trim((string)$submission->final_feedback) !== '') {
            echo html_writer::div(
                html_writer::tag('h5', get_string('feedback', 'mod_aiviva')) .
                format_text($submission->final_feedback, FORMAT_PLAIN, ['context' => $context]),
                'aiviva-feedback mt-3'
            );
        }
        // The per-step breakdown is the AI's; it is shown only while the grade is the AI's own.
        $breakdown = json_decode((string)$submission->grade_breakdown, true);
        if (is_array($breakdown) && empty($submission->grader_userid)) {
            echo $PAGE->get_renderer('mod_aiviva')->render_grade_breakdown($breakdown);
        }
    } else {
        echo html_writer::div(get_string('grade_pending_review', 'mod_aiviva'), 'alert alert-info');
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Consent form: starts the first attempt, or a new one after a graded attempt.
$consentpending = $submission && !$submission->gdpr_consent
    && in_array($status, manager::OPEN_STATUSES) && $availability === '';
if ($canstart || $consentpending) {
    $customnotice = trim((string)get_config('mod_aiviva', 'gdpr_notice_text'));
    $purgedays    = (int)$aiviva->video_purge_days;
    $gdprtext     = $customnotice !== ''
        ? format_text($customnotice, FORMAT_HTML, ['context' => $context])
        : get_string('gdpr_default_notice', 'mod_aiviva');
    $gdprtext    .= html_writer::tag('p', $purgedays > 0
        ? get_string('gdpr_retention', 'mod_aiviva', $purgedays)
        : get_string('gdpr_retention_none', 'mod_aiviva'));

    echo html_writer::start_div('aiviva-gdpr-notice card');
    if ($submission && $status === 'graded') {
        echo html_writer::div(get_string('new_attempt_notice', 'mod_aiviva'), 'alert alert-info m-3');
    }
    echo html_writer::div(
        html_writer::tag('h4', get_string('gdpr_notice_title', 'mod_aiviva')) . $gdprtext,
        'card-body'
    );
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'gdpr_consent']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::div(
        html_writer::checkbox(
            'consent',
            1,
            false,
            get_string('gdpr_consent_label', 'mod_aiviva'),
            ['id' => 'aiviva_gdpr_consent', 'required' => 'required']
        ) .
        html_writer::tag(
            'button',
            get_string($submission && $status === 'graded' ? 'start_new_attempt' : 'start_activity', 'mod_aiviva'),
            ['type' => 'submit', 'class' => 'btn btn-primary btn-lg mt-3 d-block aiviva-start-btn']
        ),
        'card-footer'
    );
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

// Step 1: PDF upload.
if ($panel === 'step1') {
    echo html_writer::start_div('aiviva-step-panel aiviva-step1');
    echo $OUTPUT->heading(get_string('step1_title', 'mod_aiviva'), 3);
    if ($aiviva->step1_description) {
        echo html_writer::div(
            format_text($aiviva->step1_description, $aiviva->step1_descriptionformat, ['context' => $context]),
            'aiviva-step-description'
        );
    }
    echo html_writer::start_div('aiviva-upload-zone', ['id' => 'aiviva_pdf_dropzone', 'role' => 'button', 'tabindex' => 0]);
    echo html_writer::div(
        get_string('pdf_dropzone_label', 'mod_aiviva') . ' ' .
        get_string('pdf_maxsize', 'mod_aiviva', (int)$aiviva->step1_maxfilesize),
        'dropzone-label'
    );
    echo html_writer::empty_tag('input', [
        'type'       => 'file',
        'id'         => 'aiviva_pdf_input',
        'accept'     => 'application/pdf',
        'class'      => 'aiviva-file-input',
        'aria-label' => get_string('upload_pdf', 'mod_aiviva'),
    ]);
    echo html_writer::end_div();
    echo html_writer::div('', 'aiviva-progress-bar', ['id' => 'aiviva_pdf_progress']);
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_pdf_status', 'role' => 'status']);
    echo html_writer::tag(
        'button',
        get_string('upload_pdf', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-primary', 'id' => 'aiviva_pdf_upload_btn', 'disabled' => 'disabled']
    );
    echo html_writer::end_div();
}

// Step 2: presentation recording.
if ($panel === 'step2') {
    echo html_writer::start_div('aiviva-step-panel aiviva-step2');
    echo $OUTPUT->heading(get_string('step2_title', 'mod_aiviva'), 3);
    if ($aiviva->step2_description) {
        echo html_writer::div(
            format_text($aiviva->step2_description, $aiviva->step2_descriptionformat, ['context' => $context]),
            'aiviva-step-description'
        );
    }
    echo html_writer::div(
        get_string('recording_instructions', 'mod_aiviva', (int)$aiviva->step2_duration),
        'alert alert-info'
    );
    echo html_writer::start_div('aiviva-recording-controls');
    echo html_writer::tag(
        'button',
        get_string('start_recording', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-danger btn-lg', 'id' => 'aiviva_rec_start_btn']
    );
    echo html_writer::div('', 'aiviva-countdown', ['id' => 'aiviva_countdown', 'aria-live' => 'assertive']);
    echo html_writer::div(get_string('recording_indicator', 'mod_aiviva'), 'aiviva-rec-indicator d-none', [
        'id' => 'aiviva_rec_indicator',
    ]);
    echo html_writer::div('', 'aiviva-timer', ['id' => 'aiviva_rec_timer']);
    echo html_writer::div('', 'aiviva-progress-bar', ['id' => 'aiviva_rec_progress']);
    echo html_writer::tag(
        'button',
        get_string('stop_recording', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-secondary d-none', 'id' => 'aiviva_rec_stop_btn']
    );
    echo html_writer::end_div();
    // Live preview of what is being captured; also the source of the screenshots sent for analysis.
    echo html_writer::tag('video', '', [
        'id' => 'aiviva_live_preview', 'class' => 'aiviva-live-preview d-none', 'muted' => 'muted', 'playsinline' => 'playsinline',
    ]);
    echo html_writer::div('', 'aiviva-video-preview', ['id' => 'aiviva_video_preview']);
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_video_status', 'role' => 'status']);
    echo html_writer::end_div();
}

// Waiting panels: the page polls and moves on by itself.
if (in_array($panel, ['analysing_pdf', 'analysing_video', 'evaluating'])) {
    $messages = [
        'analysing_pdf'   => 'pdf_uploaded_analysing',
        'analysing_video' => 'video_uploaded_analysing',
        'evaluating'      => 'evaluation_pending',
    ];
    echo html_writer::start_div('aiviva-step-panel text-center py-5');
    echo html_writer::tag('div', '', ['class' => 'spinner-border text-primary mb-3', 'aria-hidden' => 'true']);
    echo html_writer::tag('p', get_string($messages[$panel], 'mod_aiviva'), ['class' => 'lead', 'role' => 'status']);
    echo html_writer::tag('p', get_string('waiting_hint', 'mod_aiviva'), ['class' => 'text-muted']);
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_poll_status', 'role' => 'status']);
    echo html_writer::end_div();
}

// Step 3: tribunal.
if ($panel === 'step3') {
    echo html_writer::start_div('aiviva-step-panel aiviva-step3', ['id' => 'aiviva_step3_panel']);

    // Ready screen with a microphone check; replaced by the room when the session starts.
    echo html_writer::start_div('text-center p-4 my-4', ['id' => 'aiviva_tribunal_ready']);
    echo html_writer::tag('h3', get_string('tribunal_ready_title', 'mod_aiviva'), ['class' => 'mb-3']);
    echo html_writer::div(
        get_string(
            empty($submission->tribunal_timestart) ? 'tribunal_ready_notice' : 'tribunal_resume_notice',
            'mod_aiviva',
            (int)$aiviva->step3_duration
        ),
        'alert alert-warning d-inline-block text-start mb-4 aiviva-ready-notice'
    );
    echo html_writer::start_div('aiviva-mic-test mb-4');
    echo html_writer::tag(
        'button',
        get_string('mic_test_btn', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-outline-secondary', 'id' => 'aiviva_mic_test_btn']
    );
    echo html_writer::div(html_writer::div('', 'aiviva-mic-level-bar', ['id' => 'aiviva_mic_level']), 'aiviva-mic-level');
    echo html_writer::div(get_string('mic_test_hint', 'mod_aiviva'), 'text-muted small', [
        'id' => 'aiviva_mic_test_status', 'role' => 'status',
    ]);
    echo html_writer::end_div();
    echo html_writer::tag(
        'button',
        get_string(empty($submission->tribunal_timestart) ? 'tribunal_start_btn' : 'tribunal_resume_btn', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-primary btn-lg', 'id' => 'aiviva_tribunal_start_btn', 'disabled' => 'disabled']
    );
    echo html_writer::end_div();

    echo html_writer::start_div('aiviva-tribunal-room d-none', ['id' => 'aiviva_tribunal_room']);

    echo html_writer::start_div('tribunal-header');
    echo html_writer::tag(
        'h3',
        get_string('tribunal_room_title', 'mod_aiviva', format_string($aiviva->name)),
        ['class' => 'tribunal-title']
    );
    echo html_writer::div('', 'tribunal-timer', ['id' => 'aiviva_tribunal_timer', 'role' => 'timer']);
    echo html_writer::end_div();

    echo html_writer::start_div('tribunal-avatars');
    $pixbase = (new moodle_url('/mod/aiviva/pix/avatars/'))->out(false);
    for ($m = 1; $m <= 3; $m++) {
        $avatarnum = (int)$aiviva->{"tribunal_member_{$m}_avatar"};
        $customurl = $avatarnum === 0 ? aiviva_get_custom_avatar_url($context, $m) : null;

        echo html_writer::start_div('tribunal-member', ['id' => "tribunal_member_{$m}", 'data-member' => $m]);
        if ($customurl) {
            $avatar = html_writer::empty_tag('img', ['src' => $customurl->out(false), 'alt' => '', 'class' => 'avatar-image']);
        } else {
            // Built-in looping avatar; also the fallback when "custom" is selected but no image was uploaded.
            $avatarnum = ($avatarnum >= 1 && $avatarnum <= 3) ? $avatarnum : $m;
            $avatar = html_writer::tag('video', '', [
                'id'           => "avatar_video_{$m}",
                'class'        => 'avatar-video',
                'src'          => $pixbase . "avatar_{$avatarnum}_idle.mp4",
                'poster'       => $pixbase . "avatar_{$avatarnum}_poster.png",
                'loop'         => 'loop',
                'autoplay'     => 'autoplay',
                'muted'        => 'muted',
                'playsinline'  => 'playsinline',
                'aria-hidden'  => 'true',
                'data-idle'    => $pixbase . "avatar_{$avatarnum}_idle.mp4",
                'data-talking' => $pixbase . "avatar_{$avatarnum}_talking.mp4",
            ]);
        }
        echo html_writer::div($avatar, 'avatar-glow');
        echo html_writer::div(
            html_writer::tag('strong', s($aiviva->{"tribunal_member_{$m}_name"})) .
            html_writer::tag('span', s($aiviva->{"tribunal_member_{$m}_role"}), ['class' => 'member-role']),
            'member-info'
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    echo html_writer::div('', 'tribunal-transcript', ['id' => 'aiviva_tribunal_transcript', 'aria-live' => 'polite']);

    echo html_writer::start_div('tribunal-participant-controls');
    echo html_writer::div(html_writer::div('', 'aiviva-mic-level-bar', ['id' => 'aiviva_answer_level']), 'aiviva-mic-level');
    echo html_writer::tag(
        'button',
        get_string('answer_start', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-primary btn-lg push-to-talk d-none', 'id' => 'aiviva_ptt_btn']
    );
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_tribunal_status', 'role' => 'status']);
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo $OUTPUT->footer();
