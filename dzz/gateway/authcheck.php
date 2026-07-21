<?php

if (!defined('IN_DZZ')) {
    exit('Access Denied');
}

use dzz\gateway\AuthCheck;

$configuredSecret = isset($_G['config']['security']['gateway_auth_secret'])
    ? (string)$_G['config']['security']['gateway_auth_secret']
    : '';

if ($configuredSecret === '') {
    $configuredSecret = (string)getenv('DZZ_GATEWAY_AUTH_SECRET');
}

$requestSecret = isset($_SERVER['HTTP_X_DZZ_GATEWAY_SECRET'])
    ? (string)$_SERVER['HTTP_X_DZZ_GATEWAY_SECRET']
    : '';
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$status = AuthCheck::status($configuredSecret, $requestSecret, $_G['uid'], $method);

if ($status === 405) {
    header('Allow: GET, HEAD');
}

http_response_code($status);
header('Content-Length: 0');
exit;
