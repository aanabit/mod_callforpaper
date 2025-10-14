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

use mod_callforpaper\manager;

require_once('../../config.php');
require_once('locallib.php');
require_once("$CFG->libdir/rsslib.php");
require_once("$CFG->libdir/form/filemanager.php");

$id = optional_param('id', 0, PARAM_INT); // Course module id.
$d = optional_param('d', 0, PARAM_INT); // Call for paper id.
$rid = optional_param('rid', 0, PARAM_INT); // Record id.
$mode = 'addtemplate'; // Define the mode for this page, only 1 mode available.
$tags = optional_param_array('tags', [], PARAM_TAGLIST);
$redirectbackto = optional_param('backto', '', PARAM_LOCALURL); // The location to redirect back.

$url = new moodle_url('/mod/callforpaper/edit.php');

$record = null;

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, manager::MODULE);
    $manager = manager::create_from_coursemodule($cm);
} else {   // We must have $d.
    $callforpaper = $DB->get_record('callforpaper', ['id' => $d], '*', MUST_EXIST);
    $manager = manager::create_from_instance($callforpaper);
    $cm = $manager->get_coursemodule();
    $course = get_course($cm->course);
}
$callforpaper = $manager->get_instance();
$context = $manager->get_context();
$url->param('id', $cm->id);

if ($rid !== 0) {
    $record = $DB->get_record(
        'callforpaper_records',
        ['id' => $rid, 'callforpaperid' => $callforpaper->id],
        '*',
        MUST_EXIST
    );
    $url->param('rid', $rid);
}

$PAGE->set_url($url);
require_login($course, false, $cm);

$url->param('backto', $redirectbackto);

if (isguestuser()) {
    redirect('view.php?d='.$callforpaper->id);
}

/// Can't use this if there are no fields
if ($manager->can_manage_templates()) {
    if (!$manager->has_fields()) {
        redirect($CFG->wwwroot.'/mod/callforpaper/field.php?d='.$callforpaper->id);  // Redirect to field entry.
    }
}

// Get Group information for permission testing and record creation.
$currentgroup = groups_get_activity_group($cm);
$groupmode = groups_get_activity_groupmode($cm);

if (!has_capability('mod/callforpaper:manageentries', $context)) {
    if ($rid) {
        // User is editing an existing record.
        if (!callforpaper_user_can_manage_entry($record, $callforpaper, $context)) {
            throw new \moodle_exception('noaccess', 'callforpaper');
        }
    } else if (!callforpaper_user_can_add_entry($callforpaper, $currentgroup, $groupmode, $context)) {
        // User is trying to create a new record.
        throw new \moodle_exception('noaccess', 'callforpaper');
    }
}

// RSS and CSS and JS meta.
if (!empty($CFG->enablerssfeeds) && !empty($CFG->callforpaper_enablerssfeeds) && $callforpaper->rssarticles > 0) {
    $courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    $rsstitle = $courseshortname . \moodle_page::TITLE_SEPARATOR . format_string($callforpaper->name);
    rss_add_http_header($context, 'mod_callforpaper', $callforpaper, $rsstitle);
}
if ($callforpaper->csstemplate) {
    $PAGE->requires->css('/mod/callforpaper/css.php?d='.$callforpaper->id);
}
if ($callforpaper->jstemplate) {
    $PAGE->requires->js('/mod/callforpaper/js.php?d='.$callforpaper->id, true);
}

// Define page variables.
$strcallforpaper = get_string('modulenameplural','callforpaper');

if ($rid) {
    $PAGE->navbar->add(get_string('editentry', 'callforpaper'));
}

$PAGE->add_body_class('limitedwidth');
if ($rid) {
    $pagename = get_string('editentry', 'callforpaper');
} else {
    $pagename = get_string('newentry', 'callforpaper');
}
$PAGE->navbar->add($pagename);
$titleparts = [
    $pagename,
    format_string($callforpaper->name),
    format_string($course->fullname),
];
$PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
$PAGE->force_settings_menu(true);
$PAGE->set_secondary_active_tab('modulepage');
$PAGE->activityheader->disable();

// Process incoming data for adding/updating records.

// Keep track of any notifications ad submitted data.
$processeddata = null;
$callforpaperrecord = data_submitted() ?: null;

// Process the submitted form.
if ($callforpaperrecord && confirm_sesskey()) {
    // Validate the form to ensure that enough data was submitted.
    $fields = $manager->get_field_records();
    $processeddata = callforpaper_process_submission($callforpaper, $fields, $callforpaperrecord);

    if ($processeddata->validated) {
        if ($rid) {
            $recordid = $rid;
            // Updating an existing record.
            callforpaper_update_record_fields_contents($callforpaper, $record, $context, $callforpaperrecord, $processeddata);
        } else {
            // Add instance to callforpaper_record.
            $recordid = callforpaper_add_record($callforpaper, $currentgroup);
            if ($recordid) {
                // Now populate the fields contents of the new record.
                callforpaper_add_fields_contents_to_new_record($callforpaper, $context, $recordid, $fields, $callforpaperrecord, $processeddata);
            }
        }

        if ($recordid) {
            core_tag_tag::set_item_tags('mod_callforpaper', 'callforpaper_records', $recordid, $context, $tags);

            if (!empty($callforpaperrecord->saveandadd)) {
                // User has clicked "Save and add another". Reset all of the fields.
                $callforpaperrecord = null;
            } else {
                $viewurl = new moodle_url('/mod/callforpaper/view.php', [
                    'd' => $callforpaper->id,
                    'rid' => $recordid,
                ]);
                redirect($viewurl);
            }
        }
    }
}
// End of form processing.

echo $OUTPUT->header();

groups_print_activity_menu($cm, $CFG->wwwroot.'/mod/callforpaper/edit.php?d='.$callforpaper->id);

// Form goes here first in case add template is empty.
echo '<form enctype="multipart/form-data" action="edit.php" method="post">';
echo '<div>';
echo '<input name="d" value="'.$callforpaper->id.'" type="hidden" />';
echo '<input name="rid" value="'.$rid.'" type="hidden" />';
echo '<input name="sesskey" value="'.sesskey().'" type="hidden" />';
echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');

echo $OUTPUT->heading($pagename);

$template = $manager->get_template($mode);
echo $template->parse_add_entry($processeddata, $rid, $callforpaperrecord);

if (empty($redirectbackto)) {
    $redirectbackto = new \moodle_url('/mod/callforpaper/view.php', ['id' => $cm->id]);
}

$actionbuttons = html_writer::link(
    $redirectbackto,
    get_string('cancel'),
    ['class' => 'btn btn-secondary mx-1', 'role' => 'button']
);
$actionbuttons .= html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'saveandview',
    'value' => get_string('save'),
    'class' => 'btn btn-primary mx-1'
]);

if (!$rid && ((!$callforpaper->maxentries) ||
    has_capability('mod/callforpaper:manageentries', $context) ||
    (callforpaper_numentries($callforpaper) < ($callforpaper->maxentries - 1)))) {
    $actionbuttons .= html_writer::empty_tag('input', [
        'type' => 'submit', 'name' => 'saveandadd',
        'value' => get_string('saveandadd', 'callforpaper'), 'class' => 'btn btn-primary mx-1'
    ]);
}

$stickyfooter = new core\output\sticky_footer($actionbuttons);
echo $OUTPUT->render($stickyfooter);

echo $OUTPUT->box_end();
echo '</div></form>';

$possiblefields = $manager->get_fields();
foreach ($possiblefields as $field) {
    $field->print_after_form();
}

// Finish the page.
if (empty($possiblefields)) {
    throw new \moodle_exception('nofieldincallforpaper', 'callforpaper');
}
echo $OUTPUT->footer();
