<?php include 'layout/top.php'; ?>
<?php

session_start();

/*
|--------------------------------------------------------------------------
| Prevent caching
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


/*
|--------------------------------------------------------------------------
| Remove admin session data
|--------------------------------------------------------------------------
*/

unset($_SESSION['admin']);


/*
|--------------------------------------------------------------------------
| Remove other admin-related session values
|--------------------------------------------------------------------------
*/

unset($_SESSION['last_activity_admin']);
unset($_SESSION['csrf_token']);
unset($_SESSION['reset_csrf_token']);


/*
|--------------------------------------------------------------------------
| Destroy entire session
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| Remove session cookie
|--------------------------------------------------------------------------
*/

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}


/*
|--------------------------------------------------------------------------
| Destroy server-side session
|--------------------------------------------------------------------------
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| Start a fresh session for logout message
|--------------------------------------------------------------------------
*/

session_start();

$_SESSION['success_message'] =
    'You have been logged out successfully.';


/*
|--------------------------------------------------------------------------
| Redirect to admin login
|--------------------------------------------------------------------------
*/

header('Location: ' . ADMIN_URL . 'login');
exit;