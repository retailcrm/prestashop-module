ROOT_DIR=$(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
PRESTASHOP_DIR=$(ROOT_DIR)/../PrestaShop
FILE = $(TRAVIS_BUILD_DIR)/VERSION
VERSION = `cat $(FILE)`
ARCHIVE_NAME = '/tmp/retailcrm-'$(VERSION)'.zip'

all: build_archive send_to_ftp delete_archive

build_archive:
	zip -r $(ARCHIVE_NAME) ./retailcrm/*
	zip -r /tmp/retailcrm.zip ./retailcrm/*

send_to_ftp:
	curl -T $(ARCHIVE_NAME) -u $(FTP_USER):$(FTP_PASSWORD) ftp://$(FTP_HOST)
	curl -T /tmp/retailcrm.zip -u $(FTP_USER):$(FTP_PASSWORD) ftp://$(FTP_HOST)

delete_archive:
	rm -f $(ARCHIVE_NAME)
	rm -f /tmp/retailcrm.zip

composer: clone_prestashop
ifeq ($(COMPOSER_IN_TESTS),1)
	@cd $(ROOT_DIR)/../PrestaShop/tests && composer install
else
	@cd $(ROOT_DIR)/../PrestaShop && composer install --prefer-dist --no-interaction --no-progress
endif

clone_prestashop:
	@cd $(ROOT_DIR)/../ && git clone https://github.com/PrestaShop/PrestaShop

setup_apache: composer
	@bash $(PRESTASHOP_DIR)/travis-scripts/setup-php-fpm.sh
	@echo "* Preparing Apache ..."
	@sudo a2enmod rewrite actions fastcgi alias
    # Use default config
	@sudo cp -f $(PRESTASHOP_DIR)/tests/travis-ci-apache-vhost /etc/apache2/sites-available/000-default.conf
	@sudo sed -e "s?%PRESTASHOP_DIR%?$(pwd)?g" --in-place /etc/apache2/sites-available/000-default.conf
	@sudo chmod 777 -R $(HOME)
    # Starting Apache
	@sudo service apache2 restart

before_script: setup_apache
ifeq ($(COMPOSER_IN_TESTS),1)
    ifneq ("$(wildcard $(PRESTASHOP_DIR)/tests/parameters.yml.travis)","")
		@cd $(PRESTASHOP_DIR) && cp tests/parameters.yml.travis app/config/parameters.yml
    else
		@cd $(PRESTASHOP_DIR) && cp tests-legacy/parameters.yml.travis app/config/parameters.yml
    endif
	@bash $(PRESTASHOP_DIR)/travis-scripts/install-prestashop
else
	@bash $(PRESTASHOP_DIR)/travis-scripts/install-prestashop.sh
endif

test:
ifeq ($(COMPOSER_IN_TESTS),1)
	@phpunit
else
	@cd $(PRESTASHOP_DIR) && composer run-script create-test-db --timeout=0
	@php $(PRESTASHOP_DIR)/vendor/bin/phpunit -c $(ROOT_DIR)/phpunit.xml.dist
endif
