<?php

namespace core\oauth2\service\imsobv2p1;

use core_oauth2\form\issuer;

class issuerform extends issuer {

    public function definition() {
        parent::definition();

        $mform = $this->_form;
        $issuer = $this->get_persistent();

        // Base URL is required since the service uses endpoint discovery according to its own spec.
        if ($this->type == 'imsobv2p1' || $issuer->get('servicetype') == 'imsobv2p1') {
            $mform->addRule('baseurl', null, 'required', null, 'client');
        }

        // Remove the rules for clientid and client secret.
        // This service supports dynamic registration, which is performed only when the ID and secret are omitted.
        unset($this->_form->_rules['clientid']);
        unset($this->_form->_rules['clientsecret']);
        $clientidkey = array_search('clientid', $this->_form->_required);
        if ($clientidkey !== false) {
            unset($this->_form->_required[$clientidkey]);
        }
        $clientsecretkey = array_search('clientsecret', $this->_form->_required);
        if ($clientidkey !== false) {
            unset($this->_form->_required[$clientsecretkey]);
        }
    }
}
