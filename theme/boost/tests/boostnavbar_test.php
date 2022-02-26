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

namespace theme_boost;

/**
 * Tests for the boostnavbar class.
 *
 * @package    theme_boost
 * @copyright  2021 Peter Dias
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \theme_boost\boostnavbar
 */
class boostnavbar_test extends \advanced_testcase {

    /**
     * Data provider for testing get_items().
     *
     * @return array the array of test scenario data.
     */
    public function get_items_provider() {
        global $CFG;

        return [
            'Item with identical action url and text exists in the secondary navigation menu.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php')
                    ],
                ],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' =>new \moodle_url('/page1.php'),
                    ],
                    [
                        'text' => 'Node 2',
                        'action' =>new \moodle_url('/page2.php'),
                    ],
                    [
                        'text' => 'Node 3',
                        'action' =>new \moodle_url('/page1.php'),
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 3',
                        'haslink' => false, // Last item action never has a link.
                    ]
                ]
            ],
            'Multiple items with identical action url and text exist in the secondary navigation menu.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php')
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \moodle_url('/page3.php')
                    ],
                ],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => "{$CFG->wwwroot}/page2.php",
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \action_link(new \moodle_url('/page3.php'), 'Action link')
                    ],
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php')
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 4',
                        'haslink' => false, // Last item action never has a link.
                    ]
                ]
            ],
            'Multiple items exist in secondary nav, resulting in a single item once deduplication has occurred.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php')
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \moodle_url('/page3.php')
                    ],
                ],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => "{$CFG->wwwroot}/page2.php",
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \action_link(new \moodle_url('/page3.php'), 'Action link')
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => []
            ],
            'No items with identical action url and text in the secondary navigation menu.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php')
                    ],
                ],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php')
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \moodle_url('/page1.php')
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 3',
                        'haslink' => false, // Last item action never has a link.
                    ]
                ]
            ],
            'Breadcrumb items with identical text and action url (actions of same type moodle_url).' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' =>new \moodle_url('/page1.php'),
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 4',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => false, // Last item action never has a link.
                    ]
                ]
            ],
            'Breadcrumb items with identical text and action url (actions of different type moodle_url/text).' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => "{$CFG->wwwroot}/page2.php?id=1"
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 4',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => false, // Last item action never has a link.
                    ]
                ]
            ],
            'Breadcrumb items with identical text and action url (actions of different type moodle_url/action_link).' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \action_link(new \moodle_url('/page2.php', ['id' => 1]), 'Action link')
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 4',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => false, // Last item action never has a link.
                    ]
                ]
            ],
            'Breadcrumbs items with identical text but not identical action url.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 2])
                    ],
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php', ['id' => 1])
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 4',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'Breadcrumb items with identical action url but not identical text.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php', ['id' => 1])
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 3',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 4',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'Breadcrumb items without any identical action url or text.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \moodle_url('/page3.php', ['id' => 1])
                    ],
                    [
                        'text' => 'Node 4',
                        'action' => new \moodle_url('/page4.php', ['id' => 1])
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Node 1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 3',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Node 4',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'All nodes have links including leaf node. Set to remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => new \moodle_url('/'),
                        'type' => null
                    ],
                    [
                        'text' => 'node2',
                        'action' => new \moodle_url('/'),
                        'type' => null
                    ],
                    [
                        'text' => 'node3',
                        'action' => new \moodle_url('/'),
                        'type' => null
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE // Section nodes are removed on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'Only some parent nodes have links. Leaf node has a link. Set to remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => null,
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'node3',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE // Section nodes are removed on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'All parent nodes do not have links. Leaf node has a link. Set to remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => null,
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => null,
                        'type' => null,
                    ],
                    [
                        'text' => 'node3',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE // Section nodes are removed on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'All parent nodes have links. Leaf node does not has a link. Set to remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'node3',
                        'action' => null,
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE // Section nodes are removed on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'All parent nodes do not have links. Leaf node does not has a link. Set to remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => null,
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => null,
                        'type' => null,
                    ],
                    [
                        'text' => 'node3',
                        'action' => null,
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE // Section nodes are removed on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'Some parent nodes do not have links. Leaf node does not has a link. Set to remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => null,
                        'type' => null,
                    ],
                    [
                        'text' => 'node3',
                        'action' => null,
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE // Section nodes are removed on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'All nodes have links links including leaf node and section nodes. Set to remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'sectionnode',
                        'action' => new \moodle_url('/'),
                        'type' => \navigation_node::TYPE_SECTION,
                    ],
                    [
                        'text' => 'node3',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_SINGLEPAGE // Section nodes are removed on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'All nodes have links including leaf node and section nodes. Set to not remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'sectionnode',
                        'action' => new \moodle_url('/'),
                        'type' => \navigation_node::TYPE_SECTION,
                    ],
                    [
                        'text' => 'node3',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_MULTIPAGE // No section node removal on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'General',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node1',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'sectionnode',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ],
            'Only some parent nodes have links. Section node does not have a link. Set to not remove section nodes.' => [
                'navmenuname' => 'secondary',
                'navmenunodes' => [],
                'navbarnodes' => [
                    [
                        'text' => 'node1',
                        'action' => null,
                        'type' => null,
                    ],
                    [
                        'text' => 'node2',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                    [
                        'text' => 'sectionnode',
                        'action' => null,
                        'type' => \navigation_node::TYPE_SECTION,
                    ],
                    [
                        'text' => 'node3',
                        'action' => new \moodle_url('/'),
                        'type' => null,
                    ],
                ],
                'setup' => [
                    'courseconfig' => [
                        'shortname' => 'Example course',
                        'coursedisplay' => COURSE_DISPLAY_MULTIPAGE // No section node removal on 'module' context pages.
                    ],
                    'moduleconfig' => [
                        'name' => 'Example module'
                    ]
                ],
                'expected' => [
                    [
                        'content' => 'Example course',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'General',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'Example module',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node2',
                        'haslink' => true,
                    ],
                    [
                        'content' => 'node3',
                        'haslink' => false, // Last item action never has a link.
                    ],
                ]
            ]
            /*'Item with identical action url and text exists in the primary navigation menu.' => [
                'navmenuname' => 'primary',
                'navmenunodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                ],
                'navbarnodes' => [
                    [
                        'text' => 'Node 1',
                        'action' => new \moodle_url('/page1.php')
                    ],
                    [
                        'text' => 'Node 2',
                        'action' => new \moodle_url('/page2.php')
                    ],
                    [
                        'text' => 'Node 3',
                        'action' => new \moodle_url('/page1.php'),
                    ]
                ],
                'context' => 'course',
                'expected' => ['Node 2', 'Node 3']
            ],*/
        ];
    }

    /**
     * Test the boostnavbar::get_items() method.
     *
     * @covers ::get_items
     * @dataProvider get_items_provider
     * @param string $navmenuname The name of the navigation menu we would like to use (primary or secondary)
     * @param array $navmenunodes The array containing the text and action of the nodes to be added to the navigation menu
     * @param array $navbarnodes Array containing the text => action of the nodes to be added to the navbar
     * @param array $setup array containing various course and module setup options
     * @param array $expected the array of expected items.
     */
    public function test_get_items(string $navmenuname, array $navmenunodes, array $navbarnodes, array $setup, array $expected) {
        $this->resetAfterTest();

        // Note:
        // Navmenu is the primary or secondary nav.
        // Navbar is the generic breadcrumbs (also used by classic theme), on which boostnavbar depends to create its breadcrumbs.

        // Internals of boostnavbar use the global page, so we must synchronise this with the mockpage below.
        global $PAGE;
        $PAGE = new \moodle_page();

        // Get a string representing the page context so we know what to create below.
        $pagecontext = 'system';
        $pagecontext = !empty($setup['courseconfig']) ? 'course' : $pagecontext;
        $pagecontext = ($pagecontext == 'course' && !empty($setup['moduleconfig'])) ? 'module' : $pagecontext;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        if ($pagecontext == 'course' || $pagecontext == 'module') {
            $coursecat = $this->getDataGenerator()->create_category(['name' => 'Example category']);
            $courseargs = [
                'shortname' => $setup['courseconfig']['shortname'],
                'fullname' => $setup['courseconfig']['shortname'],
                'category' => $coursecat->id,
                'coursedisplay' => $setup['courseconfig']['coursedisplay'] ?? 0
            ];
            $course = $this->getDataGenerator()->create_course($courseargs);

            $PAGE->set_course($course);
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        }
        if ($pagecontext == 'module') {
            $module = $this->getDataGenerator()->create_module('assign', ['name' => $setup['moduleconfig']['name'], 'course' => $course]);
            $cm = get_coursemodule_from_instance('assign', $module->id);
            $PAGE->set_cm($cm, $course, $module);
        }

        switch ($navmenuname) {
            case 'primary':
                $navigationmenu = new \core\navigation\views\primary($PAGE);
                break;
            case 'secondary':
            default:
                $navigationmenu = new \core\navigation\views\secondary($PAGE);
                break;
        }
        foreach ($navmenunodes as $navmenunode) {
            $navigationmenu->add($navmenunode['text'], $navmenunode['action'], \navigation_node::TYPE_CUSTOM);
        }
        if ($navmenuname == 'secondary' || is_null($navmenuname)) {
            $PAGE->set_secondarynav($navigationmenu);
        }

        // Get a page instance, with 'magic_get_navbar' mocked to return a mock navbar.
        $mockpage = $this->createPartialMock(\moodle_page::class, ['magic_get_navbar']);

        // Setup page context.
        if ($pagecontext == 'course') {
            $PAGE->set_url('/course/view.php');
            $mockpage->set_url('/course/view.php');
            $mockpage->set_course($course);
        } else if ($pagecontext == 'module') {
            $PAGE->set_url('/mod/assign/view.php');
            $mockpage->set_url('/mod/assign/view.php');
            $mockpage->set_cm($cm, $course, $module);
        }

        // Create a navbar and return it from the mocked page magic_get_navbar method.
        $navbar = new \navbar($mockpage);
        foreach ($navbarnodes as $node) {
            $navbar->add($node['text'], $node['action'], $node['type'] ?? \navigation_node::TYPE_CUSTOM);
        }
        $mockpage->method('magic_get_navbar')->willReturnCallback(function () use ($navbar) {
            return $navbar;
        });

        // Verify the output of boostnavbar->get_items().
        $boostnavbar = new boostnavbar($mockpage);
        $items = $boostnavbar->get_items();
        $associtems = [];
        foreach ($items as $item) {
            $associtems[] = [
                'content' => $item->get_content(),
                'haslink' => $item->has_action()
            ];
        }
        $this->assertEquals($expected, $associtems);
    }
}
