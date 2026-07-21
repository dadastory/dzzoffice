<?php

namespace dzz\gateway;

class AuthCheck {

    public static function status($configuredSecret, $requestSecret, $uid, $method) {
        if ($configuredSecret === '') {
            return 404;
        }

        if ($method !== 'GET' && $method !== 'HEAD') {
            return 405;
        }

        if ($requestSecret === '' || !hash_equals($configuredSecret, $requestSecret)) {
            return 403;
        }

        if ((int)$uid < 1) {
            return 401;
        }

        return 204;
    }
}
