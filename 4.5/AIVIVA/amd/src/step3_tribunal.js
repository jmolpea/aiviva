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
 * Step 3 — AI tribunal.
 *
 * The server owns the session: the clock, the turn order and the transcript.
 * This module plays the examiners' audio, records each spoken answer with the
 * microphone and uploads it; the server transcribes it and replies with the
 * next question. Reloading the page resumes the same session.
 *
 * @module     mod_aiviva/step3_tribunal
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    post, showStatus, formatTime, playBeep, getAudioContext, pickMimeType,
    startLevelMeter, waitForStatusChange,
} from './utils';
import {get_strings as getStrings} from 'core/str';
import Config from 'core/config';

/** @type {number} Longest single answer, in seconds, before it is sent automatically. */
const MAX_ANSWER_SECS = 300;

/** @type {number} Answers shorter than this are treated as an accidental click. */
const MIN_ANSWER_MS = 800;

let cfg = {};
let els = {};
let str = {};
let micStream = null;
let stopMeter = null;
let recorder = null;
let answerChunks = [];
let answerStartedAt = 0;
let answerTimeout = null;
let deadline = 0;          // Local timestamp (ms) at which the session time runs out.
let clockTimer = null;
let warned = false;
let busy = false;          // True while a request or an examiner's turn is in progress.
let ended = false;
let prepared = null;       // Promise of the server-side preparation started on page load.

/**
 * Initialises the tribunal module.
 *
 * @param {Object}  config
 * @param {number}  config.cmid          - Course module id.
 * @param {number}  config.submissionid  - Submission id.
 * @param {boolean} config.started       - Whether the session has already begun (page reload).
 */
export const init = async(config) => {
    cfg = config;
    els = {
        ready: document.getElementById('aiviva_tribunal_ready'),
        room: document.getElementById('aiviva_tribunal_room'),
        micBtn: document.getElementById('aiviva_mic_test_btn'),
        micLevel: document.getElementById('aiviva_mic_level'),
        micStatus: document.getElementById('aiviva_mic_test_status'),
        startBtn: document.getElementById('aiviva_tribunal_start_btn'),
        timer: document.getElementById('aiviva_tribunal_timer'),
        transcript: document.getElementById('aiviva_tribunal_transcript'),
        answerBtn: document.getElementById('aiviva_ptt_btn'),
        answerLevel: document.getElementById('aiviva_answer_level'),
        status: document.getElementById('aiviva_tribunal_status'),
    };
    if (!els.ready || !els.room) {
        return;
    }

    const keys = [
        'you', 'mic_test_ok', 'mic_test_waiting', 'error_mic_permission', 'error_browser_unsupported',
        'tribunal_loading', 'tribunal_ending', 'tribunal_finished', 'warning_1min',
        'answer_start', 'answer_stop', 'answer_transcribing', 'answer_too_short', 'tribunal_leave_warning',
    ];
    const values = await getStrings([
        ...keys.map(key => ({key, component: 'mod_aiviva'})),
        {key: 'member_thinking', component: 'mod_aiviva', param: '{name}'},
    ]);
    keys.forEach((key, i) => {
        str[key] = values[i];
    });
    str.member_thinking = values[keys.length];

    // Have the server write the examiners' briefing and opening words now, while the
    // student reads the instructions and tests the microphone, so that "start" is instant.
    if (!cfg.started) {
        prepared = post('tribunal_prepare', {cmid: cfg.cmid, submissionid: cfg.submissionid}).catch(() => null);
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
        showStatus(els.micStatus, str.error_browser_unsupported, 'danger');
        els.micBtn.disabled = true;
        return;
    }

    els.micBtn.addEventListener('click', testMicrophone);
    els.startBtn.addEventListener('click', startSession);
    els.answerBtn.addEventListener('click', toggleAnswer);

    window.addEventListener('beforeunload', e => {
        if (deadline && !ended) {
            e.preventDefault();
            e.returnValue = str.tribunal_leave_warning;
        }
    });
};

// ------------------------------------------------------------------
// Ready screen
// ------------------------------------------------------------------

/**
 * Asks for the microphone and shows its level; the session can start once sound is heard.
 */
const testMicrophone = async() => {
    try {
        micStream = await navigator.mediaDevices.getUserMedia({audio: true, video: false});
    } catch (e) {
        showStatus(els.micStatus, str.error_mic_permission, 'danger');
        return;
    }
    getAudioContext(); // Created on this click, as browsers require a user gesture.
    els.micBtn.disabled = true;
    els.micStatus.textContent = str.mic_test_waiting;
    stopMeter = startLevelMeter(micStream, els.micLevel, () => {
        els.micStatus.textContent = str.mic_test_ok;
        els.startBtn.disabled = false;
    });
};

/**
 * Starts (or resumes) the session.
 */
const startSession = async() => {
    els.startBtn.disabled = true;
    if (stopMeter) {
        stopMeter();
    }
    els.ready.classList.add('d-none');
    els.room.classList.remove('d-none');
    stopMeter = startLevelMeter(micStream, els.answerLevel);
    showStatus(els.status, str.tribunal_loading, 'info');

    busy = true;
    try {
        await prepared;
        const data = await post('tribunal_opening', {cmid: cfg.cmid, submissionid: cfg.submissionid});
        data.history.forEach(item => appendToTranscript(item.member, item.name, item.text));
        syncClock(data.remaining);
        startClock();
        showStatus(els.status, '');
        if (data.turn) {
            await deliverTurn(data.turn);
        }
    } catch (e) {
        showStatus(els.status, e.message, 'danger');
        els.room.classList.add('d-none');
        els.ready.classList.remove('d-none');
        els.startBtn.disabled = false;
        busy = false;
        return;
    }
    busy = false;
    afterTurn();
};

// ------------------------------------------------------------------
// Clock
// ------------------------------------------------------------------

/**
 * Aligns the local countdown with the time left according to the server.
 *
 * @param {number} remainingSecs Seconds left on the server's clock.
 */
const syncClock = (remainingSecs) => {
    deadline = Date.now() + remainingSecs * 1000;
};

/**
 * Seconds left on the local countdown.
 *
 * @returns {number}
 */
const secondsLeft = () => Math.ceil((deadline - Date.now()) / 1000);

/**
 * Starts the visible countdown.
 */
const startClock = () => {
    const tick = () => {
        const left = secondsLeft();
        els.timer.textContent = formatTime(left);
        els.timer.classList.toggle('urgent', left <= 60);

        if (left <= 60 && left > 0 && !warned) {
            warned = true;
            playBeep(660, 400, 0.3);
            showStatus(els.status, str.warning_1min, 'warning');
        }
        if (left <= 0) {
            clearInterval(clockTimer);
            onTimeUp();
        }
    };
    tick();
    clockTimer = setInterval(tick, 1000);
};

/**
 * Time is up: send the answer in progress if there is one, otherwise close.
 */
const onTimeUp = () => {
    if (recorder && recorder.state === 'recording') {
        stopAnswer();
    } else if (!busy) {
        endSession();
    }
    // If busy, the turn in progress finishes and afterTurn() closes the session.
};

/**
 * Decides what happens once an examiner has finished speaking.
 */
const afterTurn = () => {
    if (ended) {
        return;
    }
    if (secondsLeft() <= 0) {
        endSession();
    } else {
        els.answerBtn.textContent = str.answer_start;
        els.answerBtn.classList.remove('d-none', 'active');
        els.answerBtn.disabled = false;
        els.answerBtn.focus();
    }
};

// ------------------------------------------------------------------
// Recording and sending an answer
// ------------------------------------------------------------------

/**
 * The answer button: first click starts recording, second click sends.
 */
const toggleAnswer = () => {
    if (recorder && recorder.state === 'recording') {
        stopAnswer();
    } else if (!busy && !ended) {
        startAnswer();
    }
};

/**
 * Starts recording the student's answer.
 */
const startAnswer = () => {
    answerChunks = [];
    const type = pickMimeType(['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4']);
    recorder = new MediaRecorder(micStream, {...(type ? {mimeType: type} : {}), audioBitsPerSecond: 32000});
    recorder.ondataavailable = e => e.data.size > 0 && answerChunks.push(e.data);
    recorder.addEventListener('stop', sendAnswer, {once: true});
    recorder.start();
    answerStartedAt = Date.now();
    answerTimeout = setTimeout(stopAnswer, MAX_ANSWER_SECS * 1000);

    els.answerBtn.textContent = str.answer_stop;
    els.answerBtn.classList.add('active');
    showStatus(els.status, '');
};

/**
 * Stops the recording; the 'stop' event then sends it.
 */
const stopAnswer = () => {
    clearTimeout(answerTimeout);
    if (recorder && recorder.state === 'recording') {
        els.answerBtn.disabled = true;
        recorder.stop();
    }
};

/**
 * Uploads the recorded answer and delivers the examiner's reply.
 */
const sendAnswer = async() => {
    els.answerBtn.classList.remove('active');

    if (Date.now() - answerStartedAt < MIN_ANSWER_MS && secondsLeft() > 0) {
        showStatus(els.status, str.answer_too_short, 'warning');
        afterTurn();
        return;
    }

    busy = true;
    els.answerBtn.classList.add('d-none');
    showStatus(els.status, str.answer_transcribing, 'info');

    const mime = (recorder.mimeType || 'audio/webm').split(';')[0];
    const blob = new Blob(answerChunks, {type: mime});

    try {
        const data = await post('tribunal_turn', {
            cmid: cfg.cmid,
            submissionid: cfg.submissionid,
            audiofile: [blob, 'answer.' + (mime.includes('mp4') ? 'mp4' : 'webm')],
        });
        if (data.expired) {
            busy = false;
            endSession();
            return;
        }
        // The answer is shown as soon as it has been understood; the examiner's reply follows.
        appendToTranscript(0, '', data.answer);
        syncClock(data.remaining);
        setThinking(data.next.member, true);
        showStatus(els.status, str.member_thinking.replace('{name}', data.next.name), 'info');

        const reply = await post('tribunal_next', {cmid: cfg.cmid, submissionid: cfg.submissionid});
        setThinking(data.next.member, false);
        syncClock(reply.remaining);
        showStatus(els.status, '');
        await deliverTurn(reply.turn);
    } catch (e) {
        setThinking(0, false);
        // Nothing was lost on the server: the student can simply answer again.
        showStatus(els.status, e.message, 'danger');
    }
    busy = false;
    afterTurn();
};

// ------------------------------------------------------------------
// Examiner turns
// ------------------------------------------------------------------

/**
 * Delivers an examiner's message: shows the text, plays the audio, animates the avatar.
 *
 * @param {Object} turn Turn payload from the server.
 */
const deliverTurn = async(turn) => {
    appendToTranscript(turn.member, turn.name, turn.text);
    setSpeaking(turn.member, true);
    const played = await playTurnAudio(turn.turn);
    if (!played) {
        // No audio: leave time to read, roughly at speaking pace.
        await new Promise(resolve => setTimeout(resolve, Math.min(12000, 1500 + turn.text.length * 55)));
    }
    setSpeaking(turn.member, false);
};

/**
 * Plays an examiner's turn. The audio is streamed from the server, so it starts
 * playing as soon as the first part has been synthesised.
 *
 * @param {number} turn Turn number.
 * @returns {Promise<boolean>} True if the audio was played to the end.
 */
const playTurnAudio = (turn) => new Promise(resolve => {
    const params = new URLSearchParams({
        action: 'tribunal_speech',
        cmid: cfg.cmid,
        submissionid: cfg.submissionid,
        turn: turn,
        sesskey: Config.sesskey,
    });
    const audio = new Audio(`${Config.wwwroot}/mod/aiviva/ajax.php?${params.toString()}`);
    let started = false;
    audio.addEventListener('playing', () => {
        started = true;
    });
    audio.addEventListener('ended', () => resolve(true));
    // An error before anything was heard falls back to a reading pause; afterwards the turn is simply over.
    audio.addEventListener('error', () => resolve(started));
    audio.play().catch(() => resolve(false));
});

/**
 * Marks the examiner who is preparing the next question.
 *
 * @param {number}  member   Member number (1-3); 0 clears every mark.
 * @param {boolean} thinking Whether they are thinking.
 */
const setThinking = (member, thinking) => {
    document.querySelectorAll('.tribunal-member').forEach(el => {
        el.classList.toggle('is-thinking', thinking && Number(el.dataset.member) === member);
    });
};

/**
 * Highlights the examiner who is speaking and switches their avatar loop.
 *
 * @param {number}  member   Member number (1-3).
 * @param {boolean} speaking Whether they are speaking.
 */
const setSpeaking = (member, speaking) => {
    document.querySelectorAll('.tribunal-member').forEach(el => {
        const isMember = Number(el.dataset.member) === member;
        el.classList.toggle('active-speaker', speaking && isMember);
        el.classList.toggle('inactive-speaker', speaking && !isMember);
    });
    const video = document.getElementById(`avatar_video_${member}`);
    if (video) {
        const src = speaking ? video.dataset.talking : video.dataset.idle;
        if (video.getAttribute('src') !== src) {
            video.src = src;
            video.play().catch(() => null);
        }
    }
};

/**
 * Appends a turn to the visible transcript.
 *
 * @param {number} member Member number, or 0 for the student.
 * @param {string} name   Examiner's name (ignored for the student).
 * @param {string} text   What was said.
 */
const appendToTranscript = (member, name, text) => {
    const item = document.createElement('div');
    item.className = 'transcript-item ' + (member ? `tribunal-turn member-${member}` : 'participant-turn');

    const label = document.createElement('span');
    label.className = 'speaker-label';
    label.textContent = member ? name : str.you;

    const message = document.createElement('span');
    message.className = 'message-text';
    message.textContent = text;

    item.append(label, message);
    els.transcript.appendChild(item);
    els.transcript.scrollTop = els.transcript.scrollHeight;
};

// ------------------------------------------------------------------
// Closing
// ------------------------------------------------------------------

/**
 * Ends the session: asks for the closing statement, then waits for the grade.
 */
const endSession = async() => {
    if (ended) {
        return;
    }
    ended = true;
    busy = true;
    clearInterval(clockTimer);
    els.timer.textContent = formatTime(0);
    els.answerBtn.classList.add('d-none');
    showStatus(els.status, str.tribunal_ending, 'info');

    // The server's clock is the one that counts; if ours ran slightly fast, try again shortly.
    for (let attempt = 0; attempt < 6; attempt++) {
        try {
            const data = await post('tribunal_closing', {cmid: cfg.cmid, submissionid: cfg.submissionid});
            if (data.turn) {
                await deliverTurn(data.turn);
            }
            break;
        } catch (e) {
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
    }

    if (stopMeter) {
        stopMeter();
    }
    if (micStream) {
        micStream.getTracks().forEach(track => track.stop());
    }

    showStatus(els.status, str.tribunal_finished, 'info');
    await waitForStatusChange(cfg, ['step3', 'submitted', 'grading']);
    window.location.reload();
};
