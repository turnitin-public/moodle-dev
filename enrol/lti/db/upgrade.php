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
//

/**
 * This file keeps track of upgrades to the lti enrolment plugin
 *
 * @package enrol_lti
 * @copyright  2016 John Okely <john@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die;

/**
 * xmldb_lti_upgrade is the function that upgrades
 * the lti module database when is needed
 *
 * This function is automaticly called when version number in
 * version.php changes.
 *
 * @param int $oldversion New old version number.
 *
 * @return boolean
 */
function xmldb_enrol_lti_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    require_once($CFG->libdir.'/db/upgradelib.php'); // Core Upgrade-related functions.

    $dbman = $DB->get_manager();

    // Automatically generated Moodle v3.5.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.6.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.7.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.9.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2021052501) {
        /*// Define table enrol_lti_tools to be updated.
        $table = new xmldb_table('enrol_lti_tools');

        // Define field privatekey to be added to enrol_lti_tools.
        $field = new xmldb_field('privatekey', XMLDB_TYPE_TEXT, null, null, null, null, null, 'secret');

        // Conditionally launch add field privatekey.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field_select('lti_types', 'ltiversion', 'LTI-1p0', 'toolproxyid IS NULL');
            $DB->set_field_select('lti_types', 'ltiversion', 'LTI-2p0', 'toolproxyid IS NOT NULL');
        }

        // Set a private key for all existing published as LTI tools.
        $recordset = $DB->get_recordset('enrol_lti_tools');
        foreach ($recordset as $record) {
            $warning = enrol_lti_verify_private_key($record);
            if (!empty($warning)) {
                break;
                echo $OUTPUT->notification($warning, 'notifyproblem');
            }
        }
        $recordset->close();
        */

        // TODO: above code might be useful, but just throwing the key/kid into config for now.

        // Now, set a private key for all existing instances.
        require_once($CFG->dirroot . '/enrol/lti/upgradelib.php');

        $recordset = $DB->get_recordset('enrol_lti_tools');
        foreach ($recordset as $record) {
            $warning = enrol_lti_verify_private_key($record);
            if (!empty($warning)) {
                echo $OUTPUT->notification($warning, 'notifyproblem');
                break;
            }
        }
        $recordset->close();

        // Lti savepoint reached.
        upgrade_plugin_savepoint(true, 2021052501, 'enrol', 'lti');
    }

    if ($oldversion < 2021052502) {
        // Define table enrol_lti_platform_registry to be created.
        $table = new xmldb_table('enrol_lti_platform_registry');

        // Adding fields to table enrol_lti_platform_registry.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('toolid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('platformid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('clientid', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('authenticationrequesturl', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('jwksurl', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('accesstokenurl', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);

        // Adding keys to table enrol_lti_platform_registry.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('toolid', XMLDB_KEY_FOREIGN, ['toolid'], 'enrol_lti_tools', ['id']);
        $table->add_key('toolid_platformid', XMLDB_KEY_UNIQUE, ['toolid', 'platformid']);

        // Conditionally launch create table for enrol_lti_platform_registry.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Lti savepoint reached.
        upgrade_plugin_savepoint(true, 2021052502, 'enrol', 'lti');
    }

    return true;
}
