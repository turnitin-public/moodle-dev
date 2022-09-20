<?php

namespace core\oauth2\service\nextcloud;

use core_oauth2\form\issuer;

class issuerform extends issuer {

    public function definition() {
        parent::definition();

        $mform = $this->_form;

        // Base URL is required for Nextcloud since it relies on OIDC endpoint discovery.
        $mform->addRule('baseurl', null, 'required', null, 'client');
    }
}
