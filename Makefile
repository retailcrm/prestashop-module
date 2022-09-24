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

setup_apache:
	bash $(PRESTASHOP_DIR)/travis-scripts/setup-php-fpm.sh
	echo "* Preparing Apache ..."
	sudo a2enmod rewrite actions fastcgi alias
    # Use default config
	sudo cp -f $(PRESTASHOP_DIR)/tests/travis-ci-apache-vhost /etc/apache2/sites-available/000-default.conf
	sudo sed -e "s?%PRESTASHOP_DIR%?$(pwd)?g" --in-place /etc/apache2/sites-available/000-default.conf
	sudo chmod 777 -R $(HOME)
    # Starting Apache
	sudo service apache2 restart

before_script: composer
	mkdir coverage
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
	mysql -u root -proot --port $(MYSQL_PORT) -e "DROP DATABASE IF EXISTS \`prestashop\`;"
	rm -rf var/cache/*
	echo "* Installing PrestaShop, this may take a while ...";
	cd $(PRESTASHOP_DIR) && php install-dev/index_cli.php --language=en --country=fr --domain=localhost --db_server=127.0.0.1:$(MYSQL_PORT) --db_name=prestashop --db_user=root --db_create=1 --name=prestashop.unit.test --email=demo@prestashop.com --password=prestashop_demo
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
#ifeq ($(BRANCH), 1.7.5.2)
#	cd $(PRESTASHOP_DIR) && php composer.phar require --dev friendsofphp/php-cs-fixer:2.16.0 --prefer-dist --no-interaction --no-progress --no-scripts
#endif
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
