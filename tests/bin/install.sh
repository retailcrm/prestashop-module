#!/usr/bin/env bash

if [ -z $TRAVIS_BUILD_DIR ]; then
	exit 0;
fi

PRESTASHOP_DIR=$TRAVIS_BUILD_DIR/../PrestaShop

create_db() {
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"
}

clone_prestashop() {
    cd ..
	git clone https://github.com/PrestaShop/PrestaShop
	cd PrestaShop
	if ! [ -z $BRANCH ]; then
        git checkout $BRANCH;
    else
        composer install;
    fi
}

install_prestashop() {
    cd $PRESTASHOP_DIR

    php install-dev/index_cli.php \
        --domain=example.com \
        --db_server=$DB_HOST \
        --db_name=$DB_NAME \
        --db_user=$DB_USER
}

create_db
clone_prestashop
install_prestashop
