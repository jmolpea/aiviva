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
 * Backup task for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/aiviva/backup/moodle2/backup_aiviva_stepslib.php');

/**
 * Activity backup task for mod_aiviva.
 */
class backup_aiviva_activity_task extends backup_activity_task {
    /**
     * Defines settings specific to mod_aiviva backup.
     */
    protected function define_my_settings(): void {
        // No specific settings beyond the standard ones.
    }

    /**
     * Defines the backup steps.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_aiviva_activity_structure_step('aiviva_structure', 'aiviva.xml'));
    }

    /**
     * Encodes any content links found in the given content.
     *
     * @param string $content HTML content.
     * @return string Content with encoded links.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to view.php.
        $pattern = "/{$base}\/mod\/aiviva\/view\.php\?id=([0-9]+)/";
        $content = preg_replace($pattern, '$@AIVIVAVIEWBYID*$1@$', $content);

        // Link to index.php.
        $pattern = "/{$base}\/mod\/aiviva\/index\.php\?id=([0-9]+)/";
        $content = preg_replace($pattern, '$@AIVIVAINDEX*$1@$', $content);

        return $content;
    }
}
