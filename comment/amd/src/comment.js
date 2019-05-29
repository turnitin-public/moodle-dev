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
 * @module     core_comment/comment
 * @package    core_comment
 * @copyright  2019 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module core_comment/comment
 */
define(
[
    'jquery',
    'core/custom_interaction_events',
    'core/config',
    'core/ajax',
    'core/templates',
    'core_comment/comment_repository',
    'core/notification'
],
function(
    $,
    CustomEvents,
    Cfg,
    Ajax,
    Templates,
    CommentRepository,
    Notification
) {

    /** Selector strings, where '@clientid@' will be replaced with the respective unique widget id.*/
    /*var SELECTORS = {
        COMMENT_LIST_CONTAINER: '#comment-list-@clientid@',
        COMMENT_CONTROL: '#comment-ctrl-@clientid@',
        COMMENT_COUNT: '#comment-widget-count-@clientid@',
    };*/

    var CommentWidget = function(args) {
        var SELECTORS = {
            COMMENT_TOGGLE: '#comment-link-@clientid@',
            COMMENT_LIST_CONTAINER: '#comment-list-@clientid@',
            COMMENT_CONTROL: '#comment-ctrl-@clientid@',
            COMMENT_COUNT: '#comment-widget-count-@clientid@',
            COMMENT_TEXTAREA: '#dlg-content-@clientid@',
            COMMENT_ACTION_POST: '#comment-action-post-@clientid@',
            COMMENT_ACTION_CANCEL: '#comment-action-cancel-@clientid@',
            COMMENT_PAGINATION_CONTAINER: "#comment-pagination-@clientid@",
        };

        $.each(SELECTORS, function(index, selector) {
            SELECTORS[index] = selector.replace('@clientid@', args.client_id);
        });

        this.client_id = args.client_id;
        this.itemid = args.itemid;
        this.commentarea = args.commentarea;
        this.component = args.component;
        this.courseid = args.courseid;
        this.contextid = args.contextid;
        this.contextlevel = args.contextlevel;
        this.instanceid = args.instanceid;
        this.autostart = args.autostart;
        this.page = args.page;
        this.notoggle = args.notoggle;
        this.count = args.count;

        // Provides public access to SELECTORS, which is a private scoped var.
        // Used to give the prototype methods access to SELECTORS without needing to make the variable public.
        this.getSelectors = function() {
            return SELECTORS;
        };

        this.registerEvents(args);

        // TODO: notoggle should be controlled by the back end template render, so remove once it's known to work.
        // Set up this instance based on supplied config.
        /*if (this.notoggle) {
            $(this.getSelectors().COMMENT_TOGGLE).hide();
        }*/

        // TODO: Autostart should result in the comments being server rendered on page load, so remove when that's done.
        //if (this.autostart) {
           // this.view(this.page);
        //}
    };

    CommentWidget.prototype.load = function() {
        var criteria = {
            contextlevel: this.contextlevel,
            instanceid: this.instanceid,
            component: this.component,
            itemid: this.itemid,
            commentarea: this.commentarea,
            page: this.page
        }

        // Get the comments and render them in the list, returning a render promise.
        return CommentRepository.findBy(criteria)
            .then(function(data) {
                //TODO: load/update the pagination section.

                return this.renderCommentsList(data.comments)
                    .then(function () {
                        return $(this.getSelectors().COMMENT_CONTROL).removeClass('hidden');
                    }.bind(this))
                    .catch(Notification.exception);
            }.bind(this))
            .catch(Notification.exception);
    };

    /**
     * Load the comments into the list area.
     *
     * @param {array} list of comments
     * @returns {Promise} a renderer promise.
     */
    CommentWidget.prototype.renderCommentsList = function(comments) {
        var context = {
            'widgetid': this.client_id,
            'comments': comments.reverse() // This maintains the odd legacy ordering.
        }
        return Templates.render('core_comment/comments_list', context)
            .then(function(source) {
                return $(this.getSelectors().COMMENT_LIST_CONTAINER).html(source);
            }.bind(this));
    };

    /**
     * Add a new comment to the page.
     *
     * @param {object} newcomment the comment to add.
     * @returns {Promise} a renderer promise.
     */
    CommentWidget.prototype.renderNewComment = function(newcomment) {
        newcomment.widgetid = this.client_id;
        return Templates.render('core_comment/comment_list_item', newcomment)
            .then(function(source) {
                var newcomment = $(source);
                newcomment.hide();
                $(this.getSelectors().COMMENT_LIST_CONTAINER).append(newcomment);
                return newcomment.show(100);
            }.bind(this))
            .catch(Notification.exception);
    };

    /**
     * Load the first page of comments from the server, update the DOM, and expand the list.
     *
     * To match legacy behaviour, this must partially expand the widget (showing loading spinner for comments),
     * then load and update the DOM accordingly (hiding the spinner when done).
     *
     * @return {Promise} a renderer promise which, once resolved, means the widget has been loaded and expanded.
     */
    CommentWidget.prototype.show = function() {
        console.log('in the show method');
        var commenttoggler = $(this.getSelectors().COMMENT_TOGGLE);
        if (commenttoggler) {
            commenttoggler.attr('aria-expanded', true);
            commenttoggler.removeClass('collapsed');
            commenttoggler.addClass('collapsible');
        }

        // Init the pagination section of the widget based on 'count' and 'commentssperpage'.
        //this.renderPaginationControls();

        $('[data-region="comment-widget-' + this.client_id+'"]').find('[data-region="loading-icon-container"]').removeClass('hidden');
        return this.load()
            .then(function() {
                $('[data-region="comment-widget-'+this.client_id+'"]').find('[data-region="loading-icon-container"]').addClass('hidden');
                $(this.getSelectors().COMMENT_CONTROL).removeClass('hidden');

            }.bind(this))
            .catch(function() {
                $('[data-region="comment-widget-'+this.client_id+'"]').find('[data-region="loading-icon-container"]').addClass('hidden');
                $(this.getSelectors().COMMENT_CONTROL).removeClass('hidden');

            });

        // TODO: Update pagination controls.
    };

    /*CommentWidget.prototype.renderPaginationControls = function() {
        // Determine how many page numbers we need to show, bound by an upper limit of ??
        var numPages =
    };*/

    /**
     *
     * @returns {Promise} a renderer promise which, once resolved, means the
     */
    CommentWidget.prototype.hide = function() {
        $(this.getSelectors().COMMENT_TEXTAREA).val('');
        var commentcontrol = $(this.getSelectors().COMMENT_CONTROL);
        var commenttoggler = $(this.getSelectors().COMMENT_TOGGLE);
        if (commenttoggler) {
            commenttoggler.attr('aria-expanded', false);
            commenttoggler.removeClass('collapsible');
            commenttoggler.addClass('collapsed');
        }
        commentcontrol.addClass('hidden');
    };

    /** Increment the count in the widget. */
    CommentWidget.prototype.incrementCount = function () {
        var countContainer = $(this.getSelectors().COMMENT_COUNT);
        var count = countContainer.html();
        count++;
        countContainer.html(count);
    };

    /** Decrement the count in the widget. */
    CommentWidget.prototype.decrementCount = function () {
        var countContainer = $(this.getSelectors().COMMENT_COUNT);
        var count = countContainer.html();
        count--;
        countContainer.html(count);
    };

    CommentWidget.prototype.clearTextArea = function() {
        $(this.getSelectors().COMMENT_TEXTAREA).val('');
    };

    /**
     * Creates a new comment based on user input.
     */
    CommentWidget.prototype.createComment = function() {
        var content = $(this.getSelectors().COMMENT_TEXTAREA).val();
        if (!content) {
            return;
        }

        var comment = {
            contextlevel: this.contextlevel,
            instanceid: this.instanceid,
            component: this.component,
            itemid: this.itemid,
            area: this.commentarea,
            content: content
        };

        return CommentRepository.add(comment)
            .then(function(newcomment) {
                return this.renderNewComment(newcomment)
                    .then(function() {
                        this.incrementCount();
                        this.clearTextArea();
                        return;
                    }.bind(this))
                    .catch(Notification.exception);
            }.bind(this))
            .catch(Notification.exception);
    };

    /**
     * Delete a single comment by id, and remove it from the UI.
     *
     * @param {int} commentId the comment id.
     * @return {Promise} renderer promise
     */
    CommentWidget.prototype.deleteComment = function(commentId) {
        return CommentRepository.deleteById(commentId)
            .then(function() {
                var element = $('#comment-' + commentId + '-' + this.client_id);
                element.hide('fast', function() {
                    element.remove();
                    this.decrementCount();
                }.bind(this));
            }.bind(this))
            .catch(Notification.exception);
    };

    /**
     * Register the event handlers for this widget.
     */
    CommentWidget.prototype.registerEvents = function() {

        // Handler for posting a comment.
        $(this.getSelectors().COMMENT_ACTION_POST).on(CustomEvents.events.activate, function(e, data) {
            data.originalEvent.preventDefault();

            return this.createComment();
        }.bind(this));

        // Handler for the 'Cancel' link (a comment configuration option - not always present in DOM).
        $(this.getSelectors().COMMENT_ACTION_CANCEL).on(CustomEvents.events.activate, function(e, data) {
            data.originalEvent.preventDefault();

            return this.view(0);
        }.bind(this));

        // Handler for deletion.
        $(this.getSelectors().COMMENT_CONTROL)
                .on(CustomEvents.events.activate, '[data-action="request-delete"]', function(e, data) {
            data.originalEvent.preventDefault();

            var deletionElement = $(e.target).closest('[data-action="request-delete"]');
            var commentId = deletionElement.attr('data-comment-id');

            return this.deleteComment(commentId);
        }.bind(this));

        // Handler for pagination activation.
        $(this.getSelectors().COMMENT_PAGINATION_CONTAINER)
                .on(CustomEvents.events.activate, 'a', function(e, data) {
            data.originalEvent.preventDefault();

            var pagenum = $(e.target).attr('data-pagenum') - 1;

            // Reset the current page.
            $('#comment-pagination-' + this.client_id + ' a.curpage').addClass('pageno');
            $('#comment-pagination-' + this.client_id + ' a.curpage').removeClass('curpage');
            $(e.target).addClass('curpage').removeClass('pageno');

            console.log('changing to page: ' + pagenum);
            this.page = pagenum;
            return this.load();
        }.bind(this));

        // Handler for toggling the comment widget open and closed.
        $(this.getSelectors().COMMENT_TOGGLE).on(CustomEvents.events.activate, function(e, data) {
            data.originalEvent.preventDefault();
            var widget = $(this.getSelectors().COMMENT_CONTROL);
            if (this.isVisible(widget)) {
                return this.hide();
            } else {
                return this.show();
            }
        }.bind(this));
    };

    /**
     * Helper to determine whether an element is visible.
     *
     * @param {object} element the element to check
     * @returns {bool} true if visible, false otherwise.
     */
    CommentWidget.prototype.isVisible = function(element) {
        return !element.hasClass('hidden');
    };

    /** Handlers for the page buttons. */
    /*CommentWidget.prototype.register_pagination = function() {
        // TODO: Test pagination events are working!!!!!!
        $('#comment-pagination-' + this.client_id + ' a').each(function(id, elem) {
            elem.on('click', function(e) {

                e.preventDefault();
                var id = elem.get('id');
                var re = new RegExp("comment-page-" + this.client_id + "-(\\d+)", "i");
                var result = id.match(re);
                console.log('loading page '+result[1]);
                this.load(result[1]);
            }.bind(this));
        });
    };*/

    return {
        init: function(args) {
            return new CommentWidget(args);
        }
    };
});
