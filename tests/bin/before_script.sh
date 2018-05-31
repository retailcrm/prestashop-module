#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

PRESTASHOP_DIR=$TRAVIS_BUILD_DIR/../PrestaShop

cd $PRESTASHOP_DIR

if [ -z $BRANCH ]; then
    cp tests/parameters.yml.travis app/config/parameters.yml
    bash travis-scripts/install-prestashop
else
    bash travis-scripts/install-prestashop.sh
fi
