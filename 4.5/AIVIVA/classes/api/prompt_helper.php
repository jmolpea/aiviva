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
 * Shared prompt-building helpers for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\api;

/**
 * Helpers shared by every class that builds a prompt.
 */
class prompt_helper {
    /** @var string[] Moodle language code => language name understood by the model. */
    private const LANGUAGES = [
        'es'    => 'Spanish',
        'en'    => 'English',
        'pt_br' => 'Brazilian Portuguese',
        'pt'    => 'Portuguese',
        'fr'    => 'French',
        'de'    => 'German',
        'it'    => 'Italian',
        'ca'    => 'Catalan',
        'eu'    => 'Basque',
        'gl'    => 'Galician',
        'nl'    => 'Dutch',
        'pl'    => 'Polish',
        'ru'    => 'Russian',
        'zh_cn' => 'Simplified Chinese',
        'zh_tw' => 'Traditional Chinese',
        'ja'    => 'Japanese',
        'ar'    => 'Arabic',
    ];

    /**
     * Returns the name of the language the AI must write in for a student.
     *
     * The student's own preferred language is used, so that feedback produced
     * by cron or by a teacher's regeneration is still in the student's language.
     *
     * @param int $userid The student (0 = use the current page language).
     * @return string Language name in English.
     */
    public static function language_for_user(int $userid = 0): string {
        $code = self::lang_code_for_user($userid);

        if (isset(self::LANGUAGES[$code])) {
            return self::LANGUAGES[$code];
        }
        // Regional packs such as es_mx or en_us fall back to their parent language.
        $parent = explode('_', $code)[0];
        return self::LANGUAGES[$parent] ?? 'the same language as the student submission';
    }

    /**
     * Returns the Moodle language code to use for content addressed to a student.
     *
     * @param int $userid The student (0 = use the current page language).
     * @return string Moodle language code, e.g. 'es' or 'pt_br'.
     */
    public static function lang_code_for_user(int $userid = 0): string {
        global $DB, $USER;

        if ($userid && $userid != $USER->id) {
            $userlang = $DB->get_field('user', 'lang', ['id' => $userid]);
            if ($userlang) {
                return $userlang;
            }
        }
        return current_language();
    }

    /**
     * Returns a pseudonymous identifier for a student, for use in prompts.
     *
     * The salt is generated on first use so that the identifier cannot be
     * reversed by hashing candidate user ids.
     *
     * @param int $userid Moodle user id.
     * @return string Pseudonymous identifier.
     */
    public static function pseudonym(int $userid): string {
        $salt = (string)get_config('mod_aiviva', 'anonymize_salt');
        if ($salt === '') {
            $salt = random_string(40);
            set_config('anonymize_salt', $salt, 'mod_aiviva');
        }
        return 'STUDENT-' . substr(hash_hmac('sha256', (string)$userid, $salt), 0, 12);
    }

    /**
     * Strips control characters from a teacher-written prompt.
     *
     * @param string|null $prompt Raw prompt.
     * @return string Clean prompt.
     */
    public static function clean(?string $prompt): string {
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$prompt));
    }

    /**
     * Returns the teacher's additional safety instructions as a prompt paragraph.
     *
     * @param \stdClass $aiviva The activity record.
     * @return string Paragraph starting with a blank line, or '' if none is configured.
     */
    public static function safety_instructions(\stdClass $aiviva): string {
        $extra = self::clean($aiviva->safety_extra_prompt ?? '');
        return $extra === '' ? '' : "\n\nAdditional rules set by the teacher (always follow them):\n" . $extra;
    }

    /**
     * Wraps untrusted, student-originated text in delimiters.
     *
     * @param string $label Upper-case label, e.g. 'STUDENT DOCUMENT'.
     * @param string $text  The untrusted text.
     * @return string Delimited block.
     */
    public static function delimit(string $label, string $text): string {
        return "=== {$label} START ===\n{$text}\n=== {$label} END ===";
    }
}
