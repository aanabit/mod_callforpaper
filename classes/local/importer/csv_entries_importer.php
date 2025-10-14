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

namespace mod_callforpaper\local\importer;

use context_module;
use core_php_time_limit;
use core_tag_tag;
use core_user;
use csv_import_reader;
use moodle_exception;
use stdClass;

/**
 * CSV entries_importer class for importing data and - if needed - files as well from a zip archive.
 *
 * @package    mod_callforpaper
 * @copyright  2023 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_entries_importer extends entries_importer {

    /** @var array Log entries for successfully added records. */
    private array $addedrecordsmessages = [];

    /**
     * Declares the entries_importer to use a csv file as data file.
     *
     * @see entries_importer::get_import_callforpaper_file_extension()
     */
    public function get_import_callforpaper_file_extension(): string {
        return 'csv';
    }

    /**
     * Import records for a callforpaper instance from csv data.
     *
     * @param stdClass $cm Course module of the callforpaper instance.
     * @param stdClass $callforpaper The callforpaper instance.
     * @param string $encoding The encoding of csv data.
     * @param string $fielddelimiter The delimiter of the csv data.
     *
     * @throws moodle_exception
     */
    public function import_csv(stdClass $cm, stdClass $callforpaper, string $encoding, string $fielddelimiter): void {
        global $CFG, $DB;
        // Large files are likely to take their time and memory. Let PHP know
        // that we'll take longer, and that the process should be recycled soon
        // to free up memory.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $iid = csv_import_reader::get_new_iid('modcallforpaper');
        $cir = new csv_import_reader($iid, 'modcallforpaper');

        $context = context_module::instance($cm->id);

        $readcount = $cir->load_csv_content($this->get_callforpaper_file_content(), $encoding, $fielddelimiter);
        if (empty($readcount)) {
            throw new \moodle_exception('csvfailed', 'callforpaper', "{$CFG->wwwroot}/mod/callforpaper/edit.php?d={$callforpaper->id}");
        } else {
            if (!$fieldnames = $cir->get_columns()) {
                throw new \moodle_exception('cannotreadtmpfile', 'error');
            }

            // Check the fieldnames are valid.
            $rawfields = $DB->get_records('callforpaper_fields', ['callforpaperid' => $callforpaper->id], '', 'name, id, type');
            $fields = [];
            $errorfield = '';
            $usernamestring = get_string('username');
            $safetoskipfields = [get_string('user'), get_string('email'),
                get_string('timeadded', 'callforpaper'), get_string('timemodified', 'callforpaper'),
                get_string('approved', 'callforpaper'), get_string('tags', 'callforpaper')];
            $userfieldid = null;
            foreach ($fieldnames as $id => $name) {
                if (!isset($rawfields[$name])) {
                    if ($name == $usernamestring) {
                        $userfieldid = $id;
                    } else if (!in_array($name, $safetoskipfields)) {
                        $errorfield .= "'$name' ";
                    }
                } else {
                    // If this is the second time, a field with this name comes up, it must be a field not provided by the user...
                    // like the username.
                    if (isset($fields[$name])) {
                        if ($name == $usernamestring) {
                            $userfieldid = $id;
                        }
                        unset($fieldnames[$id]); // To ensure the user provided content fields remain in the array once flipped.
                    } else {
                        $field = $rawfields[$name];
                        $field->type = clean_param($field->type, PARAM_ALPHA);
                        $filepath = "$CFG->dirroot/mod/callforpaper/field/$field->type/field.class.php";
                        if (!file_exists($filepath)) {
                            $errorfield .= "'$name' ";
                            continue;
                        }
                        require_once($filepath);
                        $classname = 'callforpaper_field_' . $field->type;
                        $fields[$name] = new $classname($field, $callforpaper, $cm);
                    }
                }
            }

            if (!empty($errorfield)) {
                throw new \moodle_exception('fieldnotmatched', 'callforpaper',
                    "{$CFG->wwwroot}/mod/callforpaper/edit.php?d={$callforpaper->id}", $errorfield);
            }

            $fieldnames = array_flip($fieldnames);

            $cir->init();
            while ($record = $cir->next()) {
                $authorid = null;
                if ($userfieldid) {
                    if (!($author = core_user::get_user_by_username($record[$userfieldid], 'id'))) {
                        $authorid = null;
                    } else {
                        $authorid = $author->id;
                    }
                }

                // Determine presence of "approved" field within the record to import.
                $approved = true;
                if (array_key_exists(get_string('approved', 'callforpaper'), $fieldnames)) {
                    $approvedindex = $fieldnames[get_string('approved', 'callforpaper')];
                    $approved = !empty($record[$approvedindex]);
                }

                if ($recordid = callforpaper_add_record($callforpaper, 0, $authorid, $approved)) { // Add instance to callforpaper_record.
                    foreach ($fields as $field) {
                        $fieldid = $fieldnames[$field->field->name];
                        if (isset($record[$fieldid])) {
                            $value = $record[$fieldid];
                        } else {
                            $value = '';
                        }

                        if (method_exists($field, 'update_content_import')) {
                            $field->update_content_import($recordid, $value, 'field_' . $field->field->id);
                        } else {
                            $content = new stdClass();
                            $content->fieldid = $field->field->id;
                            $content->content = $value;
                            $content->recordid = $recordid;
                            if ($field->file_import_supported() && $this->importfiletype === 'zip') {
                                $filecontent = $this->get_file_content_from_zip($content->content);
                                if (!$filecontent) {
                                    // No corresponding file in zip archive, so no record for this field being added at all.
                                    continue;
                                }
                                $contentid = $DB->insert_record('callforpaper_content', $content);
                                $field->import_file_value($contentid, $filecontent, $content->content);
                            } else {
                                $DB->insert_record('callforpaper_content', $content);
                            }
                        }
                    }

                    if (core_tag_tag::is_enabled('mod_callforpaper', 'callforpaper_records') &&
                        isset($fieldnames[get_string('tags', 'callforpaper')])) {
                        $columnindex = $fieldnames[get_string('tags', 'callforpaper')];
                        $rawtags = $record[$columnindex];
                        $tags = explode(',', $rawtags);
                        foreach ($tags as $tag) {
                            $tag = trim($tag);
                            if (empty($tag)) {
                                continue;
                            }
                            core_tag_tag::add_item_tag('mod_callforpaper', 'callforpaper_records', $recordid, $context, $tag);
                        }
                    }

                    $this->addedrecordsmessages[] = get_string('added', 'moodle',
                            count($this->addedrecordsmessages) + 1)
                        . ". " . get_string('entry', 'callforpaper')
                        . " (ID $recordid)\n";
                }
            }
            $cir->close();
            $cir->cleanup(true);
        }
    }

    /**
     * Getter for the array of messages for added records.
     *
     * For each successfully added record the array contains a log message.
     *
     * @return array Array of message strings: For each added record one message string
     */
    public function get_added_records_messages(): array {
        return $this->addedrecordsmessages;
    }
}
