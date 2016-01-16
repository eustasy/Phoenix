<?php

////	Fatal Error
// Exits with a tracker-format error.
function tracker_error($error) {
	echo 'd14:Failure Reason'.strlen($error).':'.$error.'e';
	exit(2);
}
