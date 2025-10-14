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
 * Restore type tests.
 *
 * @package    mod_callforpaper
 * @copyright  2024 Laurent David <laurent.david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class restore_type_test extends \advanced_testcase {

    /**
     * callforpaper provider for test_duplicating_callforpaper_remove_unwanted_types.
     *
     * @return array[]
     */
    public static function restore_format_test_provider(): array {
        return [
            'text' => [
                'type' => 'text',
                'expected' => 'text',
            ],
            'picture' => [
                'type' => 'picture',
                'expected' => 'picture',
            ],
            'wrong type' => [
                'type' => '../wrongtype123',
                'expected' => 'wrongtype',
            ],
        ];
    }

    /**
     * Test that duplicating a callforpaper removes unwanted / invalid format.
     *
     * @param string $type The type of the field.
     * @param string $expected The expected type of the field after duplication.
     *
     * @covers       \restore_callforpaper_activity_structure_step
     * @dataProvider restore_format_test_provider
     */
    public function test_duplicating_callforpaper_remove_unwanted_types(string $type, string $expected): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Make a test course.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', ['course' => $course->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_callforpaper')->create_field(
            (object) ['name' => 'field', 'type' => $type],
            $callforpaper
        );
        // Duplicate the callforpaper module with the type.
        $newdata = duplicate_module($course, get_fast_modinfo($course)->get_cm($callforpaper->cmid));
        // Verify the settings of the duplicated activity.
        $fields = $DB->get_records('callforpaper_fields', ['callforpaperid' => $newdata->instance], 'id');
        $newfield = reset($fields);
        $this->assertEquals($expected, $newfield->type);
    }

}
