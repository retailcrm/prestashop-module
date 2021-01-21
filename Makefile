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

composer: clone_prestashop
	cd $(PRESTASHOP_DIR) && git checkout $(BRANCH)
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) && php -r "copy('https://getcomposer.org/download/1.10.17/composer.phar', 'composer.phar');" && php composer.phar install --prefer-dist --no-interaction --no-progress
else
	cd $(PRESTASHOP_DIR)/tests && composer install
endif

clone_prestashop:
	cd $(ROOT_DIR)/../ && git clone https://github.com/PrestaShop/PrestaShop

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
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) && sed -i 's/--db_name=prestashop/--db_name=prestashop --db_user=root --db_password=root/g' travis-scripts/install-prestashop && bash travis-scripts/install-prestashop
else
	cd $(PRESTASHOP_DIR) && sed -i 's/--db_name=prestashop/--db_name=prestashop --db_user=root --db_password=root/g' travis-scripts/install-prestashop.sh && bash travis-scripts/install-prestashop.sh
endif

test:
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) && php composer.phar run-script create-test-db --timeout=0
	cd $(PRESTASHOP_DIR) && php vendor/bin/phpunit -c $(ROOT_DIR)/phpunit.xml.dist
else
	phpunit -c phpunit.xml.dist
endif

coverage:
	wget https://phar.phpunit.de/phpcov-2.0.2.phar && php phpcov-2.0.2.phar merge coverage/ --clover coverage.xml