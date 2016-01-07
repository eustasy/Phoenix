<?php

////	Text
$Sanatized['POST']['process'] = htmlentities($_POST['process'], ENT_QUOTES, 'UTF-8');
$Sanatized['GET']['message']  = htmlentities($_GET['message'],  ENT_QUOTES, 'UTF-8');
