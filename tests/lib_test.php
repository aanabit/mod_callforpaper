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
 * Unit tests for lib.php
 *
 * @package    mod_callforpaper
 * @category   phpunit
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_callforpaper;

use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/callforpaper/lib.php');

/**
 * Unit tests for lib.php
 *
 * @package    mod_callforpaper
 * @copyright  2013 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {

    /**
     * @var moodle_callforpaper
     */
    protected $DB = null;

    /**
     * Tear Down to reset DB.
     */
    public function tearDown(): void {
        global $DB;

        if (isset($this->DB)) {
            $DB = $this->DB;
            $this->DB = null;
        }
        parent::tearDown();
    }

    /**
     * Confirms that completionentries is working
     * Sets it to 1, confirms that
     * it is not complete. Inserts a record and
     * confirms that it is complete.
     */
    public function test_callforpaper_completion(): void {
        global $DB, $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enablecompletion = 1;
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $record = new \stdClass();
        $record->course = $course->id;
        $record->name = "Mod callforpaper completion test";
        $record->intro = "Some intro of some sort";
        $record->completionentries = "1";
        /* completion=2 means Show activity commplete when condition is met and completionentries means 1 record is
         * required for the activity to be considered complete
         */
        $module = $this->getDataGenerator()->create_module('callforpaper', $record, array('completion' => 2, 'completionentries' => 1));

        $cm = get_coursemodule_from_instance('callforpaper', $module->id, $course->id);
        $completion = new \completion_info($course);
        $completiondata = $completion->get_data($cm, true, 0);
        /* Confirm it is not complete as there are no entries */
        $this->assertNotEquals(1, $completiondata->completionstate);

        $field = callforpaper_get_field_new('text', $module);
        $fielddetail = new \stdClass();
        $fielddetail->d = $module->id;
        $fielddetail->mode = 'add';
        $fielddetail->type = 'text';
        $fielddetail->sesskey = sesskey();
        $fielddetail->name = 'Name';
        $fielddetail->description = 'Some name';

        $field->define_field($fielddetail);
        $field->insert_field();
        $recordid = callforpaper_add_record($module);

        $callforpapercontent = array();
        $callforpapercontent['fieldid'] = $field->field->id;
        $callforpapercontent['recordid'] = $recordid;
        $callforpapercontent['content'] = 'Asterix';
        $contentid = $DB->insert_record('callforpaper_content', $callforpapercontent);

        $cm = get_coursemodule_from_instance('callforpaper', $module->id, $course->id);
        $completion = new \completion_info($course);
        $completiondata = $completion->get_data($cm);
        /* Confirm it is complete because it has 1 entry */
        $this->assertEquals(1, $completiondata->completionstate);
    }

    public function test_callforpaper_delete_record(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a record for deleting.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $record = new \stdClass();
        $record->course = $course->id;
        $record->name = "Mod callforpaper delete test";
        $record->intro = "Some intro of some sort";

        $module = $this->getDataGenerator()->create_module('callforpaper', $record);

        $field = callforpaper_get_field_new('text', $module);

        $fielddetail = new \stdClass();
        $fielddetail->d = $module->id;
        $fielddetail->mode = 'add';
        $fielddetail->type = 'text';
        $fielddetail->sesskey = sesskey();
        $fielddetail->name = 'Name';
        $fielddetail->description = 'Some name';

        $field->define_field($fielddetail);
        $field->insert_field();
        $recordid = callforpaper_add_record($module);

        $callforpapercontent = array();
        $callforpapercontent['fieldid'] = $field->field->id;
        $callforpapercontent['recordid'] = $recordid;
        $callforpapercontent['content'] = 'Asterix';

        $contentid = $DB->insert_record('callforpaper_content', $callforpapercontent);
        $cm = get_coursemodule_from_instance('callforpaper', $module->id, $course->id);

        // Check to make sure that we have a callforpaper record.
        $callforpaper = $DB->get_records('callforpaper', array('id' => $module->id));
        $this->assertEquals(1, count($callforpaper));

        $callforpapercontent = $DB->get_records('callforpaper_content', array('id' => $contentid));
        $this->assertEquals(1, count($callforpapercontent));

        $callforpaperfields = $DB->get_records('callforpaper_fields', array('id' => $field->field->id));
        $this->assertEquals(1, count($callforpaperfields));

        $callforpaperrecords = $DB->get_records('callforpaper_records', array('id' => $recordid));
        $this->assertEquals(1, count($callforpaperrecords));

        // Test to see if a failed delete returns false.
        $result = callforpaper_delete_record(8798, $module, $course->id, $cm->id);
        $this->assertFalse($result);

        // Delete the record.
        $result = callforpaper_delete_record($recordid, $module, $course->id, $cm->id);

        // Check that all of the record is gone.
        $callforpapercontent = $DB->get_records('callforpaper_content', array('id' => $contentid));
        $this->assertEquals(0, count($callforpapercontent));

        $callforpaperrecords = $DB->get_records('callforpaper_records', array('id' => $recordid));
        $this->assertEquals(0, count($callforpaperrecords));

        // Make sure the function returns true on a successful deletion.
        $this->assertTrue($result);
    }

    /**
     * Test comment_created event.
     */
    public function test_callforpaper_comment_created_event(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a record for deleting.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $record = new \stdClass();
        $record->course = $course->id;
        $record->name = "Mod callforpaper delete test";
        $record->intro = "Some intro of some sort";
        $record->comments = 1;

        $module = $this->getDataGenerator()->create_module('callforpaper', $record);
        $field = callforpaper_get_field_new('text', $module);

        $fielddetail = new \stdClass();
        $fielddetail->name = 'Name';
        $fielddetail->description = 'Some name';

        $field->define_field($fielddetail);
        $field->insert_field();
        $recordid = callforpaper_add_record($module);

        $callforpapercontent = array();
        $callforpapercontent['fieldid'] = $field->field->id;
        $callforpapercontent['recordid'] = $recordid;
        $callforpapercontent['content'] = 'Asterix';

        $contentid = $DB->insert_record('callforpaper_content', $callforpapercontent);
        $cm = get_coursemodule_from_instance('callforpaper', $module->id, $course->id);

        $context = \context_module::instance($module->cmid);
        $cmt = new \stdClass();
        $cmt->context = $context;
        $cmt->course = $course;
        $cmt->cm = $cm;
        $cmt->area = 'callforpaper_entry';
        $cmt->itemid = $recordid;
        $cmt->showcount = true;
        $cmt->component = 'mod_callforpaper';
        $comment = new \core_comment\manager($cmt);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $comment->add('New comment');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_callforpaper\event\comment_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/callforpaper/view.php', array('id' => $cm->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test comment_deleted event.
     */
    public function test_callforpaper_comment_deleted_event(): void {
        global $DB;

        $this->resetAfterTest();

        // Create a record for deleting.
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $record = new \stdClass();
        $record->course = $course->id;
        $record->name = "Mod callforpaper delete test";
        $record->intro = "Some intro of some sort";
        $record->comments = 1;

        $module = $this->getDataGenerator()->create_module('callforpaper', $record);
        $field = callforpaper_get_field_new('text', $module);

        $fielddetail = new \stdClass();
        $fielddetail->name = 'Name';
        $fielddetail->description = 'Some name';

        $field->define_field($fielddetail);
        $field->insert_field();
        $recordid = callforpaper_add_record($module);

        $callforpapercontent = array();
        $callforpapercontent['fieldid'] = $field->field->id;
        $callforpapercontent['recordid'] = $recordid;
        $callforpapercontent['content'] = 'Asterix';

        $contentid = $DB->insert_record('callforpaper_content', $callforpapercontent);
        $cm = get_coursemodule_from_instance('callforpaper', $module->id, $course->id);

        $context = \context_module::instance($module->cmid);
        $cmt = new \stdClass();
        $cmt->context = $context;
        $cmt->course = $course;
        $cmt->cm = $cm;
        $cmt->area = 'callforpaper_entry';
        $cmt->itemid = $recordid;
        $cmt->showcount = true;
        $cmt->component = 'mod_callforpaper';
        $comment = new \core_comment\manager($cmt);
        $newcomment = $comment->add('New comment 1');

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $comment->delete($newcomment->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_callforpaper\event\comment_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/callforpaper/view.php', array('id' => $module->cmid));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Checks that callforpaper_user_can_manage_entry will return true if the user
     * has the mod/callforpaper:manageentries capability.
     */
    public function test_callforpaper_user_can_manage_entry_return_true_with_capability(): void {

        $this->resetAfterTest();
        $testdata = $this->create_user_test_data();

        $user = $testdata['user'];
        $course = $testdata['course'];
        $roleid = $testdata['roleid'];
        $context = $testdata['context'];
        $record = $testdata['record'];
        $callforpaper = new \stdClass();

        $this->setUser($user);

        assign_capability('mod/callforpaper:manageentries', CAP_ALLOW, $roleid, $context);

        $this->assertTrue(callforpaper_user_can_manage_entry($record, $callforpaper, $context),
            'callforpaper_user_can_manage_entry() returns true if the user has mod/callforpaper:manageentries capability');
    }

    /**
     * Checks that callforpaper_user_can_manage_entry will return false if the callforpaper
     * is set to readonly.
     */
    public function test_callforpaper_user_can_manage_entry_return_false_readonly(): void {

        $this->resetAfterTest();
        $testdata = $this->create_user_test_data();

        $user = $testdata['user'];
        $course = $testdata['course'];
        $roleid = $testdata['roleid'];
        $context = $testdata['context'];
        $record = $testdata['record'];

        $this->setUser($user);

        // Need to make sure they don't have this capability in order to fall back to
        // the other checks.
        assign_capability('mod/callforpaper:manageentries', CAP_PROHIBIT, $roleid, $context);

        // Causes readonly mode to be enabled.
        $callforpaper = new \stdClass();
        $now = time();
        // Add a small margin around the periods to prevent errors with slow tests.
        $callforpaper->timeviewfrom = $now - 1;
        $callforpaper->timeviewto = $now + 5;

        $this->assertFalse(callforpaper_user_can_manage_entry($record, $callforpaper, $context),
            'callforpaper_user_can_manage_entry() returns false if the callforpaper is read only');
    }

    /**
     * Checks that callforpaper_user_can_manage_entry will return false if the record
     * can't be found in the callforpaper.
     */
    public function test_callforpaper_user_can_manage_entry_return_false_no_record(): void {

        $this->resetAfterTest();
        $testdata = $this->create_user_test_data();

        $user = $testdata['user'];
        $course = $testdata['course'];
        $roleid = $testdata['roleid'];
        $context = $testdata['context'];
        $record = $testdata['record'];
        $callforpaper = new \stdClass();
        // Causes readonly mode to be disabled.
        $now = time();
        $callforpaper->timeviewfrom = $now + 100;
        $callforpaper->timeviewto = $now - 100;

        $this->setUser($user);

        // Need to make sure they don't have this capability in order to fall back to
        // the other checks.
        assign_capability('mod/callforpaper:manageentries', CAP_PROHIBIT, $roleid, $context);

        // Pass record id instead of object to force DB lookup.
        $this->assertFalse(callforpaper_user_can_manage_entry(1, $callforpaper, $context),
            'callforpaper_user_can_manage_entry() returns false if the record cannot be found');
    }

    /**
     * Checks that callforpaper_user_can_manage_entry will return false if the record
     * isn't owned by the user.
     */
    public function test_callforpaper_user_can_manage_entry_return_false_not_owned_record(): void {

        $this->resetAfterTest();
        $testdata = $this->create_user_test_data();

        $user = $testdata['user'];
        $course = $testdata['course'];
        $roleid = $testdata['roleid'];
        $context = $testdata['context'];
        $record = $testdata['record'];
        $callforpaper = new \stdClass();
        // Causes readonly mode to be disabled.
        $now = time();
        $callforpaper->timeviewfrom = $now + 100;
        $callforpaper->timeviewto = $now - 100;
        // Make sure the record isn't owned by this user.
        $record->userid = $user->id + 1;

        $this->setUser($user);

        // Need to make sure they don't have this capability in order to fall back to
        // the other checks.
        assign_capability('mod/callforpaper:manageentries', CAP_PROHIBIT, $roleid, $context);

        $this->assertFalse(callforpaper_user_can_manage_entry($record, $callforpaper, $context),
            'callforpaper_user_can_manage_entry() returns false if the record isnt owned by the user');
    }

    /**
     * Checks that callforpaper_user_can_manage_entry will return true if the callforpaper
     * doesn't require approval.
     */
    public function test_callforpaper_user_can_manage_entry_return_true_callforpaper_no_approval(): void {

        $this->resetAfterTest();
        $testdata = $this->create_user_test_data();

        $user = $testdata['user'];
        $course = $testdata['course'];
        $roleid = $testdata['roleid'];
        $context = $testdata['context'];
        $record = $testdata['record'];
        $callforpaper = new \stdClass();
        // Causes readonly mode to be disabled.
        $now = time();
        $callforpaper->timeviewfrom = $now + 100;
        $callforpaper->timeviewto = $now - 100;
        // The record doesn't need approval.
        $callforpaper->approval = false;
        // Make sure the record is owned by this user.
        $record->userid = $user->id;

        $this->setUser($user);

        // Need to make sure they don't have this capability in order to fall back to
        // the other checks.
        assign_capability('mod/callforpaper:manageentries', CAP_PROHIBIT, $roleid, $context);

        $this->assertTrue(callforpaper_user_can_manage_entry($record, $callforpaper, $context),
            'callforpaper_user_can_manage_entry() returns true if the record doesnt require approval');
    }

    /**
     * Checks that callforpaper_user_can_manage_entry will return true if the record
     * isn't yet approved.
     */
    public function test_callforpaper_user_can_manage_entry_return_true_record_unapproved(): void {

        $this->resetAfterTest();
        $testdata = $this->create_user_test_data();

        $user = $testdata['user'];
        $course = $testdata['course'];
        $roleid = $testdata['roleid'];
        $context = $testdata['context'];
        $record = $testdata['record'];
        $callforpaper = new \stdClass();
        // Causes readonly mode to be disabled.
        $now = time();
        $callforpaper->timeviewfrom = $now + 100;
        $callforpaper->timeviewto = $now - 100;
        // The record needs approval.
        $callforpaper->approval = true;
        // Make sure the record is owned by this user.
        $record->userid = $user->id;
        // The record hasn't yet been approved.
        $record->approved = false;

        $this->setUser($user);

        // Need to make sure they don't have this capability in order to fall back to
        // the other checks.
        assign_capability('mod/callforpaper:manageentries', CAP_PROHIBIT, $roleid, $context);

        $this->assertTrue(callforpaper_user_can_manage_entry($record, $callforpaper, $context),
            'callforpaper_user_can_manage_entry() returns true if the record is not yet approved');
    }

    /**
     * Checks that callforpaper_user_can_manage_entry will return the 'manageapproved'
     * value if the record has already been approved.
     */
    public function test_callforpaper_user_can_manage_entry_return_manageapproved(): void {

        $this->resetAfterTest();
        $testdata = $this->create_user_test_data();

        $user = $testdata['user'];
        $course = $testdata['course'];
        $roleid = $testdata['roleid'];
        $context = $testdata['context'];
        $record = $testdata['record'];
        $callforpaper = new \stdClass();
        // Causes readonly mode to be disabled.
        $now = time();
        $callforpaper->timeviewfrom = $now + 100;
        $callforpaper->timeviewto = $now - 100;
        // The record needs approval.
        $callforpaper->approval = true;
        // Can the user managed approved records?
        $callforpaper->manageapproved = false;
        // Make sure the record is owned by this user.
        $record->userid = $user->id;
        // The record has been approved.
        $record->approved = true;

        $this->setUser($user);

        // Need to make sure they don't have this capability in order to fall back to
        // the other checks.
        assign_capability('mod/callforpaper:manageentries', CAP_PROHIBIT, $roleid, $context);

        $canmanageentry = callforpaper_user_can_manage_entry($record, $callforpaper, $context);

        // Make sure the result of the check is what ever the manageapproved setting
        // is set to.
        $this->assertEquals($callforpaper->manageapproved, $canmanageentry,
            'callforpaper_user_can_manage_entry() returns the manageapproved setting on approved records');
    }

    /**
     * Helper method to create a set of test data for callforpaper_user_can_manage tests
     *
     * @return array contains user, course, roleid, module, context and record
     */
    private function create_user_test_data() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $roleid = $this->getDataGenerator()->create_role();
        $record = new \stdClass();
        $record->name = "test name";
        $record->intro = "test intro";
        $record->comments = 1;
        $record->course = $course->id;
        $record->userid = $user->id;

        $module = $this->getDataGenerator()->create_module('callforpaper', $record);
        $cm = get_coursemodule_from_instance('callforpaper', $module->id, $course->id);
        $context = \context_module::instance($module->cmid);

        $this->getDataGenerator()->role_assign($roleid, $user->id, $context->id);

        return array(
            'user' => $user,
            'course' => $course,
            'roleid' => $roleid,
            'module' => $module,
            'context' => $context,
            'record' => $record
        );
    }

    /**
     * Tests for mod_callforpaper_rating_can_see_item_ratings().
     *
     * @throws coding_exception
     * @throws rating_exception
     */
    public function test_mod_callforpaper_rating_can_see_item_ratings(): void {
        global $DB;

        $this->resetAfterTest();

        // Setup test data.
        $course = new \stdClass();
        $course->groupmode = SEPARATEGROUPS;
        $course->groupmodeforce = true;
        $course = $this->getDataGenerator()->create_course($course);
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id);
        $context = \context_module::instance($cm->id);

        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        // Groups and stuff.
        $role = $DB->get_record('role', array('shortname' => 'teacher'), '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user4->id, $course->id, $role->id);

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group1, $user1);
        groups_add_member($group1, $user2);
        groups_add_member($group2, $user3);
        groups_add_member($group2, $user4);

        // Add callforpaper.
        $field = callforpaper_get_field_new('text', $callforpaper);

        $fielddetail = new \stdClass();
        $fielddetail->name = 'Name';
        $fielddetail->description = 'Some name';

        $field->define_field($fielddetail);
        $field->insert_field();

        // Add a record with a group id of zero (all participants).
        $recordid1 = callforpaper_add_record($callforpaper, 0);

        $callforpapercontent = array();
        $callforpapercontent['fieldid'] = $field->field->id;
        $callforpapercontent['recordid'] = $recordid1;
        $callforpapercontent['content'] = 'Obelix';
        $DB->insert_record('callforpaper_content', $callforpapercontent);

        $recordid = callforpaper_add_record($callforpaper, $group1->id);

        $callforpapercontent = array();
        $callforpapercontent['fieldid'] = $field->field->id;
        $callforpapercontent['recordid'] = $recordid;
        $callforpapercontent['content'] = 'Asterix';
        $DB->insert_record('callforpaper_content', $callforpapercontent);

        // Now try to access it as various users.
        unassign_capability('moodle/site:accessallgroups', $role->id);
        // Eveyone should have access to the record with the group id of zero.
        $params1 = array('contextid' => 2,
                        'component' => 'mod_callforpaper',
                        'ratingarea' => 'entry',
                        'itemid' => $recordid1,
                        'scaleid' => 2);

        $params = array('contextid' => 2,
                        'component' => 'mod_callforpaper',
                        'ratingarea' => 'entry',
                        'itemid' => $recordid,
                        'scaleid' => 2);

        $this->setUser($user1);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user2);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user3);
        $this->assertFalse(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user4);
        $this->assertFalse(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));

        // Now try with accessallgroups cap and make sure everything is visible.
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role->id, $context->id);
        $this->setUser($user1);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user2);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user3);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user4);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));

        // Change group mode and verify visibility.
        $course->groupmode = VISIBLEGROUPS;
        $DB->update_record('course', $course);
        unassign_capability('moodle/site:accessallgroups', $role->id);
        $this->setUser($user1);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user2);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user3);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));
        $this->setUser($user4);
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params));
        $this->assertTrue(mod_callforpaper_rating_can_see_item_ratings($params1));

    }

    /**
     * Tests for mod_callforpaper_refresh_events.
     */
    public function test_callforpaper_refresh_events(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $timeopen = time();
        $timeclose = time() + 86400;

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $params['course'] = $course->id;
        $params['timeavailablefrom'] = $timeopen;
        $params['timeavailableto'] = $timeclose;
        $callforpaper = $generator->create_instance($params);

        // Normal case, with existing course.
        $this->assertTrue(callforpaper_refresh_events($course->id));
        $eventparams = array('modulename' => 'callforpaper', 'instance' => $callforpaper->id, 'eventtype' => 'open');
        $openevent = $DB->get_record('event', $eventparams, '*', MUST_EXIST);
        $this->assertEquals($openevent->timestart, $timeopen);

        $eventparams = array('modulename' => 'callforpaper', 'instance' => $callforpaper->id, 'eventtype' => 'close');
        $closeevent = $DB->get_record('event', $eventparams, '*', MUST_EXIST);
        $this->assertEquals($closeevent->timestart, $timeclose);
        // In case the course ID is passed as a numeric string.
        $this->assertTrue(callforpaper_refresh_events('' . $course->id));
        // Course ID not provided.
        $this->assertTrue(callforpaper_refresh_events());
        $eventparams = array('modulename' => 'callforpaper');
        $events = $DB->get_records('event', $eventparams);
        foreach ($events as $event) {
            if ($event->modulename === 'callforpaper' && $event->instance === $callforpaper->id && $event->eventtype === 'open') {
                $this->assertEquals($event->timestart, $timeopen);
            }
            if ($event->modulename === 'callforpaper' && $event->instance === $callforpaper->id && $event->eventtype === 'close') {
                $this->assertEquals($event->timestart, $timeclose);
            }
        }
    }

    /**
     * Call for paper provider for tests of callforpaper_get_config.
     *
     * @return array
     */
    public static function callforpaper_get_config_provider(): array {
        $initialdata = (object) [
            'template_foo' => true,
            'template_bar' => false,
            'template_baz' => null,
        ];

        $callforpaper = (object) [
            'config' => json_encode($initialdata),
        ];

        return [
            'Return full dataset (no key/default)' => [
                [$callforpaper],
                $initialdata,
            ],
            'Return full dataset (no default)' => [
                [$callforpaper, null],
                $initialdata,
            ],
            'Return full dataset' => [
                [$callforpaper, null, null],
                $initialdata,
            ],
            'Return requested key only, value true, no default' => [
                [$callforpaper, 'template_foo'],
                true,
            ],
            'Return requested key only, value false, no default' => [
                [$callforpaper, 'template_bar'],
                false,
            ],
            'Return requested key only, value null, no default' => [
                [$callforpaper, 'template_baz'],
                null,
            ],
            'Return unknown key, value null, no default' => [
                [$callforpaper, 'template_bum'],
                null,
            ],
            'Return requested key only, value true, default null' => [
                [$callforpaper, 'template_foo', null],
                true,
            ],
            'Return requested key only, value false, default null' => [
                [$callforpaper, 'template_bar', null],
                false,
            ],
            'Return requested key only, value null, default null' => [
                [$callforpaper, 'template_baz', null],
                null,
            ],
            'Return unknown key, value null, default null' => [
                [$callforpaper, 'template_bum', null],
                null,
            ],
            'Return requested key only, value true, default 42' => [
                [$callforpaper, 'template_foo', 42],
                true,
            ],
            'Return requested key only, value false, default 42' => [
                [$callforpaper, 'template_bar', 42],
                false,
            ],
            'Return requested key only, value null, default 42' => [
                [$callforpaper, 'template_baz', 42],
                null,
            ],
            'Return unknown key, value null, default 42' => [
                [$callforpaper, 'template_bum', 42],
                42,
            ],
        ];
    }

    /**
     * Tests for callforpaper_get_config.
     *
     * @dataProvider    callforpaper_get_config_provider
     * @param   array   $funcargs       The args to pass to callforpaper_get_config
     * @param   mixed   $expectation    The expected value
     */
    public function test_callforpaper_get_config($funcargs, $expectation): void {
        $this->assertEquals($expectation, call_user_func_array('callforpaper_get_config', $funcargs));
    }

    /**
     * Call for paper provider for tests of callforpaper_set_config.
     *
     * @return array
     */
    public static function callforpaper_set_config_provider(): array {
        $basevalue = (object) ['id' => rand(1, 1000)];
        $config = [
            'template_foo'  => true,
            'template_bar'  => false,
        ];

        $withvalues = clone $basevalue;
        $withvalues->config = json_encode((object) $config);

        return [
            'Empty config, New value' => [
                $basevalue,
                'etc',
                'newvalue',
                true,
                json_encode((object) ['etc' => 'newvalue'])
            ],
            'Has config, New value' => [
                clone $withvalues,
                'etc',
                'newvalue',
                true,
                json_encode((object) array_merge($config, ['etc' => 'newvalue']))
            ],
            'Has config, Update value, string' => [
                clone $withvalues,
                'template_foo',
                'newvalue',
                true,
                json_encode((object) array_merge($config, ['template_foo' => 'newvalue']))
            ],
            'Has config, Update value, true' => [
                clone $withvalues,
                'template_bar',
                true,
                true,
                json_encode((object) array_merge($config, ['template_bar' => true]))
            ],
            'Has config, Update value, false' => [
                clone $withvalues,
                'template_foo',
                false,
                true,
                json_encode((object) array_merge($config, ['template_foo' => false]))
            ],
            'Has config, Update value, null' => [
                clone $withvalues,
                'template_foo',
                null,
                true,
                json_encode((object) array_merge($config, ['template_foo' => null]))
            ],
            'Has config, No update, value true' => [
                clone $withvalues,
                'template_foo',
                true,
                false,
                $withvalues->config,
            ],
        ];
    }

    /**
     * Tests for callforpaper_set_config.
     *
     * @dataProvider    callforpaper_set_config_provider
     * @param   object  $callforpaper       The example row for the entry
     * @param   string  $key            The config key to set
     * @param   mixed   $value          The value of the key
     * @param   bool    $expectupdate   Whether we expected an update
     * @param   mixed   $newconfigvalue The expected value
     */
    public function test_callforpaper_set_config($callforpaper, $key, $value, $expectupdate, $newconfigvalue): void {
        global $DB;

        // Mock the callforpaper.
        // Note: Use the actual test class here rather than the abstract because are testing concrete methods.
        $this->DB = $DB;
        $DB = $this->getMockBuilder(get_class($DB))
            ->onlyMethods(['set_field'])
            ->getMock();

        $DB->expects($this->exactly((int) $expectupdate))
            ->method('set_field')
            ->with(
                'callforpaper',
                'config',
                $newconfigvalue,
                ['id' => $callforpaper->id]
            );

        // Perform the update.
        callforpaper_set_config($callforpaper, $key, $value);

        // Ensure that the value was updated by reference in $callforpaper.
        $config = json_decode($callforpaper->config);
        $this->assertEquals($value, $config->$key);
    }

    public function test_mod_callforpaper_get_tagged_records(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $course1 = $this->getDataGenerator()->create_course();

        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'field-1';
        $fieldrecord->type = 'text';

        $callforpaper1 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course1->id, 'approval' => true));
        $field1 = $callforpapergenerator->create_field($fieldrecord, $callforpaper1);

        $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value11'], 0, ['Cats', 'Dogs']);
        $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value12'], 0, ['Cats', 'mice']);
        $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value13'], 0, ['Cats']);
        $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value14'], 0);

        $tag = \core_tag_tag::get_by_name(0, 'Cats');

        // Admin can see everything.
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);
        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value12', $res->content);
        $this->assertStringContainsString('value13', $res->content);
        $this->assertStringNotContainsString('value14', $res->content);
    }

    public function test_mod_callforpaper_get_tagged_records_approval(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $course2 = $this->getDataGenerator()->create_course();
        $course1 = $this->getDataGenerator()->create_course();

        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'field-1';
        $fieldrecord->type = 'text';

        $callforpaper1 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course1->id));
        $field1 = $callforpapergenerator->create_field($fieldrecord, $callforpaper1);
        $callforpaper2 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course2->id, 'approval' => true));
        $field2 = $callforpapergenerator->create_field($fieldrecord, $callforpaper2);

        $record11 = $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value11'], 0, ['Cats', 'Dogs']);
        $record21 = $callforpapergenerator->create_entry($callforpaper2, [$field2->field->id => 'value21'], 0, ['Cats'], ['approved' => false]);
        $tag = \core_tag_tag::get_by_name(0, 'Cats');

        // Admin can see everything.
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);
        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
        $this->assertEmpty($res->prevpageurl);
        $this->assertEmpty($res->nextpageurl);

        // Create and enrol a user.
        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, $studentrole->id, 'manual');
        $this->setUser($student);

        // User can search callforpaper records inside a course.
        \core_tag_index_builder::reset_caches();
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringNotContainsString('value21', $res->content);

        $recordtoupdate = new \stdClass();
        $recordtoupdate->id = $record21;
        $recordtoupdate->approved = true;
        $DB->update_record('callforpaper_records', $recordtoupdate);

        \core_tag_index_builder::reset_caches();
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
    }

    public function test_mod_callforpaper_get_tagged_records_time(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $course2 = $this->getDataGenerator()->create_course();
        $course1 = $this->getDataGenerator()->create_course();

        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'field-1';
        $fieldrecord->type = 'text';

        $timefrom = time() - YEARSECS;
        $timeto = time() - WEEKSECS;

        $callforpaper1 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course1->id, 'approval' => true));
        $field1 = $callforpapergenerator->create_field($fieldrecord, $callforpaper1);
        $callforpaper2 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course2->id,
                                                                        'timeviewfrom' => $timefrom,
                                                                        'timeviewto'   => $timeto));
        $field2 = $callforpapergenerator->create_field($fieldrecord, $callforpaper2);
        $record11 = $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value11'], 0, ['Cats', 'Dogs']);
        $record21 = $callforpapergenerator->create_entry($callforpaper2, [$field2->field->id => 'value21'], 0, ['Cats']);
        $tag = \core_tag_tag::get_by_name(0, 'Cats');

        // Admin can see everything.
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);
        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
        $this->assertEmpty($res->prevpageurl);
        $this->assertEmpty($res->nextpageurl);

        // Create and enrol a user.
        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, $studentrole->id, 'manual');
        $this->setUser($student);

        // User can search callforpaper records inside a course.
        \core_tag_index_builder::reset_caches();
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringNotContainsString('value21', $res->content);

        $callforpaper2->timeviewto = time() + YEARSECS;
        $DB->update_record('callforpaper', $callforpaper2);

        \core_tag_index_builder::reset_caches();
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
    }

    public function test_mod_callforpaper_get_tagged_records_course_enrolment(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $course2 = $this->getDataGenerator()->create_course();
        $course1 = $this->getDataGenerator()->create_course();

        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'field-1';
        $fieldrecord->type = 'text';

        $callforpaper1 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course1->id, 'approval' => true));
        $field1 = $callforpapergenerator->create_field($fieldrecord, $callforpaper1);
        $callforpaper2 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course2->id));
        $field2 = $callforpapergenerator->create_field($fieldrecord, $callforpaper2);

        $record11 = $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value11'], 0, ['Cats', 'Dogs']);
        $record21 = $callforpapergenerator->create_entry($callforpaper2, [$field2->field->id => 'value21'], 0, ['Cats']);
        $tag = \core_tag_tag::get_by_name(0, 'Cats');

        // Admin can see everything.
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);
        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
        $this->assertEmpty($res->prevpageurl);
        $this->assertEmpty($res->nextpageurl);

        // Create and enrol a user.
        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id, 'manual');
        $this->setUser($student);
        \core_tag_index_builder::reset_caches();

        // User can search callforpaper records inside a course.
        $coursecontext = \context_course::instance($course1->id);
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringNotContainsString('value21', $res->content);

        $this->getDataGenerator()->enrol_user($student->id, $course2->id, $studentrole->id, 'manual');

        \core_tag_index_builder::reset_caches();
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
    }

    public function test_mod_callforpaper_get_tagged_records_course_groups(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $course2 = $this->getDataGenerator()->create_course();
        $course1 = $this->getDataGenerator()->create_course();

        $groupa = $this->getDataGenerator()->create_group(array('courseid' => $course2->id, 'name' => 'groupA'));
        $groupb = $this->getDataGenerator()->create_group(array('courseid' => $course2->id, 'name' => 'groupB'));

        $fieldrecord = new \stdClass();
        $fieldrecord->name = 'field-1';
        $fieldrecord->type = 'text';

        $callforpaper1 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course1->id, 'approval' => true));
        $field1 = $callforpapergenerator->create_field($fieldrecord, $callforpaper1);
        $callforpaper2 = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course2->id));
        $field2 = $callforpapergenerator->create_field($fieldrecord, $callforpaper2);
        set_coursemodule_groupmode($callforpaper2->cmid, SEPARATEGROUPS);

        $record11 = $callforpapergenerator->create_entry($callforpaper1, [$field1->field->id => 'value11'],
                0, ['Cats', 'Dogs']);
        $record21 = $callforpapergenerator->create_entry($callforpaper2, [$field2->field->id => 'value21'],
                $groupa->id, ['Cats']);
        $record22 = $callforpapergenerator->create_entry($callforpaper2, [$field2->field->id => 'value22'],
                $groupb->id, ['Cats']);
        $tag = \core_tag_tag::get_by_name(0, 'Cats');

        // Admin can see everything.
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);
        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
        $this->assertStringContainsString('value22', $res->content);
        $this->assertEmpty($res->prevpageurl);
        $this->assertEmpty($res->nextpageurl);

        // Create and enrol a user.
        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id, 'manual');
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, $studentrole->id, 'manual');
        groups_add_member($groupa, $student);
        $this->setUser($student);
        \core_tag_index_builder::reset_caches();

        // User can search callforpaper records inside a course.
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
        $this->assertStringNotContainsString('value22', $res->content);

        groups_add_member($groupb, $student);
        \core_tag_index_builder::reset_caches();
        $res = mod_callforpaper_get_tagged_records($tag, false, 0, 0, 1, 0);

        $this->assertStringContainsString('value11', $res->content);
        $this->assertStringContainsString('value21', $res->content);
        $this->assertStringContainsString('value22', $res->content);
    }

    /**
     * Test check_updates_since callback.
     */
    public function test_check_updates_since(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        // Create user.
        $student = self::getDataGenerator()->create_user();
        // User enrolment.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course->id, $studentrole->id, 'manual');
        $this->setCurrentTimeStart();
        $record = array(
            'course' => $course->id,
        );
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', $record);
        $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id, $course->id);
        $cm = \cm_info::create($cm);
        $this->setUser($student);

        // Check that upon creation, the updates are only about the new configuration created.
        $onehourago = time() - HOURSECS;
        $updates = callforpaper_check_updates_since($cm, $onehourago);
        foreach ($updates as $el => $val) {
            if ($el == 'configuration') {
                $this->assertTrue($val->updated);
                $this->assertTimeCurrent($val->timeupdated);
            } else {
                $this->assertFalse($val->updated);
            }
        }

        // Add a couple of entries.
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $fieldtypes = array('checkbox', 'date');

        $count = 1;
        // Creating test Fields with default parameter values.
        foreach ($fieldtypes as $fieldtype) {
            // Creating variables dynamically.
            $fieldname = 'field-' . $count;
            $record = new \stdClass();
            $record->name = $fieldname;
            $record->type = $fieldtype;
            $record->required = 1;

            ${$fieldname} = $callforpapergenerator->create_field($record, $callforpaper);
            $count++;
        }

        $fields = $DB->get_records('callforpaper_fields', array('callforpaperid' => $callforpaper->id), 'id');

        $contents = array();
        $contents[] = array('opt1', 'opt2', 'opt3', 'opt4');
        $contents[] = '01-01-2037'; // It should be lower than 2038, to avoid failing on 32-bit windows.
        $count = 0;
        $fieldcontents = array();
        foreach ($fields as $fieldrecord) {
            $fieldcontents[$fieldrecord->id] = $contents[$count++];
        }

        $callforpaperrecor1did = $callforpapergenerator->create_entry($callforpaper, $fieldcontents);
        $callforpaperrecor2did = $callforpapergenerator->create_entry($callforpaper, $fieldcontents);
        $records = $DB->get_records('callforpaper_records', array('callforpaperid' => $callforpaper->id));
        $this->assertCount(2, $records);
        // Check we received the entries updated.
        $updates = callforpaper_check_updates_since($cm, $onehourago);
        $this->assertTrue($updates->entries->updated);
        $this->assertEqualsCanonicalizing([$callforpaperrecor1did, $callforpaperrecor2did], $updates->entries->itemids);
    }

    public function test_callforpaper_core_calendar_provide_event_action_in_hidden_section(): void {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
                'timeavailablefrom' => time() - DAYSECS, 'timeavailableto' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Set sections 0 as hidden.
        set_section_visible($course->id, 0, 0);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    public function test_callforpaper_core_calendar_provide_event_action_for_non_user(): void {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
            'timeavailablefrom' => time() - DAYSECS, 'timeavailableto' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Now, log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory);

        // Confirm the event is not shown at all.
        $this->assertNull($actionevent);
    }

    public function test_callforpaper_core_calendar_provide_event_action_open(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
            'timeavailablefrom' => time() - DAYSECS, 'timeavailableto' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('add', 'callforpaper'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_callforpaper_core_calendar_provide_event_action_open_for_user(): void {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
            'timeavailablefrom' => time() - DAYSECS, 'timeavailableto' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Now log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('add', 'callforpaper'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_callforpaper_core_calendar_provide_event_action_closed(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
            'timeavailableto' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory);

        // No event on the dashboard if module is closed.
        $this->assertNull($actionevent);
    }

    public function test_callforpaper_core_calendar_provide_event_action_closed_for_user(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
            'timeavailableto' => time() - DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Now log out.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory, $student->id);

        // No event on the dashboard if module is closed.
        $this->assertNull($actionevent);
    }

    public function test_callforpaper_core_calendar_provide_event_action_open_in_future(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
            'timeavailablefrom' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('add', 'callforpaper'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_callforpaper_core_calendar_provide_event_action_open_in_future_for_user(): void {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id,
            'timeavailablefrom' => time() + DAYSECS));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Now log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('add', 'callforpaper'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertFalse($actionevent->is_actionable());
    }

    public function test_callforpaper_core_calendar_provide_event_action_no_time_specified(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('add', 'callforpaper'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_callforpaper_core_calendar_provide_event_action_no_time_specified_for_user(): void {
        global $CFG;

        $this->resetAfterTest();

        $this->setAdminUser();

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a student.
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a callforpaper activity.
        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', array('course' => $course->id));

        // Create a calendar event.
        $event = $this->create_action_event($course->id, $callforpaper->id, CALLFORPAPER_EVENT_TYPE_OPEN);

        // Now log out.
        $CFG->forcelogin = true; // We don't want to be logged in as guest, as guest users might still have some capabilities.
        $this->setUser();

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event for the student.
        $actionevent = mod_callforpaper_core_calendar_provide_event_action($event, $factory, $student->id);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('add', 'callforpaper'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid
     * @param int $instanceid The callforpaper id.
     * @param string $eventtype The event type. eg. CALLFORPAPER_EVENT_TYPE_OPEN.
     * @param int|null $timestart The start timestamp for the event
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype, $timestart = null) {
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'callforpaper';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        if ($timestart) {
            $event->timestart = $timestart;
        } else {
            $event->timestart = time();
        }

        return \calendar_event::create($event);
    }

    /**
     * Test the callback responsible for returning the completion rule descriptions.
     * This function should work given either an instance of the module (cm_info), such as when checking the active rules,
     * or if passed a stdClass of similar structure, such as when checking the the default completion settings for a mod type.
     */
    public function test_mod_callforpaper_completion_get_active_rule_descriptions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Two activities, both with automatic completion. One has the 'completionentries' rule, one doesn't.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 2]);
        $callforpaper1 = $this->getDataGenerator()->create_module('callforpaper', [
            'course' => $course->id,
            'completion' => 2,
            'completionentries' => 3
        ]);
        $callforpaper2 = $this->getDataGenerator()->create_module('callforpaper', [
            'course' => $course->id,
            'completion' => 2,
            'completionentries' => 0
        ]);
        $cm1 = \cm_info::create(get_coursemodule_from_instance('callforpaper', $callforpaper1->id));
        $cm2 = \cm_info::create(get_coursemodule_from_instance('callforpaper', $callforpaper2->id));

        // Data for the stdClass input type.
        // This type of input would occur when checking the default completion rules for an activity type, where we don't have
        // any access to cm_info, rather the input is a stdClass containing completion and customdata attributes, just like cm_info.
        $moddefaults = new \stdClass();
        $moddefaults->customdata = ['customcompletionrules' => ['completionentries' => 3]];
        $moddefaults->completion = 2;

        $activeruledescriptions = [get_string('completionentriesdesc', 'callforpaper', 3)];
        $this->assertEquals(mod_callforpaper_get_completion_active_rule_descriptions($cm1), $activeruledescriptions);
        $this->assertEquals(mod_callforpaper_get_completion_active_rule_descriptions($cm2), []);
        $this->assertEquals(mod_callforpaper_get_completion_active_rule_descriptions($moddefaults), $activeruledescriptions);
        $this->assertEquals(mod_callforpaper_get_completion_active_rule_descriptions(new \stdClass()), []);
    }

    /**
     * An unknown event type should not change the callforpaper instance.
     */
    public function test_mod_callforpaper_core_calendar_event_timestart_updated_unknown_event(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $callforpapergenerator = $generator->get_plugin_generator('mod_callforpaper');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $callforpaper = $callforpapergenerator->create_instance(['course' => $course->id]);
        $callforpaper->timeavailablefrom = $timeopen;
        $callforpaper->timeavailableto = $timeclose;
        $DB->update_record('callforpaper', $callforpaper);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'callforpaper',
            'instance' => $callforpaper->id,
            'eventtype' => CALLFORPAPER_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        mod_callforpaper_core_calendar_event_timestart_updated($event, $callforpaper);
        $callforpaper = $DB->get_record('callforpaper', ['id' => $callforpaper->id]);
        $this->assertEquals($timeopen, $callforpaper->timeavailablefrom);
        $this->assertEquals($timeclose, $callforpaper->timeavailableto);
    }

    /**
     * A CALLFORPAPER_EVENT_TYPE_OPEN event should update the timeavailablefrom property of the callforpaper activity.
     */
    public function test_mod_callforpaper_core_calendar_event_timestart_updated_open_event(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $callforpapergenerator = $generator->get_plugin_generator('mod_callforpaper');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeopen = $timeopen - DAYSECS;
        $callforpaper = $callforpapergenerator->create_instance(['course' => $course->id]);
        $callforpaper->timeavailablefrom = $timeopen;
        $callforpaper->timeavailableto = $timeclose;
        $callforpaper->timemodified = $timemodified;
        $DB->update_record('callforpaper', $callforpaper);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'callforpaper',
            'instance' => $callforpaper->id,
            'eventtype' => CALLFORPAPER_EVENT_TYPE_OPEN,
            'timestart' => $newtimeopen,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();
        mod_callforpaper_core_calendar_event_timestart_updated($event, $callforpaper);
        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });
        $callforpaper = $DB->get_record('callforpaper', ['id' => $callforpaper->id]);

        // Ensure the timeavailablefrom property matches the event timestart.
        $this->assertEquals($newtimeopen, $callforpaper->timeavailablefrom);
        // Ensure the timeavailableto isn't changed.
        $this->assertEquals($timeclose, $callforpaper->timeavailableto);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $callforpaper->timemodified);
        // Confirm that a module updated event is fired when the module is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * A CALLFORPAPER_EVENT_TYPE_CLOSE event should update the timeavailableto property of the callforpaper activity.
     */
    public function test_mod_callforpaper_core_calendar_event_timestart_updated_close_event(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $callforpapergenerator = $generator->get_plugin_generator('mod_callforpaper');
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $timemodified = 1;
        $newtimeclose = $timeclose + DAYSECS;
        $callforpaper = $callforpapergenerator->create_instance(['course' => $course->id]);
        $callforpaper->timeavailablefrom = $timeopen;
        $callforpaper->timeavailableto = $timeclose;
        $callforpaper->timemodified = $timemodified;
        $DB->update_record('callforpaper', $callforpaper);

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'callforpaper',
            'instance' => $callforpaper->id,
            'eventtype' => CALLFORPAPER_EVENT_TYPE_CLOSE,
            'timestart' => $newtimeclose,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // Trigger and capture the event when adding a contact.
        $sink = $this->redirectEvents();
        mod_callforpaper_core_calendar_event_timestart_updated($event, $callforpaper);
        $triggeredevents = $sink->get_events();
        $moduleupdatedevents = array_filter($triggeredevents, function($e) {
            return is_a($e, 'core\event\course_module_updated');
        });
        $callforpaper = $DB->get_record('callforpaper', ['id' => $callforpaper->id]);

        // Ensure the timeavailableto property matches the event timestart.
        $this->assertEquals($newtimeclose, $callforpaper->timeavailableto);
        // Ensure the timeavailablefrom isn't changed.
        $this->assertEquals($timeopen, $callforpaper->timeavailablefrom);
        // Ensure the timemodified property has been changed.
        $this->assertNotEquals($timemodified, $callforpaper->timemodified);
        // Confirm that a module updated event is fired when the module is changed.
        $this->assertNotEmpty($moduleupdatedevents);
    }

    /**
     * An unknown event type should not have any limits.
     */
    public function test_mod_callforpaper_core_calendar_get_valid_event_timestart_range_unknown_event(): void {
        global $CFG;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $callforpaper = new \stdClass();
        $callforpaper->timeavailablefrom = $timeopen;
        $callforpaper->timeavailableto = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'callforpaper',
            'instance' => 1,
            'eventtype' => CALLFORPAPER_EVENT_TYPE_OPEN . "SOMETHING ELSE",
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        list ($min, $max) = mod_callforpaper_core_calendar_get_valid_event_timestart_range($event, $callforpaper);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The open event should be limited by the callforpaper's timeclose property, if it's set.
     */
    public function test_mod_callforpaper_core_calendar_get_valid_event_timestart_range_open_event(): void {
        global $CFG;
        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $callforpaper = new \stdClass();
        $callforpaper->timeavailablefrom = $timeopen;
        $callforpaper->timeavailableto = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'callforpaper',
            'instance' => 1,
            'eventtype' => CALLFORPAPER_EVENT_TYPE_OPEN,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_callforpaper_core_calendar_get_valid_event_timestart_range($event, $callforpaper);
        $this->assertNull($min);
        $this->assertEquals($timeclose, $max[0]);

        // No timeclose value should result in no upper limit.
        $callforpaper->timeavailableto = 0;
        list ($min, $max) = mod_callforpaper_core_calendar_get_valid_event_timestart_range($event, $callforpaper);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * The close event should be limited by the callforpaper's timeavailablefrom property, if it's set.
     */
    public function test_mod_callforpaper_core_calendar_get_valid_event_timestart_range_close_event(): void {
        global $CFG;

        require_once($CFG->dirroot . "/calendar/lib.php");

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $timeopen = time();
        $timeclose = $timeopen + DAYSECS;
        $callforpaper = new \stdClass();
        $callforpaper->timeavailablefrom = $timeopen;
        $callforpaper->timeavailableto = $timeclose;

        // Create a valid event.
        $event = new \calendar_event([
            'name' => 'Test event',
            'description' => '',
            'format' => 1,
            'courseid' => $course->id,
            'groupid' => 0,
            'userid' => 2,
            'modulename' => 'callforpaper',
            'instance' => 1,
            'eventtype' => CALLFORPAPER_EVENT_TYPE_CLOSE,
            'timestart' => 1,
            'timeduration' => 86400,
            'visible' => 1
        ]);

        // The max limit should be bounded by the timeclose value.
        list ($min, $max) = mod_callforpaper_core_calendar_get_valid_event_timestart_range($event, $callforpaper);
        $this->assertEquals($timeopen, $min[0]);
        $this->assertNull($max);

        // No timeavailableto value should result in no upper limit.
        $callforpaper->timeavailablefrom = 0;
        list ($min, $max) = mod_callforpaper_core_calendar_get_valid_event_timestart_range($event, $callforpaper);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    /**
     * A user who does not have capabilities to add events to the calendar should be able to create an callforpaper.
     */
    public function test_creation_with_no_calendar_capabilities(): void {
        $this->resetAfterTest();
        $course = self::getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = self::getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $roleid = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $user->id, $context->id);
        assign_capability('moodle/calendar:manageentries', CAP_PROHIBIT, $roleid, $context, true);
        $generator = self::getDataGenerator()->get_plugin_generator('mod_callforpaper');
        // Create an instance as a user without the calendar capabilities.
        $this->setUser($user);
        $time = time();
        $params = array(
            'course' => $course->id,
            'timeavailablefrom' => $time + 200,
            'timeavailableto' => $time + 2000,
            'timeviewfrom' => $time + 400,
            'timeviewto' => $time + 2000,
        );
        $generator->create_instance($params);
    }

    /**
     * Test for callforpaper_generate_default_template(). This method covers different scenarios for checking when the returned value
     * is empty or not, but doesn't check if the content has the expected value when it's not empty.
     *
     * @covers ::callforpaper_generate_default_template
     */
    public function test_callforpaper_generate_default_template(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module(manager::MODULE, ['course' => $course]);

        // Check the result is empty when $callforpaper and/or $template are null.
        $nullactivity = null;
        $result = callforpaper_generate_default_template($nullactivity, 'listtemplate', 0, false, false);
        $this->assertEmpty($result);
        $result = callforpaper_generate_default_template($activity, null, 0, false, false);
        $this->assertEmpty($result);
        $result = callforpaper_generate_default_template($nullactivity, null, 0, false, false);
        $this->assertEmpty($result);

        // Check the result is empty when any of the templates that are empty are given.
        $emptytemplates = [
            'csstemplate',
            'jstemplate',
            'listtemplateheader',
            'listtemplatefooter',
            'rsstitletemplate',
        ];
        foreach ($emptytemplates as $emptytemplate) {
            $result = callforpaper_generate_default_template($activity, $emptytemplate, 0, false, false);
            $this->assertEmpty($result);
        }

        $templates = [
            'listtemplate',
            'singletemplate',
            'asearchtemplate',
        ];
        // Check the result is empty when the callforpaper has no fields.
        foreach ($templates as $template) {
            $result = callforpaper_generate_default_template($activity, $template, 0, false, false);
            $this->assertEmpty($result);
            $this->assertEmpty($activity->{$template});
        }

        // Add a field to the activity.
        $fieldrecord = new stdClass();
        $fieldrecord->name = 'field-1';
        $fieldrecord->type = 'text';
        $callforpapergenerator = $this->getDataGenerator()->get_plugin_generator('mod_callforpaper');
        $callforpapergenerator->create_field($fieldrecord, $activity);

        // Check the result is not empty when the callforpaper has no entries.
        foreach ($templates as $template) {
            $result = callforpaper_generate_default_template($activity, $template, 0, false, false);
            $this->assertNotEmpty($result);
            $this->assertEmpty($activity->{$template});
        }

        // Check the result is not empty when the callforpaper has no entries and the result is saved when $update = true.
        foreach ($templates as $template) {
            $result = callforpaper_generate_default_template($activity, $template, 0, false, true);
            $this->assertNotEmpty($result);
            $this->assertNotEmpty($activity->{$template});
        }
    }

    /**
     * Test for callforpaper_replace_field_in_templates().
     *
     * @covers ::callforpaper_replace_field_in_templates
     */
    public function test_callforpaper_replace_field_in_templates(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $templatecontent = "Field [[myfield]], [[myfield#id]], [[myfield#name]], [[myfield#description]], ";

        $params = ['course' => $course];
        foreach (manager::TEMPLATES_LIST as $templatename => $templatefile) {
            $params[$templatename] = $templatecontent;
        }
        $activity = $this->getDataGenerator()->create_module(manager::MODULE, $params);

        $generator = $this->getDataGenerator()->get_plugin_generator(manager::PLUGINNAME);
        $fieldrecord = (object)['name' => 'myfield', 'type' => 'text', 'description' => 'This is a field'];
        $generator->create_field($fieldrecord, $activity);

        callforpaper_replace_field_in_templates($activity, 'myfield', 'newfieldname');
        $dbactivity = $DB->get_record(manager::MODULE, ['id' => $activity->id]);

        $newcontent = "Field [[newfieldname]], [[newfieldname#id]], [[newfieldname#name]], [[newfieldname#description]], ";
        // Field compatible templates.
        $this->assertEquals($newcontent, $dbactivity->listtemplate);
        $this->assertEquals($newcontent, $dbactivity->singletemplate);
        $this->assertEquals($newcontent, $dbactivity->asearchtemplate);
        $this->assertEquals($newcontent, $dbactivity->addtemplate);
        $this->assertEquals($newcontent, $dbactivity->rsstemplate);
        // Other templates.
        $this->assertEquals($templatecontent, $dbactivity->listtemplateheader);
        $this->assertEquals($templatecontent, $dbactivity->listtemplatefooter);
        $this->assertEquals($templatecontent, $dbactivity->csstemplate);
        $this->assertEquals($templatecontent, $dbactivity->jstemplate);
        $this->assertEquals($templatecontent, $dbactivity->rsstitletemplate);
    }

    /**
     * Test for callforpaper_append_new_field_to_templates().
     *
     * @covers ::callforpaper_append_new_field_to_templates
     * @dataProvider callforpaper_append_new_field_to_templates_provider
     * @param bool $hasfield if the field is present in the templates
     * @param bool $hasotherfields if the field is not present in the templates
     * @param bool $expected the expected return
     */
    public function test_callforpaper_append_new_field_to_templates(bool $hasfield, bool $hasotherfields, bool $expected): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $templatecontent = "Template content";
        if ($hasfield) {
            $templatecontent .= "Has [[myfield]].";
        }
        if ($hasotherfields) {
            $templatecontent .= "And also ##otherfields##.";
        }

        $course = $this->getDataGenerator()->create_course();
        $params = ['course' => $course];
        foreach (manager::TEMPLATES_LIST as $templatename => $templatefile) {
            $params[$templatename] = $templatecontent;
        }
        $activity = $this->getDataGenerator()->create_module(manager::MODULE, $params);

        $result = callforpaper_append_new_field_to_templates($activity, 'myfield');
        $this->assertEquals($expected, $result);

        // Check fields with auto add fields.
        $dbactivity = $DB->get_record(manager::MODULE, ['id' => $activity->id]);
        if ($hasfield || $hasotherfields) {
            $this->assertEquals($dbactivity->singletemplate, $templatecontent);
            $this->assertEquals($dbactivity->addtemplate, $templatecontent);
            $this->assertEquals($dbactivity->rsstemplate, $templatecontent);
        } else {
            $regexp = '|Template content.*\[\[myfield\]\]|';
            // We don't want line breaks for the validations.
            $this->assertMatchesRegularExpression($regexp, str_replace("\n", '', $dbactivity->singletemplate));
            $this->assertMatchesRegularExpression($regexp, str_replace("\n", '', $dbactivity->addtemplate));
            $this->assertMatchesRegularExpression($regexp, str_replace("\n", '', $dbactivity->rsstemplate));
        }
        // No auto add field templates.
        $this->assertEquals($dbactivity->asearchtemplate, $templatecontent);
        $this->assertEquals($dbactivity->listtemplate, $templatecontent);
        $this->assertEquals($dbactivity->listtemplateheader, $templatecontent);
        $this->assertEquals($dbactivity->listtemplatefooter, $templatecontent);
        $this->assertEquals($dbactivity->csstemplate, $templatecontent);
        $this->assertEquals($dbactivity->jstemplate, $templatecontent);
        $this->assertEquals($dbactivity->rsstitletemplate, $templatecontent);
    }

    /**
     * Data provider for test_callforpaper_append_new_field_to_templates().
     *
     * @return array of scenarios
     */
    public static function callforpaper_append_new_field_to_templates_provider(): array {
        return [
            'Plain template' => [
                'hasfield' => false,
                'hasotherfields' => false,
                'expected' => true,
            ],
            'Field already present' => [
                'hasfield' => true,
                'hasotherfields' => false,
                'expected' => false,
            ],
            '##otherfields## tag present' => [
                'hasfield' => false,
                'hasotherfields' => true,
                'expected' => false,
            ],
            'Field already present and ##otherfields## tag present' => [
                'hasfield' => true,
                'hasotherfields' => true,
                'expected' => false,
            ],
        ];
    }

    /**
     * Test that format that are not supported are raising an exception
     *
     * @param string $type
     * @param string $expected
     * @covers \callforpaper_get_field_new
     * @dataProvider format_parser_provider
     */
    public function test_create_field(string $type, string $expected): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $callforpaper = $this->getDataGenerator()->create_module('callforpaper', ['course' => $course->id]  );
        if ($expected === 'exception') {
            $this->expectException(\moodle_exception::class);
        }
        $field = callforpaper_get_field_new($type, $callforpaper);
        $this->assertStringContainsString($expected, get_class($field));
    }

    /**
     * Data provider for test_format_parser
     *
     * @return array[]
     */
    public static function format_parser_provider(): array {
        return [
            'text' => [
                'type' => 'text',
                'expected' => 'callforpaper_field_text',
            ],
            'picture' => [
                'type' => 'picture',
                'expected' => 'callforpaper_field_picture',
            ],
            'wrong type' => [
                'type' => '../wrongformat123',
                'expected' => 'exception',
            ],
        ];
    }
}
