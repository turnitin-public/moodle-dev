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
 * @package    core_comment
 * @copyright  2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module core_comment/comment_index
 */

define(
[
    'jquery',
    'core/custom_interaction_events',
    'core/str',
    'core/config',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/templates'
],
function(
    $,
    CustomEvents,
    str,
    Cfg,
    ModalFactory,
    ModalEvents,
    ajax,
    templates
) {
    /**
     * Initialises comment index page js.
     *
     * @method initCommentindexPage
     */
    var init = function () {

        var select_all = $('#comment_select_all');
        if (select_all) {
            select_all.on('click', function (e) {
                // The default behaviour on clicking is to toggle the checked attribute
                // so just use that state to control the comment checkbox state.
                var selectcontrol = $(this);
                var comments = $('[name="comments"]');
                for (var i in comments) {
                    comments[i].checked = selectcontrol.is(':checked');
                }
            });
        }

        // Create the 'delete comment' confirmation modal.
        str.get_string('confirmdeletecomments', 'admin')
            .done(function (string) {
                ModalFactory.create({
                    type: ModalFactory.types.CONFIRM,
                    title: 'Delete comments',
                    body: string,
                })
                    .done(function (modal) {
                        var list = []; // List of selected comments.

                        // Attach a modal controller to the save button and only display if we have selected entries to delete.
                        $('#comments_delete').on('click', function (e) {
                            e.preventDefault();
                            // Get the list of checked comments.
                            list = [];
                            var comments = $('[name="comments"]');
                            for (var i in comments) {
                                if (comments[i].checked) {
                                    list.push(comments[i].value);
                                }
                            }
                            if (list.length > 0) {
                                modal.show();
                            }
                        });

                        // Handle the Delete confirmation case.
                        modal.getRoot().on(ModalEvents.yes, function (e) {
                            e.preventDefault();

                            var promises = ajax.call([{
                                methodname: 'core_comment_delete_comments',
                                args: {commentids: list}
                            }], true);

                            $.when.apply($, promises)
                                .done(function (data) {
                                    modal.hide();
                                    location.reload();
                                });
                        });
                    });
            });

    };

    return /** @alias module:core/comment_index */ {
        init: init
    };
});
