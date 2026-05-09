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
 * Helper utilities for mod_aiviva's activity configuration form.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\form;

/**
 * Static helpers used by mod_form.php to build form option arrays.
 */
class mod_form_helper {
    /**
     * Returns enabled AI model options based on global plugin config.
     *
     * @return array Associative array of model_id => display_label.
     */
    public static function get_model_options(): array {
        $config  = get_config('mod_aiviva');
        $options = [];

        if (!empty($config->enable_gpt4o)) {
            $options['gpt-4o'] = 'GPT-4o ' . get_string('model_recommended', 'mod_aiviva');
        }
        if (!empty($config->enable_gpt4o_mini)) {
            $options['gpt-4o-mini'] = 'GPT-4o mini ' . get_string('model_economical', 'mod_aiviva');
        }

        if (empty($options)) {
            $options = [
                'gpt-4o'      => 'GPT-4o',
                'gpt-4o-mini' => 'GPT-4o mini',
            ];
        }

        return $options;
    }

    /**
     * Returns the available TTS voice options.
     *
     * @return array Associative array of voice_id => display_label.
     */
    public static function get_voice_options(): array {
        return [
            'alloy'   => get_string('voice_alloy', 'mod_aiviva'),
            'echo'    => get_string('voice_echo', 'mod_aiviva'),
            'fable'   => get_string('voice_fable', 'mod_aiviva'),
            'onyx'    => get_string('voice_onyx', 'mod_aiviva'),
            'nova'    => get_string('voice_nova', 'mod_aiviva'),
            'shimmer' => get_string('voice_shimmer', 'mod_aiviva'),
        ];
    }

    /**
     * Returns the maximum allowed video file size for use in a form element.
     *
     * Respects the global admin-set maximum.
     *
     * @return int Maximum size in MB.
     */
    public static function get_max_video_size(): int {
        $config = get_config('mod_aiviva');
        return max(1, (int)($config->global_max_video_size ?? 500));
    }
}
