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
 * Prints an instance of mod_tooltest.
 *
 * @package     mod_tooltest
 * @copyright   2020 Jake Dallimore <jrhdallimore@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
global $DB, $OUTPUT, $USER, $PAGE;

// TODO: Is reidrecting like this, from the tool.php registered OIDC page, with unsigned params, safe?
// The course module ID.
$id = optional_param('id', 0, PARAM_INT);
$type = optional_param('type', 'easy', PARAM_TEXT);

if ($id) {
    $cm             = get_coursemodule_from_id('tooltest', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('tooltest', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    print_error(get_string('missingidandcmid', 'mod_tooltest'));
}

require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

$event = \mod_tooltest\event\course_module_viewed::create(array(
    'objectid' => $moduleinstance->id,
    'context' => $modulecontext
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('tooltest', $moduleinstance);
$event->trigger();

$PAGE->set_url('/mod/tooltest/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);


echo $OUTPUT->header();

echo "<b>Welcome, $USER->firstname $USER->lastname (from the tool's view page)</b>";

?>

    <script src="//cdn.jsdelivr.net/npm/phaser@3.11.0/dist/phaser.js"></script>
    <div id="mycanvas"></div>

    <script>
        <?php
            echo 'var iconset = "'.$type.'";';
        ?>
    </script>

    <script type="text/javascript">

        var config = {
            type: Phaser.AUTO,
            width: 800,
            height: 600,
            physics: {
                default: 'arcade',
                arcade: {
                    gravity: { y: 300 },
                    debug: false
                }
            },
            scene: {
                preload: preload,
                create: create,
                update: update
            },
            parent: 'mycanvas'

        };

        var player;
        var stars;
        var bombs;
        var platforms;
        var cursors;
        var score = 0;
        var gameOver = false;
        var scoreText;

        var game = new Phaser.Game(config);

        function preload ()
        {
            this.load.image('sky', 'assets/sky.png');
            this.load.image('ground', 'assets/platform.png');
            if (iconset == 'hard') {
                this.load.image('star', 'assets/bomb.png');
            } else {
                this.load.image('star', 'assets/star.png');
            }
            this.load.image('bomb', 'assets/bomb.png');
            this.load.spritesheet('dude', 'assets/dude.png', { frameWidth: 32, frameHeight: 48 });
        }

        function create ()
        {
            //  A simple background for our game
            this.add.image(400, 300, 'sky');

            //  The platforms group contains the ground and the 2 ledges we can jump on
            platforms = this.physics.add.staticGroup();

            //  Here we create the ground.
            //  Scale it to fit the width of the game (the original sprite is 400x32 in size)
            platforms.create(400, 568, 'ground').setScale(2).refreshBody();

            //  Now let's create some ledges
            platforms.create(600, 400, 'ground');
            platforms.create(50, 250, 'ground');
            platforms.create(750, 220, 'ground');

            // The player and its settings
            player = this.physics.add.sprite(100, 450, 'dude');

            //  Player physics properties. Give the little guy a slight bounce.
            player.setBounce(0.2);
            player.setCollideWorldBounds(true);

            //  Our player animations, turning, walking left and walking right.
            this.anims.create({
                key: 'left',
                frames: this.anims.generateFrameNumbers('dude', { start: 0, end: 3 }),
                frameRate: 10,
                repeat: -1
            });

            this.anims.create({
                key: 'turn',
                frames: [ { key: 'dude', frame: 4 } ],
                frameRate: 20
            });

            this.anims.create({
                key: 'right',
                frames: this.anims.generateFrameNumbers('dude', { start: 5, end: 8 }),
                frameRate: 10,
                repeat: -1
            });

            //  Input Events
            cursors = this.input.keyboard.createCursorKeys();

            //  Some stars to collect, 12 in total, evenly spaced 70 pixels apart along the x axis
            stars = this.physics.add.group({
                key: 'star',
                repeat: 11,
                setXY: { x: 12, y: 0, stepX: 70 }
            });

            stars.children.iterate(function (child) {

                //  Give each star a slightly different bounce
                child.setBounceY(Phaser.Math.FloatBetween(0.4, 0.8));

            });

            bombs = this.physics.add.group();

            //  The score
            scoreText = this.add.text(16, 16, 'score: 0', { fontSize: '32px', fill: '#000' });

            //  Collide the player and the stars with the platforms
            this.physics.add.collider(player, platforms);
            this.physics.add.collider(stars, platforms);
            this.physics.add.collider(bombs, platforms);

            //  Checks to see if the player overlaps with any of the stars, if he does call the collectStar function
            this.physics.add.overlap(player, stars, collectStar, null, this);

            this.physics.add.collider(player, bombs, hitBomb, null, this);
        }

        function update ()
        {
            if (gameOver)
            {
                return;
            }

            if (cursors.left.isDown)
            {
                player.setVelocityX(-160);

                player.anims.play('left', true);
            }
            else if (cursors.right.isDown)
            {
                player.setVelocityX(160);

                player.anims.play('right', true);
            }
            else
            {
                player.setVelocityX(0);

                player.anims.play('turn');
            }

            if (cursors.up.isDown && player.body.touching.down)
            {
                player.setVelocityY(-330);
            }
        }

        function collectStar (player, star)
        {
            star.disableBody(true, true);

            //  Add and update the score
            score += 10;
            scoreText.setText('Score: ' + score);

            if (stars.countActive(true) === 0)
            {
                //  A new batch of stars to collect
                stars.children.iterate(function (child) {

                    child.enableBody(true, child.x, 0, true, true);

                });

                var x = (player.x < 400) ? Phaser.Math.Between(400, 800) : Phaser.Math.Between(0, 400);

                var bomb = bombs.create(x, 16, 'bomb');
                bomb.setBounce(1);
                bomb.setCollideWorldBounds(true);
                bomb.setVelocity(Phaser.Math.Between(-200, 200), 20);
                bomb.allowGravity = false;

            }
        }

        function hitBomb (player, bomb)
        {
            this.physics.pause();

            player.setTint(0xff0000);

            player.anims.play('turn');

            gameOver = true;
        }

    </script>



<?php

echo $OUTPUT->footer();

