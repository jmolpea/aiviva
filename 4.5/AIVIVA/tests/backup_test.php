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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\backup_aiviva_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_aiviva_activity_structure_step::class)]
/**
 * Backup and restore round trip.
 *
 * @package    mod_aiviva
 * @category   test
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_aiviva_activity_structure_step
 * @covers     \restore_aiviva_activity_structure_step
 */
final class backup_test extends \advanced_testcase {
    public function test_course_backup_and_restore_keeps_attempts(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module  = $this->getDataGenerator()->create_module('aiviva', [
            'course' => $course->id, 'name' => 'Viva to copy', 'weight_pdf' => 20, 'weight_video' => 30,
            'weight_tribunal' => 50, 'step1_maxfilesize' => 7, 'safety_extra_prompt' => 'Be kind.',
        ]);
        $context = \context_module::instance($module->cmid);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_aiviva');
        $submission = $generator->create_submission([
            'aiviva' => $module->id, 'userid' => $student->id, 'status' => 'graded', 'workflow_state' => 'released',
            'ai_grade' => 61.5, 'final_grade' => 70, 'final_feedback' => 'Well argued.',
            'tribunal_briefing' => 'Briefing text', 'video_fileid' => 1,
        ]);
        $DB->insert_record('aiviva_tribunal_messages', (object)[
            'submission_id' => $submission->id, 'turn_number' => 1, 'speaker' => 'participant',
            'message_text' => 'My answer', 'timestamp' => time(),
        ]);
        $DB->insert_record('aiviva_overrides', (object)[
            'aiviva' => $module->id, 'userid' => $student->id, 'max_attempts' => 5,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'mod_aiviva', 'filearea' => 'tribunal_audio',
            'itemid' => $submission->id, 'filepath' => '/', 'filename' => 'answer_001.webm',
        ], 'audio');

        // Backup the course with user data.
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        // Restore into a new course.
        $backupdir = 'aiviva_test_' . random_string(8);
        $backupfile->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($backupdir)
        );
        $newcourseid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $rc = new \restore_controller(
            $backupdir,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $copy = $DB->get_record('aiviva', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame('Viva to copy', $copy->name);
        $this->assertEquals(20, $copy->weight_pdf);
        $this->assertEquals(50, $copy->weight_tribunal);
        $this->assertEquals(7, $copy->step1_maxfilesize);
        $this->assertSame('Be kind.', $copy->safety_extra_prompt);

        $restored = $DB->get_record('aiviva_submissions', ['aiviva' => $copy->id], '*', MUST_EXIST);
        $this->assertEquals($student->id, $restored->userid);
        $this->assertSame('graded', $restored->status);
        $this->assertEquals(61.5, $restored->ai_grade);
        $this->assertEquals(70, $restored->final_grade);
        $this->assertSame('Briefing text', $restored->tribunal_briefing);

        $message = $DB->get_record('aiviva_tribunal_messages', ['submission_id' => $restored->id], '*', MUST_EXIST);
        $this->assertSame('My answer', $message->message_text);

        $override = $DB->get_record('aiviva_overrides', ['aiviva' => $copy->id], '*', MUST_EXIST);
        $this->assertEquals($student->id, $override->userid);
        $this->assertEquals(5, $override->max_attempts);

        $newcm = get_coursemodule_from_instance('aiviva', $copy->id, $newcourseid, false, MUST_EXIST);
        $files = get_file_storage()->get_area_files(
            \context_module::instance($newcm->id)->id,
            'mod_aiviva',
            'tribunal_audio',
            $restored->id,
            'id',
            false
        );
        $this->assertCount(1, $files);
        $this->assertSame('answer_001.webm', reset($files)->get_filename());
    }
}
