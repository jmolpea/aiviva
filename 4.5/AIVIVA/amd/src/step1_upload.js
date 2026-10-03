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
 * Step 1 — PDF upload with drag & drop.
 *
 * @module     mod_aiviva/step1_upload
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {post, showStatus, showProgress, waitForStatusChange} from './utils';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

/** @type {Object} Module configuration passed from PHP. */
let cfg = {};

/** @type {File|null} Currently selected PDF file. */
let selectedFile = null;

/**
 * Initialises the Step 1 upload module.
 *
 * @param {Object} config - Configuration object from PHP.
 * @param {number} config.cmid         - Course module id.
 * @param {number} config.submissionid - Submission id.
 * @param {number} config.maxfilesize  - Max upload size in MB.
 */
export const init = (config) => {
    cfg = config;

    const dropzone = document.getElementById('aiviva_pdf_dropzone');
    const fileInput = document.getElementById('aiviva_pdf_input');
    const uploadBtn = document.getElementById('aiviva_pdf_upload_btn');
    const statusEl = document.getElementById('aiviva_pdf_status');

    if (!dropzone || !fileInput || !uploadBtn) {
        return;
    }

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

    // Click or keyboard to browse.
    dropzone.addEventListener('click', e => {
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });
    dropzone.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });
    fileInput.addEventListener('change', e => handleFileSelect(e.target.files[0], uploadBtn, statusEl));

    uploadBtn.addEventListener('click', () => {
        if (selectedFile) {
            confirmAndUpload(uploadBtn, statusEl, dropzone);
        }
    });
};

/**
 * Handles a file selection — validates it and enables the upload button.
 *
 * @param {File}        file      - The selected file.
 * @param {HTMLElement} uploadBtn - Upload button element.
 * @param {HTMLElement} statusEl  - Status message element.
 */
const handleFileSelect = async(file, uploadBtn, statusEl) => {
    if (!file) {
        return;
    }
    selectedFile = null;
    uploadBtn.disabled = true;

    if (file.type !== 'application/pdf') {
        showStatus(statusEl, await getString('error_not_pdf', 'mod_aiviva'), 'danger');
        return;
    }
    if (file.size > cfg.maxfilesize * 1024 * 1024) {
        showStatus(statusEl, await getString('error_file_too_large', 'mod_aiviva', cfg.maxfilesize), 'danger');
        return;
    }

    selectedFile = file;
    const size = (file.size / 1048576).toFixed(1) + ' MB';
    const selectedMsg = await getString('pdf_selected', 'mod_aiviva', {name: file.name, size});
    showStatus(statusEl, await getString('pdf_selected_next', 'mod_aiviva'), 'info');

    // Show the chosen file inside the drop zone itself, where the student is looking.
    const dropzone = document.getElementById('aiviva_pdf_dropzone');
    const label = dropzone.querySelector('.dropzone-label');
    if (label) {
        label.textContent = selectedMsg;
    }
    dropzone.classList.add('has-file');
    uploadBtn.disabled = false;
    uploadBtn.focus();
};

/**
 * Asks for confirmation, uploads the PDF, then waits for the analysis to finish.
 *
 * @param {HTMLElement} uploadBtn - Upload button (disabled during upload).
 * @param {HTMLElement} statusEl  - Status message element.
 * @param {HTMLElement} dropzone  - The drop zone, hidden once the upload succeeds.
 */
const confirmAndUpload = async(uploadBtn, statusEl, dropzone) => {
    try {
        await Notification.saveCancelPromise(
            await getString('upload_pdf', 'mod_aiviva'),
            await getString('confirm_pdf_upload', 'mod_aiviva'),
            await getString('upload_pdf', 'mod_aiviva')
        );
    } catch (e) {
        return; // Cancelled.
    }

    const progressEl = document.getElementById('aiviva_pdf_progress');
    uploadBtn.disabled = true;

    try {
        await post(
            'upload_pdf',
            {cmid: cfg.cmid, submissionid: cfg.submissionid, pdffile: selectedFile},
            pct => showProgress(progressEl, pct)
        );
    } catch (e) {
        showStatus(statusEl, e.message, 'danger');
        uploadBtn.disabled = false;
        return;
    }

    dropzone.classList.add('d-none');
    uploadBtn.classList.add('d-none');
    showStatus(statusEl, await getString('pdf_uploaded_analysing', 'mod_aiviva'), 'success');
    await waitForStatusChange(cfg, ['draft', 'step1']);
    window.location.reload();
};
