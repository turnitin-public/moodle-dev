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
 * External comment API
 *
 * @package    core_comment
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/comment/lib.php");

/**
 * External comment API functions
 *
 * @package    core_comment
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_comment_external extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_comments_parameters() {
        return new external_function_parameters(
            array(
                'contextlevel' => new external_value(PARAM_ALPHA, 'contextlevel system, course, user...'),
                'instanceid'   => new external_value(PARAM_INT, 'the Instance id of item associated with the context level'),
                'component'    => new external_value(PARAM_COMPONENT, 'component'),
                'itemid'       => new external_value(PARAM_INT, 'associated id'),
                'area'         => new external_value(PARAM_AREA, 'string comment area', VALUE_DEFAULT, ''),
                'page'         => new external_value(PARAM_INT, 'page number (0 based)', VALUE_DEFAULT, 0),
                'returncount'  => new external_value(PARAM_INT, 'return record count for area', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return a list of comments
     *
     * @param string $contextlevel ('system, course, user', etc..)
     * @param int $instanceid
     * @param string $component the name of the component
     * @param int $itemid the item id
     * @param string $area comment area
     * @param int $page page number
     * @return array of comments and warnings
     * @since Moodle 2.9
     */
    public static function get_comments($contextlevel, $instanceid, $component, $itemid, $area = '', $page = 0, $returncount = 0) {

        $warnings = array();
        $arrayparams = array(
            'contextlevel' => $contextlevel,
            'instanceid'   => $instanceid,
            'component'    => $component,
            'itemid'       => $itemid,
            'area'         => $area,
            'page'         => $page,
            'returncount'  => $returncount
        );
        $params = self::validate_parameters(self::get_comments_parameters(), $arrayparams);

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        require_capability('moodle/comment:view', $context);

        $args = new stdClass;
        $args->context   = $context;
        $args->area      = $params['area'];
        $args->itemid    = $params['itemid'];
        $args->component = $params['component'];

        $commentobject = new comment($args);
        if ($returncount) {
            $commentcount = $commentobject->count();
        }
        $comments = $commentobject->get_comments($params['page']);

        // False means no permissions to see comments.
        if ($comments === false) {
            throw new moodle_exception('nopermissions', 'error', '', 'view comments');
        }

        foreach ($comments as $key => $comment) {

                list($comments[$key]->content, $comments[$key]->format) = external_format_text($comment->content,
                                                                                                 $comment->format,
                                                                                                 $context->id,
                                                                                                 $params['component'],
                                                                                                 '',
                                                                                                 0);
        }

        $results = array(
            'comments' => $comments,
        );
        if ($returncount) {
            $results['recordcount'] = $commentcount;
        }
        $results['warnings'] = $warnings;
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function get_comments_returns() {
        return new external_single_structure(
            array(
                'comments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'                => new external_value(PARAM_INT,  'Comment ID'),
                            'content'           => new external_value(PARAM_RAW,  'The content text formated'),
                            'format'            => new external_format_value('content'),
                            'timecreated'       => new external_value(PARAM_INT,  'Time created (timestamp)'),
                            'strftimeformat'    => new external_value(PARAM_NOTAGS, 'Time format'),
                            'profileurl'        => new external_value(PARAM_URL,  'URL profile'),
                            'fullname'          => new external_value(PARAM_NOTAGS, 'fullname'),
                            'time'              => new external_value(PARAM_NOTAGS, 'Time in human format'),
                            'profileimageurl'   => new external_value(PARAM_URL,  'User profile image URL'),
                            'userid'            => new external_value(PARAM_INT,  'User ID'),
                            'delete'            => new external_value(PARAM_BOOL, 'Permission to delete=true/false', VALUE_OPTIONAL)
                        ), 'comment'
                    ), 'List of comments'
                ),
                'recordcount' => new external_value(PARAM_INT,  'Total number of comments for this comment area',  VALUE_OPTIONAL),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function delete_comments_parameters() {
        return new external_function_parameters(
            array(
                'commentids' => new external_multiple_structure(new external_value(PARAM_INT, 'comment ID')),
            )
        );
    }

    /**
     * Delete a group of comments, as specified by an array of comment ids.
     * If one or more of the comments cannot be deleted by the current user, then none of the comments will be deleted.
     *
     * @throws moodle_exception
     * @param array $commentids the array of comment ids.
     * @return bool true on successful deletion, false otherwise.
     */
    public static function delete_comments($commentids) {
        global $CFG, $DB;
        require_once($CFG->dirroot."/comment/locallib.php");

        $params = self::validate_parameters(self::delete_comments_parameters(), array('commentids' => $commentids));

        // TODO: Add this get_comment call to lib.php or the comment manager.
        list($sql, $sqlparams) = $DB->get_in_or_equal($params['commentids']);
        $comments = $DB->get_records_select('comments', 'id '.$sql, $sqlparams, '', '*');

        // Ensure the user can delete each of the specified comments. Exception will be thrown if not allowed.
        foreach ($comments as $cid => $comment) {
            exec('echo "TEST" >> /tmp/test.txt');
            // TODO why we need to instantiate a comment to check this? Seem like a manager job to me.
            $context = context::instance_by_id($comment->contextid, MUST_EXIST);
            self::validate_context($context);
            $args = new stdClass;
            $args->context   = $context;
            $args->component = $comment->component;
            $args->itemid    = $comment->itemid;
            $args->area      = $comment->commentarea;
            $cmt = new comment($args);
            if ($cmt->can_delete($cid)) {
                continue;
            }
        }

        // User is allowed to delete all comments. Make it so.
        $commentmanager = new comment_manager();
        return $commentmanager->delete_comments(implode('-', $params['commentids']));
    }

    /**
     * Returns description of method result value
     *
     * @return null
     */
    public static function delete_comments_returns() {
        return new external_value(PARAM_BOOL, 'True if the deletion was successful');
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function create_comment_parameters() {
        return new external_function_parameters(
            array(
                'comment' =>  new external_single_structure(
                    array(
                        'contextlevel' => new external_value(PARAM_ALPHA, 'contextlevel system, course, user...'),
                        'instanceid'   => new external_value(PARAM_INT, 'the Instance id of item associated with the context level'),
                        'component'    => new external_value(PARAM_COMPONENT, 'component'),
                        'itemid'       => new external_value(PARAM_INT, 'associated id'),
                        'area'         => new external_value(PARAM_AREA, 'string comment area', VALUE_DEFAULT, ''),
                        'content'      => new external_value(PARAM_RAW, 'raw comment text', VALUE_DEFAULT, 0)
                    ), 'comment'
                )
            )
        );
    }

    /**
     * Creates a comment.
     *
     * @throws moodle_exception
     * @param array $comment contains the fields needed to create a comment.
     * @return stdClass the new comment.
     */
    public static function create_comment($comment) {
        $params = self::validate_parameters(self::create_comment_parameters(), ['comment' => $comment]);
        $context = self::get_context_from_params($params['comment']);
        self::validate_context($context);
        $parsedcomment = $params['comment'];

        // Create the comment object.
        $args = new stdClass;
        $args->context   = $context;
        $args->component = $parsedcomment['component'];
        $args->itemid    = $parsedcomment['itemid'];
        $args->area      = $parsedcomment['area'];

        // TODO: Remove these: AFAICT, they're just rubbish left over from comment_ajax.php - never persisted. So why have them?
        //$args->client_id = $comment['client_id'];
        //$args->course    = $comment['course'];
        //$args->cm        = $comment['cm'];

        // TODO: I hate how the comment class also has 'manager' responsibilities.
        // TODO: Why not leave this type of stuff to the mgr/util to handle and leave comment as just a persistible?
        $manager = new comment($args);
        if ($manager->can_post()) {
            $result = $manager->add($parsedcomment['content']);
            if (!empty($result) && is_object($result)) {
                // TODO: The caller (if it's Moodle application) should know it's own client_id so we might be able to remove this.
                //$result->client_id = $comment->client_id;

                // TODO: Do we really want to return a count as well as the record? It would make skipping to the new page easier.
                // I think Delete is useful though, we might be able to create and not delete (not sure why, but possible).
                $result->count = $manager->count();
                $result->delete = $manager->can_delete($result->id);

                return $result;
            }
        }
    }

    /**
    * Returns the newly created comment.
    *
    * @return null
    */
    public static function create_comment_returns() {
        return new external_single_structure(
            array(
                'id'                => new external_value(PARAM_INT,  'Comment ID'),
                'content'           => new external_value(PARAM_RAW,  'The content text formated'),
                'format'            => new external_format_value('content'),
                'timecreated'       => new external_value(PARAM_INT,  'Time created (timestamp)'),
                'strftimeformat'    => new external_value(PARAM_NOTAGS, 'Time format'),
                'profileurl'        => new external_value(PARAM_URL,  'URL profile'),
                'fullname'          => new external_value(PARAM_NOTAGS, 'fullname'),
                'time'              => new external_value(PARAM_NOTAGS, 'Time in human format'),
                'profileimageurl'   => new external_value(PARAM_URL,  'URL user picture'),
                'userid'            => new external_value(PARAM_INT,  'User ID'),
                'count'             => new external_value(PARAM_INT,  'Count comments in this context area'),
                'delete'            => new external_value(PARAM_BOOL, 'Permission to delete=true/false', VALUE_OPTIONAL)
            ), 'comment'
        );
    }

}
