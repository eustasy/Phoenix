<?php

////	Fatal Error
// Exits with a tracker-format error.
function tracker_error($error) {
	echo 'd14:failure reason'.strlen($error).':'.$error.'e';
	exit(2);
}
