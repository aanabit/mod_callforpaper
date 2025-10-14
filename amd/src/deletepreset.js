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
 * Javascript module for deleting a callforpaper as a preset.
 *
 * @module      mod_callforpaper/deletepreset
 * @copyright   2022 Amaia Anabitarte <amaia@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {prefetchStrings} from 'core/prefetch';
import {getString} from 'core/str';
import Ajax from 'core/ajax';
import Url from 'core/url';

const selectors = {
    deletePresetButton: '[data-action="deletepreset"]',
};

/**
 * Initialize module
 */
export const init = () => {
    prefetchStrings('mod_callforpaper', [
        'deleteconfirm',
        'deletewarning',
    ]);
    prefetchStrings('core', [
        'delete',
    ]);

    registerEventListeners();
};

/**
 * Register events for delete preset option in action menu.
 */
const registerEventListeners = () => {
    document.addEventListener('click', (event) => {
        const deleteOption = event.target.closest(selectors.deletePresetButton);
        if (deleteOption) {
            event.preventDefault();
            deletePresetConfirm(deleteOption);
        }
    });
};

/**
 * Show the confirmation modal to delete the preset.
 *
 * @param {HTMLElement} deleteOption the element to delete.
 */
const deletePresetConfirm = (deleteOption) => {
    const presetName = deleteOption.getAttribute('data-presetname');
    const callforpaperId = deleteOption.getAttribute('data-callforpaperid');

    Notification.deleteCancelPromise(
        getString('deleteconfirm', 'mod_callforpaper', presetName),
        getString('deletewarning', 'mod_callforpaper'),
    ).then(() => {
        return deletePreset(callforpaperId, presetName);
    }).catch(() => {
        return;
    });
};

/**
 * Delete site user preset.
 *
 * @param {int} callforpaperId The id of the current callforpaper activity.
 * @param {string} presetName The preset name to delete.
 * @return {promise} Resolved with the result and warnings of deleting a preset.
 */
async function deletePreset(callforpaperId, presetName) {
    var request = {
        methodname: 'mod_callforpaper_delete_saved_preset',
        args: {
            callforpaperid: callforpaperId,
            presetnames: {presetname: presetName},
        }
    };
    try {
        await Ajax.call([request])[0];
        window.location.href = Url.relativeUrl(
            'mod/callforpaper/preset.php',
            {
                d: callforpaperId,
            },
            false
        );
    } catch (error) {
        Notification.exception(error);
    }
}
