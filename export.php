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
 * This file is part of the Call for paper module for Moodle
 *
 * @copyright 2005 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_callforpaper
 */

require_once('../../config.php');
require_once('lib.php');
require_once('export_form.php');

// callforpaper ID
$d = required_param('d', PARAM_INT);
$exportuser = optional_param('exportuser', false, PARAM_BOOL); // Flag for exporting user details
$exporttime = optional_param('exporttime', false, PARAM_BOOL); // Flag for exporting date/time information
$exportapproval = optional_param('exportapproval', false, PARAM_BOOL); // Flag for exporting user details
$tags = optional_param('exporttags', false, PARAM_BOOL); // Flag for exporting user details.
$redirectbackto = optional_param('backto', '', PARAM_LOCALURL); // The location to redirect back to.

$url = new moodle_url('/mod/callforpaper/export.php', array('d' => $d));
$PAGE->set_url($url);

if (! $callforpaper = $DB->get_record('callforpaper', array('id'=>$d))) {
    throw new \moodle_exception('wrongcallforpaperid', 'callforpaper');
}

if (! $cm = get_coursemodule_from_instance('callforpaper', $callforpaper->id, $callforpaper->course)) {
    throw new \moodle_exception('invalidcoursemodule');
}

if(! $course = $DB->get_record('course', array('id'=>$cm->course))) {
    throw new \moodle_exception('invalidcourseid');
}

// fill in missing properties needed for updating of instance
$callforpaper->course     = $cm->course;
$callforpaper->cmidnumber = $cm->idnumber;
$callforpaper->instance   = $cm->instance;

$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability(CALLFORPAPER_CAP_EXPORT, $context);

// get fields for this callforpaper
$fieldrecords = $DB->get_records('callforpaper_fields', array('callforpaperid'=>$callforpaper->id), 'id');

if(empty($fieldrecords)) {
    if (has_capability('mod/callforpaper:managetemplates', $context)) {
        redirect($CFG->wwwroot.'/mod/callforpaper/field.php?d='.$callforpaper->id);
    } else {
        throw new \moodle_exception('nofieldincallforpaper', 'callforpaper');
    }
}

// populate objets for this callforpapers fields
$fields = array();
foreach ($fieldrecords as $fieldrecord) {
    $fields[]= callforpaper_get_field($fieldrecord, $callforpaper);
}

$mform = new mod_callforpaper_export_form(new moodle_url('/mod/callforpaper/export.php', ['d' => $callforpaper->id,
    'backto' => $redirectbackto]), $fields, $cm, $callforpaper);

if ($mform->is_cancelled()) {
    $redirectbackto = !empty($redirectbackto) ? $redirectbackto :
        new \moodle_url('/mod/callforpaper/view.php', ['d' => $callforpaper->id]);
    redirect($redirectbackto);
} else if ($formdata = (array) $mform->get_data()) {
    $selectedfields = array();
    foreach ($formdata as $key => $value) {
        //field form elements are field_1 field_2 etc. 0 if not selected. 1 if selected.
        if (strpos($key, 'field_')===0 && !empty($value)) {
            $selectedfields[] = substr($key, 6);
        }
    }

    $currentgroup = groups_get_activity_group($cm);

    $exporter = null;
    switch ($formdata['exporttype']) {
        case 'csv':
            $exporter = new \mod_callforpaper\local\exporter\csv_entries_exporter();
            $exporter->set_delimiter_name($formdata['delimiter_name']);
            break;
        case 'ods':
            $exporter = new \mod_callforpaper\local\exporter\ods_entries_exporter();
            break;
        default:
            throw new coding_exception('Invalid export format has been specified. '
                . 'Only "csv" and "ods" are currently supported.');
    }

    $includefiles = !empty($formdata['includefiles']);
    \mod_callforpaper\local\exporter\utils::callforpaper_exportdata($callforpaper->id, $fields, $selectedfields, $exporter, $currentgroup, $context,
        $exportuser, $exporttime, $exportapproval, $tags, $includefiles);
    $count = $exporter->get_records_count();
    $filename = clean_filename("{$callforpaper->name}-{$count}_record");
    if ($count > 1) {
        $filename .= 's';
    }
    $filename .= clean_filename('-' . gmdate("Ymd_Hi"));
    $exporter->set_export_file_name($filename);
    $exporter->send_file();
}

// Build header to match the rest of the UI.
$PAGE->add_body_class('limitedwidth');
$pagename = get_string('exportentries', 'callforpaper');
$titleparts = [
    $pagename,
    format_string($callforpaper->name),
    format_string($course->fullname),
];
$PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);
$PAGE->set_secondary_active_tab('modulepage');
$PAGE->activityheader->disable();
echo $OUTPUT->header();
echo $OUTPUT->heading($pagename);

groups_print_activity_menu($cm, $url);

$mform->display();

echo $OUTPUT->footer();

die();
