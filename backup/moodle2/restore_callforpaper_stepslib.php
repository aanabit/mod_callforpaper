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
 * @package    mod_callforpaper
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_callforpaper_activity_task
 */

/**
 * Structure step to restore one callforpaper activity
 */
class restore_callforpaper_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('callforpaper', '/activity/callforpaper');
        $paths[] = new restore_path_element('callforpaper_field', '/activity/callforpaper/fields/field');
        if ($userinfo) {
            $paths[] = new restore_path_element('callforpaper_record', '/activity/callforpaper/records/record');
            $paths[] = new restore_path_element('callforpaper_content', '/activity/callforpaper/records/record/contents/content');
            $paths[] = new restore_path_element('callforpaper_rating', '/activity/callforpaper/records/record/ratings/rating');
            $paths[] = new restore_path_element('callforpaper_record_tag', '/activity/callforpaper/recordstags/tag');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_callforpaper($callforpaper) {
        global $DB;

        $callforpaper = (object)$callforpaper;
        $oldid = $callforpaper->id;
        $callforpaper->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $callforpaper->timeavailablefrom = $this->apply_date_offset($callforpaper->timeavailablefrom);
        $callforpaper->timeavailableto = $this->apply_date_offset($callforpaper->timeavailableto);
        $callforpaper->timeviewfrom = $this->apply_date_offset($callforpaper->timeviewfrom);
        $callforpaper->timeviewto = $this->apply_date_offset($callforpaper->timeviewto);
        $callforpaper->assesstimestart = $this->apply_date_offset($callforpaper->assesstimestart);
        $callforpaper->assesstimefinish = $this->apply_date_offset($callforpaper->assesstimefinish);

        if ($callforpaper->scale < 0) { // scale found, get mapping
            $callforpaper->scale = -($this->get_mappingid('scale', abs($callforpaper->scale)));
        }

        // Some old backups can arrive with callforpaper->notification = null (MDL-24470)
        // convert them to proper column default (zero)
        if (is_null($callforpaper->notification)) {
            $callforpaper->notification = 0;
        }

        // insert the callforpaper record
        $newitemid = $DB->insert_record('callforpaper', $callforpaper);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_callforpaper_field($callforpaper) {
        global $DB;

        $callforpaper = (object)$callforpaper;
        $oldid = $callforpaper->id;

        $callforpaper->callforpaperid = $this->get_new_parentid('callforpaper');
        $callforpaper->type = clean_param($callforpaper->type, PARAM_ALPHA);

        // insert the callforpaper_fields record
        $newitemid = $DB->insert_record('callforpaper_fields', $callforpaper);
        $this->set_mapping('callforpaper_field', $oldid, $newitemid, false); // no files associated
    }

    protected function process_callforpaper_record($callforpaper) {
        global $DB;

        $callforpaper = (object)$callforpaper;
        $oldid = $callforpaper->id;

        $callforpaper->userid = $this->get_mappingid('user', $callforpaper->userid);
        $callforpaper->groupid = $this->get_mappingid('group', $callforpaper->groupid);
        $callforpaper->callforpaperid = $this->get_new_parentid('callforpaper');

        // insert the callforpaper_records record
        $newitemid = $DB->insert_record('callforpaper_records', $callforpaper);
        $this->set_mapping('callforpaper_record', $oldid, $newitemid, false); // no files associated
    }

    protected function process_callforpaper_content($callforpaper) {
        global $DB;

        $callforpaper = (object)$callforpaper;
        $oldid = $callforpaper->id;

        $callforpaper->fieldid = $this->get_mappingid('callforpaper_field', $callforpaper->fieldid);
        $callforpaper->recordid = $this->get_new_parentid('callforpaper_record');

        // insert the callforpaper_content record
        $newitemid = $DB->insert_record('callforpaper_content', $callforpaper);
        $this->set_mapping('callforpaper_content', $oldid, $newitemid, true); // files by this itemname
    }

    /**
     * Add tags to restored records.
     *
     * @param stdClass $callforpaper Tag
     */
    protected function process_callforpaper_record_tag($callforpaper) {
        $callforpaper = (object)$callforpaper;

        if (!core_tag_tag::is_enabled('mod_callforpaper', 'callforpaper_records')) { // Tags disabled in server, nothing to process.
            return;
        }

        if (!$itemid = $this->get_mappingid('callforpaper_record', $callforpaper->itemid)) {
            // Some orphaned tag, we could not find the callforpaper record for it - ignore.
            return;
        }

        $tag = $callforpaper->rawname;
        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_callforpaper', 'callforpaper_records', $itemid, $context, $tag);
    }

    protected function process_callforpaper_rating($callforpaper) {
        global $DB;

        $callforpaper = (object)$callforpaper;

        // Cannot use ratings API, cause, it's missing the ability to specify times (modified/created)
        $callforpaper->contextid = $this->task->get_contextid();
        $callforpaper->itemid    = $this->get_new_parentid('callforpaper_record');
        if ($callforpaper->scaleid < 0) { // scale found, get mapping
            $callforpaper->scaleid = -($this->get_mappingid('scale', abs($callforpaper->scaleid)));
        }
        $callforpaper->rating = $callforpaper->value;
        $callforpaper->userid = $this->get_mappingid('user', $callforpaper->userid);

        // We need to check that component and ratingarea are both set here.
        if (empty($callforpaper->component)) {
            $callforpaper->component = 'mod_callforpaper';
        }
        if (empty($callforpaper->ratingarea)) {
            $callforpaper->ratingarea = 'entry';
        }

        $newitemid = $DB->insert_record('rating', $callforpaper);
    }

    protected function after_execute() {
        global $DB;
        // Add callforpaper related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_callforpaper', 'intro', null);
        // Add content related files, matching by itemname (callforpaper_content)
        $this->add_related_files('mod_callforpaper', 'content', 'callforpaper_content');
        // Adjust the callforpaper->defaultsort field
        if ($defaultsort = $DB->get_field('callforpaper', 'defaultsort', array('id' => $this->get_new_parentid('callforpaper')))) {
            if ($defaultsort = $this->get_mappingid('callforpaper_field', $defaultsort)) {
                $DB->set_field('callforpaper', 'defaultsort', $defaultsort, array('id' => $this->get_new_parentid('callforpaper')));
            }
        }
    }
}
