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

composer: clone_prestashop clone_composer fix-version-bugs
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) && php composer.phar install --prefer-dist --no-interaction --no-progress
else
	cd $(PRESTASHOP_DIR)/tests && composer install
endif

clone_prestashop:
	cd $(ROOT_DIR)/../ && git clone https://github.com/PrestaShop/PrestaShop
	cd $(PRESTASHOP_DIR) && git checkout $(BRANCH)

clone_composer:
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) \
        && php -r "copy('https://getcomposer.org/download/1.10.17/composer.phar', 'composer.phar');"
endif

before_script: composer
ifneq ("$(wildcard $(PRESTASHOP_DIR)/travis-scripts/install-prestashop)","")
	ifeq ($(COMPOSERV1),1)
		cd $(PRESTASHOP_DIR) \
			&& sed -i 's/mysql -u root/mysql -u root --port $(MYSQL_PORT)/g' travis-scripts/install-prestashop \
			&& sed -i 's/--db_server=127.0.0.1 --db_name=prestashop/--db_server=127.0.0.1:$(MYSQL_PORT) --db_name=prestashop --db_user=root/g' travis-scripts/install-prestashop \
			&& bash travis-scripts/install-prestashop
	else
		cd $(PRESTASHOP_DIR) \
			&& sed -i 's/mysql -u root/mysql -u root -proot --port $(MYSQL_PORT)/g' travis-scripts/install-prestashop.sh \
			&& sed -i 's/--db_server=127.0.0.1 --db_name=prestashop/--db_server=127.0.0.1:$(MYSQL_PORT) --db_name=prestashop --db_user=root/g' travis-scripts/install-prestashop.sh \
			&& bash travis-scripts/install-prestashop.sh
	endif
else
	rm -rf var/cache/*
	echo "* Installing PrestaShop, this may take a while ...";

ifeq ($(LOCAL_TEST),1)
	cd $(PRESTASHOP_DIR) && php install-dev/index_cli.php --db_server=db --db_user=root --db_create=1
else
	mkdir coverage
	cd $(PRESTASHOP_DIR) && php install-dev/index_cli.php --db_server=127.0.0.1:$(MYSQL_PORT)--db_user=root --db_create=1
endif
endif

fix-version-bugs:
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) \
        && sed -i 's/throw new Exception/#throw new Exception/g' src/PrestaShopBundle/Install/DatabaseDump.php
endif
ifeq ($(BRANCH), 1.7.4.4)
	cd $(PRESTASHOP_DIR) \
		&&  sed -i 's/$$install->installModules();/$$install->setTranslator(\\Context::getContext()->getTranslator());\n\t$$install->installModules();/g' tests/PrestaShopBundle/Utils/DatabaseCreator.php
	cat $(PRESTASHOP_DIR)/tests/PrestaShopBundle/Utils/DatabaseCreator.php | grep -A 3 -B 3 'install->installModules()'
endif

ifeq ($(BRANCH),$(filter $(BRANCH),1.7.6.9 1.7.7.8))
	cd $(PRESTASHOP_DIR) \
		&&  sed -i "s/SymfonyContainer::getInstance()->get('translator')/\\\\Context::getContext()->getTranslator()/g" classes/lang/DataLang.php
	cat $(PRESTASHOP_DIR)/classes/lang/DataLang.php | grep -A 3 -B 3 'this->translator = '

	cd $(PRESTASHOP_DIR) \
		&&  sed -i "s/SymfonyContainer::getInstance()->get('translator')/\\\\Context::getContext()->getTranslator()/g" classes/Language.php
	cat $(PRESTASHOP_DIR)/classes/Language.php | grep -A 3 -B 3 'translator = '
endif

lint:
	php-cs-fixer fix --config=$(ROOT_DIR)/.php-cs-fixer.php -v

lint-docker:
	docker run --rm -it -w=/app -v ${PWD}:/app oskarstark/php-cs-fixer-ga:latest --config=.php-cs-fixer.php -v

	# todo moveto version
test:
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) && php composer.phar run-script create-test-db --timeout=0
	cd $(PRESTASHOP_DIR) && php vendor/bin/phpunit -c $(ROOT_DIR)/phpunit.xml.dist
else
	phpunit -c phpunit.xml.dist
endif

coverage:
	wget https://phar.phpunit.de/phpcov-2.0.2.phar && php phpcov-2.0.2.phar merge coverage/ --clover coverage.xml

run_local_tests:
	docker-compose up -d --build
	docker exec app_test make before_script test
	docker-compose down
