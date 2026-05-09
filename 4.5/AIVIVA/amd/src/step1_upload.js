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
 * Step 1 — PDF upload and analysis UI for mod_aiviva.
 *
 * @module     mod_aiviva/step1_upload
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {uploadFile, showStatus} from './utils';
import {get_string as getString} from 'core/str';

/** @type {Object} Module configuration passed from PHP. */
let cfg = {};

/** @type {File|null} Currently selected PDF file. */
let selectedFile = null;

/**
 * Initialises the Step 1 upload module.
 *
 * @param {Object} config - Configuration object from PHP.
 * @param {number} config.cmid         - Course module id.
 * @param {string} config.sesskey      - Moodle session key.
 * @param {number} config.submissionid - Submission id (0 if not yet created).
 * @param {number} config.maxfilesize  - Max upload size in MB.
 */
export const init = (config) => {
    cfg = config;

    const dropzone   = document.getElementById('aiviva_pdf_dropzone');
    const fileInput  = document.getElementById('aiviva_pdf_input');
    const uploadBtn  = document.getElementById('aiviva_pdf_upload_btn');
    const progressEl = document.getElementById('aiviva_pdf_progress');
    const statusEl   = document.getElementById('aiviva_pdf_status');

    if (!dropzone || !fileInput) {
        return;
    }

    // Drag & drop events.
    dropzone.addEventListener('dragover', e => {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        handleFileSelect(e.dataTransfer.files[0], uploadBtn, statusEl);
    });

    // Click to browse.
    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', e => handleFileSelect(e.target.files[0], uploadBtn, statusEl));

    // Upload button.
    uploadBtn.addEventListener('click', () => {
        if (!selectedFile) {
            return;
        }
        confirmAndUpload(selectedFile, progressEl, statusEl, uploadBtn);
    });
};

/**
 * Handles a file selection — validates it and enables the upload button.
 *
 * @param {File}        file      - The selected file.
 * @param {HTMLElement} uploadBtn - Upload button element.
 * @param {HTMLElement} statusEl  - Status message element.
 */
const handleFileSelect = async (file, uploadBtn, statusEl) => {
    if (!file) {
        return;
    }

    // Validate MIME type.
    if (file.type !== 'application/pdf') {
        const msg = await getString('error_not_pdf', 'mod_aiviva');
        showStatus(statusEl, msg, 'danger');
        return;
    }

    // Validate file size.
    const maxBytes = cfg.maxfilesize * 1024 * 1024;
    if (file.size > maxBytes) {
        const msg = await getString('error_file_too_large', 'mod_aiviva', cfg.maxfilesize);
        showStatus(statusEl, msg, 'danger');
        return;
    }

    selectedFile = file;
    const msg = await getString('pdf_selected', 'mod_aiviva', {name: file.name, size: formatBytes(file.size)});
    showStatus(statusEl, msg, 'info');
    uploadBtn.disabled = false;
};

/**
 * Shows a confirmation dialog then uploads the PDF.
 *
 * @param {File}        file       - PDF file to upload.
 * @param {HTMLElement} progressEl - Progress bar element.
 * @param {HTMLElement} statusEl   - Status message element.
 * @param {HTMLElement} uploadBtn  - Upload button (disabled during upload).
 */
const confirmAndUpload = async (file, progressEl, statusEl, uploadBtn) => {
    const confirmMsg = await getString('confirm_pdf_upload', 'mod_aiviva');
    if (!window.confirm(confirmMsg)) {
        return;
    }

    uploadBtn.disabled = true;
    progressEl.style.display = 'block';
    progressEl.innerHTML = '<div class="progress"><div class="progress-bar" style="width:0%"></div></div>';

    const formData = new FormData();
    formData.append('pdffile', file);
    formData.append('cmid', cfg.cmid);
    formData.append('submissionid', cfg.submissionid);
    formData.append('sesskey', cfg.sesskey);
    formData.append('action', 'upload_pdf');

    const uploadUrl = M.cfg.wwwroot + '/mod/aiviva/ajax.php';

    try {
        const onProgress = (pct) => {
            const bar = progressEl.querySelector('.progress-bar');
            if (bar) {
                bar.style.width = pct + '%';
                bar.textContent = pct + '%';
            }
            // Show "analysing" message as soon as file is fully uploaded,
            // before the server response arrives (analysis can take 30-60 s).
            if (pct >= 100) {
                getString('pdf_uploaded_analysing', 'mod_aiviva')
                    .then(msg => showStatus(statusEl, msg, 'success'));
            }
        };

        const result = await uploadFile(uploadUrl, formData, onProgress);

        if (result.success) {
            // Analysis is running in background — show a refresh button after 15 s.
            scheduleRefreshButton(statusEl);
        } else {
            showStatus(statusEl, result.error || 'Upload failed', 'danger');
            uploadBtn.disabled = false;
        }
    } catch (e) {
        showStatus(statusEl, e.message, 'danger');
        uploadBtn.disabled = false;
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
        const label = await getString('continue_to_step2', 'mod_aiviva');
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


/**
 * Formats bytes into a human-readable string.
 *
 * @param {number} bytes - Number of bytes.
 * @returns {string} Formatted string.
 */
const formatBytes = (bytes) => {
    if (bytes < 1024) {
        return bytes + ' B';
    }
    if (bytes < 1048576) {
        return (bytes / 1024).toFixed(1) + ' KB';
    }
    return (bytes / 1048576).toFixed(1) + ' MB';
};
