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

use mod_aiviva\form\mod_form_helper;

#[\PHPUnit\Framework\Attributes\CoversFunction('aiviva_update_grades')]
#[\PHPUnit\Framework\Attributes\CoversFunction('aiviva_notify_teacher_submission_ready')]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\form\mod_form_helper::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\completion\custom_completion::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_aiviva\api\prompt_helper::class)]
/**
 * Tests for lib.php, the model catalogue and custom completion.
 *
 * @package    mod_aiviva
 * @category   test
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::aiviva_update_grades
 * @covers     ::aiviva_notify_teacher_submission_ready
 * @covers     \mod_aiviva\form\mod_form_helper
 * @covers     \mod_aiviva\completion\custom_completion
 * @covers     \mod_aiviva\api\prompt_helper
 */
final class lib_test extends \advanced_testcase {
    /**
     * Only released grades reach the gradebook, and the best attempt counts.
     */
    public function test_gradebook_gets_best_released_grade(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/aiviva/lib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $this->resetAfterTest();
        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module  = $this->getDataGenerator()->create_module('aiviva', ['course' => $course->id]);
        $aiviva  = $DB->get_record('aiviva', ['id' => $module->id], '*', MUST_EXIST);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiviva');

        $getgrade = static function () use ($course, $aiviva, $student) {
            $grades = grade_get_grades($course->id, 'mod', 'aiviva', $aiviva->id, $student->id);
            return $grades->items[0]->grades[$student->id]->grade;
        };

        // A draft grade held for review must not be visible in the gradebook.
        $first = $generator->create_submission([
            'aiviva' => $aiviva->id, 'userid' => $student->id, 'attempt' => 1, 'status' => 'graded',
            'final_grade' => 70, 'workflow_state' => 'inreview', 'timegraded' => time(),
        ]);
        aiviva_update_grades($aiviva, $student->id);
        $this->assertNull($getgrade());

        $DB->set_field('aiviva_submissions', 'workflow_state', 'released', ['id' => $first->id]);
        aiviva_update_grades($aiviva, $student->id);
        $this->assertEquals(70, $getgrade());

        // A worse second attempt does not lower the grade; a better one raises it.
        $second = $generator->create_submission([
            'aiviva' => $aiviva->id, 'userid' => $student->id, 'attempt' => 2, 'status' => 'graded',
            'final_grade' => 55, 'workflow_state' => 'released', 'timegraded' => time(),
        ]);
        aiviva_update_grades($aiviva, $student->id);
        $this->assertEquals(70, $getgrade());

        $DB->set_field('aiviva_submissions', 'final_grade', 90, ['id' => $second->id]);
        aiviva_update_grades($aiviva, $student->id);
        $this->assertEquals(90, $getgrade());
    }

    /**
     * Retired and disabled models are replaced by one that can be called.
     */
    public function test_model_resolution(): void {
        $this->resetAfterTest();

        $this->assertSame('gpt-6.1-sol', mod_form_helper::resolve_model('gpt-4o'));
        $this->assertSame('gpt-6-luna', mod_form_helper::resolve_model('gpt-4o-mini'));
        $this->assertSame('gpt-6-astra', mod_form_helper::resolve_model('gpt-6-astra'));
        $this->assertSame(mod_form_helper::DEFAULT_MODEL, mod_form_helper::resolve_model('no-such-model'));
        $this->assertSame(mod_form_helper::DEFAULT_MODEL, mod_form_helper::resolve_model(null));

        // With only the economical model enabled, everything resolves to it.
        set_config('enabled_models', 'gpt-6-luna', 'mod_aiviva');
        $this->assertSame('gpt-6-luna', mod_form_helper::resolve_model('gpt-6-astra'));
        $this->assertSame('gpt-6-luna', mod_form_helper::resolve_model(null));
        $this->assertSame(['gpt-6-luna'], array_keys(mod_form_helper::get_model_options()));
    }

    /**
     * Unknown voices fall back to the default.
     */
    public function test_voice_resolution(): void {
        $this->assertSame('coral', mod_form_helper::resolve_voice('coral'));
        $this->assertSame(mod_form_helper::DEFAULT_VOICE, mod_form_helper::resolve_voice('no-such-voice'));
        $this->assertSame(mod_form_helper::DEFAULT_VOICE, mod_form_helper::resolve_voice(null));
        $this->assertCount(13, mod_form_helper::get_voice_options());
    }

    /**
     * The "complete all three steps" rule follows the attempt status.
     */
    public function test_custom_completion(): void {
        global $DB;

        $this->resetAfterTest();
        $course  = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module  = $this->getDataGenerator()->create_module('aiviva', [
            'course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionsubmit' => 1,
        ]);
        $cm = \cm_info::create(get_coursemodule_from_instance('aiviva', $module->id));
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiviva');

        $completion = new \mod_aiviva\completion\custom_completion($cm, (int)$student->id);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionsubmit'));

        $submission = $generator->create_submission(['aiviva' => $module->id, 'userid' => $student->id, 'status' => 'step3']);
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state('completionsubmit'));

        $DB->set_field('aiviva_submissions', 'status', 'submitted', ['id' => $submission->id]);
        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state('completionsubmit'));
    }

    /**
     * In separate groups mode, only the teachers who can review the student are told about the attempt.
     */
    public function test_review_notification_respects_separate_groups(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/aiviva/lib.php');

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course    = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $student   = $generator->create_and_enrol($course, 'student');
        $mine      = $generator->create_and_enrol($course, 'teacher');
        $other     = $generator->create_and_enrol($course, 'teacher');
        $editing   = $generator->create_and_enrol($course, 'editingteacher');
        $group     = $generator->create_group(['courseid' => $course->id]);
        $othergroup = $generator->create_group(['courseid' => $course->id]);
        groups_add_member($group, $student);
        groups_add_member($group, $mine);
        groups_add_member($othergroup, $other);

        $module = $generator->create_module('aiviva', ['course' => $course->id]);
        $aiviva = $DB->get_record('aiviva', ['id' => $module->id], '*', MUST_EXIST);
        $cm     = get_coursemodule_from_instance('aiviva', $aiviva->id, $course->id, false, MUST_EXIST);
        $submission = $generator->get_plugin_generator('mod_aiviva')->create_submission([
            'aiviva' => $aiviva->id, 'userid' => $student->id, 'status' => 'graded', 'workflow_state' => 'inreview',
        ]);

        $sink = $this->redirectMessages();
        aiviva_notify_teacher_submission_ready($aiviva, $submission, $course, $cm);
        $recipients = array_map(static fn($message) => (int)$message->useridto, $sink->get_messages());
        $sink->close();

        // The teacher of the student's group, and the editing teacher, who can access all groups.
        $this->assertEqualsCanonicalizing([(int)$mine->id, (int)$editing->id], $recipients);
    }

    /**
     * The pseudonym is stable, does not contain the user id, and depends on the site's secret.
     */
    public function test_pseudonym(): void {
        $this->resetAfterTest();

        $first = \mod_aiviva\api\prompt_helper::pseudonym(42);
        $this->assertSame($first, \mod_aiviva\api\prompt_helper::pseudonym(42));
        $this->assertNotSame($first, \mod_aiviva\api\prompt_helper::pseudonym(43));
        $this->assertMatchesRegularExpression('/^STUDENT-[0-9a-f]{12}$/', $first);
        $this->assertNotEmpty(get_config('mod_aiviva', 'anonymize_salt'));

        set_config('anonymize_salt', 'another secret', 'mod_aiviva');
        $this->assertNotSame($first, \mod_aiviva\api\prompt_helper::pseudonym(42));
    }
}
