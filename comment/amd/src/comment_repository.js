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
 * @copyright  2017 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module core_comment/comment
 */
define(
[
    'jquery',
    'core/ajax'
],
function(
    $,
    Ajax
) {

    /**
     * Get the comments based on the supplied criteria.
     *
     * @param {object} criteria the request criteria.
     * @return {Promise} a promise.
     */
    var findBy = function(criteria) {
        var request = {
            methodname: 'core_comment_get_comments',
            args: {
                contextlevel: criteria.contextlevel,
                instanceid: criteria.instanceid,
                component: criteria.component,
                itemid: criteria.itemid,
                area: criteria.commentarea,
                page: criteria.page
            }
        };

        return Ajax.call([request])[0];
    };

    /**
     * Delete the comment identified by the specified id.
     *
     * @param {int} commentId the comment id
     * @returns {Promise} a promise
     */
    var deleteById = function(commentId) {
        var request = {
            methodname: 'core_comment_delete_comments',
            args: {comments: [commentId]}
        };

        return Ajax.call([request])[0];
    };


    /**
     * Create a new comment.
     *
     * @param {object} comment the comment object.
     * @returns {Promise} a promise which, once resolved, will return the created comment.
     */
    var add = function(comment) {
        var request = {
            methodname: 'core_comment_add_comments',
            args: {
                comments: [comment]
            }
        };

        return Ajax.call([request])[0]
            .then(function(comments) {
                return comments[0];
            });
    };

    return {
        findBy: findBy,
        deleteById: deleteById,
        add: add,
    };

});
