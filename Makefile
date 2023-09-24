include .env

ROOT_DIR=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
PRESTASHOP_DIR=$(ROOT_DIR)/../PrestaShop
FILE = $(ROOT_DIR)/VERSION
VERSION = `cat $(FILE)`
ARCHIVE_NAME = '/tmp/retailcrm-'$(VERSION)'.zip'

.PHONY: build_archive delete_archive

build_archive:
	zip -r $(ARCHIVE_NAME) ./retailcrm/*
	zip -r /tmp/retailcrm.zip ./retailcrm/*

delete_archive:
	rm -f $(ARCHIVE_NAME)
	rm -f /tmp/retailcrm.zip

clone_prestashop:
	cd $(ROOT_DIR)/../ && git clone https://github.com/PrestaShop/PrestaShop
	cd $(PRESTASHOP_DIR) && git checkout $(BRANCH)

# Required for versions 1.7.7.x - 1.7.8.x
# Only this command work in Makefile for replace $sfContainer->get('translator')
# sed -i 's/$$sfContainer->get('"'"'translator'"'"')/Context::getContext()->getTranslator()/g' classes/Language.php
fix_lang_bugs:
	cd $(PRESTASHOP_DIR) && sed -i \
		-e "s/throw new Exception/#throw new Exception/g" \
        -e "s/SymfonyContainer::getInstance()->get('translator')/Context::getContext()->getTranslator()/g" \
        -e 's/$$sfContainer->get('"'"'translator'"'"')/Context::getContext()->getTranslator()/g' \
			src/PrestaShopBundle/Install/DatabaseDump.php classes/lang/DataLang.php classes/Language.php

install_composer:
# Required for versions 1.7.7.x
ifeq ($(COMPOSERV1),1)
	 composer self-update --1
endif
	cd $(PRESTASHOP_DIR) && composer install

install_prestashop: clone_prestashop fix_lang_bugs install_composer
ifeq ($(LOCAL_TEST),1)
	cd $(PRESTASHOP_DIR) && php install-dev/index_cli.php --db_server=db --db_user=root --db_create=1
else
	cd $(PRESTASHOP_DIR) && php install-dev/index_cli.php --db_server=127.0.0.1:$(MYSQL_PORT) --db_user=root --db_create=1
	mkdir coverage
endif

lint:
	php-cs-fixer fix --config=$(ROOT_DIR)/.php-cs-fixer.php -v

lint-docker:
	docker run --rm -it -w=/app -v ${PWD}:/app oskarstark/php-cs-fixer-ga:latest --config=.php-cs-fixer.php -v

test:
	cd $(PRESTASHOP_DIR) && composer run-script create-test-db --timeout=0

ifeq ($(COMPOSERV1),1)
	phpunit -c $(ROOT_DIR)/phpunit.xml.dist
else
	phpunit -c phpunit.xml.dist
endif

coverage:
	wget https://phar.phpunit.de/phpcov-2.0.2.phar && php phpcov-2.0.2.phar merge coverage/ --clover coverage.xml

run_local_tests:
	docker-compose up -d --build
	docker exec app_prestashop_test make install_prestashop test
	docker-compose down
