<?php include 'layout/top.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}
?>

<?php

if (isset($_POST['form_forget_password'])) {

    try {

        $email = trim($_POST['email'] ?? '');

        // Validate email
        if ($email === '') {
            throw new Exception("Please enter your email address.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        /*
        |--------------------------------------------------------------------------
        | Find admin
        |--------------------------------------------------------------------------
        */

        $q = $pdo->prepare("SELECT id, email FROM admins WHERE email = ? AND role = ?
             LIMIT 1  ");

        $q->execute([
            $email,
            'admin'
        ]);

        $admin = $q->fetch(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | Always show the same message
        |--------------------------------------------------------------------------
        |
        | This prevents someone from discovering which emails belong
        | to administrators.
        |
        */

        if (!$admin) {

            $_SESSION['success_message'] =
                "If that email address is registered, you will receive a password reset link.";

            header('Location: ' . ADMIN_URL . 'forget-password');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Generate secure token
        |--------------------------------------------------------------------------
        */

        $token = bin2hex(random_bytes(32));

        /*
        |--------------------------------------------------------------------------
        | Hash token before storing it
        |--------------------------------------------------------------------------
        */

        $tokenHash = hash('sha256', $token);

        /*
        |--------------------------------------------------------------------------
        | Token expires after 30 minutes
        |--------------------------------------------------------------------------
        */

        $expiresAt = date('Y-m-d H:i:s',
            time() + (30 * 60) );

        /*
        |--------------------------------------------------------------------------
        | Save hashed token
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare("UPDATE admins  SET reset_token_hash = ?,  reset_token_expires = ?
                                      WHERE id = ? ");

        $statement->execute([
            $tokenHash,
            $expiresAt,
            $admin['id']
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create reset URL
        |--------------------------------------------------------------------------
        */

        $resetUrl =
            ADMIN_URL .
            'reset-password.php?token=' .
            urlencode($token);

        /*
        |--------------------------------------------------------------------------
        | Email
        |--------------------------------------------------------------------------
        */

        $email_message = "
            <h2>Password Reset Request</h2>

            <p>Hello,</p>

            <p>
                We received a request to reset the password
                for your administrator account.
            </p>

            <p>
                Click the button below to create a new password:
            </p>

            <p>
                <a href=\"{$resetUrl}\"
                   style=\"
                        display:inline-block;
                        padding:12px 20px;
                        background:#2563eb;
                        color:#ffffff;
                        text-decoration:none;
                        border-radius:6px;
                   \">
                    Reset Password
                </a>
            </p>

            <p>
                This link will expire in <strong>30 minutes</strong>.
            </p>

            <p>
                If you did not request a password reset,
                you can safely ignore this email.
            </p>

            <p>
                Regards,<br>
                Administration Team
            </p>
        ";

        /*
        |--------------------------------------------------------------------------
        | Send email
        |--------------------------------------------------------------------------
        */

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(
            SMTP_FROM,
            'Administration'
        );

        $mail->addAddress($admin['email']);

        $mail->isHTML(true);
        $mail->Subject = 'Admin Password Reset';
        $mail->Body = $email_message;

        $mail->send();

        $_SESSION['success_message'] =
            "If that email address is registered, you will receive a password reset link.";

        header('Location: ' . ADMIN_URL . 'forget-password' );

        exit;

    } catch (Exception $e) {

        $_SESSION['error_message'] =
            $e->getMessage();

        header( 'Location: ' .   ADMIN_URL . 'forget-password' );
        exit;
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
                                    <div class="form-group">
                                        <input type="email" class="form-control" name="email" placeholder="Email Address" value="" autofocus>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-lg w_100_p" name="form_forget_password">
                                    Submit
                                </button>
                                    </div>
                                    <div class="form-group">
                                        <div>
                                            <a href="<?php echo ADMIN_URL; ?>login">
                                        Back to login page
                                    </a>
                                        </div>
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

<script src="dist/js/scripts.js"></script>
<script src="dist/js/custom.js"></script>

</body>
</html>