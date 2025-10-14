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
 * Call for paper external functions and service definitions.
 *
 * @package    mod_callforpaper
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

$functions = array(

    'mod_callforpaper_get_callforpapers_by_courses' => array(
        'classname' => 'mod_callforpaper_external',
        'methodname' => 'get_callforpapers_by_courses',
        'description' => 'Returns a list of callforpaper instances in a provided set of courses, if
            no courses are provided then all the callforpaper instances the user has access to will be returned.',
        'type' => 'read',
        'capabilities' => 'mod/callforpaper:viewentry',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_view_callforpaper' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'view_callforpaper',
        'description'   => 'Simulate the view.php web interface callforpaper: trigger events, completion, etc...',
        'type'          => 'write',
        'capabilities'  => 'mod/callforpaper:viewentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_get_callforpaper_access_information' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'get_callforpaper_access_information',
        'description'   => 'Return access information for a given callforpaper.',
        'type'          => 'read',
        'capabilities'  => 'mod/callforpaper:viewentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_get_entries' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'get_entries',
        'description'   => 'Return the complete list of entries of the given callforpaper.',
        'type'          => 'read',
        'capabilities'  => 'mod/callforpaper:viewentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_get_entry' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'get_entry',
        'description'   => 'Return one entry record from the callforpaper, including contents optionally.',
        'type'          => 'read',
        'capabilities'  => 'mod/callforpaper:viewentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_get_fields' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'get_fields',
        'description'   => 'Return the list of configured fields for the given callforpaper.',
        'type'          => 'read',
        'capabilities'  => 'mod/callforpaper:viewentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_search_entries' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'search_entries',
        'description'   => 'Search for entries in the given callforpaper.',
        'type'          => 'read',
        'capabilities'  => 'mod/callforpaper:viewentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_approve_entry' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'approve_entry',
        'description'   => 'Approves or unapproves an entry.',
        'type'          => 'write',
        'capabilities'  => 'mod/callforpaper:approve',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_delete_entry' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'delete_entry',
        'description'   => 'Deletes an entry.',
        'type'          => 'write',
        'capabilities'  => 'mod/callforpaper:manageentries',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_add_entry' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'add_entry',
        'description'   => 'Adds a new entry.',
        'type'          => 'write',
        'capabilities'  => 'mod/callforpaper:writeentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_update_entry' => array(
        'classname'     => 'mod_callforpaper_external',
        'methodname'    => 'update_entry',
        'description'   => 'Updates an existing entry.',
        'type'          => 'write',
        'capabilities'  => 'mod/callforpaper:writeentry',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
    'mod_callforpaper_delete_saved_preset' => array(
        'classname'     => 'mod_callforpaper\external\delete_saved_preset',
        'description'   => 'Delete site user preset.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/callforpaper:manageuserpresets',
    ),
    'mod_callforpaper_get_mapping_information' => array(
        'classname'     => 'mod_callforpaper\external\get_mapping_information',
        'description'   => 'Get importing information',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/callforpaper:managetemplates',
    ),
);
