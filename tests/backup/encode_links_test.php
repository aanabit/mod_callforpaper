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

namespace mod_callforpaper\backup;

/**
 * Tests for Call for paper
 *
 * @package    mod_callforpaper
 * @category   test
 * @copyright  2025 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class encode_links_test extends \advanced_testcase {
    /**
     * Test that links are encoded correctly.
     *
     * @return void
     *
     * @covers       \backup_callforpaper_activity_task::encode_content_links
     * @covers       \restore_callforpaper_activity_task::define_decode_rules
     */
    public function test_encode_links(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Make a test course.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $newcourse = $generator->create_course();
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', ['course' => $course->id]);
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $field = $callforpapergenerator->create_field(
            (object) ['name' => 'field', 'type' => 'text'],
            $callforpaper
        );

        $entry = [$field->field->id => 'test'];
        $callforpapergenerator->create_entry($callforpaper, $entry);

        $callforpaper->intro = $CFG->wwwroot . '/mod/callforpaper/view.php?id=' . $callforpaper->cmid . '|';
        $callforpaper->intro .= urlencode($CFG->wwwroot . '/mod/callforpaper/view.php?id='. $callforpaper->cmid) . '|';
        $callforpaper->intro .= $CFG->wwwroot . '/mod/callforpaper/view.php?d=' . $callforpaper->id . '|';
        $callforpaper->intro .= urlencode($CFG->wwwroot . '/mod/callforpaper/view.php?d='. $callforpaper->id) . '|';
        $callforpaper->intro .= $CFG->wwwroot . '/mod/callforpaper/index.php?id=' . $callforpaper->course . '|';
        $callforpaper->intro .= urlencode($CFG->wwwroot . '/mod/callforpaper/index.php?id=' . $callforpaper->course) . '|';
        $callforpaper->intro .= $CFG->wwwroot . '/mod/callforpaper/edit.php?id=' . $callforpaper->cmid . '|';
        $callforpaper->intro .= urlencode($CFG->wwwroot . '/mod/callforpaper/edit.php?id='. $callforpaper->cmid) . '|';
        $callforpaper->intro .= $CFG->wwwroot . '/mod/callforpaper/edit.php?d=' . $callforpaper->id . '|';
        $callforpaper->intro .= urlencode($CFG->wwwroot . '/mod/callforpaper/edit.php?d=' . $callforpaper->id) . '|';

        $DB->update_record('callforpaper', $callforpaper);

        // Duplicate the callforpaper module with the type.
        $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($callforpaper->cmid));

        $newdata = $DB->get_record('callforpaper', ['id' => $newcm->instance]);

        $expected = $CFG->wwwroot . '/mod/callforpaper/view.php?id=' . $newcm->id . '|';
        $expected .= urlencode($CFG->wwwroot . '/mod/callforpaper/view.php?id=' . $newcm->id) . '|';
        $expected .= $CFG->wwwroot . '/mod/callforpaper/view.php?d=' . $newdata->id . '|';
        $expected .= urlencode($CFG->wwwroot . '/mod/callforpaper/view.php?d=' . $newdata->id) . '|';
        $expected .= $CFG->wwwroot . '/mod/callforpaper/index.php?id=' . $newcm->course . '|';
        $expected .= urlencode($CFG->wwwroot . '/mod/callforpaper/index.php?id=' . $newcm->course) . '|';
        $expected .= $CFG->wwwroot . '/mod/callforpaper/edit.php?id=' . $newcm->id . '|';
        $expected .= urlencode($CFG->wwwroot . '/mod/callforpaper/edit.php?id='. $newcm->id) . '|';
        $expected .= $CFG->wwwroot . '/mod/callforpaper/edit.php?d=' . $newdata->id . '|';
        $expected .= urlencode($CFG->wwwroot . '/mod/callforpaper/edit.php?d=' . $newdata->id) . '|';

        $this->assertEquals($expected, $newdata->intro);
    }
}
