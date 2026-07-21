#!/bin/sh

set -eu

mode="${DZZ_GATEWAY_AUTH_MODE:-off}"

case "$mode" in
    off)
        source=/etc/nginx/gateway-auth-src/auth-disabled.conf
        ;;
    dzz-authcheck)
        if [ -z "${DZZ_GATEWAY_AUTH_SECRET:-}" ]; then
            echo >&2 'DZZ_GATEWAY_AUTH_SECRET must be set when DZZ_GATEWAY_AUTH_MODE=dzz-authcheck'
            exit 1
        fi
        source=/etc/nginx/gateway-auth-src/auth-enabled.conf
        ;;
    *)
        echo >&2 "Unsupported DZZ_GATEWAY_AUTH_MODE: $mode"
        exit 1
        ;;
esac

mkdir -p /etc/nginx/gateway-auth
cp "$source" /etc/nginx/gateway-auth/active.conf
