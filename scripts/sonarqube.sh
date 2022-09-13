#!/bin/bash

# Start from root directory.
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
ROOT="$(dirname "$DIR")"

cd "$ROOT" || exit 1

# See https://docs.sonarqube.org/latest/setup/get-started-2-minutes/

docker ps | grep sonarqube
status=$?
if [[ $status != 0 ]]; then
	echo "Sonarqube container is not running. Run:"
	echo "docker run -d --name sonarqube -e SONAR_ES_BOOTSTRAP_CHECKS_DISABLE=true -p 9000:9000 sonarqube:latest"
	echo "On the first login, use admin:admin, change the password, use 'eid-login-wordpress' as projectKey and generate a token."
	exit 1
fi

token="changeme"

sonar-scanner \
	-Dsonar.projectKey=eid-login-wordpress \
	-Dsonar.sources=. \
	-Dsonar.host.url=http://127.0.0.1:9000 \
	-Dsonar.login="${token}"
