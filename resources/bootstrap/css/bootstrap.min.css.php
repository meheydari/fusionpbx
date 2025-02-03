<?php

ob_start('ob_gzhandler');
header('Content-type: text/css; charset: UTF-8');
header('Cache-Control: must-revalidate');
header('Expires: '.gmdate('D, d M Y H:i:s',time()+3600).' GMT');

echo $_SESSION['app']['languages'];
echo $_SESSION['domain']['language']['code'];
var_dump($_SESSION['domain']);
include_once 'bootstrap.min.css';

?>