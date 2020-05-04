videojs-vjsdownload 1.0.4
-------------
https://github.com/7Ds7/videojs-vjsdownload

Instructions to upgrade VideoJS download plugin into Moodle:

1. Copy plugin JS code from: https://raw.githubusercontent.com/7Ds7/videojs-vjsdownload/master/src/plugin.js
2. Paste code between "START NATIVE PLUGIN CODE" and "END NATIVE PLUGIN CODE" comment markers, in amd/src/download.js
3. Remove the following line from pasted plugin code: "import videojs from 'video.js';".
4. Remove the following line from pasted plugin code: "export default vjsdownload;".
5. Change the line: "videojs.registerPlugin('vjsdownload', vjsdownload);"
   to "videojs.registerPlugin('download', vjsdownload);" in the pasted plugin code.
6. Copy plugin SCCS from: https://raw.githubusercontent.com/7Ds7/videojs-vjsdownload/master/src/plugin.scss
7. Convert SASS to CSS, using a SASS compiler. e.g. https://jsonformatter.org/scss-to-css
8. Replace CSS in styles.css with the converted CSS.
9. run grunt from amd/ directory.
10. Update plugin version in this file and in thirdpartylibs.xml file.
