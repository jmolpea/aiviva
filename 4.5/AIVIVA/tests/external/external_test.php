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

use core_external\external_api;
use mod_aiviva\api\openai_client;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\external\get_attempt_status::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\external\start_tribunal::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\external\get_next_question::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\external\close_tribunal::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\external\regenerate_analysis::class)]
/**
 * Tests for the external functions, with the AI service replaced by canned answers.
 *
 * @package    mod_aiviva
 * @category   test
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aiviva\external\get_attempt_status
 * @covers     \mod_aiviva\external\start_tribunal
 * @covers     \mod_aiviva\external\get_next_question
 * @covers     \mod_aiviva\external\close_tribunal
 * @covers     \mod_aiviva\external\regenerate_analysis
 */
final class external_test extends \advanced_testcase {
    /**
     * Gives every test a client built from this test's settings, with the content filter off
     * so that the only requests made are the ones each test provides an answer for.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('safety_content_filter', 0, 'mod_aiviva');
        $instance = new \ReflectionProperty(openai_client::class, 'instance');
        $instance->setValue(null, null);
    }

    /**
     * Creates an activity with a student who has an attempt.
     *
     * @param array $attempt Fields of the attempt.
     * @return array [activity record with cmid, student, attempt, course]
     */
    private function setup_attempt(array $attempt = []): array {
        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module  = $this->getDataGenerator()->create_module('aiviva', ['course' => $course->id, 'grade' => 10]);
        $submission = $this->getDataGenerator()->get_plugin_generator('mod_aiviva')->create_submission($attempt + [
            'aiviva' => $module->id, 'userid' => $student->id,
        ]);

        return [$module, $student, $submission, $course];
    }

    /**
     * Builds a Chat Completions answer.
     *
     * @param string $content The text the model returns.
     * @return string JSON body.
     */
    private function chat_answer(string $content): string {
        return json_encode(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => $content]]]]);
    }

    public function test_status_of_own_attempt_only(): void {
        [$module, $student, $submission, $course] = $this->setup_attempt(['status' => 'step2']);

        $this->setUser($student);
        $result = external_api::clean_returnvalue(
            get_attempt_status::execute_returns(),
            get_attempt_status::execute($module->cmid, $submission->id)
        );
        $this->assertSame('step2', $result['status']);

        // Another student cannot read it.
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidsubmissionstatus', 'mod_aiviva'));
        get_attempt_status::execute($module->cmid, $submission->id);
    }

    public function test_tribunal_starts_then_resumes_without_restarting_the_clock(): void {
        global $DB;

        [$module, $student, $submission] = $this->setup_attempt(['status' => 'step3', 'pdf_analysis' => 'Analysis']);
        $this->setUser($student);

        // Served last in, first out: the briefing is asked for first, then the opening words.
        \curl::mock_response($this->chat_answer('Welcome, candidate.'));
        \curl::mock_response($this->chat_answer('The briefing.'));
        $started = external_api::clean_returnvalue(
            start_tribunal::execute_returns(),
            start_tribunal::execute($module->cmid, $submission->id)
        );
        $this->assertFalse($started['resumed']);
        $this->assertSame('Welcome, candidate.', $started['turn']['text']);
        $this->assertSame('Dr. Smith', $started['turn']['name']);
        $timestart = $DB->get_field('aiviva_submissions', 'tribunal_timestart', ['id' => $submission->id]);
        $this->assertNotEmpty($timestart);

        // A reload: the opening words are history, and nobody speaks until the student has answered.
        $resumed = external_api::clean_returnvalue(
            start_tribunal::execute_returns(),
            start_tribunal::execute($module->cmid, $submission->id)
        );
        $this->assertTrue($resumed['resumed']);
        $this->assertArrayNotHasKey('turn', $resumed);
        $this->assertSame('Welcome, candidate.', $resumed['history'][0]['text']);
        $this->assertEquals($timestart, $DB->get_field('aiviva_submissions', 'tribunal_timestart', ['id' => $submission->id]));

        // Asking for the next question again returns the stored one instead of generating another.
        $next = external_api::clean_returnvalue(
            get_next_question::execute_returns(),
            get_next_question::execute($module->cmid, $submission->id)
        );
        $this->assertSame('Welcome, candidate.', $next['turn']['text']);
        $this->assertGreaterThan(0, $next['remaining']);
    }

    public function test_closing_needs_the_time_to_be_up_and_queues_the_evaluation(): void {
        global $DB;

        [$module, $student, $submission] = $this->setup_attempt(['status' => 'step3', 'tribunal_timestart' => time() - 60]);
        $this->setUser($student);

        try {
            close_tribunal::execute($module->cmid, $submission->id);
            $this->fail('A session with time left cannot be closed.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_tribunal_not_finished', $e->errorcode);
        }

        $DB->set_field('aiviva_submissions', 'tribunal_timestart', time() - 700, ['id' => $submission->id]);
        \curl::mock_response($this->chat_answer('Thank you, candidate.'));
        $closed = external_api::clean_returnvalue(
            close_tribunal::execute_returns(),
            close_tribunal::execute($module->cmid, $submission->id)
        );
        $this->assertSame('submitted', $closed['status']);
        $this->assertSame('Thank you, candidate.', $closed['turn']['text']);
        $this->assertSame('submitted', $DB->get_field('aiviva_submissions', 'status', ['id' => $submission->id]));
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(\mod_aiviva\task\evaluate_submission_task::class));

        // A repeated request changes nothing and queues nothing.
        $again = external_api::clean_returnvalue(
            close_tribunal::execute_returns(),
            close_tribunal::execute($module->cmid, $submission->id)
        );
        $this->assertSame('submitted', $again['status']);
        $this->assertArrayNotHasKey('turn', $again);
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(\mod_aiviva\task\evaluate_submission_task::class));
    }

    public function test_regeneration_is_for_teachers_and_has_a_cooldown(): void {
        [$module, $student, $submission, $course] = $this->setup_attempt([
            'status' => 'graded', 'workflow_state' => 'inreview', 'timesubmitted' => time(),
            'pdf_analysis' => 'Document analysis', 'video_transcript' => 'What the student said',
            'video_analysis' => 'Presentation analysis',
        ]);

        $this->setUser($student);
        try {
            regenerate_analysis::execute($module->cmid, $submission->id, 'evaluation');
            $this->fail('A student cannot regenerate the evaluation.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        \curl::mock_response($this->chat_answer(json_encode([
            'grade_breakdown' => [
                'step1_pdf'      => ['score' => 50, 'feedback' => 'Document.'],
                'step2_video'    => ['score' => 50, 'feedback' => 'Presentation.'],
                'step3_tribunal' => ['score' => 50, 'feedback' => 'Tribunal.'],
            ],
            'overall_feedback' => 'Regenerated.',
        ])));
        $result = external_api::clean_returnvalue(
            regenerate_analysis::execute_returns(),
            regenerate_analysis::execute($module->cmid, $submission->id, 'evaluation')
        );
        $this->assertSame('graded', $result['status']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('regen_cooldown', 'mod_aiviva'));
        regenerate_analysis::execute($module->cmid, $submission->id, 'evaluation');
    }
}
