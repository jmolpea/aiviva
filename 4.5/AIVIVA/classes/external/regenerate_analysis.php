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

namespace mod_aiviva\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_aiviva\local\manager;

/**
 * Teacher action: regenerates the AI analyses and/or the evaluation of a finished attempt.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regenerate_analysis extends base {
    /** @var string[] What can be regenerated. Every scope ends by re-running the final evaluation. */
    private const SCOPES = ['pdf', 'video', 'evaluation', 'all'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return self::attempt_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'What to regenerate: pdf, video, evaluation or all'),
        ]);
    }

    /**
     * Regenerates the AI output of an attempt.
     *
     * @param int    $cmid         Course module id.
     * @param int    $submissionid Attempt id.
     * @param string $scope        One of pdf, video, evaluation, all.
     * @return array {string status}
     */
    public static function execute(int $cmid, int $submissionid, string $scope): array {
        global $DB, $USER;

        ['cmid' => $cmid, 'submissionid' => $submissionid, 'scope' => $scope] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'submissionid' => $submissionid, 'scope' => $scope]
        );
        if (!in_array($scope, self::SCOPES, true)) {
            throw new \invalid_parameter_exception('Unknown scope: ' . $scope);
        }
        [$aiviva, $course, $cm, $context] = self::require_activity($cmid, 'mod/aiviva:grade');

        $submission = $DB->get_record(
            'aiviva_submissions',
            ['id' => $submissionid, 'aiviva' => $aiviva->id],
            '*',
            MUST_EXIST
        );
        if (!manager::can_review_user($cm, $context, (int)$submission->userid)) {
            throw new \required_capability_exception($context, 'moodle/site:accessallgroups', 'nopermissions', '');
        }
        if (!in_array($submission->status, ['submitted', 'grading', 'graded'])) {
            throw new \moodle_exception('error_regen_not_finished', 'mod_aiviva');
        }
        self::check_cooldown((int)$USER->id, (int)$submission->id, $scope, $scope === 'all' ? 120 : 60);
        self::allow_long_work(900);

        $update = (object)['id' => $submission->id, 'timemodified' => time()];
        $fs = get_file_storage();

        if ($scope === 'pdf' || $scope === 'all') {
            $pdffiles = $fs->get_area_files($context->id, 'mod_aiviva', 'submission_pdf', $submission->id, 'id', false);
            if ($pdffiles) {
                $analyzer = new \mod_aiviva\api\pdf_analyzer();
                $update->pdf_analysis = $analyzer->analyse(reset($pdffiles), $aiviva, (int)$submission->userid);
            } else if ($scope === 'pdf') {
                throw new \moodle_exception('error_no_pdf', 'mod_aiviva');
            }
        }

        if ($scope === 'video' || $scope === 'all') {
            try {
                $result = (new \mod_aiviva\api\video_analyzer())->analyse($context, $submission, $aiviva);
                $update->video_transcript = $result['transcript'];
                $update->video_analysis   = $result['analysis'];
            } catch (\moodle_exception $e) {
                // With "regenerate all" a purged recording is not an error: the stored analysis is kept.
                if ($scope === 'video' || $e->errorcode !== 'error_no_recording') {
                    throw $e;
                }
            }
        }

        $DB->update_record('aiviva_submissions', $update);
        $submission = $DB->get_record('aiviva_submissions', ['id' => $submission->id], '*', MUST_EXIST);
        $submission = (new \mod_aiviva\api\evaluator())->evaluate($submission, $aiviva, $course, $cm);

        return ['status' => $submission->status];
    }

    /**
     * Return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Attempt status after the regeneration'),
        ]);
    }

    /**
     * Enforces a per-teacher, per-attempt cooldown, since each regeneration costs API calls.
     *
     * @param int    $userid       Teacher's user id.
     * @param int    $submissionid Attempt being regenerated.
     * @param string $scope        What is regenerated, for cache key namespacing.
     * @param int    $cooldownsecs Minimum seconds between calls.
     * @throws \moodle_exception if the cooldown has not yet expired.
     */
    private static function check_cooldown(int $userid, int $submissionid, string $scope, int $cooldownsecs): void {
        $cache = \cache::make('mod_aiviva', 'ratelimit');
        $key   = 'regen_' . $scope . '_' . $userid . '_' . $submissionid;
        $last  = (int)($cache->get($key) ?: 0);
        $now   = time();
        if ($last > 0 && ($now - $last) < $cooldownsecs) {
            throw new \moodle_exception('regen_cooldown', 'mod_aiviva');
        }
        $cache->set($key, $now);
    }
}
