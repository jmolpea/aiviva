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
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the database structure for mod_aiviva.
 *
 * Upgrade blocks MUST appear in strictly ascending version order, and the
 * version passed to upgrade_mod_savepoint() must match the one tested in the
 * surrounding condition. Adding a block out of order makes the savepoint go
 * backwards, which raises downgrade_exception and aborts the upgrade halfway.
 *
 * @param int $oldversion the version we are upgrading from.
 * @return bool always true.
 */
function xmldb_aiviva_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

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

    if ($oldversion < 2026100100) {
        // Move activities off retired OpenAI models and update the column defaults.
        $table = new xmldb_table('aiviva');
        $modelmap = \mod_aiviva\form\mod_form_helper::get_legacy_model_map();
        foreach (['openai_model_pdf', 'openai_model_tribunal', 'openai_model_eval'] as $fieldname) {
            foreach ($modelmap as $retired => $replacement) {
                $DB->set_field('aiviva', $fieldname, $replacement, [$fieldname => $retired]);
            }
            $field = new xmldb_field(
                $fieldname,
                XMLDB_TYPE_CHAR,
                '50',
                null,
                XMLDB_NOTNULL,
                null,
                \mod_aiviva\form\mod_form_helper::DEFAULT_MODEL
            );
            $dbman->change_field_default($table, $field);
        }

        // The per-model checkboxes were replaced by the 'enabled_models' setting.
        unset_config('enable_gpt4o', 'mod_aiviva');
        unset_config('enable_gpt4o_mini', 'mod_aiviva');

        upgrade_mod_savepoint(true, 2026100100, 'aiviva');
    }

    if ($oldversion < 2026100200) {
        // Activity: availability window, PDF size limit and grade weights.
        $table = new xmldb_table('aiviva');
        $fields = [
            new xmldb_field('timeopen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'max_attempts'),
            new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeopen'),
            new xmldb_field('step1_maxfilesize', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '20', 'timeclose'),
            new xmldb_field('weight_pdf', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '33', 'grade'),
            new xmldb_field('weight_video', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '33', 'weight_pdf'),
            new xmldb_field('weight_tribunal', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '34', 'weight_video'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Completion by grade is handled by core; the custom columns were never used.
        foreach (['completiongrade', 'completionmingradeval'] as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // Submission: server-side tribunal clock and the AI-proposed grade.
        $table = new xmldb_table('aiviva_submissions');
        $fields = [
            new xmldb_field('tribunal_timestart', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'tribunal_briefing'),
            new xmldb_field('ai_grade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null, 'tribunal_timestart'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Grades produced before this version were all AI grades unless a teacher edited them.
        $DB->execute(
            "UPDATE {aiviva_submissions} SET ai_grade = final_grade WHERE ai_grade IS NULL AND grader_userid IS NULL"
        );

        // Settings that were never read by the plugin.
        unset_config('disk_warning_threshold_gb', 'mod_aiviva');
        unset_config('ffmpeg_path', 'mod_aiviva');

        upgrade_mod_savepoint(true, 2026100200, 'aiviva');
    }

    if ($oldversion < 2026100201) {
        // The privacy notice setting used to be pre-filled with the old built-in text, which
        // contained an unreplaced placeholder and statements that are no longer accurate.
        // Clearing it makes the current built-in notice (translated, with the real retention) apply.
        $notice = (string)get_config('mod_aiviva', 'gdpr_notice_text');
        if (strpos($notice, '{$a}') !== false) {
            set_config('gdpr_notice_text', '', 'mod_aiviva');
        }

        // Limits saved with the previous, lower defaults would cut long analyses short or
        // time out on large documents now that work is always sent in full.
        $minimums = ['safety_max_tokens' => 16000, 'api_timeout' => 300, 'api_rate_limit' => 30];
        foreach ($minimums as $name => $minimum) {
            $current = get_config('mod_aiviva', $name);
            if ($current !== false && (int)$current < $minimum) {
                set_config($name, $minimum, 'mod_aiviva');
            }
        }

        upgrade_mod_savepoint(true, 2026100201, 'aiviva');
    }

    if ($oldversion < 2026100300) {
        // The per-activity API key was never used: keys are a site setting.
        $table = new xmldb_table('aiviva');
        $field = new xmldb_field('openai_apikey');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // API keys saved as plain text are now stored encrypted.
        foreach (['openai_apikey', 'openai_apikey_secondary'] as $name) {
            $key = (string)get_config('mod_aiviva', $name);
            if ($key === '' || preg_match('/^(sodium|openssl-aes-256-ctr):/', $key)) {
                continue;
            }
            try {
                set_config($name, \core\encryption::encrypt($key), 'mod_aiviva');
            } catch (\Throwable $e) {
                // No encryption key can be created on this site: the key keeps working as it is.
                debugging('aiviva: the API key could not be encrypted: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        upgrade_mod_savepoint(true, 2026100300, 'aiviva');
    }

    if ($oldversion < 2026100700) {
        // New activities hold the AI grade for teacher review unless the teacher decides otherwise.
        // Only the column default changes: existing activities keep the setting they were saved with.
        $table = new xmldb_table('aiviva');
        $field = new xmldb_field(
            'grading_workflow',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'tribunal_member_3_avatar_custom'
        );
        $dbman->change_field_default($table, $field);

        upgrade_mod_savepoint(true, 2026100700, 'aiviva');
    }

    return true;
}
