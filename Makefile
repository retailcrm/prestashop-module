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
