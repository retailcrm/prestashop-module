#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

PRESTASHOP_DIR=$TRAVIS_BUILD_DIR/../PrestaShop

cd $PRESTASHOP_DIR
composer run-script create-test-db --timeout=0
