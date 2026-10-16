<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';
$page_title = 'My Profile — MidwesternBank';

require_once "front_extra_css/extra_profile.php";

 require_once  'layout/customer-header.php';
 
$acc = getAccountFull($pdo, $user_id);



// ============================================================
// HANDLE PROFILE UPDATE
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_profile'])) {
    try {
        if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security check failed.");
        }
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name']  ?? '');
        $phone      = trim($_POST['phone']       ?? '');

        if(empty($first_name)) throw new Exception("First name is required.");
        if(empty($last_name))  throw new Exception("Last name is required.");
        if(empty($phone))      throw new Exception("Phone number is required.");

        if(!preg_match("/^[a-zA-Z\s\-']{2,50}$/", $first_name)) throw new Exception("Invalid first name.");
        if(!preg_match("/^[a-zA-Z\s\-']{2,50}$/", $last_name))  throw new Exception("Invalid last name.");
        if(!preg_match('/^[0-9\+\-\(\)\s]{7,20}$/', $phone))    throw new Exception("Invalid phone number.");

        $s = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?");
        $s->execute([$first_name, $last_name, $phone, $user_id]);

        

        // Update account name
        $new_name = strtoupper($first_name.' '.$last_name);
        $s = $pdo->prepare("UPDATE accounts SET account_name=? WHERE user_id=?");
        $s->execute([$new_name, $user_id]);

        $_SESSION['user']['first_name'] = $first_name;
        $_SESSION['user']['last_name']  = $last_name;

        $_SESSION['success_message'] = "Profile updated successfully.";
      
    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
   

    // Re-fetch fresh data
    $s = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $s->execute([$user_id]);
    $user = $s->fetch(PDO::FETCH_ASSOC);
    $full_name = htmlspecialchars($user['first_name'].' '.$user['last_name']);
    $initials  = strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1));
}

$id_labels = ['passport'=>'International Passport','national_id'=>'National ID Card','drivers_license'=>"Driver's License"];
$kyc_ok = $user['status'] === 'active';
$balance = getBalance($pdo, $user_id);
?>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><i class="ri-checkbox-circle-line"></i><span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if(!empty($error_message)): ?>
    <div class="alert alert-danger"><i class="ri-error-warning-line"></i><span><?php echo htmlspecialchars($error_message); ?></span></div>
<?php endif; ?>

<!-- Profile Hero -->
<div class="profile-hero">
    <div class="hero-av"><?php echo $initials; ?></div>
    <div style="flex:1;">
        <div class="hero-name"><?php echo $full_name; ?></div>
        <div class="hero-email"><?php echo htmlspecialchars($user['email']); ?></div>
        <div>
            <span class="hero-badge <?php echo $kyc_ok?'hb-active':'hb-pending'; ?>">
                <i class="ri-shield-check-line"></i>
                <?php echo $kyc_ok?'KYC Verified':ucfirst($user['status']); ?>
            </span>
            <span class="hero-badge hb-acc">
                <i class="ri-bank-line"></i> MidwesternBank Current
            </span>
            <span class="hero-badge hb-acc">
                <i class="ri-calendar-line"></i>
                Member since <?php echo date('M Y',strtotime($user['created_at'])); ?>
            </span>
        </div>
    </div>
</div>

<div class="profile-layout">

    <!-- LEFT -->
    <div>
        <!-- Edit Form -->
        <div class="card">
            <div class="card-title"><i class="ri-user-edit-line"></i> Edit Personal Information</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="form_profile" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control"
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control"
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" class="form-control"
                               value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        <small style="color:var(--text-muted);font-size:11px;margin-top:3px;display:block;">
                            Contact support to change your email address
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control"
                               value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="text" class="form-control"
                               value="<?php echo date('d F Y',strtotime($user['date_of_birth'])); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Nationality</label>
                        <input type="text" class="form-control"
                               value="<?php echo htmlspecialchars($user['nationality']); ?>" disabled>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="ri-save-line"></i> Save Changes
                </button>
            </form>
        </div>

        <!-- Account Info -->
        <div class="card">
            <div class="card-title"><i class="ri-bank-line"></i> Account Information</div>
            <div class="acct-banner">
                <div class="ab-lbl">Account Number</div>
                <div class="ab-val"><?php echo htmlspecialchars($user['account_number']); ?></div>
                <div style="font-size:12px;opacity:.7;margin-top:6px;">Balance: <strong><?php echo formatMoney($balance); ?></strong></div>
            </div>
            <?php
            $ai = [
                ['Account Name',    $acc['account_name']   ?? strtoupper($full_name)],
                ['Account Type',    ($acc['account_type']  ?? 'Checking') . ' Account'],
                ['Routing Number',  $acc['routing_number'] ?? '—'],
                ['Wire Routing',    $acc['wire_routing']   ?? '—'],
                ['SWIFT / BIC',     $acc['swift_code']     ?? BANK_SWIFT],
                ['Employment',      ucfirst(str_replace('_',' ',$user['employment_status']))],
                ['Source of Funds', ucfirst(str_replace('_',' ',$user['source_of_funds']))],
                ['Member Since',    date('d F Y',strtotime($user['created_at']))],
                ['Last Login',      !empty($user['last_login']) ? date('d M Y, H:i',strtotime($user['last_login'])) : 'First login'],
            ];
            foreach($ai as [$l,$v]):
            ?>
            <div class="info-row">
                <span class="info-lbl"><?php echo $l; ?></span>
                <span class="info-val" style="font-family:<?php echo in_array($l,['Routing Number','Wire Routing','SWIFT / BIC'])?'monospace':'inherit'; ?>;">
                    <?php echo htmlspecialchars($v); ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT -->
    <div>

        <!-- KYC Documents -->
        <div class="card">
            <div class="card-title"><i class="ri-file-shield-line"></i> Identity Verification (KYC)</div>

            <div class="kyc-doc">
                <div class="kyc-icon" style="background:<?php echo $kyc_ok?'#d1fae5':'#fef3c7'; ?>;color:<?php echo $kyc_ok?'#059669':'#d97706'; ?>;">
                    <i class="ri-id-card-line"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:14px;font-weight:600;"><?php echo htmlspecialchars($id_labels[$user['id_type']]??ucfirst($user['id_type'])); ?></div>
                    <div style="font-size:12px;color:<?php echo $kyc_ok?'var(--green)':'var(--orange)'; ?>;font-weight:500;">
                        <?php echo $kyc_ok?'✓ Approved & Verified':'⏳ Pending Review'; ?>
                    </div>
                </div>
                <i class="<?php echo $kyc_ok?'ri-checkbox-circle-fill':'ri-time-line'; ?>" style="font-size:20px;color:<?php echo $kyc_ok?'var(--green)':'var(--orange)'; ?>;"></i>
            </div>

            <div class="kyc-doc">
                <div class="kyc-icon" style="background:<?php echo $kyc_ok?'#d1fae5':'#fef3c7'; ?>;color:<?php echo $kyc_ok?'#059669':'#d97706'; ?>;">
                    <i class="ri-home-4-line"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:14px;font-weight:600;">Proof of Address</div>
                    <div style="font-size:12px;color:<?php echo $kyc_ok?'var(--green)':'var(--orange)'; ?>;font-weight:500;">
                        <?php echo $kyc_ok?'✓ Utility Bill Verified':'⏳ Pending Review'; ?>
                    </div>
                </div>
                <i class="<?php echo $kyc_ok?'ri-checkbox-circle-fill':'ri-time-line'; ?>" style="font-size:20px;color:<?php echo $kyc_ok?'var(--green)':'var(--orange)'; ?>;"></i>
            </div>

            <?php if($user['status']==='pending_kyc'): ?>
                <div class="alert alert-warning" style="margin-top:10px;font-size:12px;">
                    <i class="ri-information-line"></i>
                    <span>Your documents are under review. This typically takes 1–2 business days.</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-title"><i class="ri-links-line"></i> Quick Settings</div>
            <?php
            $links = [
                ['security',     'ri-shield-line',            'Password & Security',      'Change password, 2FA, login alerts'],
                ['settings',     'ri-settings-3-line',        'Notification Preferences', 'Email, SMS, push alerts'],
                ['beneficiaries','ri-user-star-line',         'Manage Beneficiaries',     'Add or remove saved recipients'],
                ['cards',        'ri-bank-card-line',         'My Cards',                 'View and manage your cards'],
                ['support',      'ri-customer-service-2-line','Contact Support',          'Help with your account'],
            ];
            foreach($links as [$url,$ic,$title,$desc]):
            ?>
            <a href="<?php echo $url; ?>" class="info-row" style="text-decoration:none;color:inherit;cursor:pointer;transition:background .15s;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;background:var(--accent-light);color:var(--accent);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
                        <i class="<?php echo $ic; ?>"></i>
                    </div>
                    <div>
                        <div style="font-size:14px;font-weight:600;"><?php echo $title; ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?php echo $desc; ?></div>
                    </div>
                </div>
                <i class="ri-arrow-right-s-line" style="color:var(--text-muted);font-size:20px;"></i>
            </a>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php include 'layout/customer-footer.php'; ?>