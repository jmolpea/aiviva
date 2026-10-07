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
 * Submission lifecycle rules for mod_aiviva.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\local;

/**
 * Central place for the rules that decide what a student may do and when:
 * attempts, availability window, overrides, the tribunal clock and grades.
 */
class manager {
    /** @var int Seconds a tribunal may overrun its duration before turns are refused. */
    public const TRIBUNAL_GRACE_SECS = 120;

    /** @var int Seconds after the tribunal deadline before an abandoned session is closed by cron. */
    public const TRIBUNAL_ABANDON_SECS = 900;

    /** @var string[] Statuses in which the student is still working on the attempt. */
    public const OPEN_STATUSES = ['draft', 'step1', 'step2', 'step3'];

    /**
     * Returns the attempt limit and availability window that apply to a user,
     * after user and group overrides. A user override beats a group override;
     * among several group overrides the most permissive value wins.
     *
     * @param \stdClass $aiviva The activity record.
     * @param int       $userid The user.
     * @return \stdClass {int max_attempts (0 = unlimited); int timeopen; int timeclose}
     */
    public static function get_effective_settings(\stdClass $aiviva, int $userid): \stdClass {
        global $DB;

        $settings = (object)[
            'max_attempts' => (int)$aiviva->max_attempts,
            'timeopen'     => (int)($aiviva->timeopen ?? 0),
            'timeclose'    => (int)($aiviva->timeclose ?? 0),
        ];

        $groupids = array_keys(groups_get_all_groups($aiviva->course, $userid, 0, 'g.id'));
        if ($groupids) {
            [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
            $params['aiviva'] = $aiviva->id;
            $overrides = $DB->get_records_select('aiviva_overrides', "aiviva = :aiviva AND groupid $insql", $params);
            $attempts = $opens = $closes = [];
            foreach ($overrides as $override) {
                if ($override->max_attempts !== null) {
                    $attempts[] = (int)$override->max_attempts;
                }
                if ($override->timeopen !== null) {
                    $opens[] = (int)$override->timeopen;
                }
                if ($override->timeclose !== null) {
                    $closes[] = (int)$override->timeclose;
                }
            }
            if ($attempts) {
                $settings->max_attempts = max($attempts);
            }
            if ($opens) {
                $settings->timeopen = min($opens);
            }
            if ($closes) {
                $settings->timeclose = max($closes);
            }
        }

        $useroverride = $DB->get_record('aiviva_overrides', ['aiviva' => $aiviva->id, 'userid' => $userid], '*', IGNORE_MULTIPLE);
        if ($useroverride) {
            foreach (['max_attempts', 'timeopen', 'timeclose'] as $field) {
                if ($useroverride->$field !== null) {
                    $settings->$field = (int)$useroverride->$field;
                }
            }
        }

        return $settings;
    }

    /**
     * Tells whether the activity is currently open for a user.
     *
     * @param \stdClass $settings Result of {@see self::get_effective_settings()}.
     * @param int|null  $now      Timestamp to test (defaults to now).
     * @return string '' when open, otherwise 'notopen' or 'closed'.
     */
    public static function availability(\stdClass $settings, ?int $now = null): string {
        $now = $now ?? time();
        if ($settings->timeopen && $now < $settings->timeopen) {
            return 'notopen';
        }
        if ($settings->timeclose && $now > $settings->timeclose) {
            return 'closed';
        }
        return '';
    }

    /**
     * Returns the user's most recent attempt, if any.
     *
     * @param int $aivivaid The activity id.
     * @param int $userid   The user.
     * @return \stdClass|null
     */
    public static function get_latest_submission(int $aivivaid, int $userid): ?\stdClass {
        global $DB;
        $records = $DB->get_records(
            'aiviva_submissions',
            ['aiviva' => $aivivaid, 'userid' => $userid],
            'attempt DESC, id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * Tells whether the user may begin a further attempt.
     *
     * A new attempt is only possible once the previous one is finished, the
     * activity is open, and the attempt limit has not been reached.
     *
     * @param \stdClass      $settings Effective settings for the user.
     * @param \stdClass|null $latest   The user's latest attempt, if any.
     * @return bool
     */
    public static function can_start_attempt(\stdClass $settings, ?\stdClass $latest): bool {
        if (self::availability($settings) !== '') {
            return false;
        }
        if (!$latest) {
            return true;
        }
        if ($latest->status !== 'graded' || $latest->workflow_state !== 'released') {
            return false;
        }
        return $settings->max_attempts === 0 || (int)$latest->attempt < $settings->max_attempts;
    }

    /**
     * Creates the next attempt for a user who has just given consent.
     *
     * @param \stdClass      $aiviva  The activity record.
     * @param int            $userid  The user.
     * @param \stdClass|null $latest  The user's latest attempt, if any.
     * @return \stdClass The new submission record.
     */
    public static function create_attempt(\stdClass $aiviva, int $userid, ?\stdClass $latest): \stdClass {
        global $DB;

        $now = time();
        $submission = (object)[
            'aiviva'            => $aiviva->id,
            'userid'            => $userid,
            'groupid'           => 0,
            'status'            => 'draft',
            'attempt'           => $latest ? (int)$latest->attempt + 1 : 1,
            'gdpr_consent'      => 1,
            'gdpr_consent_time' => $now,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ];
        $submission->id = $DB->insert_record('aiviva_submissions', $submission);

        return $DB->get_record('aiviva_submissions', ['id' => $submission->id], '*', MUST_EXIST);
    }

    /**
     * Deletes one attempt with all its messages and files.
     *
     * @param \stdClass $submission The submission record.
     * @param \context  $context    The module context.
     */
    public static function delete_submission(\stdClass $submission, \context $context): void {
        global $DB;

        $fs = get_file_storage();
        foreach (self::submission_fileareas() as $filearea) {
            $fs->delete_area_files($context->id, 'mod_aiviva', $filearea, $submission->id);
        }
        $DB->delete_records('aiviva_tribunal_messages', ['submission_id' => $submission->id]);
        $DB->delete_records('aiviva_submissions', ['id' => $submission->id]);
    }

    /**
     * File areas that hold student data, all keyed by submission id.
     *
     * @return string[]
     */
    public static function submission_fileareas(): array {
        return ['submission_pdf', 'submission_video', 'submission_audio', 'submission_frames', 'tribunal_audio'];
    }

    /**
     * Tells whether a teacher has edited the grade or the feedback of an attempt.
     *
     * The grader is null while the grade is the AI's own, and 0 once the teacher
     * who edited it has had their personal data deleted.
     *
     * @param \stdClass $submission The submission record.
     * @return bool
     */
    public static function grade_was_edited(\stdClass $submission): bool {
        return ($submission->grader_userid ?? null) !== null;
    }

    /**
     * Returns the current user's own attempt, enforcing every rule that applies
     * to a student action.
     *
     * @param \stdClass $aiviva       The activity record.
     * @param int       $submissionid Submission id sent by the browser.
     * @param string[]  $statuses     Statuses in which the action is allowed (empty = any).
     * @param bool      $requireopen  Whether the activity must be within its availability window.
     * @return \stdClass Submission record.
     * @throws \moodle_exception if the attempt is not the user's latest, lacks consent,
     *                           is in the wrong state, or the activity is closed.
     */
    public static function require_own_submission(
        \stdClass $aiviva,
        int $submissionid,
        array $statuses = [],
        bool $requireopen = false
    ): \stdClass {
        global $USER;

        $submission = self::get_latest_submission($aiviva->id, $USER->id);
        if (!$submission || (int)$submission->id !== $submissionid) {
            throw new \moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
        }
        if (!$submission->gdpr_consent) {
            throw new \moodle_exception('gdpr_consent_required', 'mod_aiviva');
        }
        if ($statuses && !in_array($submission->status, $statuses, true)) {
            throw new \moodle_exception('invalidsubmissionstatus', 'mod_aiviva');
        }
        if ($requireopen) {
            $availability = self::availability(self::get_effective_settings($aiviva, $USER->id));
            if ($availability !== '') {
                throw new \moodle_exception('error_' . $availability, 'mod_aiviva');
            }
        }
        return $submission;
    }

    /**
     * Tells whether a user may review a given student's attempts, honouring
     * the activity's separate-groups mode.
     *
     * @param \stdClass|\cm_info $cm         The course module.
     * @param \context           $context    The module context.
     * @param int                $userid     The student.
     * @param int|null           $reviewerid The reviewing user (defaults to the current user).
     * @return bool
     */
    public static function can_review_user($cm, \context $context, int $userid, ?int $reviewerid = null): bool {
        global $USER;

        $reviewerid ??= (int)$USER->id;
        if (
            groups_get_activity_groupmode($cm) != SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $context, $reviewerid)
        ) {
            return true;
        }
        $mine   = groups_get_all_groups($cm->course, $reviewerid, $cm->groupingid, 'g.id');
        $theirs = groups_get_all_groups($cm->course, $userid, $cm->groupingid, 'g.id');
        return (bool)array_intersect_key($mine, $theirs);
    }

    /**
     * Seconds left on the tribunal clock (negative once the time is up).
     *
     * @param \stdClass $aiviva     The activity record.
     * @param \stdClass $submission The submission record.
     * @param int|null  $now        Timestamp to test (defaults to now).
     * @return int Remaining seconds; the full duration if the tribunal has not started.
     */
    public static function tribunal_remaining(\stdClass $aiviva, \stdClass $submission, ?int $now = null): int {
        $duration = max(1, (int)$aiviva->step3_duration) * MINSECS;
        if (empty($submission->tribunal_timestart)) {
            return $duration;
        }
        return (int)$submission->tribunal_timestart + $duration - ($now ?? time());
    }

    /**
     * Marks the tribunal as finished and records activity completion.
     *
     * Safe to call more than once: only the first call changes anything.
     *
     * @param \stdClass $submission The submission record (updated in place).
     * @param \stdClass $course     The course record.
     * @param \stdClass $cm         The course module record.
     * @return bool True if this call closed the attempt, false if it was already closed.
     */
    public static function mark_submitted(\stdClass $submission, \stdClass $course, \stdClass $cm): bool {
        global $DB;

        $now = time();
        $DB->execute(
            "UPDATE {aiviva_submissions}
                SET status = :newstatus, timesubmitted = :timesubmitted, timemodified = :timemodified
              WHERE id = :id AND status = :oldstatus",
            [
                'newstatus' => 'submitted', 'timesubmitted' => $now, 'timemodified' => $now,
                'id' => $submission->id, 'oldstatus' => 'step3',
            ]
        );
        $fresh = $DB->get_record('aiviva_submissions', ['id' => $submission->id], 'id, status, timesubmitted', MUST_EXIST);
        if ($fresh->status !== 'submitted' || (int)$fresh->timesubmitted !== $now) {
            return false;
        }

        $submission->status        = 'submitted';
        $submission->timesubmitted = $now;

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $submission->userid);
        }

        return true;
    }

    /**
     * Normalises the three grade weights so that they add up to 1.
     *
     * @param \stdClass $aiviva The activity record.
     * @return float[] Keys step1_pdf, step2_video, step3_tribunal.
     */
    public static function get_weights(\stdClass $aiviva): array {
        $weights = [
            'step1_pdf'      => max(0, (int)($aiviva->weight_pdf ?? 33)),
            'step2_video'    => max(0, (int)($aiviva->weight_video ?? 33)),
            'step3_tribunal' => max(0, (int)($aiviva->weight_tribunal ?? 34)),
        ];
        $total = array_sum($weights);
        if ($total <= 0) {
            return ['step1_pdf' => 1 / 3, 'step2_video' => 1 / 3, 'step3_tribunal' => 1 / 3];
        }
        return array_map(static fn($weight) => $weight / $total, $weights);
    }

    /**
     * Computes the weighted percentage from the per-step scores returned by the
     * model. The arithmetic is done here rather than trusted to the model.
     *
     * @param array $breakdown Per-step data, each with a 'score' between 0 and 100.
     * @param array $weights   Result of {@see self::get_weights()}.
     * @return float Percentage between 0 and 100.
     */
    public static function weighted_percentage(array $breakdown, array $weights): float {
        $total = 0.0;
        foreach ($weights as $step => $weight) {
            $score  = (float)($breakdown[$step]['score'] ?? 0);
            $total += max(0.0, min(100.0, $score)) * $weight;
        }
        return round($total, 2);
    }
}
