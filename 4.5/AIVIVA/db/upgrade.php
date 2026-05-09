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
 * Upgrade script for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the database structure for mod_aiviva.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool always true.
 */
function xmldb_aiviva_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024032203) {
        // Add tribunal_briefing column to aiviva_submissions.
        $table = new xmldb_table('aiviva_submissions');
        $field = new xmldb_field(
            'tribunal_briefing',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'tribunal_analysis'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2024032203, 'aiviva');
    }

    if ($oldversion < 2024032202) {
        // Add aiviva_overrides table.
        $table = new xmldb_table('aiviva_overrides');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('aiviva', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('max_attempts', XMLDB_TYPE_INTEGER, '4', null, null);
        $table->add_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('aiviva', XMLDB_KEY_FOREIGN, ['aiviva'], 'aiviva', ['id']);

        $table->add_index('aiviva_userid', XMLDB_INDEX_NOTUNIQUE, ['aiviva', 'userid']);
        $table->add_index('aiviva_groupid', XMLDB_INDEX_NOTUNIQUE, ['aiviva', 'groupid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2024032202, 'aiviva');
    }

    return true;
}
