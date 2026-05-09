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
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/aiviva/lib.php');

$id     = required_param('id', PARAM_INT);     // Course module id.
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$sesskeyneeded = false;

// Mutating actions require sesskey.
$mutating = ['gdpr_consent', 'start', 'submit_pdf', 'submit_video', 'tribunal_turn'];
if (in_array($action, $mutating)) {
    $sesskeyneeded = true;
}

$cm      = get_coursemodule_from_id('aiviva', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$aiviva  = $DB->get_record('aiviva', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/aiviva:view', $context);

if ($sesskeyneeded) {
    require_sesskey();
}

// Fire the course_module_viewed event.
\mod_aiviva\event\course_module_viewed::create_from_cm($cm, $course, $aiviva)->trigger();

// Update completion state.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Fetch or create submission record.
$userid = $USER->id;
if ($aiviva->group_submission) {
    $group = groups_get_activity_group($cm, true);
    $groupid = $group ? $group : 0;
} else {
    $groupid = 0;
}

$submission = $DB->get_record('aiviva_submissions', [
    'aiviva'  => $aiviva->id,
    'userid'  => $userid,
    'attempt' => $DB->get_field_sql(
        'SELECT COALESCE(MAX(attempt), 0) FROM {aiviva_submissions} WHERE aiviva = ? AND userid = ?',
        [$aiviva->id, $userid]
    ) ?: 1,
]);

// Handle GDPR consent action.
if ($action === 'gdpr_consent') {
    $consentgiven = required_param('consent', PARAM_INT);
    if ($consentgiven == 1) {
        if (!$submission) {
            $submission              = new stdClass();
            $submission->aiviva      = $aiviva->id;
            $submission->userid      = $userid;
            $submission->groupid     = $groupid;
            $submission->status      = 'draft';
            $submission->attempt     = 1;
            $submission->gdpr_consent      = 1;
            $submission->gdpr_consent_time = time();
            $submission->timecreated       = time();
            $submission->timemodified      = time();
            $submission->id = $DB->insert_record('aiviva_submissions', $submission);
        } else {
            $DB->set_field('aiviva_submissions', 'gdpr_consent', 1, ['id' => $submission->id]);
            $DB->set_field('aiviva_submissions', 'gdpr_consent_time', time(), ['id' => $submission->id]);
            $submission->gdpr_consent = 1;
        }
    }
    redirect(new moodle_url('/mod/aiviva/view.php', ['id' => $id]));
}

// Determine attempts used.
$attemptsused = (int)$DB->count_records_select(
    'aiviva_submissions',
    'aiviva = ? AND userid = ?',
    [$aiviva->id, $userid]
);
$maxattempts  = (int)$aiviva->max_attempts;
$cansubmit    = has_capability('mod/aiviva:submit', $context);
$attemptsremaining = ($maxattempts === 0) ? PHP_INT_MAX : max(0, $maxattempts - $attemptsused);

// Page output.
$PAGE->set_url('/mod/aiviva/view.php', ['id' => $id]);
$PAGE->set_title(format_string($aiviva->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Load AMD modules and CSS.
$PAGE->requires->css('/mod/aiviva/styles.css');
$PAGE->requires->js_call_amd('mod_aiviva/step1_upload', 'init', [[
    'cmid'         => $cm->id,
    'sesskey'      => sesskey(),
    'submissionid' => $submission ? $submission->id : 0,
    'maxfilesize'  => (int)$aiviva->step2_maxfilesize,
]]);
$PAGE->requires->js_call_amd('mod_aiviva/step2_recording', 'init', [[
    'cmid'          => $cm->id,
    'sesskey'       => sesskey(),
    'submissionid'  => $submission ? $submission->id : 0,
    'durationmins'  => (int)$aiviva->step2_duration,
    'maxsizemb'     => (int)$aiviva->step2_maxfilesize,
]]);
// Map Moodle lang code to BCP-47 for the Web Speech API.
$moodlelang = current_language();
$langbcp47map = [
    'es'    => 'es-ES', 'es_es' => 'es-ES', 'pt_br' => 'pt-BR',
    'pt'    => 'pt-PT', 'fr'    => 'fr-FR', 'de'    => 'de-DE',
    'it'    => 'it-IT', 'ca'    => 'ca-ES', 'eu'    => 'eu-ES',
    'gl'    => 'gl-ES', 'en'    => 'en-US',
];
$speechlang = $langbcp47map[$moodlelang] ?? str_replace('_', '-', $moodlelang);

$PAGE->requires->js_call_amd('mod_aiviva/step3_tribunal', 'init', [[
    'cmid'             => $cm->id,
    'sesskey'          => sesskey(),
    'submissionid'     => $submission ? $submission->id : 0,
    'durationmins'     => (int)$aiviva->step3_duration,
    'member1voice'     => s($aiviva->tribunal_member_1_voice),
    'member2voice'     => s($aiviva->tribunal_member_2_voice),
    'member3voice'     => s($aiviva->tribunal_member_3_voice),
    'speechlang'       => $speechlang,
    'submissionstatus' => $submission ? $submission->status : 'draft',
]]);

echo $OUTPUT->header();

// Determine current step from submission status.
$currentstep = 0;
if ($submission) {
    // Currentstep = index of the panel to display (0=step1, 1=step2, 2=step3).
    // 'stepN' status means the student is ready FOR step N (i.e. step N-1 is done).
    $statusmap = [
        'draft'     => 0, // Show step 1 panel.
        'step1'     => 0, // Still in step 1 (PDF uploading/analysing) - show step 1.
        'step2'     => 1, // PDF done - show step 2 (video).
        'step3'     => 2, // Video done - show step 3 (tribunal).
        'submitted' => 2, // Tribunal done, awaiting evaluation.
        'grading'   => 2,
        'graded'    => 2,
    ];
    $currentstep = $statusmap[$submission->status] ?? 0;
}

// Stepper UI.
$stepnames = [
    get_string('step1_title', 'mod_aiviva'),
    get_string('step2_title', 'mod_aiviva'),
    get_string('step3_title', 'mod_aiviva'),
];

echo html_writer::start_div('aiviva-container');

// Stepper.
echo html_writer::start_div('aiviva-stepper');
for ($i = 0; $i < 3; $i++) {
    $stepnum    = $i + 1;
    $stepclass  = 'aiviva-step';
    if ($i < $currentstep) {
        $stepclass .= ' completed';
    } else if ($i === $currentstep) {
        $stepclass .= ' active';
    }
    echo html_writer::start_div($stepclass, ['data-step' => $stepnum]);
    echo html_writer::span($stepnum, 'step-number');
    echo html_writer::span($stepnames[$i], 'step-label');
    echo html_writer::end_div();
    if ($i < 2) {
        echo html_writer::span('', 'step-connector');
    }
}
echo html_writer::end_div(); // End of .aiviva-stepper.

// Attempt info.
$attemptsinfo = get_string('attemptsinfo', 'mod_aiviva', [
    'used'      => $attemptsused,
    'remaining' => ($maxattempts === 0) ? get_string('unlimited', 'mod_aiviva') : $attemptsremaining,
    'max'       => ($maxattempts === 0) ? get_string('unlimited', 'mod_aiviva') : $maxattempts,
]);
echo html_writer::div($attemptsinfo, 'aiviva-attempts-info');

// GDPR Consent (shown if not yet given).
if ($cansubmit && (!$submission || !$submission->gdpr_consent)) {
    $purgedays  = (int)($aiviva->video_purge_days ?? get_config('mod_aiviva', 'video_purge_days') ?? 15);
    $gdprtext   = get_string('gdpr_default_notice', 'mod_aiviva', $purgedays);

    echo html_writer::start_div('aiviva-gdpr-notice card');
    echo html_writer::div(
        html_writer::tag('h4', get_string('gdpr_notice_title', 'mod_aiviva')) . $gdprtext,
        'card-body'
    );

    // Consent form.
    $consentform = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/mod/aiviva/view.php', ['id' => $id]),
    ]);
    $consentform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'gdpr_consent']);
    $consentform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $consentform .= html_writer::div(
        html_writer::checkbox(
            'consent',
            1,
            false,
            get_string('gdpr_consent_label', 'mod_aiviva'),
            ['id' => 'aiviva_gdpr_consent', 'required' => 'required']
        ) .
        html_writer::tag(
            'button',
            get_string('start_activity', 'mod_aiviva'),
            ['type' => 'submit', 'class' => 'btn btn-primary btn-lg mt-3 aiviva-start-btn', 'id' => 'aiviva_start_btn']
        ),
        'card-footer'
    );
    $consentform .= html_writer::end_tag('form');
    echo $consentform;
    echo html_writer::end_div(); // End of .aiviva-gdpr-notice.
}

// Step panels (shown when GDPR accepted).
if ($submission && $submission->gdpr_consent) {
    // Step 1 panel.
    $s1visible = ($currentstep === 0) ? '' : 'd-none';
    echo html_writer::start_div("aiviva-step-panel aiviva-step1 {$s1visible}", ['id' => 'aiviva_step1_panel']);
    echo $OUTPUT->heading(get_string('step1_title', 'mod_aiviva'), 3);
    if ($aiviva->step1_description) {
        echo html_writer::div(
            format_text($aiviva->step1_description, $aiviva->step1_descriptionformat),
            'aiviva-step-description'
        );
    }
    // PDF upload zone.
    echo html_writer::start_div('aiviva-upload-zone', ['id' => 'aiviva_pdf_dropzone']);
    echo html_writer::div(get_string('pdf_dropzone_label', 'mod_aiviva'), 'dropzone-label');
    echo html_writer::empty_tag('input', [
        'type'   => 'file',
        'id'     => 'aiviva_pdf_input',
        'name'   => 'pdffile',
        'accept' => 'application/pdf',
        'class'  => 'aiviva-file-input',
    ]);
    echo html_writer::end_div();
    echo html_writer::div('', 'aiviva-progress-bar', ['id' => 'aiviva_pdf_progress']);
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_pdf_status']);
    echo html_writer::tag(
        'button',
        get_string('upload_pdf', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-primary', 'id' => 'aiviva_pdf_upload_btn', 'disabled' => 'disabled']
    );
    echo html_writer::end_div(); // End of .aiviva-step1.

    // Step 2 panel.
    $s2visible = ($currentstep === 1) ? '' : 'd-none';
    echo html_writer::start_div("aiviva-step-panel aiviva-step2 {$s2visible}", ['id' => 'aiviva_step2_panel']);
    echo $OUTPUT->heading(get_string('step2_title', 'mod_aiviva'), 3);
    if ($aiviva->step2_description) {
        echo html_writer::div(
            format_text($aiviva->step2_description, $aiviva->step2_descriptionformat),
            'aiviva-step-description'
        );
    }
    echo html_writer::start_div('aiviva-recording-controls');
    echo html_writer::tag(
        'button',
        get_string('start_recording', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-danger btn-lg', 'id' => 'aiviva_rec_start_btn']
    );
    echo html_writer::div('', 'aiviva-countdown', ['id' => 'aiviva_countdown']);
    echo html_writer::div('', 'aiviva-rec-indicator d-none', ['id' => 'aiviva_rec_indicator']);
    echo html_writer::div('', 'aiviva-timer', ['id' => 'aiviva_rec_timer']);
    echo html_writer::div('', 'aiviva-progress-bar', ['id' => 'aiviva_rec_progress']);
    echo html_writer::tag(
        'button',
        get_string('stop_recording', 'mod_aiviva'),
        ['type' => 'button', 'class' => 'btn btn-secondary d-none', 'id' => 'aiviva_rec_stop_btn']
    );
    echo html_writer::end_div(); // End of .aiviva-recording-controls.
    echo html_writer::div('', 'aiviva-video-preview', ['id' => 'aiviva_video_preview']);
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_video_status']);
    echo html_writer::end_div(); // End of .aiviva-step2.

    // Evaluating panel (shown when tribunal is done but evaluation is pending).
    $evaluatingstatuses = ['submitted', 'grading'];
    $evalvisible = ($submission && in_array($submission->status, $evaluatingstatuses)) ? '' : 'd-none';
    echo html_writer::start_div("aiviva-step-panel text-center py-5 {$evalvisible}", ['id' => 'aiviva_evaluating_panel']);
    echo html_writer::tag('div', '', ['class' => 'spinner-border text-primary mb-3', 'role' => 'status']);
    echo html_writer::tag('p', get_string('evaluation_pending', 'mod_aiviva'), ['class' => 'lead']);
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_eval_status']);
    echo html_writer::end_div();

    // Step 3 panel.
    $s3excludedstatuses = ['submitted', 'grading', 'graded'];
    $s3visible = ($currentstep >= 2 && !in_array($submission->status, $s3excludedstatuses)) ? '' : 'd-none';
    echo html_writer::start_div("aiviva-step-panel aiviva-step3 {$s3visible}", ['id' => 'aiviva_step3_panel']);
    echo html_writer::start_div('aiviva-tribunal-room');

    // Header.
    echo html_writer::start_div('tribunal-header');
    echo html_writer::tag(
        'h3',
        get_string('tribunal_room_title', 'mod_aiviva', format_string($aiviva->name)),
        ['class' => 'tribunal-title']
    );
    echo html_writer::div('', 'tribunal-timer', ['id' => 'aiviva_tribunal_timer']);
    echo html_writer::end_div();

    // Avatar panel.
    echo html_writer::start_div('tribunal-avatars');
    for ($m = 1; $m <= 3; $m++) {
        $namefield   = "tribunal_member_{$m}_name";
        $rolefield   = "tribunal_member_{$m}_role";
        $avatarfield = "tribunal_member_{$m}_avatar";

        echo html_writer::start_div('tribunal-member', ['id' => "tribunal_member_{$m}", 'data-member' => $m]);
        $avatarnum = (int)($aiviva->$avatarfield);
        if ($avatarnum >= 1 && $avatarnum <= 3) {
            // Video loop avatar — wrapped in avatar-glow for circular clip + active-speaker styling.
            $pixbase    = (new moodle_url('/mod/aiviva/pix/avatars/'))->out(false);
            $idleurl    = $pixbase . "avatar_{$avatarnum}_idle.mp4";
            $talkingurl = $pixbase . "avatar_{$avatarnum}_talking.mp4";
            $posterurl  = $pixbase . "avatar_{$avatarnum}_poster.png";
            $videotag   = html_writer::tag('video', '', [
                'id'           => "avatar_video_{$m}",
                'class'        => 'avatar-video',
                'src'          => $idleurl,
                'poster'       => $posterurl,
                'loop'         => 'loop',
                'autoplay'     => 'autoplay',
                'muted'        => 'muted',
                'playsinline'  => 'playsinline',
                'data-idle'    => $idleurl,
                'data-talking' => $talkingurl,
            ]);
            echo html_writer::div($videotag, 'avatar-glow');
        } else {
            // Custom avatar (0) or fallback: use SVG.
            $avatarfile = $CFG->dirroot . "/mod/aiviva/pix/avatars/avatar_{$avatarnum}.svg";
            $avatarsvg  = file_exists($avatarfile) ? file_get_contents($avatarfile) : '';
            echo html_writer::div($avatarsvg, 'avatar-glow');
        }
        echo html_writer::div(
            html_writer::tag('strong', s($aiviva->$namefield)) .
            html_writer::tag('span', s($aiviva->$rolefield), ['class' => 'member-role']),
            'member-info'
        );
        echo html_writer::end_div();
    }
    echo html_writer::end_div(); // End of .tribunal-avatars.

    // Transcript area.
    echo html_writer::div('', 'tribunal-transcript', ['id' => 'aiviva_tribunal_transcript']);

    // Participant controls.
    echo html_writer::start_div('tribunal-participant-controls');
    echo html_writer::div('', 'participant-waveform', ['id' => 'aiviva_waveform']);
    echo html_writer::tag(
        'button',
        '🎤 ' . get_string('push_to_talk', 'mod_aiviva'),
        [
            'type'  => 'button',
            'class' => 'btn btn-primary btn-lg push-to-talk d-none',
            'id'    => 'aiviva_ptt_btn',
        ]
    );
    echo html_writer::div('', 'aiviva-status-message', ['id' => 'aiviva_tribunal_status']);
    echo html_writer::end_div();

    // Sidebar.
    echo html_writer::start_div('tribunal-sidebar', ['id' => 'aiviva_tribunal_sidebar']);
    echo $OUTPUT->heading(get_string('conversation_log', 'mod_aiviva'), 4);
    echo html_writer::div('', 'conversation-log', ['id' => 'aiviva_conversation_log']);
    echo html_writer::end_div();

    echo html_writer::end_div(); // End of .aiviva-tribunal-room.
    echo html_writer::end_div(); // End of .aiviva-step3.

    // Graded state display.
    if ($submission->status === 'graded' && isset($submission->final_grade)) {
        echo html_writer::start_div('aiviva-results card mt-4');
        echo html_writer::start_div('card-body');
        echo $OUTPUT->heading(get_string('results_title', 'mod_aiviva'), 3);

        if ($submission->workflow_state === 'released') {
            echo html_writer::div(
                get_string('your_grade', 'mod_aiviva') . ' ' .
                html_writer::tag('strong', format_float($submission->final_grade, 2)),
                'aiviva-final-grade alert alert-success'
            );

            if ($submission->final_feedback) {
                echo html_writer::div(
                    html_writer::tag('h5', get_string('feedback', 'mod_aiviva')) .
                    format_text($submission->final_feedback, FORMAT_HTML),
                    'aiviva-feedback mt-3'
                );
            }
        } else {
            echo html_writer::div(
                get_string('grade_pending_review', 'mod_aiviva'),
                'alert alert-info'
            );
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo html_writer::end_div(); // End of .aiviva-container.

echo $OUTPUT->footer();
