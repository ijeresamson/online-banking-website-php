<?php
session_start();
require_once 'config/db-config.php';

// Clear remember me token from DB
if(!empty($_SESSION['user_id'])) {
    try {
        $pdo->prepare("UPDATE users SET remember_token=NULL WHERE id=?")->execute([$_SESSION['user_id']]);
    } catch(PDOException $e) {}
}

// Clear remember me cookie
if(isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time()-3600, '/', '', false, true);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login with message
header("Location:".BASE_URL. "login?msg=logged_out");
exit;