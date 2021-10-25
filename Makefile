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
	cd $(PRESTASHOP_DIR) \
        && php -r "copy('https://getcomposer.org/download/1.10.17/composer.phar', 'composer.phar');" \
        && php composer.phar install --prefer-dist --no-interaction --no-progress
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

lint:
	cd $(PRESTASHOP_DIR) && php vendor/bin/php-cs-fixer fix --dry-run --config-file=$(ROOT_DIR)/.php-cs-fixer.php -vvv

#lint-fix:
	#cd $(PRESTASHOP_DIR) && docker run --rm -it -w=/app -v ${PWD}:/app oskarstark/php-cs-fixer-ga:latest --config-file=$(ROOT_DIR)/.php-cs-fixer.php --using-cache=no


test:
ifeq ($(COMPOSERV1),1)
	cd $(PRESTASHOP_DIR) \
        && sed -i 's/throw new Exception/#throw new Exception/g' src/PrestaShopBundle/Install/DatabaseDump.php \
        && php composer.phar run-script create-test-db --timeout=0
	cd $(PRESTASHOP_DIR) && php vendor/bin/phpunit -c $(ROOT_DIR)/phpunit.xml.dist
else
	phpunit -c phpunit.xml.dist
endif

coverage:
	wget https://phar.phpunit.de/phpcov-2.0.2.phar && php phpcov-2.0.2.phar merge coverage/ --clover coverage.xml