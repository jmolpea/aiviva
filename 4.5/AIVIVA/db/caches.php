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
 * Cache definitions for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Per-user, per-minute API call counter for rate limiting.
    'ratelimit' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 120, // 2 minutes; keys are keyed to minute windows.
        'simplekeys' => true,
        'simpledata' => true,
    ],
    // Opening words of a tribunal session, prepared before the student presses start.
    'tribunal' => [
        'mode'       => cache_store::MODE_APPLICATION,
        'ttl'        => 7200,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];
