<?php

////	Fatal Error
// Exits with a tracker-format error.
function tracker_error(string $error): never {
	echo 'd14:failure reason'.strlen($error).':'.$error.'e';
	exit(2);
}
