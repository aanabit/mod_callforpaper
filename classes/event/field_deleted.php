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
 * The mod_callforpaper field deleted event.
 *
 * @package    mod_callforpaper
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_callforpaper\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_callforpaper field deleted event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - string fieldname: the name of the field.
 *      - int callforpaperid: the id of the callforpaper activity.
 * }
 *
 * @package    mod_callforpaper
 * @since      Moodle 2.7
 * @copyright  2014 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_deleted extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'callforpaper_fields';
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventfielddeleted', 'mod_callforpaper');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' deleted the field with id '$this->objectid' in the callforpaper activity " .
            "with course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/callforpaper/field.php', array('d' => $this->other['callforpaperid']));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception when validation does not pass.
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['fieldname'])) {
            throw new \coding_exception('The \'fieldname\' value must be set in other.');
        }

        if (!isset($this->other['callforpaperid'])) {
            throw new \coding_exception('The \'callforpaperid\' value must be set in other.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'callforpaper_fields', 'restore' => 'callforpaper_field');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['callforpaperid'] = array('db' => 'callforpaper', 'restore' => 'callforpaper');

        return $othermapped;
    }
}
