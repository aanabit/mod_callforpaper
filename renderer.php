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
 * Call for paper activity renderer.
 *
 * @copyright 2010 Sam Hemelryk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_callforpaper
 */

use mod_callforpaper\manager;

defined('MOODLE_INTERNAL') || die();

class mod_callforpaper_renderer extends plugin_renderer_base {
    /**
     * Importing a preset on a callforpaper module.
     *
     * @param stdClass $callforpapermodule  Call for paper module to import to.
     * @param \mod_callforpaper\local\importer\preset_importer $importer Importer instance to use for the importing.
     *
     * @return string
     */
    public function importing_preset(stdClass $callforpapermodule, \mod_callforpaper\local\importer\preset_importer $importer): string {

        $strwarning = get_string('mappingwarning', 'callforpaper');

        $params = $importer->settings;
        $newfields = $params->importfields;
        $currentfields = $params->currentfields;

        $html = html_writer::start_tag('div', ['class' => 'presetmapping']);
        $html .= html_writer::start_tag('form', ['method' => 'post', 'action' => '']);
        $html .= html_writer::start_tag('div');
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'finishimport']);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'd', 'value' => $callforpapermodule->id]);

        $inputselector = $importer->get_preset_selector();
        $html .= html_writer::empty_tag(
                'input',
                ['type' => 'hidden', 'name' => $inputselector['name'], 'value' => $inputselector['value']]
        );

        if (!empty($newfields)) {
            $table = new html_table();
            $table->data = array();

            foreach ($newfields as $nid => $newfield) {
                $row = array();
                $row[0] = html_writer::tag('label', $newfield->name, array('for'=>'id_'.$newfield->name));
                $attrs = ['name' => 'field_' . $nid, 'id' => 'id_' . $newfield->name, 'class' => 'form-select'];
                $row[1] = html_writer::start_tag('select', $attrs);

                $selected = false;
                foreach ($currentfields as $cid => $currentfield) {
                    if ($currentfield->type != $newfield->type) {
                        continue;
                    }
                    if ($currentfield->name == $newfield->name) {
                        $row[1] .= html_writer::tag(
                            'option',
                            get_string('mapexistingfield', 'callforpaper', $currentfield->name),
                            ['value' => $cid, 'selected' => 'selected']
                        );
                        $selected = true;
                    } else {
                        $row[1] .= html_writer::tag(
                            'option',
                            get_string('mapexistingfield', 'callforpaper', $currentfield->name),
                            ['value' => $cid]
                        );
                    }
                }

                if ($selected) {
                    $row[1] .= html_writer::tag('option', get_string('mapnewfield', 'callforpaper'), array('value'=>'-1'));
                } else {
                    $row[1] .= html_writer::tag('option', get_string('mapnewfield', 'callforpaper'), array('value'=>'-1', 'selected'=>'selected'));
                }

                $row[1] .= html_writer::end_tag('select');
                $table->data[] = $row;
            }
            $html .= html_writer::table($table);
            $html .= html_writer::tag('p', $strwarning);
        } else {
            $html .= $this->output->notification(get_string('nodefinedfields', 'callforpaper'));
        }

        $html .= html_writer::start_tag('div', array('class'=>'overwritesettings'));
        $attrs = ['type' => 'checkbox', 'name' => 'overwritesettings', 'id' => 'overwritesettings', 'class' => 'me-2'];
        $html .= html_writer::empty_tag('input', $attrs);
        $html .= html_writer::tag('label', get_string('overwritesettings', 'callforpaper'), ['for' => 'overwritesettings']);
        $html .= html_writer::end_tag('div');

        $actionbuttons = html_writer::start_div();
        $cancelurl = new moodle_url('/mod/callforpaper/field.php', ['d' => $callforpapermodule->id]);
        $actionbuttons .= html_writer::tag('a', get_string('cancel') , [
            'href' => $cancelurl->out(false),
            'class' => 'btn btn-secondary mx-1',
            'role' => 'button',
        ]);
        $actionbuttons .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary mx-1',
            'value' => get_string('continue'),
        ]);
        $actionbuttons .= html_writer::end_div();

        $stickyfooter = new core\output\sticky_footer($actionbuttons);
        $html .= $this->render($stickyfooter);

        $html .= html_writer::end_tag('div');
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_tag('div');

        return $html;
    }

    /**
     * Renders the action bar for the field page.
     *
     * @param \mod_callforpaper\output\fields_action_bar $actionbar
     * @return string The HTML output
     */
    public function render_fields_action_bar(\mod_callforpaper\output\fields_action_bar $actionbar): string {
        $callforpaper = $actionbar->export_for_template($this);
        return $this->render_from_template('mod_callforpaper/action_bar', $callforpaper);
    }

    /**
     * Renders the action bar for the view page.
     *
     * @param \mod_callforpaper\output\view_action_bar $actionbar
     * @return string The HTML output
     */
    public function render_view_action_bar(\mod_callforpaper\output\view_action_bar $actionbar): string {
        $callforpaper = $actionbar->export_for_template($this);
        return $this->render_from_template('mod_callforpaper/view_action_bar', $callforpaper);
    }

    /**
     * Renders the action bar for the template page.
     *
     * @param \mod_callforpaper\output\templates_action_bar $actionbar
     * @return string The HTML output
     */
    public function render_templates_action_bar(\mod_callforpaper\output\templates_action_bar $actionbar): string {
        $callforpaper = $actionbar->export_for_template($this);
        return $this->render_from_template('mod_callforpaper/templates_action_bar', $callforpaper);
    }

    /**
     * Renders the action bar for the preset page.
     *
     * @param \mod_callforpaper\output\presets_action_bar $actionbar
     * @return string The HTML output
     */
    public function render_presets_action_bar(\mod_callforpaper\output\presets_action_bar $actionbar): string {
        $callforpaper = $actionbar->export_for_template($this);
        return $this->render_from_template('mod_callforpaper/presets_action_bar', $callforpaper);
    }

    /**
     * Renders the presets table in the preset page.
     *
     * @param \mod_callforpaper\output\presets $presets
     * @return string The HTML output
     */
    public function render_presets(\mod_callforpaper\output\presets $presets): string {
        $callforpaper = $presets->export_for_template($this);
        return $this->render_from_template('mod_callforpaper/presets', $callforpaper);
    }

    /**
     * Renders the default template.
     *
     * @param \mod_callforpaper\output\defaulttemplate $template
     * @return string The HTML output
     */
    public function render_defaulttemplate(\mod_callforpaper\output\defaulttemplate $template): string {
        $callforpaper = $template->export_for_template($this);
        return $this->render_from_template($template->get_templatename(), $callforpaper);
    }

    /**
     * Renders the action bar for the zero state (no fields created) page.
     *
     * @param \mod_callforpaper\manager $manager The manager instance.
     *
     * @return string The HTML output
     */
    public function render_callforpaper_zero_state(\mod_callforpaper\manager $manager): string {
        $actionbar = new \mod_callforpaper\output\zero_state_action_bar($manager);
        $callforpaper = $actionbar->export_for_template($this);
        if (empty($callforpaper)) {
            // No actions for the user.
            $callforpaper['title'] = get_string('activitynotready');
            $callforpaper['intro'] = get_string('comebacklater');
            $callforpaper['noitemsimgurl'] = $this->output->image_url('noentries_zero_state', 'mod_callforpaper')->out();
        } else {
            $callforpaper['title'] = get_string('startbuilding', 'mod_callforpaper');
            $callforpaper['intro'] = get_string('createactivity', 'mod_callforpaper');
            $callforpaper['noitemsimgurl'] = $this->output->image_url('view_zero_state', 'mod_callforpaper')->out();
        }

        return $this->render_from_template('mod_callforpaper/zero_state', $callforpaper);
    }

    /**
     * Renders the action bar for an empty callforpaper view page.
     *
     * @param \mod_callforpaper\manager $manager The manager instance.
     *
     * @return string The HTML output
     */
    public function render_empty_callforpaper(\mod_callforpaper\manager $manager): string {
        $actionbar = new \mod_callforpaper\output\empty_callforpaper_action_bar($manager);
        $callforpaper = $actionbar->export_for_template($this);
        $callforpaper['noitemsimgurl'] = $this->output->image_url('view_zero_state', 'mod_callforpaper')->out();

        return $this->render_from_template('mod_callforpaper/view_noentries', $callforpaper);
    }

    /**
     * Renders the action bar for the zero state (no fields created) page.
     *
     * @param \mod_callforpaper\manager $manager The manager instance.
     *
     * @return string The HTML output
     */
    public function render_fields_zero_state(\mod_callforpaper\manager $manager): string {
        $callforpaper = [
            'noitemsimgurl' => $this->output->image_url('fields_zero_state', 'mod_callforpaper')->out(),
            'title' => get_string('nofields', 'mod_callforpaper'),
            'intro' => get_string('createfields', 'mod_callforpaper'),
            ];
        if ($manager->can_manage_templates()) {
            $actionbar = new \mod_callforpaper\output\action_bar($manager->get_instance()->id, $this->page->url);
            $createfieldbutton = $actionbar->get_create_fields();
            $callforpaper['createfieldbutton'] = $createfieldbutton->export_for_template($this);
        }

        return $this->render_from_template('mod_callforpaper/zero_state', $callforpaper);
    }

    /**
     * Renders the action bar for the templates zero state (no fields created) page.
     *
     * @param \mod_callforpaper\manager $manager The manager instance.
     *
     * @return string The HTML output
     */
    public function render_templates_zero_state(\mod_callforpaper\manager $manager): string {
        $actionbar = new \mod_callforpaper\output\zero_state_action_bar($manager);
        $callforpaper = $actionbar->export_for_template($this);
        $callforpaper['title'] = get_string('notemplates', 'mod_callforpaper');
        $callforpaper['intro'] = get_string('createtemplates', 'mod_callforpaper');
        $callforpaper['noitemsimgurl'] = $this->output->image_url('templates_zero_state', 'mod_callforpaper')->out();

        return $this->render_from_template('mod_callforpaper/zero_state', $callforpaper);
    }
}
