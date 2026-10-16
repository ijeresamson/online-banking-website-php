<?php
include '../config/db-config.php';
include '../config/functions.php';
include 'layout/header.php';
include "../toast.php"; 

$cur_page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);

if($cur_page != 'login.php' && $cur_page != 'forget-password.php' && $cur_page != 'reset-password.php'):
include 'layout/navbar.php';
include 'layout/sidebar.php';
endif;

