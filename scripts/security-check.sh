#!/bin/bash

# This script executes some security related tools.

# Start from root directory.
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
ROOT="$(dirname "$DIR")"

cd "$ROOT" || exit 1

# Checks for PHP packages with known security vulnerabilities.
if ! command -v local-php-security-checker &>/dev/null; then
	echo "----------------------------------------------------------"
	echo "local-php-security-checker could not be found, skipping..."
	echo "----------------------------------------------------------"
else
	local-php-security-checker
fi

# Also use trivy to scan for vulnerabilities.
if ! command -v trivy &>/dev/null; then
	echo "-------------------------------------"
	echo "trivy could not be found, skipping..."
	echo "-------------------------------------"
else
	trivy fs ./
fi

# Checks for JavaScript packages with known security vulnerabilities.
npm audit --omit=dev
