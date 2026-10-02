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
 * Shared utility functions for mod_aiviva AMD modules.
 *
 * @module     mod_aiviva/utils
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';

const ENDPOINT = Config.wwwroot + '/mod/aiviva/ajax.php';

/** @type {AudioContext|null} Shared audio context, created on first use after a user gesture. */
let audioContext = null;

/**
 * Returns the shared AudioContext, creating and resuming it if needed.
 *
 * @returns {AudioContext|null} Null if the browser has no Web Audio support.
 */
export const getAudioContext = () => {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) {
        return null;
    }
    if (!audioContext) {
        audioContext = new Ctx();
    }
    if (audioContext.state === 'suspended') {
        audioContext.resume().catch(() => null);
    }
    return audioContext;
};

/**
 * Sends a request to the plugin's AJAX endpoint.
 *
 * @param {string}   action       Endpoint action.
 * @param {Object}   params       Request fields. Blob values are sent as files; an array
 *                                [blob, filename] sets the file name.
 * @param {Function} [onProgress] Upload progress callback (0-100).
 * @returns {Promise<Object>} Resolves with the response; rejects with an Error carrying the server message.
 */
export const post = (action, params = {}, onProgress = null) => {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('sesskey', Config.sesskey);
    Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            formData.append(key, value[0], value[1]);
        } else {
            formData.append(key, value);
        }
    });

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', ENDPOINT, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        if (onProgress) {
            xhr.upload.addEventListener('progress', e => {
                if (e.lengthComputable) {
                    onProgress(Math.round((e.loaded / e.total) * 100));
                }
            });
        }

        xhr.onload = () => {
            let data = null;
            try {
                data = JSON.parse(xhr.responseText);
            } catch (e) {
                reject(new Error(`HTTP ${xhr.status}`));
                return;
            }
            if (data && data.success) {
                resolve(data);
            } else {
                reject(new Error((data && data.error) || `HTTP ${xhr.status}`));
            }
        };
        xhr.onerror = () => reject(new Error('Network error'));
        xhr.send(formData);
    });
};

/**
 * Polls the attempt status until it leaves the given set of statuses.
 *
 * Network errors are tolerated: the poll simply carries on.
 *
 * @param {Object}   cfg           Module config with cmid and submissionid.
 * @param {string[]} whileStatuses Statuses that mean "still waiting".
 * @param {number}   [intervalMs]  Delay between polls.
 * @returns {Promise<string>} Resolves with the new status.
 */
export const waitForStatusChange = (cfg, whileStatuses, intervalMs = 4000) => {
    const url = `${ENDPOINT}?action=status&cmid=${cfg.cmid}&submissionid=${cfg.submissionid}`;
    return new Promise(resolve => {
        const poll = async() => {
            try {
                const response = await fetch(url, {credentials: 'same-origin'});
                const data = await response.json();
                if (data.success && !whileStatuses.includes(data.status)) {
                    resolve(data.status);
                    return;
                }
            } catch (e) {
                // Keep polling.
            }
            setTimeout(poll, intervalMs);
        };
        setTimeout(poll, intervalMs);
    });
};

/**
 * Formats seconds into MM:SS display string.
 *
 * @param {number} totalSeconds - Total seconds remaining.
 * @returns {string} Formatted time string.
 */
export const formatTime = (totalSeconds) => {
    const safe = Math.max(0, Math.floor(totalSeconds));
    const minutes = Math.floor(safe / 60);
    const seconds = safe % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

/**
 * Plays a beep sound using the Web Audio API.
 *
 * @param {number} frequency  - Tone frequency in Hz.
 * @param {number} duration   - Duration in milliseconds.
 * @param {number} volume     - Volume 0-1.
 */
export const playBeep = (frequency = 440, duration = 200, volume = 0.3) => {
    const ctx = getAudioContext();
    if (!ctx) {
        return;
    }
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.frequency.value = frequency;
    gain.gain.setValueAtTime(volume, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);
    osc.start(ctx.currentTime);
    osc.stop(ctx.currentTime + duration / 1000);
};

/**
 * Shows a status message in a container element.
 *
 * @param {HTMLElement} el      - The container element.
 * @param {string}      message - The message text ('' hides the element).
 * @param {string}      type    - Bootstrap alert type ('info', 'success', 'warning', 'danger').
 */
export const showStatus = (el, message, type = 'info') => {
    if (!el) {
        return;
    }
    if (!message) {
        el.className = 'aiviva-status-message';
        el.textContent = '';
        return;
    }
    el.className = `aiviva-status-message alert alert-${type}`;
    el.textContent = message;
};

/**
 * Renders a Bootstrap progress bar inside a container.
 *
 * @param {HTMLElement} el      Container element.
 * @param {number}      percent 0-100.
 * @param {boolean}     [label] Whether to print the percentage inside the bar.
 * @param {string}      [extraClass] Extra class for the bar (e.g. 'bg-danger').
 */
export const showProgress = (el, percent, label = true, extraClass = '') => {
    if (!el) {
        return;
    }
    let bar = el.querySelector('.progress-bar');
    if (!bar) {
        const wrapper = document.createElement('div');
        wrapper.className = 'progress';
        bar = document.createElement('div');
        bar.className = 'progress-bar';
        bar.setAttribute('role', 'progressbar');
        wrapper.appendChild(bar);
        el.replaceChildren(wrapper);
    }
    const value = Math.max(0, Math.min(100, Math.round(percent)));
    bar.className = 'progress-bar ' + extraClass;
    bar.style.width = value + '%';
    bar.setAttribute('aria-valuenow', value);
    bar.textContent = label ? value + '%' : '';
};

/**
 * Captures the current frame of a video element as a base64 JPEG.
 *
 * The frame is scaled down so that its longest side is at most maxSide pixels.
 *
 * @param {HTMLVideoElement} videoEl - The video element.
 * @param {number}           maxSide - Longest side of the output, in pixels.
 * @param {number}           quality - JPEG quality 0-1.
 * @returns {string|null} Base64 JPEG data (no data: prefix), or null if the video has no picture yet.
 */
export const extractVideoFrame = (videoEl, maxSide = 1280, quality = 0.6) => {
    if (!videoEl.videoWidth || !videoEl.videoHeight) {
        return null;
    }
    const scale = Math.min(1, maxSide / Math.max(videoEl.videoWidth, videoEl.videoHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(videoEl.videoWidth * scale);
    canvas.height = Math.round(videoEl.videoHeight * scale);
    canvas.getContext('2d').drawImage(videoEl, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', quality).split(',')[1];
};

/**
 * Returns the first recording format the browser supports.
 *
 * @param {string[]} candidates MIME types in order of preference.
 * @returns {string} A supported MIME type, or '' to let the browser choose.
 */
export const pickMimeType = (candidates) => {
    return candidates.find(type => window.MediaRecorder && MediaRecorder.isTypeSupported(type)) || '';
};

/**
 * Drives a level bar from a live audio stream, so the user can see the microphone working.
 *
 * @param {MediaStream} stream  Audio stream.
 * @param {HTMLElement} barEl   Element whose width shows the level.
 * @param {Function}    [onSound] Called once, the first time sound is clearly detected.
 * @returns {Function} Call it to stop the meter.
 */
export const startLevelMeter = (stream, barEl, onSound = null) => {
    const ctx = getAudioContext();
    if (!ctx || !barEl) {
        return () => null;
    }
    const source = ctx.createMediaStreamSource(stream);
    const analyser = ctx.createAnalyser();
    analyser.fftSize = 512;
    source.connect(analyser);

    const samples = new Uint8Array(analyser.fftSize);
    let frame = null;
    let heard = false;

    const draw = () => {
        analyser.getByteTimeDomainData(samples);
        let sum = 0;
        for (let i = 0; i < samples.length; i++) {
            const v = (samples[i] - 128) / 128;
            sum += v * v;
        }
        const level = Math.min(1, Math.sqrt(sum / samples.length) * 4);
        barEl.style.width = Math.round(level * 100) + '%';
        if (!heard && level > 0.08) {
            heard = true;
            if (onSound) {
                onSound();
            }
        }
        frame = requestAnimationFrame(draw);
    };
    frame = requestAnimationFrame(draw);

    return () => {
        cancelAnimationFrame(frame);
        source.disconnect();
        barEl.style.width = '0%';
    };
};
