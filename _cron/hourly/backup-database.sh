#!/bin/bash
cd ~/Server/phoenix/_backups || { echo "BACKUP_DIR_NOT_FOUND" >&2; exit 1; }
# Peers are ephemeral (expire after 3x announce_interval) and can be recreated
# by running Setup in admin.php, so there is no value in backing up their rows.
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
	--ignore-table=phoenix.phoenix_peers \
	-u'phoenix' \
	-p'Password1' \
	phoenix > "phoenix.$(date +%Y%m%d_%H%M).sql"
