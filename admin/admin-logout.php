<?php include 'layout/top.php'; ?>
<?php
session_start();
session_unset();
session_destroy();
session_start();
$_SESSION['success_message'] = "You have been logged out.";
header('Location: ' .  ADMIN_URL  . 'login');
exit;