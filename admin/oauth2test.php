<?php

// Page to render a link with target="_blank" link and provide some simple js to call when the oauth2 auth code upgrade is complete.
// The full flow is as follows (all steps and pages):
// - This page loads a link <a href="issuer/authorize" target="_blank">Authorize</a>
// - Clicking the link opens the authorization endpoint in a new window
// - User interacts with auth server, granting approval (or not - see cancel case)
// - Auth server sends back the auth code to moodlenet_callback.php
// - moodlenet_callback.php exchanges (via backchannel) the auth code for an access token
// - moodlenet_callback.php then calls a function on the window.opener, then closes itself.
require_once(__DIR__ . '/../config.php');

require_login();
$PAGE->set_url('/admin/oauth2test.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title("MoodleNet - example OAuth 2 workflow");
$PAGE->set_heading("MoodleNet - example OAuth 2 workflow");

// Bits we need to create the link, or to load the js.
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

// Just handles purging to test the refresh flow.
$purge = optional_param('purge', false, PARAM_BOOL);
if ($purge) {
    // Purge the access token on page load so we can test refresh by reloading the page after a successful authorization.
    $name = 'oauth2-state-'.$client->get_issuer()->get('id');
    unset($SESSION->{$name});

    $purgeall = optional_param('buttonpurgealltokens', '', PARAM_RAW);
    if ($purgeall) {
        $DB->delete_records('oauth2_refresh_token', ['userid' => $USER->id, 'issuerid' => $client->get_issuer()->get('id')]);
    }
    redirect('oauth2test.php');
}

global $DB;
$refreshtoken = $DB->get_record('oauth2_refresh_token', ['userid' => $USER->id, 'issuerid' => $client->get_issuer()->get('id')]);
$hasRefreshToken = $refreshtoken ? 'Yes': 'No';
$hasAccessToken = $client->get_accesstoken() ? 'Yes' : 'No';
$refreshing = ($hasRefreshToken == 'Yes' && $hasAccessToken == 'No') ? 'No access token found.<br>Exchanging refresh token for new access token...' : '';
$hasAccessToken = $client->is_logged_in() ? 'Yes': 'No';
$refreshing = $refreshing ? $refreshing .'<br>Done.<br><br>' : '';
$alreadyauthd = empty($refreshing) && $hasAccessToken == 'Yes' ? 'You have already granted authorization and have an access token<br><br>' : '';

$js = <<<EOD
<script>
    // Called from moodlenet_callback (the popup window) when authorized (right before it closes itself).
    window.doSomething = (hasAccessToken, hasRefreshToken, error, errorDescription) => {
        const hasAccessTokenTxt = hasAccessToken ? 'Yes' : 'No';
        const hasRefreshTokenTxt = hasRefreshToken ? 'Yes' : 'No';

        if (error) {
            const errorTxt = errorDescription ? errorDescription : error;
            document.getElementById('authnotice').innerHTML = 'Authorization error. <br>Error: ' + errorTxt;
        } else {
            document.getElementById('authbutton').remove();
            document.getElementById('authnotice').innerHTML = 'Authorization complete.';
        }
        document.getElementById('authnotice').innerHTML += '<br><br>Has access token: ' + hasAccessTokenTxt +
        '<br>Has refresh token: ' + hasRefreshTokenTxt;
    };

    // Open the new window with js, so it can be closed with js.
    document.getElementById('authbutton').addEventListener('click', e => {
        window.open('{$client->get_login_url()->out(false)}', 'moodlenet_auth', 'location=0,status=0,width=500,height=300,scrollbars=yes');
    });
</script>
EOD;


// Display stuff.
echo $OUTPUT->header();
if (empty($alreadyauthd) && empty($refreshing)) {
    echo html_writer::nonempty_tag('button', 'Authorize', ['id' => 'authbutton', 'class' => 'btn btn-primary mt-4']);
}
echo html_writer::div($refreshing . $alreadyauthd . 'Has access token: '.$hasAccessToken .'<br>Has refresh token: '.$hasRefreshToken, 'mt-4', ['id' => 'authnotice']);
echo html_writer::empty_tag('br');
echo html_writer::div('
<form action="oauth2test.php" method="post">
<input type="hidden" name="purge" value="true">
<button name="buttonpurgeaccesstoken" value="purgeaccess" class="btn btn-primary">Purge access token and reload</button>
<button name="buttonpurgealltokens" value="purgeall" class="btn btn-primary">Purge both tokens and reload</button>
</form>
');

echo $js;
echo $OUTPUT->footer();
