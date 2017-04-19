#!/bin/bash
cd ~/Server/phoenix/_backups || exit BACKUP_DIR_NOT_FOUND
mysqldump \
	--allow-keywords \
	--replace \
	--routines \
	--skip-add-drop-table \
	--skip-lock-tables \
	--single-transaction \
	--triggers \
	--tz-utc \
	--verbose \
	-u'phoenix' \
	-p'Password1' \
	phoenix > "phoenix.$(date +%Y%m%d_%H%M).sql"
