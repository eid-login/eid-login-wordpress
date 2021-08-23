#!/bin/bash

# Start from root directory.
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
ROOT="$(dirname "$DIR")"

cd "$ROOT" || exit 1

rm eidlogin.zip

rm -rf ./vendor/

composer install --optimize-autoloader --no-dev
# Required: composer global require coenjacobs/mozart
~/.config/composer/vendor/bin/mozart compose
composer dump-autoload

zip -r eidlogin.zip ./ \
	-x '*.git*' \
	-x '*.phpdoc*' \
	-x '*.scannerwork*' \
	-x '*cypress*' \
	-x '*node_modules*' \
	-x '*phpdocs*' \
	-x '*scripts*' \
	-x '*vendor/twig/twig/doc*'
