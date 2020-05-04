This is where you can add VideoJS plugins to be used in your Moodle.

VideoJS plugins for Moodle follow a similar pattern to other Moodle Plugins.
A simple example for a VIdeoJS plugin in Moodle is the download plugin,
that can be found in media/player/videojs/plugins//download

The name of your plugin is important and is used in several places:

Component name
If you plugin is called "download" then the componennt name of your
plugin is: videojs_download

media/player/videojs/plugins/pluginname
This is the directory where you plugin code is.
The name of the directory needs to be the name of your plugin.

media/player/videojs/plugins/pluginname/classes/pluginname.php
This is where the main class for your plugin is defined.
The name of this PHP file must match the name of your plugin
The class that is defined in this plugin is also called the same as
the name of your plugin.
This class only requires one method to be defined: get_plugin_config()
This method takes no arguments and returns a standard class of the
settings your plugin needs. These are passed as JSON to the VideoJS player.

media/player/videojs/plugins/pluginname/amd/src/pluginname.js
This is where the main code for your VideoJS plugin is defined.
VideoJS plugins are written in Javascript and added to the VideoJS
object at page load time.

Documentation on how to create a basic VideoJS plugin
can be found: https://docs.videojs.com/tutorial-plugins.html

A list of existing VideoJS Plugins can be found: https://github.com/videojs/video.js/wiki/Plugins
These plugins will need to be converted into a Moodle compatible
format, before you can use them with Moodle.

All VideoJS plugins in Moodle must include the VideoJS lazy loader,
i.e "define(['media_videojs/video-lazy'], function(videojs) {"

Also all plugins must register themselves,
e.g. "videojs.registerPlugin('download', vjsdownload);" 
Where the first parameter in the registerPlugin method is the name of you plugin.

User configurable plugin settings.
If you wish to have user configurable settings for your plugin
define a settings file at: media/player/videojs/plugins/pluginname/settings.php
Defined settings should be processed by the "get_plugin_config()" method
in your plugins class.

Custom plugin styling.
If your plugin requires CSS then this can be included the
same way as for other plugins by creating a "styles.css" file
and adding it to the base directory of your plugin. 
e.g media/player/videojs/plugins/pluginname/styles.css
