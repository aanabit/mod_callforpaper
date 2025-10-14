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

namespace mod_callforpaper\output;

use templatable;
use renderable;
use core_tag_tag;
use mod_callforpaper\manager;

/**
 * Renderable class for template editor tools.
 *
 * @package    mod_callforpaper
 * @copyright  2022 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_editor_tools implements templatable, renderable {

    /** @var manager manager instance. */
    private $manager;

    /** @var string the template name. */
    private $templatename;

    /**
     * The class constructor.
     *
     * @param manager $manager the activity instance manager
     * @param string $templatename the template to edit
     */
    public function __construct(manager $manager, string $templatename) {
        $this->manager = $manager;
        $this->templatename = $templatename;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $tools = [
            $this->get_field_tags($this->templatename),
            $this->get_field_info_tags($this->templatename),
            $this->get_action_tags($this->templatename),
            $this->get_other_tags($this->templatename),
        ];
        $tools = array_filter($tools, static function ($value) {
            return !empty($value['tags']);
        });
        return [
            'toolshelp' => $output->help_icon('availabletags', 'callforpaper'),
            'hastools' => !empty($tools),
            'tools' => array_values($tools),
        ];
    }

    /**
     * Return the field template tags.
     *
     * @param string $templatename the template name
     * @return array|null array of tags.
     */
    protected function get_field_tags(string $templatename): array {
        $name = get_string('fields', 'callforpaper');
        if ($templatename == 'csstemplate' || $templatename == 'jstemplate') {
            return $this->get_optgroup_data($name, []);
        }
        $taglist = [];
        $fields = $this->manager->get_fields();
        foreach ($fields as $field) {
            if ($field->type === 'unknown') {
                continue;
            }
            $fieldname = $field->get_name();
            $taglist["[[$fieldname]]"] = $fieldname;
        }
        $taglist['##otherfields##'] = get_string('otherfields', 'callforpaper');
        return $this->get_optgroup_data($name, $taglist);
    }

    /**
     * Return the field information template tags.
     *
     * @param string $templatename the template name
     * @return array|null array of tags.
     */
    protected function get_field_info_tags(string $templatename): array {
        $name = get_string('fieldsinformationtags', 'callforpaper');
        $taglist = [];
        $fields = $this->manager->get_fields();
        foreach ($fields as $field) {
            if ($field->type === 'unknown') {
                continue;
            }
            $fieldname = $field->get_name();
            if ($templatename == 'addtemplate') {
                $taglist["[[$fieldname#id]]"] = get_string('fieldtagid', 'mod_callforpaper', $fieldname);
            }
            $taglist["[[$fieldname#name]]"] = get_string('fieldtagname', 'mod_callforpaper', $fieldname);
            $taglist["[[$fieldname#description]]"] = get_string('fieldtagdescription', 'mod_callforpaper', $fieldname);
        }
        return $this->get_optgroup_data($name, $taglist);
    }

    /**
     * Return the field action tags.
     *
     * @param string $templatename the template name
     * @return array|null array of tags.
     */
    protected function get_action_tags(string $templatename): array {
        $name = get_string('actions');
        if ($templatename == 'addtemplate' || $templatename == 'asearchtemplate') {
            return $this->get_optgroup_data($name, []);
        }
        $taglist = [
            '##actionsmenu##' => get_string('actionsmenu', 'callforpaper'),
            '##edit##' => get_string('edit', 'callforpaper'),
            '##delete##' => get_string('delete', 'callforpaper'),
            '##approve##' => get_string('approve', 'callforpaper'),
            '##disapprove##' => get_string('disapprove', 'callforpaper'),
        ];
        if ($templatename != 'rsstemplate') {
            $taglist['##export##'] = get_string('export', 'callforpaper');
        }
        if ($templatename != 'singletemplate') {
            $taglist['##more##'] = get_string('more', 'callforpaper');
            $taglist['##moreurl##'] = get_string('moreurl', 'callforpaper');
            $taglist['##delcheck##'] = get_string('delcheck', 'callforpaper');
        }
        return $this->get_optgroup_data($name, $taglist);
    }

    /**
     * Return the available other tags
     *
     * @param string $templatename the template name
     * @return array associative array of tags => tag name
     */
    protected function get_other_tags(string $templatename): array {
        $name = get_string('other', 'callforpaper');
        $taglist = [];
        if ($templatename == 'asearchtemplate') {
            $taglist['##firstname##'] = get_string('firstname');
            $taglist['##lastname##'] = get_string('lastname');
            return $this->get_optgroup_data($name, $taglist);
        }
        if (core_tag_tag::is_enabled('mod_callforpaper', 'callforpaper_records')) {
            $taglist['##tags##'] = get_string('tags');
        }
        if ($templatename == 'addtemplate') {
            return $this->get_optgroup_data($name, $taglist);
        }
        $taglist['##timeadded##'] = get_string('timeadded', 'callforpaper');
        $taglist['##timemodified##'] = get_string('timemodified', 'callforpaper');
        $taglist['##user##'] = get_string('user');
        $taglist['##userpicture##'] = get_string('userpic');
        $taglist['##approvalstatus##'] = get_string('approvalstatus', 'callforpaper');
        $taglist['##id##'] = get_string('id', 'callforpaper');

        if ($templatename == 'singletemplate') {
            return $this->get_optgroup_data($name, $taglist);
        }

        $taglist['##comments##'] = get_string('comments', 'callforpaper');

        return $this->get_optgroup_data($name, $taglist);
    }

    /**
     * Generate a valid optgroup data.
     *
     * @param string $name the optgroup name
     * @param array $taglist the indexed array of taglists ($tag => $tagname)
     * @return array of optgroup data
     */
    protected function get_optgroup_data(string $name, array $taglist): array {
        $tags = [];
        foreach ($taglist as $tag => $tagname) {
            $tags[] = [
                'tag' => "$tag",
                'tagname' => $tagname . ' - ' . $tag,
            ];
        }
        return [
            'name' => $name,
            'tags' => $tags,
        ];
    }
}
