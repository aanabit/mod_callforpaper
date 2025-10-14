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

/**
 * Renderable class for the action bar elements in the field pages in the callforpaper activity.
 *
 * @package    mod_callforpaper
 * @copyright  2021 Mihail Geshoski <mihail@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields_action_bar implements templatable, renderable {

    /** @var int $id The callforpaper module id. */
    private $id;

    /**
     * The class constructor.
     *
     * @param int $id The callforpaper module id
     * @param \action_menu|null $unused5 This parameter has been deprecated since 4.2 and should not be used anymore.
     */
    public function __construct(int $id) {
        $this->id = $id;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output The renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {

        $callforpaper = [
            'd' => $this->id,
            'title' => get_string('managefields', 'mod_callforpaper'),
        ];

        return $callforpaper;
    }
}
