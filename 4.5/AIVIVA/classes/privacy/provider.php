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
 * Privacy provider for mod_aiviva (GDPR compliance).
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_aiviva\local\manager;

/**
 * Privacy provider for mod_aiviva.
 *
 * Data stored locally: attempts (analyses, transcripts, grades, consent), the
 * tribunal conversation, uploaded and recorded files, per-user overrides, and
 * which teacher edited the grade of an attempt.
 *
 * Data sent to an external service (OpenAI): the submitted document, the
 * recorded audio, screenshots of the presentation, and the conversation.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns the metadata describing what data this plugin stores.
     *
     * @param collection $collection The initialised metadata collection.
     * @return collection The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'aiviva_submissions',
            [
                'userid'              => 'privacy:metadata:aiviva_submissions:userid',
                'attempt'             => 'privacy:metadata:aiviva_submissions:attempt',
                'status'              => 'privacy:metadata:aiviva_submissions:status',
                'gdpr_consent'        => 'privacy:metadata:aiviva_submissions:gdpr_consent',
                'gdpr_consent_time'   => 'privacy:metadata:aiviva_submissions:gdpr_consent_time',
                'pdf_analysis'        => 'privacy:metadata:aiviva_submissions:pdf_analysis',
                'video_transcript'    => 'privacy:metadata:aiviva_submissions:video_transcript',
                'video_analysis'      => 'privacy:metadata:aiviva_submissions:video_analysis',
                'tribunal_transcript' => 'privacy:metadata:aiviva_submissions:tribunal_transcript',
                'tribunal_briefing'   => 'privacy:metadata:aiviva_submissions:tribunal_briefing',
                'tribunal_analysis'   => 'privacy:metadata:aiviva_submissions:tribunal_analysis',
                'ai_grade'            => 'privacy:metadata:aiviva_submissions:ai_grade',
                'final_grade'         => 'privacy:metadata:aiviva_submissions:final_grade',
                'final_feedback'      => 'privacy:metadata:aiviva_submissions:final_feedback',
                'grade_breakdown'     => 'privacy:metadata:aiviva_submissions:grade_breakdown',
                'grader_userid'       => 'privacy:metadata:aiviva_submissions:grader_userid',
                'timecreated'         => 'privacy:metadata:aiviva_submissions:timecreated',
                'timesubmitted'       => 'privacy:metadata:aiviva_submissions:timesubmitted',
            ],
            'privacy:metadata:aiviva_submissions'
        );

        $collection->add_database_table(
            'aiviva_tribunal_messages',
            [
                'speaker'      => 'privacy:metadata:aiviva_tribunal_messages:speaker',
                'message_text' => 'privacy:metadata:aiviva_tribunal_messages:message_text',
                'timestamp'    => 'privacy:metadata:aiviva_tribunal_messages:timestamp',
            ],
            'privacy:metadata:aiviva_tribunal_messages'
        );

        $collection->add_database_table(
            'aiviva_overrides',
            [
                'userid'       => 'privacy:metadata:aiviva_overrides:userid',
                'max_attempts' => 'privacy:metadata:aiviva_overrides:max_attempts',
                'timeopen'     => 'privacy:metadata:aiviva_overrides:timeopen',
                'timeclose'    => 'privacy:metadata:aiviva_overrides:timeclose',
            ],
            'privacy:metadata:aiviva_overrides'
        );

        $collection->link_subsystem('core_files', 'privacy:metadata:core_files');

        $collection->add_external_location_link(
            'openai_api',
            [
                'pseudonym'          => 'privacy:metadata:openai:pseudonym',
                'document'           => 'privacy:metadata:openai:document',
                'audio'              => 'privacy:metadata:openai:audio',
                'video_frames'       => 'privacy:metadata:openai:video_frames',
                'conversation_turns' => 'privacy:metadata:openai:conversation_turns',
            ],
            'privacy:metadata:openai'
        );

        return $collection;
    }

    /**
     * Returns the contexts where the given user has data.
     *
     * @param int $userid The Moodle user id.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = ['ctxlevel' => CONTEXT_MODULE, 'modname' => 'aiviva', 'userid' => $userid];

        foreach (['aiviva_submissions', 'aiviva_overrides'] as $table) {
            $sql = "SELECT ctx.id
                      FROM {context} ctx
                      JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {{$table}} t ON t.aiviva = cm.instance
                     WHERE t.userid = :userid";
            $contextlist->add_from_sql($sql, $params);
        }

        // Activities in which the user, as a teacher, edited the grade of somebody's attempt.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :ctxlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {aiviva_submissions} t ON t.aiviva = cm.instance
                 WHERE t.grader_userid = :userid";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Returns the list of users with data in a context.
     *
     * @param userlist $userlist The userlist.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        foreach (['aiviva_submissions', 'aiviva_overrides'] as $table) {
            $sql = "SELECT t.userid
                      FROM {{$table}} t
                      JOIN {course_modules} cm ON cm.instance = t.aiviva
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                     WHERE cm.id = :cmid AND t.userid IS NOT NULL";
            $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid, 'modname' => 'aiviva']);
        }

        // Teachers who edited a grade. A grader of 0 is a teacher whose data has already been deleted.
        $sql = "SELECT t.grader_userid
                  FROM {aiviva_submissions} t
                  JOIN {course_modules} cm ON cm.instance = t.aiviva
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid AND t.grader_userid > 0";
        $userlist->add_from_sql('grader_userid', $sql, ['cmid' => $context->instanceid, 'modname' => 'aiviva']);
    }

    /**
     * Exports all data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('aiviva', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $overrides = $DB->get_records('aiviva_overrides', ['aiviva' => $cm->instance, 'userid' => $userid]);
            foreach ($overrides as $override) {
                writer::with_context($context)->export_data(
                    [get_string('overrides_heading', 'mod_aiviva'), $override->id],
                    (object)[
                        'max_attempts' => $override->max_attempts,
                        'timeopen'     => $override->timeopen ? transform::datetime($override->timeopen) : null,
                        'timeclose'    => $override->timeclose ? transform::datetime($override->timeclose) : null,
                    ]
                );
            }

            $submissions = $DB->get_records('aiviva_submissions', ['aiviva' => $cm->instance, 'userid' => $userid]);
            foreach ($submissions as $submission) {
                $subcontext = [get_string('attempt_number', 'mod_aiviva', (int)$submission->attempt)];

                writer::with_context($context)->export_data($subcontext, (object)[
                    'attempt'           => $submission->attempt,
                    'status'            => $submission->status,
                    'gdpr_consent'      => transform::yesno($submission->gdpr_consent),
                    'gdpr_consent_time' => $submission->gdpr_consent_time
                        ? transform::datetime($submission->gdpr_consent_time)
                        : null,
                    'pdf_analysis'      => $submission->pdf_analysis,
                    'video_transcript'  => $submission->video_transcript,
                    'video_analysis'    => $submission->video_analysis,
                    'tribunal_briefing' => $submission->tribunal_briefing,
                    'tribunal_analysis' => $submission->tribunal_analysis,
                    'grade_breakdown'   => $submission->grade_breakdown,
                    'ai_grade'          => $submission->ai_grade,
                    'final_grade'       => $submission->final_grade,
                    'final_feedback'    => $submission->final_feedback,
                    'workflow_state'    => $submission->workflow_state,
                    'timecreated'       => transform::datetime($submission->timecreated),
                    'timesubmitted'     => $submission->timesubmitted
                        ? transform::datetime($submission->timesubmitted)
                        : null,
                ]);

                $messages = $DB->get_records(
                    'aiviva_tribunal_messages',
                    ['submission_id' => $submission->id],
                    'turn_number ASC, id ASC'
                );
                if ($messages) {
                    $msgdata = array_values(array_map(static function ($m) {
                        return [
                            'turn'    => $m->turn_number,
                            'speaker' => $m->speaker,
                            'text'    => $m->message_text,
                            'time'    => transform::datetime($m->timestamp),
                        ];
                    }, $messages));
                    writer::with_context($context)->export_data(
                        array_merge($subcontext, [get_string('tribunal_log', 'mod_aiviva')]),
                        (object)['messages' => $msgdata]
                    );
                }

                foreach (manager::submission_fileareas() as $filearea) {
                    writer::with_context($context)->export_area_files($subcontext, 'mod_aiviva', $filearea, $submission->id);
                }
            }

            // What the user did as a teacher: the grade and feedback they set on other people's
            // attempts. The attempts are numbered here, so that nothing identifies the student.
            $graded = $DB->get_records(
                'aiviva_submissions',
                ['aiviva' => $cm->instance, 'grader_userid' => $userid],
                'timegraded ASC, id ASC'
            );
            $number = 0;
            foreach ($graded as $submission) {
                $number++;
                writer::with_context($context)->export_data(
                    [get_string('privacy:gradedattempts', 'mod_aiviva'), $number],
                    (object)[
                        'final_grade'    => $submission->final_grade,
                        'final_feedback' => $submission->final_feedback,
                        'timegraded'     => $submission->timegraded
                            ? transform::datetime($submission->timegraded)
                            : null,
                    ]
                );
            }
        }
    }

    /**
     * Deletes all data for all users in the given context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('aiviva', $context->instanceid);
        if (!$cm) {
            return;
        }

        foreach ($DB->get_records('aiviva_submissions', ['aiviva' => $cm->instance]) as $submission) {
            manager::delete_submission($submission, $context);
        }
        $DB->delete_records_select('aiviva_overrides', 'aiviva = :aiviva AND userid IS NOT NULL', ['aiviva' => $cm->instance]);
    }

    /**
     * Deletes all data for the given user in the given contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [$userid]);
        }
    }

    /**
     * Deletes all data for all users in the given userlist.
     *
     * @param approved_userlist $userlist The approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Deletes the attempts and overrides of the given users in one context, and
     * removes their name from the grades they edited as teachers.
     *
     * @param \context $context The context.
     * @param int[]    $userids The users.
     */
    private static function delete_users_in_context(\context $context, array $userids): void {
        global $DB;

        if (!$context instanceof \context_module || !$userids) {
            return;
        }
        $cm = get_coursemodule_from_id('aiviva', $context->instanceid);
        if (!$cm) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['aiviva'] = $cm->instance;

        $submissions = $DB->get_records_select('aiviva_submissions', "aiviva = :aiviva AND userid {$insql}", $params);
        foreach ($submissions as $submission) {
            manager::delete_submission($submission, $context);
        }
        $DB->delete_records_select('aiviva_overrides', "aiviva = :aiviva AND userid {$insql}", $params);

        // Grades these users edited as teachers belong to the students and stay, but no longer
        // name them: 0 keeps the grade marked as edited by a teacher, without saying which one.
        $DB->set_field_select('aiviva_submissions', 'grader_userid', 0, "aiviva = :aiviva AND grader_userid {$insql}", $params);
    }
}
