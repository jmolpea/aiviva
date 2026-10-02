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
 * Step 2 — Screen recording of the presentation.
 *
 * Three things are produced and uploaded together:
 *  - the screen recording with the mixed audio (for the teacher);
 *  - a small audio-only recording (what the server transcribes);
 *  - screenshots taken at regular intervals (what the AI looks at).
 *
 * @module     mod_aiviva/step2_recording
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
    post, showStatus, showProgress, formatTime, playBeep, extractVideoFrame,
    getAudioContext, pickMimeType, waitForStatusChange,
} from './utils';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

/** @type {number} Seconds of countdown before the recording starts. */
const COUNTDOWN_SECS = 5;

/** @type {number} Upper bound on screenshots, matching the server's limit. */
const MAX_FRAMES = 40;

let cfg = {};
let els = {};
let displayStream = null;
let micStream = null;
let audioNodes = [];
let videoRecorder = null;
let audioRecorder = null;
let videoChunks = [];
let audioChunks = [];
let frames = [];
let recordingTimer = null;
let frameTimer = null;
let elapsedSeconds = 0;
let previewUrl = null;

/**
 * Initialises the Step 2 recording module.
 *
 * @param {Object} config
 * @param {number} config.cmid          - Course module id.
 * @param {number} config.submissionid  - Current submission id.
 * @param {number} config.durationmins  - Max recording duration in minutes.
 * @param {number} config.maxsizemb     - Max upload size in MB.
 */
export const init = (config) => {
    cfg = config;
    els = {
        start: document.getElementById('aiviva_rec_start_btn'),
        stop: document.getElementById('aiviva_rec_stop_btn'),
        status: document.getElementById('aiviva_video_status'),
        countdown: document.getElementById('aiviva_countdown'),
        indicator: document.getElementById('aiviva_rec_indicator'),
        timer: document.getElementById('aiviva_rec_timer'),
        progress: document.getElementById('aiviva_rec_progress'),
        live: document.getElementById('aiviva_live_preview'),
        preview: document.getElementById('aiviva_video_preview'),
    };
    if (!els.start) {
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia || !window.MediaRecorder) {
        els.start.disabled = true;
        getString('error_browser_unsupported', 'mod_aiviva').then(msg => showStatus(els.status, msg, 'danger')).catch(() => null);
        return;
    }

    els.start.addEventListener('click', requestCapture);
    els.stop.addEventListener('click', stopRecording);
};

/**
 * Asks for the microphone and the screen, then starts the countdown.
 */
const requestCapture = async() => {
    els.start.disabled = true;
    showStatus(els.status, '');

    try {
        // The microphone first: without the student's voice there is nothing to assess.
        micStream = await navigator.mediaDevices.getUserMedia({audio: true, video: false});
    } catch (e) {
        showStatus(els.status, await getString('error_mic_permission', 'mod_aiviva'), 'danger');
        els.start.disabled = false;
        return;
    }

    try {
        displayStream = await navigator.mediaDevices.getDisplayMedia({video: true, audio: true});
    } catch (e) {
        releaseStreams();
        showStatus(els.status, await getString('error_screen_permission', 'mod_aiviva'), 'danger');
        els.start.disabled = false;
        return;
    }

    // If the student ends the share from the browser's own bar, finish the recording cleanly.
    displayStream.getVideoTracks()[0].addEventListener('ended', stopRecording);

    els.live.srcObject = displayStream;
    els.live.classList.remove('d-none');
    els.live.play().catch(() => null);

    await runCountdown();
    beginRecording();
};

/**
 * Runs the visual and audible countdown.
 *
 * @returns {Promise<void>} Resolves when the countdown reaches zero.
 */
const runCountdown = () => new Promise(resolve => {
    let remaining = COUNTDOWN_SECS;
    els.countdown.style.display = 'flex';

    const tick = () => {
        if (remaining <= 0) {
            els.countdown.style.display = 'none';
            resolve();
            return;
        }
        els.countdown.textContent = remaining;
        els.countdown.className = 'aiviva-countdown' + (remaining <= 3 ? ' urgent' : '');
        playBeep(remaining <= 3 ? 880 : 440, 120, 0.2);
        remaining--;
        setTimeout(tick, 1000);
    };
    tick();
});

/**
 * Mixes the microphone with any audio shared from the screen.
 *
 * @returns {MediaStream} A stream with a single mixed audio track.
 */
const mixAudio = () => {
    const ctx = getAudioContext();
    if (!ctx) {
        return micStream;
    }
    const destination = ctx.createMediaStreamDestination();
    const sources = [micStream];
    if (displayStream.getAudioTracks().length) {
        sources.push(new MediaStream(displayStream.getAudioTracks()));
    }
    sources.forEach(stream => {
        const node = ctx.createMediaStreamSource(stream);
        node.connect(destination);
        audioNodes.push(node);
    });
    return destination.stream;
};

/**
 * Starts both recorders, the timer and the screenshot capture.
 */
const beginRecording = async() => {
    if (!displayStream || !displayStream.active) {
        // The share was cancelled during the countdown.
        resetToStart();
        return;
    }

    videoChunks = [];
    audioChunks = [];
    frames = [];

    const mixed = mixAudio();
    const combined = new MediaStream([...displayStream.getVideoTracks(), ...mixed.getAudioTracks()]);

    const videoType = pickMimeType(['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4']);
    const audioType = pickMimeType(['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4']);

    videoRecorder = new MediaRecorder(combined, videoType ? {mimeType: videoType} : {});
    audioRecorder = new MediaRecorder(mixed, {
        ...(audioType ? {mimeType: audioType} : {}),
        audioBitsPerSecond: 32000,
    });
    videoRecorder.ondataavailable = e => e.data.size > 0 && videoChunks.push(e.data);
    audioRecorder.ondataavailable = e => e.data.size > 0 && audioChunks.push(e.data);

    const stopped = Promise.all([videoRecorder, audioRecorder].map(
        recorder => new Promise(resolve => recorder.addEventListener('stop', resolve, {once: true}))
    ));
    stopped.then(onRecordingStopped).catch(Notification.exception);

    videoRecorder.start(1000);
    audioRecorder.start(1000);

    els.indicator.classList.remove('d-none');
    els.stop.classList.remove('d-none');
    showStatus(els.status, await getString('recording_started', 'mod_aiviva'), 'success');

    const totalSecs = cfg.durationmins * 60;
    elapsedSeconds = 0;
    els.timer.textContent = formatTime(totalSecs);

    recordingTimer = setInterval(async() => {
        elapsedSeconds++;
        const remaining = totalSecs - elapsedSeconds;
        els.timer.textContent = formatTime(remaining);
        showProgress(els.progress, (elapsedSeconds / totalSecs) * 100, false, 'bg-danger');

        if (remaining === 120) {
            playBeep(550, 300, 0.3);
            showStatus(els.status, await getString('warning_2min', 'mod_aiviva'), 'warning');
        } else if (remaining === 60) {
            playBeep(660, 500, 0.4);
            showStatus(els.status, await getString('warning_1min', 'mod_aiviva'), 'warning');
        } else if (remaining <= 0) {
            showStatus(els.status, await getString('recording_time_up', 'mod_aiviva'), 'info');
            stopRecording();
        }
    }, 1000);

    // Screenshots: spread evenly so that even the longest recording stays within the limit.
    const frameEvery = Math.max(15, Math.ceil(totalSecs / (MAX_FRAMES - 2)));
    const capture = () => {
        if (frames.length >= MAX_FRAMES) {
            return;
        }
        const frame = extractVideoFrame(els.live);
        if (frame) {
            frames.push(frame);
        }
    };
    setTimeout(capture, 2000);
    frameTimer = setInterval(capture, frameEvery * 1000);
};

/**
 * Stops the recording. Safe to call more than once.
 */
const stopRecording = () => {
    clearInterval(recordingTimer);
    clearInterval(frameTimer);

    // One last screenshot of the final slide.
    if (videoRecorder && videoRecorder.state !== 'inactive' && frames.length < MAX_FRAMES) {
        const frame = extractVideoFrame(els.live);
        if (frame) {
            frames.push(frame);
        }
    }

    [videoRecorder, audioRecorder].forEach(recorder => {
        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        }
    });

    releaseStreams();
    els.indicator.classList.add('d-none');
    els.stop.classList.add('d-none');
    els.live.classList.add('d-none');
};

/**
 * Stops every captured track and disconnects the audio mix.
 */
const releaseStreams = () => {
    audioNodes.forEach(node => node.disconnect());
    audioNodes = [];
    [displayStream, micStream].forEach(stream => stream && stream.getTracks().forEach(track => track.stop()));
    displayStream = null;
    micStream = null;
    if (els.live) {
        els.live.srcObject = null;
    }
};

/**
 * Returns the UI to its initial state so the student can record again.
 */
const resetToStart = () => {
    releaseStreams();
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }
    els.preview.replaceChildren();
    els.progress.replaceChildren();
    els.timer.textContent = '';
    els.live.classList.add('d-none');
    showStatus(els.status, '');
    els.start.disabled = false;
};

/**
 * Called when both recorders have finished: shows the preview with submit / retry.
 */
const onRecordingStopped = async() => {
    const videoBlob = new Blob(videoChunks, {type: (videoRecorder.mimeType || 'video/webm').split(';')[0]});
    const audioBlob = new Blob(audioChunks, {type: (audioRecorder.mimeType || 'audio/webm').split(';')[0]});
    const sizeMb = videoBlob.size / 1048576;

    const [submitLabel, retryLabel, sizeLabel] = await Promise.all([
        getString('submit_video', 'mod_aiviva'),
        getString('retry_recording', 'mod_aiviva'),
        getString('recording_size', 'mod_aiviva', sizeMb.toFixed(1)),
    ]);

    previewUrl = URL.createObjectURL(videoBlob);
    const video = document.createElement('video');
    video.controls = true;
    video.src = previewUrl;
    video.className = 'aiviva-review-video';

    const size = document.createElement('p');
    size.className = 'mt-2 text-muted';
    size.textContent = sizeLabel;

    const submitBtn = document.createElement('button');
    submitBtn.type = 'button';
    submitBtn.className = 'btn btn-primary mt-3';
    submitBtn.textContent = submitLabel;

    const retryBtn = document.createElement('button');
    retryBtn.type = 'button';
    retryBtn.className = 'btn btn-secondary mt-3 ms-2';
    retryBtn.textContent = retryLabel;

    els.preview.replaceChildren(video, size, submitBtn, retryBtn);
    els.progress.replaceChildren();

    if (sizeMb > cfg.maxsizemb) {
        submitBtn.disabled = true;
        showStatus(els.status, await getString('error_video_too_large', 'mod_aiviva', cfg.maxsizemb), 'danger');
    } else {
        showStatus(els.status, await getString('recording_review', 'mod_aiviva'), 'info');
    }

    retryBtn.addEventListener('click', resetToStart);
    submitBtn.addEventListener('click', async() => {
        try {
            await Notification.saveCancelPromise(submitLabel, await getString('confirm_video_submit', 'mod_aiviva'), submitLabel);
        } catch (e) {
            return; // Cancelled.
        }
        submitBtn.disabled = true;
        retryBtn.disabled = true;
        const uploaded = await uploadRecording(videoBlob, audioBlob);
        if (!uploaded) {
            submitBtn.disabled = false;
            retryBtn.disabled = false;
        }
    });
};

/**
 * Uploads the recording, then waits for the analysis to finish.
 *
 * @param {Blob} videoBlob The screen recording.
 * @param {Blob} audioBlob The audio-only recording.
 * @returns {Promise<boolean>} False if the upload failed and can be retried.
 */
const uploadRecording = async(videoBlob, audioBlob) => {
    showStatus(els.status, await getString('uploading_video', 'mod_aiviva'), 'info');

    const extension = blob => (blob.type.includes('mp4') ? 'mp4' : 'webm');
    const params = {
        cmid: cfg.cmid,
        submissionid: cfg.submissionid,
        videofile: [videoBlob, 'recording.' + extension(videoBlob)],
        frames: JSON.stringify(frames),
    };
    if (audioBlob.size > 0) {
        params.audiofile = [audioBlob, 'audio.' + extension(audioBlob)];
    }

    try {
        await post('upload_video', params, pct => showProgress(els.progress, pct));
    } catch (e) {
        showStatus(els.status, e.message, 'danger');
        return false;
    }

    els.preview.replaceChildren();
    showStatus(els.status, await getString('video_uploaded_analysing', 'mod_aiviva'), 'success');
    await waitForStatusChange(cfg, ['step2']);
    window.location.reload();
    return true;
};
