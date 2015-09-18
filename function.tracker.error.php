<?php

////	Fatal Error
// Exits with a tracker-format error.
function tracker_error($error) {
	exit('d14:Failure Reason'.strlen($error).':'.$error.'e');
}