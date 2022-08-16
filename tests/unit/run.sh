#!/bin/bash

echo
echo '==================  Unit tests =================='
echo

target="$(dirname ${BASH_SOURCE[0]})"

php $target/../../vendor/phpunit/phpunit/phpunit --configuration $target/phpunit.xml --coverage-text $* $target
