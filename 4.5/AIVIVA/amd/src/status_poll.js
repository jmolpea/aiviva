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
 * Waits for a background AI job to finish, then reloads the page so the
 * student moves on to the next step without having to do anything.
 *
 * @module     mod_aiviva/status_poll
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {waitForStatusChange, showStatus} from './utils';
import {get_string as getString} from 'core/str';

/** @type {number} After this long, tell the student they may leave and come back. */
const SLOW_NOTICE_MS = 3 * 60 * 1000;

/**
 * Initialises the poller.
 *
 * @param {Object} cfg
 * @param {number} cfg.cmid         Course module id.
 * @param {number} cfg.submissionid Submission id.
 * @param {string} cfg.status       Status the page was rendered with.
 */
export const init = (cfg) => {
    // While the evaluation runs the status moves from 'submitted' to 'grading'; both mean "wait".
    const waiting = ['submitted', 'grading'].includes(cfg.status) ? ['submitted', 'grading'] : [cfg.status];

    setTimeout(async() => {
        const message = await getString('waiting_slow', 'mod_aiviva');
        showStatus(document.getElementById('aiviva_poll_status'), message, 'info');
    }, SLOW_NOTICE_MS);

    waitForStatusChange(cfg, waiting).then(() => window.location.reload()).catch(() => null);
};
