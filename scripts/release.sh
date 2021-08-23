#!/bin/bash

# Start from root directory.
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
SRC="$(dirname "$DIR")/"
DEST="$(dirname "$SRC")/subversion/eidlogin/trunk/"

cd "$SRC" || exit 1

rm -rf ./vendor/

composer install --optimize-autoloader --no-dev
# Required: composer global require coenjacobs/mozart
~/.config/composer/vendor/bin/mozart compose
# Prevent linter error while pushing code to the SVN repo, see:
# https://wordpress.org/support/topic/errors-parsing-standard-input-code-bitwise-or/
rm -rf vendor/symfony/polyfill-mbstring/
composer dump-autoload

# Exclude all hidden files and other unnecessary folders and files.
# Important: don't remove the .svn folder in $DEST!
rsync -av --delete --progress \
	--exclude=".*" \
	--exclude="*.zip" \
	--exclude="cypress*" \
	--exclude="composer.*" \
	--exclude="node_modules" \
	--exclude="package*" \
	--exclude="phpdocs" \
	--exclude="psalm*" \
	--exclude="scripts" \
	--exclude="vendor/twig/twig/doc" \
	"$SRC" \
	"$DEST"
