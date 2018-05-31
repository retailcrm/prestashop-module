#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

PRESTASHOP_DIR=$TRAVIS_BUILD_DIR/../PrestaShop

if ! [ -z $BRANCH ]; then
    phpunit
else
    cd $PRESTASHOP_DIR
    composer run-script create-test-db --timeout=0
    php ../PrestaShop/vendor/bin/phpunit -c $TRAVIS_BUILD_DIR/phpunit.xml.dist
fi
