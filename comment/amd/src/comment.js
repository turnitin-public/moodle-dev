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
 * @class      comments_widget_events
 * @package    core_comment
 * @copyright  2017 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'core/config', 'core/modal_factory', 'core/modal_events', 'core/custom_interaction_events', 'core/ajax', 'core/templates', 'core_comment/comment_widget_events'],
    function($, Str, Cfg, ModalFactory, ModalEvents, CustomEvents, ajax, templates, CommentEvents) {

    return /** @alias module:core/comment */ {
        /**
         * Initialises tag index page js.
         *
         * @method initCommentindexPage
         */
        initCommentindexPage: function() {

            var select_all = $('#comment_select_all');
            if (select_all) {
                select_all.on('click', function(e) {
                    // The default behaviour on clicking is to toggle the checked attribute
                    // so just use that state to control the comment checkbox state.
                    var selectcontrol =  $(this);
                    var comments = $('[name="comments"]');
                    for (var i in comments) {
                        comments[i].checked = selectcontrol.is(':checked');
                    }
                });
            }

            // Create the 'delete comment' confirmation modal.
            Str.get_string('confirmdeletecomments', 'admin')
                .done(function(string) {
                    ModalFactory.create({
                        type: ModalFactory.types.CONFIRM,
                        title: 'Delete comments',
                        body: string,
                    })
                    .done(function(modal) {
                        var list = []; // List of selected comments.

                        // Attach a modal controller to the save button and only display if we have selected entries to delete.
                        $('#comments_delete').on('click', function(e) {
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
                        modal.getRoot().on(ModalEvents.yes, function(e) {
                            e.preventDefault();

                            var promises = ajax.call([{
                                methodname: 'core_comment_delete_comments',
                                args: {commentids: list}
                            }], true);

                            $.when.apply($, promises)
                            .done(function(data) {
                                modal.hide();
                                location.reload();
                            });
                        });
                    });
                })

        },

        /** Init js for comments widget.
         * @method init
         */
        init: function(args) {
            // All the widget code is encapsulated in a helper object for now.
            // TODO: split out to a standalone AMD module later.
            var widgetcontroller = {
                /** Holds all the strings we'll need for the comments widget. */
                strings: [],

                /** Constructor */
                init: function(args) {
                    // Get the static strings we'll need for this widget. Dynamic strings need to be fetched on the fly.
                    Str.get_strings([
                        {
                            key:        'addcomment',
                            component:  'moodle'
                        },
                        {
                            key:        'deletecomment',
                            component:  'moodle'
                        },
                        {
                            key:        'commentsrequirelogin',
                            component:  'moodle'
                        }
                    ]).then(function(strings) {
                        // Save the strings.
                        this.strings['addcomment'] = strings[0];
                        this.strings['deletecomment'] = strings[1];
                        this.strings['commentsrequirelogin'] = strings[2];

                        // Attach the toggle callback to the handle (if present).
                        this.client_id = args.client_id;
                        this.itemid = args.itemid;
                        this.commentarea = args.commentarea;
                        this.component = args.component;
                        this.courseid = args.courseid;
                        this.contextid = args.contextid;
                        this.contextlevel = args.contextlevel;
                        this.instanceid = args.instanceid;
                        this.autostart = args.autostart;
                        this.page = (args.page) ? args.page : 0; // Default to page 0.
                        this.notoggle = args.notoggle;
                        this.commentsperpage = args.commentsperpage;

                        if (this.autostart) {
                            // Templates have been rendered with the autostart flag, so show the widget.
                            // TODO: Need to separate out autostart (expanded) away from the toggler.
                            this.toggleExpanded();
                        }

                        this.registerEvents(); // Wire up the UI controls.
                        this.toggle_textarea(false);
                        return;
                    }.bind(this)).catch(Notification.exception);
                },
                toggleExpanded: function() {
                    console.log('viewing comments for page ' + this.page + '.');

                    var commenttoggler = $('#comment-link-' + this.client_id);
                    var container = $('#comment-ctrl-' + this.client_id);
                    var ta = $('#dlg-content-' + this.client_id);
                    if (!container.is(":visible")) {
                        // Update the icon.
                        templates.renderPix('t/switch_minus', 'core').then(function(result) {
                            $('#comment-toggle-' + this.client_id).html(result);
                            return;
                        }.bind(this)).catch(Notification.exception);

                        console.log('not visible, expanding for '+this.client_id);
                        // TODO: Looks like another autostart thing to check. What did autostart do in stable? and vs notoggle?
                        // TODO Surely notoggle would imply autostart, otherwise how can you see the comments?
                        if (!this.autostart) {
                            window.console.log('autostart not found');
                            this.load();
                        }

                        container.show(200);

                        // Accessibility updates.
                        if (commenttoggler) {
                            commenttoggler.attr('aria-expanded', true);
                            commenttoggler.removeClass('collapsed');
                            commenttoggler.addClass('collapsible');
                        }
                    } else {
                        // Update the icon.
                        templates.renderPix('t/switch_plus', 'core').then(function(result) {
                            $('#comment-toggle-' + this.client_id).html(result);
                        }.bind(this)).catch(Notification.exception);
                        console.log('hiding it');
                        container.hide(200);

                        // Reset the text area.
                        ta.val('');

                        // Accessibility updates.
                        commenttoggler.attr('aria-expanded', false);
                        commenttoggler.removeClass('collapsible');
                        commenttoggler.addClass('collapsed');
                    }
                    return;
                },
                /** Takes in the return data from the web service and returns a promise */
                getCommentListTemplate: function(data) {
                    var listContext = {
                        'widgetid': this.client_id,
                        'comments': data.comments,
                    }
                    return templates.render('core_comment/comments_list', listContext);
                },
                /**
                 * Takes in the return data from the create web service and returns a promise which, when resolved, will contain
                 * the template data.
                 */
                getCommentListItemTemplate: function(data) {
                    return templates.render('core_comment/comment_list_item', data);
                },
                /**
                 * Takes in the total number of comments (from ws return data) and returns a promise which, when resolved,
                 * will contain the template data.
                 */
                getPagingTemplate: function(totalComments) {
                    // Work out how many pages we have based on the current number of comments.
                    var numPages = Math.ceil((totalComments / this.commentsperpage));
                    var pages = [];
                    for (var i = 0; i < numPages; i++) {
                        pages.push(
                            {
                                page: i,
                                name: (i + 1) // Page 0 shown as '1', 1 as '2', etc.
                            }
                        );
                    }
                    var pagingContext = {
                        'pagenumbers': pages
                    };
                    return templates.render('core_comment/comments_paging', pagingContext);
                },
                /**
                 * Reloads the widget for the current page of comments.
                 */
                load: function() {
                    /* REFERENCE:
                    'contextlevel' => new external_value(PARAM_ALPHA, 'contextlevel system, course, user...'),
                    'instanceid'   => new external_value(PARAM_INT, 'the Instance id of item associated with the context level'),
                    'component'    => new external_value(PARAM_COMPONENT, 'component'),
                    'itemid'       => new external_value(PARAM_INT, 'associated id'),
                    'area'         => new external_value(PARAM_AREA, 'string comment area', VALUE_DEFAULT, ''),
                    'page'         => new external_value(PARAM_INT, 'page number (0 based)', VALUE_DEFAULT, 0),
                    */
                    console.log('loading comments for page ' + this.page + ' now');

                    // Get the comments via the 'core_comment_get_comments' web service.
                    var promises = ajax.call([{
                        methodname: 'core_comment_get_comments',
                        args: {
                            contextlevel: this.contextlevel,
                            instanceid: this.instanceid,
                            component: this.component,
                            itemid: this.itemid,
                            area: this.commentarea,
                            page: this.page,
                            returncount: true // If true, data will contain a field, 'commentcount'.
                        }
                    }], true);

                    // TODO: The below code should fire an event for 'COMMENTS_LOADED' so we can update the widget count.
                    // TODO: Really, we should wait for both the template load promises to resolve, and process the results together.
                    promises[0].then(function(data) {
                        this.getCommentListTemplate(data).then(function (result) {
                            // Add the comment list to the DOM and get the paging template.
                            $('#comment-list-' + this.client_id).html(result);
                            return this.getPagingTemplate(data.recordcount);
                        }.bind(this)).then(function (result) {
                            // Add the paging html to the DOM.
                            $('#comment-pagination-' + this.client_id).html(result);
                            return;
                        }.bind(this)).catch(Notification.exception);
                    }.bind(this)).catch(Notification.exception);
                },
                // TODO What does this fn do? Do we NEED it?
                toggle_textarea: function(focus) {
                    console.log('toggling text area');

                    var textarea = $('#dlg-content-' + this.client_id);
                    if (!textarea) {
                        return false;
                    }

                    if (focus) {
                        console.log('focus true');
                        if (textarea.val() == this.strings['addcomment']) {
                            console.log('val not empty');
                            textarea.val('');

                        }
                        textarea.css('color', 'black');
                    } else {
                        console.log('no focus');
                        if (textarea.val() == '') {
                            console.log('text area is empty');
                            textarea.val(this.strings['addcomment']);
                            textarea.css('color','grey');
                            textarea.attr('rows', 2);
                        }
                    }
                },
                /** Creates a new comment */
                createComment: function() {
                    console.log('creating a new comment');
                    var textarea = $('#dlg-content-' + this.client_id);
                    var content = textarea.val();
                    if (!content || content == this.strings['addcomment']) {
                        return;
                    }

                    var promises = ajax.call([{
                        methodname: 'core_comment_create_comment',
                        args: {
                            comment: {
                                contextlevel: this.contextlevel,
                                instanceid: this.instanceid,
                                component: this.component,
                                itemid: this.itemid,
                                area: this.commentarea,
                                content: content
                            }
                        }
                    }], true);

                    promises[0].then(function(data) {
                        // Create a new comment_list_item and add to the DOM.
                        data.widgetid = this.client_id;
                        data.delete = true;
                        this.getCommentListItemTemplate(data).then(function(result) {
                            var newcomment = $(result);
                            newcomment.hide(); //TODO: Maybe a widget global animation setting?

                            // TODO: This show is not strictly required, as we refresh the view anyway, but it makes it look nice.
                            $('#comment-list-' + this.client_id).prepend(newcomment);
                            newcomment.show('fast');

                            // TODO: should probably fire  COMMENT_CREATED event here and let the listeners update their stuff.
                             // Instead of chaining to the .then below.
                            this.updateTogglerCount(data.count);
                            textarea.val('');
                            this.toggle_textarea(false);
                            this.page = 0;
                            this.load();


                            $('#dlg-content-' + this.client_id).focus();
                            return;
                        }.bind(this)).catch(Notification.exception);
                    }.bind(this)).fail(Notification.exception);
                },
                /** Delete a single comment */
                deleteComment: function(commentid) {
                    console.log('deleting comment '+commentid);
                    // Make a web service call to core_comment_delete_comments
                    var promises = ajax.call([{
                        methodname: 'core_comment_delete_comments',
                        args: {commentids: [commentid]}
                    }], true);

                    promises[0].then(function(data) {
                        var element = $('#comment-' + commentid + '-' + this.client_id);
                        element.hide('fast', function() {
                            element.remove();
                        });


                        // TODO: Need to think about how to get the count after deletion. Another service call.
                        // TODO: Pagination might also need updating.
                        //this.updateTogglerCount(data.count);
                        this.load();
                        return;
                    }.bind(this)).catch(Notification.exception);
                },
                /** Update the string used to toggle the comment list. */
                updateTogglerCount: function (count) {
                    $('#comment-count-' + this.client_id).html(count);
                },
                /** Adds CustomEvent handlers for various interactive UI components.*/
                registerEvents: function() {
                    // Widget toggle control, only if required.
                    if (!this.notoggle) {
                        //TODO hiding the toggle should be handled elsewhere, somewhere in display/view IMO.
                        //if (this.notoggle) {
                        //   handle.hide();
                        //}

                        CustomEvents.define($('#comment-link-' + this.client_id), [CustomEvents.events.activate]);
                        $('#comment-link-' + this.client_id).on(CustomEvents.events.activate, function(e, data) {
                            // Stop event bubbling.
                            data.originalEvent.preventDefault();
                            e.preventDefault();

                            this.toggleExpanded(); // TODO maybe use the current page number here? What does stable do?
                        }.bind(this));
                    }

                    // New comment text area focus events.
                    $('#dlg-content-' + this.client_id).on('focus', function(e) {
                        e.preventDefault();
                        this.toggle_textarea(true);
                    }.bind(this));
                    $('#dlg-content-' + this.client_id).on('blur', function(e) {
                        e.preventDefault();
                        this.toggle_textarea(false);
                    }.bind(this));

                    // Comment creation button/link.
                    CustomEvents.define($('#comment-action-post-' + this.client_id), [CustomEvents.events.activate]);
                    $('#comment-action-post-' + this.client_id).on(CustomEvents.events.activate, function(e, data) {
                        // Stop event bubbling.
                        data.originalEvent.preventDefault();
                        e.preventDefault();

                        // Create a comment.
                        this.createComment();
                    }.bind(this));

                    // Cancel comment box (a comment configuration option - not always present in DOM).
                    //TODO Work out the use case for cancel and audit this code.
                    var cancel = $('#comment-action-cancel-' + this.client_id);
                    if (cancel) {
                        cancel.on('click', function(e) {
                            e.preventDefault();
                            this.toggleExpanded();
                            return false;
                        }.bind(this));
                    }

                    // Pagination controls.
                    CustomEvents.define($('[id^=comment-pagination-' + this.client_id + ']'), [CustomEvents.events.activate]);

                    // Filter so the callback is only called for <a> type children having the '.comment-page' class.
                    // The <a> element can then be accessed in the callback via e.currentTarget.
                    $('[id^=comment-pagination-' + this.client_id + ']').on(CustomEvents.events.activate, "a.comment-page",
                        function(e, data) {
                        // Stop event bubbling.
                        data.originalEvent.preventDefault();
                        e.preventDefault();

                        // Get the desired page from the clicked element.
                        var id = $(e.currentTarget).attr('id');
                        var re = new RegExp("comment-page-(\\d+)", "i");
                        var result = id.match(re);

                        // Set the current page and refresh the view.
                        this.page = result[1];
                        this.load();
                    }.bind(this));

                    // Deletion controls.
                    CustomEvents.define($('[id^=comment-list-' + this.client_id + ']'), [CustomEvents.events.activate]);
                    $('[id^=comment-list-' + this.client_id + ']').on(CustomEvents.events.activate, "a.comment-delete",
                        function(e, data) {
                        // Stop event bubbling.
                        data.originalEvent.preventDefault();
                        e.preventDefault();

                        // Get the trigger element.
                        var triggerElement = $(e.currentTarget);

                        // Get the comment id from the trigger and delete it.
                        var id = triggerElement.attr('id');
                        var re = new RegExp("comment-delete-" + this.client_id + "-(\\d+)", "i");
                        var result = id.match(re);
                        this.deleteComment(result[1]);
                    }.bind(this));
                }
            };

            // Make the magic happen.
            widgetcontroller.init(args);
        }
    };
});