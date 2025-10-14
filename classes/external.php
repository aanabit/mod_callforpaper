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
 * Call for paper module external API
 *
 * @package    mod_callforpaper
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . "/mod/callforpaper/locallib.php");

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;
use mod_callforpaper\external\callforpaper_summary_exporter;
use mod_callforpaper\external\record_exporter;
use mod_callforpaper\external\field_exporter;
use mod_callforpaper\manager;

/**
 * Call for paper module external functions
 *
 * @package    mod_callforpaper
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class mod_callforpaper_external extends external_api {

    /**
     * Describes the parameters for get_callforpapers_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_callforpapers_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id', VALUE_REQUIRED),
                    'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of callforpapers in a provided list of courses,
     * if no list is provided all callforpapers that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array the callforpaper details
     * @since Moodle 2.9
     */
    public static function get_callforpapers_by_courses($courseids = array()) {
        global $PAGE;

        $params = self::validate_parameters(self::get_callforpapers_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Array to store the callforpapers to return.
        $arrcallforpapers = array();

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($dbcourses, $warnings) = util::validate_courses($params['courseids'], $mycourses);

            // Get the callforpapers in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $callforpapers = get_all_instances_in_courses("callforpaper", $dbcourses);

            foreach ($callforpapers as $callforpaper) {

                $context = context_module::instance($callforpaper->coursemodule);
                // Remove fields added by get_all_instances_in_courses.
                unset($callforpaper->coursemodule, $callforpaper->section, $callforpaper->visible, $callforpaper->groupmode, $callforpaper->groupingid);

                // This information should be only available if the user can see the callforpaper entries.
                if (!has_capability('mod/callforpaper:viewentry', $context)) {
                    $fields = array('comments', 'timeavailablefrom', 'timeavailableto', 'timeviewfrom',
                                    'timeviewto', 'requiredentries', 'requiredentriestoview', 'maxentries', 'rssarticles',
                                    'singletemplate', 'listtemplate', 'listtemplateheader', 'listtemplatefooter', 'addtemplate',
                                    'rsstemplate', 'rsstitletemplate', 'csstemplate', 'jstemplate', 'asearchtemplate', 'approval',
                                    'manageapproved', 'defaultsort', 'defaultsortdir');

                    foreach ($fields as $field) {
                        unset($callforpaper->{$field});
                    }
                }

                // Check additional permissions for returning optional private settings.
                // I avoid intentionally to use can_[add|update]_moduleinfo.
                if (!has_capability('moodle/course:manageactivities', $context)) {

                    $fields = array('scale', 'assessed', 'assesstimestart', 'assesstimefinish', 'editany', 'notification',
                                    'timemodified');

                    foreach ($fields as $field) {
                        unset($callforpaper->{$field});
                    }
                }
                $exporter = new callforpaper_summary_exporter($callforpaper, array('context' => $context));
                $callforpaper = $exporter->export($PAGE->get_renderer('core'));
                $callforpaper->name = \core_external\util::format_string($callforpaper->name, $context);
                $arrcallforpapers[] = $callforpaper;
            }
        }

        $result = array();
        $result['callforpapers'] = $arrcallforpapers;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_callforpapers_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 2.9
     */
    public static function get_callforpapers_by_courses_returns() {

        return new external_single_structure(
            array(
                'callforpapers' => new external_multiple_structure(
                    callforpaper_summary_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating a callforpaper.
     *
     * @param int $callforpaperid callforpaper instance id
     * @return array array containing the callforpaper object, course, context and course module objects
     * @since  Moodle 3.3
     */
    protected static function validate_callforpaper($callforpaperid) {
        global $DB;

        // Request and permission validation.
        $callforpaper = $DB->get_record('callforpaper', array('id' => $callforpaperid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($callforpaper, 'callforpaper');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/callforpaper:viewentry', $context);

        return array($callforpaper, $course, $cm, $context);
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function view_callforpaper_parameters() {
        return new external_function_parameters(
            array(
                'callforpaperid' => new external_value(PARAM_INT, 'callforpaper instance id')
            )
        );
    }

    /**
     * Simulate the callforpaper/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $callforpaperid the callforpaper instance id
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function view_callforpaper($callforpaperid) {

        $params = self::validate_parameters(self::view_callforpaper_parameters(), array('callforpaperid' => $callforpaperid));
        $warnings = array();

        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($params['callforpaperid']);

        // Call the callforpaper/lib API.
        $manager = manager::create_from_coursemodule($cm);
        $manager->set_module_viewed($course);

        $result = [
            'status' => true,
            'warnings' => $warnings,
        ];
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function view_callforpaper_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_callforpaper_access_information_parameters() {
        return new external_function_parameters(
            array(
                'callforpaperid' => new external_value(PARAM_INT, 'Call for paper instance id.'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group.',
                                                   VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given callforpaper.
     *
     * @param int $callforpaperid the callforpaper instance id
     * @param int $groupid (optional) group id, 0 means that the function will determine the user group
     * @return array of warnings and access information
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_callforpaper_access_information($callforpaperid, $groupid = 0) {

        $params = array('callforpaperid' => $callforpaperid, 'groupid' => $groupid);
        $params = self::validate_parameters(self::get_callforpaper_access_information_parameters(), $params);
        $warnings = array();

        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($params['callforpaperid']);

        $result = array(
            'warnings' => $warnings
        );

        $groupmode = groups_get_activity_groupmode($cm);
        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode) {
                $groupid = groups_get_activity_group($cm);
            } else {
                $groupid = 0;
            }
        }
        // Group related information.
        $result['groupid'] = $groupid;
        $result['canaddentry'] = callforpaper_user_can_add_entry($callforpaper, $groupid, $groupmode, $context);

        // Now capabilities.
        $result['canmanageentries'] = has_capability('mod/callforpaper:manageentries', $context);
        $result['canapprove'] = has_capability('mod/callforpaper:approve', $context);

        // Now time access restrictions.
        list($result['timeavailable'], $warnings) = callforpaper_get_time_availability_status($callforpaper, $result['canmanageentries']);

        // Other information.
        $result['numentries'] = callforpaper_numentries($callforpaper);
        $result['entrieslefttoadd'] = callforpaper_get_entries_left_to_add($callforpaper, $result['numentries'], $result['canmanageentries']);
        $result['entrieslefttoview'] = callforpaper_get_entries_left_to_view($callforpaper, $result['numentries'], $result['canmanageentries']);
        $result['inreadonlyperiod'] = callforpaper_in_readonly_period($callforpaper);

        return $result;
    }

    /**
     * Returns description of method result value.
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function get_callforpaper_access_information_returns() {
        return new external_single_structure(
            array(
                'groupid' => new external_value(PARAM_INT, 'User current group id (calculated)'),
                'canaddentry' => new external_value(PARAM_BOOL, 'Whether the user can add entries or not.'),
                'canmanageentries' => new external_value(PARAM_BOOL, 'Whether the user can manage entries or not.'),
                'canapprove' => new external_value(PARAM_BOOL, 'Whether the user can approve entries or not.'),
                'timeavailable' => new external_value(PARAM_BOOL, 'Whether the callforpaper is available or not by time restrictions.'),
                'inreadonlyperiod' => new external_value(PARAM_BOOL, 'Whether the callforpaper is in read mode only.'),
                'numentries' => new external_value(PARAM_INT, 'The number of entries the current user added.'),
                'entrieslefttoadd' => new external_value(PARAM_INT, 'The number of entries left to complete the activity.'),
                'entrieslefttoview' => new external_value(PARAM_INT, 'The number of entries left to view other users entries.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_entries_parameters() {
        return new external_function_parameters(
            array(
                'callforpaperid' => new external_value(PARAM_INT, 'callforpaper instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                   VALUE_DEFAULT, 0),
                'returncontents' => new external_value(PARAM_BOOL, 'Whether to return contents or not. This will return each entry
                                                        raw contents and the complete list view (using the template).',
                                                        VALUE_DEFAULT, false),
                'sort' => new external_value(PARAM_INT, 'Sort the records by this field id, reserved ids are:
                                                0: timeadded
                                                -1: firstname
                                                -2: lastname
                                                -3: approved
                                                -4: timemodified.
                                                Empty for using the default callforpaper setting.', VALUE_DEFAULT, null),
                'order' => new external_value(PARAM_ALPHA, 'The direction of the sorting: \'ASC\' or \'DESC\'.
                                                Empty for using the default callforpaper setting.', VALUE_DEFAULT, null),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given feedback
     *
     * @param int $callforpaperid       the callforpaper instance id
     * @param int $groupid          (optional) group id, 0 means that the function will determine the user group
     * @param bool $returncontents  Whether to return the entries contents or not
     * @param str $sort             sort by this field
     * @param int $order            the direction of the sorting
     * @param int $page             page of records to return
     * @param int $perpage          number of records to return per page
     * @return array of warnings and the entries
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_entries($callforpaperid, $groupid = 0, $returncontents = false, $sort = null, $order = null,
            $page = 0, $perpage = 0) {
        global $PAGE, $DB;

        $params = array('callforpaperid' => $callforpaperid, 'groupid' => $groupid, 'returncontents' => $returncontents ,
                        'sort' => $sort, 'order' => $order, 'page' => $page, 'perpage' => $perpage);
        $params = self::validate_parameters(self::get_entries_parameters(), $params);
        $warnings = array();

        if (!empty($params['order'])) {
            $params['order'] = strtoupper($params['order']);
            if ($params['order'] != 'ASC' && $params['order'] != 'DESC') {
                throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $params['order'] . ')');
            }
        }

        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($params['callforpaperid']);
        // Check callforpaper is open in time.
        callforpaper_require_time_available($callforpaper, null, $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                // We don't need to validate a possible groupid = 0 since it would be handled by callforpaper_search_entries.
                $groupid = groups_get_activity_group($cm);
            } else {
                $groupid = 0;
            }
        }

        $manager = manager::create_from_instance($callforpaper);

        list($records, $maxcount, $totalcount, $page, $nowperpage, $sort, $mode) =
            callforpaper_search_entries($callforpaper, $cm, $context, 'list', $groupid, '', $params['sort'], $params['order'],
                $params['page'], $params['perpage']);

        $entries = [];
        $contentsids = [];  // Store here the content ids of the records returned.
        foreach ($records as $record) {
            $user = user_picture::unalias($record, null, 'userid');
            $related = array('context' => $context, 'callforpaper' => $callforpaper, 'user' => $user);

            $contents = $DB->get_records('callforpaper_content', array('recordid' => $record->id));
            $contentsids = array_merge($contentsids, array_keys($contents));
            if ($params['returncontents']) {
                $related['contents'] = $contents;
            } else {
                $related['contents'] = null;
            }

            $exporter = new record_exporter($record, $related);
            $entries[] = $exporter->export($PAGE->get_renderer('core'));
        }

        // Retrieve total files size for the records retrieved.
        $totalfilesize = 0;
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_callforpaper', 'content');
        foreach ($files as $file) {
            if ($file->is_directory() || !in_array($file->get_itemid(), $contentsids)) {
                continue;
            }
            $totalfilesize += $file->get_filesize();
        }

        $result = array(
            'entries' => $entries,
            'totalcount' => $totalcount,
            'totalfilesize' => $totalfilesize,
            'warnings' => $warnings
        );

        // Check if we should return the list rendered.
        if ($params['returncontents']) {
            $parser = $manager->get_template('listtemplate', ['page' => $page]);
            $result['listviewcontents'] = $parser->parse_entries($records);
        }

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function get_entries_returns() {
        return new external_single_structure(
            array(
                'entries' => new external_multiple_structure(
                    record_exporter::get_read_structure()
                ),
                'totalcount' => new external_value(PARAM_INT, 'Total count of records.'),
                'totalfilesize' => new external_value(PARAM_INT, 'Total size (bytes) of the files included in the records.'),
                'listviewcontents' => new external_value(PARAM_RAW, 'The list view contents as is rendered in the site.',
                                                            VALUE_OPTIONAL),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_entry_parameters() {
        return new external_function_parameters(
            array(
                'entryid' => new external_value(PARAM_INT, 'record entry id'),
                'returncontents' => new external_value(PARAM_BOOL, 'Whether to return contents or not.', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Return one entry record from the callforpaper, including contents optionally.
     *
     * @param int $entryid          the record entry id id
     * @param bool $returncontents  whether to return the entries contents or not
     * @return array of warnings and the entries
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_entry($entryid, $returncontents = false) {
        global $PAGE, $DB;

        $params = array('entryid' => $entryid, 'returncontents' => $returncontents);
        $params = self::validate_parameters(self::get_entry_parameters(), $params);
        $warnings = array();

        $record = $DB->get_record('callforpaper_records', array('id' => $params['entryid']), '*', MUST_EXIST);
        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($record->callforpaperid);

        // Check callforpaper is open in time.
        $canmanageentries = has_capability('mod/callforpaper:manageentries', $context);
        callforpaper_require_time_available($callforpaper, $canmanageentries);

        $manager = manager::create_from_instance($callforpaper);

        if ($record->groupid != 0) {
            if (!groups_group_visible($record->groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        }

        // Check correct record entry. Group check was done before.
        if (!callforpaper_can_view_record($callforpaper, $record, $record->groupid, $canmanageentries)) {
            throw new moodle_exception('notapprovederror', 'callforpaper');
        }

        $related = array('context' => $context, 'callforpaper' => $callforpaper, 'user' => null);
        if ($params['returncontents']) {
            $related['contents'] = $DB->get_records('callforpaper_content', array('recordid' => $record->id));
        } else {
            $related['contents'] = null;
        }
        $exporter = new record_exporter($record, $related);
        $entry = $exporter->export($PAGE->get_renderer('core'));

        $result = array(
            'entry' => $entry,
            'ratinginfo' => \core_rating\external\util::get_rating_info($callforpaper, $context, 'mod_callforpaper', 'entry', array($record)),
            'warnings' => $warnings
        );
        // Check if we should return the entry rendered.
        if ($params['returncontents']) {
            $records = [$record];
            $parser = $manager->get_template('singletemplate');
            $result['entryviewcontents'] = $parser->parse_entries($records);
        }

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function get_entry_returns() {
        return new external_single_structure(
            array(
                'entry' => record_exporter::get_read_structure(),
                'entryviewcontents' => new external_value(PARAM_RAW, 'The entry as is rendered in the site.', VALUE_OPTIONAL),
                'ratinginfo' => \core_rating\external\util::external_ratings_structure(),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_fields_parameters() {
        return new external_function_parameters(
            array(
                'callforpaperid' => new external_value(PARAM_INT, 'Call for paper instance id.'),
            )
        );
    }

    /**
     * Return the list of configured fields for the given callforpaper.
     *
     * @param int $callforpaperid the callforpaper id
     * @return array of warnings and the fields
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_fields($callforpaperid) {
        global $PAGE;

        $params = array('callforpaperid' => $callforpaperid);
        $params = self::validate_parameters(self::get_fields_parameters(), $params);
        $fields = $warnings = array();

        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($params['callforpaperid']);

        // Check callforpaper is open in time.
        $canmanageentries = has_capability('mod/callforpaper:manageentries', $context);
        callforpaper_require_time_available($callforpaper, $canmanageentries);

        $fieldinstances = callforpaper_get_field_instances($callforpaper);

        foreach ($fieldinstances as $fieldinstance) {
            $record = $fieldinstance->field;
            // Now get the configs the user can see with his current permissions.
            $configs = $fieldinstance->get_config_for_external();
            foreach ($configs as $name => $value) {
                // Overwrite.
                $record->{$name} = $value;
            }

            $exporter = new field_exporter($record, array('context' => $context));
            $fields[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'fields' => $fields,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function get_fields_returns() {
        return new external_single_structure(
            array(
                'fields' => new external_multiple_structure(
                    field_exporter::get_read_structure()
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function search_entries_parameters() {
        return new external_function_parameters(
            array(
                'callforpaperid' => new external_value(PARAM_INT, 'callforpaper instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                   VALUE_DEFAULT, 0),
                'returncontents' => new external_value(PARAM_BOOL, 'Whether to return contents or not.', VALUE_DEFAULT, false),
                'search' => new external_value(PARAM_NOTAGS, 'search string (empty when using advanced)', VALUE_DEFAULT, ''),
                'advsearch' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'Field key for search.
                                                            Use fn or ln for first or last name'),
                            'value' => new external_value(PARAM_RAW, 'JSON encoded value for search'),
                        )
                    ), 'Advanced search', VALUE_DEFAULT, array()
                ),
                'sort' => new external_value(PARAM_INT, 'Sort the records by this field id, reserved ids are:
                                                0: timeadded
                                                -1: firstname
                                                -2: lastname
                                                -3: approved
                                                -4: timemodified.
                                                Empty for using the default callforpaper setting.', VALUE_DEFAULT, null),
                'order' => new external_value(PARAM_ALPHA, 'The direction of the sorting: \'ASC\' or \'DESC\'.
                                                Empty for using the default callforpaper setting.', VALUE_DEFAULT, null),
                'page' => new external_value(PARAM_INT, 'The page of records to return.', VALUE_DEFAULT, 0),
                'perpage' => new external_value(PARAM_INT, 'The number of records to return per page', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return access information for a given feedback
     *
     * @param int $callforpaperid       the callforpaper instance id
     * @param int $groupid          (optional) group id, 0 means that the function will determine the user group
     * @param bool $returncontents  whether to return contents or not
     * @param str $search           search text
     * @param array $advsearch      advanced search callforpaper
     * @param str $sort             sort by this field
     * @param int $order            the direction of the sorting
     * @param int $page             page of records to return
     * @param int $perpage          number of records to return per page
     * @return array of warnings and the entries
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function search_entries($callforpaperid, $groupid = 0, $returncontents = false, $search = '', $advsearch = [],
            $sort = null, $order = null, $page = 0, $perpage = 0) {
        global $PAGE, $DB;

        $params = array('callforpaperid' => $callforpaperid, 'groupid' => $groupid, 'returncontents' => $returncontents, 'search' => $search,
                        'advsearch' => $advsearch, 'sort' => $sort, 'order' => $order, 'page' => $page, 'perpage' => $perpage);
        $params = self::validate_parameters(self::search_entries_parameters(), $params);
        $warnings = array();

        if (!empty($params['order'])) {
            $params['order'] = strtoupper($params['order']);
            if ($params['order'] != 'ASC' && $params['order'] != 'DESC') {
                throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $params['order'] . ')');
            }
        }

        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($params['callforpaperid']);
        // Check callforpaper is open in time.
        callforpaper_require_time_available($callforpaper, null, $context);

        $manager = manager::create_from_instance($callforpaper);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                // We don't need to validate a possible groupid = 0 since it would be handled by callforpaper_search_entries.
                $groupid = groups_get_activity_group($cm);
            } else {
                $groupid = 0;
            }
        }

        if (!empty($params['advsearch'])) {
            $advanced = true;
            $defaults = [];
            $fn = $ln = ''; // Defaults for first and last name.
            // Force defaults for advanced search.
            foreach ($params['advsearch'] as $adv) {
                if ($adv['name'] == 'fn') {
                    $fn = json_decode($adv['value']);
                    continue;
                }
                if ($adv['name'] == 'ln') {
                    $ln = json_decode($adv['value']);
                    continue;
                }
                $defaults[$adv['name']] = json_decode($adv['value']);
            }
            list($searcharray, $params['search']) = callforpaper_build_search_array($callforpaper, false, [], $defaults, $fn, $ln);
        } else {
            $advanced = null;
            $searcharray = null;
        }

        list($records, $maxcount, $totalcount, $page, $nowperpage, $sort, $mode) =
            callforpaper_search_entries($callforpaper, $cm, $context, 'list', $groupid, $params['search'], $params['sort'], $params['order'],
                $params['page'], $params['perpage'], $advanced, $searcharray);

        $entries = [];
        foreach ($records as $record) {
            $user = user_picture::unalias($record, null, 'userid');
            $related = array('context' => $context, 'callforpaper' => $callforpaper, 'user' => $user);
            if ($params['returncontents']) {
                $related['contents'] = $DB->get_records('callforpaper_content', array('recordid' => $record->id));
            } else {
                $related['contents'] = null;
            }

            $exporter = new record_exporter($record, $related);
            $entries[] = $exporter->export($PAGE->get_renderer('core'));
        }

        $result = array(
            'entries' => $entries,
            'totalcount' => $totalcount,
            'maxcount' => $maxcount,
            'warnings' => $warnings
        );

        // Check if we should return the list rendered.
        if ($params['returncontents']) {
            $parser = $manager->get_template('listtemplate', ['page' => $page]);
            $result['listviewcontents'] = $parser->parse_entries($records);
        }

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function search_entries_returns() {
        return new external_single_structure(
            array(
                'entries' => new external_multiple_structure(
                    record_exporter::get_read_structure()
                ),
                'totalcount' => new external_value(PARAM_INT, 'Total count of records returned by the search.'),
                'maxcount' => new external_value(PARAM_INT, 'Total count of records that the user could see in the callforpaper
                    (if all the search criterias were removed).', VALUE_OPTIONAL),
                'listviewcontents' => new external_value(PARAM_RAW, 'The list view contents as is rendered in the site.',
                                                            VALUE_OPTIONAL),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function approve_entry_parameters() {
        return new external_function_parameters(
            array(
                'entryid' => new external_value(PARAM_INT, 'Record entry id.'),
                'approve' => new external_value(PARAM_BOOL, 'Whether to approve (true) or unapprove the entry.',
                                                VALUE_DEFAULT, true),
            )
        );
    }

    /**
     * Approves or unapproves an entry.
     *
     * @param int $entryid          the record entry id id
     * @param bool $approve         whether to approve (true) or unapprove the entry
     * @return array of warnings and the entries
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function approve_entry($entryid, $approve = true) {
        global $PAGE, $DB;

        $params = array('entryid' => $entryid, 'approve' => $approve);
        $params = self::validate_parameters(self::approve_entry_parameters(), $params);
        $warnings = array();

        $record = $DB->get_record('callforpaper_records', array('id' => $params['entryid']), '*', MUST_EXIST);
        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($record->callforpaperid);
        // Check callforpaper is open in time.
        callforpaper_require_time_available($callforpaper, null, $context);
        // Check specific capabilities.
        require_capability('mod/callforpaper:approve', $context);

        callforpaper_approve_entry($record->id, $params['approve']);

        $result = array(
            'status' => true,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function approve_entry_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function delete_entry_parameters() {
        return new external_function_parameters(
            array(
                'entryid' => new external_value(PARAM_INT, 'Record entry id.'),
            )
        );
    }

    /**
     * Deletes an entry.
     *
     * @param int $entryid the record entry id
     * @return array of warnings success status
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function delete_entry($entryid) {
        global $PAGE, $DB;

        $params = array('entryid' => $entryid);
        $params = self::validate_parameters(self::delete_entry_parameters(), $params);
        $warnings = array();

        $record = $DB->get_record('callforpaper_records', array('id' => $params['entryid']), '*', MUST_EXIST);
        list($callforpaper, $course, $cm, $context) = self::validate_callforpaper($record->callforpaperid);

        if (callforpaper_user_can_manage_entry($record, $callforpaper, $context)) {
            callforpaper_delete_record($record->id, $callforpaper, $course->id, $cm->id);
        } else {
            throw new moodle_exception('noaccess', 'callforpaper');
        }

        $result = array(
            'status' => true,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function delete_entry_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'Always true. If we see this field it means that the entry was deleted.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function add_entry_parameters() {
        return new external_function_parameters(
            array(
                'callforpaperid' => new external_value(PARAM_INT, 'callforpaper instance id'),
                'groupid' => new external_value(PARAM_INT, 'Group id, 0 means that the function will determine the user group',
                                                   VALUE_DEFAULT, 0),
                'callforpaper' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'fieldid' => new external_value(PARAM_INT, 'The field id.'),
                            'subfield' => new external_value(PARAM_NOTAGS, 'The subfield name (if required).', VALUE_DEFAULT, ''),
                            'value' => new external_value(PARAM_RAW, 'The contents for the field always JSON encoded.'),
                        )
                    ), 'The fields callforpaper to be created'
                ),
            )
        );
    }

    /**
     * Adds a new entry to a callforpaper
     *
     * @param int $callforpaperid the callforpaper instance id
     * @param int $groupid (optional) group id, 0 means that the function will determine the user group
     * @param array $callforpaper the fields callforpaper to be created
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function add_entry($callforpaperid, $groupid, $callforpaper) {
        global $DB;

        $params = array('callforpaperid' => $callforpaperid, 'groupid' => $groupid, 'callforpaper' => $callforpaper);
        $params = self::validate_parameters(self::add_entry_parameters(), $params);
        $warnings = array();
        $fieldnotifications = array();

        list($callforpaperobject, $course, $cm, $context) = self::validate_callforpaper($params['callforpaperid']);

        $fields = $DB->get_records('callforpaper_fields', ['callforpaperid' => $callforpaperobject->id]);
        if (empty($fields)) {
            throw new moodle_exception('nofieldincallforpaper', 'callforpaper');
        }

        // Check callforpaper is open in time.
        callforpaper_require_time_available($callforpaperobject, null, $context);

        $groupmode = groups_get_activity_groupmode($cm);
        // Determine default group.
        if (empty($params['groupid'])) {
            // Check to see if groups are being used here.
            if ($groupmode) {
                $groupid = groups_get_activity_group($cm);
            } else {
                $groupid = 0;
            }
        }

        // Group is validated inside the function.
        if (!callforpaper_user_can_add_entry($callforpaperobject, $groupid, $groupmode, $context)) {
            throw new moodle_exception('noaccess', 'callforpaper');
        }

        // Prepare the callforpaper as is expected by the API.
        $callforpaperrecord = new stdClass;
        foreach ($params['callforpaper'] as $callforpaper) {
            $subfield = ($callforpaper['subfield'] !== '') ? '_' . $callforpaper['subfield'] : '';
            // We ask for JSON encoded values because of multiple choice forms or checkboxes that use array parameters.
            $callforpaperrecord->{'field_' . $callforpaper['fieldid'] . $subfield} = json_decode($callforpaper['value']);
        }
        // Validate to ensure that enough callforpaper was submitted.
        $processeddata = callforpaper_process_submission($callforpaperobject, $fields, $callforpaperrecord);

        // Format notifications.
        if (!empty($processeddata->fieldnotifications)) {
            foreach ($processeddata->fieldnotifications as $field => $notififications) {
                foreach ($notififications as $notif) {
                    $fieldnotifications[] = [
                        'fieldname' => $field,
                        'notification' => $notif,
                    ];
                }
            }
        }

        // Create a new (empty) record.
        $newentryid = 0;
        if ($processeddata->validated && $recordid = callforpaper_add_record($callforpaperobject, $groupid)) {
            $newentryid = $recordid;
            // Now populate the fields contents of the new record.
            callforpaper_add_fields_contents_to_new_record($callforpaperobject, $context, $recordid, $fields, $callforpaperrecord, $processeddata);
        }

        $result = array(
            'newentryid' => $newentryid,
            'generalnotifications' => $processeddata->generalnotifications,
            'fieldnotifications' => $fieldnotifications,
        );
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function add_entry_returns() {
        return new external_single_structure(
            array(
                'newentryid' => new external_value(PARAM_INT, 'True new created entry id. 0 if the entry was not created.'),
                'generalnotifications' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'General notifications')
                ),
                'fieldnotifications' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'fieldname' => new external_value(PARAM_TEXT, 'The field name.'),
                            'notification' => new external_value(PARAM_RAW, 'The notification for the field.'),
                        )
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function update_entry_parameters() {
        return new external_function_parameters(
            array(
                'entryid' => new external_value(PARAM_INT, 'The entry record id.'),
                'callforpaper' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'fieldid' => new external_value(PARAM_INT, 'The field id.'),
                            'subfield' => new external_value(PARAM_NOTAGS, 'The subfield name (if required).', VALUE_DEFAULT, null),
                            'value' => new external_value(PARAM_RAW, 'The new contents for the field always JSON encoded.'),
                        )
                    ), 'The fields callforpaper to be updated'
                ),
            )
        );
    }

    /**
     * Updates an existing entry.
     *
     * @param int $entryid the callforpaper instance id
     * @param array $callforpaper the fields callforpaper to be created
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function update_entry($entryid, $callforpaper) {
        global $DB;

        $params = array('entryid' => $entryid, 'callforpaper' => $callforpaper);
        $params = self::validate_parameters(self::update_entry_parameters(), $params);
        $warnings = array();
        $fieldnotifications = array();
        $updated = false;

        $record = $DB->get_record('callforpaper_records', array('id' => $params['entryid']), '*', MUST_EXIST);
        list($callforpaperobject, $course, $cm, $context) = self::validate_callforpaper($record->callforpaperid);
        // Check callforpaper is open in time.
        callforpaper_require_time_available($callforpaperobject, null, $context);

        if (!callforpaper_user_can_manage_entry($record, $callforpaperobject, $context)) {
            throw new moodle_exception('noaccess', 'callforpaper');
        }

        // Prepare the callforpaper as is expected by the API.
        $callforpaperrecord = new stdClass;
        foreach ($params['callforpaper'] as $callforpaper) {
            $subfield = ($callforpaper['subfield'] !== '') ? '_' . $callforpaper['subfield'] : '';
            // We ask for JSON encoded values because of multiple choice forms or checkboxes that use array parameters.
            $callforpaperrecord->{'field_' . $callforpaper['fieldid'] . $subfield} = json_decode($callforpaper['value']);
        }
        // Validate to ensure that enough callforpaper was submitted.
        $fields = $DB->get_records('callforpaper_fields', array('callforpaperid' => $callforpaperobject->id));
        $processeddata = callforpaper_process_submission($callforpaperobject, $fields, $callforpaperrecord);

        // Format notifications.
        if (!empty($processeddata->fieldnotifications)) {
            foreach ($processeddata->fieldnotifications as $field => $notififications) {
                foreach ($notififications as $notif) {
                    $fieldnotifications[] = [
                        'fieldname' => $field,
                        'notification' => $notif,
                    ];
                }
            }
        }

        if ($processeddata->validated) {
            // Now update the fields contents.
            callforpaper_update_record_fields_contents($callforpaperobject, $record, $context, $callforpaperrecord, $processeddata);
            $updated = true;
        }

        $result = array(
            'updated' => $updated,
            'generalnotifications' => $processeddata->generalnotifications,
            'fieldnotifications' => $fieldnotifications,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return \core_external\external_description
     * @since Moodle 3.3
     */
    public static function update_entry_returns() {
        return new external_single_structure(
            array(
                'updated' => new external_value(PARAM_BOOL, 'True if the entry was successfully updated, false other wise.'),
                'generalnotifications' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'General notifications')
                ),
                'fieldnotifications' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'fieldname' => new external_value(PARAM_TEXT, 'The field name.'),
                            'notification' => new external_value(PARAM_RAW, 'The notification for the field.'),
                        )
                    )
                ),
                'warnings' => new external_warnings()
            )
        );
    }
}
