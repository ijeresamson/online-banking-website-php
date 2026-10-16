<?php include 'layout/top.php'; ?>
<?php   

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


if (isset($_POST['form_login'])) {

    try {

        // Make sure the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Invalid request.");
        }

        // Get and clean input
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate email
        if ($email === '') {
            throw new Exception("Email and password are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email and password are required.");
        }

        if ($password === '') {
            throw new Exception("Email and password are required.");
        }

        // Check CSRF token
        if (
            !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please refresh the page and try again.");
        }

        // Find active admin
        $q = $pdo->prepare("SELECT id, name, email, photo, password, role, status
                            FROM admins WHERE email = ? AND role = ? LIMIT 1");

        $q->execute([$email, 'admin']);

        $admin = $q->fetch(PDO::FETCH_ASSOC);

        // Use one generic error message
        if (!$admin || !password_verify($password, $admin['password'])) {
            throw new Exception("Invalid email or password.");
        }

        // Check account status
        if (isset($admin['status']) && $admin['status'] !== 'active') {
            throw new Exception("Your account is currently inactive.");
        }

        // Prevent session fixation
        session_regenerate_id(true);

        // Remove password before storing admin data in session
        unset($admin['password']);

        // Store admin information
        $_SESSION['admin'] = $admin;
        $_SESSION['admin']['photo'];

        // Update login timestamp if your table has this column
        /*
        $update = $pdo->prepare("
            UPDATE admins
            SET last_login = NOW()
            WHERE id = ?
        ");
        $update->execute([$admin['id']]);
        */

        // Remove used CSRF token
        unset($_SESSION['csrf_token']);

        $_SESSION['success_message'] = "Login successful!";

        header('Location: ' . ADMIN_URL);
        exit;

    } catch (Exception $e) {

        $_SESSION['error_message'] = $e->getMessage();

        header('Location: ' . ADMIN_URL . 'login');
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
                                <h4 class="text-center">Admin Panel Login</h4>
                            </div>
                            <div class="card-body card-body-auth">

                                <form method="POST" action="">

                                  <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" >
                                         <div class="form-group">
                                        <input type="email" class="form-control" name="email" placeholder="Email Address" value="" autofocus>
                                    </div>
                                    <div class="form-group">
                                        <input type="password" class="form-control" name="password"  placeholder="Password">
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-lg w_100_p" name="form_login">
                                            Login
                                        </button>
                                    </div>
                                    <div class="form-group">
                                        <div>
                                            <a href="<?php echo ADMIN_URL; ?>forget-password">
                                        Forget Password?
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

<?php include "layout/footer.php"; ?>