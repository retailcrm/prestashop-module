#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

PRESTASHOP_DIR=$TRAVIS_BUILD_DIR/../PrestaShop

cd $PRESTASHOP_DIR

if [ -z $BRANCH ]; then
	if [ -f "tests/parameters.yml.travis" ]; then
	    cp tests/parameters.yml.travis app/config/parameters.yml
    else
        cp tests-legacy/parameters.yml.travis app/config/parameters.yml
    fi

    bash travis-scripts/install-prestashop
else
    bash travis-scripts/install-prestashop.sh
fi
