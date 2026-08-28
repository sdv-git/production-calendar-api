#!/bin/sh
set -eu
php-fpm -F &
exec nginx -g 'daemon off;'
