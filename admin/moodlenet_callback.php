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

require_once(__DIR__ . '/../config.php');

require_login();

/// Headers to make it not cacheable
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

/// Wait as long as it takes for this script to finish
core_php_time_limit::raise();

function get_client(string $scopes = ''): core\oauth2\client {
    global $DB;

    // Naive - just grab the first issuer for 'moodlenet'.
    $issuerid = $DB->get_field('oauth2_issuer', 'id', ['servicetype' => 'moodlenet']);
    $issuer = \core\oauth2\api::get_issuer($issuerid);

    $returnurl = new moodle_url('/admin/moodlenet_callback.php');
    $returnurl->param('callback', 'yes');
    $returnurl->param('sesskey', sesskey());

    return \core\oauth2\api::get_user_oauth_client($issuer, $returnurl, $scopes, true);
}

$client = get_client('scope1 scope2 scope3');
$isloggedin = $client->is_logged_in(); // Will upgrade the auth code to a token.
$isloggedinjs = $isloggedin ? 1 : 0;
global $DB, $USER;
$refreshtoken = $DB->get_record('oauth2_refresh_token', ['userid' => $USER->id, 'issuerid' => $client->get_issuer()->get('id')]);
$hasrefreshtokenjs = $refreshtoken ? 1 : 0;
$error = optional_param('error', '', PARAM_TEXT);
$errordesc = optional_param('error_description', '', PARAM_TEXT);


// call opener window to refresh repository
// the callback url should be something like this:
// http://xx.moodle.com/repository/repository_callback.php?repo_id=1&sid=xxx
// sid is the attached auth token from external source
// If Moodle is working on HTTPS mode, then we are not allowed to access
// parent window, in this case, we need to alert user to refresh the repository
// manually.
$strhttpsbug = json_encode(get_string('cannotaccessparentwin', 'repository'));
$strrefreshnonjs = get_string('refreshnonjsfilepicker', 'repository');
$reloadparent = optional_param('reloadparent', false, PARAM_BOOL);
// If this request is coming from a popup, close window and reload parent window.
if ($reloadparent == true) {
    $js = <<<EOD
<html>
<head>
    <script type="text/javascript">
        window.opener.location.reload();
        window.close();
    </script>
</head>
<body></body>
</html>
EOD;
    die($js);
}

$js =<<<EOD
<html>
<head>
    <script type="text/javascript">
    try {
        if (window.opener) {
            window.opener.doSomething($isloggedinjs, $hasrefreshtokenjs, '$error', '$errordesc');
            window.close();
        } else {
            throw new Error('Whoops!');
        }
    } catch (e) {
        alert({$strhttpsbug});
        window.console.log(e);
    }
    </script>
</head>
<body>
    <noscript>
    {$strrefreshnonjs}
    </noscript>
</body>
</html>
EOD;

die($js);
