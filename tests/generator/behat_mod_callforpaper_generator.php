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
 * Behat data generator for mod_callforpaper.
 *
 * @package   mod_callforpaper
 * @category  test
 * @copyright 2022 Noel De Martin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_callforpaper_generator extends behat_generator_base {

    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'entries' => [
                'singular' => 'entry',
                'datagenerator' => 'entry',
                'required' => ['callforpaper'],
                'switchids' => ['callforpaper' => 'callforpaperid', 'user' => 'userid', 'group' => 'groupid'],
            ],
            'fields' => [
                'singular' => 'field',
                'datagenerator' => 'field',
                'required' => ['callforpaper', 'type', 'name'],
                'switchids' => ['callforpaper' => 'callforpaperid'],
            ],
            'templates' => [
                'singular' => 'template',
                'datagenerator' => 'template',
                'required' => ['callforpaper', 'name'],
                'switchids' => ['callforpaper' => 'callforpaperid'],
            ],
            'presets' => [
                'singular' => 'preset',
                'datagenerator' => 'preset',
                'required' => ['callforpaper', 'name'],
                'switchids' => ['callforpaper' => 'callforpaperid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     * Get the callforpaper id using an activity idnumber.
     *
     * @param string $idnumber
     * @return int The callforpaper id
     */
    protected function get_callforpaper_id(string $idnumber): int {
        $cm = $this->get_cm_by_activity_name('callforpaper', $idnumber);

        return $cm->instance;
    }

    /**
     * Add an entry.
     *
     * @param array $callforpaper Entry data.
     */
    public function process_entry(array $callforpaper): void {
        global $DB;

        $callforpaperrecord = $DB->get_record('callforpaper', ['id' => $callforpaper['callforpaperid']], '*', MUST_EXIST);

        unset($callforpaper['callforpaperid']);
        $userid = 0;
        if (array_key_exists('userid', $callforpaper)) {
            $userid = $callforpaper['userid'];
            unset($callforpaper['userid']);
        }
        if (array_key_exists('groupid', $callforpaper)) {
            $groupid = $callforpaper['groupid'];
            unset($callforpaper['groupid']);
        } else {
            $groupid = 0;
        }
        $options = null;
        if (array_key_exists('approved', $callforpaper)) {
            $options = ['approved' => $callforpaper['approved']];
            unset($callforpaper['approved']);
        }

        $callforpaper = array_reduce(array_keys($callforpaper), function ($fields, $fieldname) use ($callforpaper, $callforpaperrecord) {
            global $DB;

            $field = $DB->get_record('callforpaper_fields', ['name' => $fieldname, 'callforpaperid' => $callforpaperrecord->id], 'id', MUST_EXIST);

            $fields[$field->id] = $callforpaper[$fieldname];

            return $fields;
        }, []);

        $this->get_callforpaper_generator()->create_entry($callforpaperrecord, $callforpaper, $groupid, [], $options, $userid);
    }

    /**
     * Add a field.
     *
     * @param array $callforpaperField data.
     */
    public function process_field(array $callforpaper): void {
        global $DB;

        $callforpaperreccord = $DB->get_record('callforpaper', ['id' => $callforpaper['callforpaperid']], '*', MUST_EXIST);

        unset($callforpaper['callforpaperid']);

        $this->get_callforpaper_generator()->create_field((object) $callforpaper, $callforpaperreccord);
    }

    /**
     * Add a template.
     *
     * @param array $callforpaper Template data.
     */
    public function process_template(array $callforpaper): void {
        global $DB;

        $callforpaperrecord = $DB->get_record('callforpaper', ['id' => $callforpaper['callforpaperid']], '*', MUST_EXIST);

        if (empty($callforpaper['content'])) {
            callforpaper_generate_default_template($callforpaperrecord, $callforpaper['name']);
        } else {
            $newdata = new stdClass();
            $newdata->id = $callforpaperrecord->id;
            $newdata->{$callforpaper['name']} = $callforpaper['content'];
            $DB->update_record('callforpaper', $newdata);
        }
    }

    /**
     * Saves a preset.
     *
     * @param array $callforpaperPreset data.
     */
    protected function process_preset(array $callforpaper): void {
        global $DB;

        $instance = $DB->get_record('callforpaper', ['id' => $callforpaper['callforpaperid']], '*', MUST_EXIST);

        $this->get_callforpaper_generator()->create_preset($instance, (object) $callforpaper);
    }

    /**
     * Get the module callforpaper generator.
     *
     * @return mod_callforpaper_generator Call for paper callforpaper generator.
     */
    protected function get_callforpaper_generator(): mod_callforpaper_generator {
        return $this->componentdatagenerator;
    }

}
