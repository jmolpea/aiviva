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
 * Teacher review page: the "regenerate AI analysis" buttons.
 *
 * @module     mod_aiviva/submissions
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {post, showStatus} from './utils';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

/**
 * Initialises the regenerate buttons.
 *
 * @param {Object} cfg
 * @param {number} cfg.cmid         Course module id.
 * @param {number} cfg.submissionid Submission being reviewed.
 */
export const init = (cfg) => {
    const buttons = [...document.querySelectorAll('.aiviva-regen-btn')];
    const statusEl = document.getElementById('aiviva-regen-status');

    buttons.forEach(button => button.addEventListener('click', async() => {
        try {
            await Notification.saveCancelPromise(
                button.textContent.trim(),
                await getString('regen_confirm', 'mod_aiviva'),
                await getString('regen_confirm_btn', 'mod_aiviva')
            );
        } catch (e) {
            return; // Cancelled.
        }

        buttons.forEach(b => {
            b.disabled = true;
        });
        showStatus(statusEl, await getString('regen_running', 'mod_aiviva'), 'info');

        try {
            await post(button.dataset.action, {cmid: cfg.cmid, submissionid: cfg.submissionid});
            showStatus(statusEl, await getString('regen_success', 'mod_aiviva'), 'success');
            window.location.reload();
        } catch (e) {
            showStatus(statusEl, e.message, 'danger');
            buttons.forEach(b => {
                b.disabled = false;
            });
        }
    }));
};
