<?php

namespace core\oauth2;

use core\oauth2\service\v1\service;

class helper {

    public static function get_service_form(?\core\oauth2\issuer $issuer, string $type = '',
            bool $istemplate = false): \core_oauth2\form\issuer {

        // Grab the service-specific form if present, otherwise default to \core_oauth2\form\issuer.
        if ($type) {
            $formclass = "\\core\\oauth2\\service\\{$type}\\issuerform";
            if (class_exists($formclass) && is_subclass_of($formclass, \core_oauth2\form\issuer::class)) {
                return new $formclass(null, ['persistent' => $issuer, 'type' => $type, 'istemplate' => $istemplate]);
            }
        }
        return new \core_oauth2\form\issuer(null, ['persistent' => $issuer, 'type' => $type, 'istemplate' => $istemplate]);
    }

    public static function get_service_names(): array {
        global $CFG;
        $directory = $CFG->libdir . '/classes/oauth2/service/';
        return array_values(array_filter(scandir($directory), function($f) use ($directory) {
            return is_dir($directory . '/' . $f) && !in_array($f, ['.', '..', 'v1']);
        }));
    }

    public static function get_service_classname(string $issuertype): string {
        $serviceclass = "\\core\\oauth2\\service\\{$issuertype}\\{$issuertype}";
        if (class_exists($serviceclass) && is_subclass_of($serviceclass, service::class)) {
            return $serviceclass;
        }
        return 'core\\oauth2\\service\\custom\\custom';
    }

    public static function get_service_instance(issuer $issuer): service {
        $issuertype = $issuer->get('servicetype');
        if (!empty($issuertype)) {
            $serviceclass = "\\core\\oauth2\\service\\{$issuertype}\\{$issuertype}";
            if (class_exists($serviceclass) && is_subclass_of($serviceclass, service::class)) {
                return $serviceclass::get_instance($issuer);
            }
        }

        $defaultclass = 'core\\oauth2\\service\\custom\\custom';
        return $defaultclass::get_instance($issuer);
    }
}
