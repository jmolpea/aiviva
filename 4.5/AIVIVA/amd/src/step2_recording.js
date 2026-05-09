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
 * Step 2 — Screen recording with countdown, timers, and video upload.
 *
 * @module     mod_aiviva/step2_recording
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {uploadFile, showStatus, formatTime, playBeep, extractVideoFrame} from './utils';
import {get_string as getString} from 'core/str';

let cfg = {};
let mediaRecorder   = null;
let recordedChunks  = [];
let recordingStream = null;
let recordingTimer  = null;
let elapsedSeconds  = 0;
let extractedFrames = [];

/**
 * Initialises the Step 2 recording module.
 *
 * @param {Object} config
 * @param {number} config.cmid          - Course module id.
 * @param {string} config.sesskey       - Moodle session key.
 * @param {number} config.submissionid  - Current submission id.
 * @param {number} config.durationmins  - Max recording duration in minutes.
 * @param {number} config.maxsizemb     - Max upload size in MB.
 */
export const init = (config) => {
    cfg = config;

    const startBtn   = document.getElementById('aiviva_rec_start_btn');
    const stopBtn    = document.getElementById('aiviva_rec_stop_btn');
    const statusEl   = document.getElementById('aiviva_video_status');

    if (!startBtn) {
        return;
    }

    startBtn.addEventListener('click', () => requestScreenCapture(startBtn, stopBtn, statusEl));
    stopBtn.addEventListener('click', () => stopRecording(stopBtn));
};

// ------------------------------------------------------------------
// Screen capture & recording
// ------------------------------------------------------------------

/**
 * Requests screen-capture permission and starts the countdown.
 *
 * @param {HTMLElement} startBtn - Start button.
 * @param {HTMLElement} stopBtn  - Stop button.
 * @param {HTMLElement} statusEl - Status message element.
 */
const requestScreenCapture = async (startBtn, stopBtn, statusEl) => {
    try {
        recordingStream = await navigator.mediaDevices.getDisplayMedia({
            video: {cursor: 'always'},
            audio: true,
        });

        // Also capture microphone and merge.
        try {
            const micStream = await navigator.mediaDevices.getUserMedia({audio: true, video: false});
            micStream.getAudioTracks().forEach(t => recordingStream.addTrack(t));
        } catch (e) {
            // Microphone not available — continue without it.
        }

        startBtn.disabled = true;
        await startCountdown(10, startBtn, stopBtn, statusEl);

    } catch (e) {
        const msg = await getString('error_screen_permission', 'mod_aiviva');
        showStatus(statusEl, msg, 'danger');
    }
};

/**
 * Runs a 10-second visual + audio countdown then starts recording.
 *
 * @param {number}      seconds  - Countdown duration.
 * @param {HTMLElement} startBtn - Start button.
 * @param {HTMLElement} stopBtn  - Stop button.
 * @param {HTMLElement} statusEl - Status element.
 */
const startCountdown = (seconds, startBtn, stopBtn, statusEl) => {
    return new Promise(resolve => {
        const countdownEl = document.getElementById('aiviva_countdown');
        if (countdownEl) {
            countdownEl.style.display = 'flex';
        }

        let remaining = seconds;

        const tick = async () => {
            if (countdownEl) {
                countdownEl.textContent = remaining;
                countdownEl.className = 'aiviva-countdown' + (remaining <= 3 ? ' urgent' : '');
            }

            if (remaining <= 3) {
                playBeep(880, 150, 0.4); // Higher pitch for final 3 seconds.
            } else {
                playBeep(440, 100, 0.2);
            }

            if (remaining <= 0) {
                if (countdownEl) {
                    countdownEl.style.display = 'none';
                }
                const goMsg = await getString('recording_started', 'mod_aiviva');
                showStatus(statusEl, goMsg, 'success');
                beginRecording(stopBtn, statusEl);
                resolve();
                return;
            }

            remaining--;
            setTimeout(tick, 1000);
        };

        tick();
    });
};

/**
 * Begins the actual MediaRecorder session.
 *
 * @param {HTMLElement} stopBtn  - Stop button.
 * @param {HTMLElement} statusEl - Status element.
 */
const beginRecording = (stopBtn, statusEl) => {
    recordedChunks = [];
    extractedFrames = [];

    const options = {mimeType: 'video/webm;codecs=vp9'};
    if (!MediaRecorder.isTypeSupported(options.mimeType)) {
        options.mimeType = 'video/webm';
    }

    mediaRecorder = new MediaRecorder(recordingStream, options);
    mediaRecorder.ondataavailable = e => {
        if (e.data.size > 0) {
            recordedChunks.push(e.data);
        }
    };
    mediaRecorder.onstop = () => onRecordingStopped(statusEl);

    mediaRecorder.start(1000); // Collect data every second.

    // Show REC indicator.
    document.getElementById('aiviva_rec_indicator')?.classList.remove('d-none');
    stopBtn?.classList.remove('d-none');

    // Start elapsed time timer.
    const totalSecs = cfg.durationmins * 60;
    elapsedSeconds = 0;
    const timerEl = document.getElementById('aiviva_rec_timer');
    const progressEl = document.getElementById('aiviva_rec_progress');

    recordingTimer = setInterval(async () => {
        elapsedSeconds++;
        const remaining = totalSecs - elapsedSeconds;

        if (timerEl) {
            timerEl.textContent = formatTime(remaining);
        }
        if (progressEl) {
            const pct = Math.min(100, (elapsedSeconds / totalSecs) * 100);
            progressEl.innerHTML = `<div class="progress">
                <div class="progress-bar bg-danger" style="width:${pct}%"></div>
            </div>`;
        }

        // Warnings.
        if (remaining === 120) {
            const msg = await getString('warning_2min', 'mod_aiviva');
            showStatus(statusEl, msg, 'warning');
            playBeep(550, 300, 0.3);
        } else if (remaining === 60) {
            const msg = await getString('warning_1min', 'mod_aiviva');
            showStatus(statusEl, msg, 'warning');
            playBeep(660, 500, 0.4);
        }

        // Auto-stop.
        if (remaining <= 0) {
            const msg = await getString('recording_time_up', 'mod_aiviva');
            showStatus(statusEl, msg, 'info');
            stopRecording(stopBtn);
        }
    }, 1000);

    // Frame extraction: every 30 seconds.
    const frameInterval = setInterval(() => {
        const videoEl = document.querySelector('.aiviva-recording-preview video');
        if (videoEl) {
            try {
                extractedFrames.push(extractVideoFrame(videoEl));
            } catch (e) {
                // Frame extraction failed — skip.
            }
        }
    }, 30000);

    // Attach interval ID to mediaRecorder for cleanup.
    mediaRecorder._frameInterval = frameInterval;
};

/**
 * Stops the recording and cleans up.
 *
 * @param {HTMLElement} stopBtn - Stop button.
 */
const stopRecording = (stopBtn) => {
    clearInterval(recordingTimer);

    if (mediaRecorder?._frameInterval) {
        clearInterval(mediaRecorder._frameInterval);
    }

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }

    recordingStream?.getTracks().forEach(t => t.stop());

    document.getElementById('aiviva_rec_indicator')?.classList.add('d-none');
    stopBtn?.classList.add('d-none');
};

/**
 * Called when MediaRecorder finishes — shows preview and upload controls.
 *
 * @param {HTMLElement} statusEl - Status element.
 */
const onRecordingStopped = async (statusEl) => {
    const blob    = new Blob(recordedChunks, {type: 'video/webm'});
    const url     = URL.createObjectURL(blob);
    const previewEl = document.getElementById('aiviva_video_preview');

    if (previewEl) {
        previewEl.innerHTML = `
            <video controls style="max-width:100%" src="${url}"></video>
            <p class="mt-2 text-muted">Size: ${(blob.size / 1048576).toFixed(1)} MB</p>
        `;
    }

    // Max file size check.
    const maxBytes = cfg.maxsizemb * 1024 * 1024;
    if (blob.size > maxBytes) {
        const msg = await getString('error_video_too_large', 'mod_aiviva', cfg.maxsizemb);
        showStatus(statusEl, msg, 'danger');
        return;
    }

    const confirmMsg = await getString('confirm_video_submit', 'mod_aiviva');
    const submitBtn  = document.createElement('button');
    submitBtn.className = 'btn btn-primary mt-3';
    submitBtn.textContent = await getString('submit_video', 'mod_aiviva');

    const retryBtn = document.createElement('button');
    retryBtn.className = 'btn btn-secondary mt-3 ms-2';
    retryBtn.textContent = await getString('retry_recording', 'mod_aiviva');

    if (previewEl) {
        previewEl.appendChild(submitBtn);
        previewEl.appendChild(retryBtn);
    }

    submitBtn.addEventListener('click', async () => {
        if (!window.confirm(confirmMsg)) {
            return;
        }
        submitBtn.disabled = true;
        retryBtn.disabled  = true;
        await uploadVideo(blob, statusEl);
    });

    retryBtn.addEventListener('click', () => {
        if (previewEl) {
            previewEl.innerHTML = '';
        }
        recordedChunks = [];
        extractedFrames = [];
        document.getElementById('aiviva_rec_start_btn').disabled = false;
    });
};

/**
 * Uploads the recorded video to the server.
 *
 * @param {Blob}        blob     - The recorded video blob.
 * @param {HTMLElement} statusEl - Status element.
 */
const uploadVideo = async (blob, statusEl) => {
    const progressEl = document.getElementById('aiviva_rec_progress');
    const uploadUrl  = M.cfg.wwwroot + '/mod/aiviva/ajax.php';

    const formData = new FormData();
    formData.append('videofile', blob, 'recording.webm');
    formData.append('cmid', cfg.cmid);
    formData.append('submissionid', cfg.submissionid);
    formData.append('sesskey', cfg.sesskey);
    formData.append('action', 'upload_video');
    formData.append('frames', JSON.stringify(extractedFrames.slice(0, 20)));

    const uploadingMsg = await getString('uploading_video', 'mod_aiviva');
    showStatus(statusEl, uploadingMsg, 'info');

    try {
        const onProgress = (pct) => {
            if (progressEl) {
                progressEl.innerHTML = `<div class="progress">
                    <div class="progress-bar" style="width:${pct}%">${pct}%</div>
                </div>`;
            }
            // Show "analysing" message as soon as file is fully uploaded,
            // before the server response arrives (analysis can take 1-3 min).
            if (pct >= 100) {
                getString('video_uploaded_analysing', 'mod_aiviva')
                    .then(msg => showStatus(statusEl, msg, 'success'));
            }
        };

        const result = await uploadFile(uploadUrl, formData, onProgress);

        if (result.success) {
            // Analysis is running in background — show a refresh button after 15 s.
            scheduleRefreshButton(statusEl);
        } else {
            showStatus(statusEl, result.error || 'Upload failed', 'danger');
        }
    } catch (e) {
        showStatus(statusEl, e.message, 'danger');
    }
};

/**
 * Schedules a "Continue" button that reloads the page after a delay.
 * On reload, view.php reads the submission status from the DB and shows
 * the correct step automatically.
 *
 * @param {HTMLElement} statusEl - Status element used as insertion reference.
 * @param {number}      [delay]  - Milliseconds to wait before showing (default 15 000).
 */
const scheduleRefreshButton = (statusEl, delay = 15000) => {
    setTimeout(async () => {
        const label = await getString('continue_to_step3', 'mod_aiviva');
        const btn = document.createElement('button');
        btn.className = 'btn btn-success btn-lg mt-3 d-block mx-auto';
        btn.textContent = label;
        btn.addEventListener('click', () => {
            btn.disabled = true;
            window.location.reload();
        });
        statusEl?.parentNode?.insertBefore(btn, statusEl.nextSibling);
    }, delay);
};
