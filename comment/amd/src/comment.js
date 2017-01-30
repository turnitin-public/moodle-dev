// Standard license block omitted.
/*
 * @package    core_comment
 * @copyright  2017 Jake Dallimore <jrhdallimore@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module core_comment/comment
 */
define(['jquery', 'core/str', 'core/config', 'core/modal_factory', 'core/modal_events', 'core/ajax', 'core/templates'],
    function($, str, Cfg, ModalFactory, ModalEvents, ajax, templates) {

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
            str.get_string('confirmdeletecomments', 'admin')
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
                    str.get_strings([
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
                    ]).done(function(strings) {
                        // Save the strings.
                        //TODO: can we automate this string assignment?
                        this.strings['addcomment'] = strings[0];
                        this.strings['deletecomment'] = strings[1];
                        this.strings['commentsrequirelogin'] = strings[2];
                        console.log('strings fetched are:');
                        console.log(this.strings);

                        // Attach the toggle callback to the handle (if present).
                        console.log(args);
                        this.client_id = args.client_id;
                        this.itemid = args.itemid;
                        this.commentarea = args.commentarea;
                        this.component = args.component;
                        this.courseid = args.courseid;
                        this.contextid = args.contextid;
                        this.contextlevel = args.contextlevel;
                        this.instanceid = args.instanceid;
                        this.autostart = (args.autostart);
                        this.page = args.page;
                        this.notoggle = args.notoggle;

                        // Expand comments by default?
                        if (this.autostart) {
                            this.view(args.page);
                        }

                        // TODO - make sure that register actions can be placed here - I moved it to stop if being called so much
                        // TODO : from the view method.
                        this.register_toggler();
                        this.register_actions();
                        this.toggle_textarea(false);
                    }.bind(this));
                },
                view: function(pagenum) {
                    console.log('viewing comments for page ' + pagenum + '!');

                    var commenttoggler = $('#comment-link-' + this.client_id);
                    var container = $('#comment-ctrl-' + this.client_id);
                    var ta = $('#dlg-content-' + this.client_id);
                    var img = $('#comment-img-' + this.client_id);
                    var display = container.css('display');
                    if (display == 'none' || display == '') {
                        console.log('showing comments');
                        // Show.
                        if (!this.autostart) {
                            this.load(page);
                        } else {
                            this.register_delete_buttons();
                            this.register_pagination();
                        }
                        container.show(300);
                        // TODO: use background image instead - see local commit on another issue.
                        /*if (img) {
                            img.set('src', M.util.image_url('t/expanded', 'core'));
                        }*/
                        if (commenttoggler) {
                            commenttoggler.attr('aria-expanded', true);
                            commenttoggler.removeClass('collapsed');
                            commenttoggler.addClass('collapsible');
                        }
                    } else {
                        // Hide.
                        container.hide(200);
                        // TODO RTL hacks here = yucky! Do this in CSS!!
                        //var collapsedimage = 't/collapsed'; // ltr mode
                        /*if ($(document.body).hasClass('dir-rtl')) {
                            collapsedimage = 't/collapsed_rtl';
                        } else {
                            collapsedimage = 't/collapsed';
                        }
                        img.attr('src', M.util.image_url(collapsedimage, 'core'));
                        */
                        if (ta) {
                            ta.val('');
                        }
                        if (commenttoggler) {
                            commenttoggler.attr('aria-expanded', false);
                            commenttoggler.removeClass('collapsible');
                            commenttoggler.addClass('collapsed');
                        }
                    }
                    if (ta) {
                        ta.on('focus', function() {
                            console.log('textarea focus callback');
                            this.toggle_textarea(true);
                        }.bind(this));
                        ta.on('blur', function() {
                            this.toggle_textarea(false);
                        }.bind(this));
                    }

                    return false;
                },
                load: function() {
                    /*
                    'contextlevel' => new external_value(PARAM_ALPHA, 'contextlevel system, course, user...'),
                    'instanceid'   => new external_value(PARAM_INT, 'the Instance id of item associated with the context level'),
                    'component'    => new external_value(PARAM_COMPONENT, 'component'),
                    'itemid'       => new external_value(PARAM_INT, 'associated id'),
                    'area'         => new external_value(PARAM_AREA, 'string comment area', VALUE_DEFAULT, ''),
                    'page'         => new external_value(PARAM_INT, 'page number (0 based)', VALUE_DEFAULT, 0),
                    */
                    console.log('loading comments now');
                    // Get the comments via the 'core_comment_get_comments' web service.
                    var promises = ajax.call([{
                        methodname: 'core_comment_get_comments',
                        args: {
                            contextlevel: this.contextlevel,
                            instanceid: this.instanceid,
                            component: this.component,
                            itemid: this.itemid,
                            area: this.commentarea,
                            page: this.page
                        }
                    }], true);

                    $.when.apply($, promises)
                    .done(function(data) {
                        //TODO: How do we load/update the pagination section now that we're using the web service and not the ajax.php?
                        console.log('service call complete');

                        // Get the template and render in the DOM.
                        var context = {
                            'widgetid': this.client_id,
                            'dummytest': "dog",
                            'comments': data.comments
                        }
                        templates.render('core_comment/comments_list', context)
                            .done(function(source, javascript) {
                                console.log('done fetching template');

                                // Add the template html to the DOM.
                                var container = $('#comment-list-' + this.client_id);
                                container.html(source);

                                //  TODO: Is this the right place for these fns to be located? Called on every load? hmmmm.
                                this.register_pagination();
                                this.register_delete_buttons();

                                // Reload the link count in case it has changed.
                                // TODO: as above - need the total comment count.
                                //this.update_toggler_string(count);

                                //templates.runTemplateJS(javascript); // Don't have any JS in the template. It's all here.
                            }.bind(this))
                            .fail(function(ex) {
                                // TODO core/notify exception for this.
                            });
                    }.bind(this));
                },
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
                            console.log('val is empty');
                            textarea.val(this.strings['addcomment']);
                            textarea.css('color','grey');
                            textarea.attr('rows', 2);
                        }
                    }
                },
                /** Creates a new comment */
                create_comment: function() {
                    console.log('creating a new comment');
                    var textarea = $('#dlg-content-' + this.client_id);
                    var content = textarea.val();
                    if(!content || content == this.strings['addcomment']) {
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

                    $.when.apply($, promises)
                        .done(function(data) {
                            console.log('new comment created');
                            var container = $('#comment-list-' + this.client_id);

                            // Populate and render the 'core_comment/comment_list_item' template.
                            data.widgetid = this.client_id;
                            templates.render('core_comment/comment_list_item', data)
                                .done(function(source, javascript) {
                                    var container = $('#comment-list-' + this.client_id);
                                    var newcomment = $(source);
                                    newcomment.hide();
                                    container.append(newcomment);
                                    newcomment.show(100);
                                    // TODO: Why remap all events? Can't we just add the event handler for the new comment?
                                    this.register_delete_buttons(); // Re-map all events for the delete buttons.

                                    // Update the comment count link text.
                                    this.update_toggler_string(data.count);

                                    textarea.val('');
                                    this.toggle_textarea(false);

                                    //templates.runTemplateJS(javascript); // Don't have any JS in the template. It's all here.
                                }.bind(this))
                                .fail(function(ex) {
                                    // Deal with this exception (I recommend core/notify exception function for this).
                                });
                        }.bind(this))
                        .fail(function(e) {
                            // TODO: Core notification when comment creation fails.
                        });
                },
                /** Delete a single comment */
                delete_comment: function(commentid) {
                    console.log('deleting comment '+commentid);
                    // Make a web service call to core_comment_delete_comments
                    var promises = ajax.call([{
                        methodname: 'core_comment_delete_comments',
                        args: {commentids: [commentid]}
                    }], true);

                    $.when.apply($, promises)
                    .done(function(data) {
                        var htmlid = 'comment-' + commentid + '-' + this.client_id;
                        var element = $('#' + htmlid);
                        element.hide('fast', function() {
                            element.remove();
                        });

                        // Update the comment count link text.
                        //TODO: Need to think about how to get the count after deletion. Another service call?
                        //this.update_toggler_string(data.count);
                    }.bind(this));
                },
                /** Update the string used to toggle the comment list. */
                update_toggler_string: function (count) {
                    str.get_string('commentscount', 'moodle', count)
                        .done(function(string) {
                            var linkText = $('#comment-link-text-' + this.client_id);
                            if (linkText) {
                                linkText.html(string);
                            }
                        }.bind(this));
                },
                /** Adds handlers for the textarea actions 'Add comment' and 'Cancel'. */
                register_actions: function() {
                    console.log('registration of comment actions');
                    // Add new comment.
                    var action_btn = $('#comment-action-post-' + this.client_id);
                    if (action_btn) {
                        action_btn.on('click', function(e) {
                            console.log('new comment callback');
                            e.preventDefault();
                            this.create_comment();
                            return false;
                        }.bind(this));
                    }
                    // Cancel comment box (a comment configuration option - not always present in DOM).
                    var cancel = $('#comment-action-cancel-' + this.client_id);
                    if (cancel) {
                        cancel.on('click', function(e) {
                            e.preventDefault();
                            this.view(0);
                            return false;
                        }.bind(this));
                    }
                },
                /** Adds handlers to the delete icons for each comment. */
                register_delete_buttons: function() {
                    console.log('registering delete handlers');
                    $('div.comment-delete a').each(function(id, elem) {

                        elem = $(elem);
                        var theid = elem.attr('id');
                        var parseid = new RegExp("comment-delete-" + this.client_id + "-(\\d+)", "i");
                        var commentid = theid.match(parseid);
                        if (!commentid || !commentid[1]) {
                            return;
                        }

                        // TODO: Restructure this code so that we're not reassigning all events every time we add a comment.
                        // Strip all existing event listeners from the deletion link.
                        elem.off('click');
                        elem.off('keypress');

                        var deletehandler = function(e) {
                            console.log('in delete handler');
                            e.preventDefault();
                            this.delete_comment(commentid[1]);
                        };
                        elem.on('click', deletehandler.bind(this));
                        elem.on('keypress', function(e) {
                            if ($.inArray(e.which, [32])) { // Enter key binding.
                                deletehandler.bind(this);
                            }
                        });
                    }.bind(this));
                },
                /** Handlers for the page buttons. */
                register_pagination: function() {
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
                },
                register_toggler: function(notoggle) {
                    var handle = $('#comment-link-' + this.client_id);
                    if (handle) {
                        if (notoggle) {
                            handle.hide();
                        }
                        handle.on('click', function(e) {
                            e.preventDefault();
                            this.view(0);
                            //return false;
                        }.bind(this));
                    }
                }
            };

            // Make the magic happen.
            widgetcontroller.init(args);
        }
    };
});
