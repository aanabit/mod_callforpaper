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

namespace mod_callforpaper;

use coding_exception;
use dml_exception;
use mod_callforpaper\local\importer\csv_entries_importer;
use moodle_exception;
use zip_archive;

/**
 * Unit tests for import.php.
 *
 * @package    mod_callforpaper
 * @category   test
 * @covers     \mod_callforpaper\local\importer\entries_importer
 * @covers     \mod_callforpaper\local\importer\csv_entries_importer
 * @copyright  2019 Tobias Reischmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entries_import_test extends \advanced_testcase {

    /**
     * Set up function.
     */
    protected function setUp(): void {
        parent::setUp();

        global $CFG;
        require_once($CFG->dirroot . '/mod/callforpaper/lib.php');
        require_once($CFG->dirroot . '/lib/datalib.php');
        require_once($CFG->dirroot . '/lib/csvlib.class.php');
        require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
        require_once($CFG->dirroot . '/mod/callforpaper/tests/generator/lib.php');
    }

    /**
     * Get the test data.
     * In this instance we are setting up callforpaper records to be used in the unit tests.
     *
     * @return array
     */
    protected function get_test_data(): array {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', array('username' => 'student'));

        $callforpaper = $generator->create_instance(array('course' => $course->id));
        $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id);

        // Add fields.
        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'ID'; // Identifier of the records for testing.
        $fieldrecord->type = 'number';
        $generator->create_field($fieldrecord, $callforpaper);

        $fieldrecord->name = 'Param2';
        $fieldrecord->type = 'text';
        $generator->create_field($fieldrecord, $callforpaper);

        $fieldrecord->name = 'filefield';
        $fieldrecord->type = 'file';
        $generator->create_field($fieldrecord, $callforpaper);

        $fieldrecord->name = 'picturefield';
        $fieldrecord->type = 'picture';
        $generator->create_field($fieldrecord, $callforpaper);

        return [
            'teacher' => $teacher,
            'student' => $student,
            'callforpaper' => $callforpaper,
            'cm' => $cm,
        ];
    }

    /**
     * Test uploading entries for a callforpaper instance without userdata.
     *
     * @throws dml_exception
     */
    public function test_import(): void {
        [
            'callforpaper' => $callforpaper,
            'cm' => $cm,
            'teacher' => $teacher,
        ] = $this->get_test_data();

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import.csv'),
            'test_callforpaper_import.csv');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        // No userdata is present in the file: Fallback is to assign the uploading user as author.
        $expecteduserids = array();
        $expecteduserids[1] = $teacher->id;
        $expecteduserids[2] = $teacher->id;

        $records = $this->get_callforpaper_records($callforpaper->id);
        $this->assertCount(2, $records);
        foreach ($records as $record) {
            $identifier = $record->items['ID']->content;
            $this->assertEquals($expecteduserids[$identifier], $record->userid);
        }
    }

    /**
     * Test uploading entries for a callforpaper instance with userdata.
     *
     * At least one entry has an identifiable user, which is assigned as author.
     *
     * @throws dml_exception
     */
    public function test_import_with_userdata(): void {
        [
            'callforpaper' => $callforpaper,
            'cm' => $cm,
            'teacher' => $teacher,
            'student' => $student,
        ] = $this->get_test_data();

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_userdata.csv'),
            'test_callforpaper_import_with_userdata.csv');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        $expecteduserids = array();
        $expecteduserids[1] = $student->id; // User student exists and is assigned as author.
        $expecteduserids[2] = $teacher->id; // User student2 does not exist. Fallback is the uploading user.

        $records = $this->get_callforpaper_records($callforpaper->id);
        $this->assertCount(2, $records);
        foreach ($records as $record) {
            $identifier = $record->items['ID']->content;
            $this->assertEquals($expecteduserids[$identifier], $record->userid);
        }
    }

    /**
     * Test uploading entries for a callforpaper instance with userdata and a defined field 'Username'.
     *
     * This should test the corner case, in which a user has defined a callforpaper fields, which has the same name
     * as the current lang string for username. In that case, the first Username entry is used for the field.
     * The second one is used to identify the author.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_import_with_field_username(): void {
        [
            'callforpaper' => $callforpaper,
            'cm' => $cm,
            'teacher' => $teacher,
            'student' => $student,
        ] = $this->get_test_data();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');

        // Add username field.
        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'Username';
        $fieldrecord->type = 'text';
        $generator->create_field($fieldrecord, $callforpaper);

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_field_username.csv'),
            'test_callforpaper_import_with_field_username.csv');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        $expecteduserids = array();
        $expecteduserids[1] = $student->id; // User student exists and is assigned as author.
        $expecteduserids[2] = $teacher->id; // User student2 does not exist. Fallback is the uploading user.
        $expecteduserids[3] = $student->id; // User student exists and is assigned as author.

        $expectedcontent = array();
        $expectedcontent[1] = array(
            'Username' => 'otherusername1',
            'Param2' => 'My first entry',
        );
        $expectedcontent[2] = array(
            'Username' => 'otherusername2',
            'Param2' => 'My second entry',
        );
        $expectedcontent[3] = array(
            'Username' => 'otherusername3',
            'Param2' => 'My third entry',
        );

        $records = $this->get_callforpaper_records($callforpaper->id);
        $this->assertCount(3, $records);
        foreach ($records as $record) {
            $identifier = $record->items['ID']->content;
            $this->assertEquals($expecteduserids[$identifier], $record->userid);

            foreach ($expectedcontent[$identifier] as $field => $value) {
                $this->assertEquals($value, $record->items[$field]->content,
                    "The value of field \"$field\" for the record at position \"$identifier\" " .
                    "which is \"{$record->items[$field]->content}\" does not match the expected value \"$value\".");
            }
        }
    }

    /**
     * Test uploading entries for a callforpaper instance with a field 'Username' but only one occurrence in the csv file.
     *
     * This should test the corner case, in which a user has defined a callforpaper fields, which has the same name
     * as the current lang string for username. In that case, the only Username entry is used for the field.
     * The author should not be set.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_import_with_field_username_without_userdata(): void {
        [
            'callforpaper' => $callforpaper,
            'cm' => $cm,
            'teacher' => $teacher,
            'student' => $student,
        ] = $this->get_test_data();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');

        // Add username field.
        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'Username';
        $fieldrecord->type = 'text';
        $generator->create_field($fieldrecord, $callforpaper);

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_userdata.csv'),
            'test_callforpaper_import_with_userdata.csv');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        // No userdata is present in the file: Fallback is to assign the uploading user as author.
        $expecteduserids = array();
        $expecteduserids[1] = $teacher->id;
        $expecteduserids[2] = $teacher->id;

        $expectedcontent = array();
        $expectedcontent[1] = array(
            'Username' => 'student',
            'Param2' => 'My first entry',
        );
        $expectedcontent[2] = array(
            'Username' => 'student2',
            'Param2' => 'My second entry',
        );

        $records = $this->get_callforpaper_records($callforpaper->id);
        $this->assertCount(2, $records);
        foreach ($records as $record) {
            $identifier = $record->items['ID']->content;
            $this->assertEquals($expecteduserids[$identifier], $record->userid);

            foreach ($expectedcontent[$identifier] as $field => $value) {
                $this->assertEquals($value, $record->items[$field]->content,
                    "The value of field \"$field\" for the record at position \"$identifier\" " .
                    "which is \"{$record->items[$field]->content}\" does not match the expected value \"$value\".");
            }
        }
    }

    /**
     * Call for paper provider for {@see test_import_without_approved}
     *
     * @return array[]
     */
    public static function import_without_approved_provider(): array {
        return [
            'Teacher can approve entries' => ['teacher', [1, 1]],
            'Student cannot approve entries' => ['student', [0, 0]],
        ];
    }

    /**
     * Test importing file without approved status column
     *
     * @param string $user
     * @param int[] $expected
     *
     * @dataProvider import_without_approved_provider
     */
    public function test_import_without_approved(string $user, array $expected): void {
        $testdata = $this->get_test_data();
        ['callforpaper' => $callforpaper, 'cm' => $cm] = $testdata;

        $this->setUser($testdata[$user]);

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import.csv'), 'test_callforpaper_import.csv');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        $records = $this->get_callforpaper_records($callforpaper->id);
        $this->assertEquals($expected, array_column($records, 'approved'));
    }

    /**
     * Call for paper provider for {@see test_import_with_approved}
     *
     * @return array[]
     */
    public static function import_with_approved_provider(): array {
        return [
            'Teacher can approve entries' => ['teacher', [1, 0]],
            'Student cannot approve entries' => ['student', [0, 0]],
        ];
    }

    /**
     * Test importing file with approved status column
     *
     * @param string $user
     * @param int[] $expected
     *
     * @dataProvider import_with_approved_provider
     */
    public function test_import_with_approved(string $user, array $expected): void {
        $testdata = $this->get_test_data();
        ['callforpaper' => $callforpaper, 'cm' => $cm] = $testdata;

        $this->setUser($testdata[$user]);

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_approved.csv'),
            'test_callforpaper_import_with_approved.csv');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        $records = $this->get_callforpaper_records($callforpaper->id);
        $this->assertEquals($expected, array_column($records, 'approved'));
    }

    /**
     * Tests the import including files from a zip archive.
     */
    public function test_import_with_files(): void {
        [
            'callforpaper' => $callforpaper,
            'cm' => $cm,
        ] = $this->get_test_data();

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_files.zip'),
            'test_callforpaper_import_with_files.zip');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        $records = $this->get_callforpaper_records($callforpaper->id);
        $ziparchive = new zip_archive();
        $ziparchive->open(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_files.zip'));

        $importedcontent = array_values($records)[0]->items;
        $this->assertEquals(17, $importedcontent['ID']->content);
        $this->assertEquals('samplefile.png', $importedcontent['filefield']->content);
        $this->assertEquals('samplepicture.png', $importedcontent['picturefield']->content);

        // We now check if content of imported file from zip content is identical to the content of the file
        // stored in the mod_callforpaper record in the field 'filefield'.
        $fileindex = array_values(array_map(fn($file) => $file->index,
            array_filter($ziparchive->list_files(), fn($file) => $file->pathname === 'files/samplefile.png')))[0];
        $filestream = $ziparchive->get_stream($fileindex);
        $filefield = callforpaper_get_field_from_name('filefield', $callforpaper);
        $filefieldfilecontent = fread($filestream, $ziparchive->get_info($fileindex)->size);
        $this->assertEquals($filefield->get_file(array_keys($records)[0])->get_content(),
            $filefieldfilecontent);
        fclose($filestream);

        // We now check if content of imported picture from zip content is identical to the content of the picture file
        // stored in the mod_callforpaper record in the field 'picturefield'.
        $fileindex = array_values(array_map(fn($file) => $file->index,
            array_filter($ziparchive->list_files(), fn($file) => $file->pathname === 'files/samplepicture.png')))[0];
        $filestream = $ziparchive->get_stream($fileindex);
        $filefield = callforpaper_get_field_from_name('picturefield', $callforpaper);
        $filefieldfilecontent = fread($filestream, $ziparchive->get_info($fileindex)->size);
        $this->assertEquals($filefield->get_file(array_keys($records)[0])->get_content(),
            $filefieldfilecontent);
        fclose($filestream);
        $this->assertCount(1, $importer->get_added_records_messages());
        $ziparchive->close();
    }

    /**
     * Tests the import including files from a zip archive.
     */
    public function test_import_with_files_missing_file(): void {
        [
            'callforpaper' => $callforpaper,
            'cm' => $cm,
        ] = $this->get_test_data();

        $importer = new csv_entries_importer(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_files_missing_file.zip'),
            'test_callforpaper_import_with_files_missing_file.zip');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');

        $records = $this->get_callforpaper_records($callforpaper->id);
        $ziparchive = new zip_archive();
        $ziparchive->open(self::get_fixture_path(__NAMESPACE__, 'test_callforpaper_import_with_files_missing_file.zip'));

        $importedcontent = array_values($records)[0]->items;
        $this->assertEquals(17, $importedcontent['ID']->content);
        $this->assertFalse(isset($importedcontent['filefield']));
        $this->assertEquals('samplepicture.png', $importedcontent['picturefield']->content);
        $this->assertCount(1, $importer->get_added_records_messages());
        $ziparchive->close();
    }

    /**
     * Returns the records of the callforpaper instance.
     *
     * Each records has an item entry, which contains all fields associated with this item.
     * Each fields has the parameters name, type and content.
     *
     * @param int $callforpaperid Id of the callforpaper instance.
     * @return array The records of the callforpaper instance.
     * @throws dml_exception
     */
    private function get_callforpaper_records(int $callforpaperid): array {
        global $DB;

        $records = $DB->get_records('callforpaper_records', ['callforpaperid' => $callforpaperid]);
        foreach ($records as $record) {
            $sql = 'SELECT f.name, f.type, con.content FROM
                {callforpaper_content} con JOIN {callforpaper_fields} f ON con.fieldid = f.id
                WHERE con.recordid = :recordid';
            $items = $DB->get_records_sql($sql, array('recordid' => $record->id));
            $record->items = $items;
        }
        return $records;
    }

    /**
     * Tests if the amount of imported records is counted properly.
     *
     * @dataProvider get_added_record_messages_provider
     * @param string $callforpaperfilecontent the content of the datafile to test as string
     * @param int $expectedcount the expected count of messages depending on the datafile content
     */
    public function test_get_added_record_messages(string $callforpaperfilecontent, int $expectedcount): void {
        [
            'callforpaper' => $callforpaper,
            'cm' => $cm,
        ] = $this->get_test_data();

        // First we need to create the zip file from the provided data.
        $tmpdir = make_request_directory();
        $callforpaperfile = $tmpdir . '/entries_import_test_datafile_tmp_' . time() . '.csv';
        file_put_contents($callforpaperfile, $callforpaperfilecontent);

        $importer = new csv_entries_importer($callforpaperfile, 'testdatafile.csv');
        $importer->import_csv($cm, $callforpaper, 'UTF-8', 'comma');
        $this->assertEquals($expectedcount, count($importer->get_added_records_messages()));
    }

    /**
     * Call for paper provider method for self::test_get_added_record_messages.
     *
     * @return array data for testing
     */
    public static function get_added_record_messages_provider(): array {
        return [
            'only header' => [
                'callforpaperfilecontent' => 'ID,Param2,filefield,picturefield' . PHP_EOL,
                'expectedcount' => 0 // One line is being assumed to be the header.
            ],
            'one record' => [
                'callforpaperfilecontent' => 'ID,Param2,filefield,picturefield' . PHP_EOL
                    . '5,"some short text",testfilename.pdf,testpicture.png',
                'expectedcount' => 1
            ],
            'two records' => [
                'callforpaperfilecontent' => 'ID,Param2,filefield,picturefield' . PHP_EOL
                    . '5,"some short text",testfilename.pdf,testpicture.png' . PHP_EOL
                    . '3,"other text",testfilename2.pdf,testpicture2.png',
                'expectedcount' => 2
            ],
        ];
    }
}
