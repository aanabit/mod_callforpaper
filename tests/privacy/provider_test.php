<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy provider tests.
 *
 * @package    mod_callforpaper
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_callforpaper\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_userlist;
use mod_callforpaper\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests class.
 *
 * @package    mod_callforpaper
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var stdClass The student object. */
    protected $student;
    /** @var stdClass The student object. */
    protected $student2;
    /** @var stdClass The student object. */
    protected $student3;

    /** @var stdClass The callforpaper object. */
    protected $callforpapermodule;

    /** @var stdClass The course object. */
    protected $course;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $params = [
            'course' => $course->id,
            'name' => 'Call for paper module',
            'comments' => 1,
            'assessed' => 1,
        ];

        // The callforpaper activity.
        $callforpapermodule = $this->get_generator()->create_instance($params);

        $fieldtypes = array('checkbox', 'date', 'menu', 'multimenu', 'number', 'radiobutton', 'text', 'textarea', 'url',
            'file', 'picture');
        // Creating test Fields with default parameter values.
        foreach ($fieldtypes as $count => $fieldtype) {
            // Creating variables dynamically.
            $fieldname = 'field' . $count;
            $record = new \stdClass();
            $record->name = $fieldname;
            $record->description = $fieldname . ' descr';
            $record->type = $fieldtype;

            ${$fieldname} = $this->get_generator()->create_field($record, $callforpapermodule);
        }

        $cm = get_coursemodule_from_instance('callforpaper', $callforpapermodule->id);

        // Create a student.
        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $student3 = $generator->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $generator->enrol_user($student1->id,  $course->id, $studentrole->id);
        $generator->enrol_user($student2->id,  $course->id, $studentrole->id);
        $generator->enrol_user($student3->id,  $course->id, $studentrole->id);

        // Add records.
        $this->setUser($student1);
        $record1id = $this->generate_callforpaper_record($callforpapermodule);
        $this->generate_callforpaper_record($callforpapermodule);

        $this->setUser($student2);
        $this->generate_callforpaper_record($callforpapermodule);
        $this->generate_callforpaper_record($callforpapermodule);
        $this->generate_callforpaper_record($callforpapermodule);

        $this->setUser($student3);
        $this->generate_callforpaper_record($callforpapermodule);

        $this->student = $student1;
        $this->student2 = $student2;
        $this->student3 = $student3;
        $this->callforpapermodule = $callforpapermodule;
        $this->course = $course;
    }

    /**
     * Get mod_callforpaper generator
     *
     * @return mod_callforpaper_generator
     */
    protected function get_generator() {
        return $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
    }

    /**
     * Generates one record in the callforpaper module as the current student
     *
     * @param stdClass $callforpapermodule
     * @return mixed
     */
    protected function generate_callforpaper_record($callforpapermodule) {
        global $DB;

        static $counter = 0;
        $counter++;

        $contents = array();
        $contents[] = array('opt1', 'opt2', 'opt3', 'opt4');
        $contents[] = sprintf("%02f", $counter) . '-01-2000';
        $contents[] = 'menu1';
        $contents[] = array('multimenu1', 'multimenu2', 'multimenu3', 'multimenu4');
        $contents[] = 5 * $counter;
        $contents[] = 'radioopt1';
        $contents[] = 'text for testing' . $counter;
        $contents[] = "<p>text area testing $counter<br /></p>";
        $contents[] = array('example.url', 'sampleurl' . $counter);
        $contents[] = "Filename{$counter}.pdf"; // File - filename.
        $contents[] = array("Cat{$counter}.jpg", 'Cat' . $counter); // Picture - filename with alt text.
        $count = 0;
        $fieldcontents = array();
        $fields = $DB->get_records('callforpaper_fields', array('callforpaperid' => $callforpapermodule->id), 'id');
        foreach ($fields as $fieldrecord) {
            $fieldcontents[$fieldrecord->id] = $contents[$count++];
        }
        $tags = ['Cats', 'mice' . $counter];
        return $this->get_generator()->create_entry($callforpapermodule, $fieldcontents, 0, $tags);
    }

    /**
     * Test for provider::get_metadata().
     */
    public function test_get_metadata(): void {
        $collection = new collection('mod_callforpaper');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(7, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('callforpaper_records', $table->get_name());

        $table = next($itemcollection);
        $this->assertEquals('callforpaper_content', $table->get_name());
    }

    /**
     * Test for provider::get_contexts_for_userid().
     */
    public function test_get_contexts_for_userid(): void {
        $cm = get_coursemodule_from_instance('callforpaper', $this->callforpapermodule->id);

        $contextlist = provider::get_contexts_for_userid($this->student->id);
        $this->assertCount(1, $contextlist);
        $contextforuser = $contextlist->current();
        $cmcontext = \context_module::instance($cm->id);
        $this->assertEquals($cmcontext->id, $contextforuser->id);
    }

    /**
     * Test for provider::get_users_in_context().
     */
    public function test_get_users_in_context(): void {
        $component = 'mod_callforpaper';
        $cm = get_coursemodule_from_instance('callforpaper', $this->callforpapermodule->id);
        $cmcontext = \context_module::instance($cm->id);

        $userlist = new \core_privacy\local\request\userlist($cmcontext, $component);
        provider::get_users_in_context($userlist);

        $this->assertCount(3, $userlist);

        $expected = [$this->student->id, $this->student2->id, $this->student3->id];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Get test privacy writer
     *
     * @param context $context
     * @return \core_privacy\tests\request\content_writer
     */
    protected function get_writer($context) {
        return \core_privacy\local\request\writer::with_context($context);
    }

    /**
     * Test for provider::export_user_data().
     */
    public function test_export_for_context(): void {
        global $DB;
        $cm = get_coursemodule_from_instance('callforpaper', $this->callforpapermodule->id);
        $cmcontext = \context_module::instance($cm->id);
        $records = $DB->get_records_select('callforpaper_records', 'userid = :userid ORDER BY id', ['userid' => $this->student->id]);
        $record = reset($records);
        $contents = $DB->get_records('callforpaper_content', ['recordid' => $record->id]);

        // Export all of the data for the context.
        $this->export_context_data_for_user($this->student->id, $cmcontext, 'mod_callforpaper');
        $writer = $this->get_writer($cmcontext);
        $callforpaper = $writer->get_data([$record->id]);
        $this->assertNotEmpty($callforpaper);
        foreach ($contents as $content) {
            $callforpaper = $writer->get_data([$record->id, $content->id]);
            $this->assertNotEmpty($callforpaper);
            $hasfile = in_array($callforpaper->field['type'], ['file', 'picture']);
            $this->assertEquals($hasfile, !empty($writer->get_files([$record->id, $content->id])));
        }
        $tags = $writer->get_related_data([$record->id], 'tags');
        $this->assertNotEmpty($tags);
    }

    /**
     * Test for provider::delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $cm = get_coursemodule_from_instance('callforpaper', $this->callforpapermodule->id);
        $cmcontext = \context_module::instance($cm->id);

        provider::delete_data_for_all_users_in_context($cmcontext);

        $appctxt = new \core_privacy\local\request\approved_contextlist($this->student, 'mod_callforpaper', [$cmcontext->id]);
        provider::export_user_data($appctxt);
        $this->assertFalse($this->get_writer($cmcontext)->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_user().
     */
    public function test_delete_data_for_user(): void {
        $cm = get_coursemodule_from_instance('callforpaper', $this->callforpapermodule->id);
        $cmcontext = \context_module::instance($cm->id);

        $appctxt = new \core_privacy\local\request\approved_contextlist($this->student, 'mod_callforpaper', [$cmcontext->id]);
        provider::delete_data_for_user($appctxt);

        provider::export_user_data($appctxt);
        $this->assertFalse($this->get_writer($cmcontext)->has_any_data());
    }

    /**
     * Test for provider::delete_data_for_users().
     */
    public function test_delete_data_for_users(): void {
        $cm = get_coursemodule_from_instance('callforpaper', $this->callforpapermodule->id);
        $cmcontext = \context_module::instance($cm->id);
        $userstodelete = [$this->student->id, $this->student2->id];

        // Ensure student, student 2 and student 3 have data before being deleted.
        $appctxt = new \core_privacy\local\request\approved_contextlist($this->student, 'mod_callforpaper', [$cmcontext->id]);
        provider::export_user_data($appctxt);
        $this->assertTrue($this->get_writer($cmcontext)->has_any_data());

        $appctxt = new \core_privacy\local\request\approved_contextlist($this->student2, 'mod_callforpaper', [$cmcontext->id]);
        provider::export_user_data($appctxt);
        $this->assertTrue($this->get_writer($cmcontext)->has_any_data());

        // Delete data for student 1 and 2.
        $approvedlist = new approved_userlist($cmcontext, 'mod_callforpaper', $userstodelete);
        provider::delete_data_for_users($approvedlist);

        // Reset the writer so it doesn't contain the data from before deletion.
        \core_privacy\local\request\writer::reset();

        // Ensure data is now deleted for student and student 2.
        $appctxt = new \core_privacy\local\request\approved_contextlist($this->student, 'mod_callforpaper', [$cmcontext->id]);
        provider::export_user_data($appctxt);

        $this->assertFalse($this->get_writer($cmcontext)->has_any_data());

        $appctxt = new \core_privacy\local\request\approved_contextlist($this->student2, 'mod_callforpaper', [$cmcontext->id]);
        provider::export_user_data($appctxt);

        $this->assertFalse($this->get_writer($cmcontext)->has_any_data());

        // Ensure data still intact for student 3.
        $appctxt = new \core_privacy\local\request\approved_contextlist($this->student3, 'mod_callforpaper', [$cmcontext->id]);
        provider::export_user_data($appctxt);

        $this->assertTrue($this->get_writer($cmcontext)->has_any_data());
    }
}
