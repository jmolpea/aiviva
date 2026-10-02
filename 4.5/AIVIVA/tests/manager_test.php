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

use mod_aiviva\local\manager;

/**
 * Tests for the submission lifecycle rules.
 *
 * @package    mod_aiviva
 * @category   test
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_aiviva\local\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Creates a course with one activity and one enrolled student.
     *
     * @param array $settings Activity settings.
     * @return array [activity record, student, course]
     */
    private function setup_activity(array $settings = []): array {
        global $DB;

        $this->resetAfterTest();
        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module  = $this->getDataGenerator()->create_module('aiviva', ['course' => $course->id] + $settings);
        $aiviva  = $DB->get_record('aiviva', ['id' => $module->id], '*', MUST_EXIST);

        return [$aiviva, $student, $course];
    }

    public function test_weights_are_normalised(): void {
        $weights = manager::get_weights((object)['weight_pdf' => 50, 'weight_video' => 25, 'weight_tribunal' => 25]);
        $this->assertEqualsWithDelta(0.5, $weights['step1_pdf'], 0.0001);
        $this->assertEqualsWithDelta(0.25, $weights['step2_video'], 0.0001);
        $this->assertEqualsWithDelta(1.0, array_sum($weights), 0.0001);

        // All-zero weights fall back to equal thirds rather than dividing by zero.
        $weights = manager::get_weights((object)['weight_pdf' => 0, 'weight_video' => 0, 'weight_tribunal' => 0]);
        $this->assertEqualsWithDelta(1 / 3, $weights['step3_tribunal'], 0.0001);
    }

    public function test_weighted_percentage_bounds_scores(): void {
        $weights = ['step1_pdf' => 0.5, 'step2_video' => 0.25, 'step3_tribunal' => 0.25];
        $breakdown = [
            'step1_pdf'      => ['score' => 80],
            'step2_video'    => ['score' => 150], // Out of range: counted as 100.
            'step3_tribunal' => ['score' => -20], // Out of range: counted as 0.
        ];
        $this->assertSame(65.0, manager::weighted_percentage($breakdown, $weights));

        // A missing step scores zero.
        $this->assertSame(40.0, manager::weighted_percentage(['step1_pdf' => ['score' => 80]], $weights));
    }

    public function test_availability(): void {
        $now = 1000000;
        $this->assertSame('', manager::availability((object)['timeopen' => 0, 'timeclose' => 0], $now));
        $this->assertSame('notopen', manager::availability((object)['timeopen' => $now + 10, 'timeclose' => 0], $now));
        $this->assertSame('closed', manager::availability((object)['timeopen' => 0, 'timeclose' => $now - 10], $now));
        $this->assertSame('', manager::availability((object)['timeopen' => $now - 10, 'timeclose' => $now + 10], $now));
    }

    public function test_user_override_beats_group_override(): void {
        global $DB;

        [$aiviva, $student, $course] = $this->setup_activity(['max_attempts' => 1, 'timeclose' => 5000]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group->id, $student->id);

        $settings = manager::get_effective_settings($aiviva, $student->id);
        $this->assertSame(1, $settings->max_attempts);
        $this->assertSame(5000, $settings->timeclose);

        $DB->insert_record('aiviva_overrides', (object)[
            'aiviva' => $aiviva->id, 'groupid' => $group->id, 'max_attempts' => 3, 'timeclose' => 6000,
        ]);
        $settings = manager::get_effective_settings($aiviva, $student->id);
        $this->assertSame(3, $settings->max_attempts);
        $this->assertSame(6000, $settings->timeclose);

        // The user override only sets the attempt limit, so the group's closing date still applies.
        $DB->insert_record('aiviva_overrides', (object)[
            'aiviva' => $aiviva->id, 'userid' => $student->id, 'max_attempts' => 0,
        ]);
        $settings = manager::get_effective_settings($aiviva, $student->id);
        $this->assertSame(0, $settings->max_attempts);
        $this->assertSame(6000, $settings->timeclose);
    }

    public function test_attempts_are_limited_and_sequential(): void {
        global $DB;

        [$aiviva, $student] = $this->setup_activity(['max_attempts' => 2]);
        $settings = manager::get_effective_settings($aiviva, $student->id);

        $this->assertNull(manager::get_latest_submission($aiviva->id, $student->id));
        $this->assertTrue(manager::can_start_attempt($settings, null));

        $first = manager::create_attempt($aiviva, $student->id, null);
        $this->assertSame(1, (int)$first->attempt);
        $this->assertSame(1, (int)$first->gdpr_consent);

        // An attempt in progress blocks a new one.
        $this->assertFalse(manager::can_start_attempt($settings, $first));

        // Graded but not yet released: still blocked.
        $DB->update_record('aiviva_submissions', (object)[
            'id' => $first->id, 'status' => 'graded', 'workflow_state' => 'inreview',
        ]);
        $first = manager::get_latest_submission($aiviva->id, $student->id);
        $this->assertFalse(manager::can_start_attempt($settings, $first));

        // Released: the second attempt is allowed.
        $DB->set_field('aiviva_submissions', 'workflow_state', 'released', ['id' => $first->id]);
        $first = manager::get_latest_submission($aiviva->id, $student->id);
        $this->assertTrue(manager::can_start_attempt($settings, $first));

        $second = manager::create_attempt($aiviva, $student->id, $first);
        $this->assertSame(2, (int)$second->attempt);
        $this->assertEquals($second->id, manager::get_latest_submission($aiviva->id, $student->id)->id);

        // The limit of two is reached even once the second attempt is released.
        $DB->update_record('aiviva_submissions', (object)[
            'id' => $second->id, 'status' => 'graded', 'workflow_state' => 'released',
        ]);
        $second = manager::get_latest_submission($aiviva->id, $student->id);
        $this->assertFalse(manager::can_start_attempt($settings, $second));
    }

    public function test_no_attempt_when_closed(): void {
        [$aiviva, $student] = $this->setup_activity(['timeclose' => time() - HOURSECS]);
        $settings = manager::get_effective_settings($aiviva, $student->id);
        $this->assertFalse(manager::can_start_attempt($settings, null));
    }

    public function test_tribunal_clock_is_server_side(): void {
        $aiviva = (object)['step3_duration' => 10];

        // Not started: the full duration is left.
        $this->assertSame(600, manager::tribunal_remaining($aiviva, (object)['tribunal_timestart' => null], 5000));

        // Started 4 minutes ago.
        $this->assertSame(360, manager::tribunal_remaining($aiviva, (object)['tribunal_timestart' => 5000], 5240));

        // Overrun: negative.
        $this->assertSame(-60, manager::tribunal_remaining($aiviva, (object)['tribunal_timestart' => 5000], 5660));
    }

    public function test_mark_submitted_only_once(): void {
        global $DB;

        [$aiviva, $student, $course] = $this->setup_activity();
        $cm = get_coursemodule_from_instance('aiviva', $aiviva->id, $course->id, false, MUST_EXIST);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiviva');
        $submission = $generator->create_submission([
            'aiviva' => $aiviva->id, 'userid' => $student->id, 'status' => 'step3', 'tribunal_timestart' => time() - 700,
        ]);

        $this->assertTrue(manager::mark_submitted($submission, $course, $cm));
        $this->assertSame('submitted', $DB->get_field('aiviva_submissions', 'status', ['id' => $submission->id]));

        // A second close (second tab, cron) must not submit again.
        $DB->set_field('aiviva_submissions', 'timesubmitted', 1, ['id' => $submission->id]);
        $this->assertFalse(manager::mark_submitted($submission, $course, $cm));
        $this->assertEquals(1, $DB->get_field('aiviva_submissions', 'timesubmitted', ['id' => $submission->id]));
    }

    public function test_delete_submission_removes_messages_and_files(): void {
        global $DB;

        [$aiviva, $student, $course] = $this->setup_activity();
        $cm = get_coursemodule_from_instance('aiviva', $aiviva->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiviva');
        $submission = $generator->create_submission(['aiviva' => $aiviva->id, 'userid' => $student->id]);

        $DB->insert_record('aiviva_tribunal_messages', (object)[
            'submission_id' => $submission->id, 'turn_number' => 0, 'speaker' => 'tribunal_1',
            'message_text' => 'Welcome', 'timestamp' => time(),
        ]);
        $fs = get_file_storage();
        foreach (manager::submission_fileareas() as $filearea) {
            $fs->create_file_from_string([
                'contextid' => $context->id, 'component' => 'mod_aiviva', 'filearea' => $filearea,
                'itemid' => $submission->id, 'filepath' => '/', 'filename' => 'file.bin',
            ], 'content');
        }

        manager::delete_submission($submission, $context);

        $this->assertFalse($DB->record_exists('aiviva_submissions', ['id' => $submission->id]));
        $this->assertFalse($DB->record_exists('aiviva_tribunal_messages', ['submission_id' => $submission->id]));
        foreach (manager::submission_fileareas() as $filearea) {
            $this->assertTrue($fs->is_area_empty($context->id, 'mod_aiviva', $filearea, $submission->id));
        }
    }
}
