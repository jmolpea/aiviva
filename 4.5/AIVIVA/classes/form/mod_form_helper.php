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
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\form;

/**
 * Static helpers used by mod_form.php to build form option arrays.
 */
class mod_form_helper {
    /** @var string Model used when an activity has no (or an unknown) model stored. */
    public const DEFAULT_MODEL = 'gpt-6.1-sol';

    /** @var string Speech-to-text model used for the video presentation transcript. */
    public const TRANSCRIPTION_MODEL = 'gpt-transcribe';

    /** @var string Text-to-speech model used for the tribunal voices. */
    public const TTS_MODEL = 'gpt-4o-mini-tts';

    /** @var string Voice used when an activity has no (or an unknown) voice stored. */
    public const DEFAULT_VOICE = 'onyx';

    /** @var string[] Model id => language string suffix describing its tier. */
    private const MODELS = [
        'gpt-6.1-sol'  => 'model_recommended',
        'gpt-6-astra'  => 'model_premium',
        'gpt-6-luna'   => 'model_economical',
    ];

    /** @var string[] Model id => display name. */
    private const MODEL_NAMES = [
        'gpt-6.1-sol'  => 'GPT-6.1 Sol',
        'gpt-6-astra'  => 'GPT-6 Astra',
        'gpt-6-luna'   => 'GPT-6 Luna',
    ];

    /** @var string[] Retired model id => current replacement. */
    private const LEGACY_MODELS = [
        'gpt-4o'      => 'gpt-6.1-sol',
        'gpt-4o-mini' => 'gpt-6-luna',
    ];

    /** @var string[] Voices supported by the text-to-speech model. */
    private const VOICES = [
        'alloy', 'ash', 'ballad', 'cedar', 'coral', 'echo', 'fable',
        'marin', 'nova', 'onyx', 'sage', 'shimmer', 'verse',
    ];

    /**
     * Returns every model the plugin supports, for the admin settings page.
     *
     * @return array Associative array of model_id => display_label.
     */
    public static function get_all_model_options(): array {
        $options = [];
        foreach (self::MODELS as $id => $tier) {
            $options[$id] = self::MODEL_NAMES[$id] . ' ' . get_string($tier, 'mod_aiviva');
        }
        return $options;
    }

    /**
     * Returns the model ids enabled by the site administrator.
     *
     * Falls back to the whole catalogue when nothing has been configured yet.
     *
     * @return string[] Enabled model ids.
     */
    public static function get_enabled_models(): array {
        $configured = (string)get_config('mod_aiviva', 'enabled_models');
        $enabled    = array_intersect(array_keys(self::MODELS), array_filter(explode(',', $configured)));
        return $enabled ? array_values($enabled) : array_keys(self::MODELS);
    }

    /**
     * Returns enabled AI model options based on global plugin config.
     *
     * @return array Associative array of model_id => display_label.
     */
    public static function get_model_options(): array {
        return array_intersect_key(self::get_all_model_options(), array_flip(self::get_enabled_models()));
    }

    /**
     * Maps a stored model id to one that can actually be called.
     *
     * Retired ids are replaced by their successor, and ids that are unknown or
     * have been disabled by the administrator fall back to an enabled model.
     *
     * @param string|null $model Model id stored on the activity.
     * @return string A supported, enabled model id.
     */
    public static function resolve_model(?string $model): string {
        $model   = self::LEGACY_MODELS[$model] ?? $model;
        $enabled = self::get_enabled_models();
        if ($model !== null && in_array($model, $enabled, true)) {
            return $model;
        }
        return in_array(self::DEFAULT_MODEL, $enabled, true) ? self::DEFAULT_MODEL : reset($enabled);
    }

    /**
     * Returns the replacement map for retired model ids (used by the upgrade script).
     *
     * @return string[] Retired model id => current replacement.
     */
    public static function get_legacy_model_map(): array {
        return self::LEGACY_MODELS;
    }

    /**
     * Returns the available TTS voice options.
     *
     * @return array Associative array of voice_id => display_label.
     */
    public static function get_voice_options(): array {
        $options = [];
        foreach (self::VOICES as $voice) {
            $options[$voice] = get_string('voice_' . $voice, 'mod_aiviva');
        }
        return $options;
    }

    /**
     * Maps a stored voice id to one the text-to-speech model supports.
     *
     * @param string|null $voice Voice id stored on the activity.
     * @return string A supported voice id.
     */
    public static function resolve_voice(?string $voice): string {
        return in_array($voice, self::VOICES, true) ? $voice : self::DEFAULT_VOICE;
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
