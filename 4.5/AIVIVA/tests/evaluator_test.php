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

namespace mod_aiviva;

use mod_aiviva\api\evaluator;
use mod_aiviva\api\openai_client;

#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\api\evaluator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\api\openai_client::class)]
/**
 * Tests for the final evaluation, with the AI service replaced by canned answers.
 *
 * @package    mod_aiviva
 * @category   test
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aiviva\api\evaluator
 * @covers     \mod_aiviva\api\openai_client
 */
final class evaluator_test extends \advanced_testcase {
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
     * Builds a Chat Completions answer.
     *
     * @param string $content      The text the model returns.
     * @param string $finishreason Why the model stopped ('stop', or 'length' when cut short).
     * @return string JSON body.
     */
    private function chat_answer(string $content, string $finishreason = 'stop'): string {
        return json_encode(['choices' => [['finish_reason' => $finishreason, 'message' => ['content' => $content]]]]);
    }

    /**
     * Builds the evaluation JSON a model would return.
     *
     * @return string
     */
    private function evaluation_json(): string {
        return json_encode([
            'grade_breakdown' => [
                'step1_pdf'      => ['score' => 80, 'feedback' => 'Solid document.'],
                'step2_video'    => ['score' => 60, 'feedback' => 'Clear presentation.'],
                'step3_tribunal' => ['score' => 70, 'feedback' => 'Good answers.'],
            ],
            'overall_feedback'         => 'Well done.',
            'strengths'                => ['Structure'],
            'areas_for_improvement'    => ['Depth'],
            'academic_integrity_flags' => [],
        ]);
    }

    /**
     * Creates an activity with a finished attempt.
     *
     * @param array $attempt Fields of the attempt that differ from a complete one.
     * @return array [submission, activity record, course, course module]
     */
    private function setup_attempt(array $attempt = []): array {
        global $DB;

        $this->resetAfterTest();
        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module  = $this->getDataGenerator()->create_module('aiviva', ['course' => $course->id, 'grade' => 10]);
        $aiviva  = $DB->get_record('aiviva', ['id' => $module->id], '*', MUST_EXIST);
        $cm      = get_coursemodule_from_instance('aiviva', $aiviva->id, $course->id, false, MUST_EXIST);

        $submission = $this->getDataGenerator()->get_plugin_generator('mod_aiviva')->create_submission($attempt + [
            'aiviva' => $aiviva->id, 'userid' => $student->id, 'status' => 'submitted', 'timesubmitted' => time(),
            'pdf_analysis' => 'Document analysis', 'video_fileid' => -1,
            'video_transcript' => 'What the student said', 'video_analysis' => 'Presentation analysis',
        ]);

        return [$submission, $aiviva, $course, $cm];
    }

    public function test_complete_evidence_releases_the_weighted_grade(): void {
        [$submission, $aiviva, $course, $cm] = $this->setup_attempt();

        \curl::mock_response($this->chat_answer($this->evaluation_json()));
        $graded = (new evaluator())->evaluate($submission, $aiviva, $course, $cm);

        $this->assertSame('graded', $graded->status);
        $this->assertSame('released', $graded->workflow_state);
        // Weights 33/33/34 over scores 80/60/70 give 70 per cent of a maximum grade of 10.
        $this->assertEqualsWithDelta(7.0, (float)$graded->final_grade, 0.0001);
        $this->assertEqualsWithDelta(7.0, (float)$graded->ai_grade, 0.0001);
        $this->assertStringContainsString('Well done.', $graded->final_feedback);
        $this->assertStringContainsString('Structure', $graded->final_feedback);
    }

    public function test_work_the_ai_could_not_read_holds_the_grade_for_review(): void {
        // The student recorded a presentation, but no transcript of it exists and the recording is gone.
        [$submission, $aiviva, $course, $cm] = $this->setup_attempt(['video_transcript' => null, 'video_analysis' => null]);
        $this->assertEquals(0, $aiviva->grading_workflow);

        \curl::mock_response($this->chat_answer($this->evaluation_json()));
        $graded = (new evaluator())->evaluate($submission, $aiviva, $course, $cm);

        $this->assertSame('graded', $graded->status);
        $this->assertSame('inreview', $graded->workflow_state);
    }

    public function test_answer_cut_short_is_requested_again(): void {
        $this->resetAfterTest();

        // Responses are served last in, first out.
        \curl::mock_response($this->chat_answer('The whole answer.'));
        \curl::mock_response($this->chat_answer('The whole ans', 'length'));

        $response = openai_client::get_instance()->chat_completion([['role' => 'user', 'content' => 'Hello']], 'gpt-6-luna');
        $this->assertSame('The whole answer.', $response['choices'][0]['message']['content']);
    }

    public function test_answer_still_cut_short_is_refused(): void {
        $this->resetAfterTest();

        \curl::mock_response($this->chat_answer('The whole', 'length'));
        \curl::mock_response($this->chat_answer('The wh', 'length'));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('error_ai_truncated', 'mod_aiviva'));
        openai_client::get_instance()->chat_completion([['role' => 'user', 'content' => 'Hello']], 'gpt-6-luna');
    }
}
