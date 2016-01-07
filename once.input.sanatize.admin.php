<?php

////	Text

// $_POST['process']
$Sanatized['POST']['process'] = false;
if ( !empty($_POST['process']) ) $Sanatized['POST']['process'] = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
