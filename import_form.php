<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/csvlib.class.php');

class mod_callforpaper_import_form extends moodleform {

    function definition() {
        $mform =& $this->_form;

        $callforpaperid = $this->_customdata['callforpaperid'];
        $backtourl = $this->_customdata['backtourl'];

        $mform->addElement('filepicker', 'recordsfile', get_string('csvfile', 'callforpaper'),
            null, ['accepted_types' => ['application/zip', 'text/csv']]);

        $delimiters = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'fielddelimiter', get_string('fielddelimiter', 'callforpaper'), $delimiters);
        $mform->setDefault('fielddelimiter', 'comma');

        $mform->addElement('text', 'fieldenclosure', get_string('fieldenclosure', 'callforpaper'));
        $mform->setType('fieldenclosure', PARAM_CLEANHTML);

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('fileencoding', 'mod_callforpaper'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        // Call for paper activity ID.
        $mform->addElement('hidden', 'd');
        $mform->setType('d', PARAM_INT);
        $mform->setDefault('d', $callforpaperid);

        // Back to URL.
        $mform->addElement('hidden', 'backto');
        $mform->setType('backto', PARAM_LOCALURL);
        $mform->setDefault('backto', $backtourl);

        $this->add_action_buttons(true, get_string('submit'));
    }
}
