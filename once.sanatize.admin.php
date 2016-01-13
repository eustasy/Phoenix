<?php

////	Text

// $_POST['process']
$Process = false;
if ( !empty($_POST['process']) ) {
	$Process = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
}
