<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

// ============================================================
// REDIRECT IF ALREADY LOGGED IN
// ============================================================
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard"); 
    exit;
}



// ============================================================
// CSRF TOKEN
// ============================================================
if(empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';

// ============================================================
// HANDLE LOGIN
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_login'])) {
    try {
        if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new \Exception("Security check failed. Please refresh the page.");
        }

        $email    = strtolower(trim($_POST['email']    ?? ''));
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember_me']);

        if(empty($email))    throw new \Exception("Email address is required.");
        if(empty($password)) throw new \Exception("Password is required.");
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \Exception("Please enter a valid email address.");

        // Brute-force check
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        try {
            $att_stmt = $pdo->prepare(" SELECT COUNT(*) FROM login_attempts WHERE email=? AND ip_address=? AND success=0
                  AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $att_stmt->execute([$email, $ip]);
            if((int)$att_stmt->fetchColumn() >= 5) {
                throw new \Exception("Too many failed login attempts. Please wait 15 minutes before trying again.");
            }
        } catch(\PDOException $e) { /* table may not exist yet */ }

        // Fetch user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $log_success = 0;

        if($user && password_verify($password, $user['password'])) {
            // Check account status
            if($user['status'] === 'suspended') {
                throw new \Exception("Your account has been suspended. Please contact support for assistance.");
            }
            if($user['status'] === 'closed') {
                throw new \Exception("This account has been closed. Please contact support.");
            }

            // Log success
            $log_success = 1;
            try {
                $pdo->prepare("INSERT INTO login_attempts (email,ip_address,success,attempted_at) VALUES (?,?,1,NOW())")->execute([$email,$ip]);
                $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            } catch(\PDOException $e) {}

            // Set session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user']      = $user;
            $_SESSION['logged_in'] = true;

            // Remember me cookie (30 days)
            if($remember) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("UPDATE users SET remember_token=? WHERE id=?")->execute([$token, $user['id']]);
                setcookie('remember_me', $token, time()+60*60*24*30, '/', '', false, true);
            }

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Pending KYC notice
            if($user['status'] === 'pending_kyc') {
                $_SESSION['info_message'] = "Your account is pending KYC verification. Some features are limited until approved.";
            }

            header("Location: dashboard");
             exit;

        } else {
            // Log failure
            try {
                $pdo->prepare("INSERT INTO login_attempts (email,ip_address,success,attempted_at) VALUES (?,?,0,NOW())")->execute([$email,$ip]);
            } catch(\PDOException $e) {}
            throw new \Exception("Incorrect email or password. Please try again.");
        }

    } catch(\PDOException $e) {
        error_log('Login DB error: '.$e->getMessage());
        $error_message = "A system error occurred. Please try again later.";
    } catch(\Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Remember me auto-login
if(empty($_SESSION['user_id']) && !empty($_COOKIE['remember_me'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token=? AND status='active' LIMIT 1");
        $stmt->execute([$_COOKIE['remember_me']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user']      = $user;
            $_SESSION['logged_in'] = true;
            $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
            header("Location: dashboard"); 
            exit;
        }
    } catch(\PDOException $e) {}
}

// Flash message from register redirect
$success_message = '';
if(!empty($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — MidwesternBank Online Banking</title>
    <meta name="description" content="Sign in to your MidwesternBank online banking account. Secure access to your checking account, transfers, and more.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="style/login.css" rel="stylesheet">
</head>
<body>

<!-- ============================================================ TOP BAR ============================================================ -->
<header class="auth-topbar">
    <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/'; ?>" class="auth-logo">
        <?php echo mwLogo(36); ?>
        <div class="auth-logo-text">Midwestern<span>Bank</span></div>
    </a>
    <div class="auth-topbar-links">
        <a href="<?php echo defined('BASE_URL') ? BASE_URL.'about' : '/about'; ?>">About</a>
        <a href="<?php echo defined('BASE_URL') ? BASE_URL.'home-security' : '/home-security'; ?>">Security</a>
        <a href="register" class="btn-gold">Open Account →</a>
    </div>
</header>

<!-- ============================================================ MAIN ============================================================ -->
<main class="auth-main">

    <!-- ====== LEFT PANEL ====== -->
    <div class="auth-left">
        <div class="auth-left-content">

            <div class="auth-left-eyebrow">
                🏦 FDIC Insured · 35+ Years of Banking
            </div>

            <h2>Welcome Back to <span>MidwesternBank</span></h2>

            <p class="auth-left-desc">
                Sign in to access your Checking Account, send wire transfers, manage your Visa card, and track every transaction — securely, from anywhere.
            </p>

            <div class="auth-features">
                <div class="auth-feature">
                    <div class="auth-feature-icon"><i class="ri-shield-check-line"></i></div>
                    <div>
                        <div class="auth-feature-title">Bank-Grade Security</div>
                        <div class="auth-feature-desc">256-bit AES encryption · Multi-factor authentication · Real-time fraud monitoring</div>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon"><i class="ri-exchange-funds-line"></i></div>
                    <div>
                        <div class="auth-feature-title">Instant Transfers</div>
                        <div class="auth-feature-desc">Send money domestically via ACH or internationally via SWIFT wire transfer</div>
                    </div>
                </div>
                <div class="auth-feature">
                    <div class="auth-feature-icon"><i class="ri-bank-card-line"></i></div>
                    <div>
                        <div class="auth-feature-title">Visa Debit Cards</div>
                        <div class="auth-feature-desc">Freeze, unfreeze, and set spending limits directly from your account dashboard</div>
                    </div>
                </div>
            </div>

            <div class="auth-left-trust">
                <div class="auth-trust-badge">🏛 FDIC Insured</div>
                <div class="auth-trust-badge">🔒 256-bit SSL</div>
                <div class="auth-trust-badge">⚖️ AML Compliant</div>
            </div>
        </div>
    </div>

    <!-- ====== RIGHT PANEL ====== -->
    <div class="auth-right">
        <div class="auth-form-wrap">

            <!-- Form header -->
            <div class="auth-form-header">
                <!-- Mobile only logo -->
                <div style="display:none;align-items:center;gap:10px;margin-bottom:24px;" id="mobileLogo">
                    <?php echo mwLogo(40); ?>
                    <div style="font-size:22px;font-weight:800;color:var(--navy);">Midwestern<span style="color:var(--gold);">Bank</span></div>
                </div>

                <div class="auth-form-title">Sign In to Your Account</div>
                <div class="auth-form-subtitle">
                    Don't have an account?
                    <a href="register">Open a free account →</a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if(!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>

            <?php if(!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="ri-checkbox-circle-line"></i>
                <span><?php echo $success_message; ?></span>
            </div>
            <?php endif; ?>

            <?php if(!empty($_SESSION['info_message'])): ?>
            <div class="alert alert-info">
                <i class="ri-information-line"></i>
                <span><?php echo htmlspecialchars($_SESSION['info_message']); ?></span>
            </div>
            <?php unset($_SESSION['info_message']); ?>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form method="POST" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="form_login"  value="1">

                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span>*</span></label>
                    <div class="input-wrap">
                        <i class="ri-mail-line input-icon"></i>
                        <input type="email" id="email" name="email" class="form-input"
                               placeholder="you@example.com"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               autocomplete="email" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password <span>*</span></label>
                    <div class="input-wrap">
                        <i class="ri-lock-line input-icon"></i>
                        <input type="password" id="password" name="password" class="form-input"
                               placeholder="Enter your password"
                               autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('password','pwIcon')" tabindex="-1">
                            <i class="ri-eye-off-line" id="pwIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember me / forget -->
                <div class="form-check-row">
                    <label class="form-check-label">
                        <input type="checkbox" name="remember_me" value="1">
                        Remember me for 30 days
                    </label>
                    <a href="forget-password" class="forget-link">forget password?</a>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="ri-shield-check-line"></i>
                    Sign In Securely
                </button>
            </form>

            <!-- Security Note -->
            <div class="security-note">
                <i class="ri-shield-star-line" style="font-size:16px;flex-shrink:0;"></i>
                <span>MidwesternBank will <strong>never</strong> ask for your password, PIN, or OTP by email or phone.</span>
            </div>

            <!-- Divider -->
            <div class="divider">
                <span class="divider-text">New to MidwesternBank?</span>
            </div>

            <!-- Register CTA -->
            <div class="register-cta">
                <p>Open a free Checking Account in minutes. FDIC insured. No monthly fees.</p>
                <a href="register" class="btn-register">
                    <i class="ri-arrow-right-line"></i>
                    Open Your Free Account
                </a>
            </div>

        </div>
    </div>

</main>

<!-- ============================================================ FOOTER ============================================================ -->
<footer class="auth-footer">
    <div class="auth-footer-text">
        &copy; <?php echo date('Y'); ?> MidwesternBank, N.A. All rights reserved. Member FDIC. Equal Housing Lender.
    </div>
    <div class="auth-footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Accessibility</a>
        <a href="<?php echo defined('BASE_URL') ? BASE_URL.'home-security' : '/home-security'; ?>">Security Center</a>
    </div>
</footer>

<script>
// Show mobile logo on small screens
(function(){
    const ml = document.getElementById('mobileLogo');
    if(window.innerWidth <= 900 && ml) ml.style.display = 'flex';
    window.addEventListener('resize', function(){
        if(ml) ml.style.display = window.innerWidth <= 900 ? 'flex' : 'none';
    });
})();

// Toggle password visibility
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if(input.type === 'password') {
        input.type = 'text';
        icon.className = 'ri-eye-line';
    } else {
        input.type = 'password';
        icon.className = 'ri-eye-off-line';
    }
}


// Scroll to alert
document.addEventListener('DOMContentLoaded', function(){
    const alert = document.querySelector('.alert');
    if(alert) setTimeout(() => alert.scrollIntoView({behavior:'smooth',block:'center'}), 100);
});
</script>

<style>
@keyframes spin { from{transform:rotate(0deg);} to{transform:rotate(360deg);} }
</style>

</body>
</html>