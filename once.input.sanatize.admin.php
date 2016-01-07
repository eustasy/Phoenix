<?php

////	Text

// $_POST['process']
$Sanatized['POST']['process'] = false;
if ( !empty($_POST['process']) ) $Sanatized['POST']['process'] = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');

// $_GET['message']
$Sanatized['GET']['message'] = false;
if ( !empty($_GET['message']) ) $Sanatized['GET']['message'] = htmlentities($_GET['message'], ENT_QUOTES, 'UTF-8');
