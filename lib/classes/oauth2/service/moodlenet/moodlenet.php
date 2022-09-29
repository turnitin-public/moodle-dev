<?php

namespace core\oauth2\service\moodlenet;

use core\oauth2\discovery\oauth2_auth_server_metadata;
use core\oauth2\endpoint;
use core\oauth2\issuer;
use core\oauth2\service\v1\service;

class moodlenet implements service {

    protected issuer $issuer;

    protected oauth2_auth_server_metadata $oauth2metadatareader;

    protected bool $discovered = false;

    public function __construct(issuer $issuer, ?oauth2_auth_server_metadata $oauth2metadatareader) {
        $this->issuer = $issuer;
        $this->oauth2metadatareader = $oauth2metadatareader;
    }

    public static function get_template(): ?issuer {
        $record = (object) [
            'name' => 'MoodleNet',
            'image' => 'https://moodle.net/favicon.ico',
            'baseurl' => 'https://moodle.net',
            'showonloginpage' => issuer::SERVICEONLY,
            'servicetype' => 'moodlenet',
        ];

        return new issuer(0, $record);
    }

    public static function get_instance(issuer $issuer): service {
        $issuerurl = $issuer->get('baseurl');
        $metadatareader = !empty($issuerurl) ? new oauth2_auth_server_metadata(new \moodle_url($issuerurl), new \curl()) : null;

        return new self($issuer, $metadatareader);
    }

    public function get_issuer(): issuer {
        $this->get_issuer_configuration();
        return $this->issuer;
    }

    public function get_endpoints(): array {
        $this->get_issuer_configuration();
        return array_values($this->endpoints);
    }

    public function get_field_mappings(): array {
        // MoodleNet isn't used as an identity provider.
        return [];
    }

    protected function get_issuer_configuration(): void {
        if ($this->discovered || empty($this->oauth2metadatareader)) {
            return;
        }

        $this->issuerconfig = $this->oauth2metadatareader->read_configuration();
        foreach ($this->issuerconfig as $key => $value) {
            if ($key == 'scopes_supported') {
                $this->issuer->set('scopessupported', implode(' ', $value));
            }
        }

        foreach ($this->oauth2metadatareader->get_endpoints() as $name => $url) {
            $record = (object) [
                'name' => $name,
                'url' => $url
            ];
            $this->endpoints[$record->name] = new endpoint(0, $record);
        }
        $this->discovered = true;
    }
}
