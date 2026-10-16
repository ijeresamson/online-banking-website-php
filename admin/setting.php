<?php include 'layout/top.php'; ?>

<?php
if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ============================================================
// CHANGE ADMIN PASSWORD
// ============================================================
if (isset($_POST['form_admin_password'])) {

    try {

        // CSRF validation
        if (
            empty($_POST['csrf_token']) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Security check failed. Please refresh the page and try again.");
        }

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Basic validation
        if (empty($current)) {
            throw new Exception("Current password is required.");
        }

        if (empty($new)) {
            throw new Exception("New password is required.");
        }

        if (strlen($new) < 8) {
            throw new Exception("New password must be at least 8 characters.");
        }

        if (!preg_match('/[A-Z]/', $new)) {
            throw new Exception("New password must contain at least one uppercase letter.");
        }

        if (!preg_match('/[a-z]/', $new)) {
            throw new Exception("New password must contain at least one lowercase letter.");
        }

        if (!preg_match('/[0-9]/', $new)) {
            throw new Exception("New password must contain at least one number.");
        }

        if (!preg_match('/[\W_]/', $new)) {
            throw new Exception("New password must contain at least one special character.");
        }

        if ($new !== $confirm) {
            throw new Exception("New passwords do not match.");
        }

        if ($current === $new) {
            throw new Exception("New password must be different from your current password.");
        }


        // ====================================================
        // GET CURRENT ADMIN
        // ====================================================

        if (
            empty($_SESSION['admin']) ||
            empty($_SESSION['admin']['id'])
        ) {
            throw new Exception("Admin session expired. Please log in again.");
        }

        $admin_id = (int)$_SESSION['admin']['id'];

        $stmt = $pdo->prepare(" SELECT id, password
            FROM admins
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$admin_id]);

        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            throw new Exception("Admin account could not be found.");
        }


        // ====================================================
        // VERIFY CURRENT PASSWORD
        // ====================================================

        if (!password_verify($current, $admin['password'])) {
            throw new Exception("Current password is incorrect.");
        }


        // ====================================================
        // UPDATE PASSWORD
        // ====================================================

        $new_hash = password_hash($new, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE admins
            SET password = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $new_hash,
            $admin_id
        ]);


        // ====================================================
        // REGENERATE SESSION ID
        // ====================================================

        session_regenerate_id(true);

        $_SESSION['success_message'] =
            "Admin password changed successfully.";

        header("Location: security");
        exit;

    } catch (Exception $e) {

        $pass_error = $e->getMessage();
    }
}



// ============================================================
// ADMIN INFORMATION
// ============================================================

$admin_id    = (int)($_SESSION['admin']['id'] ?? 0);
$admin_email = $_SESSION['admin']['email'] ?? '';

$admin_info = [];

try {

    $stmt = $pdo->prepare("SELECT id, email, role, created_at
        FROM admins
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$admin_id]);

    $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $admin_info = [];
}



// ============================================================
// FETCH LOGIN HISTORY
// ============================================================

$login_logs = [];

try {

    $stmt = $pdo->prepare("SELECT *
        FROM login_attempts
        WHERE email = ?
        ORDER BY attempted_at DESC
        LIMIT 10
    ");

    $stmt->execute([$admin_email]);

    $login_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $login_logs = [];
}



// ============================================================
// FAILED LOGINS TODAY
// ============================================================

$failed_today = 0;

try {

    $stmt = $pdo->query("SELECT COUNT(*)
        FROM login_attempts
        WHERE success = 0
        AND DATE(attempted_at) = CURDATE()
    ");

    $failed_today = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    $failed_today = 0;
}



// ============================================================
// SUCCESSFUL LOGINS TODAY
// ============================================================

$successful_today = 0;

try {

    $stmt = $pdo->query(" SELECT COUNT(*)
        FROM login_attempts
        WHERE success = 1
        AND DATE(attempted_at) = CURDATE()
    ");

    $successful_today = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    $successful_today = 0;
}



// ============================================================
// SUSPICIOUS IP ADDRESSES
// ============================================================

$blocked_ips_stmt = [];

try {

    $stmt = $pdo->query(" SELECT
            ip_address,
            COUNT(*) AS cnt,
            MAX(attempted_at) AS last_attempt
        FROM login_attempts
        WHERE success = 0
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY ip_address
        HAVING cnt >= 3
        ORDER BY cnt DESC
        LIMIT 10
    ");

    $blocked_ips_stmt = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $blocked_ips_stmt = [];
}



// ============================================================
// PASSWORD STRENGTH REQUIREMENTS
// ============================================================

$password_requirements = [
    'Minimum 8 characters',
    'At least one uppercase letter',
    'At least one lowercase letter',
    'At least one number',
    'At least one special character'
];

?>



<div class="main-content">

<section class="section">


    <!-- ======================================================
         HEADER
    ======================================================= -->

    <div class="section-header justify-content-between">

        <h1>
            <i class="fas fa-shield-alt mr-2"></i>
            Admin Security
        </h1>

        <div>

            <span class="badge badge-success px-3 py-2">
                <i class="fas fa-lock mr-1"></i>
                Security Center
            </span>

        </div>

    </div>



    <!-- ======================================================
         SUCCESS MESSAGE
    ======================================================= -->

    <?php if (!empty($_SESSION['success_message'])): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="fas fa-check-circle mr-2"></i>

            <?php
            echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']);
            ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert">
                &times;
            </button>

        </div>

    <?php endif; ?>



    <!-- ======================================================
         SECURITY STATS
    ======================================================= -->

    <div class="row mb-4">


        <!-- Failed Logins -->

        <div class="col-lg-3 col-md-6">

            <div class="card card-statistic-1">

                <div class="card-icon bg-warning">

                    <i class="fas fa-exclamation-triangle"></i>

                </div>

                <div class="card-wrap">

                    <div class="card-header">

                        <h4>Failed Logins Today</h4>

                    </div>

                    <div class="card-body">

                        <?php echo number_format($failed_today); ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- Suspicious IPs -->

        <div class="col-lg-3 col-md-6">

            <div class="card card-statistic-1">

                <div class="card-icon bg-danger">

                    <i class="fas fa-ban"></i>

                </div>

                <div class="card-wrap">

                    <div class="card-header">

                        <h4>Suspicious IPs</h4>

                    </div>

                    <div class="card-body">

                        <?php echo number_format(count($blocked_ips_stmt)); ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- Successful Logins -->

        <div class="col-lg-3 col-md-6">

            <div class="card card-statistic-1">

                <div class="card-icon bg-success">

                    <i class="fas fa-sign-in-alt"></i>

                </div>

                <div class="card-wrap">

                    <div class="card-header">

                        <h4>Successful Logins</h4>

                    </div>

                    <div class="card-body">

                        <?php echo number_format($successful_today); ?>

                    </div>

                </div>

            </div>

        </div>



        <!-- Session -->

        <div class="col-lg-3 col-md-6">

            <div class="card card-statistic-1">

                <div class="card-icon bg-info">

                    <i class="fas fa-clock"></i>

                </div>

                <div class="card-wrap">

                    <div class="card-header">

                        <h4>Session Timeout</h4>

                    </div>

                    <div class="card-body">

                        20 min

                    </div>

                </div>

            </div>

        </div>

    </div>



    <div class="row">


        <!-- ==================================================
             LEFT COLUMN
        =================================================== -->

        <div class="col-lg-5">


            <!-- ==============================================
                 ADMIN ACCOUNT
            =============================================== -->

            <div class="card">

                <div class="card-header">

                    <h4>
                        <i class="fas fa-user-shield mr-2"></i>
                        Admin Account
                    </h4>

                </div>

                <div class="card-body">


                    <div class="text-center mb-4">

                        <div
                            class="mx-auto bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                            style="
                                width:80px;
                                height:80px;
                                font-size:30px;
                                font-weight:700;
                            "
                        >

                            <?php

                            $email_for_avatar = $admin_email;

                            echo strtoupper(
                                substr($email_for_avatar, 0, 1)
                            );

                            ?>

                        </div>

                        <h5 class="mt-3 mb-1">

                            <?php
                            echo htmlspecialchars(
                                $admin_email
                            );
                            ?>

                        </h5>

                        <span class="badge badge-primary">

                            <?php
                            echo htmlspecialchars(
                                ucfirst(
                                    $admin_info['role']
                                    ?? 'Administrator'
                                )
                            );
                            ?>

                        </span>

                    </div>



                    <div class="border rounded p-3">

                        <div class="d-flex justify-content-between mb-2">

                            <span class="text-muted">
                                Email
                            </span>

                            <strong>
                                <?php
                                echo htmlspecialchars($admin_email);
                                ?>
                            </strong>

                        </div>


                        <div class="d-flex justify-content-between mb-2">

                            <span class="text-muted">
                                Role
                            </span>

                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    ucfirst(
                                        $admin_info['role']
                                        ?? 'admin'
                                    )
                                );
                                ?>
                            </strong>

                        </div>


                        <?php if (!empty($admin_info['created_at'])): ?>

                            <div class="d-flex justify-content-between">

                                <span class="text-muted">
                                    Account Created
                                </span>

                                <strong>

                                    <?php
                                    echo date(
                                        'd M Y',
                                        strtotime(
                                            $admin_info['created_at']
                                        )
                                    );
                                    ?>

                                </strong>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>



            <!-- ==============================================
                 PASSWORD REQUIREMENTS
            =============================================== -->

            <div class="card">

                <div class="card-header">

                    <h4>
                        <i class="fas fa-key mr-2"></i>
                        Password Requirements
                    </h4>

                </div>

                <div class="card-body">

                    <p class="text-muted">
                        Your administrator password should contain:
                    </p>

                    <ul class="list-unstyled mb-0">

                        <?php foreach (
                            $password_requirements
                            as $requirement
                        ): ?>

                            <li class="mb-2">

                                <i class="fas fa-check-circle text-success mr-2"></i>

                                <?php
                                echo htmlspecialchars(
                                    $requirement
                                );
                                ?>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            </div>


        </div>



        <!-- ==================================================
             RIGHT COLUMN
        =================================================== -->

        <div class="col-lg-7">


            <!-- ==============================================
                 CHANGE PASSWORD
            =============================================== -->

            <div class="card">

                <div class="card-header">

                    <h4>
                        <i class="fas fa-lock mr-2"></i>
                        Change Admin Password
                    </h4>

                </div>

                <div class="card-body">


                    <?php if (!empty($pass_error)): ?>

                        <div class="alert alert-danger">

                            <i class="fas fa-exclamation-circle mr-2"></i>

                            <?php
                            echo htmlspecialchars(
                                $pass_error
                            );
                            ?>

                        </div>

                    <?php endif; ?>



                    <form method="POST" autocomplete="off">


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php
                            echo htmlspecialchars(
                                $_SESSION['csrf_token']
                            );
                            ?>"
                        >


                        <input
                            type="hidden"
                            name="form_admin_password"
                            value="1"
                        >



                        <!-- Current -->

                        <div class="form-group">

                            <label>
                                Current Password
                            </label>

                            <div class="input-group">

                                <input
                                    type="password"
                                    name="current_password"
                                    id="current_password"
                                    class="form-control"
                                    required
                                >

                                <div class="input-group-append">

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        onclick="togglePassword('current_password', this)"
                                    >

                                        <i class="fas fa-eye"></i>

                                    </button>

                                </div>

                            </div>

                        </div>



                        <!-- New -->

                        <div class="form-group">

                            <label>
                                New Password
                            </label>

                            <div class="input-group">

                                <input
                                    type="password"
                                    name="new_password"
                                    id="new_password"
                                    class="form-control"
                                    required
                                    minlength="8"
                                >

                                <div class="input-group-append">

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        onclick="togglePassword('new_password', this)"
                                    >

                                        <i class="fas fa-eye"></i>

                                    </button>

                                </div>

                            </div>

                        </div>



                        <!-- Confirm -->

                        <div class="form-group">

                            <label>
                                Confirm New Password
                            </label>

                            <div class="input-group">

                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    class="form-control"
                                    required
                                >

                                <div class="input-group-append">

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        onclick="togglePassword('confirm_password', this)"
                                    >

                                        <i class="fas fa-eye"></i>

                                    </button>

                                </div>

                            </div>

                        </div>



                        <button
                            type="submit"
                            class="btn btn-primary btn-block"
                        >

                            <i class="fas fa-save mr-2"></i>

                            Change Password

                        </button>


                    </form>

                </div>

            </div>



            <!-- ==============================================
                 SUSPICIOUS IPs
            =============================================== -->

            <div class="card">

                <div class="card-header">

                    <h4>
                        <i class="fas fa-user-secret mr-2"></i>
                        Suspicious IP Addresses
                    </h4>

                </div>

                <div class="card-body">


                    <?php if (empty($blocked_ips_stmt)): ?>

                        <div class="text-center py-3">

                            <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>

                            <p class="text-muted mb-0">

                                No suspicious IP activity detected.

                            </p>

                        </div>

                    <?php else: ?>


                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead>

                                    <tr>

                                        <th>IP Address</th>

                                        <th>Failed Attempts</th>

                                        <th>Last Attempt</th>

                                        <th>Status</th>

                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach (
                                        $blocked_ips_stmt
                                        as $ip
                                    ): ?>

                                        <tr>

                                            <td>

                                                <code>

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $ip['ip_address']
                                                    );
                                                    ?>

                                                </code>

                                            </td>


                                            <td>

                                                <span class="badge badge-danger">

                                                    <?php
                                                    echo number_format(
                                                        $ip['cnt']
                                                    );
                                                    ?>

                                                </span>

                                            </td>


                                            <td>

                                                <small>

                                                    <?php
                                                    echo date(
                                                        'd M Y H:i:s',
                                                        strtotime(
                                                            $ip['last_attempt']
                                                        )
                                                    );
                                                    ?>

                                                </small>

                                            </td>


                                            <td>

                                                <span class="badge badge-warning">

                                                    Suspicious

                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


        </div>

    </div>



    <!-- ======================================================
         LOGIN HISTORY
    ======================================================= -->

    <div class="card mt-4">

        <div class="card-header">

            <h4>

                <i class="fas fa-history mr-2"></i>

                Recent Admin Login History

            </h4>

            <div class="card-header-action">

                <span class="text-muted">

                    Last 10 attempts

                </span>

            </div>

        </div>


        <div class="card-body">


            <?php if (empty($login_logs)): ?>

                <div class="text-center py-4">

                    <i class="fas fa-history fa-2x text-muted mb-2"></i>

                    <p class="text-muted">

                        No login history found.

                    </p>

                </div>

            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead>

                            <tr>

                                <th>Date & Time</th>

                                <th>IP Address</th>

                                <th>Result</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach (
                                $login_logs
                                as $log
                            ): ?>

                                <tr>

                                    <td>

                                        <small>

                                            <?php
                                            echo date(
                                                'd M Y H:i:s',
                                                strtotime(
                                                    $log['attempted_at']
                                                )
                                            );
                                            ?>

                                        </small>

                                    </td>


                                    <td>

                                        <code>

                                            <?php
                                            echo htmlspecialchars(
                                                $log['ip_address']
                                                ?? 'Unknown'
                                            );
                                            ?>

                                        </code>

                                    </td>


                                    <td>

                                        <?php if (
                                            !empty($log['success'])
                                        ): ?>

                                            <span class="badge badge-success">

                                                <i class="fas fa-check mr-1"></i>

                                                Successful

                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-danger">

                                                <i class="fas fa-times mr-1"></i>

                                                Failed

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>


</section>

</div>



<script>

function togglePassword(fieldId, button) {

    const field = document.getElementById(fieldId);

    const icon = button.querySelector('i');

    if (field.type === 'password') {

        field.type = 'text';

        icon.classList.remove('fa-eye');

        icon.classList.add('fa-eye-slash');

    } else {

        field.type = 'password';

        icon.classList.remove('fa-eye-slash');

        icon.classList.add('fa-eye');

    }

}

</script>


<?php include 'layout/footer.php'; ?>