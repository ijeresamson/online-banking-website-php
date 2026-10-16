<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
require_once 'vendor/autoload.php';

if(isset($_SESSION['user_id'])) { header("Location: dashboard"); 
exit; }

if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ============================================================
// SEND RESET OTP EMAIL
// ============================================================
function sendResetOtp(string $email, string $name, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = (int)SMTP_PORT;
        $mail->SMTPOptions= ['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
        $mail->Timeout    = 10;
        $mail->setFrom(SMTP_FROM, 'MidwesternBank, N.A.');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "MidwesternBank Password Reset Code — {$otp}";
        $mail->Body = '
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f7fc;font-family:Inter,-apple-system,sans-serif;">
<div style="max-width:520px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(12,35,64,.12);">
  <div style="background:linear-gradient(135deg,#0c2340,#1a4b8c);padding:30px 36px;text-align:center;">
    <div style="font-size:22px;font-weight:800;color:#fff;">Midwestern<span style="color:#c59b3d;">Bank</span></div>
    <div style="font-size:13px;color:rgba(255,255,255,.55);margin-top:4px;">Account Security</div>
  </div>
  <div style="padding:32px 36px;">
    <div style="font-size:18px;font-weight:700;color:#0c2340;margin-bottom:10px;">Password Reset Request</div>
    <p style="font-size:14px;color:#64748b;line-height:1.7;margin-bottom:24px;">
      Hi <strong>'.htmlspecialchars($name).'</strong>,<br><br>
      We received a request to reset the password for your MidwesternBank account.
      Use the code below to continue. If you did not request this, please ignore this email — your account remains secure.
    </p>
    <div style="background:#f4f7fc;border:2px dashed #c59b3d;border-radius:14px;padding:28px;text-align:center;margin-bottom:24px;">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#64748b;margin-bottom:10px;">Reset Code</div>
      <div style="font-size:44px;font-weight:800;color:#0c2340;letter-spacing:12px;font-family:monospace;">'.$otp.'</div>
      <div style="font-size:12px;color:#dc2626;font-weight:600;margin-top:10px;">⏱ Expires in 15 minutes</div>
    </div>
    <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;font-size:13px;color:#dc2626;margin-bottom:20px;">
      <strong>Security Alert:</strong> MidwesternBank will <strong>NEVER</strong> call or email to ask for this code. Do not share it with anyone.
    </div>
    <p style="font-size:12px;color:#94a3b8;text-align:center;">
    If you did not request this, your password is still safe — no action needed.</p>
  </div>
  <div style="background:#070f1c;padding:18px 36px;text-align:center;">
    <div style="font-size:11px;color:rgba(255,255,255,.3);line-height:1.8;">
      MidwesternBank, N.A. · '.BANK_ADDRESS.'<br>Member FDIC &copy; '.date('Y').' All rights reserved
    </div>
  </div>
</div>
</body></html>';
        $mail->AltBody = "Hi {$name},\n\nYour MidwesternBank password reset code is: {$otp}\n\nExpires in 15 minutes.\nDo NOT share this with anyone.\n\nMidwesternBank, N.A.";
        $mail->send(); return true;
    } catch(\Exception $e) {
        error_log('[forget-pw] Email failed: '.$e->getMessage()); return false;
    }
}

// ============================================================
// STATE
// ============================================================
$view   = 'request'; // request | verify | new_password
$errors = [];
$msg    = '';
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Restore view from session
if(!empty($_SESSION['pw_reset'])) {
    $pr = $_SESSION['pw_reset'];
    if(!empty($pr['otp_hash']) && empty($pr['verified']))       $view = 'verify';
    elseif(!empty($pr['verified']) && $pr['verified'] === true) $view = 'new_password';
}

// ============================================================
// HANDLE — REQUEST EMAIL
// ============================================================

if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'request_reset') {
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']??''))
            throw new \Exception("Security check failed.");

        $email = strtolower(trim($_POST['email']??''));
        if(empty($email)||!filter_var($email,FILTER_VALIDATE_EMAIL))
            throw new \Exception("Please enter a valid email address.");

        // Always show same success message (don't reveal if email exists)
        $s = $pdo->prepare("SELECT id, first_name, last_name, status FROM users WHERE email=? LIMIT 1");
        $s->execute([$email]);
        $user = $s->fetch(PDO::FETCH_ASSOC);

        if($user && $user['status']!=='suspended') {
            // Cooldown: 60s between attempts
            if(!empty($_SESSION['pw_reset']['sent_at']) && (time()-$_SESSION['pw_reset']['sent_at']) < 60)
                throw new \Exception("Please wait ".( 60-(time()-$_SESSION['pw_reset']['sent_at']) )." second(s) before requesting again.");

            $otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            $sent = sendResetOtp($email, trim($user['first_name'].' '.$user['last_name']), $otp);
            if(!$sent) throw new \Exception("Could not send email to <strong>{$email}</strong>. Please check the address and try again.");

            $_SESSION['pw_reset'] = [
                'user_id'    => (int)$user['id'],
                'email'      => $email,
                'name'       => $user['first_name'],
                'otp_hash'   => password_hash($otp, PASSWORD_DEFAULT),
                'otp_exp'    => time() + 900, // 15 min
                'attempts'   => 0,
                'resends'    => 0,
                'sent_at'    => time(),
                'resend_after'=> time() + 60,
            ];
        }
        // Same message whether user found or not (security)
        $view = 'verify';
        $msg  = "If an account with <strong>{$email}</strong> exists, a reset code has been sent. Check your inbox (and spam folder).";

    } catch(\Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// ============================================================
// HANDLE — RESEND OTP
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'resend_otp') {
    try {
        $pr = $_SESSION['pw_reset'] ?? null;
        if(!$pr) throw new \Exception("Session expired. Please start over.");

        if(time() < ($pr['resend_after']??0)) {
            $wait = ($pr['resend_after']??0) - time();
            throw new \Exception("Please wait {$wait} second(s) before resending.");
        }
        if(($pr['resends']??0) >= 3) throw new \Exception("Maximum resend limit reached. Please start over.");

        $s = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id=? LIMIT 1");
        $s->execute([$pr['user_id']]); $u = $s->fetch(PDO::FETCH_ASSOC);

        $new_otp = str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $sent = sendResetOtp($pr['email'], trim(($u['first_name']??'').' '.($u['last_name']??'')), $new_otp);
        if(!$sent) throw new \Exception("Failed to resend. Please try again in a moment.");

        $_SESSION['pw_reset']['otp_hash']    = password_hash($new_otp, PASSWORD_DEFAULT);
        $_SESSION['pw_reset']['otp_exp']     = time() + 900;
        $_SESSION['pw_reset']['attempts']    = 0;
        $_SESSION['pw_reset']['resends']     = ($pr['resends']??0) + 1;
        $_SESSION['pw_reset']['sent_at']     = time();
        $_SESSION['pw_reset']['resend_after']= time() + 60;

        $view = 'verify';
        $msg  = "A new code has been sent to <strong>".htmlspecialchars($_SESSION['pw_reset']['email'])."</strong>.";

    } catch(\Exception $e) {
        $errors[] = $e->getMessage(); $view = 'verify';
    }
}

// ============================================================
// HANDLE — VERIFY OTP
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'verify_otp') {
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']??''))
            throw new \Exception("Security check failed.");

        $pr = $_SESSION['pw_reset'] ?? null;
        if(!$pr || empty($pr['otp_hash'])) throw new \Exception("Session expired. Please start again.");

        $entered = preg_replace('/\D/','', implode('', (array)($_POST['otp_digits']??[])));
        if(empty($entered)) $entered = preg_replace('/\D/','', trim($_POST['otp_single']??''));

        if(strlen($entered) !== 6) throw new \Exception("Please enter all 6 digits of the reset code.");
        if(time() > $pr['otp_exp'])  throw new \Exception("Your reset code has expired. Click <strong>Resend Code</strong>.");
        if(($pr['attempts']??0) >= 5) throw new \Exception("Too many attempts. Please click Resend Code for a new one.");

        if(!password_verify($entered, $pr['otp_hash'])) {
            $_SESSION['pw_reset']['attempts'] = ($pr['attempts']??0) + 1;
            $left = 5 - $_SESSION['pw_reset']['attempts'];
            if($left <= 0) throw new \Exception("Too many incorrect attempts. Please request a new code.");
            throw new \Exception("Incorrect code. <strong>{$left}</strong> attempt(s) remaining.");
        }

        // OTP correct
        $_SESSION['pw_reset']['verified'] = true;
        unset($_SESSION['pw_reset']['otp_hash']);
        $view = 'new_password';
        $msg  = "Code verified ✓ Now set your new password.";

    } catch(\Exception $e) {
        $errors[] = $e->getMessage(); $view = 'verify';
    }
}

// ============================================================
// HANDLE — SET NEW PASSWORD
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_password') {
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']??''))
            throw new \Exception("Security check failed.");

        $pr = $_SESSION['pw_reset'] ?? null;
        if(!$pr || empty($pr['verified'])) throw new \Exception("Session expired. Please start again.");

        $new_pw  = $_POST['new_password']     ?? '';
        $conf_pw = $_POST['confirm_password'] ?? '';

        if(strlen($new_pw) < 8)                throw new \Exception("Password must be at least 8 characters.");
        if(!preg_match('/[A-Z]/', $new_pw))    throw new \Exception("Password must contain at least one uppercase letter.");
        if(!preg_match('/[a-z]/', $new_pw))    throw new \Exception("Password must contain at least one lowercase letter.");
        if(!preg_match('/[0-9]/', $new_pw))    throw new \Exception("Password must contain at least one number.");
        if(!preg_match('/[\W_]/', $new_pw))    throw new \Exception("Password must contain at least one special character (!@#\$%).");
        if($new_pw !== $conf_pw)               throw new \Exception("Passwords do not match.");

        // Check new password isn't same as old
        $s = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $s->execute([$pr['user_id']]); $old_hash = $s->fetchColumn();
        if($old_hash && password_verify($new_pw, $old_hash))
            throw new \Exception("New password cannot be the same as your current password.");

        // Save
       
// ============================================================
// SAVE NEW PASSWORD
// ============================================================

$new_hash = password_hash($new_pw, PASSWORD_DEFAULT);

if ($new_hash === false) {
    throw new \Exception("Could not secure the new password. Please try again.");
}

$update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
 
$update->execute([$new_hash, (int)$pr['user_id']]);

// Make sure the database was actually updated
if ($update->rowCount() < 1) {
    throw new \Exception("Password could not be updated. Please try again.");
}

// Clear reset session
unset($_SESSION['pw_reset']);

// Generate a fresh CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Success message
$_SESSION['success_message'] = "Password reset successfully! Please sign in with your new password.";

header("Location: login");
exit;


      

    } catch(\Exception $e) {
        $errors[] = $e->getMessage(); $view = 'new_password';
    }
}

// ============================================================
// CANCEL
// ============================================================
if(isset($_GET['cancel'])) {
     unset($_SESSION['pw_reset']);
 header("Location: forget-password");
  exit; }

$pr            = $_SESSION['pw_reset'] ?? [];
$otp_exp_in   = isset($pr['otp_exp'])      ? max(0, $pr['otp_exp']-time())      : 0;
$resend_wait  = isset($pr['resend_after']) ? max(0, $pr['resend_after']-time()) : 0;
$resend_count = (int)($pr['resends']       ?? 0);
$attempts_used= (int)($pr['attempts']      ?? 0);
$masked_email = '';
if(!empty($pr['email'])) {
    $em = $pr['email'];
    $at = strpos($em,'@');
    $masked_email = substr($em,0,2).str_repeat('*',max(0,$at-3)).substr($em,$at-1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo ['request'=>'forget Password','verify'=>'Verify Code','new_password'=>'New Password'][$view]??'Reset'; ?> — MidwesternBank</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
<style>
:root { --navy:#0c2340; --gold:#c59b3d; --blue:#1a4b8c; --red:#dc2626; --green:#059669; --border:#e2e8f0; --muted:#64748b; }
*,*::before,*::after{ box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Inter',sans-serif; background:linear-gradient(135deg,#f0f4f8 0%,#e8eef6 100%); min-height:100vh; display:flex; flex-direction:column; }

/* Topbar */
.auth-top { background:var(--navy); height:60px; display:flex; align-items:center; padding:0 28px; justify-content:space-between; position:sticky; top:0; z-index:100; }
.auth-logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
.auth-logo-txt { font-size:18px; font-weight:800; color:#fff; }
.auth-logo-txt span { color:var(--gold); }
.auth-top-link { font-size:13px; color:rgba(255,255,255,.7); text-decoration:none; padding:6px 14px; border-radius:7px; transition:all .2s; }
.auth-top-link:hover { background:rgba(255,255,255,.12); color:#fff; }

/* Center wrapper */
.auth-center { flex:1; display:flex; align-items:center; justify-content:center; padding:32px 16px; }

/* Card */
.fp-card { background:#fff; border-radius:18px; width:100%; max-width:440px; overflow:hidden; box-shadow:0 8px 40px rgba(12,35,64,.14); animation:slideUp .3s ease; }
@keyframes slideUp { from{transform:translateY(18px);opacity:0;} to{transform:translateY(0);opacity:1;} }

/* Card header */
.fp-head { background:linear-gradient(135deg,var(--navy),#1a4b8c); padding:28px 32px; text-align:center; }
.fp-progress { display:flex; justify-content:center; gap:7px; margin-bottom:18px; }
.fp-prog-dot { width:8px; height:8px; border-radius:50%; background:rgba(255,255,255,.25); transition:all .3s; }
.fp-prog-dot.done   { background:var(--gold); }
.fp-prog-dot.active { background:var(--gold); width:24px; border-radius:4px; }

/* Body */
.fp-body { padding:28px 32px; }

/* Alerts */
.fp-error   { background:#fff5f5; border:1px solid #fecaca; color:var(--red); border-radius:9px; padding:11px 14px; font-size:13px; margin-bottom:14px; display:flex; gap:10px; align-items:flex-start; }
.fp-success { background:#f0fff4; border:1px solid #bbf7d0; color:var(--green); border-radius:9px; padding:11px 14px; font-size:13px; margin-bottom:14px; display:flex; gap:10px; align-items:flex-start; }

/* Form elements */
.fp-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--navy); display:block; margin-bottom:6px; }
.fp-input-wrap { position:relative; margin-bottom:14px; }
.fp-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:17px; color:var(--muted); pointer-events:none; }
.fp-input {
    width:100%; height:46px; padding:0 14px 0 42px;
    border:1.5px solid var(--border); border-radius:9px;
    font-size:14px; font-family:'Inter',sans-serif;
    color:#1a1a2e; background:#fafafa; outline:none; transition:all .2s;
}
.fp-input:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(26,75,140,.07); }
.pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:17px; color:var(--muted); transition:color .2s; }
.pw-toggle:hover { color:var(--navy); }

/* Password strength */
.pw-bar  { height:5px; background:var(--border); border-radius:3px; overflow:hidden; margin:6px 0 3px; }
.pw-fill { height:100%; border-radius:3px; transition:all .3s; width:0; }
.pw-lbl  { font-size:11px; }

/* Submit btn */
.fp-btn {
    width:100%; height:48px; border-radius:10px; border:none;
    background:linear-gradient(135deg,var(--gold),#d4a843);
    color:var(--navy); font-size:15px; font-weight:800; font-family:'Inter',sans-serif;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
    transition:all .25s; box-shadow:0 4px 14px rgba(197,155,61,.3);
    margin-top:6px;
}
.fp-btn:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(197,155,61,.4); }
.fp-btn:disabled { opacity:.65; cursor:not-allowed; transform:none; }

/* OTP Boxes */
.otp-boxes { display:flex; justify-content:center; gap:9px; margin:20px 0; }
.otp-box {
    width:54px; height:62px; border-radius:12px;
    border:2px solid var(--border); background:#fafafa;
    text-align:center; font-size:28px; font-weight:800;
    font-family:'Inter',sans-serif; color:var(--navy);
    outline:none; transition:all .2s;
}
.otp-box:focus  { border-color:var(--blue); background:#fff; box-shadow:0 0 0 4px rgba(26,75,140,.1); transform:scale(1.04); }
.otp-box.filled { border-color:var(--navy); background:#f0f4ff; }
.otp-box.success{ border-color:var(--green); background:#f0fff4; color:var(--green); }
.otp-box.error  { border-color:var(--red); background:#fff5f5; color:var(--red); animation:shake .3s; }
@keyframes shake { 0%,100%{transform:translateX(0);} 25%{transform:translateX(-5px);} 75%{transform:translateX(5px);} }

/* Timer */
.otp-timer { text-align:center; font-size:13px; color:var(--muted); margin-bottom:14px; display:flex; align-items:center; justify-content:center; gap:7px; }
.otp-timer-num { font-size:15px; font-weight:700; color:var(--navy); font-family:monospace; }
.otp-timer-num.urgent { color:var(--red); animation:blink .8s infinite; }
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:.4;} }

/* Attempts bar */
.att-bar  { height:4px; background:var(--border); border-radius:2px; overflow:hidden; margin-bottom:14px; }
.att-fill { height:100%; background:var(--green); border-radius:2px; transition:all .4s; }

.fp-link { display:flex; align-items:center; justify-content:center; gap:5px; font-size:13px; color:var(--muted); text-align:center; margin-top:14px; text-decoration:none; }
.fp-link a { color:var(--gold); font-weight:700; text-decoration:none; }
.fp-link a:hover { text-decoration:underline; }

.resend-btn { background:none; border:none; cursor:pointer; color:var(--gold); font-weight:700; font-size:13px; font-family:'Inter',sans-serif; text-decoration:underline; transition:color .2s; }
.resend-btn:hover { color:#b8860b; }
.resend-btn:disabled { color:var(--muted); text-decoration:none; cursor:not-allowed; }

/* Footer */
.auth-footer { background:var(--navy); padding:16px 24px; text-align:center; }
.auth-footer div { font-size:11px; color:rgba(255,255,255,.3); }

/* Mobile */
@media(max-width:480px){
    .fp-body { padding:20px; }
    .fp-head { padding:22px 20px; }
    .otp-box { width:44px; height:54px; font-size:22px; }
    .otp-boxes { gap:6px; }
}
@keyframes spin { from{transform:rotate(0deg);} to{transform:rotate(360deg);} }
</style>
</head>
<body>

<!-- TOP BAR -->
<header class="auth-top">
    <a href="<?php echo defined('BASE_URL')?BASE_URL:'/'; ?>" class="auth-logo">
        <?php echo mwLogo(32); ?>
        <div class="auth-logo-txt">Midwestern<span>Bank</span></div>
    </a>
    <a href="login" class="auth-top-link"><i class="ri-arrow-left-line"></i> Sign In</a>
</header>

<div class="auth-center">
<div class="fp-card">

    <!-- HEAD -->
    <div class="fp-head">
        <!-- Progress -->
        <div class="fp-progress">
            <div class="fp-prog-dot <?php echo in_array($view,['verify','new_password'])?'done':($view==='request'?'active':''); ?>"></div>
            <div class="fp-prog-dot <?php echo $view==='verify'?'active':($view==='new_password'?'done':''); ?>"></div>
            <div class="fp-prog-dot <?php echo $view==='new_password'?'active':''; ?>"></div>
        </div>
        <?php echo mwLogo(38); ?>
        <div style="font-size:20px;font-weight:800;color:#fff;margin-top:10px;">
            <?php echo ['request'=>'forget Password','verify'=>'Check Your Email','new_password'=>'Set New Password'][$view]; ?>
        </div>
        <div style="font-size:12px;color:rgba(255,255,255,.6);margin-top:5px;">
            <?php
            echo match($view) {
                'request'      => 'Enter your email and we\'ll send a reset code',
                'verify'       => !empty($masked_email) ? "Code sent to <strong style='color:var(--gold);'>{$masked_email}</strong>" : "Enter the 6-digit code from your email",
                'new_password' => 'Choose a strong new password for your account',
            };
            ?>
        </div>
    </div>

    <!-- BODY -->
    <div class="fp-body">

        <?php if(!empty($errors)): ?>
        <div class="fp-error">
            <i class="ri-error-warning-line" style="font-size:18px;flex-shrink:0;margin-top:1px;"></i>
            <div><?php echo implode('<br>',$errors); ?></div>
        </div>
        <?php endif; ?>

        <?php if(!empty($msg)): ?>
        <div class="fp-success">
            <i class="ri-checkbox-circle-line" style="font-size:18px;flex-shrink:0;margin-top:1px;"></i>
            <div><?php echo $msg; ?></div>
        </div>
        <?php endif; ?>

        <!-- ========== STEP 1: EMAIL ========== -->
        <?php if($view === 'request'): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action"     value="request_reset">

            <div>
                <label class="fp-label">Email Address <span style="color:var(--red);">*</span></label>
                <div class="fp-input-wrap">
                    <i class="ri-mail-line fp-icon"></i>
                    <input type="email" name="email" class="fp-input" required
                           placeholder="Enter the email linked to your account"
                           value="<?php echo htmlspecialchars($_POST['email']??''); ?>"
                           autocomplete="email" autofocus>
                </div>
            </div>

            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:11px 14px;font-size:12px;color:#1d4ed8;margin-bottom:16px;">
                <i class="ri-information-line mr-1"></i>
                We'll send a <strong>6-digit code</strong> to your email. Enter it on the next page to reset your password.
            </div>

            <button type="submit" class="fp-btn">
                <i class="ri-send-plane-fill"></i> Send Reset Code
            </button>
        </form>

        <!-- ========== STEP 2: OTP ========== -->
        <?php elseif($view === 'verify'): ?>

        <!-- Attempts bar -->
        <?php $att_pct = max(0, 100-($attempts_used/5*100)); $att_col = $attempts_used>=3?'#ef4444':($attempts_used>=2?'#f59e0b':'var(--green)'); ?>
        <div class="att-bar"><div class="att-fill" style="width:<?php echo $att_pct; ?>%;background:<?php echo $att_col; ?>;"></div></div>

        <!-- Timer -->
        <div class="otp-timer">
            <i class="ri-time-line"></i> Code expires in:
            <span class="otp-timer-num" id="otpTimer">
                <?php $m=floor($otp_exp_in/60);$s=$otp_exp_in%60;echo sprintf('%02d:%02d',$m,$s); ?>
            </span>
        </div>

        <form method="POST" id="otpForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action"     value="verify_otp">
            <input type="hidden" name="otp_single" id="otpHidden"  value="">

            <div class="otp-boxes">
                <?php for($i=0;$i<6;$i++): ?>
                <input type="tel" class="otp-box" id="ob<?php echo $i; ?>"
                       maxlength="1" inputmode="numeric" pattern="[0-9]"
                       autocomplete="<?php echo $i===0?'one-time-code':'off'; ?>"
                       placeholder="·">
                <?php endfor; ?>
            </div>

            <button type="submit" id="verifyBtn" class="fp-btn" disabled>
                <i class="ri-shield-check-fill"></i> Verify Code
            </button>
        </form>

        <div style="text-align:center;margin-top:14px;font-size:13px;color:var(--muted);">
            Didn't receive it?
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action"     value="resend_otp">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" class="resend-btn" id="resendBtn"
                        <?php echo ($resend_wait>0||$resend_count>=3)?'disabled':''; ?>>
                    <?php if($resend_count>=3): ?>Limit reached
                    <?php elseif($resend_wait>0): ?>Resend in <span id="resendTimer"><?php echo $resend_wait; ?>s</span>
                    <?php else: ?>Resend Code<?php endif; ?>
                </button>
            </form>
            <?php if($resend_count>0&&$resend_count<3): ?>
                <span style="font-size:11px;color:var(--muted);">(<?php echo 3-$resend_count; ?> left)</span>
            <?php endif; ?>
        </div>
        <div style="text-align:center;margin-top:10px;">
            <a href="forget-password?cancel=1" style="font-size:12px;color:var(--muted);text-decoration:underline;">
                <i class="ri-arrow-left-line"></i> Start Over
            </a>
        </div>

        <!-- ========== STEP 3: NEW PASSWORD ========== -->
        <?php elseif($view === 'new_password'): ?>

        <div style="background:#f0fff4;border:1px solid #bbf7d0;border-radius:9px;padding:10px 14px;font-size:13px;color:var(--green);margin-bottom:16px;display:flex;gap:8px;align-items:center;">
            <i class="ri-shield-check-fill"></i> Identity verified — now set your new password.
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action"     value="set_password">

            <div>
                <label class="fp-label">New Password <span style="color:var(--red);">*</span></label>
                <div class="fp-input-wrap">
                    <i class="ri-lock-line fp-icon"></i>
                    <input type="password" name="new_password" id="newPw" class="fp-input"
                           placeholder="Choose a strong password" required
                           autocomplete="new-password" oninput="checkPwStr(this.value)">
                    <button type="button" class="pw-toggle" onclick="togglePw('newPw','newPwEye')">
                        <i class="ri-eye-off-line" id="newPwEye"></i>
                    </button>
                </div>
                <div class="pw-bar"><div class="pw-fill" id="pwFill"></div></div>
                <div id="pwLbl" class="pw-lbl"></div>
                <div style="font-size:11px;color:var(--muted);margin-top:4px;">
                    Min 8 chars · Uppercase · Lowercase · Number · Special character
                </div>
            </div>

            <div style="margin-top:14px;">
                <label class="fp-label">Confirm New Password <span style="color:var(--red);">*</span></label>
                <div class="fp-input-wrap">
                    <i class="ri-lock-check-line fp-icon"></i>
                    <input type="password" name="confirm_password" id="confPw" class="fp-input"
                           placeholder="Re-enter your new password" required
                           autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePw('confPw','confPwEye')">
                        <i class="ri-eye-off-line" id="confPwEye"></i>
                    </button>
                </div>
                <div id="matchLbl" style="font-size:12px;height:16px;margin-top:4px;"></div>
            </div>

            <button type="submit" class="fp-btn" id="setPwBtn" style="margin-top:16px;">
                <i class="ri-lock-password-line"></i> Set New Password
            </button>
        </form>

        <?php endif; ?>

        <div class="fp-link">
            <i class="ri-shield-user-line"></i>
            Remembered it? <a href="login">Sign In</a>
        </div>
    </div>
</div>
</div>

<footer class="auth-footer">
    <div>&copy; <?php echo date('Y'); ?> MidwesternBank, N.A. · Member FDIC · <?php echo BANK_SWIFT; ?></div>
</footer>

<script>
// ── OTP BOXES ──
const boxes  = document.querySelectorAll('.otp-box');
const hid    = document.getElementById('otpHidden');
const vBtn   = document.getElementById('verifyBtn');

function getOtp() { return Array.from(boxes).map(b=>b.value.replace(/\D/g,'')).join(''); }
function updateHid() { if(hid) hid.value = getOtp(); }
function checkOtpReady() { if(vBtn) vBtn.disabled = getOtp().length !== 6; }

boxes.forEach((box,i) => {
    box.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g,'').slice(-1);
        this.classList.toggle('filled', this.value !== '');
        updateHid(); checkOtpReady();
        if(this.value && i<5) boxes[i+1].focus();
    });
    box.addEventListener('keydown', function(e) {
        if(e.key==='Backspace'&&!this.value&&i>0){ boxes[i-1].value='';boxes[i-1].classList.remove('filled');boxes[i-1].focus();updateHid();checkOtpReady(); }
        if(e.key==='ArrowLeft'&&i>0)  boxes[i-1].focus();
        if(e.key==='ArrowRight'&&i<5) boxes[i+1].focus();
    });
    box.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasted = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
        if(pasted.length>=6) {
            boxes.forEach((b,idx)=>{ b.value=pasted[idx]||''; b.classList.toggle('filled',b.value!==''); });
            boxes[5].focus(); updateHid(); checkOtpReady();
            // Auto submit after paste
            if(getOtp().length===6) setTimeout(()=>document.getElementById('otpForm')?.submit(),400);
        }
    });
});

// Auto-submit when all 6 filled
document.querySelectorAll('.otp-box').forEach(b=>b.addEventListener('input',()=>{
    if(getOtp().length===6) setTimeout(()=>{if(vBtn&&!vBtn.disabled) document.getElementById('otpForm')?.submit();},300);
}));

// Countdown timer
const timerEl = document.getElementById('otpTimer');
let secs = <?php echo $otp_exp_in; ?>;
if(timerEl && secs>0) {
    const ti = setInterval(()=>{
        secs=Math.max(0,secs-1);
        const m=Math.floor(secs/60),s=secs%60;
        timerEl.textContent=(m<10?'0':'')+m+':'+(s<10?'0':'')+s;
        if(secs<=60) timerEl.classList.add('urgent');
        if(secs<=0){ clearInterval(ti); timerEl.textContent='Expired'; if(vBtn){vBtn.disabled=true;vBtn.innerHTML='<i class="ri-time-line"></i> Code Expired';vBtn.style.background='#e2e8f0';vBtn.style.color='#64748b';} }
    },1000);
}

// Resend countdown
let rsecs=<?php echo $resend_wait; ?>;
const rBtn=document.getElementById('resendBtn'), rTimer=document.getElementById('resendTimer');
if(rBtn && rsecs>0) {
    rBtn.disabled=true;
    const ri=setInterval(()=>{
        rsecs=Math.max(0,rsecs-1);
        if(rTimer) rTimer.textContent=rsecs+'s';
        if(rsecs<=0){ clearInterval(ri); rBtn.disabled=false; rBtn.textContent='Resend Code'; }
    },1000);
}

// Focus first box
document.addEventListener('DOMContentLoaded',()=>{ if(boxes.length) boxes[0].focus(); });

// Prevent double-submit
document.getElementById('otpForm')?.addEventListener('submit',function(){
    if(vBtn){ vBtn.disabled=true; vBtn.innerHTML='<i class="ri-loader-4-line" style="animation:spin 1s linear infinite;"></i> Verifying...'; }
    boxes.forEach(b=>b.classList.add('success'));
});

// ── PASSWORD STRENGTH ──
function checkPwStr(val) {
    let s=0;
    if(val.length>=8)s++;if(val.length>=12)s++;
    if(/[A-Z]/.test(val))s++;if(/[a-z]/.test(val))s++;
    if(/[0-9]/.test(val))s++;if(/[\W_]/.test(val))s++;
    const c=['transparent','#ef4444','#f97316','#eab308','#3b82f6','#22c55e','#059669'];
    const l=['','Very Weak','Weak','Fair','Good','Strong','Very Strong'];
    const i=Math.min(s,6);
    const f=document.getElementById('pwFill'),lb=document.getElementById('pwLbl');
    if(f){f.style.width=Math.round(i/6*100)+'%';f.style.background=c[i];}
    if(lb){lb.textContent=i>0?l[i]:'';lb.style.color=c[i];}
}

// Password match check
const np=document.getElementById('newPw'), cp=document.getElementById('confPw'), ml=document.getElementById('matchLbl');
if(np&&cp&&ml) {
    [np,cp].forEach(el=>el.addEventListener('input',()=>{
        if(!cp.value){ml.textContent='';return;}
        if(np.value===cp.value){ml.textContent='✓ Passwords match';ml.style.color='var(--green)';}
        else{ml.textContent='✗ Passwords do not match';ml.style.color='var(--red)';}
    }));
}

// Show/hide password
function togglePw(inputId, iconId) {
    const inp=document.getElementById(inputId),ico=document.getElementById(iconId);
    if(inp.type==='password'){inp.type='text';ico.className='ri-eye-line';}
    else{inp.type='password';ico.className='ri-eye-off-line';}
}
</script>
</body>
</html>