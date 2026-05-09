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
 * Activity module configuration form for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2024 AI Viva Project
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity creation/editing form.
 */
class mod_aiviva_mod_form extends moodleform_mod {
    /**
     * Defines the form fields.
     */
    public function definition(): void {
        global $CFG;

        $mform   = $this->_form;
        $config  = get_config('mod_aiviva');

        // Section: General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Activity name.
        $mform->addElement('text', 'name', get_string('activityname', 'mod_aiviva'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Description / intro.
        $this->standard_intro_elements();

        // Max attempts.
        $attemptoptions = [
            1 => '1',
            2 => '2',
            3 => '3',
            0 => get_string('unlimited', 'mod_aiviva'),
        ];
        $mform->addElement('select', 'max_attempts', get_string('maxattempts', 'mod_aiviva'), $attemptoptions);
        $mform->setDefault('max_attempts', 2);
        $mform->addHelpButton('max_attempts', 'maxattempts', 'mod_aiviva');

        // Group submission.
        $mform->addElement('advcheckbox', 'group_submission', get_string('groupsubmission', 'mod_aiviva'));
        $mform->addHelpButton('group_submission', 'groupsubmission', 'mod_aiviva');

        // Grouping selector (shown only when group_submission is checked).
        $groupings = groups_get_all_groupings($this->current->course ?? 0);
        $groupingoptions = [0 => get_string('none')];
        foreach ($groupings as $grouping) {
            $groupingoptions[$grouping->id] = format_string($grouping->name);
        }
        $mform->addElement('select', 'groupingid', get_string('grouping', 'group'), $groupingoptions);
        $mform->hideIf('groupingid', 'group_submission', 'notchecked');

        // Section: Step 1 — PDF.
        $mform->addElement('header', 'step1_header', get_string('step1_header', 'mod_aiviva'));

        $mform->addElement(
            'editor',
            'step1_description_editor',
            get_string('step1_description', 'mod_aiviva'),
            null,
            $this->get_editor_options()
        );
        $mform->setType('step1_description_editor', PARAM_RAW);
        $mform->addHelpButton('step1_description_editor', 'step1_description', 'mod_aiviva');

        $mform->addElement(
            'textarea',
            'step1_prompt',
            get_string('step1_prompt', 'mod_aiviva'),
            ['rows' => 6, 'cols' => 80]
        );
        $mform->setType('step1_prompt', PARAM_RAW);
        $mform->addHelpButton('step1_prompt', 'step1_prompt', 'mod_aiviva');

        $mform->addElement(
            'select',
            'openai_model_pdf',
            get_string('openai_model_pdf', 'mod_aiviva'),
            $this->get_model_options()
        );
        $mform->setDefault('openai_model_pdf', 'gpt-4o');

        // Section: Step 2 — Video presentation.
        $mform->addElement('header', 'step2_header', get_string('step2_header', 'mod_aiviva'));

        $mform->addElement(
            'editor',
            'step2_description_editor',
            get_string('step2_description', 'mod_aiviva'),
            null,
            $this->get_editor_options()
        );
        $mform->setType('step2_description_editor', PARAM_RAW);
        $mform->addHelpButton('step2_description_editor', 'step2_description', 'mod_aiviva');

        $mform->addElement(
            'textarea',
            'step2_prompt',
            get_string('step2_prompt', 'mod_aiviva'),
            ['rows' => 6, 'cols' => 80]
        );
        $mform->setType('step2_prompt', PARAM_RAW);
        $mform->addHelpButton('step2_prompt', 'step2_prompt', 'mod_aiviva');

        $durationoptions = [5 => '5 min', 10 => '10 min', 15 => '15 min', 30 => '30 min'];
        $mform->addElement('select', 'step2_duration', get_string('step2_duration', 'mod_aiviva'), $durationoptions);
        $mform->setDefault('step2_duration', 10);

        $globalmaxsize = (int)($config->global_max_video_size ?? 500);
        $mform->addElement('text', 'step2_maxfilesize', get_string('step2_maxfilesize', 'mod_aiviva'));
        $mform->setType('step2_maxfilesize', PARAM_INT);
        $mform->setDefault('step2_maxfilesize', $globalmaxsize);
        $mform->addRule('step2_maxfilesize', null, 'required', null, 'client');
        $mform->addHelpButton('step2_maxfilesize', 'step2_maxfilesize', 'mod_aiviva');

        $mform->addElement(
            'select',
            'openai_model_tribunal',
            get_string('openai_model_tribunal', 'mod_aiviva'),
            $this->get_model_options()
        );
        $mform->setDefault('openai_model_tribunal', 'gpt-4o');

        // Section: Step 3 — Tribunal.
        $mform->addElement('header', 'step3_header', get_string('step3_header', 'mod_aiviva'));

        $mform->addElement('text', 'step3_duration', get_string('step3_duration', 'mod_aiviva'));
        $mform->setType('step3_duration', PARAM_INT);
        $mform->setDefault('step3_duration', 10);

        $mform->addElement(
            'textarea',
            'step3_prompt_eval',
            get_string('step3_prompt_eval', 'mod_aiviva'),
            ['rows' => 6, 'cols' => 80]
        );
        $mform->setType('step3_prompt_eval', PARAM_RAW);
        $mform->addHelpButton('step3_prompt_eval', 'step3_prompt_eval', 'mod_aiviva');

        $mform->addElement(
            'select',
            'openai_model_eval',
            get_string('openai_model_eval', 'mod_aiviva'),
            $this->get_model_options()
        );
        $mform->setDefault('openai_model_eval', 'gpt-4o');

        // Tribunal members.
        for ($i = 1; $i <= 3; $i++) {
            $this->add_tribunal_member_fields($mform, $i);
        }

        // Section: Grading and Workflow.
        $mform->addElement('header', 'grading_header', get_string('grading_header', 'mod_aiviva'));

        $mform->addElement('text', 'grade', get_string('maximumgrade', 'mod_aiviva'));
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);

        $mform->addElement('advcheckbox', 'grading_workflow', get_string('grading_workflow', 'mod_aiviva'));
        $mform->addHelpButton('grading_workflow', 'grading_workflow', 'mod_aiviva');

        $mform->addElement('advcheckbox', 'notify_student', get_string('notify_student', 'mod_aiviva'));
        $mform->setDefault('notify_student', 1);

        // Section: Security.
        $mform->addElement('header', 'security_header', get_string('security_header', 'mod_aiviva'));

        $mform->addElement(
            'textarea',
            'safety_extra_prompt',
            get_string('safety_extra_prompt', 'mod_aiviva'),
            ['rows' => 4, 'cols' => 80]
        );
        $mform->setType('safety_extra_prompt', PARAM_RAW);
        $mform->addHelpButton('safety_extra_prompt', 'safety_extra_prompt', 'mod_aiviva');

        // Standard Moodle sections (restrictions, completion, etc.).
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds fields for a single tribunal member.
     *
     * @param MoodleQuickForm $mform  The form.
     * @param int             $index  Member index (1, 2 or 3).
     */
    private function add_tribunal_member_fields(MoodleQuickForm $mform, int $index): void {
        $mform->addElement(
            'header',
            "tribunal_member_{$index}_header",
            get_string('tribunal_member_header', 'mod_aiviva', $index)
        );
        $mform->setExpanded("tribunal_member_{$index}_header", $index === 1);

        $mform->addElement(
            'text',
            "tribunal_member_{$index}_name",
            get_string('tribunal_member_name', 'mod_aiviva')
        );
        $mform->setType("tribunal_member_{$index}_name", PARAM_TEXT);
        $defaultnames = [1 => 'Dr. Smith', 2 => 'Prof. Johnson', 3 => 'Dr. Williams'];
        $mform->setDefault("tribunal_member_{$index}_name", $defaultnames[$index]);

        $mform->addElement(
            'text',
            "tribunal_member_{$index}_role",
            get_string('tribunal_member_role', 'mod_aiviva')
        );
        $mform->setType("tribunal_member_{$index}_role", PARAM_TEXT);
        $defaultroles = [1 => 'Chair', 2 => 'Reviewer', 3 => 'Subject Expert'];
        $mform->setDefault("tribunal_member_{$index}_role", $defaultroles[$index]);

        $mform->addElement(
            'textarea',
            "tribunal_member_{$index}_prompt",
            get_string('tribunal_member_prompt', 'mod_aiviva'),
            ['rows' => 5, 'cols' => 80]
        );
        $mform->setType("tribunal_member_{$index}_prompt", PARAM_RAW);
        $mform->addHelpButton("tribunal_member_{$index}_prompt", 'tribunal_member_prompt', 'mod_aiviva');

        $mform->addElement(
            'select',
            "tribunal_member_{$index}_voice",
            get_string('tribunal_member_voice', 'mod_aiviva'),
            $this->get_voice_options()
        );
        $defaultvoices = [1 => 'onyx', 2 => 'nova', 3 => 'echo'];
        $mform->setDefault("tribunal_member_{$index}_voice", $defaultvoices[$index]);

        // Avatar selection (radio).
        $avatargroup = [];
        $avatargroup[] = $mform->createElement(
            'radio',
            "tribunal_member_{$index}_avatar",
            '',
            get_string('avatar_1', 'mod_aiviva'),
            1
        );
        $avatargroup[] = $mform->createElement(
            'radio',
            "tribunal_member_{$index}_avatar",
            '',
            get_string('avatar_2', 'mod_aiviva'),
            2
        );
        $avatargroup[] = $mform->createElement(
            'radio',
            "tribunal_member_{$index}_avatar",
            '',
            get_string('avatar_3', 'mod_aiviva'),
            3
        );
        $avatargroup[] = $mform->createElement(
            'radio',
            "tribunal_member_{$index}_avatar",
            '',
            get_string('avatar_custom', 'mod_aiviva'),
            0
        );
        $mform->addGroup(
            $avatargroup,
            "tribunal_member_{$index}_avatar_group",
            get_string('tribunal_member_avatar', 'mod_aiviva'),
            '<br/>',
            false
        );
        $defaultavatars = [1 => 3, 2 => 2, 3 => 1];
        $mform->setDefault("tribunal_member_{$index}_avatar", $defaultavatars[$index]);

        // Custom avatar file manager.
        $mform->addElement(
            'filemanager',
            "tribunal_member_{$index}_avatar_custom",
            get_string('tribunal_member_avatar_custom', 'mod_aiviva'),
            null,
            [
                'subdirs'   => 0,
                'maxfiles'  => 1,
                'accepted_types' => ['image'],
            ]
        );
        $mform->hideIf(
            "tribunal_member_{$index}_avatar_custom",
            "tribunal_member_{$index}_avatar",
            'neq',
            0
        );
    }

    /**
     * Returns available AI model options based on global config.
     *
     * @return array Associative array of model_id => label.
     */
    private function get_model_options(): array {
        $config  = get_config('mod_aiviva');
        $options = [];

        if (!empty($config->enable_gpt4o)) {
            $options['gpt-4o'] = 'GPT-4o ' . get_string('model_recommended', 'mod_aiviva');
        }
        if (!empty($config->enable_gpt4o_mini)) {
            $options['gpt-4o-mini'] = 'GPT-4o mini ' . get_string('model_economical', 'mod_aiviva');
        }

        if (empty($options)) {
            // Fallback: show both if none configured yet.
            $options['gpt-4o']      = 'GPT-4o';
            $options['gpt-4o-mini'] = 'GPT-4o mini';
        }

        return $options;
    }

    /**
     * Returns available TTS voice options.
     *
     * @return array Associative array of voice_id => label.
     */
    private function get_voice_options(): array {
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
     * Returns editor options.
     *
     * @return array Editor options array.
     */
    private function get_editor_options(): array {
        return [
            'subdirs'  => 0,
            'maxfiles' => 0,
            'context'  => $this->context ?? null,
        ];
    }

    /**
     * Prepares data for the form (e.g. decrypts API key for display).
     *
     * @param array $defaultvalues Array of default values (modified in-place).
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Decrypt API key for display.
        if (!empty($defaultvalues['openai_apikey'])) {
            try {
                $defaultvalues['openai_apikey'] = \core\encryption::decrypt($defaultvalues['openai_apikey']);
            } catch (\moodle_exception $e) {
                $defaultvalues['openai_apikey'] = '';
            }
        }

        // Prepare editor fields.
        foreach (['step1_description', 'step2_description'] as $field) {
            $defaultvalues["{$field}_editor"] = [
                'text'   => $defaultvalues[$field] ?? '',
                'format' => $defaultvalues["{$field}format"] ?? FORMAT_HTML,
            ];
        }
    }

    /**
     * Performs server-side validation.
     *
     * @param array $data  Form data.
     * @param array $files Uploaded files.
     * @return array Errors array (field => message).
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $config      = get_config('mod_aiviva');
        $globalmaxsz = (int)($config->global_max_video_size ?? 500);

        if (!empty($data['step2_maxfilesize'])) {
            if ((int)$data['step2_maxfilesize'] > $globalmaxsz) {
                $errors['step2_maxfilesize'] = get_string(
                    'error_maxfilesize_exceeds_global',
                    'mod_aiviva',
                    $globalmaxsz
                );
            }
            if ((int)$data['step2_maxfilesize'] < 1) {
                $errors['step2_maxfilesize'] = get_string('error_maxfilesize_toosmall', 'mod_aiviva');
            }
        }

        if (!empty($data['step3_duration']) && (int)$data['step3_duration'] < 1) {
            $errors['step3_duration'] = get_string('error_duration_invalid', 'mod_aiviva');
        }

        return $errors;
    }
}
