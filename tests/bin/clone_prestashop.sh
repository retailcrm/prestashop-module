#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

PRESTASHOP_DIR=$TRAVIS_BUILD_DIR/../PrestaShop

cd ..
git clone https://github.com/PrestaShop/PrestaShop
cd PrestaShop

if ! [ -z $BRANCH ]; then
    git checkout $BRANCH;
    cd tests
    composer install
else
    composer install --prefer-dist --no-interaction --no-progress
fi
