<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

// ============================================================
// AUTH
// ============================================================
if(!isset($_SESSION['user_id'])) {
    header("Location: login?next=transfer"); exit;
}
$user_id = (int)$_SESSION['user_id'];

// CSRF
if(empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// FETCH ACCOUNT & PIN DATA
// ============================================================
$account  = getAccountFull($pdo, $user_id);
$balance  = (float)($account['balance'] ?? 0);
$acc_no   = $account['account_number'] ?? '';
$acc_name = $account['account_name'] ?? trim(($account['first_name']??'').' '.($account['last_name']??''));

$pin_stmt = $pdo->prepare(" SELECT transfer_pin, transfer_pin_attempts,
           transfer_pin_locked_until, transfer_pin_created_at
    FROM users WHERE id=? LIMIT 1
");
$pin_stmt->execute([$user_id]);
$pin_data   = $pin_stmt->fetch(PDO::FETCH_ASSOC);
$has_pin    = !empty($pin_data['transfer_pin']);
$pin_locked = false;
$pin_locked_min = 0;

if(!empty($pin_data['transfer_pin_locked_until'])) {
    $locked_until = strtotime($pin_data['transfer_pin_locked_until']);
    if($locked_until > time()) {
        $pin_locked     = true;
        $pin_locked_min = (int)ceil(($locked_until - time()) / 60);
    } else {
        $pdo->prepare("UPDATE users SET transfer_pin_attempts=0, transfer_pin_locked_until=NULL WHERE id=?")->execute([$user_id]);
    }
}

// Beneficiaries
$beneficiaries = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM beneficiaries WHERE user_id=? ORDER BY name ASC");
    $stmt->execute([$user_id]);
    $beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// ============================================================
// HANDLER
// ============================================================
$step         = (int)($_POST['step'] ?? 1);
$active_tab   = $_POST['active_tab'] ?? 'internal';
$error_message = '';
$done         = false;
$done_data    = [];


// ============================================================
// STATE
// ============================================================
$view          = 'form';   // form | review | success | create_pin | change_pin
$errors        = [];
$transfer_data = null;
$success_data  = null;
$old           = $_POST;   // preserve form values on error

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

// ============================================================
// HANDLE — CREATE / CHANGE PIN
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_pin') {
    $form_type = $_POST['form_type'] ?? 'create';
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))
            throw new Exception("Security check failed. Please refresh and try again.");

        $verify_pw   = $_POST['verify_password'] ?? '';
        $new_pin     = preg_replace('/\D/', '', $_POST['new_pin']     ?? '');
        $confirm_pin = preg_replace('/\D/', '', $_POST['confirm_pin'] ?? '');

        if(empty($verify_pw))
            throw new Exception("Please enter your account password to continue.");

        $s = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $s->execute([$user_id]);
        $pw_hash = $s->fetchColumn();
        if(!$pw_hash || !password_verify($verify_pw, $pw_hash))
            throw new Exception("Incorrect account password. Please try again.");

        if(strlen($new_pin) < 4 || strlen($new_pin) > 6)
            throw new Exception("Transfer PIN must be 4 to 6 digits only.");
        if($new_pin !== $confirm_pin)
            throw new Exception("PINs do not match. Please re-enter both PIN fields.");

        $weak = ['0000','1111','2222','3333','4444','5555','6666','7777','8888','9999',
                 '1234','4321','5678','8765','0000','000000','111111','123456','654321','999999'];
        if(in_array($new_pin, $weak, true))
            throw new Exception("That PIN is too simple. Please choose a more secure PIN that is harder to guess.");
        if(preg_match('/^(\d)\1+$/', $new_pin))
            throw new Exception("PIN cannot be all the same digit (e.g. 1111). Please choose a more unique PIN.");

        $hashed = password_hash($new_pin, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users
            SET transfer_pin=?, transfer_pin_attempts=0,
                transfer_pin_locked_until=NULL,
                transfer_pin_created_at=NOW()
            WHERE id=?
        ")->execute([$hashed, $user_id]);

        $_SESSION['success_message'] = $form_type === 'change'
            ? "✅ Transfer PIN updated successfully! You can now send money securely."
            : "✅ Transfer PIN created successfully! You can now send money.";

        header("Location: transfer");
         exit;

    } catch(Exception $e) {
        $errors[] = $e->getMessage();
        $view = $form_type === 'change' ? 'change_pin' : 'create_pin';
    }
}

// ============================================================
// HANDLE — SHOW PIN FORMS VIA GET
// ============================================================
elseif($action === 'create_pin') {
    $view = 'create_pin';
}
elseif($action === 'change_pin') {
    $view = 'change_pin';
}

// ============================================================
// HANDLE — REVIEW STEP (Step 1 → Step 2)
// ============================================================
elseif($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'review') {
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))
            throw new Exception("Security check failed.");
        if(!$has_pin)
            throw new Exception("You must create a Transfer PIN before sending money.");
        if($pin_locked)
            throw new Exception("Your Transfer PIN is locked for {$pin_locked_min} more minute(s).");

        $type   = in_array($_POST['transfer_type'] ?? '', ['internal','external'])
                  ? $_POST['transfer_type'] : 'internal';
        $amount = (float)str_replace([',','$',' '], '', $_POST['amount'] ?? '0');
        $desc   = substr(trim(strip_tags($_POST['description'] ?? '')), 0, 150);

        if($amount <= 0)
            throw new Exception("Please enter a transfer amount greater than \$0.00.");
        if($amount > 500000)
            throw new Exception("Maximum single transfer is \$500,000.00. Contact support for larger transfers.");
        if($amount > $balance)
            throw new Exception("Insufficient funds. Your available balance is <strong>".formatMoney($balance)."</strong>.");

        $pending = ['type'=>$type, 'amount'=>$amount, 'description'=>$desc];

        if($type === 'internal') {
            $rec_acc = preg_replace('/\s+/', '', trim($_POST['recipient_account'] ?? ''));

            if(empty($rec_acc))
                throw new Exception("Recipient account number is required.");
            if($rec_acc === $acc_no)
                throw new Exception("You cannot transfer funds to your own account.");

            $s = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.status,
                       COALESCE(a.account_name, CONCAT(UPPER(u.first_name),' ',UPPER(u.last_name))) AS display_name,
                       a.account_type
                FROM users u
                LEFT JOIN accounts a ON u.id = a.user_id
                WHERE u.account_number = ? LIMIT 1
            ");
            $s->execute([$rec_acc]);
            $recip = $s->fetch(PDO::FETCH_ASSOC);

            if(!$recip)
                throw new Exception("Account number <strong>".htmlspecialchars($rec_acc)."</strong> was not found in our system. Please verify the number and try again.");
            if($recip['status'] === 'suspended')
                throw new Exception("The recipient's account is currently suspended and cannot receive transfers.");
            if($recip['status'] === 'closed')
                throw new Exception("The recipient's account has been permanently closed.");

            $pending['recipient_account'] = $rec_acc;
            $pending['recipient_id']      = (int)$recip['id'];
            $pending['recipient_name']    = $recip['display_name'];
            $pending['recipient_type']    = $recip['account_type'] ?? 'Checking';

        } else {
            $ext_name    = trim($_POST['ext_recipient_name'] ?? '');
            $ext_account = trim($_POST['ext_account']        ?? '');
            $ext_bank    = trim($_POST['ext_bank_name']      ?? '');
            $ext_routing = preg_replace('/\D/', '', trim($_POST['ext_routing'] ?? ''));
            $ext_swift   = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($_POST['ext_swift'] ?? '')));
            $ext_country = trim($_POST['ext_country']        ?? 'United States');

            if(empty($ext_name))    throw new Exception("Recipient full name is required.");
            if(empty($ext_account)) throw new Exception("Recipient account number or IBAN is required.");
            if(empty($ext_bank))    throw new Exception("Recipient bank name is required.");
            if(empty($ext_routing) && empty($ext_swift))
                throw new Exception("Please provide either a Routing Number (US/domestic) or a SWIFT/BIC code (international).");
            if(!empty($ext_routing) && strlen($ext_routing) !== 9)
                throw new Exception("US Routing numbers must be exactly 9 digits. You entered: ".strlen($ext_routing)." digit(s).");
            if(!empty($ext_swift) && (strlen($ext_swift) < 8 || strlen($ext_swift) > 11))
                throw new Exception("SWIFT/BIC codes must be 8 to 11 characters.");

            $pending['ext_recipient_name'] = $ext_name;
            $pending['ext_account']        = $ext_account;
            $pending['ext_bank_name']      = $ext_bank;
            $pending['ext_routing']        = $ext_routing;
            $pending['ext_swift']          = $ext_swift;
            $pending['ext_country']        = $ext_country;
            $pending['recipient_name']     = $ext_name;
        }

        $_SESSION['transfer_pending'] = $pending;
        $transfer_data = $pending;
        $view = 'review';

    } catch(Exception $e) {
        $errors[] = $e->getMessage();
        $view = 'form';
    }
}

// ============================================================
// HANDLE — CONFIRM & PROCESS TRANSFER (Step 2 → Done)
// ============================================================
elseif($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'confirm_transfer') {
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))
            throw new Exception("Security check failed.");
        if(empty($_SESSION['transfer_pending']))
            throw new Exception("Your transfer session has expired. Please start a new transfer.");

        $entered_pin = preg_replace('/\D/', '', $_POST['transfer_pin'] ?? '');
        if(empty($entered_pin))
            throw new Exception("Please enter your Transfer PIN to confirm.");
        if(strlen($entered_pin) < 4)
            throw new Exception("Transfer PIN must be at least 4 digits.");

        // Fresh PIN data
        $s = $pdo->prepare("
            SELECT transfer_pin, transfer_pin_attempts, transfer_pin_locked_until
            FROM users WHERE id=? LIMIT 1
        ");
        $s->execute([$user_id]);
        $fresh = $s->fetch(PDO::FETCH_ASSOC);

        if(empty($fresh['transfer_pin']))
            throw new Exception("No Transfer PIN configured. Please create one first.");

        if(!empty($fresh['transfer_pin_locked_until'])) {
            $lock = strtotime($fresh['transfer_pin_locked_until']);
            if($lock > time()) {
                $mins = (int)ceil(($lock - time()) / 60);
                throw new Exception("Transfer PIN is locked. Please try again in {$mins} minute(s).");
            }
            $pdo->prepare("UPDATE users SET transfer_pin_attempts=0, transfer_pin_locked_until=NULL WHERE id=?")->execute([$user_id]);
        }

        if(!password_verify($entered_pin, $fresh['transfer_pin'])) {
            $attempts = (int)$fresh['transfer_pin_attempts'] + 1;
            $max = 5;
            if($attempts >= $max) {
                $pdo->prepare("UPDATE users SET transfer_pin_attempts=?,
                    transfer_pin_locked_until=DATE_ADD(NOW(),INTERVAL 30 MINUTE)
                    WHERE id=?
                ")->execute([$attempts, $user_id]);
                throw new Exception("Too many incorrect attempts. Your Transfer PIN is now locked for 30 minutes. Contact support if you need immediate access.");
            }
            $pdo->prepare("UPDATE users SET transfer_pin_attempts=? WHERE id=?")->execute([$attempts, $user_id]);
            $left = $max - $attempts;
            throw new Exception("Incorrect Transfer PIN. You have <strong>{$left}</strong> attempt(s) remaining before your PIN is locked.");
        }

        // ✅ PIN correct — reset attempts
        $pdo->prepare("UPDATE users SET transfer_pin_attempts=0 WHERE id=?")->execute([$user_id]);

        $td      = $_SESSION['transfer_pending'];
        $cur_bal = getBalance($pdo, $user_id);
        $amount  = (float)$td['amount'];

        if($amount > $cur_bal)
            throw new Exception("Insufficient funds. Current balance is ".formatMoney($cur_bal)." which is less than the transfer amount of ".formatMoney($amount).".");

        $s = $pdo->prepare("SELECT account_number FROM users WHERE id=? LIMIT 1");
        $s->execute([$user_id]);
        $sender_acc = (string)$s->fetchColumn();

        $pdo->beginTransaction();
        $ref = generateRef('TRF');
        $desc_final = $td['description'] ?: ($td['type'] === 'internal' ? 'MidwesternBank Internal Transfer' : 'International Wire Transfer');

        if($td['type'] === 'internal') {
            $pdo->prepare("INSERT INTO transfers
                    (sender_id, recipient_id, sender_account, recipient_account,
                     amount, description, reference, transfer_type, status, created_at)
                VALUES (?,?,?,?,?,?,?,'internal','pending',NOW())
            ")->execute([
                $user_id, $td['recipient_id'], $sender_acc, $td['recipient_account'],
                $amount, $desc_final, $ref
            ]);

            recordTransaction(
                $pdo, $user_id, 'transfer', $amount,
                $cur_bal, $cur_bal,
                "Transfer to {$td['recipient_name']} — {$desc_final}",
                $ref, 'pending',
                $td['recipient_name'], $td['recipient_account']
            );
        } else {
            $pdo->prepare("INSERT INTO transfers
                    (sender_id, sender_account, recipient_name_ext, recipient_account,
                     recipient_bank_name, recipient_routing, recipient_bank_swift,
                     recipient_bank_country, amount, description, reference,
                     transfer_type, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,'external','pending',NOW())
            ")->execute([
                $user_id, $sender_acc,
                $td['ext_recipient_name'], $td['ext_account'],
                $td['ext_bank_name'],
                $td['ext_routing'] ?? '',
                $td['ext_swift']   ?? '',
                $td['ext_country'] ?? 'United States',
                $amount, $desc_final, $ref
            ]);

            recordTransaction(
                $pdo, $user_id, 'transfer', $amount,
                $cur_bal, $cur_bal,
                "Wire to {$td['ext_recipient_name']} ({$td['ext_bank_name']}) — {$desc_final}",
                $ref, 'pending',
                $td['ext_recipient_name'], $td['ext_account']
            );
        }

        $pdo->commit();

        $_SESSION['transfer_success'] = [
            'reference'      => $ref,
            'amount'         => formatMoney($amount),
            'recipient_name' => $td['recipient_name'],
            'type'           => $td['type'],
            'description'    => $desc_final,
        ];

        unset($_SESSION['transfer_pending']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: transfer?done=1");
         exit;

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = $e->getMessage();
        $view = 'review';
        $transfer_data = $_SESSION['transfer_pending'] ?? null;
    }
}

// ============================================================
// HANDLE — CANCEL REVIEW
// ============================================================
elseif($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel_review') {
    unset($_SESSION['transfer_pending']);
    header("Location: transfer?new=1"); 
    exit;
}

// ============================================================
// GET STATE DETECTION
// ============================================================
if(isset($_GET['done']) && !empty($_SESSION['transfer_success'])) {
    $view = 'success';
    $success_data = $_SESSION['transfer_success'];
    unset($_SESSION['transfer_success']);
} elseif($view === 'form' && !empty($_SESSION['transfer_pending']) && !isset($_GET['new'])) {
    $transfer_data = $_SESSION['transfer_pending'];
    $view = 'review';
}

// ============================================================
// PAGE SETUP
// ============================================================
$page_title = 'Send Money';

// Determine default tab (from GET or pending session)
$default_tab = $_GET['tab'] ?? ($transfer_data['type'] ?? 'internal');

// Countries
$countries = ['United States','United Kingdom','Canada','Germany','France','Netherlands',
              'Australia','Switzerland','Nigeria','Ghana','Kenya','South Africa',
              'India','UAE','Saudi Arabia','Singapore','Japan','Brazil','Mexico','Other'];

$extra_css = '
/* ============================================================
   TRANSFER PAGE — MIDWESTERNBANK
============================================================ */

/* ── LAYOUT ── */
.tf-layout { display:grid; grid-template-columns:1fr 300px; gap:22px; align-items:start; }
@media(max-width:900px){ .tf-layout{ grid-template-columns:1fr; } }

/* ── CARD ── */
.tf-card { background:#fff; border-radius:14px; padding:26px; box-shadow:var(--shadow); border:1px solid var(--border); }

/* ── STEPS ── */
.tf-steps { display:flex; align-items:center; margin-bottom:26px; }
.tf-step  { display:flex; align-items:center; gap:8px; }
.tf-snum  { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; transition:all .3s; }
.tf-snum.done    { background:var(--green); color:#fff; }
.tf-snum.active  { background:var(--navy); color:#fff; box-shadow:0 0 0 4px rgba(12,35,64,.12); }
.tf-snum.waiting { background:var(--bg); color:var(--text-muted); border:2px solid var(--border); }
.tf-slbl { font-size:12px; font-weight:600; color:var(--text-muted); }
.tf-slbl.active { color:var(--navy); }
.tf-sline { flex:1; height:2px; background:var(--border); margin:0 10px; transition:background .3s; }
.tf-sline.done { background:var(--green); }

/* ── TABS ── */
.tf-tabs { display:flex; gap:5px; background:#f4f7fc; border-radius:10px; padding:5px; margin-bottom:24px; }
.tf-tab  { flex:1; height:42px; border-radius:8px; border:none; font-size:13px; font-weight:600; font-family:"Inter",sans-serif; cursor:pointer; transition:all .2s; background:transparent; color:var(--text-muted); display:flex; align-items:center; justify-content:center; gap:7px; }
.tf-tab i { font-size:15px; }
.tf-tab.active { background:#fff; color:var(--navy); box-shadow:0 2px 8px rgba(12,35,64,.1); }

/* ── FORM ── */
.tf-field { margin-bottom:16px; }
.tf-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--navy); margin-bottom:6px; }
.tf-label small { text-transform:none; font-weight:400; color:var(--text-muted); letter-spacing:0; }
.tf-label .req   { color:var(--red); }
.tf-wrap { position:relative; }
.tf-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); font-size:16px; color:var(--text-muted); pointer-events:none; z-index:1; }
.tf-input {
    width:100%; height:46px; padding:0 14px 0 42px;
    border:1.5px solid var(--border); border-radius:9px;
    font-size:14px; font-family:"Inter",sans-serif;
    color:var(--text); background:#fafafa; outline:none; transition:all .2s;
}
.tf-input:focus   { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(26,75,140,.07); }
.tf-input.no-icon { padding-left:14px; }
.tf-input-lg      { height:58px; font-size:24px; font-weight:700; padding-left:52px; letter-spacing:-.5px; }
.tf-prefix { position:absolute; left:15px; top:50%; transform:translateY(-50%); font-size:22px; font-weight:700; color:var(--navy); z-index:1; }
.tf-input-err { border-color:var(--red) !important; box-shadow:0 0 0 3px rgba(220,38,38,.07) !important; }

/* ── GRID ── */
.tf-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.tf-grid .col-2 { grid-column:1/-1; }
@media(max-width:600px){ .tf-grid{ grid-template-columns:1fr; } .tf-grid .col-2{ grid-column:1; } }

/* ── QUICK AMOUNTS ── */
.quick-amounts { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.quick-amt { padding:4px 12px; border-radius:7px; border:1.5px solid var(--border); background:#fafafa; font-size:12px; font-weight:600; cursor:pointer; color:var(--navy); transition:all .15s; font-family:"Inter",sans-serif; }
.quick-amt:hover { background:var(--navy); color:#fff; border-color:var(--navy); }

/* ── BENEFICIARY PILLS ── */
.bene-wrap  { margin-bottom:18px; }
.bene-lbl   { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.7px; color:var(--text-muted); margin-bottom:8px; }
.bene-pills { display:flex; flex-wrap:wrap; gap:6px; }
.bene-pill  { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border:1.5px solid var(--border); border-radius:20px; cursor:pointer; font-size:12px; font-weight:600; background:#fafafa; color:var(--text); transition:all .18s; max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bene-pill:hover { background:var(--navy); color:#fff; border-color:var(--navy); }
.bene-av { width:22px; height:22px; border-radius:5px; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:700; flex-shrink:0; }

/* ── REVIEW CARD ── */
.rev-card { background:linear-gradient(135deg,var(--navy),#1e3a5f); border-radius:14px; padding:22px; color:#fff; margin-bottom:16px; }
.rev-amount { font-size:40px; font-weight:800; text-align:center; margin:12px 0; letter-spacing:-1px; }
.rev-arrow  { text-align:center; color:var(--gold); font-size:20px; margin:6px 0; }
.rev-row    { display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border-bottom:1px solid rgba(255,255,255,.08); font-size:13px; gap:16px; }
.rev-row:last-child { border-bottom:none; }
.rev-lbl { color:rgba(255,255,255,.5); flex-shrink:0; white-space:nowrap; }
.rev-val { font-weight:700; color:#fff; text-align:right; word-break:break-word; }
.rev-free { margin-top:12px; background:rgba(197,155,61,.12); border:1px solid rgba(197,155,61,.25); border-radius:8px; padding:9px 14px; font-size:12px; color:var(--gold); text-align:center; }

/* ── PIN INPUT ── */
.pin-section { background:#f8f9fc; border-radius:12px; padding:20px; border:1.5px solid var(--border); margin-top:16px; }
.pin-section-title { font-size:14px; font-weight:700; color:var(--navy); margin-bottom:4px; display:flex; align-items:center; gap:8px; }
.pin-dots { display:flex; justify-content:center; gap:10px; margin:16px 0 8px; }
.pin-dot  { width:14px; height:14px; border-radius:50%; border:2px solid #94a3b8; background:transparent; transition:all .18s; }
.pin-dot.filled { background:var(--navy); border-color:var(--navy); }
.pin-keypad { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; max-width:230px; margin:0 auto 14px; }
.pin-key { height:52px; border-radius:10px; border:1.5px solid var(--border); background:#fff; font-size:20px; font-weight:700; color:var(--navy); cursor:pointer; transition:all .14s; font-family:"Inter",sans-serif; display:flex; align-items:center; justify-content:center; user-select:none; }
.pin-key:hover  { background:var(--navy); color:#fff; border-color:var(--navy); }
.pin-key:active { transform:scale(.94); }
.pin-key.blank  { pointer-events:none; opacity:0; background:transparent; border:none; }
.pin-key.del    { color:var(--red); font-size:17px; }
.pin-key.del:hover { background:var(--red); color:#fff; border-color:var(--red); }

/* ── SUCCESS ── */
.succ-icon { width:76px; height:76px; background:#d1fae5; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:34px; color:var(--green); margin:0 auto 18px; animation:popIn .4s cubic-bezier(.175,.885,.32,1.275); }
@keyframes popIn { from{transform:scale(0);opacity:0;} to{transform:scale(1);opacity:1;} }
.succ-amount { font-size:32px; font-weight:800; color:var(--navy); margin:10px 0; text-align:center; }
.succ-ref { font-family:monospace; font-size:13px; color:var(--gold); background:#fef9e7; padding:5px 16px; border-radius:7px; display:inline-block; }

/* ── NO-PIN BANNER ── */
.no-pin-banner { background:linear-gradient(135deg,#fffbeb,#fef3c7); border:2px solid #f59e0b; border-radius:14px; padding:18px 22px; margin-bottom:22px; display:flex; align-items:center; gap:16px; }

/* ── PIN SETUP FORM ── */
.pin-boxes { display:flex; justify-content:center; gap:8px; margin:12px 0; }
.pin-box { width:48px; height:56px; border-radius:10px; border:2px solid var(--border); background:#fafafa; text-align:center; font-size:24px; font-weight:700; font-family:"Inter",sans-serif; color:var(--navy); outline:none; transition:all .18s; -webkit-text-security:disc; caret-color:var(--navy); }
.pin-box:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(26,75,140,.07); }
.pin-box.filled { border-color:var(--navy); background:#f0f4ff; }
.pin-strength { display:flex; gap:3px; height:4px; margin:8px 0 4px; }
.pin-str-seg  { flex:1; background:var(--border); border-radius:2px; transition:background .3s; }

/* ── SIDEBAR ── */
.pin-status-box { border-radius:10px; padding:14px; display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.pin-status-box.active   { background:#f0fff4; border:1px solid #bbf7d0; }
.pin-status-box.inactive { background:#fffbeb; border:1px solid #fde68a; }
.pin-status-box.locked   { background:#fff5f5; border:1px solid #fecaca; }
.pw-eye { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:16px; color:var(--text-muted); padding:0; }
.pw-eye:hover { color:var(--navy); }
.caution-box { background:#fff9f0; border:1px solid #fde68a; border-radius:9px; padding:12px 15px; font-size:13px; color:#92400e; margin-bottom:16px; display:flex; gap:10px; align-items:flex-start; }

/* ── BENEFICIARY SIDEBAR ── */
.beneficiary-card { overflow:hidden; }
.beneficiary-search-wrap { position:relative; margin-bottom:8px; }
.beneficiary-search-wrap > i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:15px; color:var(--text-muted); pointer-events:none; }
.beneficiary-search { width:100%; height:38px; padding:0 12px 0 36px; border:1.5px solid var(--border); border-radius:8px; background:#fafafa; color:var(--text); font-family:"Inter",sans-serif; font-size:12px; outline:none; transition:all .2s; box-sizing:border-box; }
.beneficiary-search:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(26,75,140,.07); }
.beneficiary-count { font-size:10px; color:var(--text-muted); margin:0 2px 8px; }
.beneficiary-list { max-height:400px; overflow-y:auto; display:flex; flex-direction:column; gap:6px; padding-right:2px; }
.beneficiary-list::-webkit-scrollbar { width:4px; }
.beneficiary-list::-webkit-scrollbar-thumb { background:var(--border); border-radius:10px; }
.beneficiary-item { width:100%; display:flex; align-items:center; gap:9px; padding:10px; border:1px solid var(--border); border-radius:10px; background:#fff; color:var(--text); text-align:left; cursor:pointer; transition:all .18s ease; font-family:"Inter",sans-serif; }
.beneficiary-item:hover { border-color:var(--blue); background:#f8fbff; transform:translateY(-1px); }
.beneficiary-item.selected { border-color:var(--navy); background:#f0f5ff; box-shadow:0 0 0 2px rgba(12,35,64,.07); }
.beneficiary-avatar { width:36px; height:36px; min-width:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; }
.beneficiary-avatar.internal { background:#d1fae5; color:#059669; }
.beneficiary-avatar.external { background:#ede9fe; color:#7c3aed; }
.beneficiary-info { flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
.beneficiary-name { font-size:12px; font-weight:700; color:var(--navy); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.beneficiary-account { font-size:10px; color:var(--text-muted); font-family:monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.beneficiary-bank { font-size:10px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.beneficiary-type { flex-shrink:0; padding:3px 7px; border-radius:20px; font-size:9px; font-weight:800; letter-spacing:.3px; }
.beneficiary-type.internal { background:#d1fae5; color:#059669; }
.beneficiary-type.external { background:#dbeafe; color:#2563eb; }
.beneficiary-arrow { flex-shrink:0; color:var(--text-muted); font-size:16px; transition:transform .18s; }
.beneficiary-item:hover .beneficiary-arrow { transform:translateX(2px); color:var(--navy); }
.beneficiary-empty { text-align:center; padding:24px 12px; color:var(--text-muted); }
.beneficiary-empty > i { display:block; font-size:38px; color:var(--border); margin-bottom:8px; }
.beneficiary-empty p { font-size:13px; margin:0 0 12px; }
.beneficiary-no-results { text-align:center; padding:25px 10px; color:var(--text-muted); }
.beneficiary-no-results > i { display:block; font-size:28px; color:var(--border); margin-bottom:6px; }
.beneficiary-no-results div { font-size:12px; font-weight:700; }
.beneficiary-no-results small { font-size:10px; }
@media(max-width:900px){ .beneficiary-list{max-height:320px;} }
#beneficiaryToast { position:fixed; right:24px; bottom:24px; z-index:9999; display:flex; align-items:center; gap:8px; background:var(--navy); color:#fff; padding:11px 16px; border-radius:9px; font-size:12px; font-weight:600; box-shadow:0 8px 25px rgba(0,0,0,.18); opacity:0; visibility:hidden; transform:translateY(10px); transition:all .25s ease; }
#beneficiaryToast i { color:#86efac; font-size:16px; }
#beneficiaryToast.show { opacity:1; visibility:visible; transform:translateY(0); }
@media(max-width:600px) { #beneficiaryToast { right:15px; left:15px; bottom:15px; justify-content:center; } }

';

include 'layout/customer-header.php';

// ─────────────────────────────────────────────
// Helper: render step indicators
// ─────────────────────────────────────────────
function renderSteps(int $current): void {
    $steps = ['Details', 'Review', 'Done'];
    echo '<div class="tf-steps">';
    foreach($steps as $i => $label) {
        $n = $i + 1;
        $cls = $n < $current ? 'done' : ($n === $current ? 'active' : 'waiting');
        $icon = $n < $current ? '<i class="ri-check-line"></i>' : $n;
        echo '<div class="tf-step">';
        echo '<div class="tf-snum '.$cls.'">'.$icon.'</div>';
        echo '<div class="tf-slbl '.($n===$current?'active':'').'">'.$label.'</div>';
        echo '</div>';
        if($i < count($steps)-1) {
            echo '<div class="tf-sline '.($n<$current?'done':'').'"></div>';
        }
    }
    echo '</div>';
}
?>

<?php /* ============================== CREATE PIN VIEW ============================== */ ?>
<?php if($view === 'create_pin' || $view === 'change_pin'): ?>
<?php
$is_change   = ($view === 'change_pin');
$pin_form_title = $is_change ? 'Change Your Transfer PIN' : 'Create Your Transfer PIN';
$pin_form_sub   = $is_change ? 'Update your Transfer PIN to a new one.' : 'Set a private PIN to authorize all your outgoing transfers.';
?>

<div class="page-title"><?php echo $pin_form_title; ?></div>
<div class="page-subtitle"><?php echo $pin_form_sub; ?></div>

<?php if(!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="ri-error-warning-line"></i>
        <span><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></span>
    </div>
<?php endif; ?>

<div style="max-width:540px;margin:0 auto;">
    <div class="tf-card">

        <!-- Brand header -->
        <div style="background:linear-gradient(135deg,var(--navy),#1a4b8c);border-radius:12px;padding:22px;text-align:center;margin-bottom:24px;">
            <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:8px;">
                <?php echo mwLogo(32); ?>
                <span style="font-size:17px;font-weight:800;color:#fff;">Midwestern<span style="color:var(--gold);">Bank</span></span>
            </div>
            <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:4px;"><?php echo $pin_form_title; ?></div>
            <div style="font-size:12px;color:rgba(255,255,255,.6);"><?php echo $pin_form_sub; ?></div>
        </div>

        <form method="POST" id="pinSetupForm">
            <input type="hidden" name="action"    value="set_pin">
            <input type="hidden" name="form_type" value="<?php echo $is_change?'change':'create'; ?>">
            <input type="hidden" name="csrf_token"value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="new_pin"    id="hiddenNewPin"    value="">
            <input type="hidden" name="confirm_pin"id="hiddenConfirmPin"value="">

            <!-- Account password -->
            <div class="tf-field">
                <label class="tf-label">Account Password <span class="req">*</span></label>
                <div class="tf-wrap">
                    <i class="ri-lock-line tf-icon"></i>
                    <input type="password" name="verify_password" id="verifyPw" class="tf-input"
                           placeholder="Enter your login password to continue"
                           autocomplete="current-password" required>
                    <button type="button" class="pw-eye" onclick="toggleEye('verifyPw','verifyPwEye')">
                        <i class="ri-eye-off-line" id="verifyPwEye"></i>
                    </button>
                </div>
            </div>

            <!-- New PIN -->
            <div class="tf-field">
                <label class="tf-label">New Transfer PIN (4–6 digits) <span class="req">*</span></label>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">
                    Do not use your birthday, 1234, or repeated digits. Your PIN is encrypted.
                </div>
                <div class="pin-boxes" id="newPinBoxes">
                    <?php for($i=0;$i<6;$i++): ?>
                        <input type="tel" class="pin-box" id="np<?php echo $i; ?>"
                               maxlength="1" inputmode="numeric" pattern="[0-9]"
                               autocomplete="off" required>
                    <?php endfor; ?>
                </div>
                <!-- Strength bar -->
                <div class="pin-strength">
                    <?php for($i=0;$i<6;$i++): ?>
                        <div class="pin-str-seg" id="pstr<?php echo $i; ?>"></div>
                    <?php endfor; ?>
                </div>
                <div id="pinStrLbl" style="font-size:11px;text-align:center;height:14px;margin-bottom:6px;"></div>
            </div>

            <!-- Confirm PIN -->
            <div class="tf-field">
                <label class="tf-label">Confirm Transfer PIN <span class="req">*</span></label>
                <div class="pin-boxes" id="confirmPinBoxes">
                    <?php for($i=0;$i<6;$i++): ?>
                        <input type="tel" class="pin-box" id="cp<?php echo $i; ?>"
                               maxlength="1" inputmode="numeric" pattern="[0-9]"
                               autocomplete="off" required>
                    <?php endfor; ?>
                </div>
                <div id="pinMatchLbl" style="font-size:12px;text-align:center;height:16px;margin-top:5px;"></div>
            </div>

            <!-- Security notice -->
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1d4ed8;margin-bottom:18px;">
                <i class="ri-shield-check-line mr-1"></i>
                Your PIN is <strong>encrypted</strong> and never stored in plain text. MidwesternBank will <strong>never</strong> ask for your PIN by phone, email, or message.
            </div>

            <div style="display:flex;gap:10px;">
                <a href="transfer" class="btn btn-secondary" style="flex:1;height:46px;display:flex;align-items:center;justify-content:center;">
                    <i class="ri-arrow-left-line mr-1"></i> Cancel
                </a>
                <button type="submit" id="pinSetupBtn" class="btn btn-gold" style="flex:2;height:46px;font-size:14px;" disabled>
                    <i class="ri-shield-keyhole-line mr-1"></i>
                    <?php echo $is_change ? 'Update Transfer PIN' : 'Create Transfer PIN'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php /* ============================== SUCCESS VIEW ============================== */ ?>
<?php elseif($view === 'success' && $success_data): ?>

<div class="page-title">Transfer Submitted</div>
<div class="page-subtitle">Your transfer is pending compliance review</div>

<div style="max-width:560px;margin:0 auto;">
    <div class="tf-card" style="text-align:center;padding:36px;">

        <div class="succ-icon"><i class="ri-checkbox-circle-fill"></i></div>
        <div style="font-size:14px;color:var(--text-muted);">Transfer successfully submitted to</div>
        <div style="font-size:20px;font-weight:700;color:var(--navy);margin:6px 0;">
            <?php echo htmlspecialchars($success_data['recipient_name']); ?>
        </div>
        <div class="succ-amount"><?php echo htmlspecialchars($success_data['amount']); ?></div>
        <div class="succ-ref">Ref: <?php echo htmlspecialchars($success_data['reference']); ?></div>
        <div style="margin-top:6px;font-size:12px;color:var(--text-muted);">
            <?php echo $success_data['type'] === 'internal' ? 'Internal Transfer · MidwesternBank' : 'International Wire Transfer'; ?>
        </div>

        <div style="background:var(--bg);border-radius:12px;padding:18px;margin:22px 0;font-size:13px;text-align:left;">
            <div style="font-weight:700;color:var(--navy);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                <i class="ri-information-line" style="color:var(--gold);"></i> What happens next?
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;color:var(--text-muted);line-height:1.6;">
                <div>✅ Your transfer request has been submitted and recorded</div>
                <div>🔍 Our compliance team will review it within <strong>1–2 business days</strong></div>
                <div>📧 You will receive an email confirmation once it is approved and processed</div>
                <div>🔔 Track progress in your <a href="transactions" style="color:var(--gold);font-weight:600;">Transaction History</a></div>
                <div>💬 Questions? <a href="support" style="color:var(--gold);font-weight:600;">Open a support ticket</a></div>
            </div>
        </div>

        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="transfer?new=1" class="btn btn-primary">
                <i class="ri-add-line"></i> New Transfer
            </a>
            <a href="transactions" class="btn btn-secondary">
                <i class="ri-file-list-3-line"></i> View Transactions
            </a>
            <a href="dashboard" class="btn btn-secondary">
                <i class="ri-dashboard-3-line"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<?php /* ============================== REVIEW VIEW (Step 2) ============================== */ ?>
<?php elseif($view === 'review' && $transfer_data): ?>

<?php
$td     = $transfer_data;
$type   = $td['type'];
$amount = (float)$td['amount'];
$recip  = htmlspecialchars($td['recipient_name'] ?? '—');
?>

<div class="page-title">Review & Confirm Transfer</div>
<div class="page-subtitle">Check all details carefully before entering your PIN</div>

<?php if(!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="ri-error-warning-line"></i>
        <div><?php echo implode('<br>', $errors); ?></div>
    </div>
<?php endif; ?>

<div style="max-width:600px;margin:0 auto;">
    <div class="tf-card">

        <?php renderSteps(2); ?>

        <!-- Review summary card -->
        <div class="rev-card">
            <div style="font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;text-align:center;">
                You are sending
            </div>
            <div class="rev-amount">$<?php echo number_format($amount, 2); ?></div>
            <div class="rev-arrow"><i class="ri-arrow-down-line"></i></div>

            <?php if($type === 'internal'): ?>
                <?php
                $rows = [
                    ['To Account',     htmlspecialchars($td['recipient_account'])],
                    ['Account Name',   htmlspecialchars($td['recipient_name'])],
                    ['Account Type',   htmlspecialchars($td['recipient_type'] ?? 'Checking')],
                    ['Bank',           'MidwesternBank, N.A.'],
                    ['Transfer Type',  'Internal Transfer'],
                    ['From Account',   htmlspecialchars($acc_no)],
                ];
                ?>
            <?php else: ?>
                <?php
                $rows = [
                    ['Recipient',      htmlspecialchars($td['ext_recipient_name'])],
                    ['Account / IBAN', htmlspecialchars($td['ext_account'])],
                    ['Bank',           htmlspecialchars($td['ext_bank_name'])],
                ];
                if(!empty($td['ext_routing'])) $rows[] = ['Routing No.', htmlspecialchars($td['ext_routing'])];
                if(!empty($td['ext_swift']))   $rows[] = ['SWIFT / BIC', htmlspecialchars($td['ext_swift'])];
                $rows[] = ['Country', htmlspecialchars($td['ext_country'])];
                $rows[] = ['Transfer Type', 'International Wire Transfer'];
                $rows[] = ['From Account', htmlspecialchars($acc_no)];
                ?>
            <?php endif; ?>

            <?php if(!empty($td['description'])): ?>
                <?php $rows[] = ['Note', htmlspecialchars($td['description'])]; ?>
            <?php endif; ?>

            <?php foreach($rows as [$l,$v]): ?>
                <div class="rev-row"><span class="rev-lbl"><?php echo $l; ?></span><span class="rev-val"><?php echo $v; ?></span></div>
            <?php endforeach; ?>

            <div class="rev-free">
                <i class="ri-shield-check-line mr-1"></i>
                MidwesternBank charges <strong>no transfer fees</strong> · Processing: 1–2 business days after compliance review
            </div>
        </div>

        <!-- Caution -->
        <div class="caution-box">
            <i class="ri-error-warning-line" style="font-size:18px;color:#f59e0b;flex-shrink:0;margin-top:1px;"></i>
            <span>
                Please verify all details above carefully. MidwesternBank <strong>cannot reverse</strong> a completed transfer.
                If you notice an error, click <em>Edit Transfer</em> below. For fraud concerns, contact support immediately.
            </span>
        </div>

        <!-- PIN ENTRY SECTION -->
        <div class="pin-section">
            <div class="pin-section-title">
                <i class="ri-shield-keyhole-line" style="color:var(--gold);"></i>
                Enter Transfer PIN to Confirm
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:0;">
                Your 4–6 digit Transfer PIN is required to authorize this payment.
            </div>

            <!-- PIN dots display -->
            <div class="pin-dots" id="pinDots">
                <?php for($i=0;$i<6;$i++): ?>
                    <div class="pin-dot" id="pd<?php echo $i; ?>"></div>
                <?php endfor; ?>
            </div>

            <!-- Actual form -->
            <form method="POST" id="confirmForm">
                <input type="hidden" name="action"       value="confirm_transfer">
                <input type="hidden" name="csrf_token"   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="transfer_pin" id="pinHiddenInput" value="">

                <!-- Keypad -->
                <div class="pin-keypad">
                    <?php for($i=1;$i<=9;$i++): ?>
                        <button type="button" class="pin-key" onclick="kPress('<?php echo $i; ?>')"><?php echo $i; ?></button>
                    <?php endfor; ?>
                    <div class="pin-key blank"></div>
                    <button type="button" class="pin-key" onclick="kPress('0')">0</button>
                    <button type="button" class="pin-key del" onclick="kDel()">
                        <i class="ri-delete-back-2-line"></i>
                    </button>
                </div>

                <!-- Attempts info -->
                <?php
                $attempts_left = 5 - (int)($pin_data['transfer_pin_attempts'] ?? 0);
                if($attempts_left < 5 && $attempts_left > 0):
                ?>
                <div style="text-align:center;font-size:12px;color:var(--red);margin-bottom:12px;">
                    <i class="ri-information-line mr-1"></i>
                    <?php echo $attempts_left; ?> attempt(s) remaining before PIN lockout
                </div>
                <?php endif; ?>

                <!-- Submit button -->
                <button type="submit" id="pinConfirmBtn" class="btn btn-gold btn-block" style="height:48px;font-size:14px;margin-bottom:0;" disabled>
                    <i class="ri-shield-keyhole-line"></i> Confirm & Send Money
                </button>
            </form>

            <div style="text-align:center;margin-top:12px;font-size:12px;color:var(--text-muted);">
                Forgot your PIN?
                <a href="transfer?action=change_pin" style="color:var(--gold);font-weight:700;">Reset it here →</a>
            </div>
        </div>

        <!-- Back / Cancel -->
        <div style="margin-top:14px;display:flex;gap:10px;">
            <form method="POST" style="flex:1;">
                <input type="hidden" name="action"     value="cancel_review">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" class="btn btn-secondary btn-block" style="height:44px;">
                    <i class="ri-edit-line mr-1"></i> Edit Transfer
                </button>
            </form>
        </div>

    </div>
</div>

<?php /* ============================== FORM VIEW (Step 1) ============================== */ ?>
<?php else: ?>

<?php if(!$has_pin): ?>
<!-- NO-PIN BANNER -->
<div class="no-pin-banner">
    <div style="width:48px;height:48px;background:#f59e0b;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;flex-shrink:0;">🔐</div>
    <div style="flex:1;">
        <div style="font-size:15px;font-weight:700;color:#92400e;margin-bottom:3px;">Transfer PIN Required</div>
        <div style="font-size:13px;color:#b45309;line-height:1.5;">
            A <strong>Transfer PIN</strong> is required to send money from your MidwesternBank account.
            Your PIN is separate from your login password and protects every outgoing transfer.
        </div>
    </div>
    <a href="transfer?action=create_pin" class="btn btn-gold" style="flex-shrink:0;height:44px;">
        <i class="ri-shield-keyhole-line"></i> Create PIN Now
    </a>
</div>
<?php elseif($pin_locked): ?>
<div class="alert alert-danger">
    <i class="ri-lock-2-line"></i>
    <span>
        <strong>Transfer PIN Locked.</strong>
        Too many incorrect PIN attempts. Please wait <strong><?php echo $pin_locked_min; ?> minute(s)</strong>.
        <a href="support" style="color:inherit;font-weight:700;margin-left:6px;">Contact Support →</a>
    </span>
</div>
<?php endif; ?>

<div class="page-title">Send Money</div>
<div class="page-subtitle">
    Domestic &amp; international transfers · Balance:
    <strong style="color:var(--navy);"><?php echo formatMoney($balance); ?></strong>
</div>

<?php if(!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="ri-error-warning-line"></i>
        <div><?php echo implode('<br>', $errors); ?></div>
    </div>
<?php endif; ?>

<div class="tf-layout">

    <!-- ===== LEFT: MAIN FORM ===== -->
    <div>
        <div class="tf-card">
            <?php renderSteps(1); ?>

            <!-- TABS -->
            <div class="tf-tabs">
                <button class="tf-tab" id="tab-int" type="button" onclick="switchTab('internal')">
                    <i class="ri-bank-line"></i> Internal Transfer
                </button>
                <button class="tf-tab" id="tab-ext" type="button" onclick="switchTab('external')">
                    <i class="ri-global-line"></i> Wire / External
                </button>
            </div>

            <form method="POST" id="transferForm">
                <input type="hidden" name="action"        value="review">
                <input type="hidden" name="csrf_token"    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="transfer_type" id="transferTypeInput" value="<?php echo htmlspecialchars($default_tab); ?>">

                <!-- ══ INTERNAL PANEL ══ -->
                <div id="panel-internal">

                    <?php if(!empty($internal_benes)): ?>
                    <div class="bene-wrap">
                        <div class="bene-lbl">Quick Select from Saved Beneficiaries</div>
                        <div class="bene-pills">
                            <?php foreach($internal_benes as $b):
                                $bn  = htmlspecialchars($b['nickname'] ?? $b['beneficiary_name'] ?? '');
                                $bac = htmlspecialchars($b['account_number'] ?? $b['beneficiary_account'] ?? '');
                                $ini = strtoupper(substr(strip_tags($bn),0,2));
                            ?>
                            <button type="button" class="bene-pill"
                                    onclick="fillInt(<?php echo json_encode($bac); ?>,<?php echo json_encode($bn); ?>)">
                                <div class="bene-av" style="background:#dbeafe;color:#2563eb;"><?php echo $ini; ?></div>
                                <span><?php echo mb_substr(strip_tags($bn),0,14); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="tf-field">
                        <label class="tf-label">Recipient Account Number <span class="req">*</span></label>
                        <div class="tf-wrap">
                            <i class="ri-account-circle-line tf-icon"></i>
                            <input type="text" name="recipient_account" id="recAccount"
                                   class="tf-input" placeholder="Enter 10-digit account number"
                                   maxlength="20" autocomplete="off"
                                   value="<?php echo htmlspecialchars($old['recipient_account'] ?? ''); ?>"
                                   style="font-family:monospace;letter-spacing:2px;font-size:16px;">
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                            <i class="ri-information-line mr-1"></i>
                            Only MidwesternBank account numbers are accepted for internal transfers.
                        </div>
                    </div>
                </div>

                <!-- ══ EXTERNAL PANEL ══ -->
                <div id="panel-external" style="display:none;">

                    <?php if(!empty($external_benes)): ?>
                    <div class="bene-wrap">
                        <div class="bene-lbl">Saved Wire Recipients</div>
                        <div class="bene-pills">
                            <?php foreach($external_benes as $b):
                                $bn = htmlspecialchars($b['nickname'] ?? $b['beneficiary_name'] ?? '');
                                $bdata = json_encode([
                                    'name'    => $b['beneficiary_name'] ?? $b['nickname'] ?? '',
                                    'account' => $b['account_number']   ?? $b['beneficiary_account'] ?? '',
                                    'bank'    => $b['bank_name']         ?? '',
                                    'routing' => $b['routing_number']    ?? '',
                                    'swift'   => $b['swift_code']        ?? '',
                                    'country' => $b['bank_country']      ?? 'United States',
                                ]);
                            ?>
                            <button type="button" class="bene-pill" onclick="fillExt(<?php echo $bdata; ?>)">
                                <div class="bene-av" style="background:#ede9fe;color:#7c3aed;">🌐</div>
                                <span><?php echo mb_substr(strip_tags($bn),0,14); ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="tf-grid">
                        <div class="tf-field col-2">
                            <label class="tf-label">Recipient Full Name <span class="req">*</span></label>
                            <div class="tf-wrap">
                                <i class="ri-user-3-line tf-icon"></i>
                                <input type="text" name="ext_recipient_name" id="extName"
                                       class="tf-input" placeholder="Full name as on bank account"
                                       value="<?php echo htmlspecialchars($old['ext_recipient_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="tf-field">
                            <label class="tf-label">Account No. / IBAN <span class="req">*</span></label>
                            <div class="tf-wrap">
                                <i class="ri-bank-card-line tf-icon"></i>
                                <input type="text" name="ext_account" id="extAccount"
                                       class="tf-input" placeholder="Account number or IBAN"
                                       value="<?php echo htmlspecialchars($old['ext_account'] ?? ''); ?>"
                                       style="font-family:monospace;">
                            </div>
                        </div>
                        <div class="tf-field">
                            <label class="tf-label">Recipient Bank Name <span class="req">*</span></label>
                            <div class="tf-wrap">
                                <i class="ri-building-line tf-icon"></i>
                                <input type="text" name="ext_bank_name" id="extBank"
                                       class="tf-input" placeholder="e.g. JPMorgan Chase, Barclays"
                                       value="<?php echo htmlspecialchars($old['ext_bank_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="tf-field">
                            <label class="tf-label">Routing Number <small>(US/ACH — 9 digits)</small></label>
                            <div class="tf-wrap">
                                <i class="ri-hashtag tf-icon"></i>
                                <input type="text" name="ext_routing" id="extRouting"
                                       class="tf-input" placeholder="e.g. 021000021"
                                       maxlength="9" inputmode="numeric"
                                       value="<?php echo htmlspecialchars($old['ext_routing'] ?? ''); ?>"
                                       style="font-family:monospace;">
                            </div>
                        </div>
                        <div class="tf-field">
                            <label class="tf-label">SWIFT / BIC <small>(International)</small></label>
                            <div class="tf-wrap">
                                <i class="ri-global-line tf-icon"></i>
                                <input type="text" name="ext_swift" id="extSwift"
                                       class="tf-input" placeholder="e.g. CHASUS33"
                                       maxlength="11" style="font-family:monospace;text-transform:uppercase;"
                                       value="<?php echo htmlspecialchars($old['ext_swift'] ?? ''); ?>"
                                       oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </div>
                        <div class="tf-field col-2">
                            <label class="tf-label">Recipient Country</label>
                            <div class="tf-wrap">
                                <i class="ri-map-pin-line tf-icon"></i>

                                <input type="text" name="ext_country" id="extCountry"
                                       class="tf-input" placeholder="United States"
                                       value="<?php echo htmlspecialchars($old['ext_country'] ?? ''); ?>">

                             
                            </div>
                        </div>
                    </div>

                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1d4ed8;margin-bottom:16px;">
                        <i class="ri-information-line mr-1"></i>
                        Provide a <strong>Routing Number</strong> (US domestic ACH) <em>or</em> a <strong>SWIFT/BIC code</strong> (international wire). You can find both on the recipient's bank statement or by contacting their bank.
                    </div>
                </div>

                <!-- ══ AMOUNT (shared) ══ -->
                <div class="tf-field">
                    <label class="tf-label">Transfer Amount (USD) <span class="req">*</span></label>
                    <div class="tf-wrap">
                        <span class="tf-prefix">$</span>
                        <input type="number" name="amount" id="amountInput"
                               class="tf-input tf-input-lg"
                               placeholder="0.00" min="1" max="500000" step="0.01"
                               value="<?php echo htmlspecialchars($old['amount'] ?? ''); ?>">
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-top:5px;">
                        <span style="font-size:12px;color:var(--text-muted);">
                            Available: <strong style="color:var(--green);"><?php echo formatMoney($balance); ?></strong>
                        </span>
                        <span style="font-size:12px;color:var(--text-muted);">Max: $500,000.00</span>
                    </div>
                    <!-- Quick amounts -->
                    <div class="quick-amounts">
                        <?php foreach([100,500,1000,5000,10000,50000] as $q): ?>
                            <button type="button" class="quick-amt"
                                    onclick="document.getElementById('amountInput').value=<?php echo $q; ?>">
                                $<?php echo number_format($q); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ══ DESCRIPTION (shared) ══ -->
                <div class="tf-field">
                    <label class="tf-label">Transfer Note <small>(Optional)</small></label>
                    <div class="tf-wrap">
                        <i class="ri-chat-1-line tf-icon"></i>
                        <input type="text" name="description" class="tf-input"
                               placeholder="e.g. Monthly rent, Invoice #1024, Family support"
                               maxlength="150"
                               value="<?php echo htmlspecialchars($old['description'] ?? ''); ?>">
                    </div>
                </div>

                <!-- ══ SUBMIT ══ -->
                <button type="submit" class="btn btn-primary btn-block"
                        style="height:50px;font-size:15px;margin-top:6px;"
                        <?php echo (!$has_pin || $pin_locked) ? 'disabled' : ''; ?>>
                    <i class="ri-arrow-right-line"></i> Review Transfer
                </button>

                <?php if(!$has_pin): ?>
                    <p style="text-align:center;font-size:12px;color:var(--text-muted);margin-top:10px;">
                        <i class="ri-information-line mr-1"></i>
                        <a href="transfer?action=create_pin" style="color:var(--gold);font-weight:700;">Create a Transfer PIN</a>
                        to enable sending money.
                    </p>
                <?php elseif($pin_locked): ?>
                    <p style="text-align:center;font-size:12px;color:var(--red);margin-top:10px;">
                        <i class="ri-lock-2-line mr-1"></i>
                        PIN locked for <?php echo $pin_locked_min; ?> more minute(s).
                    </p>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <!-- ===== RIGHT: SIDEBAR ===== -->
    <div>

        <!-- PIN STATUS -->
        <div class="tf-card" style="border-top:3px solid <?php echo $pin_locked?'var(--red)':($has_pin?'var(--green)':'#f59e0b'); ?>;">
            <div class="card-title" style="margin-bottom:12px;"><i class="ri-shield-keyhole-line"></i> Transfer PIN</div>

            <?php if($pin_locked): ?>
            <div class="pin-status-box locked">
                <div style="width:40px;height:40px;background:#fee2e2;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">🔒</div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:var(--red);">PIN Locked</div>
                    <div style="font-size:11px;color:var(--text-muted);"><?php echo $pin_locked_min; ?> min remaining</div>
                </div>
            </div>
            <a href="support" class="btn btn-danger btn-block" style="font-size:13px;">
                <i class="ri-customer-service-2-line"></i> Contact Support
            </a>

            <?php elseif($has_pin): ?>
            <div class="pin-status-box active">
                <div style="width:40px;height:40px;background:#d1fae5;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">🔐</div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:var(--green);">PIN Active</div>
                    <div style="font-size:11px;color:var(--text-muted);">
                        <?php echo !empty($pin_data['transfer_pin_created_at']) ? 'Set '.date('d M Y',strtotime($pin_data['transfer_pin_created_at'])) : 'Configured'; ?>
                    </div>
                </div>
            </div>
            <a href="transfer?action=change_pin" class="btn btn-secondary btn-block" style="font-size:13px;">
                <i class="ri-refresh-line"></i> Change Transfer PIN
            </a>

            <?php else: ?>
            <div class="pin-status-box inactive">
                <div style="width:40px;height:40px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">⚠️</div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:#d97706;">No PIN Set</div>
                    <div style="font-size:11px;color:var(--text-muted);">Required to send money</div>
                </div>
            </div>
            <a href="transfer?action=create_pin" class="btn btn-gold btn-block">
                <i class="ri-shield-keyhole-line"></i> Create Transfer PIN
            </a>
            <?php endif; ?>

            <div style="background:var(--bg);border-radius:8px;padding:10px 12px;font-size:11px;color:var(--text-muted);line-height:1.6;margin-top:12px;">
                <i class="ri-information-line mr-1"></i>
                Your PIN is separate from your login password and encrypted. Required for every outgoing transfer.
            </div>
        </div>






        <!-- ACCOUNT SUMMARY -->
       

         <!-- Right: Beneficiaries -->
        <div>
            <div class="tf-card beneficiary-card">
                <div class="card-title" style="margin-bottom:12px;">
                    <i class="ri-user-star-line"></i> Quick Send to Saved
                </div>

                <?php if(empty($beneficiaries)): ?>
                    <div class="beneficiary-empty">
                        <i class="ri-user-add-line"></i>
                        <p>No beneficiaries saved.</p>
                        <a href="beneficiaries" class="btn btn-secondary" style="height:34px;font-size:12px;">Add Beneficiary</a>
                    </div>
                <?php else: ?>
                    <div class="beneficiary-search-wrap">
                        <i class="ri-search-line"></i>
                        <input type="text" class="beneficiary-search"
                               placeholder="Search beneficiary, account or bank..."
                               oninput="filterBeneficiaries(this.value)"
                               autocomplete="off">
                    </div>
                    <div class="beneficiary-count" id="beneficiaryCount">
                        <?php echo count($beneficiaries); ?> <?php echo count($beneficiaries)===1?'beneficiary':'beneficiaries'; ?>
                    </div>
                    <div class="beneficiary-list" id="beneficiaryList">
                        <?php foreach($beneficiaries as $b):
                            $btype = $b['beneficiary_type'] ?? 'internal';
                            $bname = $b['name'] ?? '';
                            $bacc = $b['account_number'] ?? '';
                            $bbank = $b['bank_name'] ?? '';
                            $brtn = $b['routing_number'] ?? '';
                            $bwire = $b['wire_routing'] ?? '';
                            $bswift = $b['swift_code'] ?? '';
                            $bcountry = $b['bank_country'] ?? 'United States';
                            $parts = preg_split('/\s+/', trim($bname));
                            $bi = strtoupper(substr($parts[0] ?? '',0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
                        ?>
                        <button type="button" class="beneficiary-item"
                                data-type="<?php echo htmlspecialchars($btype); ?>"
                                data-name="<?php echo htmlspecialchars($bname); ?>"
                                data-account="<?php echo htmlspecialchars($bacc); ?>"
                                data-bank="<?php echo htmlspecialchars($bbank); ?>"
                                data-routing="<?php echo htmlspecialchars($brtn); ?>"
                                data-wire="<?php echo htmlspecialchars($bwire); ?>"
                                data-swift="<?php echo htmlspecialchars($bswift); ?>"
                                data-country="<?php echo htmlspecialchars($bcountry); ?>"
                                onclick="selectBeneficiary(this)">
                            <span class="beneficiary-avatar <?php echo $btype==='external'?'external':'internal'; ?>"><?php echo htmlspecialchars($bi ?: 'B'); ?></span>
                            <span class="beneficiary-info">
                                <span class="beneficiary-name"><?php echo htmlspecialchars($bname); ?></span>
                                <span class="beneficiary-account"><?php echo htmlspecialchars($bacc); ?></span>
                                <span class="beneficiary-bank"><?php echo htmlspecialchars($bbank); ?></span>
                            </span>
                            <span class="beneficiary-type <?php echo $btype==='external'?'external':'internal'; ?>"><?php echo $btype==='external'?'EXT':'INT'; ?></span>
                            <i class="ri-arrow-right-s-line beneficiary-arrow"></i>
                        </button>
                        <?php endforeach; ?>
                        <div class="beneficiary-no-results" id="beneficiaryNoResults" style="display:none;">
                            <i class="ri-search-line"></i>
                            <div>No beneficiaries found</div>
                            <small>Try a different name, account number or bank.</small>
                        </div>
                    </div>
                    <a href="beneficiaries" class="btn btn-secondary btn-block" style="margin-top:10px;height:34px;font-size:12px;">Manage Beneficiaries</a>
                <?php endif; ?>
            </div>
        </div>

</div><!-- /.tf-layout -->

<?php endif; // end main form view ?>

<?php
$extra_js = "
// ============================================================
// TAB SWITCHING (UI only — actual value in hidden input)
// ============================================================
function switchTab(tab) {
    document.getElementById('panel-internal').style.display = tab==='internal' ? '' : 'none';
    document.getElementById('panel-external').style.display = tab==='external' ? '' : 'none';
    document.getElementById('tab-int').classList.toggle('active', tab==='internal');
    document.getElementById('tab-ext').classList.toggle('active', tab==='external');
    document.getElementById('transferTypeInput').value = tab;
}





// ============================================================
// PIN KEYPAD (Review page)
// ============================================================
let pinVal = '';

function kPress(k) {
    if(pinVal.length >= 6) return;
    pinVal += k;
    updateDots();
    updateHidden();
    checkPinReady();
}
function kDel() {
    pinVal = pinVal.slice(0,-1);
    updateDots();
    updateHidden();
    checkPinReady();
}
function updateDots() {
    for(let i=0;i<6;i++) {
        const d = document.getElementById('pd'+i);
        if(d) d.className = 'pin-dot'+(i<pinVal.length?' filled':'');
    }
}
function updateHidden() {
    const el = document.getElementById('pinHiddenInput');
    if(el) el.value = pinVal;
}
function checkPinReady() {
    const btn = document.getElementById('pinConfirmBtn');
    if(btn) btn.disabled = pinVal.length < 4;
}

// Keyboard support for PIN entry
document.addEventListener('keydown', function(e) {
    // Only intercept if confirm form exists on page
    if(!document.getElementById('confirmForm')) return;
    if(e.key >= '0' && e.key <= '9') { kPress(e.key); e.preventDefault(); }
    if(e.key === 'Backspace')         { kDel(); e.preventDefault(); }
    if(e.key === 'Enter' && pinVal.length >= 4) {
        const btn = document.getElementById('pinConfirmBtn');
        if(btn && !btn.disabled) document.getElementById('confirmForm').submit();
    }
});

// ============================================================
// PIN SETUP BOXES (Create/Change PIN page)
// ============================================================
function pinBoxIn(i, prefix) {
    const el  = document.getElementById(prefix+i);
    if(!el) return;
    const val = el.value.replace(/\D/g,'');
    el.value  = val.slice(-1);
    el.classList.toggle('filled', el.value !== '');
    if(el.value && i < 5) {
        const next = document.getElementById(prefix+(i+1));
        if(next) next.focus();
    }
    if(prefix==='np') updatePinStrength();
    checkPinBoxMatch();
    checkPinSetupReady();
}

function pinBoxKey(e, i, prefix) {
    if(e.key==='Backspace' && !document.getElementById(prefix+i).value && i>0) {
        document.getElementById(prefix+(i-1)).focus();
    }
}

function getBoxPin(prefix) {
    return [0,1,2,3,4,5].map(i=>{
        const el=document.getElementById(prefix+i);
        return el?el.value:'';
    }).join('');
}

function updatePinStrength() {
    const pin = getBoxPin('np');
    const len = pin.length;
    const weak=['0000','1111','2222','3333','4444','5555','1234','4321','5678','000000','123456','654321'];
    let score=0;
    if(len>=4) score++;
    if(len>=5) score++;
    if(len===6) score++;
    if(len>=4 && !/^(\d)\1+$/.test(pin)) score++;
    if(len>=4 && !weak.includes(pin)) score++;
    if(len>=4 && !/^(012|123|234|345|456|567|678|789)/.test(pin)) score++;
    const colors=['#e2e8f0','#ef4444','#f97316','#eab308','#3b82f6','#22c55e','#059669'];
    const labels=['','Very Weak','Weak','Fair','Good','Strong','Very Strong'];
    for(let i=0;i<6;i++) {
        const seg=document.getElementById('pstr'+i);
        if(seg) seg.style.background = i<score ? colors[score] : '#e2e8f0';
    }
    const lbl=document.getElementById('pinStrLbl');
    if(lbl){ lbl.textContent=len>=4?labels[score]:''; lbl.style.color=len>=4?colors[score]:''; }
}

function checkPinBoxMatch() {
    const np  = getBoxPin('np');
    const cp  = getBoxPin('cp');
    const lbl = document.getElementById('pinMatchLbl');
    if(!lbl||!cp) return;
    if(cp===np && np.length>=4) {
        lbl.textContent='✓ PINs match'; lbl.style.color='var(--green)';
    } else if(np.startsWith(cp)||!cp) {
        lbl.textContent=''; lbl.style.color='';
    } else {
        lbl.textContent='✗ PINs do not match'; lbl.style.color='var(--red)';
    }
}

function checkPinSetupReady() {
    const pw = document.getElementById('verifyPw');
    const np = getBoxPin('np');
    const cp = getBoxPin('cp');
    const btn= document.getElementById('pinSetupBtn');
    if(btn && pw) btn.disabled = !(pw.value.trim().length>=4 && np.length>=4 && np===cp);
}

// Wire up PIN box inputs on setup page
(function() {
    for(let i=0;i<6;i++){
        ['np','cp'].forEach(prefix => {
            const el=document.getElementById(prefix+i);
            if(el){
                el.addEventListener('input',  ()=>pinBoxIn(i,prefix));
                el.addEventListener('keydown',e=>pinBoxKey(e,i,prefix));
            }
        });
    }
    const pw=document.getElementById('verifyPw');
    if(pw) pw.addEventListener('input', checkPinSetupReady);
})();

// On form submit, collect PIN boxes into hidden inputs
const setupForm = document.getElementById('pinSetupForm');
if(setupForm) {
    setupForm.addEventListener('submit', function(e) {
        const np = getBoxPin('np');
        const cp = getBoxPin('cp');
        if(np.length < 4) {
            e.preventDefault();
            alert('Please enter at least 4 digits for your Transfer PIN.');
            document.getElementById('np0').focus();
            return;
        }
        if(np !== cp) {
            e.preventDefault();
            alert('PINs do not match. Please re-enter both PIN fields.');
            for(let i=0;i<6;i++){const c=document.getElementById('cp'+i);if(c)c.value='';}
            document.getElementById('cp0').focus();
            return;
        }
        document.getElementById('hiddenNewPin').value     = np;
        document.getElementById('hiddenConfirmPin').value = cp;
    });
}

// ============================================================
// PASSWORD SHOW / HIDE
// ============================================================
function toggleEye(inputId, iconId) {
    const inp = document.getElementById(inputId);
    const ico = document.getElementById(iconId);
    if(inp.type==='password') { inp.type='text';     ico.className='ri-eye-line'; }
    else                       { inp.type='password'; ico.className='ri-eye-off-line'; }
}

// ============================================================
// INIT: set default tab
// ============================================================
(function(){
    const tab = document.getElementById('transferTypeInput');
    if(tab) switchTab(tab.value || 'internal');
})();

// Prevent accidental double-submit on confirm form
const confirmForm = document.getElementById('confirmForm');
if(confirmForm) {
    confirmForm.addEventListener('submit', function() {
        const btn=document.getElementById('pinConfirmBtn');
        if(btn){ btn.disabled=true; btn.innerHTML='<i class=\"ri-loader-4-line\"></i> Processing...'; }
    });
}
";

$extra_js .= <<<'BENEFICIARY_JS'
// ============================================================
// BENEFICIARY SELECTION
// ============================================================
function clearBeneficiarySelections() {
    document.querySelectorAll('.beneficiary-item').forEach(function(item) {
        item.classList.remove('selected');
    });
}

function selectBeneficiary(item) {
    if (!item) return;
    clearBeneficiarySelections();
    item.classList.add('selected');

    const type = item.dataset.type || 'internal';
    const name = item.dataset.name || '';
    const account = item.dataset.account || '';
    const bank = item.dataset.bank || '';
    const routing = item.dataset.routing || '';
    const wire = item.dataset.wire || '';
    const swift = item.dataset.swift || '';
    const country = item.dataset.country || 'United States';

    switchTab(type);

    if (type === 'internal') {
        const accountInput = document.getElementById('recAccount');
        if (accountInput) {
            accountInput.value = account;
            accountInput.focus();
            accountInput.dispatchEvent(new Event('input', {bubbles:true}));
        }
    } else {
        const nameInput = document.getElementById('extName');
        const accountInput = document.getElementById('extAccount');
        const bankInput = document.getElementById('extBank');
        const routingInput = document.getElementById('extRouting');
        const swiftInput = document.getElementById('extSwift');
        const countryInput = document.getElementById('extCountry');

        if (nameInput) { nameInput.value=name; nameInput.dispatchEvent(new Event('input',{bubbles:true})); }
        if (accountInput) { accountInput.value=account; accountInput.dispatchEvent(new Event('input',{bubbles:true})); }
        if (bankInput) { bankInput.value=bank; bankInput.dispatchEvent(new Event('input',{bubbles:true})); }
        if (routingInput) { routingInput.value=routing || wire; routingInput.dispatchEvent(new Event('input',{bubbles:true})); }
        if (swiftInput) { swiftInput.value=swift.toUpperCase(); swiftInput.dispatchEvent(new Event('input',{bubbles:true})); }
        if (countryInput) { countryInput.value=country; countryInput.dispatchEvent(new Event('change',{bubbles:true})); }
    }

    showBeneficiaryToast(name);
}

function filterBeneficiaries(query) {
    const search = String(query || '').trim().toLowerCase();
    const items = document.querySelectorAll('.beneficiary-item');
    const noResults = document.getElementById('beneficiaryNoResults');
    const count = document.getElementById('beneficiaryCount');
    let visible = 0;

    items.forEach(function(item) {
        const name = (item.dataset.name || '').toLowerCase();
        const account = (item.dataset.account || '').toLowerCase();
        const bank = (item.dataset.bank || '').toLowerCase();
        const type = (item.dataset.type || '').toLowerCase();
        const matches = !search || name.includes(search) || account.includes(search) || bank.includes(search) || type.includes(search);
        item.style.display = matches ? 'flex' : 'none';
        if (matches) visible++;
    });

    if (count) count.textContent = visible + (visible === 1 ? ' beneficiary' : ' beneficiaries');
    if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
}

function showBeneficiaryToast(name) {
    let toast = document.getElementById('beneficiaryToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'beneficiaryToast';
        toast.innerHTML = '<i class="ri-check-line"></i><span></span>';
        document.body.appendChild(toast);
    }
    const text = toast.querySelector('span');
    if (text) text.textContent = name ? name + ' selected' : 'Beneficiary selected';
    toast.classList.add('show');
    clearTimeout(window.beneficiaryToastTimer);
    window.beneficiaryToastTimer = setTimeout(function(){ toast.classList.remove('show'); }, 2200);
}
BENEFICIARY_JS;

include 'layout/customer-footer.php';
?>