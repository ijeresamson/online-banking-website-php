<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
require_once 'vendor/autoload.php';

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

// ============================================================
// OTP SEND HELPER
// ============================================================
function sendOtpEmail(string $email, string $first_name, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = (int)SMTP_PORT;
        $mail->SMTPOptions = ['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
        $mail->Timeout    = 10;
        set_time_limit(60);

        $mail->setFrom(SMTP_FROM, 'MidwesternBank, N.A.');
        $mail->addAddress($email, $first_name);
        $mail->isHTML(true);
        $mail->Subject = "Your MidwesternBank Verification Code  {$otp}";
        $mail->Body    = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#f4f7fc;font-family:Inter,-apple-system,sans-serif;'>
          <div style='max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(12,35,64,.12);'>

            <!-- Header -->
            <div style='background:linear-gradient(135deg,#0c2340,#142d52);padding:32px 36px;text-align:center;'>
              <div style='display:inline-flex;align-items:center;gap:12px;'>
              
                <img src='cid:mwlogo' alt='MidwesternBank' style='width:44px;height:44px;'>
                <div style='font-size:22px;font-weight:800;color:#fff;'>Midwestern<span style='color:#c59b3d;'>Bank</span></div>
              </div>
              <div style='font-size:13px;color:rgba(255,255,255,.55);margin-top:6px;'>Official Banking Communication</div>
            </div>

             <!-- ======= HEADER ======= -->

            <!-- Body -->
            <div style='padding:36px;'>
              <div style='font-size:18px;font-weight:700;color:#0c2340;margin-bottom:10px;'>
                Hi {$first_name}, verify your email address
              </div>
              <p style='font-size:14px;color:#64748b;line-height:1.7;margin-bottom:24px;'>
                You are opening a new MidwesternBank Checking Account. To complete your application,
                please enter the verification code below. This code confirms your email address
                and secures your account.
              </p>

              <!-- OTP Box -->
              <div style='background:#f4f7fc;border:2px dashed #c59b3d;border-radius:14px;padding:28px;text-align:center;margin-bottom:24px;'>
                <div style='font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;margin-bottom:12px;'>
                  Your Verification Code
                </div>
                <div style='font-size:48px;font-weight:800;color:#0c2340;letter-spacing:10px;font-family:monospace;'>
                  {$otp}
                </div>
                <div style='font-size:12px;color:#dc2626;font-weight:600;margin-top:12px;'>
                   Expires in 10 minutes
                </div>
              </div>

              <!-- Instructions -->
              <div style='background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:24px;'>
                <div style='font-size:13px;font-weight:700;color:#1e40af;margin-bottom:6px;'>
                  📋 How to use this code:
                </div>
                <ol style='font-size:13px;color:#1d4ed8;margin:0;padding-left:18px;line-height:1.8;'>
                  <li>Return to the MidwesternBank registration page</li>
                  <li>Enter the 6-digit code in the verification field</li>
                  <li>Click <strong>Verify &amp; Create Account</strong></li>
                </ol>
              </div>

              <!-- Security Warning -->
              <div style='background:#fff5f5;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;margin-bottom:24px;'>
                <div style='font-size:13px;font-weight:700;color:#dc2626;margin-bottom:4px;'>
                  🔒 Security Notice
                </div>
                <ul style='font-size:12px;color:#dc2626;margin:0;padding-left:18px;line-height:1.8;'>
                  <li>MidwesternBank will <strong>never</strong> call or email to ask for this code</li>
                  <li>Do <strong>not</strong> share this code with anyone</li>
                  <li>If you did not request this, ignore this email and no account will be created</li>
                </ul>
              </div>

              <p style='font-size:13px;color:#94a3b8;text-align:center;margin-bottom:0;'>
                If you have any issues, contact us at
                <a href='mailto:support@midwesternbank.com' style='color:#c59b3d;'>support@midwesternbank.com</a>
              </p>
            </div>

            <!-- Footer -->
            <div style='background:#070f1c;padding:20px 36px;text-align:center;'>
              <div style='font-size:12px;color:rgba(255,255,255,.35);line-height:1.8;'>
                MidwesternBank, N.A. · 200 Midwestern Plaza, Chicago, IL 60601<br>
                Member FDIC · Equal Housing Lender<br>
                &copy; ".date('Y')." All rights reserved.
                <a href='#' style='color:rgba(255,255,255,.35);'>Unsubscribe</a>
              </div>
            </div>

          </div>
        </body>
        </html>";

        $mail->AltBody = "Hi {$first_name},\n\nYour MidwesternBank email verification code is:\n\n{$otp}\n\nThis code expires in 10 minutes.\nDo NOT share this code with anyone.\n\nMidwesternBank, N.A.";

        $mail->send();
        return true;
    } catch(\Exception $e) {
        error_log('OTP Email send error: '.$e->getMessage());
        return false;
    }
}

// ============================================================
// HELPER — Generate unique account number
// ============================================================
function generateAccountNumber($pdo) {
    do {
        $num = '30'.mt_rand(10000000, 99999999);
        $s   = $pdo->prepare("SELECT COUNT(*) FROM users WHERE account_number=?");
        $s->execute([$num]);
    } while((int)$s->fetchColumn() > 0);
    return (string)$num;
}

// ============================================================
// HELPER — Validate uploaded file MIME
// ============================================================
function isAllowedFile(string $tmp, string $ext): bool {
    if(!in_array($ext, ['jpg','jpeg','png','pdf'], true)) return false;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if(!$finfo) return false;
    $mime  = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    return in_array($mime, ['image/jpeg','image/png','application/pdf'], true);
}

// ============================================================
// STATE
// ============================================================
$view          = 'register'; // register | verify_otp
$errors        = [];
$old           = [];
$otp_errors    = [];
$otp_cooldown  = 0;

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// Restore view if OTP is pending
if(!empty($_SESSION['reg_pending']) && $action !== 'submit_registration') {
    $view = 'verify_otp';
}

// ============================================================
// HANDLE — SUBMIT REGISTRATION (Step 1 → Send OTP)
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit_registration') {

    $old = [
        'first_name'        => trim($_POST['first_name']        ?? ''),
        'last_name'         => trim($_POST['last_name']         ?? ''),
        'email'             => trim($_POST['email']             ?? ''),
        'phone'             => trim($_POST['phone']             ?? ''),
        'date_of_birth'     => trim($_POST['date_of_birth']     ?? ''),
        'nationality'       => trim($_POST['nationality']       ?? ''),
        'employment_status' => trim($_POST['employment_status'] ?? ''),
        'source_of_funds'   => trim($_POST['source_of_funds']   ?? ''),
        'id_type'           => trim($_POST['id_type']           ?? ''),
    ];

    $id_document_path      = '';
    $address_document_path = '';

    try {
        // CSRF
        if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))
            throw new \Exception("Security check failed. Please refresh and try again.");

        $first_name        = $old['first_name'];
        $last_name         = $old['last_name'];
        $email             = strtolower($old['email']);
        $phone             = $old['phone'];
        $date_of_birth     = $old['date_of_birth'];
        $nationality       = $old['nationality'];
        $employment_status = $old['employment_status'];
        $source_of_funds   = $old['source_of_funds'];
        $id_type           = $old['id_type'];
        $password          = $_POST['password']         ?? '';
        $confirm_password  = $_POST['confirm_password'] ?? '';

        // ── VALIDATE PERSONAL INFO ──
        if(empty($first_name) || strlen($first_name)<2 || strlen($first_name)>50)
            throw new \Exception("First name must be 2–50 characters.");
        if(!preg_match("/^[a-zA-Z\s\-']+$/", $first_name))
            throw new \Exception("First name contains invalid characters.");
        if(empty($last_name) || strlen($last_name)<2 || strlen($last_name)>50)
            throw new \Exception("Last name must be 2–50 characters.");
        if(!preg_match("/^[a-zA-Z\s\-']+$/", $last_name))
            throw new \Exception("Last name contains invalid characters.");

        if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new \Exception("Please enter a valid email address.");
        if(strlen($email) > 255)
            throw new \Exception("Email address is too long.");

        // Check email not already registered
        $s = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $s->execute([$email]);
        if($s->fetch())
            throw new \Exception("An account with this email address already exists. Please <a href='login'>sign in</a> instead.");

        if(empty($phone) || !preg_match('/^[0-9\+\-\(\)\s]{7,20}$/', $phone))
            throw new \Exception("Please enter a valid phone number.");

        if(empty($date_of_birth))
            throw new \Exception("Date of birth is required.");
        $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
        if(!$dob || $dob->format('Y-m-d') !== $date_of_birth)
            throw new \Exception("Invalid date of birth.");
        $age = (new DateTime())->diff($dob)->y;
        if($age < 18) throw new \Exception("You must be at least 18 years old to open an account.");
        if($age > 120) throw new \Exception("Please enter a valid date of birth.");

        if(empty($nationality)) throw new \Exception("Please select your country.");

        $allowed_emp = ['employed','self_employed','freelancer','student','unemployed','retired'];
        if(!in_array($employment_status, $allowed_emp))
            throw new \Exception("Please select your employment status.");

        $allowed_funds = ['salary','business','investment','inheritance','savings','other'];
        if(!in_array($source_of_funds, $allowed_funds))
            throw new \Exception("Please select your primary source of funds.");

        $allowed_ids = ['passport','drivers_license','national_id'];
        if(!in_array($id_type, $allowed_ids))
            throw new \Exception("Please select your ID document type.");

        // ── VALIDATE PASSWORD ──
        if(empty($password)) throw new \Exception("Password is required.");
        if(strlen($password) < 8) throw new \Exception("Password must be at least 8 characters.");
        if(!preg_match('/[A-Z]/', $password)) throw new \Exception("Password must contain at least one uppercase letter.");
        if(!preg_match('/[a-z]/', $password)) throw new \Exception("Password must contain at least one lowercase letter.");
        if(!preg_match('/[0-9]/', $password)) throw new \Exception("Password must contain at least one number.");
        if(!preg_match('/[\W_]/', $password)) throw new \Exception("Password must contain at least one special character (e.g. !@#\$%).");
        if($password !== $confirm_password)   throw new \Exception("Passwords do not match. Please re-enter both fields.");

        // ── VALIDATE FILE UPLOADS ──
        $max_size   = 5 * 1024 * 1024;
        $upload_dir = dirname(__DIR__).'/uploads/kyc/';
        if(!is_dir($upload_dir)) {
            if(!@mkdir($upload_dir, 0755, true)) {
                $upload_dir = __DIR__.'/uploads/kyc/';
                if(!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
            }
        }

        if(!isset($_FILES['id_document']) || $_FILES['id_document']['error'] !== UPLOAD_ERR_OK)
            throw new \Exception("Please upload your Government ID document (JPG, PNG, or PDF).");
        if($_FILES['id_document']['size'] > $max_size)
            throw new \Exception("ID Document must not exceed 5MB.");
        $id_ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
        if(!isAllowedFile($_FILES['id_document']['tmp_name'], $id_ext))
            throw new \Exception("ID Document must be a JPG, PNG, or PDF file.");
        $id_document_path = $upload_dir.'id_'.bin2hex(random_bytes(16)).'.'.$id_ext;

        if(!isset($_FILES['address_document']) || $_FILES['address_document']['error'] !== UPLOAD_ERR_OK)
            throw new \Exception("Please upload your Proof of Address document.");
        if($_FILES['address_document']['size'] > $max_size)
            throw new \Exception("Proof of Address must not exceed 5MB.");
        $addr_ext = strtolower(pathinfo($_FILES['address_document']['name'], PATHINFO_EXTENSION));
        if(!isAllowedFile($_FILES['address_document']['tmp_name'], $addr_ext))
            throw new \Exception("Proof of Address must be a JPG, PNG, or PDF file.");
        $address_document_path = $upload_dir.'addr_'.bin2hex(random_bytes(16)).'.'.$addr_ext;

        // ── MOVE FILES ──
        if(!move_uploaded_file($_FILES['id_document']['tmp_name'], $id_document_path))
            throw new \Exception("Failed to save ID document. Please try again.");
        if(!move_uploaded_file($_FILES['address_document']['tmp_name'], $address_document_path)) {
            if(file_exists($id_document_path)) unlink($id_document_path);
            throw new \Exception("Failed to save address document. Please try again.");
        }

        // ── GENERATE & STORE OTP ──
        $otp      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_hash = password_hash($otp, PASSWORD_DEFAULT);

        // Store pending registration in session
        $_SESSION['reg_pending'] = [
            'first_name'           => $first_name,
            'last_name'            => $last_name,
            'email'                => $email,
            'phone'                => $phone,
            'date_of_birth'        => $date_of_birth,
            'nationality'          => $nationality,
            'employment_status'    => $employment_status,
            'source_of_funds'      => $source_of_funds,
            'id_type'              => $id_type,
            'id_document_path'     => $id_document_path,
            'address_document_path'=> $address_document_path,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'otp_hash'             => $otp_hash,
            'otp_expires'          => time() + 600, // 10 minutes
            'otp_attempts'         => 0,
            'otp_resend_after'     => time() + 60,  // can resend after 60 seconds
            'otp_resend_count'     => 0,
        ];

        // ── SEND OTP EMAIL ──
        $sent = sendOtpEmail($email, $first_name, $otp);

        if(!$sent) {
            // Clean up uploaded files if email fails
            if(file_exists($id_document_path))      unlink($id_document_path);
            if(file_exists($address_document_path)) unlink($address_document_path);
            unset($_SESSION['reg_pending']);
            throw new \Exception("We could not send the verification code to <strong>".htmlspecialchars($email)."</strong>. Please check the email address and try again.");
        }

        // Log OTP in dev mode
        if(defined('APP_DEBUG') && APP_DEBUG) {
            error_log("OTP for {$email}: {$otp}");
        }

        // Move to OTP view
        $view = 'verify_otp';

    } catch(\Exception $e) {
        // Clean up uploaded files on error
        if(!empty($id_document_path)      && file_exists($id_document_path))      unlink($id_document_path);
        if(!empty($address_document_path) && file_exists($address_document_path)) unlink($address_document_path);
        $errors[] = $e->getMessage();
        $view = 'register';
    }
}

// ============================================================
// HANDLE — RESEND OTP
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'resend_otp') {
    if(empty($_SESSION['reg_pending'])) {
        $otp_errors[] = "Session expired. Please start your registration again.";
        $view = 'register';
    } else {
        $pending = &$_SESSION['reg_pending'];

        // Cooldown check
        if(time() < $pending['otp_resend_after']) {
            $wait = $pending['otp_resend_after'] - time();
            $otp_errors[] = "Please wait {$wait} more second(s) before requesting a new code.";
            $view = 'verify_otp';
        } elseif(($pending['otp_resend_count'] ?? 0) >= 3) {
            $otp_errors[] = "Maximum resend limit reached. Please start your registration again.";
            $view = 'verify_otp';
        } else {
            // Generate new OTP
            $new_otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pending['otp_hash']         = password_hash($new_otp, PASSWORD_DEFAULT);
            $pending['otp_expires']      = time() + 600;
            $pending['otp_attempts']     = 0;
            $pending['otp_resend_after'] = time() + 60;
            $pending['otp_resend_count'] = ($pending['otp_resend_count'] ?? 0) + 1;

            $sent = sendOtpEmail($pending['email'], $pending['first_name'], $new_otp);

            if($sent) {
                $_SESSION['otp_success'] = "A new verification code has been sent to <strong>".htmlspecialchars($pending['email'])."</strong>.";
            } else {
                $otp_errors[] = "Failed to resend the code. Please check your email address or try again in a moment.";
            }
            $view = 'verify_otp';
        }
    }
}

// ============================================================
// HANDLE — VERIFY OTP & CREATE ACCOUNT (Step 2)
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'verify_otp') {
    if(empty($_SESSION['reg_pending'])) {
        $otp_errors[] = "Your registration session has expired. Please fill in the form again.";
        $view = 'register';
    } else {
        $pending  = &$_SESSION['reg_pending'];
        $entered  = preg_replace('/\D/', '', implode('', (array)($_POST['otp_digits'] ?? [])));

        // Also support single input
        if(empty($entered)) {
            $entered = preg_replace('/\D/', '', trim($_POST['otp_single'] ?? ''));
        }

        try {
            // CSRF
            if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))
                throw new \Exception("Security check failed. Please refresh and try again.");

            if(empty($entered) || strlen($entered) !== 6)
                throw new \Exception("Please enter all 6 digits of your verification code.");

            // Expiry check
            if(time() > $pending['otp_expires'])
                throw new \Exception("Your verification code has expired. Please click <strong>Resend Code</strong> to get a new one.");

            // Attempt limit
            if($pending['otp_attempts'] >= 5)
                throw new \Exception("Too many incorrect attempts. Please request a new code by clicking <strong>Resend Code</strong>.");

            // Verify OTP
            if(!password_verify($entered, $pending['otp_hash'])) {
                $pending['otp_attempts']++;
                $remaining = 5 - $pending['otp_attempts'];
                if($remaining <= 0) {
                    throw new \Exception("Too many incorrect attempts. Please request a new code by clicking <strong>Resend Code</strong>.");
                }
                throw new \Exception("Incorrect verification code. You have <strong>{$remaining}</strong> attempt(s) remaining.");
            }

            // ✅ OTP CORRECT — Create account
            $pdo->beginTransaction();

            $account_number = generateAccountNumber($pdo);

            $stmt = $pdo->prepare("INSERT INTO users (
                    account_number, first_name, last_name, email, phone,
                    date_of_birth, nationality, employment_status, source_of_funds,
                    id_type, id_document, address_document,
                    password, email_verified, status, created_at
                ) VALUES (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,1,'pending_kyc',NOW()
                )
            ");
            $stmt->execute([
                $account_number,
                $pending['first_name'],
                $pending['last_name'],
                $pending['email'],
                $pending['phone'],
                $pending['date_of_birth'],
                $pending['nationality'],
                $pending['employment_status'],
                $pending['source_of_funds'],
                $pending['id_type'],
                $pending['id_document_path'],
                $pending['address_document_path'],
                $pending['password_hash'],
            ]);

            $new_user_id = (int)$pdo->lastInsertId();
            if($new_user_id === 0) throw new \Exception("Account creation failed. Please try again.");

            // Create bank account record
            createAccountForUser($pdo, $new_user_id, $pending['first_name'], $pending['last_name'], 'Checking');

            $pdo->commit();

        
            // SEND WELCOME EMAIL
            // ============================================================

            include "email_box/welcome-email.php";

            // Clear session
            unset($_SESSION['reg_pending']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $_SESSION['success_message'] =
                "🎉 Account created and email verified! Your account number is <strong>{$account_number}</strong>. " .
                "Your KYC documents are under review — you'll be notified by email within 1–2 business days.";

            header("Location: login"); 
            exit;

        } catch(\Exception $e) {
            $otp_errors[] = $e->getMessage();
            $view = 'verify_otp';
        }
    }
}

// ============================================================
// HANDLE — CANCEL / START OVER
// ============================================================
if(isset($_GET['cancel_otp'])) {
    if(!empty($_SESSION['reg_pending'])) {
        $p = $_SESSION['reg_pending'];
        // Clean up uploaded files
        if(!empty($p['id_document_path'])      && file_exists($p['id_document_path']))      unlink($p['id_document_path']);
        if(!empty($p['address_document_path']) && file_exists($p['address_document_path'])) unlink($p['address_document_path']);
    }
    unset($_SESSION['reg_pending']);
    header("Location: register");
     exit;
}

// ============================================================
// OTP timer info
// ============================================================
$otp_expires_in   = 0;
$otp_resend_wait  = 0;
$otp_resend_count = 0;

if(!empty($_SESSION['reg_pending'])) {
    $otp_expires_in   = max(0, $_SESSION['reg_pending']['otp_expires']      - time());
    $otp_resend_wait  = max(0, $_SESSION['reg_pending']['otp_resend_after']  - time());
    $otp_resend_count = (int)($_SESSION['reg_pending']['otp_resend_count']   ?? 0);
}

$masked_email = '';
if(!empty($_SESSION['reg_pending']['email'])) {
    $em  = $_SESSION['reg_pending']['email'];
    $at  = strpos($em, '@');
    $masked_email = substr($em, 0, 2)
        . str_repeat('*', max(0, $at - 3))
        . substr($em, $at - 1);
}

$otp_success_msg = $_SESSION['otp_success'] ?? '';
unset($_SESSION['otp_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $view === 'verify_otp' ? 'Verify Your Email — ' : 'Open Account — '; ?>
        MidwesternBank Online Banking
    </title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
    /* ============================================================
       MIDWESTERNBANK — REGISTER + OTP STYLES
    ============================================================ */
    :root {
        --navy:   #0c2340;
        --navy-2: #142d52;
        --blue:   #1a4b8c;
        --gold:   #c59b3d;
        --gold-2: #d4a843;
        --white:  #ffffff;
        --light:  #f4f7fc;
        --border: #e2e8f0;
        --text:   #1a1a2e;
        --muted:  #64748b;
        --red:    #dc2626;
        --green:  #059669;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
        font-family: 'Inter', -apple-system, sans-serif;
        background: var(--light);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* ── TOP BAR ── */
    .auth-topbar {
        background: var(--navy);
        padding: 0 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 64px;
        box-shadow: 0 2px 12px rgba(0,0,0,.2);
        position: sticky; top: 0; z-index: 100;
        flex-shrink: 0;
    }
    .auth-logo { display:flex; align-items:center; gap:12px; text-decoration:none; }
    .auth-logo-text { font-size:20px; font-weight:800; color:#fff; letter-spacing:-.3px; }
    .auth-logo-text span { color: var(--gold); }
    .auth-topbar-links { display:flex; align-items:center; gap:10px; }
    .auth-topbar-links a { font-size:13px; color:rgba(255,255,255,.75); text-decoration:none; padding:7px 14px; border-radius:7px; transition:all .2s; }
    .auth-topbar-links a:hover { color:#fff; background:rgba(255,255,255,.1); }
    .btn-signin { background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2); color:#fff !important; font-weight:600; }

    /* ── REGISTER LAYOUT ── */
    .reg-layout {
        display: grid;
        grid-template-columns: 360px 1fr;
        flex: 1;
    }

    /* ── SIDEBAR ── */
    .reg-sidebar {
        background: linear-gradient(160deg, var(--navy) 0%, var(--navy-2) 60%, #1a4b8c 100%);
        padding: 40px 32px;
        position: sticky;
        top: 64px;
        height: calc(100vh - 64px);
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
    .reg-sidebar::-webkit-scrollbar { width: 4px; }
    .reg-sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 2px; }

    .step-item { display:flex; align-items:flex-start; gap:14px; padding:12px 0; position:relative; }
    .step-item:not(:last-child)::after { content:''; position:absolute; left:16px; top:44px; width:2px; height:calc(100% - 14px); background:rgba(255,255,255,.08); }
    .step-num { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; position:relative; z-index:1; transition:all .3s; }
    .step-num.done    { background:var(--gold); color:var(--navy); }
    .step-num.active  { background:var(--blue); color:#fff; box-shadow:0 0 0 4px rgba(197,155,61,.2); }
    .step-num.waiting { background:rgba(255,255,255,.08); color:rgba(255,255,255,.3); border:1px solid rgba(255,255,255,.1); }
    .step-lbl  { font-size:13px; font-weight:600; color:#fff; margin-bottom:2px; }
    .step-desc { font-size:11px; color:rgba(255,255,255,.4); }

    .acct-box { background:rgba(197,155,61,.1); border:1px solid rgba(197,155,61,.25); border-radius:12px; padding:16px; margin:20px 0; }
    .acct-box-title { font-size:12px; font-weight:700; color:var(--gold); margin-bottom:10px; display:flex; align-items:center; gap:6px; }
    .acct-row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid rgba(255,255,255,.06); font-size:12px; }
    .acct-row:last-child { border-bottom:none; }
    .acct-lbl { color:rgba(255,255,255,.4); }
    .acct-val { color:#fff; font-weight:600; }

    .trust-item { display:flex; align-items:center; gap:10px; padding:7px 0; font-size:12px; color:rgba(255,255,255,.45); }
    .trust-icon { width:26px; height:26px; background:rgba(197,155,61,.1); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--gold); font-size:13px; flex-shrink:0; }

    /* ── FORM AREA ── */
    .reg-form-area { background:var(--light); padding:36px 44px; overflow-y:auto; }
    .reg-form-wrap { max-width:680px; }

    /* ── SECTION CARDS ── */
    .form-section { background:#fff; border-radius:14px; padding:26px 28px; margin-bottom:18px; border:1px solid var(--border); box-shadow:0 1px 4px rgba(12,35,64,.06); }
    .section-head { display:flex; align-items:center; gap:12px; margin-bottom:22px; padding-bottom:14px; border-bottom:1px solid var(--border); }
    .section-icon { width:40px; height:40px; background:linear-gradient(135deg,var(--navy),var(--blue)); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; color:var(--gold); flex-shrink:0; }
    .section-title { font-size:15px; font-weight:700; color:var(--navy); }
    .section-num   { font-size:11px; color:var(--muted); margin-top:2px; }

    /* ── FORM GRID ── */
    .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .col-full  { grid-column:1/-1; }

    /* ── FORM FIELDS ── */
    .form-group { display:flex; flex-direction:column; gap:5px; }
    .form-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--navy); }
    .form-label span.req { color:var(--red); }
    .input-wrap { position:relative; }
    .inp-icon   { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--muted); pointer-events:none; }
    .form-input {
        width:100%; height:46px; padding:0 14px 0 42px;
        border:1.5px solid var(--border); border-radius:9px;
        font-size:14px; font-family:'Inter',sans-serif;
        color:var(--text); background:#fafafa; outline:none; transition:all .2s;
    }
    .form-input:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(26,75,140,.07); }
    .form-input.no-icon { padding-left:14px; }
    select.form-input { cursor:pointer; }
    .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:16px; color:var(--muted); transition:color .2s; padding:0; }
    .pw-toggle:hover { color:var(--navy); }

    /* ── STRENGTH BAR ── */
    .pw-strength-bar  { height:5px; background:var(--border); border-radius:3px; margin-top:7px; overflow:hidden; }
    .pw-strength-fill { height:100%; border-radius:3px; transition:all .3s; width:0; }
    .pw-strength-lbl  { font-size:11px; margin-top:4px; }

    /* ── FILE UPLOAD ── */
    .file-upload-box { position:relative; }
    .file-upload-box input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; z-index:2; }
    .file-label {
        display:flex; align-items:center; justify-content:center; gap:10px;
        height:52px; border:2px dashed var(--border); border-radius:9px;
        background:#fafafa; font-size:13px; color:var(--muted);
        cursor:pointer; transition:all .2s; font-weight:500;
    }
    .file-label:hover { border-color:var(--blue); color:var(--blue); background:#f0f5ff; }
    .file-label.uploaded { border-color:var(--green); color:var(--green); background:#f0fff4; border-style:solid; }

    /* ── ALERTS ── */
    .alert { display:flex; align-items:flex-start; gap:12px; padding:14px 18px; border-radius:10px; font-size:14px; font-weight:500; margin-bottom:18px; border:1px solid transparent; animation:slideIn .3s ease; line-height:1.5; }
    @keyframes slideIn { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:translateY(0);} }
    .alert i    { font-size:18px; flex-shrink:0; margin-top:1px; }
    .alert-error   { background:#fff5f5; border-color:#fecaca; color:#dc2626; }
    .alert-success { background:#f0fff4; border-color:#bbf7d0; color:#059669; }
    .alert-info    { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }

    /* ── CHECKBOX ── */
    .check-row { display:flex; align-items:flex-start; gap:10px; padding:14px 16px; background:var(--light); border-radius:9px; border:1px solid var(--border); }
    .check-row input { accent-color:var(--blue); margin-top:3px; flex-shrink:0; }
    .check-row label { font-size:13px; color:var(--muted); line-height:1.6; cursor:pointer; }
    .check-row a { color:var(--blue); font-weight:600; }

    /* ── SUBMIT BUTTON ── */
    .btn-submit {
        width:100%; height:52px; background:linear-gradient(135deg,var(--gold),var(--gold-2));
        color:var(--navy); border:none; border-radius:10px;
        font-size:16px; font-weight:800; font-family:'Inter',sans-serif;
        cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px;
        transition:all .25s; box-shadow:0 4px 16px rgba(197,155,61,.3); margin-top:18px;
    }
    .btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(197,155,61,.4); }
    .btn-submit:disabled { opacity:.7; cursor:not-allowed; transform:none; }

    /* ── OTP PAGE ── */
    .otp-page { min-height:calc(100vh - 64px - 72px); display:flex; align-items:center; justify-content:center; padding:40px 24px; background:var(--light); }
    .otp-card { background:#fff; border-radius:20px; width:100%; max-width:480px; overflow:hidden; box-shadow:0 8px 40px rgba(12,35,64,.14); }
    .otp-head { background:linear-gradient(135deg,var(--navy),#1a4b8c); padding:32px 36px; text-align:center; }
    .otp-body { padding:32px 36px; }

    /* OTP digit boxes */
    .otp-boxes { display:flex; justify-content:center; gap:10px; margin:24px 0; }
    .otp-box {
        width:56px; height:64px; border-radius:12px;
        border:2px solid var(--border); background:#fafafa;
        text-align:center; font-size:28px; font-weight:800;
        font-family:'Inter',sans-serif; color:var(--navy);
        outline:none; transition:all .2s; caret-color:var(--navy);
    }
    .otp-box:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 4px rgba(26,75,140,.1); transform:scale(1.05); }
    .otp-box.filled { border-color:var(--navy); background:#f0f4ff; }
    .otp-box.success{ border-color:var(--green); background:#f0fff4; color:var(--green); }
    .otp-box.error  { border-color:var(--red); background:#fff5f5; color:var(--red); animation:shake .3s; }
    @keyframes shake { 0%,100%{transform:translateX(0);} 25%{transform:translateX(-6px);} 75%{transform:translateX(6px);} }

    /* OTP Timer */
    .otp-timer { display:flex; align-items:center; justify-content:center; gap:8px; font-size:13px; color:var(--muted); margin-bottom:16px; }
    .otp-timer-num { font-size:15px; font-weight:700; color:var(--navy); font-family:monospace; }
    .otp-timer-num.urgent { color:var(--red); animation:pulse .8s infinite; }
    @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.5;} }

    /* OTP attempts bar */
    .attempts-bar { height:4px; background:var(--border); border-radius:2px; overflow:hidden; margin-bottom:16px; }
    .attempts-fill { height:100%; background:var(--green); border-radius:2px; transition:width .4s, background .4s; }

    /* Resend section */
    .resend-section { text-align:center; margin-top:18px; font-size:13px; color:var(--muted); }
    .resend-btn { background:none; border:none; cursor:pointer; color:var(--gold); font-weight:700; font-size:13px; font-family:'Inter',sans-serif; padding:0; text-decoration:underline; transition:color .2s; }
    .resend-btn:hover { color:var(--gold-2); }
    .resend-btn:disabled { color:var(--muted); text-decoration:none; cursor:not-allowed; }

    /* Progress dots on OTP page */
    .otp-progress { display:flex; justify-content:center; gap:8px; margin-bottom:24px; }
    .otp-prog-dot { width:8px; height:8px; border-radius:50%; background:rgba(255,255,255,.25); transition:all .3s; }
    .otp-prog-dot.active { background:var(--gold); width:24px; border-radius:4px; }
    .otp-prog-dot.done   { background:var(--green); }

    /* FOOTER */
    .auth-footer { background:var(--navy); padding:20px 32px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; flex-shrink:0; }
    .auth-footer-text { font-size:12px; color:rgba(255,255,255,.4); }
    .auth-footer-links { display:flex; gap:18px; }
    .auth-footer-links a { font-size:12px; color:rgba(255,255,255,.4); text-decoration:none; }
    .auth-footer-links a:hover { color:rgba(255,255,255,.7); }

    /* ── RESPONSIVE ── */
    @media(max-width:1000px){
        .reg-layout { grid-template-columns:1fr; }
        .reg-sidebar { display:none; }
        .reg-form-area { padding:24px 20px; }
        .form-grid { grid-template-columns:1fr; }
        .col-full { grid-column:1; }
    }
    @media(max-width:600px){
        .form-section { padding:18px; }
        .otp-box { width:46px; height:54px; font-size:24px; }
        .otp-boxes { gap:7px; }
    }
    @keyframes spin { from{transform:rotate(0deg);} to{transform:rotate(360deg);} }
    </style>
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
        <a href="login" class="btn-signin">Sign In</a>
    </div>
</header>

<?php /* ============================== OTP VERIFICATION VIEW ============================== */ ?>
<?php if($view === 'verify_otp'): ?>

<div class="otp-page">
    <div class="otp-card">

        <!-- Header -->
        <div class="otp-head">
            <!-- Progress dots: step 1 done, step 2 active -->
            <div class="otp-progress">
                <div class="otp-prog-dot done"></div>
                <div class="otp-prog-dot active"></div>
                <div class="otp-prog-dot"></div>
            </div>

            <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:12px;">
                <?php echo mwLogo(34); ?>
                <span style="font-size:17px;font-weight:800;color:#fff;">Midwestern<span style="color:var(--gold);">Bank</span></span>
            </div>

            <div style="font-size:22px;font-weight:800;color:#fff;margin-bottom:6px;">
                Check Your Email
            </div>
            <div style="font-size:13px;color:rgba(255,255,255,.65);line-height:1.6;">
                We sent a 6-digit verification code to<br>
                <strong style="color:var(--gold);"><?php echo htmlspecialchars($masked_email); ?></strong>
            </div>
        </div>

        <!-- Body -->
        <div class="otp-body">

            <?php if(!empty($otp_success_msg)): ?>
                <div class="alert alert-success">
                    <i class="ri-checkbox-circle-line"></i>
                    <span><?php echo $otp_success_msg; ?></span>
                </div>
            <?php endif; ?>

            <?php if(!empty($otp_errors)): ?>
                <div class="alert alert-error">
                    <i class="ri-error-warning-line"></i>
                    <div><?php echo implode('<br>', $otp_errors); ?></div>
                </div>
            <?php endif; ?>

            <!-- Attempts bar -->
            <?php
            $attempts_used = (int)($_SESSION['reg_pending']['otp_attempts'] ?? 0);
            $attempts_pct  = max(0, 100 - ($attempts_used / 5 * 100));
            $bar_color     = $attempts_used >= 3 ? '#ef4444' : ($attempts_used >= 2 ? '#f59e0b' : 'var(--green)');
            ?>
            <div class="attempts-bar">
                <div class="attempts-fill" style="width:<?php echo $attempts_pct; ?>%;background:<?php echo $bar_color; ?>;"></div>
            </div>

            <!-- Timer -->
            <div class="otp-timer">
                <i class="ri-time-line"></i>
                Code expires in:
                <span class="otp-timer-num" id="countdownTimer">
                    <?php
                    $m = floor($otp_expires_in / 60);
                    $s = $otp_expires_in % 60;
                    echo sprintf('%02d:%02d', $m, $s);
                    ?>
                </span>
            </div>

            <!-- Instruction -->
            <div style="background:#f8faff;border:1px solid #dbeafe;border-radius:10px;padding:14px 16px;font-size:13px;color:#1d4ed8;margin-bottom:20px;display:flex;gap:10px;">
                <i class="ri-mail-open-line" style="font-size:18px;flex-shrink:0;"></i>
                <span>
                    Enter the 6-digit code from your email. Check your <strong>Spam</strong> or <strong>Junk</strong> folder if you don't see it.
                </span>
            </div>

            <!-- OTP Form -->
            <form method="POST" id="otpForm">
                <input type="hidden" name="action"     value="verify_otp">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="otp_single" id="otpHidden" value="">

                <!-- 6 Digit Boxes -->
                <div class="otp-boxes">
                    <?php for($i=0;$i<6;$i++): ?>
                        <input type="tel" class="otp-box" id="otp<?php echo $i; ?>"
                               maxlength="1" inputmode="numeric" pattern="[0-9]"
                               autocomplete="<?php echo $i===0?'one-time-code':'off'; ?>"
                               placeholder="·">
                    <?php endfor; ?>
                </div>

                <!-- Verify Button -->
                <button type="submit" id="verifyBtn" class="btn-submit" disabled>
                    <i class="ri-shield-check-fill"></i>
                    Verify &amp; Create Account
                </button>
            </form>

            <!-- Resend -->
            <div class="resend-section">
                Didn't receive the code?
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action"     value="resend_otp">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" class="resend-btn"
                            id="resendBtn"
                            <?php echo $otp_resend_wait > 0 || $otp_resend_count >= 3 ? 'disabled' : ''; ?>>
                        <?php if($otp_resend_count >= 3): ?>
                            Resend limit reached
                        <?php elseif($otp_resend_wait > 0): ?>
                            Resend in <span id="resendTimer"><?php echo $otp_resend_wait; ?>s</span>
                        <?php else: ?>
                            Resend Code
                        <?php endif; ?>
                    </button>
                </form>
                <?php if($otp_resend_count > 0 && $otp_resend_count < 3): ?>
                    <span style="color:var(--muted);font-size:12px;">
                        (<?php echo 3-$otp_resend_count; ?> resend(s) remaining)
                    </span>
                <?php endif; ?>
            </div>

            <!-- Start Over -->
            <div style="text-align:center;margin-top:14px;">
                <a href="register?cancel_otp=1" style="font-size:12px;color:var(--muted);text-decoration:underline;">
                    <i class="ri-arrow-left-line"></i> Go back and edit my information
                </a>
            </div>

        </div>
    </div>
</div>

<script>
// ============================================================
// OTP DIGIT BOXES
// ============================================================
const boxes    = document.querySelectorAll('.otp-box');
const hiddenEl = document.getElementById('otpHidden');
const verifyBtn= document.getElementById('verifyBtn');

function getOtpValue() {
    return Array.from(boxes).map(b=>b.value.replace(/\D/g,'')).join('');
}

function updateHidden() {
    hiddenEl.value = getOtpValue();
}

function checkReady() {
    const val = getOtpValue();
    verifyBtn.disabled = val.length !== 6;
}

function clearBoxErrors() {
    boxes.forEach(b => b.classList.remove('error','success'));
}

boxes.forEach((box, i) => {
    // Input event
    box.addEventListener('input', function() {
        const val = this.value.replace(/\D/g,'');
        this.value = val.slice(-1);
        this.classList.toggle('filled', this.value !== '');
        clearBoxErrors();
        updateHidden();
        checkReady();
        if(this.value && i < 5) boxes[i+1].focus();
    });

    // Keydown — handle backspace
    box.addEventListener('keydown', function(e) {
        if(e.key === 'Backspace' && !this.value && i > 0) {
            boxes[i-1].value = '';
            boxes[i-1].classList.remove('filled');
            boxes[i-1].focus();
            updateHidden();
            checkReady();
        }
        if(e.key === 'ArrowLeft'  && i > 0) boxes[i-1].focus();
        if(e.key === 'ArrowRight' && i < 5) boxes[i+1].focus();
    });

    // Paste support — paste full 6 digits
    box.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasted = (e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
        if(pasted.length >= 6) {
            boxes.forEach((b, idx) => {
                b.value = pasted[idx] || '';
                b.classList.toggle('filled', b.value !== '');
            });
            boxes[5].focus();
            updateHidden();
            checkReady();
        }
    });
});

// Auto-submit when all 6 filled
function checkAutoSubmit() {
    if(getOtpValue().length === 6) {
        setTimeout(() => {
            if(!verifyBtn.disabled) {
                // Visual feedback before submit
                boxes.forEach(b => b.classList.add('success'));
                setTimeout(() => document.getElementById('otpForm').submit(), 400);
            }
        }, 200);
    }
}

document.querySelectorAll('.otp-box').forEach(box => {
    box.addEventListener('input', checkAutoSubmit);
});

// Focus first box
document.addEventListener('DOMContentLoaded', () => {
    boxes[0].focus();
});

// Prevent double submit
document.getElementById('otpForm').addEventListener('submit', function() {
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="ri-loader-4-line" style="animation:spin 1s linear infinite;"></i> Verifying...';
    // Set filled class for visual
    boxes.forEach(b => { if(b.value) b.classList.add('success'); });
});

// ============================================================
// COUNTDOWN TIMER
// ============================================================
let remainingSeconds = <?php echo $otp_expires_in; ?>;
let resendSeconds    = <?php echo $otp_resend_wait; ?>;

const countdownEl = document.getElementById('countdownTimer');
const resendBtn   = document.getElementById('resendBtn');
const resendTimer = document.getElementById('resendTimer');

function pad(n) { return String(n).padStart(2,'0'); }

const mainTimer = setInterval(() => {
    remainingSeconds = Math.max(0, remainingSeconds - 1);
    const m = Math.floor(remainingSeconds / 60);
    const s = remainingSeconds % 60;

    if(countdownEl) {
        countdownEl.textContent = pad(m)+':'+pad(s);
        if(remainingSeconds <= 60) countdownEl.classList.add('urgent');
        else countdownEl.classList.remove('urgent');
    }

    if(remainingSeconds <= 0) {
        clearInterval(mainTimer);
        if(countdownEl) { countdownEl.textContent = 'Expired'; countdownEl.style.color='var(--red)'; }
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="ri-time-line"></i> Code Expired — Resend Code Below';
        verifyBtn.style.background = '#e2e8f0';
        verifyBtn.style.color      = 'var(--muted)';
    }
}, 1000);

// Resend countdown
if(resendSeconds > 0 && resendBtn) {
    resendBtn.disabled = true;
    const resendCountdown = setInterval(() => {
        resendSeconds = Math.max(0, resendSeconds - 1);
        if(resendTimer) resendTimer.textContent = resendSeconds+'s';
        if(resendSeconds <= 0) {
            clearInterval(resendCountdown);
            resendBtn.disabled = false;
            resendBtn.textContent = 'Resend Code';
        }
    }, 1000);
}

// Browser autofill support (OTP from SMS/email autofill)
if('OTPCredential' in window) {
    const ac = new AbortController();
    navigator.credentials.get({ otp: { transport:['sms'] }, signal: ac.signal })
        .then(otp => {
            if(otp && otp.code) {
                const digits = otp.code.replace(/\D/g,'');
                boxes.forEach((b,i) => {
                    b.value = digits[i] || '';
                    b.classList.toggle('filled', b.value !== '');
                });
                updateHidden();
                checkReady();
            }
        }).catch(()=>{});
}
</script>

<?php /* ============================== REGISTER FORM VIEW ============================== */ ?>
<?php else: ?>

<div class="reg-layout">

    <!-- SIDEBAR -->
    <aside class="reg-sidebar">

        <!-- Steps -->
        <div style="margin-bottom:24px;">
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.35);margin-bottom:14px;">
                Application Progress
            </div>

            <!-- Progress dots: step 1 active, step 2 waiting -->
            <div class="otp-progress" style="justify-content:flex-start;gap:6px;margin-bottom:20px;">
                <div class="otp-prog-dot active"></div>
                <div class="otp-prog-dot" style="background:rgba(255,255,255,.15);"></div>
                <div class="otp-prog-dot" style="background:rgba(255,255,255,.15);"></div>
            </div>

            <?php
            $steps = [
                ['Personal Information',      'Full name, email, and contact'],
                ['Email Verification',         'Confirm via 6-digit OTP code'],
                ['KYC Review &amp; Activation','Our team reviews within 1–2 days'],
            ];
            $step_status = ['active','waiting','waiting'];
            foreach($steps as $i => [$lbl,$desc]):
                $st = $step_status[$i];
            ?>
            <div class="step-item">
                <div class="step-num <?php echo $st; ?>">
                    <?php echo $st==='done' ? '<i class="ri-check-line"></i>' : ($i+1); ?>
                </div>
                <div>
                    <div class="step-lbl"><?php echo $lbl; ?></div>
                    <div class="step-desc"><?php echo $desc; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Account Info -->
        <div class="acct-box">
            <div class="acct-box-title"><i class="ri-bank-line"></i> Your New Account</div>
            <?php
            $acct_rows = [
                ['Type',    'Checking Account'],
                ['Currency','USD — US Dollar'],
                ['Fee',     'No Monthly Fee'],
                ['Min. Bal','None Required'],

            ];
            foreach($acct_rows as [$l,$v]):
            ?>
            <div class="acct-row">
                <span class="acct-lbl"><?php echo $l; ?></span>
                <span class="acct-val"><?php echo $v; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- OTP Security Notice -->
        <div style="background:rgba(197,155,61,.08);border:1px solid rgba(197,155,61,.2);border-radius:10px;padding:14px;margin-bottom:20px;">
            <div style="font-size:12px;font-weight:700;color:var(--gold);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                <i class="ri-mail-check-line"></i> Email Verification
            </div>
            <div style="font-size:11px;color:rgba(255,255,255,.5);line-height:1.7;">
                After submitting your application, you will receive a <strong style="color:rgba(255,255,255,.7);">6-digit OTP code</strong> by email.
                Enter this code to confirm your identity and activate your account application.
            </div>
        </div>

        <!-- Trust -->
        <div style="margin-top:auto;padding-top:20px;border-top:1px solid rgba(255,255,255,.07);">
            <?php
            $trust=[
              
                ['ri-lock-fill',         '256-bit AES Encryption'],
                ['ri-mail-check-line',   'Email OTP Verification'],
                ['ri-customer-service-2-line','24/7 Support'],
            ];
            foreach($trust as [$ic,$lbl]):
            ?>
            <div class="trust-item">
                <div class="trust-icon"><i class="<?php echo $ic; ?>"></i></div>
                <span><?php echo $lbl; ?></span>
            </div>
            <?php endforeach; ?>
        </div>

    </aside>

    <!-- FORM AREA -->
    <main class="reg-form-area">
        <div class="reg-form-wrap">

            <!-- Header -->
            <div style="margin-bottom:24px;">
                <div style="font-size:26px;font-weight:800;color:var(--navy);margin-bottom:6px;">
                    Open Your Checking Account
                </div>
                <p style="font-size:14px;color:var(--muted);">
                    Free · No monthly fees · FDIC insured · Takes about 5 minutes.
                    Already a member? <a href="login" style="color:var(--gold);font-weight:700;">Sign In →</a>
                </p>
            </div>

            <?php if(!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="ri-error-warning-line"></i>
                    <div>
                        <strong>Please fix the following:</strong><br>
                        <?php echo implode('<br>', $errors); ?>
                    </div>
                </div>
            <?php endif; ?>

          

            <form method="POST" enctype="multipart/form-data" id="regForm" novalidate>
                <input type="hidden" name="action"        value="submit_registration">
                <input type="hidden" name="csrf_token"    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- ── SECTION 1: PERSONAL INFO ── -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="section-icon"><i class="ri-user-line"></i></div>
                        <div>
                            <div class="section-title">Personal Information</div>
                            <div class="section-num">Section 1 of 4</div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">First Name <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-user-line inp-icon"></i>
                                <input type="text" name="first_name" class="form-input"
                                       placeholder="John"
                                       value="<?php echo htmlspecialchars($old['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-user-line inp-icon"></i>
                                <input type="text" name="last_name" class="form-input"
                                       placeholder="Doe"
                                       value="<?php echo htmlspecialchars($old['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-mail-line inp-icon"></i>
                                <input type="email" name="email" class="form-input"
                                       placeholder="john@example.com"
                                       value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>"
                                       autocomplete="email" required>
                            </div>
                            <div style="font-size:11px;color:var(--muted);margin-top:3px;">
                                <i class="ri-information-line mr-1"></i>
                                The OTP code will be sent here — double-check this.
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-phone-line inp-icon"></i>
                                <input type="tel" name="phone" class="form-input"
                                       placeholder="+1 (555) 019-2834"
                                       value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-calendar-line inp-icon"></i>
                                <input type="date" name="date_of_birth" class="form-input"
                                       value="<?php echo htmlspecialchars($old['date_of_birth'] ?? ''); ?>"
                                       max="<?php echo date('Y-m-d',strtotime('-18 years')); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country / Nationality <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-global-line inp-icon"></i>
                                <select name="nationality" class="form-input" required>
                                    <option value="">Select Country...</option>
                                    <?php
                                    $ctrs=['United States','United Kingdom','Canada','Nigeria','Ghana','Kenya','South Africa','Australia','Germany','France','India','Brazil','Mexico','UAE','Saudi Arabia','Other'];
                                    foreach($ctrs as $c):
                                        $sel = ($old['nationality']??'')===$c?'selected':'';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 2: FINANCIAL ── -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="section-icon"><i class="ri-briefcase-4-line"></i></div>
                        <div>
                            <div class="section-title">Financial &amp; Compliance</div>
                            <div class="section-num">Section 2 of 4 · Required by US Federal Banking Regulations</div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Employment Status <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-briefcase-line inp-icon"></i>
                                <select name="employment_status" class="form-input" required>
                                    <option value="">Select Status...</option>
                                    <?php
                                    $emp=['employed'=>'Employed Full-Time','self_employed'=>'Self-Employed / Business Owner','freelancer'=>'Freelancer / Contractor','student'=>'Student','unemployed'=>'Unemployed','retired'=>'Retired'];
                                    foreach($emp as $v=>$l):
                                        $sel=($old['employment_status']??'')===$v?'selected':'';
                                    ?>
                                    <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Primary Source of Funds <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-money-dollar-circle-line inp-icon"></i>
                                <select name="source_of_funds" class="form-input" required>
                                    <option value="">Select Source...</option>
                                    <?php
                                    $funds=['salary'=>'Salary / Employment','business'=>'Business Income','investment'=>'Investments','inheritance'=>'Inheritance / Gifts','savings'=>'Personal Savings','other'=>'Other'];
                                    foreach($funds as $v=>$l):
                                        $sel=($old['source_of_funds']??'')===$v?'selected':'';
                                    ?>
                                    <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 3: KYC DOCUMENTS ── -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="section-icon"><i class="ri-file-shield-2-line"></i></div>
                        <div>
                            <div class="section-title">Identity Verification (KYC)</div>
                            <div class="section-num">Section 3 of 4 · FinCEN &amp; Bank Secrecy Act Compliance</div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group col-full">
                            <label class="form-label">Government ID Type <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-id-card-line inp-icon"></i>
                                <select name="id_type" class="form-input" required>
                                    <option value="">Select Document Type...</option>
                                    <?php
                                    $id_types=['passport'=>'International Passport','drivers_license'=>"Driver's License",'national_id'=>'National Identity Card'];
                                    foreach($id_types as $v=>$l):
                                        $sel=($old['id_type']??'')===$v?'selected':'';
                                    ?>
                                    <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ID Document <span class="req">*</span></label>
                            <div class="file-upload-box">
                                <input type="file" name="id_document" id="idDoc"
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       onchange="markUploaded(this,'idDocLabel','idDocSize')">
                                <label class="file-label" id="idDocLabel" for="idDoc">
                                    <i class="ri-upload-cloud-2-line" style="font-size:20px;"></i>
                                    <div>
                                        <div style="font-weight:600;">Upload ID Document</div>
                                        <div id="idDocSize" style="font-size:11px;margin-top:2px;">JPG, PNG or PDF · Max 5MB</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Proof of Address <span class="req">*</span></label>
                            <div class="file-upload-box">
                                <input type="file" name="address_document" id="addrDoc"
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       onchange="markUploaded(this,'addrDocLabel','addrDocSize')">
                                <label class="file-label" id="addrDocLabel" for="addrDoc">
                                    <i class="ri-file-text-line" style="font-size:20px;"></i>
                                    <div>
                                        <div style="font-weight:600;">Upload Proof of Address</div>
                                        <div id="addrDocSize" style="font-size:11px;margin-top:2px;">Utility bill / Bank statement · Max 5MB</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info" style="margin-top:14px;margin-bottom:0;">
                        <i class="ri-information-line"></i>
                        <span>Documents are reviewed within 1–2 business days. You'll be notified by email once your account is fully activated.</span>
                    </div>
                </div>

                <!-- ── SECTION 4: SECURITY ── -->
                <div class="form-section">
                    <div class="section-head">
                        <div class="section-icon"><i class="ri-lock-2-line"></i></div>
                        <div>
                            <div class="section-title">Account Security</div>
                            <div class="section-num">Section 4 of 4 · Set a strong password</div>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Password <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-lock-line inp-icon"></i>
                                <input type="password" name="password" id="pwInput" class="form-input"
                                       placeholder="Choose a strong password"
                                       autocomplete="new-password" oninput="checkStrength(this.value)" required>
                                <button type="button" class="pw-toggle" onclick="togglePw('pwInput','pwEye')">
                                    <i class="ri-eye-off-line" id="pwEye"></i>
                                </button>
                            </div>
                            <div class="pw-strength-bar"><div class="pw-strength-fill" id="pwFill"></div></div>
                            <div id="pwLabel" class="pw-strength-lbl" style="color:var(--muted);"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password <span class="req">*</span></label>
                            <div class="input-wrap">
                                <i class="ri-lock-check-line inp-icon"></i>
                                <input type="password" name="confirm_password" id="cpInput" class="form-input"
                                       placeholder="Re-enter your password"
                                       autocomplete="new-password" required>
                                <button type="button" class="pw-toggle" onclick="togglePw('cpInput','cpEye')">
                                    <i class="ri-eye-off-line" id="cpEye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div style="background:var(--light);border-radius:8px;padding:10px 14px;margin-top:8px;font-size:12px;color:var(--muted);">
                        <strong>Requirements:</strong> Min 8 chars · Uppercase · Lowercase · Number · Special character (!@#$%^&*)
                    </div>
                </div>

                <!-- ── TERMS & SUBMIT ── -->
                <div class="form-section">
                    <div class="check-row" style="margin-bottom:16px;">
                        <input type="checkbox" id="termsCheck" required>
                        <label for="termsCheck">
                            I certify that all information provided is accurate and complete. I agree to MidwesternBank's
                            <a href="#" target="_blank">Terms of Service</a>,
                            <a href="#" target="_blank">Privacy Policy</a>, and
                            <a href="#" target="_blank">AML/KYC Compliance Agreement</a>.
                            I understand that providing false information may result in account closure.
                        </label>
                    </div>

                    <div style="background:#f0fff4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;font-size:12px;color:#059669;margin-bottom:16px;display:flex;gap:8px;align-items:flex-start;">
                        <i class="ri-mail-send-line" style="font-size:18px;flex-shrink:0;"></i>
                        <div>
                            <strong>Next step:</strong> After clicking submit, a <strong>6-digit verification code</strong> will be sent to your email.
                            You'll need to enter this code to complete your account registration. Make sure <strong><?php echo !empty($old['email'])?htmlspecialchars($old['email']):'your email address'; ?></strong> is correct before submitting.
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="ri-send-plane-fill"></i>
                        Submit Application &amp; Send Verification Code
                    </button>

                    <div style="text-align:center;margin-top:16px;font-size:14px;color:var(--muted);">
                        Already have an account?
                        <a href="login" style="color:var(--navy);font-weight:700;">Sign In Here</a>
                    </div>
                </div>

            </form>
        </div>
    </main>

</div><!-- /.reg-layout -->

<script>
// ── PASSWORD STRENGTH ──
function checkStrength(val) {
    let s=0;
    if(val.length>=8) s++; if(val.length>=12) s++;
    if(/[A-Z]/.test(val)) s++; if(/[a-z]/.test(val)) s++;
    if(/[0-9]/.test(val)) s++; if(/[\W_]/.test(val)) s++;
    const fill = document.getElementById('pwFill');
    const lbl  = document.getElementById('pwLabel');
    const c=['transparent','#ef4444','#f97316','#eab308','#3b82f6','#22c55e','#059669'];
    const l=['','Very Weak','Weak','Fair','Good','Strong','Very Strong'];
    const i=Math.min(s,6);
    fill.style.width=Math.round((i/6)*100)+'%'; fill.style.background=c[i];
    lbl.textContent=i>0?l[i]:''; lbl.style.color=c[i];
}

// ── TOGGLE PASSWORD ──
function togglePw(inputId, iconId) {
    const inp=document.getElementById(inputId), ico=document.getElementById(iconId);
    if(inp.type==='password'){ inp.type='text'; ico.className='ri-eye-line'; }
    else { inp.type='password'; ico.className='ri-eye-off-line'; }
}

// ── FILE UPLOAD LABEL ──
function markUploaded(input, labelId, sizeId) {
    if(input.files && input.files.length > 0) {
        const f=input.files[0];
        const lbl=document.getElementById(labelId);
        const size=document.getElementById(sizeId);
        lbl.classList.add('uploaded');
        lbl.querySelector('div').firstElementChild.textContent = '✓ '+f.name.substring(0,24)+(f.name.length>24?'…':'');
        size.textContent=(f.size/1024/1024).toFixed(2)+' MB — click to change';
    }
}

// ── TERMS CHECK BEFORE SUBMIT ──
document.getElementById('regForm').addEventListener('submit', function(e) {
    const terms=document.getElementById('termsCheck');
    if(!terms.checked) {
        e.preventDefault();
        const row=terms.closest('.check-row');
        row.style.borderColor='#dc2626'; row.style.background='#fff5f5';
        setTimeout(()=>{ row.style.borderColor=''; row.style.background=''; },3000);
        terms.scrollIntoView({behavior:'smooth',block:'center'});
        return;
    }
    const btn=document.getElementById('submitBtn');
    btn.disabled=true;
    btn.innerHTML='<i class="ri-loader-4-line" style="animation:spin 1s linear infinite;"></i> Sending Verification Code...';
});

// ── SCROLL TO ERROR ──
document.addEventListener('DOMContentLoaded', () => {
    const err=document.querySelector('.alert-error');
    if(err) setTimeout(()=>err.scrollIntoView({behavior:'smooth',block:'center'}),100);
});
</script>

<?php endif; // end register view ?>

<!-- ============================================================ FOOTER ============================================================ -->
<footer class="auth-footer">
    <div class="auth-footer-text">
        &copy; <?php echo date('Y'); ?> MidwesternBank, N.A. All rights reserved.
    </div>
    <div class="auth-footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Accessibility</a>
        <a href="<?php echo defined('BASE_URL') ? BASE_URL.'home-security' : '/home-security'; ?>">Security</a>
    </div>
</footer>

</body>
</html>