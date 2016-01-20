#!/bin/bash
cd ~/Server/phoenix/_backups
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
	phoenix > phoenix.`date +\%Y\%m\%d_\%H\%M`.sql
