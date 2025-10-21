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
 * This file is part of the Callforpaper module for Moodle
 *
 * @copyright 2005 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package mod_callforpaper
 */

use mod_callforpaper\manager;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/callforpaper/locallib.php');
require_once($CFG->libdir . '/rsslib.php');

/// One of these is necessary!
$id = optional_param('id', 0, PARAM_INT);  // course module id
$d = optional_param('d', 0, PARAM_INT);   // callforpaper id
$rid = optional_param('rid', 0, PARAM_INT);    //record id
$mode = optional_param('mode', '', PARAM_ALPHA);    // Force the browse mode  ('single')
$filter = optional_param('filter', 0, PARAM_BOOL);
// search filter will only be applied when $filter is true

$edit = optional_param('edit', -1, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_INT);
/// These can be added to perform an action on a record
$approve = optional_param('approve', 0, PARAM_INT);    //approval recordid
$disapprove = optional_param('disapprove', 0, PARAM_INT);    // disapproval recordid
$delete = optional_param('delete', 0, PARAM_INT);    //delete recordid
$multidelete = optional_param_array('delcheck', null, PARAM_INT);
$serialdelete = optional_param('serialdelete', null, PARAM_RAW);
$confirm = optional_param('confirm', 0, PARAM_INT);

$record = null;

if ($id) {
    list($course, $cm) = get_course_and_cm_from_cmid($id, manager::MODULE);
    $manager = manager::create_from_coursemodule($cm);
} else if ($rid) {
    $record = $DB->get_record('callforpaper_records', ['id' => $rid], '*', MUST_EXIST);
    $manager = manager::create_from_callforpaper_record($record);
    $cm = $manager->get_coursemodule();
    $course = get_course($cm->course);
} else {   // We must have $d.
    $callforpaper = $DB->get_record('callforpaper', ['id' => $d], '*', MUST_EXIST);
    $manager = manager::create_from_instance($callforpaper);
    $cm = $manager->get_coursemodule();
    $course = get_course($cm->course);
}

$callforpaper = $manager->get_instance();
$context = $manager->get_context();

require_login($course, true, $cm);

require_once($CFG->dirroot . '/comment/lib.php');
comment::init();

require_capability('mod/callforpaper:reviewentry', $context);

\mod_callforpaper\local\reviewer_information::pull_data_for($callforpaper->id);

/// Check further parameters that set browsing preferences
if (!isset($SESSION->dataprefs)) {
    $SESSION->dataprefs = array();
}
if (!isset($SESSION->dataprefs[$callforpaper->id])) {
    $SESSION->dataprefs[$callforpaper->id] = array();
    $SESSION->dataprefs[$callforpaper->id]['search'] = '';
    $SESSION->dataprefs[$callforpaper->id]['search_array'] = array();
    $SESSION->dataprefs[$callforpaper->id]['sort'] = $callforpaper->defaultsort;
    $SESSION->dataprefs[$callforpaper->id]['advanced'] = 0;
    $SESSION->dataprefs[$callforpaper->id]['order'] = ($callforpaper->defaultsortdir == 0) ? 'ASC' : 'DESC';
}

// reset advanced form
if (!is_null(optional_param('resetadv', null, PARAM_RAW))) {
    $SESSION->dataprefs[$callforpaper->id]['search_array'] = array();
    // we need the redirect to cleanup the form state properly
    redirect("view.php?id=$cm->id&amp;mode=$mode&amp;search=&amp;advanced=1");
}

$advanced = optional_param('advanced', -1, PARAM_INT);
if ($advanced == -1) {
    $advanced = $SESSION->dataprefs[$callforpaper->id]['advanced'];
} else {
    if (!$advanced) {
        // explicitly switched to normal mode - discard all advanced search settings
        $SESSION->dataprefs[$callforpaper->id]['search_array'] = array();
    }
    $SESSION->dataprefs[$callforpaper->id]['advanced'] = $advanced;
}

$search_array = $SESSION->dataprefs[$callforpaper->id]['search_array'];

if (!empty($advanced)) {
    $search = '';

    //Added to ammend paging error. This error would occur when attempting to go from one page of advanced
    //search results to another.  All fields were reset in the page transfer, and there was no way of determining
    //whether or not the user reset them.  This would cause a blank search to execute whenever the user attempted
    //to see any page of results past the first.
    //This fix works as follows:
    //$paging flag is set to false when page 0 of the advanced search results is viewed for the first time.
    //Viewing any page of results after page 0 passes the false $paging flag though the URL (see line 523) and the
    //execution falls through to the second condition below, allowing paging to be set to true.
    //Paging remains true and keeps getting passed though the URL until a new search is performed
    //(even if page 0 is revisited).
    //A false $paging flag generates advanced search results based on the fields input by the user.
    //A true $paging flag generates davanced search results from the $SESSION global.

    $paging = optional_param('paging', NULL, PARAM_BOOL);
    if($page == 0 && !isset($paging)) {
        $paging = false;
    }
    else {
        $paging = true;
    }

    // Now build the advanced search array.
    list($search_array, $search) = callforpaper_build_search_array($callforpaper, $paging, $search_array);
    $SESSION->dataprefs[$callforpaper->id]['search_array'] = $search_array;     // Make it sticky.

} else {
    $search = optional_param('search', $SESSION->dataprefs[$callforpaper->id]['search'], PARAM_NOTAGS);
    //Paging variable not used for standard search. Set it to null.
    $paging = NULL;
}

// Disable search filters if $filter is not true:
if (! $filter) {
    $search = '';
}

$SESSION->dataprefs[$callforpaper->id]['search'] = $search;   // Make it sticky

$sort = optional_param('sort', $SESSION->dataprefs[$callforpaper->id]['sort'], PARAM_INT);
$SESSION->dataprefs[$callforpaper->id]['sort'] = $sort;       // Make it sticky

$order = (optional_param('order', $SESSION->dataprefs[$callforpaper->id]['order'], PARAM_ALPHA) == 'ASC') ? 'ASC': 'DESC';
$SESSION->dataprefs[$callforpaper->id]['order'] = $order;     // Make it sticky


$oldperpage = get_user_preferences('data_perpage_'.$callforpaper->id, 10);
$perpage = optional_param('perpage', $oldperpage, PARAM_INT);

if ($perpage < 2) {
    $perpage = 2;
}
if ($perpage != $oldperpage) {
    set_user_preference('data_perpage_'.$callforpaper->id, $perpage);
}

// Trigger module viewed event and completion.
$manager->set_module_viewed($course);

$urlparams = array('d' => $callforpaper->id);
if ($record) {
    $urlparams['rid'] = $record->id;
}
if ($mode) {
    $urlparams['mode'] = $mode;
}
if ($page) {
    $urlparams['page'] = $page;
}
if ($filter) {
    $urlparams['filter'] = $filter;
}
$pageurl = new moodle_url('/mod/callforpaper/reviewview.php', $urlparams);

// Initialize $PAGE, compute blocks.
$PAGE->set_url($pageurl);

if (($edit != -1) and $PAGE->user_allowed_editing()) {
    $USER->editing = $edit;
}

$courseshortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

/// RSS and CSS and JS meta
$meta = '';
if (!empty($CFG->enablerssfeeds) && !empty($CFG->callforpaper_enablerssfeeds) && $callforpaper->rssarticles > 0) {
    $rsstitle = $courseshortname . ': ' . format_string($callforpaper->name);
    rss_add_http_header($context, 'mod_callforpaper', $callforpaper, $rsstitle);
}
if ($callforpaper->csstemplate) {
    $PAGE->requires->css('/mod/callforpaper/css.php?d='.$callforpaper->id);
}
if ($callforpaper->jstemplate) {
    $PAGE->requires->js('/mod/callforpaper/js.php?d='.$callforpaper->id, true);
}

/// Print the page header
// Note: MDL-19010 there will be further changes to printing header and blocks.
// The code will be much nicer than this eventually.

if ($PAGE->user_allowed_editing() && !$PAGE->theme->haseditswitch) {
    // Change URL parameter and block display string value depending on whether editing is enabled or not
    if ($PAGE->user_is_editing()) {
        $urlediting = 'off';
        $strediting = get_string('blockseditoff');
    } else {
        $urlediting = 'on';
        $strediting = get_string('blocksediton');
    }
    $editurl = new moodle_url($CFG->wwwroot.'/mod/callforpaper/view.php', ['id' => $cm->id, 'edit' => $urlediting]);
    $PAGE->set_button($OUTPUT->single_button($editurl, $strediting));
}

if ($mode == 'asearch') {
    $PAGE->navbar->add(get_string('search'));
}

$PAGE->add_body_class('mediumwidth');
$titleparts = [
        format_string($callforpaper->name),
        format_string($course->fullname),
];
if (!empty(trim($search))) {
    // Indicate search results on page title when searching.
    array_unshift($titleparts, get_string('searchresults', 'callforpaper', s($search)));
} else if (!empty($delete) && empty($confirm)) {
    // Displaying the delete confirmation page.
    array_unshift($titleparts, get_string('deleteentry', 'callforpaper'));
} else if ($record !== null || $mode == 'single') {
    // Indicate on the page tile if the user is viewing this page on single view mode.
    array_unshift($titleparts, get_string('single', 'callforpaper'));
}
$PAGE->set_title(implode(moodle_page::TITLE_SEPARATOR, $titleparts));
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);
if ($delete && callforpaper_user_can_manage_entry($delete, $callforpaper, $context)) {
    $PAGE->activityheader->disable();
}

// Check to see if groups are being used here.
// We need the most up to date current group value. Make sure it is updated at this point.
$currentgroup = groups_get_activity_group($cm, true);
$groupmode = groups_get_activity_groupmode($cm);
$canmanageentries = has_capability('mod/callforpaper:manageentries', $context);
echo $OUTPUT->header();

if (!$manager->has_fields()) {
    // It's a brand-new callforpaper. There are no fields.
    $renderer = $manager->get_renderer();
    echo $renderer->render_callforpaper_zero_state($manager);
    echo $OUTPUT->footer();
    // Don't check the rest of the options. There is no field, there is nothing else to work with.
    exit;
}

// Detect entries not approved yet and show hint instead of not found error.
if ($record and !callforpaper_can_view_record($callforpaper, $record, $currentgroup, $canmanageentries)) {
    throw new \moodle_exception('notapprovederror', 'callforpaper');
}

// Do we need to show a link to the RSS feed for the records?
//this links has been Settings (callforpaper activity administration) block
/*if (!empty($CFG->enablerssfeeds) && !empty($CFG->callforpaper_enablerssfeeds) && $callforpaper->rssarticles > 0) {
    echo '<div style="float:right;">';
    rss_print_link($context->id, $USER->id, 'mod_callforpaper', $callforpaper->id, get_string('rsstype'));
    echo '</div>';
    echo '<div style="clear:both;"></div>';
}*/

if ($callforpaper->intro and empty($page) and empty($record) and $mode != 'single') {
    $options = new stdClass();
    $options->noclean = true;
}

/// Delete any requested records

if ($delete && callforpaper_user_can_manage_entry($delete, $callforpaper, $context)) {
    if ($confirm) {
        require_sesskey();

        if (callforpaper_delete_record($delete, $callforpaper, $course->id, $cm->id)) {
            echo $OUTPUT->notification(get_string('recorddeleted','callforpaper'), 'notifysuccess');
        }
    } else {   // Print a confirmation page
        $userfieldsapi = \core_user\fields::for_userpic()->excluding('id');
        $allnamefields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
        $dbparams = array($delete);
        if ($deleterecord = $DB->get_record_sql("SELECT dr.*, $allnamefields
                                                   FROM {callforpaper_records} dr
                                                        JOIN {user} u ON dr.userid = u.id
                                                  WHERE dr.id = ?", $dbparams, MUST_EXIST)) { // Need to check this is valid.
            if ($deleterecord->callforpaperid == $callforpaper->id) {                       // Must be from this callforpaper
                echo $OUTPUT->heading(get_string('deleteentry', 'mod_callforpaper'), 2, 'mb-4');
                $deletebutton = new single_button(
                        new moodle_url('/mod/callforpaper/view.php?d=' . $callforpaper->id . '&delete=' . $delete . '&confirm=1'),
                        get_string('delete'), 'post',
                        single_button::BUTTON_DANGER
                );
                echo $OUTPUT->confirm(get_string('confirmdeleterecord','callforpaper'),
                        $deletebutton, 'view.php?d='.$callforpaper->id);

                $records[] = $deleterecord;
                $parser = $manager->get_template('singletemplate');
                echo $parser->parse_entries($records);

                echo $OUTPUT->footer();
                exit;
            }
        }
    }
}


// Multi-delete.
if ($serialdelete) {
    $multidelete = json_decode($serialdelete);
}

if ($multidelete && $canmanageentries) {
    if ($confirm) {
        require_sesskey();

        foreach ($multidelete as $value) {
            callforpaper_delete_record($value, $callforpaper, $course->id, $cm->id);
        }
    } else {
        $validrecords = array();
        $recordids = array();
        foreach ($multidelete as $value) {
            $userfieldsapi = \core_user\fields::for_userpic()->excluding('id');
            $allnamefields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;
            $dbparams = array('id' => $value);
            if ($deleterecord = $DB->get_record_sql("SELECT dr.*, $allnamefields
                                                       FROM {callforpaper_records} dr
                                                       JOIN {user} u ON dr.userid = u.id
                                                      WHERE dr.id = ?", $dbparams)) { // Need to check this is valid.
                if ($deleterecord->callforpaperid == $callforpaper->id) {  // Must be from this callforpaper.
                    $validrecords[] = $deleterecord;
                    $recordids[] = $deleterecord->id;
                }
            }
        }
        $serialiseddata = json_encode($recordids);
        $submitactions = array('d' => $callforpaper->id, 'sesskey' => sesskey(), 'confirm' => '1', 'serialdelete' => $serialiseddata);
        $action = new moodle_url('/mod/callforpaper/reviewview.php', $submitactions);
        $cancelurl = new moodle_url('/mod/callforpaper/reviewview.php', array('d' => $callforpaper->id));
        $deletebutton = new single_button($action, get_string('delete'), 'post', single_button::BUTTON_DANGER);
        echo $OUTPUT->confirm(get_string('confirmdeleterecords', 'callforpaper'), $deletebutton, $cancelurl);
        $parser = $manager->get_template('listtemplate');
        echo $parser->parse_entries($validrecords);
        echo $OUTPUT->footer();
        exit;
    }
}

// If callforpaper activity closed dont let students in.
// No need to display warnings because activity dates are displayed at the top of the page.
list($showactivity, $warnings) = callforpaper_get_time_availability_status($callforpaper, $canmanageentries);

if ($showactivity) {

    if ($mode == 'asearch') {
        $maxcount = 0;
        callforpaper_print_preference_form($callforpaper, $perpage, $search, $sort, $order, $search_array, $advanced, $mode);

    } else {
        // Approve or disapprove any requested records
        $approvecap = has_capability('mod/callforpaper:approve', $context);

        if (($approve || $disapprove) && $approvecap) {
            require_sesskey();
            $newapproved = $approve ? true : false;
            $recordid = $newapproved ? $approve : $disapprove;
            if ($approverecord = $DB->get_record('callforpaper_records', array('id' => $recordid))) {   // Need to check this is valid
                if ($approverecord->callforpaperid == $callforpaper->id) {                       // Must be from this callforpaper
                    callforpaper_approve_entry($approverecord->id, $newapproved);
                    $msgkey = $newapproved ? 'recordapproved' : 'recorddisapproved';
                    echo $OUTPUT->notification(get_string($msgkey, 'callforpaper'), 'notifysuccess');
                }
            }
        }

        $numentries = callforpaper_numentries($callforpaper);
        /// Check the number of entries required against the number of entries already made (doesn't apply to teachers)
        if ($callforpaper->entriesleft = callforpaper_get_entries_left_to_add($callforpaper, $numentries, $canmanageentries)) {
            $strentrieslefttoadd = get_string('entrieslefttoadd', 'callforpaper', $callforpaper);
            echo $OUTPUT->notification($strentrieslefttoadd);
        }

        /// Check the number of entries required before to view other participant's entries against the number of entries already made (doesn't apply to teachers)
        $requiredentries_allowed = true;
        if ($callforpaper->entrieslefttoview = callforpaper_get_entries_left_to_view($callforpaper, $numentries, $canmanageentries)) {
            $strentrieslefttoaddtoview = get_string('entrieslefttoaddtoview', 'callforpaper', $callforpaper);
            echo $OUTPUT->notification($strentrieslefttoaddtoview);
            $requiredentries_allowed = false;
        }

        if ($groupmode != NOGROUPS) {
            $returnurl = new moodle_url('/mod/callforpaper/reviewview.php', ['d' => $callforpaper->id, 'mode' => $mode, 'search' => s($search),
                    'sort' => s($sort), 'order' => s($order)]);
            echo html_writer::div(groups_print_activity_menu($cm, $returnurl, true), 'mb-3');
        }

        // Search for entries.
        list($records, $maxcount, $totalcount, $page, $nowperpage, $sort, $mode) =
                callforpaper_search_entries($callforpaper, $cm, $context, $mode, $currentgroup, $search, $sort, $order, $page, $perpage, $advanced, $search_array, $record);
        $hasrecords = !empty($records);

        if ($maxcount == 0) {
            $renderer = $manager->get_renderer();
            echo $renderer->render_empty_callforpaper($manager);
            echo $OUTPUT->footer();
            // There is no entry, so makes no sense to check different views, pagination, etc.
            exit;
        }

        // Advanced search form doesn't make sense for single (redirects list view).
        if ($maxcount && $mode != 'single') {
            callforpaper_print_preference_form($callforpaper, $perpage, $search, $sort, $order, $search_array, $advanced, $mode);
        }

        if (empty($records)) {
            if ($maxcount){
                $a = new stdClass();
                $a->max = $maxcount;
                $a->reseturl = "reviewview.php?id=$cm->id&amp;mode=$mode&amp;search=&amp;advanced=0";
                echo $OUTPUT->box_start();
                echo get_string('foundnorecords', 'callforpaper', $a);
                echo $OUTPUT->box_end();
            } else {
                echo $OUTPUT->box_start();
                echo get_string('norecords', 'callforpaper');
                echo $OUTPUT->box_end();
            }

        } else {
            //  We have some records to print.
            $formurl = new moodle_url('/mod/callforpaper/reviewview.php', ['d' => $callforpaper->id, 'sesskey' => sesskey()]);
            echo html_writer::start_tag('form', ['action' => $formurl, 'method' => 'post']);

            if ($maxcount != $totalcount) {
                $a = new stdClass();
                $a->num = $totalcount;
                $a->max = $maxcount;
                $a->reseturl = "view.php?id=$cm->id&amp;mode=$mode&amp;search=&amp;advanced=0";
                echo $OUTPUT->box_start();
                echo get_string('foundrecords', 'callforpaper', $a);
                echo $OUTPUT->box_end();
            }

            if ($mode == 'single' && false) { // Single template
                $baseurl = '/mod/callforpaper/reviewview.php';
                $baseurlparams = ['d' => $callforpaper->id, 'mode' => 'single'];
                if (!empty($search)) {
                    $baseurlparams['filter'] = 1;
                }
                if (!empty($page)) {
                    $baseurlparams['page'] = $page;
                }
                $baseurl = new moodle_url($baseurl, $baseurlparams);

                echo $OUTPUT->box_start('', 'callforpaper-singleview-content');
                require_once($CFG->dirroot.'/rating/lib.php');
                if ($callforpaper->assessed != RATING_AGGREGATE_NONE) {
                    $ratingoptions = new stdClass;
                    $ratingoptions->context = $context;
                    $ratingoptions->component = 'mod_callforpaper';
                    $ratingoptions->ratingarea = 'entry';
                    $ratingoptions->items = $records;
                    $ratingoptions->aggregate = $callforpaper->assessed;//the aggregation method
                    $ratingoptions->scaleid = $callforpaper->scale;
                    $ratingoptions->userid = $USER->id;
                    $ratingoptions->returnurl = $baseurl->out();
                    $ratingoptions->assesstimestart = $callforpaper->assesstimestart;
                    $ratingoptions->assesstimefinish = $callforpaper->assesstimefinish;

                    $rm = new rating_manager();
                    $records = $rm->get_ratings($ratingoptions);
                }

                $options = [
                        'search' => $search,
                        'page' => $page,
                        'baseurl' => $baseurl,
                ];
                $parser = $manager->get_template('singletemplate', $options);
                echo $parser->parse_entries($records);
                echo $OUTPUT->box_end();
            } else {
                // List template.
                $baseurl = '/mod/callforpaper/reviewview.php';
                $baseurlparams = ['d' => $callforpaper->id, 'advanced' => $advanced, 'paging' => $paging];
                if (!empty($search)) {
                    $baseurlparams['filter'] = 1;
                }
                $baseurl = new moodle_url($baseurl, $baseurlparams);

                echo $OUTPUT->box_start('', 'callforpaper-listview-content');

                $options = [
                        'search' => $search,
                        'page' => $page,
                        'baseurl' => $baseurl,
                ];

                $firstrecord = $records[array_key_first($records)];

                $parser = $manager->get_template('reviewerlisttemplateheader', $options);
                echo $parser->parse_entries([$firstrecord]);

                $parser = $manager->get_template('reviewerlisttemplate', $options);
                echo $parser->parse_entries($records);

                $parser = $manager->get_template('reviewerlisttemplatefooter', $options);
                echo $parser->parse_entries([$firstrecord]);
                echo $OUTPUT->box_end();
            }

            $stickyfooter = new mod_callforpaper\output\view_footer(
                    $manager,
                    $totalcount,
                    $page,
                    $nowperpage,
                    $baseurl,
                    $parser
            );
            echo $OUTPUT->render($stickyfooter);

            echo html_writer::end_tag('form');
        }
    }

    $search = trim($search);
    if (empty($records)) {
        $records = array();
    }
}

echo $OUTPUT->footer();
