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

namespace mod_aiviva\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use mod_aiviva\local\manager;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\privacy\provider::class)]
/**
 * Tests for the privacy provider.
 *
 * @package    mod_aiviva
 * @category   test
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aiviva\privacy\provider
 */
final class provider_test extends provider_testcase {
    /**
     * Creates an activity with two students, each with an attempt, a tribunal
     * message, a file in every student file area and a user override.
     *
     * @return array [module context, first student, second student, activity id]
     */
    private function setup_attempts(): array {
        global $DB;

        $this->resetAfterTest();
        $course   = $this->getDataGenerator()->create_course();
        $module   = $this->getDataGenerator()->create_module('aiviva', ['course' => $course->id]);
        $context  = \context_module::instance($module->cmid);
        $students = [];

        foreach ([1, 2] as $number) {
            $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $submission = $this->getDataGenerator()->get_plugin_generator('mod_aiviva')->create_submission([
                'aiviva' => $module->id, 'userid' => $student->id, 'status' => 'graded', 'workflow_state' => 'released',
                'pdf_analysis' => "Analysis {$number}", 'video_transcript' => "Transcript {$number}",
                'final_grade' => 50 + $number, 'final_feedback' => "Feedback {$number}", 'timesubmitted' => time(),
            ]);
            $DB->insert_record('aiviva_tribunal_messages', (object)[
                'submission_id' => $submission->id, 'turn_number' => 1, 'speaker' => 'participant',
                'message_text' => "Answer {$number}", 'timestamp' => time(),
            ]);
            foreach (manager::submission_fileareas() as $filearea) {
                get_file_storage()->create_file_from_string([
                    'contextid' => $context->id, 'component' => 'mod_aiviva', 'filearea' => $filearea,
                    'itemid' => $submission->id, 'filepath' => '/', 'filename' => $filearea . '.bin', 'userid' => $student->id,
                ], 'content');
            }
            $DB->insert_record('aiviva_overrides', (object)[
                'aiviva' => $module->id, 'userid' => $student->id, 'max_attempts' => 5,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            $students[] = $student;
        }

        return [$context, $students[0], $students[1], (int)$module->id];
    }

    /**
     * Counts the stored student files of an activity.
     *
     * @param \context $context The module context.
     * @return int
     */
    private function count_student_files(\context $context): int {
        $count = 0;
        foreach (manager::submission_fileareas() as $filearea) {
            $count += count(get_file_storage()->get_area_files($context->id, 'mod_aiviva', $filearea, false, 'id', false));
        }
        return $count;
    }

    public function test_metadata_declares_tables_files_and_openai(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_aiviva'));
        $names = array_map(static fn($item) => $item->get_name(), $collection->get_collection());

        $this->assertContains('aiviva_submissions', $names);
        $this->assertContains('aiviva_tribunal_messages', $names);
        $this->assertContains('aiviva_overrides', $names);
        $this->assertContains('core_files', $names);
        $this->assertContains('openai_api', $names);
    }

    public function test_contexts_and_users_are_found(): void {
        [$context, $first, $second] = $this->setup_attempts();
        $outsider = $this->getDataGenerator()->create_user();

        $this->assertEquals([$context->id], provider::get_contexts_for_userid($first->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($outsider->id)->get_contextids());

        $userlist = new userlist($context, 'mod_aiviva');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $userlist->get_userids());
    }

    public function test_export_contains_the_attempt_the_conversation_and_the_files(): void {
        [$context, $first] = $this->setup_attempts();

        $this->export_context_data_for_user($first->id, $context, 'mod_aiviva');
        $exported = writer::with_context($context);
        $this->assertTrue($exported->has_any_data());

        $subcontext = [get_string('attempt_number', 'mod_aiviva', 1)];
        $attempt = $exported->get_data($subcontext);
        $this->assertSame('Analysis 1', $attempt->pdf_analysis);
        $this->assertSame('Transcript 1', $attempt->video_transcript);
        $this->assertSame('Feedback 1', $attempt->final_feedback);

        $log = $exported->get_data(array_merge($subcontext, [get_string('tribunal_log', 'mod_aiviva')]));
        $this->assertSame('Answer 1', $log->messages[0]['text']);

        $this->assertCount(count(manager::submission_fileareas()), $exported->get_files($subcontext));
    }

    public function test_delete_for_one_user_keeps_the_others(): void {
        global $DB;

        [$context, $first, $second, $aivivaid] = $this->setup_attempts();

        provider::delete_data_for_user(new approved_contextlist($first, 'mod_aiviva', [$context->id]));

        $this->assertFalse($DB->record_exists('aiviva_submissions', ['aiviva' => $aivivaid, 'userid' => $first->id]));
        $this->assertFalse($DB->record_exists('aiviva_overrides', ['aiviva' => $aivivaid, 'userid' => $first->id]));
        $this->assertTrue($DB->record_exists('aiviva_submissions', ['aiviva' => $aivivaid, 'userid' => $second->id]));
        $this->assertTrue($DB->record_exists('aiviva_overrides', ['aiviva' => $aivivaid, 'userid' => $second->id]));
        $this->assertSame(1, $DB->count_records('aiviva_tribunal_messages'));
        $this->assertSame(count(manager::submission_fileareas()), $this->count_student_files($context));
    }

    /**
     * Marks the first student's attempt as graded by a new teacher.
     *
     * @param \context $context  The module context.
     * @param \stdClass $student The student whose attempt is graded.
     * @param int $aivivaid      The activity id.
     * @return \stdClass The teacher.
     */
    private function grade_as_teacher(\context $context, \stdClass $student, int $aivivaid): \stdClass {
        global $DB;

        $course  = get_course($context->get_course_context()->instanceid);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $DB->set_field_select(
            'aiviva_submissions',
            'grader_userid',
            $teacher->id,
            'aiviva = :aiviva AND userid = :userid',
            ['aiviva' => $aivivaid, 'userid' => $student->id]
        );
        $DB->set_field('aiviva_submissions', 'timegraded', time(), ['aiviva' => $aivivaid, 'userid' => $student->id]);

        return $teacher;
    }

    public function test_teacher_who_edited_a_grade_is_found_and_gets_an_export(): void {
        [$context, $first, $second, $aivivaid] = $this->setup_attempts();
        $teacher = $this->grade_as_teacher($context, $first, $aivivaid);

        $this->assertEquals([$context->id], provider::get_contexts_for_userid($teacher->id)->get_contextids());

        $userlist = new userlist($context, 'mod_aiviva');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$first->id, $second->id, $teacher->id], $userlist->get_userids());

        $this->export_context_data_for_user($teacher->id, $context, 'mod_aiviva');
        $exported = writer::with_context($context);
        $graded = $exported->get_data([get_string('privacy:gradedattempts', 'mod_aiviva'), 1]);
        $this->assertEquals(51, $graded->final_grade);
        $this->assertSame('Feedback 1', $graded->final_feedback);
        $this->assertNotEmpty($graded->timegraded);

        // Nothing of the student's own work, nor who the student is, goes into the teacher's export.
        $this->assertObjectNotHasProperty('userid', $graded);
        $this->assertObjectNotHasProperty('pdf_analysis', $graded);
        $this->assertEmpty($exported->get_data([get_string('attempt_number', 'mod_aiviva', 1)]));
    }

    public function test_delete_for_a_teacher_removes_their_name_but_keeps_the_grade(): void {
        global $DB;

        [$context, $first, , $aivivaid] = $this->setup_attempts();
        $teacher = $this->grade_as_teacher($context, $first, $aivivaid);

        provider::delete_data_for_user(new approved_contextlist($teacher, 'mod_aiviva', [$context->id]));

        $submission = $DB->get_record('aiviva_submissions', ['aiviva' => $aivivaid, 'userid' => $first->id], '*', MUST_EXIST);
        $this->assertEquals(0, $submission->grader_userid);
        $this->assertEquals(51, $submission->final_grade);
        $this->assertTrue(manager::grade_was_edited($submission));
        $this->assertEmpty(provider::get_contexts_for_userid($teacher->id)->get_contextids());

        // The same through the list of users of a context.
        $teacher = $this->grade_as_teacher($context, $first, $aivivaid);
        provider::delete_data_for_users(new approved_userlist($context, 'mod_aiviva', [$teacher->id]));
        $this->assertEquals(0, $DB->get_field('aiviva_submissions', 'grader_userid', ['id' => $submission->id]));
        $this->assertSame(2, $DB->count_records('aiviva_submissions', ['aiviva' => $aivivaid]));
    }

    public function test_delete_for_a_list_of_users(): void {
        global $DB;

        [$context, $first, $second, $aivivaid] = $this->setup_attempts();

        provider::delete_data_for_users(new approved_userlist($context, 'mod_aiviva', [$second->id]));

        $this->assertTrue($DB->record_exists('aiviva_submissions', ['aiviva' => $aivivaid, 'userid' => $first->id]));
        $this->assertFalse($DB->record_exists('aiviva_submissions', ['aiviva' => $aivivaid, 'userid' => $second->id]));
        $this->assertFalse($DB->record_exists('aiviva_overrides', ['aiviva' => $aivivaid, 'userid' => $second->id]));
    }

    public function test_delete_for_all_users_keeps_group_overrides(): void {
        global $DB;

        [$context, , , $aivivaid] = $this->setup_attempts();
        $DB->insert_record('aiviva_overrides', (object)[
            'aiviva' => $aivivaid, 'groupid' => 7, 'max_attempts' => 3, 'timecreated' => time(), 'timemodified' => time(),
        ]);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $DB->count_records('aiviva_submissions', ['aiviva' => $aivivaid]));
        $this->assertSame(0, $DB->count_records('aiviva_tribunal_messages'));
        $this->assertSame(0, $this->count_student_files($context));
        $this->assertSame(1, $DB->count_records('aiviva_overrides', ['aiviva' => $aivivaid]));
    }
}
