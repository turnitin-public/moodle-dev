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

namespace core_ltix;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/ltix/constants.php');

use core_ltix\local\ltiopenid\registration_helper;
use core_component;
use core_text;
use curl;
use DateTime;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Helper class specifically dealing with LTI tools.
 *
 * @package    core_ltix
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    public static function get_tools_by_domain($domain, $state = null, $courseid = null) {
        global $DB, $SITE;

        $statefilter = '';
        $coursefilter = '';

        if ($state) {
            $statefilter = 'AND t.state = :state';
        }

        if ($courseid && $courseid != $SITE->id) {
            $coursefilter = 'OR t.course = :courseid';
        }

        $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
        $query = "SELECT t.*
                FROM {lti_types} t
           LEFT JOIN {lti_types_categories} tc on t.id = tc.typeid
               WHERE t.tooldomain = :tooldomain
                 AND (t.course = :siteid $coursefilter)
                 $statefilter
                 AND (tc.id IS NULL OR tc.categoryid = :categoryid)";

        return $DB->get_records_sql($query, [
            'courseid' => $courseid,
            'siteid' => $SITE->id,
            'tooldomain' => $domain,
            'state' => $state,
            'categoryid' => $coursecategory
        ]);
    }

    public static function get_domain_from_url($url) {
        $matches = array();

        if (preg_match(LTI_URL_DOMAIN_REGEX, $url ?? '', $matches)) {
            return $matches[1];
        }
    }

    public static function get_tools_by_url($url, $state, $courseid = null) {
        $domain = self::get_domain_from_url($url);

        return self::get_tools_by_domain($domain, $state, $courseid);
    }

    public static function get_tool_by_url_match($url, $courseid = null, $state = LTI_TOOL_STATE_CONFIGURED) {
        $possibletools = self::get_tools_by_url($url, $state, $courseid);

        return self::get_best_tool_by_url($url, $possibletools, $courseid);
    }

    public static function get_best_tool_by_url($url, $tools, $courseid = null) {
        if (count($tools) === 0) {
            return null;
        }

        $urllower = self::get_url_thumbprint($url);

        foreach ($tools as $tool) {
            $tool->_matchscore = 0;

            $toolbaseurllower = self::get_url_thumbprint($tool->baseurl);

            if ($urllower === $toolbaseurllower) {
                // 100 points for exact thumbprint match.
                $tool->_matchscore += 100;
            } else if (substr($urllower, 0, strlen($toolbaseurllower)) === $toolbaseurllower) {
                // 50 points if tool thumbprint starts with the base URL thumbprint.
                $tool->_matchscore += 50;
            }

            // Prefer course tools over site tools.
            if (!empty($courseid)) {
                // Minus 10 points for not matching the course id (global tools).
                if ($tool->course != $courseid) {
                    $tool->_matchscore -= 10;
                }
            }
        }

        $bestmatch = array_reduce($tools, function($value, $tool) {
            if ($tool->_matchscore > $value->_matchscore) {
                return $tool;
            } else {
                return $value;
            }

        }, (object) array('_matchscore' => -1));

        // None of the tools are suitable for this URL.
        if ($bestmatch->_matchscore <= 0) {
            return null;
        }

        return $bestmatch;
    }

    public static function get_url_thumbprint($url) {
        // Parse URL requires a schema otherwise everything goes into 'path'.  Fixed 5.4.7 or later.
        if (preg_match('/https?:\/\//', $url) !== 1) {
            $url = 'http://' . $url;
        }
        $urlparts = parse_url(strtolower($url));
        if (!isset($urlparts['path'])) {
            $urlparts['path'] = '';
        }

        if (!isset($urlparts['query'])) {
            $urlparts['query'] = '';
        }

        if (!isset($urlparts['host'])) {
            $urlparts['host'] = '';
        }

        if (substr($urlparts['host'], 0, 4) === 'www.') {
            $urlparts['host'] = substr($urlparts['host'], 4);
        }

        $urllower = $urlparts['host'] . '/' . $urlparts['path'];

        if ($urlparts['query'] != '') {
            $urllower .= '?' . $urlparts['query'];
        }

        return $urllower;
    }

    /**
     * Update the database with a tool proxy instance
     *
     * @param object $config Tool proxy definition
     *
     * @return int  Record id number
     */
    public static function add_tool_proxy($config) {
        global $USER, $DB;

        $toolproxy = new \stdClass();
        if (isset($config->lti_registrationname)) {
            $toolproxy->name = trim($config->lti_registrationname);
        }
        if (isset($config->lti_registrationurl)) {
            $toolproxy->regurl = trim($config->lti_registrationurl);
        }
        if (isset($config->lti_capabilities)) {
            $toolproxy->capabilityoffered = implode("\n", $config->lti_capabilities);
        } else {
            $toolproxy->capabilityoffered = implode("\n", array_keys(self::get_capabilities()));
        }
        if (isset($config->lti_services)) {
            $toolproxy->serviceoffered = implode("\n", $config->lti_services);
        } else {
            $func = function($s) {
                return $s->get_id();
            };
            $servicenames = array_map($func, self::get_services());
            $toolproxy->serviceoffered = implode("\n", $servicenames);
        }
        if (isset($config->toolproxyid) && !empty($config->toolproxyid)) {
            $toolproxy->id = $config->toolproxyid;
            if (!isset($toolproxy->state) || ($toolproxy->state != LTI_TOOL_PROXY_STATE_ACCEPTED)) {
                $toolproxy->state = LTI_TOOL_PROXY_STATE_CONFIGURED;
                $toolproxy->guid = random_string();
                $toolproxy->secret = random_string();
            }
            $id = ::update_tool_proxy($toolproxy);
        } else {
            $toolproxy->state = LTI_TOOL_PROXY_STATE_CONFIGURED;
            $toolproxy->timemodified = time();
            $toolproxy->timecreated = $toolproxy->timemodified;
            if (!isset($toolproxy->createdby)) {
                $toolproxy->createdby = $USER->id;
            }
            $toolproxy->guid = random_string();
            $toolproxy->secret = random_string();
            $id = $DB->insert_record('lti_tool_proxies', $toolproxy);
        }

        return $id;
    }

    /**
     * Initializes an array with the services supported by the LTI module
     *
     * @return array List of services
     */
    public static function get_services() {
        $services = array();
        $definedservices = core_component::get_plugin_list('ltiservice');
        foreach ($definedservices as $name => $location) {
            $classname = "\\ltiservice_{$name}\\local\\service\\{$name}";
            $services[] = new $classname();
        }

        return $services;
    }

    /**
     * Updates a tool proxy in the database
     *
     * @param object $toolproxy Tool proxy
     *
     * @return int    Record id number
     */
    public static function update_tool_proxy($toolproxy) {
        global $DB;

        $toolproxy->timemodified = time();
        $id = $DB->update_record('lti_tool_proxies', $toolproxy);

        return $id;
    }

    /**
     * Delete a Tool Proxy
     *
     * @param int $id   Tool Proxy id
     */
    public static function delete_tool_proxy($id) {
        global $DB;
        $DB->delete_records('lti_tool_settings', array('toolproxyid' => $id));
        $tools = $DB->get_records('lti_types', array('toolproxyid' => $id));
        foreach ($tools as $tool) {
            self::delete_type($tool->id);
        }
        $DB->delete_records('lti_tool_proxies', array('id' => $id));
    }

    /**
     * Get SQL to query DB for LTI tool proxy records.
     *
     * @param bool $orphanedonly If true, return SQL to get orphaned proxies only.
     * @param bool $count If true, return SQL to get the count of the records instead of the records themselves.
     * @return string SQL.
     */
    public static function get_tool_proxy_sql(bool $orphanedonly = false, bool $count = false): string {
        if ($count) {
            $select = "SELECT count(*) as type_count";
            $sort = "";
        } else {
            // We only want the fields from lti_tool_proxies table. Must define every column to be compatible with mysqli.
            $select = "SELECT ltp.id, ltp.name, ltp.regurl, ltp.state, ltp.guid, ltp.secret, ltp.vendorcode,
                              ltp.capabilityoffered, ltp.serviceoffered, ltp.toolproxy, ltp.createdby,
                              ltp.timecreated, ltp.timemodified";
            $sort = " ORDER BY ltp.name ASC, ltp.state DESC, ltp.timemodified DESC";
        }
        $from = " FROM {lti_tool_proxies} ltp";
        if ($orphanedonly) {
            $join = " LEFT JOIN {lti_types} lt ON ltp.id = lt.toolproxyid";
            $where = " WHERE lt.toolproxyid IS null";
        } else {
            $join = "";
            $where = "";
        }

        return $select . $from . $join . $where . $sort;
    }

    /**
     * This function builds the request that must be sent to an LTI 2 tool provider
     *
     * @param object    $tool           Basic LTI tool object
     * @param array     $params         Custom launch parameters
     *
     * @return array                    Request details
     */
    public static function build_request_lti2($tool, $params) {

        $requestparams = array();

        $capabilities = self::get_capabilities();
        $enabledcapabilities = explode("\n", $tool->enabledcapability);
        foreach ($enabledcapabilities as $capability) {
            if (array_key_exists($capability, $capabilities)) {
                $val = $capabilities[$capability];
                if ($val && (substr($val, 0, 1) != '$')) {
                    if (isset($params[$val])) {
                        $requestparams[$capabilities[$capability]] = $params[$capabilities[$capability]];
                    }
                }
            }
        }

        return $requestparams;

    }

    /**
     * Processes the tool provider's response to the ContentItemSelectionRequest and builds the configuration data from the
     * selected content item. This configuration data can be then used when adding a tool into the course.
     *
     * @param int $typeid The tool type ID.
     * @param string $messagetype The value for the lti_message_type parameter.
     * @param string $ltiversion The value for the lti_version parameter.
     * @param string $consumerkey The consumer key.
     * @param string $contentitemsjson The JSON string for the content_items parameter.
     * @return stdClass The array of module information objects.
     * @throws moodle_exception
     * @throws moodle\mod\lti\OAuthException
     */
    public static function tool_configuration_from_content_item($typeid, $messagetype, $ltiversion, $consumerkey, $contentitemsjson) {
        $tool = self::get_type($typeid);
        // Validate parameters.
        if (!$tool) {
            throw new moodle_exception('errortooltypenotfound', 'core_ltix');
        }
        // Check lti_message_type. Show debugging if it's not set to ContentItemSelection.
        // No need to throw exceptions for now since lti_message_type does not seem to be used in this processing at the moment.
        if ($messagetype !== 'ContentItemSelection') {
            debugging("lti_message_type is invalid: {$messagetype}. It should be set to 'ContentItemSelection'.",
                DEBUG_DEVELOPER);
        }

        // Check LTI versions from our side and the response's side. Show debugging if they don't match.
        // No need to throw exceptions for now since LTI version does not seem to be used in this processing at the moment.
        $expectedversion = $tool->ltiversion;
        $islti2 = ($expectedversion === LTI_VERSION_2);
        if ($ltiversion !== $expectedversion) {
            debugging("lti_version from response does not match the tool's configuration. Tool: {$expectedversion}," .
                " Response: {$ltiversion}", DEBUG_DEVELOPER);
        }

        $items = json_decode($contentitemsjson);
        if (empty($items)) {
            throw new moodle_exception('errorinvaliddata', 'core_ltix', '', $contentitemsjson);
        }
        if (!isset($items->{'@graph'}) || !is_array($items->{'@graph'})) {
            throw new moodle_exception('errorinvalidresponseformat', 'core_ltix');
        }

        $config = null;
        $items = $items->{'@graph'};
        if (!empty($items)) {
            $typeconfig = self::get_type_type_config($tool->id);
            if (count($items) == 1) {
                $config = self::content_item_to_form($tool, $typeconfig, $items[0]);
            } else {
                $multiple = [];
                foreach ($items as $item) {
                    $multiple[] = self::content_item_to_form($tool, $typeconfig, $item);
                }
                $config = new stdClass();
                $config->multiple = $multiple;
            }
        }
        return $config;
    }

    /**
     * Converts LTI 1.1 Content Item for LTI Link to Form data.
     *
     * @param object $tool Tool for which the item is created for.
     * @param object $typeconfig The tool configuration.
     * @param object $item Item populated from JSON to be converted to Form form
     *
     * @return stdClass Form config for the item
     */
    public static function content_item_to_form(object $tool, object $typeconfig, object $item) : stdClass {
        global $OUTPUT;

        $config = new stdClass();
        $config->name = '';
        if (isset($item->title)) {
            $config->name = $item->title;
        }
        if (empty($config->name)) {
            $config->name = $tool->name;
        }
        if (isset($item->text)) {
            $config->introeditor = [
                'text' => $item->text,
                'format' => FORMAT_PLAIN
            ];
        } else {
            $config->introeditor = [
                'text' => '',
                'format' => FORMAT_PLAIN
            ];
        }
        if (isset($item->icon->{'@id'})) {
            $iconurl = new moodle_url($item->icon->{'@id'});
            // Assign item's icon URL to secureicon or icon depending on its scheme.
            if (strtolower($iconurl->get_scheme()) === 'https') {
                $config->secureicon = $iconurl->out(false);
            } else {
                $config->icon = $iconurl->out(false);
            }
        }
        if (isset($item->url)) {
            $url = new moodle_url($item->url);
            $config->toolurl = $url->out(false);
            $config->typeid = 0;
        } else {
            $config->typeid = $tool->id;
        }
        $config->instructorchoiceacceptgrades = LTI_SETTING_NEVER;
        $islti2 = $tool->ltiversion === LTI_VERSION_2;
        if (!$islti2 && isset($typeconfig->lti_acceptgrades)) {
            $acceptgrades = $typeconfig->lti_acceptgrades;
            if ($acceptgrades == LTI_SETTING_ALWAYS) {
                // We create a line item regardless if the definition contains one or not.
                $config->instructorchoiceacceptgrades = LTI_SETTING_ALWAYS;
                $config->grade_modgrade_point = 100;
            }
            if ($acceptgrades == LTI_SETTING_DELEGATE || $acceptgrades == LTI_SETTING_ALWAYS) {
                if (isset($item->lineItem)) {
                    $lineitem = $item->lineItem;
                    $config->instructorchoiceacceptgrades = LTI_SETTING_ALWAYS;
                    $maxscore = 100;
                    if (isset($lineitem->scoreConstraints)) {
                        $sc = $lineitem->scoreConstraints;
                        if (isset($sc->totalMaximum)) {
                            $maxscore = $sc->totalMaximum;
                        } else if (isset($sc->normalMaximum)) {
                            $maxscore = $sc->normalMaximum;
                        }
                    }
                    $config->grade_modgrade_point = $maxscore;
                    $config->lineitemresourceid = '';
                    $config->lineitemtag = '';
                    $config->lineitemsubreviewurl = '';
                    $config->lineitemsubreviewparams = '';
                    if (isset($lineitem->assignedActivity) && isset($lineitem->assignedActivity->activityId)) {
                        $config->lineitemresourceid = $lineitem->assignedActivity->activityId?:'';
                    }
                    if (isset($lineitem->tag)) {
                        $config->lineitemtag = $lineitem->tag?:'';
                    }
                    if (isset($lineitem->submissionReview)) {
                        $subreview = $lineitem->submissionReview;
                        $config->lineitemsubreviewurl = 'DEFAULT';
                        if (!empty($subreview->url)) {
                            $config->lineitemsubreviewurl = $subreview->url;
                        }
                        if (isset($subreview->custom)) {
                            $config->lineitemsubreviewparams = params_to_string($subreview->custom);
                        }
                    }
                }
            }
        }
        $config->instructorchoicesendname = LTI_SETTING_NEVER;
        $config->instructorchoicesendemailaddr = LTI_SETTING_NEVER;

        // Since 4.3, the launch container is dictated by the value set in tool configuration and isn't controllable by content items.
        $config->launchcontainer = LTI_LAUNCH_CONTAINER_DEFAULT;

        if (isset($item->custom)) {
            $config->instructorcustomparameters = params_to_string($item->custom);
        }

        // Set the status, allowing the form to validate, and pass an indicator to the relevant form field.
        $config->selectcontentstatus = true;
        $config->selectcontentindicator = $OUTPUT->pix_icon('i/valid', get_string('yes')) . get_string('contentselected', 'ltix');

        return $config;
    }

    /**
     * Converts the new Deep-Linking format for Content-Items to the old format.
     *
     * @param string $param JSON string representing new Deep-Linking format
     * @return string  JSON representation of content-items
     */
    public static function convert_content_items($param) {
        $items = array();
        $json = json_decode($param);
        if (!empty($json) && is_array($json)) {
            foreach ($json as $item) {
                if (isset($item->type)) {
                    $newitem = clone $item;
                    switch ($item->type) {
                        case 'ltiResourceLink':
                            $newitem->{'@type'} = 'LtiLinkItem';
                            $newitem->mediaType = 'application\/vnd.ims.lti.v1.ltilink';
                            break;
                        case 'link':
                        case 'rich':
                            $newitem->{'@type'} = 'ContentItem';
                            $newitem->mediaType = 'text/html';
                            break;
                        case 'file':
                            $newitem->{'@type'} = 'FileItem';
                            break;
                    }
                    unset($newitem->type);
                    if (isset($item->html)) {
                        $newitem->text = $item->html;
                        unset($newitem->html);
                    }
                    if (isset($item->iframe)) {
                        // DeepLinking allows multiple options to be declared as supported.
                        // We favor iframe over new window if both are specified.
                        $newitem->placementAdvice = new stdClass();
                        $newitem->placementAdvice->presentationDocumentTarget = 'iframe';
                        if (isset($item->iframe->width)) {
                            $newitem->placementAdvice->displayWidth = $item->iframe->width;
                        }
                        if (isset($item->iframe->height)) {
                            $newitem->placementAdvice->displayHeight = $item->iframe->height;
                        }
                        unset($newitem->iframe);
                        unset($newitem->window);
                    } else if (isset($item->window)) {
                        $newitem->placementAdvice = new stdClass();
                        $newitem->placementAdvice->presentationDocumentTarget = 'window';
                        if (isset($item->window->targetName)) {
                            $newitem->placementAdvice->windowTarget = $item->window->targetName;
                        }
                        if (isset($item->window->width)) {
                            $newitem->placementAdvice->displayWidth = $item->window->width;
                        }
                        if (isset($item->window->height)) {
                            $newitem->placementAdvice->displayHeight = $item->window->height;
                        }
                        unset($newitem->window);
                    } else if (isset($item->presentation)) {
                        // This may have been part of an early draft but is not in the final spec
                        // so keeping it around for now in case it's actually been used.
                        $newitem->placementAdvice = new stdClass();
                        if (isset($item->presentation->documentTarget)) {
                            $newitem->placementAdvice->presentationDocumentTarget = $item->presentation->documentTarget;
                        }
                        if (isset($item->presentation->windowTarget)) {
                            $newitem->placementAdvice->windowTarget = $item->presentation->windowTarget;
                        }
                        if (isset($item->presentation->width)) {
                            $newitem->placementAdvice->dislayWidth = $item->presentation->width;
                        }
                        if (isset($item->presentation->height)) {
                            $newitem->placementAdvice->dislayHeight = $item->presentation->height;
                        }
                        unset($newitem->presentation);
                    }
                    if (isset($item->icon) && isset($item->icon->url)) {
                        $newitem->icon->{'@id'} = $item->icon->url;
                        unset($newitem->icon->url);
                    }
                    if (isset($item->thumbnail) && isset($item->thumbnail->url)) {
                        $newitem->thumbnail->{'@id'} = $item->thumbnail->url;
                        unset($newitem->thumbnail->url);
                    }
                    if (isset($item->lineItem)) {
                        unset($newitem->lineItem);
                        $newitem->lineItem = new stdClass();
                        $newitem->lineItem->{'@type'} = 'LineItem';
                        $newitem->lineItem->reportingMethod = 'http://purl.imsglobal.org/ctx/lis/v2p1/Result#totalScore';
                        if (isset($item->lineItem->label)) {
                            $newitem->lineItem->label = $item->lineItem->label;
                        }
                        if (isset($item->lineItem->resourceId)) {
                            $newitem->lineItem->assignedActivity = new stdClass();
                            $newitem->lineItem->assignedActivity->activityId = $item->lineItem->resourceId;
                        }
                        if (isset($item->lineItem->tag)) {
                            $newitem->lineItem->tag = $item->lineItem->tag;
                        }
                        if (isset($item->lineItem->scoreMaximum)) {
                            $newitem->lineItem->scoreConstraints = new stdClass();
                            $newitem->lineItem->scoreConstraints->{'@type'} = 'NumericLimits';
                            $newitem->lineItem->scoreConstraints->totalMaximum = $item->lineItem->scoreMaximum;
                        }
                        if (isset($item->lineItem->submissionReview)) {
                            $newitem->lineItem->submissionReview = $item->lineItem->submissionReview;
                        }
                    }
                    $items[] = $newitem;
                }
            }
        }

        $newitems = new stdClass();
        $newitems->{'@context'} = 'http://purl.imsglobal.org/ctx/lti/v1/ContentItem';
        $newitems->{'@graph'} = $items;

        return json_encode($newitems);
    }

    /**
     * Extracts the enabled capabilities into an array, including those implicitly declared in a parameter
     *
     * @param object $tool  Tool instance object
     *
     * @return array List of enabled capabilities
     */
    public static function get_enabled_capabilities($tool) {
        if (!isset($tool)) {
            return array();
        }
        if (!empty($tool->enabledcapability)) {
            $enabledcapabilities = explode("\n", $tool->enabledcapability);
        } else {
            $enabledcapabilities = array();
        }
        if (!empty($tool->parameter)) {
            $paramstr = str_replace("\r\n", "\n", $tool->parameter);
            $paramstr = str_replace("\n\r", "\n", $paramstr);
            $paramstr = str_replace("\r", "\n", $paramstr);
            $params = explode("\n", $paramstr);
            foreach ($params as $param) {
                $pos = strpos($param, '=');
                if (($pos === false) || ($pos < 1)) {
                    continue;
                }
                $value = trim(core_text::substr($param, $pos + 1, strlen($param)));
                if (substr($value, 0, 1) == '$') {
                    $value = substr($value, 1);
                    if (!in_array($value, $enabledcapabilities)) {
                        $enabledcapabilities[] = $value;
                    }
                }
            }
        }
        return $enabledcapabilities;
    }

    /**
     * Splits the custom parameters
     *
     * @param string    $customstr      String containing the parameters
     *
     * @return array of custom parameters
     */
    public static function split_parameters($customstr) {
        $customstr = str_replace("\r\n", "\n", $customstr);
        $customstr = str_replace("\n\r", "\n", $customstr);
        $customstr = str_replace("\r", "\n", $customstr);
        $lines = explode("\n", $customstr);  // Or should this split on "/[\n;]/"?
        $retval = array();
        foreach ($lines as $line) {
            $pos = strpos($line, '=');
            if ( $pos === false || $pos < 1 ) {
                continue;
            }
            $key = trim(core_text::substr($line, 0, $pos));
            $val = trim(core_text::substr($line, $pos + 1, strlen($line)));
            $retval[$key] = $val;
        }
        return $retval;
    }

    /**
     * Splits the custom parameters field to the various parameters
     *
     * @param object    $toolproxy      Tool proxy instance object
     * @param object    $tool           Tool instance object
     * @param array     $params         LTI launch parameters
     * @param string    $customstr      String containing the parameters
     * @param boolean   $islti2         True if an LTI 2 tool is being launched
     *
     * @return array of custom parameters
     */
    public static function split_custom_parameters($toolproxy, $tool, $params, $customstr, $islti2 = false) {
        $splitted = self::split_parameters($customstr);
        $retval = array();
        foreach ($splitted as $key => $val) {
            $val = self::parse_custom_parameter($toolproxy, $tool, $params, $val, $islti2);
            $key2 = self::map_keyname($key);
            $retval['custom_'.$key2] = $val;
            if (($islti2 || ($tool->ltiversion === LTI_VERSION_1P3)) && ($key != $key2)) {
                $retval['custom_'.$key] = $val;
            }
        }
        return $retval;
    }

    /**
     * Adds the custom parameters to an array
     *
     * @param object    $toolproxy      Tool proxy instance object
     * @param object    $tool           Tool instance object
     * @param array     $params         LTI launch parameters
     * @param array     $parameters     Array containing the parameters
     *
     * @return array    Array of custom parameters
     */
    public static function get_custom_parameters($toolproxy, $tool, $params, $parameters) {
        $retval = array();
        foreach ($parameters as $key => $val) {
            $key2 = self::map_keyname($key);
            $val = self::parse_custom_parameter($toolproxy, $tool, $params, $val, true);
            $retval['custom_'.$key2] = $val;
            if ($key != $key2) {
                $retval['custom_'.$key] = $val;
            }
        }
        return $retval;
    }

    /**
     * Parse a custom parameter to replace any substitution variables
     *
     * @param object    $toolproxy      Tool proxy instance object
     * @param object    $tool           Tool instance object
     * @param array     $params         LTI launch parameters
     * @param string    $value          Custom parameter value
     * @param boolean   $islti2         True if an LTI 2 tool is being launched
     *
     * @return string Parsed value of custom parameter
     */
    public static function parse_custom_parameter($toolproxy, $tool, $params, $value, $islti2) {
        // This is required as {${$valarr[0]}->{$valarr[1]}}" may be using the USER or COURSE var.
        global $USER, $COURSE;

        if ($value) {
            if (substr($value, 0, 1) == '\\') {
                $value = substr($value, 1);
            } else if (substr($value, 0, 1) == '$') {
                $value1 = substr($value, 1);
                $enabledcapabilities = self::get_enabled_capabilities($tool);
                if (!$islti2 || in_array($value1, $enabledcapabilities)) {
                    $capabilities = self::get_capabilities();
                    if (array_key_exists($value1, $capabilities)) {
                        $val = $capabilities[$value1];
                        if ($val) {
                            if (substr($val, 0, 1) != '$') {
                                $value = $params[$val];
                            } else {
                                $valarr = explode('->', substr($val, 1), 2);
                                $value = "{${$valarr[0]}->{$valarr[1]}}";
                                $value = str_replace('<br />' , ' ', $value);
                                $value = str_replace('<br>' , ' ', $value);
                                $value = format_string($value);
                            }
                        } else {
                            $value = self::calculate_custom_parameter($value1);
                        }
                    } else {
                        $val = $value;
                        $services = self::get_services();
                        foreach ($services as $service) {
                            $service->set_tool_proxy($toolproxy);
                            $service->set_type($tool);
                            $value = $service->parse_value($val);
                            if ($val != $value) {
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $value;
    }

    /**
     * Calculates the value of a custom parameter that has not been specified earlier
     *
     * @param string    $value          Custom parameter value
     *
     * @return string Calculated value of custom parameter
     */
    public static function calculate_custom_parameter($value) {
        global $USER, $COURSE;

        switch ($value) {
            case 'Moodle.Person.userGroupIds':
                return implode(",", groups_get_user_groups($COURSE->id, $USER->id)[0]);
            case 'Context.id.history':
                return implode(",", self::get_course_history($COURSE));
            case 'CourseSection.timeFrame.begin':
                if (empty($COURSE->startdate)) {
                    return "";
                }
                $dt = new DateTime("@$COURSE->startdate", new DateTimeZone('UTC'));
                return $dt->format(DateTime::ATOM);
            case 'CourseSection.timeFrame.end':
                if (empty($COURSE->enddate)) {
                    return "";
                }
                $dt = new DateTime("@$COURSE->enddate", new DateTimeZone('UTC'));
                return $dt->format(DateTime::ATOM);
        }
        return null;
    }

    /**
     * Build the history chain for this course using the course originalcourseid.
     *
     * @param object $course course for which the history is returned.
     *
     * @return array ids of the source course in ancestry order, immediate parent 1st.
     */
    public static function get_course_history($course) {
        global $DB;
        $history = [];
        $parentid = $course->originalcourseid;
        while (!empty($parentid) && !in_array($parentid, $history)) {
            $history[] = $parentid;
            $parentid = $DB->get_field('course', 'originalcourseid', array('id' => $parentid));
        }
        return $history;
    }

    /**
     * Used for building the names of the different custom parameters
     *
     * @param string $key   Parameter name
     * @param bool $tolower Do we want to convert the key into lower case?
     * @return string       Processed name
     */
    public static function map_keyname($key, $tolower = true) {
        if ($tolower) {
            $newkey = '';
            $key = core_text::strtolower(trim($key));
            foreach (str_split($key) as $ch) {
                if ( ($ch >= 'a' && $ch <= 'z') || ($ch >= '0' && $ch <= '9') ) {
                    $newkey .= $ch;
                } else {
                    $newkey .= '_';
                }
            }
        } else {
            $newkey = $key;
        }
        return $newkey;
    }

    /**
     * Given an array of tools, filter them based on their state
     *
     * @param array $tools An array of lti_types records
     * @param int $state One of the LTI_TOOL_STATE_* constants
     * @return array
     */
    public static function filter_tool_types(array $tools, $state) {
        $return = array();
        foreach ($tools as $key => $tool) {
            if ($tool->state == $state) {
                $return[$key] = $tool;
            }
        }
        return $return;
    }

    /**
     * Given an array of tool proxies, filter them based on their state
     *
     * @param array $toolproxies An array of lti_tool_proxies records
     * @param int $state One of the LTI_TOOL_PROXY_STATE_* constants
     *
     * @return array
     */
    public static function filter_tool_proxy_types(array $toolproxies, $state) {
        $return = array();
        foreach ($toolproxies as $key => $toolproxy) {
            if ($toolproxy->state == $state) {
                $return[$key] = $toolproxy;
            }
        }
        return $return;
    }

    /**
     * Get the tool proxy instance given its GUID
     *
     * @param string  $toolproxyguid   Tool proxy GUID value
     *
     * @return object
     */
    public static function get_tool_proxy_from_guid($toolproxyguid) {
        global $DB;

        $toolproxy = $DB->get_record('lti_tool_proxies', array('guid' => $toolproxyguid));

        return $toolproxy;
    }

    /**
     * Get the tool proxy instance given its registration URL
     *
     * @param string $regurl Tool proxy registration URL
     *
     * @return array The record of the tool proxy with this url
     */
    public static function get_tool_proxies_from_registration_url($regurl) {
        global $DB;

        return $DB->get_records_sql(
            'SELECT * FROM {lti_tool_proxies}
        WHERE '.$DB->sql_compare_text('regurl', 256).' = :regurl',
            array('regurl' => $regurl)
        );
    }

    /**
     * Generates some of the tool proxy configuration based on the admin configuration details
     *
     * @param int $id
     *
     * @return mixed Tool Proxy details
     */
    public static function get_tool_proxy($id) {
        global $DB;

        $toolproxy = $DB->get_record('lti_tool_proxies', array('id' => $id));
        return $toolproxy;
    }

    /**
     * Returns lti tool proxies.
     *
     * @param bool $orphanedonly Only retrieves tool proxies that have no type associated with them
     * @return array of basicLTI types
     */
    public static function get_tool_proxies($orphanedonly) {
        global $DB;

        if ($orphanedonly) {
            $usedproxyids = array_values($DB->get_fieldset_select('lti_types', 'toolproxyid', 'toolproxyid IS NOT NULL'));
            $proxies = $DB->get_records('lti_tool_proxies', null, 'state DESC, timemodified DESC');
            foreach ($proxies as $key => $value) {
                if (in_array($value->id, $usedproxyids)) {
                    unset($proxies[$key]);
                }
            }
            return $proxies;
        } else {
            return $DB->get_records('lti_tool_proxies', null, 'state DESC, timemodified DESC');
        }
    }

    /**
     * Generates some of the tool proxy configuration based on the admin configuration details
     *
     * @param int $id
     *
     * @return mixed  Tool Proxy details
     */
    public static function get_tool_proxy_config($id) {
        $toolproxy = self::get_tool_proxy($id);

        $tp = new \stdClass();
        $tp->lti_registrationname = $toolproxy->name;
        $tp->toolproxyid = $toolproxy->id;
        $tp->state = $toolproxy->state;
        $tp->lti_registrationurl = $toolproxy->regurl;
        $tp->lti_capabilities = explode("\n", $toolproxy->capabilityoffered);
        $tp->lti_services = explode("\n", $toolproxy->serviceoffered);

        return $tp;
    }

    /**
     * Gets the tool settings
     *
     * @param int  $toolproxyid   Id of tool proxy record (or tool ID if negative)
     * @param int  $courseid      Id of course (null if system settings)
     * @param int  $instanceid    Id of course module (null if system or context settings)
     *
     * @return array  Array settings
     */
    public static function get_tool_settings($toolproxyid, $courseid = null, $instanceid = null) {
        global $DB;

        $settings = array();
        if ($toolproxyid > 0) {
            $settingsstr = $DB->get_field('lti_tool_settings', 'settings', array('toolproxyid' => $toolproxyid,
                'course' => $courseid, 'coursemoduleid' => $instanceid));
        } else {
            $settingsstr = $DB->get_field('lti_tool_settings', 'settings', array('typeid' => -$toolproxyid,
                'course' => $courseid, 'coursemoduleid' => $instanceid));
        }
        if ($settingsstr !== false) {
            $settings = json_decode($settingsstr, true);
        }
        return $settings;
    }

    /**
     * Sets the tool settings (
     *
     * @param array  $settings      Array of settings
     * @param int    $toolproxyid   Id of tool proxy record (or tool ID if negative)
     * @param int    $courseid      Id of course (null if system settings)
     * @param int    $instanceid    Id of course module (null if system or context settings)
     */
    public static function set_tool_settings($settings, $toolproxyid, $courseid = null, $instanceid = null) {
        global $DB;

        $json = json_encode($settings);
        if ($toolproxyid >= 0) {
            $record = $DB->get_record('lti_tool_settings', array('toolproxyid' => $toolproxyid,
                'course' => $courseid, 'coursemoduleid' => $instanceid));
        } else {
            $record = $DB->get_record('lti_tool_settings', array('typeid' => -$toolproxyid,
                'course' => $courseid, 'coursemoduleid' => $instanceid));
        }
        if ($record !== false) {
            $DB->update_record('lti_tool_settings', (object)array('id' => $record->id, 'settings' => $json, 'timemodified' => time()));
        } else {
            $record = new \stdClass();
            if ($toolproxyid > 0) {
                $record->toolproxyid = $toolproxyid;
            } else {
                $record->typeid = -$toolproxyid;
            }
            $record->course = $courseid;
            $record->coursemoduleid = $instanceid;
            $record->settings = $json;
            $record->timecreated = time();
            $record->timemodified = $record->timecreated;
            $DB->insert_record('lti_tool_settings', $record);
        }
    }

    public static function ensure_url_is_https($url) {
        if (!strstr($url, '://')) {
            $url = 'https://' . $url;
        } else {
            // If the URL starts with http, replace with https.
            if (stripos($url, 'http://') === 0) {
                $url = 'https://' . substr($url, 7);
            }
        }

        return $url;
    }

    public static function request_is_using_ssl() {
        global $CFG;
        return (stripos($CFG->wwwroot, 'https://') === 0);
    }

    /**
     * Initializes an array with the capabilities supported by the LTI module
     *
     * @return array List of capability names (without a dollar sign prefix)
     */
    public static function get_capabilities() {
        $capabilities = array(
            'basic-lti-launch-request' => '',
            'ContentItemSelectionRequest' => '',
            'ToolProxyRegistrationRequest' => '',
            'Context.id' => 'context_id',
            'Context.title' => 'context_title',
            'Context.label' => 'context_label',
            'Context.id.history' => null,
            'Context.sourcedId' => 'lis_course_section_sourcedid',
            'Context.longDescription' => '$COURSE->summary',
            'Context.timeFrame.begin' => '$COURSE->startdate',
            'CourseSection.title' => 'context_title',
            'CourseSection.label' => 'context_label',
            'CourseSection.sourcedId' => 'lis_course_section_sourcedid',
            'CourseSection.longDescription' => '$COURSE->summary',
            'CourseSection.timeFrame.begin' => null,
            'CourseSection.timeFrame.end' => null,
            'ResourceLink.id' => 'resource_link_id',
            'ResourceLink.title' => 'resource_link_title',
            'ResourceLink.description' => 'resource_link_description',
            'User.id' => 'user_id',
            'User.username' => '$USER->username',
            'Person.name.full' => 'lis_person_name_full',
            'Person.name.given' => 'lis_person_name_given',
            'Person.name.family' => 'lis_person_name_family',
            'Person.email.primary' => 'lis_person_contact_email_primary',
            'Person.sourcedId' => 'lis_person_sourcedid',
            'Person.name.middle' => '$USER->middlename',
            'Person.address.street1' => '$USER->address',
            'Person.address.locality' => '$USER->city',
            'Person.address.country' => '$USER->country',
            'Person.address.timezone' => '$USER->timezone',
            'Person.phone.primary' => '$USER->phone1',
            'Person.phone.mobile' => '$USER->phone2',
            'Person.webaddress' => '$USER->url',
            'Membership.role' => 'roles',
            'Result.sourcedId' => 'lis_result_sourcedid',
            'Result.autocreate' => 'lis_outcome_service_url',
            'BasicOutcome.sourcedId' => 'lis_result_sourcedid',
            'BasicOutcome.url' => 'lis_outcome_service_url',
            'Moodle.Person.userGroupIds' => null);

        return $capabilities;
    }

    /**
     * Search for a tag within an XML DOMDocument
     *
     * @param  string $url The url of the cartridge to be loaded
     * @param  array  $map The map of tags to keys in the return array
     * @param  array  $propertiesmap The map of properties to keys in the return array
     * @return array An associative array with the given keys and their values from the cartridge
     * @throws moodle_exception if the cartridge could not be loaded correctly
     */
    public static function load_cartridge($url, $map, $propertiesmap = array()) {
        global $CFG;
        require_once($CFG->libdir. "/filelib.php");

        $curl = new curl();
        $response = $curl->get($url);

        // Got a completely empty response (real or error), cannot process this with
        // DOMDocument::loadXML() because it errors with ValueError. So let's throw
        // the moodle_exception before waiting to examine the errors later.
        if (trim($response) === '') {
            throw new moodle_exception('errorreadingfile', '', '', $url);
        }

        // TODO MDL-46023 Replace this code with a call to the new library.
        $origerrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument();
        @$document->loadXML($response, LIBXML_NONET);

        $cartridge = new DomXpath($document);

        $errors = libxml_get_errors();

        libxml_clear_errors();
        libxml_use_internal_errors($origerrors);

        if (count($errors) > 0) {
            $message = 'Failed to load cartridge.';
            foreach ($errors as $error) {
                $message .= "\n" . trim($error->message, "\n\r\t .") . " at line " . $error->line;
            }
            throw new moodle_exception('errorreadingfile', '', '', $url, $message);
        }

        $toolinfo = array();
        foreach ($map as $tag => $key) {
            $value = self::get_tag($tag, $cartridge);
            if ($value) {
                $toolinfo[$key] = $value;
            }
        }
        if (!empty($propertiesmap)) {
            foreach ($propertiesmap as $property => $key) {
                $value = self::get_tag("property", $cartridge, $property);
                if ($value) {
                    $toolinfo[$key] = $value;
                }
            }
        }

        return $toolinfo;
    }

    /**
     * Search for a tag within an XML DOMDocument
     *
     * @param  stdClass $tagname The name of the tag to search for
     * @param  XPath    $xpath   The XML to find the tag in
     * @param  XPath    $attribute The attribute to search for (if we should search for a child node with the given
     * value for the name attribute
     */
    public static function get_tag($tagname, $xpath, $attribute = null) {
        if ($attribute) {
            $result = $xpath->query('//*[local-name() = \'' . $tagname . '\'][@name="' . $attribute . '"]');
        } else {
            $result = $xpath->query('//*[local-name() = \'' . $tagname . '\']');
        }
        if ($result->length > 0) {
            return $result->item(0)->nodeValue;
        }
        return null;
    }

    /**
     * Determines if the given url is for a IMS basic cartridge
     *
     * @param  string $url The url to be checked
     * @return True if the url is for a cartridge
     */
    public static function is_cartridge($url) {
        // If it is empty, it's not a cartridge.
        if (empty($url)) {
            return false;
        }
        // If it has xml at the end of the url, it's a cartridge.
        if (preg_match('/\.xml$/', $url)) {
            return true;
        }
        // Even if it doesn't have .xml, load the url to check if it's a cartridge..
        try {
            $toolinfo = \core_ltix\helper::load_cartridge($url,
                array(
                    "launch_url" => "launchurl"
                )
            );
            if (!empty($toolinfo['launchurl'])) {
                return true;
            }
        } catch (moodle_exception $e) {
            return false; // Error loading the xml, so it's not a cartridge.
        }
        return false;
    }

   /**
     * Returns all LTI tool types (preconfigured tools) visible in the given course.
     *
     * This list will contain both site level tools and course-level tools.
     *
     * @param int $courseid the id of the course.
     * @param int $userid the id of the user.
     * @param array $coursevisible options for 'coursevisible' field, which will default to
     *        [LTI_COURSEVISIBLE_PRECONFIGURED, LTI_COURSEVISIBLE_ACTIVITYCHOOSER] if omitted.
     * @return \stdClass[] the array of tool type objects.
     */
    public static function get_lti_types_by_course(int $courseid, array $coursevisible = []): array {
        global $DB, $SITE;

        if (empty($coursevisible)) {
            $coursevisible = [LTI_COURSEVISIBLE_PRECONFIGURED, LTI_COURSEVISIBLE_ACTIVITYCHOOSER];
        }
        [$coursevisiblesql, $coursevisparams] = $DB->get_in_or_equal($coursevisible, SQL_PARAMS_NAMED, 'coursevisible');
        [$coursevisiblesql1, $coursevisparams1] = $DB->get_in_or_equal($coursevisible, SQL_PARAMS_NAMED, 'coursevisible');
        [$coursevisibleoverriddensql, $coursevisoverriddenparams] = $DB->get_in_or_equal(
            $coursevisible,
            SQL_PARAMS_NAMED,
            'coursevisibleoverridden');

        $coursecond = implode(" OR ", ["t.course = :courseid", "t.course = :siteid"]);
        $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
        $query = "SELECT *
                    FROM (SELECT t.*, c.coursevisible as coursevisibleoverridden
                            FROM {lti_types} t
                       LEFT JOIN {lti_types_categories} tc ON t.id = tc.typeid
                       LEFT JOIN {lti_coursevisible} c ON c.typeid = t.id AND c.courseid = $courseid
                           WHERE (t.coursevisible $coursevisiblesql
                                 OR (c.coursevisible $coursevisiblesql1 AND t.coursevisible NOT IN (:lticoursevisibleno)))
                             AND ($coursecond)
                             AND t.state = :active
                             AND (tc.id IS NULL OR tc.categoryid = :categoryid)) tt
                   WHERE tt.coursevisibleoverridden IS NULL
                      OR tt.coursevisibleoverridden $coursevisibleoverriddensql";

        return $DB->get_records_sql(
            $query,
            [
                'siteid' => $SITE->id,
                'courseid' => $courseid,
                'active' => LTI_TOOL_STATE_CONFIGURED,
                'categoryid' => $coursecategory,
                'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
                'lticoursevisibleno' => LTI_COURSEVISIBLE_NO,
            ] + $coursevisparams + $coursevisparams1 + $coursevisoverriddenparams
        );
    }

    /**
     * Override coursevisible for a given tool on course level.
     *
     * @param int $tooltypeid Type ID
     * @param int $courseid Course ID
     * @param \core\context\course $context Course context
     * @param bool $showinactivitychooser Show or not show in activity chooser
     * @return bool True if the coursevisible was changed, false otherwise.
     */
    public static function override_type_showinactivitychooser(int $tooltypeid, int $courseid, \core\context\course $context,
        bool $showinactivitychooser): bool {
        global $DB;

        require_capability('moodle/ltix:addcoursetool', $context);

        $ltitype = self::get_type($tooltypeid);
        if ($ltitype && ($ltitype->coursevisible != LTI_COURSEVISIBLE_NO)) {
            $coursevisible = $showinactivitychooser ? LTI_COURSEVISIBLE_ACTIVITYCHOOSER : LTI_COURSEVISIBLE_PRECONFIGURED;
            $ltitype->coursevisible = $coursevisible;

            $config = new \stdClass();
            $config->lti_coursevisible = $coursevisible;

            if (intval($ltitype->course) != intval(get_site()->id)) {
                // It is course tool - just update it.
                self::update_type($ltitype, $config);
            } else {
                $coursecategory = $DB->get_field('course', 'category', ['id' => $courseid]);
                $sql = "SELECT COUNT(*) AS count
                      FROM {lti_types_categories} tc
                     WHERE tc.typeid = :typeid";
                $restrictedtool = $DB->count_records_sql($sql, ['typeid' => $tooltypeid]);
                if ($restrictedtool) {
                    $record = $DB->get_record('lti_types_categories', ['typeid' => $tooltypeid, 'categoryid' => $coursecategory]);
                    if (!$record) {
                        throw new \moodle_exception('You are not allowed to change this setting for this tool.');
                    }
                }

                // This is site tool, but we would like to have course level setting for it.
                $lticoursevisible = $DB->get_record('lti_coursevisible', ['typeid' => $tooltypeid, 'courseid' => $courseid]);
                if (!$lticoursevisible) {
                    $lticoursevisible = new \stdClass();
                    $lticoursevisible->typeid = $tooltypeid;
                    $lticoursevisible->courseid = $courseid;
                    $lticoursevisible->coursevisible = $coursevisible;
                    $DB->insert_record('lti_coursevisible', $lticoursevisible);
                } else {
                    $lticoursevisible->coursevisible = $coursevisible;
                    $DB->update_record('lti_coursevisible', $lticoursevisible);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Returns configuration details for the tool
     *
     * @param int $typeid Basic LTI tool typeid
     *
     * @return array        Tool Configuration
     */
    public static function get_type_config($typeid) {
        global $DB;

        $query = "SELECT name, value
                FROM {lti_types_config}
               WHERE typeid = :typeid1
           UNION ALL
              SELECT 'toolurl' AS name, baseurl AS value
                FROM {lti_types}
               WHERE id = :typeid2
           UNION ALL
              SELECT 'icon' AS name, icon AS value
                FROM {lti_types}
               WHERE id = :typeid3
           UNION ALL
              SELECT 'secureicon' AS name, secureicon AS value
                FROM {lti_types}
               WHERE id = :typeid4";

        $typeconfig = array();
        $configs = $DB->get_records_sql($query,
            array('typeid1' => $typeid, 'typeid2' => $typeid, 'typeid3' => $typeid, 'typeid4' => $typeid));

        if (!empty($configs)) {
            foreach ($configs as $config) {
                $typeconfig[$config->name] = $config->value;
            }
        }

        return $typeconfig;
    }

    public static function get_type($typeid) {
        global $DB;

        return $DB->get_record('lti_types', array('id' => $typeid));
    }

    /**
     * Returns all basicLTI tools configured by the administrator
     *
     * @param int $course
     *
     * @return array
     */
    public static function filter_get_types($course) {
        global $DB;

        if (!empty($course)) {
            $where = "WHERE t.course = :course";
            $params = array('course' => $course);
        } else {
            $where = '';
            $params = array();
        }
        $query = "SELECT t.id, t.name, t.baseurl, t.state, t.toolproxyid, t.timecreated, tp.name tpname
                FROM {lti_types} t LEFT OUTER JOIN {lti_tool_proxies} tp ON t.toolproxyid = tp.id
                {$where}";
        return $DB->get_records_sql($query, $params);
    }

    /**
     * Delete a Basic LTI configuration
     *
     * @param int $id Configuration id
     */
    public static function delete_type($id) {
        global $DB;

        // We should probably just copy the launch URL to the tool instances in this case... using a single query.
        /*
        $instances = $DB->get_records('lti', array('typeid' => $id));
        foreach ($instances as $instance) {
            $instance->typeid = 0;
            $DB->update_record('lti', $instance);
        }*/

        $DB->delete_records('lti_types', array('id' => $id));
        $DB->delete_records('lti_types_config', array('typeid' => $id));
        $DB->delete_records('lti_types_categories', array('typeid' => $id));
    }

    public static function set_state_for_type($id, $state) {
        global $DB;

        $DB->update_record('lti_types', (object) array('id' => $id, 'state' => $state));
    }

    public static function update_type($type, $config) {
        global $DB, $CFG;

        self::prepare_type_for_save($type, $config);

        if (helper::request_is_using_ssl() && !empty($type->secureicon)) {
            $clearcache = !isset($config->oldicon) || ($config->oldicon !== $type->secureicon);
        } else {
            $clearcache = isset($type->icon) && (!isset($config->oldicon) || ($config->oldicon !== $type->icon));
        }
        unset($config->oldicon);

        if ($DB->update_record('lti_types', $type)) {
            foreach ($config as $key => $value) {
                if (substr($key, 0, 4) == 'lti_' && !is_null($value)) {
                    $record = new \StdClass();
                    $record->typeid = $type->id;
                    $record->name = substr($key, 4);
                    $record->value = $value;
                    self::update_config($record);
                }
                if (substr($key, 0, 11) == 'ltiservice_' && !is_null($value)) {
                    $record = new \StdClass();
                    $record->typeid = $type->id;
                    $record->name = $key;
                    $record->value = $value;
                    self::update_config($record);
                }
            }
            if (isset($type->toolproxyid) && $type->ltiversion === LTI_VERSION_1P3) {
                // We need to remove the tool proxy for this tool to function under 1.3.
                $toolproxyid = $type->toolproxyid;
                $DB->delete_records('lti_tool_settings', array('toolproxyid' => $toolproxyid));
                $DB->delete_records('lti_tool_proxies', array('id' => $toolproxyid));
                $type->toolproxyid = null;
                $DB->update_record('lti_types', $type);
            }
            $DB->delete_records('lti_types_categories', ['typeid' => $type->id]);
            if (isset($config->lti_coursecategories) && !empty($config->lti_coursecategories)) {
                self::type_add_categories($type->id, $config->lti_coursecategories);
            }
            require_once($CFG->libdir . '/modinfolib.php');
            if ($clearcache) {
                $sql = "SELECT cm.id, cm.course
                      FROM {course_modules} cm
                      JOIN {modules} m ON cm.module = m.id
                      JOIN {lti} l ON l.course = cm.course
                     WHERE m.name = :name AND l.typeid = :typeid";

                $rs = $DB->get_recordset_sql($sql, ['name' => 'lti', 'typeid' => $type->id]);

                $courseids = [];
                foreach ($rs as $record) {
                    $courseids[] = $record->course;
                    \course_modinfo::purge_course_module_cache($record->course, $record->id);
                }
                $rs->close();
                $courseids = array_unique($courseids);
                foreach ($courseids as $courseid) {
                    rebuild_course_cache($courseid, false, true);
                }
            }
        }
    }

    public static function prepare_type_for_save($type, $config) {
        if (isset($config->lti_toolurl)) {
            $type->baseurl = $config->lti_toolurl;
            if (isset($config->lti_tooldomain)) {
                $type->tooldomain = $config->lti_tooldomain;
            } else {
                $type->tooldomain = helper::get_domain_from_url($config->lti_toolurl);
            }
        }
        if (isset($config->lti_description)) {
            $type->description = $config->lti_description;
        }
        if (isset($config->lti_typename)) {
            $type->name = $config->lti_typename;
        }
        if (isset($config->lti_ltiversion)) {
            $type->ltiversion = $config->lti_ltiversion;
        }
        if (isset($config->lti_clientid)) {
            $type->clientid = $config->lti_clientid;
        }
        if ((!empty($type->ltiversion) && $type->ltiversion === LTI_VERSION_1P3) && empty($type->clientid)) {
            $type->clientid = registration_helper::get()->new_clientid();
        } else if (empty($type->clientid)) {
            $type->clientid = null;
        }
        if (isset($config->lti_coursevisible)) {
            $type->coursevisible = $config->lti_coursevisible;
        }

        if (isset($config->lti_icon)) {
            $type->icon = $config->lti_icon;
        }
        if (isset($config->lti_secureicon)) {
            $type->secureicon = $config->lti_secureicon;
        }

        $type->forcessl = !empty($config->lti_forcessl) ? $config->lti_forcessl : 0;
        $config->lti_forcessl = $type->forcessl;
        if (isset($config->lti_contentitem)) {
            $type->contentitem = !empty($config->lti_contentitem) ? $config->lti_contentitem : 0;
            $config->lti_contentitem = $type->contentitem;
        }
        if (isset($config->lti_toolurl_ContentItemSelectionRequest)) {
            if (!empty($config->lti_toolurl_ContentItemSelectionRequest)) {
                $type->toolurl_ContentItemSelectionRequest = $config->lti_toolurl_ContentItemSelectionRequest;
            } else {
                $type->toolurl_ContentItemSelectionRequest = '';
            }
            $config->lti_toolurl_ContentItemSelectionRequest = $type->toolurl_ContentItemSelectionRequest;
        }

        $type->timemodified = time();

        unset ($config->lti_typename);
        unset ($config->lti_toolurl);
        unset ($config->lti_description);
        unset ($config->lti_ltiversion);
        unset ($config->lti_clientid);
        unset ($config->lti_icon);
        unset ($config->lti_secureicon);
    }

    /**
     * Updates a tool configuration in the database
     *
     * @param object  $config   Tool configuration
     *
     * @return mixed Record id number
     */
    public static function update_config($config) {
        global $DB;

        $old = $DB->get_record('lti_types_config', array('typeid' => $config->typeid, 'name' => $config->name));

        if ($old) {
            $config->id = $old->id;
            $return = $DB->update_record('lti_types_config', $config);
        } else {
            $return = $DB->insert_record('lti_types_config', $config);
        }
        return $return;
    }

    /**
     * Add LTI Type course category.
     *
     * @param int $typeid
     * @param string $lticoursecategories Comma separated list of course categories.
     * @return void
     */
    public static function type_add_categories(int $typeid, string $lticoursecategories = '') : void {
        global $DB;
        $coursecategories = explode(',', $lticoursecategories);
        foreach ($coursecategories as $coursecategory) {
            $DB->insert_record('lti_types_categories', ['typeid' => $typeid, 'categoryid' => $coursecategory]);
        }
    }

    public static function add_type($type, $config) {
        global $USER, $SITE, $DB;

        self::prepare_type_for_save($type, $config);

        if (!isset($type->state)) {
            $type->state = LTI_TOOL_STATE_PENDING;
        }

        if (!isset($type->ltiversion)) {
            $type->ltiversion = LTI_VERSION_1;
        }

        if (!isset($type->timecreated)) {
            $type->timecreated = time();
        }

        if (!isset($type->createdby)) {
            $type->createdby = $USER->id;
        }

        if (!isset($type->course)) {
            $type->course = $SITE->id;
        }

        // Create a salt value to be used for signing passed data to extension services
        // The outcome service uses the service salt on the instance. This can be used
        // for communication with services not related to a specific LTI instance.
        $config->lti_servicesalt = uniqid('', true);

        $id = $DB->insert_record('lti_types', $type);

        if ($id) {
            foreach ($config as $key => $value) {
                if (!is_null($value)) {
                    if (substr($key, 0, 4) === 'lti_') {
                        $fieldname = substr($key, 4);
                    } else if (substr($key, 0, 11) !== 'ltiservice_') {
                        continue;
                    } else {
                        $fieldname = $key;
                    }

                    $record = new \StdClass();
                    $record->typeid = $id;
                    $record->name = $fieldname;
                    $record->value = $value;

                    self::add_config($record);
                }
            }
            if (isset($config->lti_coursecategories) && !empty($config->lti_coursecategories)) {
                self::type_add_categories($id, $config->lti_coursecategories);
            }
        }

        return $id;
    }

    /**
     * Add a tool configuration in the database
     *
     * @param object $config   Tool configuration
     *
     * @return int Record id number
     */
    public static function add_config($config) {
        global $DB;

        return $DB->insert_record('lti_types_config', $config);
    }

    /** get Organization ID using default if no value provided
     * @param object $typeconfig
     * @return string
     */
    public static function get_organizationid($typeconfig) {
        global $CFG;
        // Default the organizationid if not specified.
        if (empty($typeconfig['organizationid'])) {
            if (($typeconfig['organizationid_default'] ?? LTI_DEFAULT_ORGID_SITEHOST) == LTI_DEFAULT_ORGID_SITEHOST) {
                $urlparts = parse_url($CFG->wwwroot);
                return $urlparts['host'];
            } else {
                return md5(get_site_identifier());
            }
        }
        return $typeconfig['organizationid'];
    }

    /**
     * Generates some of the tool configuration based on the admin configuration details
     *
     * @param int $id
     *
     * @return stdClass Configuration details
     */
    public static function get_type_type_config($id) {
        global $DB;

        $basicltitype = $DB->get_record('lti_types', array('id' => $id));
        $config = self::get_type_config($id);

        $type = new \stdClass();

        $type->lti_typename = $basicltitype->name;

        $type->typeid = $basicltitype->id;

        $type->course = $basicltitype->course;

        $type->toolproxyid = $basicltitype->toolproxyid;

        $type->lti_toolurl = $basicltitype->baseurl;

        $type->lti_ltiversion = $basicltitype->ltiversion;

        $type->lti_clientid = $basicltitype->clientid;
        $type->lti_clientid_disabled = $type->lti_clientid;

        $type->lti_description = $basicltitype->description;

        $type->lti_parameters = $basicltitype->parameter;

        $type->lti_icon = $basicltitype->icon;

        $type->lti_secureicon = $basicltitype->secureicon;

        if (isset($config['resourcekey'])) {
            $type->lti_resourcekey = $config['resourcekey'];
        }
        if (isset($config['password'])) {
            $type->lti_password = $config['password'];
        }
        if (isset($config['publickey'])) {
            $type->lti_publickey = $config['publickey'];
        }
        if (isset($config['publickeyset'])) {
            $type->lti_publickeyset = $config['publickeyset'];
        }
        if (isset($config['keytype'])) {
            $type->lti_keytype = $config['keytype'];
        }
        if (isset($config['initiatelogin'])) {
            $type->lti_initiatelogin = $config['initiatelogin'];
        }
        if (isset($config['redirectionuris'])) {
            $type->lti_redirectionuris = $config['redirectionuris'];
        }

        if (isset($config['sendname'])) {
            $type->lti_sendname = $config['sendname'];
        }
        if (isset($config['instructorchoicesendname'])) {
            $type->lti_instructorchoicesendname = $config['instructorchoicesendname'];
        }
        if (isset($config['sendemailaddr'])) {
            $type->lti_sendemailaddr = $config['sendemailaddr'];
        }
        if (isset($config['instructorchoicesendemailaddr'])) {
            $type->lti_instructorchoicesendemailaddr = $config['instructorchoicesendemailaddr'];
        }
        if (isset($config['acceptgrades'])) {
            $type->lti_acceptgrades = $config['acceptgrades'];
        }
        if (isset($config['instructorchoiceacceptgrades'])) {
            $type->lti_instructorchoiceacceptgrades = $config['instructorchoiceacceptgrades'];
        }
        if (isset($config['allowroster'])) {
            $type->lti_allowroster = $config['allowroster'];
        }
        if (isset($config['instructorchoiceallowroster'])) {
            $type->lti_instructorchoiceallowroster = $config['instructorchoiceallowroster'];
        }

        if (isset($config['customparameters'])) {
            $type->lti_customparameters = $config['customparameters'];
        }

        if (isset($config['forcessl'])) {
            $type->lti_forcessl = $config['forcessl'];
        }

        if (isset($config['organizationid_default'])) {
            $type->lti_organizationid_default = $config['organizationid_default'];
        } else {
            // Tool was configured before this option was available and the default then was host.
            $type->lti_organizationid_default = LTI_DEFAULT_ORGID_SITEHOST;
        }
        if (isset($config['organizationid'])) {
            $type->lti_organizationid = $config['organizationid'];
        }
        if (isset($config['organizationurl'])) {
            $type->lti_organizationurl = $config['organizationurl'];
        }
        if (isset($config['organizationdescr'])) {
            $type->lti_organizationdescr = $config['organizationdescr'];
        }
        if (isset($config['launchcontainer'])) {
            $type->lti_launchcontainer = $config['launchcontainer'];
        }

        if (isset($config['coursevisible'])) {
            $type->lti_coursevisible = $config['coursevisible'];
        }

        if (isset($config['contentitem'])) {
            $type->lti_contentitem = $config['contentitem'];
        }

        if (isset($config['toolurl_ContentItemSelectionRequest'])) {
            $type->lti_toolurl_ContentItemSelectionRequest = $config['toolurl_ContentItemSelectionRequest'];
        }

        if (isset($config['debuglaunch'])) {
            $type->lti_debuglaunch = $config['debuglaunch'];
        }

        if (isset($config['module_class_type'])) {
            $type->lti_module_class_type = $config['module_class_type'];
        }

        // Get the parameters from the LTI services.
        foreach ($config as $name => $value) {
            if (strpos($name, 'ltiservice_') === 0) {
                $type->{$name} = $config[$name];
            }
        }

        return $type;
    }

    /**
     * Get the total number of LTI tool types and tool proxies.
     *
     * @param bool $orphanedonly If true, only count orphaned proxies.
     * @param int $toolproxyid If not 0, only count tool types that have this tool proxy id.
     * @return int Count of tools.
     */
    public static function get_lti_types_and_proxies_count(bool $orphanedonly = false, int $toolproxyid = 0): int {
        global $DB;

        $typessql = "SELECT count(*)
                   FROM {lti_types}";
        $typesparams = [];
        if (!empty($toolproxyid)) {
            $typessql .= " WHERE toolproxyid = :toolproxyid";
            $typesparams['toolproxyid'] = $toolproxyid;
        }

        $proxiessql = helper::get_tool_proxy_sql($orphanedonly, true);

        $countsql = "SELECT ($typessql) + ($proxiessql) as total" . $DB->sql_null_from_clause();

        return $DB->count_records_sql($countsql, $typesparams);
    }

    /**
     * Get both LTI tool proxies and tool types.
     *
     * If limit and offset are not zero, a subset of the tools will be returned. Tool proxies will be counted before tool
     * types.
     * For example: If 10 tool proxies and 10 tool types exist, and the limit is set to 15, then 10 proxies and 5 types
     * will be returned.
     *
     * @param int $limit Maximum number of tools returned.
     * @param int $offset Do not return tools before offset index.
     * @param bool $orphanedonly If true, only return orphaned proxies.
     * @param int $toolproxyid If not 0, only return tool types that have this tool proxy id.
     * @return array list(proxies[], types[]) List containing array of tool proxies and array of tool types.
     */
    public static function get_lti_types_and_proxies(int $limit = 0, int $offset = 0, bool $orphanedonly = false, int $toolproxyid = 0): array {
        global $DB;

        if ($orphanedonly) {
            $orphanedproxiessql = helper::get_tool_proxy_sql($orphanedonly, false);
            $countsql = helper::get_tool_proxy_sql($orphanedonly, true);
            $proxies  = $DB->get_records_sql($orphanedproxiessql, null, $offset, $limit);
            $totalproxiescount = $DB->count_records_sql($countsql);
        } else {
            $proxies = $DB->get_records('lti_tool_proxies', null, 'name ASC, state DESC, timemodified DESC',
                '*', $offset, $limit);
            $totalproxiescount = $DB->count_records('lti_tool_proxies');
        }

        // Find new offset and limit for tool types after getting proxies and set up query.
        $typesoffset = max($offset - $totalproxiescount, 0); // Set to 0 if negative.
        $typeslimit = max($limit - count($proxies), 0); // Set to 0 if negative.
        $typesparams = [];
        if (!empty($toolproxyid)) {
            $typesparams['toolproxyid'] = $toolproxyid;
        }

        $types = $DB->get_records('lti_types', $typesparams, 'name ASC, state DESC, timemodified DESC',
            '*', $typesoffset, $typeslimit);

        return [$proxies, array_map('serialise_tool_type', $types)];
    }

    /**
     * Returns information on the current state of the tool type
     *
     * @param stdClass $type The tool type
     *
     * @return array An array with a text description of the state, and boolean for whether it is in each state:
     * pending, configured, rejected, unknown
     */
    public static function get_tool_type_state_info(stdClass $type) {
        $isconfigured = false;
        $ispending = false;
        $isrejected = false;
        $isunknown = false;
        switch ($type->state) {
            case LTI_TOOL_STATE_CONFIGURED:
                $state = get_string('active', 'ltix');
                $isconfigured = true;
                break;
            case LTI_TOOL_STATE_PENDING:
                $state = get_string('pending', 'ltix');
                $ispending = true;
                break;
            case LTI_TOOL_STATE_REJECTED:
                $state = get_string('rejected', 'ltix');
                $isrejected = true;
                break;
            default:
                $state = get_string('unknownstate', 'ltix');
                $isunknown = true;
                break;
        }

        return array(
            'text' => $state,
            'pending' => $ispending,
            'configured' => $isconfigured,
            'rejected' => $isrejected,
            'unknown' => $isunknown
        );
    }

    /**
     * Returns a summary of each LTI capability this tool type requires in plain language
     *
     * @param stdClass $type The tool type
     *
     * @return array An array of text descriptions of each of the capabilities this tool type requires
     */
    public static function get_tool_type_capability_groups($type) {
        $capabilities = helper::get_enabled_capabilities($type);
        $groups = array();
        $hascourse = false;
        $hasactivities = false;
        $hasuseraccount = false;
        $hasuserpersonal = false;

        foreach ($capabilities as $capability) {
            // Bail out early if we've already found all groups.
            if (count($groups) >= 4) {
                continue;
            }

            if (!$hascourse && preg_match('/^CourseSection/', $capability)) {
                $hascourse = true;
                $groups[] = get_string('courseinformation', 'core_ltix');
            } else if (!$hasactivities && preg_match('/^ResourceLink/', $capability)) {
                $hasactivities = true;
                $groups[] = get_string('courseactivitiesorresources', 'core_ltix');
            } else if (!$hasuseraccount && preg_match('/^User/', $capability) || preg_match('/^Membership/', $capability)) {
                $hasuseraccount = true;
                $groups[] = get_string('useraccountinformation', 'core_ltix');
            } else if (!$hasuserpersonal && preg_match('/^Person/', $capability)) {
                $hasuserpersonal = true;
                $groups[] = get_string('userpersonalinformation', 'core_ltix');
            }
        }

        return $groups;
    }

    /**
     * Create a new access token.
     *
     * @param int $typeid Tool type ID
     * @param string[] $scopes Scopes permitted for new token
     *
     * @return stdClass Access token
     */
    public static function new_access_token($typeid, $scopes) {
        global $DB;

        // Make sure the token doesn't exist (even if it should be almost impossible with the random generation).
        $numtries = 0;
        do {
            $numtries ++;
            $generatedtoken = md5(uniqid((string) rand(), true));
            if ($numtries > 5) {
                throw new moodle_exception('Failed to generate LTI access token');
            }
        } while ($DB->record_exists('lti_access_tokens', array('token' => $generatedtoken)));
        $newtoken = new stdClass();
        $newtoken->typeid = $typeid;
        $newtoken->scope = json_encode(array_values($scopes));
        $newtoken->token = $generatedtoken;

        $newtoken->timecreated = time();
        $newtoken->validuntil = $newtoken->timecreated + LTI_ACCESS_TOKEN_LIFE;
        $newtoken->lastaccess = null;

        $DB->insert_record('lti_access_tokens', $newtoken);

        return $newtoken;
    }

    /**
     * Allows you to load settings for an external tool type from an IMS cartridge.
     *
     * @param  string   $url     The URL to the cartridge
     * @param  stdClass $type    The tool type object to be filled in
     * @throws moodle_exception if the cartridge could not be loaded correctly
     */
    public static function load_type_from_cartridge($url, $type) {
        $toolinfo = helper::load_cartridge($url,
            array(
                "title" => "lti_typename",
                "launch_url" => "lti_toolurl",
                "description" => "lti_description",
                "icon" => "lti_icon",
                "secure_icon" => "lti_secureicon"
            ),
            array(
                "icon_url" => "lti_extension_icon",
                "secure_icon_url" => "lti_extension_secureicon"
            )
        );
        // If an activity name exists, unset the cartridge name so we don't override it.
        if (isset($type->lti_typename)) {
            unset($toolinfo['lti_typename']);
        }

        // Always prefer cartridge core icons first, then, if none are found, look at the extension icons.
        if (empty($toolinfo['lti_icon']) && !empty($toolinfo['lti_extension_icon'])) {
            $toolinfo['lti_icon'] = $toolinfo['lti_extension_icon'];
        }
        unset($toolinfo['lti_extension_icon']);

        if (empty($toolinfo['lti_secureicon']) && !empty($toolinfo['lti_extension_secureicon'])) {
            $toolinfo['lti_secureicon'] = $toolinfo['lti_extension_secureicon'];
        }
        unset($toolinfo['lti_extension_secureicon']);

        // Ensure Custom icons aren't overridden by cartridge params.
        if (!empty($type->lti_icon)) {
            unset($toolinfo['lti_icon']);
        }

        if (!empty($type->lti_secureicon)) {
            unset($toolinfo['lti_secureicon']);
        }

        foreach ($toolinfo as $property => $value) {
            $type->$property = $value;
        }
    }

    /**
     * Loads the cartridge information into the tool type, if the launch url is for a cartridge file
     *
     * @param stdClass $type The tool type object to be filled in
     */
    public static function load_type_if_cartridge($type) {
        if (!empty($type->lti_toolurl) && \core_ltix\helper::is_cartridge($type->lti_toolurl)) {
            self::load_type_from_cartridge($type->lti_toolurl, $type);
        }
    }
}
