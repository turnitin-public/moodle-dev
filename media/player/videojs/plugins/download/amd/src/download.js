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
 * VideoJS download plugin.
 *
 * @package    videojs_download
 * @copyright  2019 Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['media_videojs/video-lazy'], function(videojs) {

/*
 * START NATIVE PLUGIN CODE.
 */

 // Default options for the plugin.
    const defaults = {
      beforeElement: 'fullscreenToggle',
      textControl: 'Download video',
      name: 'downloadButton',
      downloadURL: null
    };

    const vjsButton = videojs.getComponent('Button');

    class DownloadButton extends vjsButton {

      /**
      * Allow sub components to stack CSS class names
      *
      * @return {String} The constructed class name
      * @method buildCSSClass
      */
      buildCSSClass() {
        return `vjs-vjsdownload ${super.buildCSSClass()}`;
      }

      /**
      * Handles click for full screen
      *
      * @method handleClick
      */
      handleClick() {
        let p = this.player();

        window.open(this.options_.downloadURL || p.currentSrc(), 'Download');
        p.trigger('downloadvideo');
      }

    }

    /**
     * Function to invoke when the player is ready.
     *
     * This is a great place for your plugin to initialize itself. When this
     * function is called, the player will have its DOM and child components
     * in place.
     *
     * @function onPlayerReady
     * @param    {Player} player
     * @param    {Object} [options={}]
     */
    const onPlayerReady = (player, options) => {
      let DButton = player.controlBar.addChild(new DownloadButton(player, options), {});

      DButton.controlText(options.textControl);

      player.controlBar.el().insertBefore(DButton.el(),
        player.controlBar.getChild(options.beforeElement).el());

      player.addClass('vjs-vjsdownload');
    };

    /**
     * A video.js plugin.
     *
     * In the plugin function, the value of `this` is a video.js `Player`
     * instance. You cannot rely on the player being in a "ready" state here,
     * depending on how the plugin is invoked. This may or may not be important
     * to you; if not, remove the wait for "ready"!
     *
     * @function vjsdownload
     * @param    {Object} [options={}]
     *           An object of options left to the plugin author to define.
     */
    const vjsdownload = function(options) {
      this.ready(() => {
        onPlayerReady(this, videojs.mergeOptions(defaults, options));
      });
    };

    // Register the plugin with video.js.
    videojs.registerPlugin('download', vjsdownload);

/*
 * END NATIVE PLUGIN CODE.
 */

   return vjsdownload;

});
