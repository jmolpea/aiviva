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
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Makes an AJAX request to a Moodle web service URL.
 *
 * @param {string} url      - The endpoint URL.
 * @param {Object} data     - POST data (will be JSON-stringified).
 * @param {string} sesskey  - Moodle session key for CSRF protection.
 * @returns {Promise<Object>} Resolved with parsed JSON response.
 */
export const ajaxPost = (url, data, sesskey) => {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({...data, sesskey}),
    }).then(res => {
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }
        return res.json();
    });
};

/**
 * Uploads a file using multipart/form-data.
 *
 * @param {string}   url        - The upload endpoint URL.
 * @param {FormData} formData   - The FormData object containing the file.
 * @param {Function} onProgress - Progress callback (0-100).
 * @returns {Promise<Object>} Resolved with parsed JSON response.
 */
export const uploadFile = (url, formData, onProgress) => {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        if (onProgress) {
            xhr.upload.addEventListener('progress', e => {
                if (e.lengthComputable) {
                    onProgress(Math.round((e.loaded / e.total) * 100));
                }
            });
        }

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch (e) {
                    reject(new Error('Invalid JSON response'));
                }
            } else {
                reject(new Error(`HTTP ${xhr.status}`));
            }
        };
        xhr.onerror = () => reject(new Error('Network error'));
        xhr.send(formData);
    });
};

/**
 * Formats seconds into MM:SS display string.
 *
 * @param {number} totalSeconds - Total seconds remaining.
 * @returns {string} Formatted time string.
 */
export const formatTime = (totalSeconds) => {
    const minutes = Math.floor(Math.abs(totalSeconds) / 60);
    const seconds = Math.abs(totalSeconds) % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
};

/**
 * Plays a beep sound using the Web Audio API.
 *
 * @param {number} frequency  - Tone frequency in Hz (default 440).
 * @param {number} duration   - Duration in milliseconds (default 200).
 * @param {number} volume     - Volume 0-1 (default 0.3).
 */
export const playBeep = (frequency = 440, duration = 200, volume = 0.3) => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.frequency.value = frequency;
        gain.gain.setValueAtTime(volume, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration / 1000);

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration / 1000);
    } catch (e) {
        // AudioContext not available — silently ignore.
    }
};

/**
 * Shows a status message in a container element.
 *
 * @param {HTMLElement} el      - The container element.
 * @param {string}      message - The message text.
 * @param {string}      type    - Bootstrap alert type ('info', 'success', 'warning', 'danger').
 */
export const showStatus = (el, message, type = 'info') => {
    if (!el) {
        return;
    }
    el.className = `aiviva-status-message alert alert-${type}`;
    el.textContent = message;
    el.style.display = 'block';
};

/**
 * Extracts a video frame as a base64 JPEG string using a hidden canvas.
 *
 * @param {HTMLVideoElement} videoEl - The video element.
 * @param {number}           quality - JPEG quality 0-1 (default 0.7).
 * @returns {string} Base64 data URL.
 */
export const extractVideoFrame = (videoEl, quality = 0.7) => {
    const canvas = document.createElement('canvas');
    canvas.width  = videoEl.videoWidth  || 640;
    canvas.height = videoEl.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);
    // Return only the base64 portion (strip data: prefix).
    return canvas.toDataURL('image/jpeg', quality).split(',')[1];
};
