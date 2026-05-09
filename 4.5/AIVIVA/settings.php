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
 * Global plugin settings for mod_aiviva.
 *
 * Displayed under: Site administration > Plugins > Activity modules > AI Viva
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Section: API Keys.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/apikeys_heading',
        get_string('settings_apikeys_heading', 'mod_aiviva'),
        get_string('settings_apikeys_heading_desc', 'mod_aiviva')
    ));

    // Primary OpenAI API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_aiviva/openai_apikey',
        get_string('settings_openai_apikey', 'mod_aiviva'),
        get_string('settings_openai_apikey_desc', 'mod_aiviva'),
        ''
    ));

    // Secondary API Key (optional, for Whisper/TTS separation).
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_aiviva/openai_apikey_secondary',
        get_string('settings_openai_apikey_secondary', 'mod_aiviva'),
        get_string('settings_openai_apikey_secondary_desc', 'mod_aiviva'),
        ''
    ));

    // Section: Available Models.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/models_heading',
        get_string('settings_models_heading', 'mod_aiviva'),
        get_string('settings_models_heading_desc', 'mod_aiviva')
    ));

    // Enable GPT-4o.
    $settings->add(new admin_setting_configcheckbox(
        'mod_aiviva/enable_gpt4o',
        get_string('settings_enable_gpt4o', 'mod_aiviva'),
        get_string('settings_enable_gpt4o_desc', 'mod_aiviva'),
        1
    ));

    // Enable GPT-4o-mini.
    $settings->add(new admin_setting_configcheckbox(
        'mod_aiviva/enable_gpt4o_mini',
        get_string('settings_enable_gpt4o_mini', 'mod_aiviva'),
        get_string('settings_enable_gpt4o_mini_desc', 'mod_aiviva'),
        1
    ));

    // Estimated cost notice.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/cost_estimate_heading',
        get_string('settings_cost_estimate_heading', 'mod_aiviva'),
        get_string('settings_cost_estimate_desc', 'mod_aiviva')
    ));

    // Section: Security.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/security_heading',
        get_string('settings_security_heading', 'mod_aiviva'),
        get_string('settings_security_heading_desc', 'mod_aiviva')
    ));

    // Enable OpenAI content filter (moderation endpoint).
    $settings->add(new admin_setting_configcheckbox(
        'mod_aiviva/safety_content_filter',
        get_string('settings_safety_content_filter', 'mod_aiviva'),
        get_string('settings_safety_content_filter_desc', 'mod_aiviva'),
        1
    ));

    // Max tokens per API call.
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/safety_max_tokens',
        get_string('settings_safety_max_tokens', 'mod_aiviva'),
        get_string('settings_safety_max_tokens_desc', 'mod_aiviva'),
        4096,
        PARAM_INT
    ));

    // Anonymize student names in prompts (always on, display-only).
    $settings->add(new admin_setting_heading(
        'mod_aiviva/anonymize_heading',
        get_string('settings_anonymize_heading', 'mod_aiviva'),
        get_string('settings_anonymize_desc', 'mod_aiviva')
    ));

    // Salt for anonymisation hash.
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/anonymize_salt',
        get_string('settings_anonymize_salt', 'mod_aiviva'),
        get_string('settings_anonymize_salt_desc', 'mod_aiviva'),
        '',
        PARAM_TEXT
    ));

    // Section: Storage.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/storage_heading',
        get_string('settings_storage_heading', 'mod_aiviva'),
        get_string('settings_storage_heading_desc', 'mod_aiviva')
    ));

    // Global max video file size (MB).
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/global_max_video_size',
        get_string('settings_global_max_video_size', 'mod_aiviva'),
        get_string('settings_global_max_video_size_desc', 'mod_aiviva'),
        500,
        PARAM_INT
    ));

    // Default video purge days.
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/video_purge_days',
        get_string('settings_video_purge_days', 'mod_aiviva'),
        get_string('settings_video_purge_days_desc', 'mod_aiviva'),
        15,
        PARAM_INT
    ));

    // Disk space warning threshold (GB).
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/disk_warning_threshold_gb',
        get_string('settings_disk_warning_threshold', 'mod_aiviva'),
        get_string('settings_disk_warning_threshold_desc', 'mod_aiviva'),
        10,
        PARAM_INT
    ));

    // Section: GDPR notice text.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/gdpr_heading',
        get_string('settings_gdpr_heading', 'mod_aiviva'),
        get_string('settings_gdpr_heading_desc', 'mod_aiviva')
    ));

    $settings->add(new admin_setting_configtextarea(
        'mod_aiviva/gdpr_notice_text',
        get_string('settings_gdpr_notice_text', 'mod_aiviva'),
        get_string('settings_gdpr_notice_text_desc', 'mod_aiviva'),
        get_string('gdpr_default_notice', 'mod_aiviva'),
        PARAM_RAW
    ));

    // Section: Server tools.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/servertools_heading',
        get_string('settings_servertools_heading', 'mod_aiviva'),
        get_string('settings_servertools_heading_desc', 'mod_aiviva')
    ));

    // FFmpeg binary path (used for server-side video frame extraction).
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/ffmpeg_path',
        get_string('settings_ffmpeg_path', 'mod_aiviva'),
        get_string('settings_ffmpeg_path_desc', 'mod_aiviva'),
        '',
        PARAM_RAW
    ));

    // Section: Advanced / API timeouts.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/advanced_heading',
        get_string('settings_advanced_heading', 'mod_aiviva'),
        ''
    ));

    // API request timeout (seconds).
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/api_timeout',
        get_string('settings_api_timeout', 'mod_aiviva'),
        get_string('settings_api_timeout_desc', 'mod_aiviva'),
        120,
        PARAM_INT
    ));

    // Max API calls per minute per user.
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/api_rate_limit',
        get_string('settings_api_rate_limit', 'mod_aiviva'),
        get_string('settings_api_rate_limit_desc', 'mod_aiviva'),
        10,
        PARAM_INT
    ));
}
