<?php
$dbhost = 'localhost';
$dbname = 'online_banking';
$dbuser = 'root';
$dbpass = '';
try {
    $pdo = new PDO("mysql:host={$dbhost};dbname={$dbname}", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch( PDOException $exception ) {
    echo "Connection error :" . $exception->getMessage();
}
define("BASE_URL", "http://localhost/My_project/online-bank/");
define("ADMIN_URL", BASE_URL. "admin/");

define("SMTP_HOST", "sandbox.smtp.mailtrap.io");
define("SMTP_PORT", "587");
define("SMTP_USERNAME", "6024dfc404183e");
define("SMTP_PASSWORD", "eb1aedf9749db5");
define("SMTP_ENCRYPTION", "tls");
define("SMTP_FROM", "support@mdwestern.com");



