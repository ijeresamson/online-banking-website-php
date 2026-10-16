<?php include 'layout/top.php'; ?>
<?php
if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
}

/*
|--------------------------------------------------------------------------
| Check reset token
|--------------------------------------------------------------------------
*/

$token = trim($_GET['token'] ?? '');

if ($token === '') {

    $_SESSION['error_message'] = 'Invalid password reset link.';

    header('Location: ' . ADMIN_URL . 'login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Hash token
|--------------------------------------------------------------------------
*/

$tokenHash = hash('sha256', $token);


/*
|--------------------------------------------------------------------------
| Find valid admin using the token
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare("SELECT id, email FROM admins
                            WHERE reset_token_hash = ?
                            AND reset_token_expires > NOW()
                            AND role = ?
                            LIMIT 1 ");

$statement->execute([
    $tokenHash,
    'admin'
]);

$admin = $statement->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Invalid or expired token
|--------------------------------------------------------------------------
*/

if (!$admin) {

    $_SESSION['error_message'] =
        'This password reset link is invalid or has expired.';

    header('Location: ' . ADMIN_URL . 'login');
    exit;
}


/*
|--------------------------------------------------------------------------
| Generate CSRF token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['reset_csrf_token'])) {

    $_SESSION['reset_csrf_token'] =
        bin2hex(random_bytes(32));
}


/*
|--------------------------------------------------------------------------
| Reset password
|--------------------------------------------------------------------------
*/

if (isset($_POST['form_reset_password'])) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Verify CSRF
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $_POST['csrf_token'],
                $_SESSION['reset_csrf_token']
            )
            ||
            !hash_equals(
                $_SESSION['reset_csrf_token'],
                $_POST['csrf_token']
            )
        ) {

            throw new Exception(
                'Invalid request. Please refresh the page and try again.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Get passwords
        |--------------------------------------------------------------------------
        */

        $password = $_POST['password'] ?? '';
        $retypePassword = $_POST['retype_password'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Check empty
        |--------------------------------------------------------------------------
        */

        if (
            trim($password) === '' ||
            trim($retypePassword) === ''
        ) {

            throw new Exception(
                'Password fields cannot be empty.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Password length
        |--------------------------------------------------------------------------
        */

        if (strlen($password) < 8) {

            throw new Exception(
                'Password must be at least 8 characters long.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Password confirmation
        |--------------------------------------------------------------------------
        */

        if ($password !== $retypePassword) {

            throw new Exception(
                'Passwords do not match.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Password strength
        |--------------------------------------------------------------------------
        */

        if (
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {

            throw new Exception(
                'Password must contain at least 8 characters, including an uppercase letter, lowercase letter, number, and special character.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Hash password
        |--------------------------------------------------------------------------
        */

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {

            throw new Exception(
                'Unable to secure the password.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update password
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(" UPDATE admins SET
                password = ?,
                status = 'active',
                reset_token_hash = NULL,
                reset_token_expires = NULL
            WHERE id = ?
            AND reset_token_hash = ?
            AND reset_token_expires > NOW()
        ");

        $statement->execute([
            $passwordHash,
            $admin['id'],
            $tokenHash
        ]);


        /*
        |--------------------------------------------------------------------------
        | Check update
        |--------------------------------------------------------------------------
        */

        if ($statement->rowCount() !== 1) {

            throw new Exception(
                'The password reset link is invalid or has expired.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Remove CSRF token
        |--------------------------------------------------------------------------
        */

        unset($_SESSION['reset_csrf_token']);


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        $_SESSION['success_message'] =
            'Your password has been reset successfully. Please login with your new password.';

        header(
            'Location: ' . ADMIN_URL . 'login'
        );

        exit;

    } catch (Exception $e) {

        $error_message = $e->getMessage();
    }
}
?>

<body>
<div id="app">
    <div class="main-wrapper">
        <section class="section">
            <div class="container container-login">
                <div class="row">
                    <div class="col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 col-lg-6 offset-lg-3 col-xl-4 offset-xl-4">
                        <div class="card card-primary border-box">
                            <div class="card-header card-header-auth">
                                <h4 class="text-center">Reset Password</h4>
                            </div>
                            <div class="card-body card-body-auth">

                            <form method="POST" action="">

                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['reset_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="form-group">
                            <input
                                type="password"
                                class="form-control"
                                name="password"
                                placeholder="New Password"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <input
                                type="password"
                                class="form-control"
                                name="retype_password"
                                placeholder="Retype Password"
                                autocomplete="new-password"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <button
                                type="submit"
                                class="btn btn-primary btn-lg w_100_p"
                                name="form_reset_password">
                                Reset Password
                            </button>
                        </div>

                    </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include "layout/footer.php" ?>