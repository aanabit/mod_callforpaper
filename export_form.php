<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden!');
}
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/csvlib.class.php');

class mod_callforpaper_export_form extends moodleform {
    var $_callforpaperfields = array();
    var $_cm;
    var $_callforpaper;

     // @param string $url: the url to post to
     // @param array $callforpaperfields: objects in this callforpaper
    public function __construct($url, $callforpaperfields, $cm, $callforpaper) {
        $this->_callforpaperfields = $callforpaperfields;
        $this->_cm = $cm;
        $this->_callforpaper = $callforpaper;
        parent::__construct($url);
    }

    function definition() {
        $mform =& $this->_form;
        $mform->addElement('header', 'exportformat', get_string('chooseexportformat', 'callforpaper'));

        $optionattrs = ['class' => 'mt-1 mb-1'];

        // Export format type radio group.
        $typesarray = array();
        $typesarray[] = $mform->createElement('radio', 'exporttype', null, get_string('csvwithselecteddelimiter', 'callforpaper'), 'csv',
            $optionattrs);
        // Temporarily commenting out Excel export option. See MDL-19864.
        //$typesarray[] = $mform->createElement('radio', 'exporttype', null, get_string('excel', 'callforpaper'), 'xls');
        $typesarray[] = $mform->createElement('radio', 'exporttype', null, get_string('ods', 'callforpaper'), 'ods', $optionattrs);
        $mform->addGroup($typesarray, 'exportar', get_string('exportformat', 'callforpaper'), null, false);
        $mform->addRule('exportar', null, 'required');
        $mform->setDefault('exporttype', 'csv');

        // CSV delimiter list.
        $choices = csv_import_reader::get_delimiter_list();
        $key = array_search(';', $choices);
        if ($key !== false) {
            // Array $choices contains the semicolon -> drop it (because its encrypted form also contains a semicolon):
            unset($choices[$key]);
        }
        $mform->addElement('select', 'delimiter_name', get_string('fielddelimiter', 'callforpaper'), $choices);
        $mform->hideIf('delimiter_name', 'exporttype', 'neq', 'csv');
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        // Fields to be exported.
        $mform->addElement('header', 'exportfieldsheader', get_string('chooseexportfields', 'callforpaper'));
        $mform->setExpanded('exportfieldsheader');
        $numfieldsthatcanbeselected = 0;
        $exportfields = [];
        $unsupportedfields = [];
        foreach ($this->_callforpaperfields as $field) {
            $label = get_string('fieldnametype', 'callforpaper', (object)['name' => s($field->field->name), 'type' => $field->name()]);
            if ($field->text_export_supported()) {
                $numfieldsthatcanbeselected++;
                $exportfields[] = $mform->createElement('advcheckbox', 'field_' . $field->field->id, '', $label,
                    array_merge(['group' => 1], $optionattrs));
                $mform->setDefault('field_' . $field->field->id, 1);
            } else {
                $unsupportedfields[] = $label;
            }
        }
        $mform->addGroup($exportfields, 'exportfields', get_string('selectfields', 'callforpaper'), ['<br>'], false);

        if ($numfieldsthatcanbeselected > 1) {
            $this->add_checkbox_controller(1, null, null, 1);
        }

        // List fields that cannot be exported.
        if (!empty($unsupportedfields)) {
            $unsupportedfieldslist = html_writer::tag('p', get_string('unsupportedfieldslist', 'callforpaper'), ['class' => 'mt-1']);
            $unsupportedfieldslist .= html_writer::alist($unsupportedfields);
            $mform->addElement('static', 'unsupportedfields', get_string('unsupportedfields', 'callforpaper'), $unsupportedfieldslist);
        }

        // Export options.
        $mform->addElement('header', 'exportoptionsheader', get_string('exportoptions', 'callforpaper'));
        $mform->setExpanded('exportoptionsheader');
        $exportoptions = [];
        if (core_tag_tag::is_enabled('mod_callforpaper', 'callforpaper_records')) {
            $exportoptions[] = $mform->createElement('checkbox', 'exporttags', get_string('includetags', 'callforpaper'), '', $optionattrs);
            $mform->setDefault('exporttags', 1);
        }
        $context = context_module::instance($this->_cm->id);
        if (has_capability('mod/callforpaper:exportuserinfo', $context)) {
            $exportoptions[] = $mform->createElement('checkbox', 'exportuser', get_string('includeuserdetails', 'callforpaper'), '',
                $optionattrs);
        }
        $exportoptions[] = $mform->createElement('checkbox', 'exporttime', get_string('includetime', 'callforpaper'), '', $optionattrs);
        if ($this->_callforpaper->approval) {
            $exportoptions[] = $mform->createElement('checkbox', 'exportapproval', get_string('includeapproval', 'callforpaper'), '',
                $optionattrs);
        }
        $exportoptions[] = $mform->createElement('checkbox', 'includefiles', get_string('includefiles', 'callforpaper'), '', $optionattrs);
        $mform->setDefault('includefiles', 1);
        $mform->addGroup($exportoptions, 'exportoptions', get_string('selectexportoptions', 'callforpaper'), ['<br>'], false);

        $this->add_action_buttons(true, get_string('exportentries', 'callforpaper'));
    }

}


