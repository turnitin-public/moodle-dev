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
 * Contains tests for the legacy_user_repository.
 *
 * @package enrol_lti
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace enrol_lti\local\ltiadvantage\repository;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lti_advantage_testcase.php');

/**
 * Tests for legacy_user_repository.
 *
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class legacy_user_repository_testcase extends \lti_advantage_testcase {
    /**
     * Setup run for each test case.
     */
    protected function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test the find_by_consumer method with a range of cases.
     *
     * @dataProvider find_by_consumer_provider
     * @param array $params array containing the params to use for the find_by_consumer() method call.
     * @param bool $found whether or not the user could be found.
     */
    public function test_find_by_consumer(array $params, bool $found) {
        // Set up legacy consumer information.
        $course = $this->getDataGenerator()->create_course();

        $legacydata = [
            'users' => [
                ['user_id' => '123-abc'],
            ],
            'consumer_key' => 'CONSUMER_1',
            'tools' => [
                ['secret' => 'toolsecret1'],
            ]
        ];
        [$tools, $consumer, $users] = $this->setup_legacy_data($course, $legacydata);

        $legacyuserrepo = new legacy_user_repository();

        // Find the legacy user associated with the consumer, if any.
        $legacyuser = $legacyuserrepo->find_by_consumer($params['legacy_consumer_key'], $params['legacy_user_id']);
        if ($found) {
            $this->assertInstanceOf(\stdClass::class, $legacyuser);
            $this->assertEquals($users[0]->username, $legacyuser->username);
        } else {
            $this->assertNull($legacyuser);
        }
    }

    /**
     * Data provider for testing find_by_consumer.
     *
     * @return array[] the test case data.
     */
    public function find_by_consumer_provider(): array {
        return [
            'Legacy user exists' => [
                'params' => [
                    'legacy_consumer_key' => 'CONSUMER_1',
                    'legacy_user_id' => '123-abc'
                ],
                'found' => true
            ],
            'Non existent legacy username' => [
                'params' => [
                    'legacy_consumer_key' => 'CONSUMER_1',
                    'legacy_user_id' => 'non-existent-user-id'
                ],
                'found' => false
            ],
            'Non existent legacy consumerkey' => [
                'params' => [
                    'legacy_consumer_key' => 'CONSUMER_2',
                    'legacy_user_id' => '123-abc'
                ],
                'found' => false
            ]
        ];
    }
}
