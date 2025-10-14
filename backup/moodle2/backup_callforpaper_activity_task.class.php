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
 * Defines backup_callforpaper_activity_task
 *
 * @package     mod_callforpaper
 * @category    backup
 * @copyright   2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/callforpaper/backup/moodle2/backup_callforpaper_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the Call for paper instance
 */
class backup_callforpaper_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance callforpaper in the callforpaper.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_callforpaper_activity_structure_step('callforpaper_structure', 'callforpaper.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");
        $baseunquoted = $CFG->wwwroot;

        // Link to the list of callforpaper.
        $search = '/(' . $base . '\/mod\/callforpaper\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERINDEX*$2@$', $content);

        // Link to the list of callforpapers, urlencoded.
        $search = '/(' . urlencode($baseunquoted . '/mod/callforpaper/index.php?id=') . ')([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERINDEXURLENCODED*$2@$', $content);

        // Link to callforpaper view by moduleid.
        $search = '/(' . $base . '\/mod\/callforpaper\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERVIEWBYID*$2@$', $content);

        // Link to callforpaper view by moduleid, urlencoded.
        $search = '/(' . urlencode($baseunquoted . '/mod/callforpaper/view.php?id=') . ')([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERVIEWBYIDURLENCODED*$2@$', $content);

        // Link to one "record" of the callforpaper.
        $search = '/(' . $base . '\/mod\/callforpaper\/view.php\?d\=)([0-9]+)\&(amp;)rid\=([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERVIEWRECORD*$2*$4@$', $content);

        // Link to one "record" of the callforpaper, urlencoded.
        $search = '/(' . urlencode($baseunquoted . '/mod/callforpaper/view.php?d=') . ')([0-9]+)%26rid%3D([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERVIEWRECORDURLENCODED*$2*$3@$', $content);

        // Link to callforpaper view by callforpaperid.
        $search = '/(' . $base . '\/mod\/callforpaper\/view.php\?d\=)([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERVIEWBYD*$2@$', $content);

        // Link to callforpaper view by callforpaperid, urlencoded.
        $search = '/(' . urlencode($baseunquoted . '/mod/callforpaper/view.php?d=') . ')([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPERVIEWBYDURLENCODED*$2@$', $content);

        // Link to the edit page.
        $search = '/(' . $base . '\/mod\/callforpaper\/edit.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPEREDITBYID*$2@$', $content);

        // Link to the edit page, urlencoded.
        $search = '/(' . urlencode($baseunquoted . '/mod/callforpaper/edit.php?id=') . ')([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPEREDITBYIDURLENCODED*$2@$', $content);

        // Link to the edit page by callforpaperid.
        $search = '/(' . $base . '\/mod\/callforpaper\/edit.php\?d\=)([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPEREDITBYD*$2@$', $content);

        // Link to the edit page by callforpaperid, urlencoded.
        $search = '/(' . urlencode($baseunquoted . '/mod/callforpaper/edit.php?d=') . ')([0-9]+)/';
        $content = preg_replace($search, '$@CALLFORPAPEREDITBYDURLENCODED*$2@$', $content);

        return $content;
    }
}
