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
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Section: License.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/license_heading',
        get_string('license_heading', 'mod_aiviva'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_aiviva/license_key',
        get_string('license_key', 'mod_aiviva'),
        get_string('license_key_desc', 'mod_aiviva', \mod_aiviva\license\validator::TRIAL_DAYS),
        '',
        PARAM_RAW_TRIMMED
    ));

    // License status indicator — computed inline at render time (offline, no DB hit).
    $licenseresult = \mod_aiviva\license\validator::get_settings_status();
    $settings->add(new admin_setting_heading(
        'mod_aiviva/license_status_display',
        '',
        html_writer::tag('span', $licenseresult['text'], ['class' => $licenseresult['css']])
    ));

    // Section: API Keys.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/apikeys_heading',
        get_string('settings_apikeys_heading', 'mod_aiviva'),
        get_string('settings_apikeys_heading_desc', 'mod_aiviva')
    ));

    // Primary OpenAI API Key, stored encrypted and never shown again once saved.
    $settings->add(new admin_setting_encryptedpassword(
        'mod_aiviva/openai_apikey',
        get_string('settings_openai_apikey', 'mod_aiviva'),
        get_string('settings_openai_apikey_desc', 'mod_aiviva')
    ));

    // Secondary API Key (optional, for transcription/TTS separation).
    $settings->add(new admin_setting_encryptedpassword(
        'mod_aiviva/openai_apikey_secondary',
        get_string('settings_openai_apikey_secondary', 'mod_aiviva'),
        get_string('settings_openai_apikey_secondary_desc', 'mod_aiviva')
    ));

    // Section: Available Models.
    $settings->add(new admin_setting_heading(
        'mod_aiviva/models_heading',
        get_string('settings_models_heading', 'mod_aiviva'),
        get_string('settings_models_heading_desc', 'mod_aiviva')
    ));

    // Models that teachers may choose from in the activity settings.
    $modeloptions = \mod_aiviva\form\mod_form_helper::get_all_model_options();
    $settings->add(new admin_setting_configmulticheckbox(
        'mod_aiviva/enabled_models',
        get_string('settings_enabled_models', 'mod_aiviva'),
        get_string('settings_enabled_models_desc', 'mod_aiviva'),
        array_fill_keys(array_keys($modeloptions), 1),
        $modeloptions
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
        16000,
        PARAM_INT
    ));

    // How students are identified in prompts (display-only).
    $settings->add(new admin_setting_heading(
        'mod_aiviva/anonymize_heading',
        get_string('settings_anonymize_heading', 'mod_aiviva'),
        get_string('settings_anonymize_desc', 'mod_aiviva')
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
        300,
        PARAM_INT
    ));

    // Max API calls per minute per user.
    $settings->add(new admin_setting_configtext(
        'mod_aiviva/api_rate_limit',
        get_string('settings_api_rate_limit', 'mod_aiviva'),
        get_string('settings_api_rate_limit_desc', 'mod_aiviva'),
        30,
        PARAM_INT
    ));
}
