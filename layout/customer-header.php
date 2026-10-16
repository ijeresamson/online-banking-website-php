<?php

require_once 'config/db-config.php';
require_once 'config/helpers.php';


// ============================================================
// AUTH CHECK
// ============================================================
if(!isset($_SESSION['user_id'])) {
    header("Location:". BASE_URL. "login");
     exit;
}



// ============================================================
// SESSION TIMEOUT — 30 minutes
// ============================================================
if(isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_unset(); session_destroy();
    header("Location:". BASE_URL. "login?reason=timeout");
     exit;
}
$_SESSION['last_activity'] = time();

// ============================================================
// FETCH FRESH USER DATA
// ============================================================
try {
    $s = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $s->execute([$_SESSION['user_id']]);
    $user = $s->fetch(PDO::FETCH_ASSOC);
    if(!$user) { session_destroy();
     header("Location:".BASE_URL. "login");
     exit; 
     }
} catch(PDOException $e) {
    $user = $_SESSION['user'] ?? [];
}

$user_id   = (int)($user['id'] ?? $_SESSION['user_id']);
$full_name = htmlspecialchars(($user['first_name']??'').' '.($user['last_name']??''));
$initials  = strtoupper(substr($user['first_name']??'U',0,1).substr($user['last_name']??'',0,1));
$balance   = getBalance($pdo, $user_id);

// Unread support replies
$unread_support = 0;
try {
    $s = $pdo->prepare(" SELECT COUNT(*) FROM support_tickets
        WHERE user_id=? AND admin_reply IS NOT NULL AND user_seen_reply=0
    ");
    $s->execute([$user_id]);
    $unread_support = (int)$s->fetchColumn();
} catch(PDOException $e) {}

// Current page for active nav
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title).' — MidwesternBank' : 'MidwesternBank Online Banking'; ?></title>
    <meta name="description" content="MidwesternBank Online Banking Portal">
    <meta name="robots" content="noindex, nofollow">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link href="style/customer-head.css" rel="stylesheet">
    
    <style> <?php echo $extra_css ?? ''; ?> </style>
      
</head>
<body>

<div class="shell">

<!-- ============================================================ SIDEBAR ============================================================ -->
<aside class="sidebar" id="sidebar">

    <!-- Brand -->
   <a href="<?php echo BASE_URL;  ?>"> <div class="sb-brand">
        <?php echo mwLogo(38); ?>
        <div>
            <div class="sb-brand-text">Midwestern<span>Bank</span></div>
            <div class="sb-brand-sub">Online Banking Portal</div>
        </div>
    </div> </a>

    <!-- Balance Card -->
    <div class="sb-balance">
        <div class="sb-balance-lbl">Available Balance</div>
        <div class="sb-balance-amt"><?php echo formatMoney($balance); ?></div>
        <div class="sb-balance-acct"><?php echo htmlspecialchars($user['account_number'] ?? '—'); ?></div>
        <div class="sb-balance-status">
            <div class="sb-balance-dot"></div>
            <?php echo ucfirst(str_replace('_',' ',$user['status']??'active')); ?>
        </div>
    </div>

    <!-- MAIN NAV -->
    <div class="sb-section-label">Main Menu</div>
    <nav class="sb-nav">
        <?php
        $nav_main = [
            ['dashboard',    'ri-dashboard-3-line',     'Dashboard'],
            ['receive',      'ri-arrow-down-circle-line','Receive Money'],
            ['transfer',     'ri-send-plane-line',      'Send Money'],
            ['transactions', 'ri-file-list-3-line',     'Transactions'],
            ['statements',   'ri-printer-line',         'Statements'],
        ];
        foreach($nav_main as [$href,$icon,$label]):
            $is_active = ($current_page === basename($href,'.php'));
        ?>
        <a href="<?php echo $href; ?>" class="sb-nav-item <?php echo $is_active?'active':''; ?>">
            <i class="<?php echo $icon; ?>"></i>
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sb-divider"></div>

    <!-- BANKING NAV -->
    <div class="sb-section-label">Banking</div>
    <nav class="sb-nav">
        <?php
        $nav_banking = [
            ['cards',         'ri-bank-card-line',          'My Cards'],
            ['beneficiaries', 'ri-user-star-line',          'Beneficiaries'],
        ];
        foreach($nav_banking as [$href,$icon,$label]):
            $is_active = ($current_page === basename($href,'.php'));
        ?>
        <a href="<?php echo $href; ?>" class="sb-nav-item <?php echo $is_active?'active':''; ?>">
            <i class="<?php echo $icon; ?>"></i>
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sb-divider"></div>

    <!-- ACCOUNT NAV -->
    <div class="sb-section-label">Account</div>
    <nav class="sb-nav">
        <a href="<?php echo BASE_URL; ?>profile"  class="sb-nav-item <?php echo $current_page==='profile'?'active':''; ?>">
            <i class="ri-user-3-line"></i> My Profile
        </a>
        <a href="<?php echo BASE_URL; ?>security" class="sb-nav-item <?php echo $current_page==='security'?'active':''; ?>">
            <i class="ri-shield-check-line"></i> Security
        </a>
       
        <a href="<?php echo BASE_URL; ?>support"  class="sb-nav-item <?php echo $current_page==='support'?'active':''; ?>">
            <i class="ri-customer-service-2-line"></i>
            Support
            <?php if($unread_support > 0): ?>
                <span class="sb-nav-badge"><?php echo $unread_support; ?></span>
            <?php endif; ?>
        </a>
    </nav>

    <!-- Sidebar bottom -->
    <div class="sb-bottom">
        <a href="<?php echo BASE_URL; ?>logout" class="sb-nav-item"
           style="color:rgba(255,100,100,.75);"
           onclick="return confirm('Sign out of MidwesternBank Online Banking?')">
            <i class="ri-logout-box-r-line" style="color:rgba(255,100,100,.75);"></i>
            Sign Out
        </a>
        <div style="margin-top:12px;padding:0 12px;font-size:10px;color:rgba(255,255,255,.25);line-height:1.5;">
            MidwesternBank, N.A. · Member FDIC<br>
            &copy; <?php echo date('Y'); ?> All rights reserved.
        </div>
    </div>

</aside>

<!-- ============================================================ SIDEBAR OVERLAY ============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ============================================================ TOP BAR ============================================================ -->
<header class="topbar">

    <!-- Hamburger (mobile) -->
    <button class="topbar-hamburger" id="hamburgerBtn" onclick="openSidebar()" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>

    <!-- Page Title -->
    <div class="topbar-page">
        <div class="topbar-page-title">
            <?php echo isset($page_title) ? htmlspecialchars(explode('—',$page_title)[0]) : 'Dashboard'; ?>
        </div>
 
    </div>

    <!-- Action Buttons -->
    <div class="topbar-actions">

        <!-- Quick Transfer -->
        <a href="<?php echo BASE_URL; ?>transfer" class="topbar-btn" title="Send Money">
            <i class="ri-send-plane-line"></i>
        </a>

        <!-- Receive -->
        <a href="<?php echo BASE_URL; ?>receive" class="topbar-btn" title="Receive Money">
            <i class="ri-qr-code-line"></i>
        </a>

        <!-- Support / Notifications -->
        <a href="<?php echo BASE_URL; ?>support" class="topbar-btn" title="Support & Notifications">
            <i class="ri-notification-3-line"></i>
            <?php if($unread_support > 0): ?>
                <span class="topbar-notif-dot"></span>
            <?php endif; ?>
        </a>

        <!-- Profile Dropdown -->
        <div class="topbar-profile-wrap">
            <button class="topbar-profile-btn" id="userProfileBtn">
                <div class="topbar-avatar"><?php echo $initials; ?></div>
                <div>
                    <div class="topbar-profile-name"><?php echo $full_name; ?></div>
                    <div class="topbar-profile-role">
                        <?php echo ucfirst(str_replace('_',' ',$user['status']??'Customer')); ?>
                    </div>
                </div>
                <i class="ri-arrow-down-s-line" style="font-size:16px;color:var(--text-muted);margin-left:2px;"></i>
            </button>

            <!-- Dropdown -->
            <div class="profile-dropdown" id="profileDropdown">
                <div class="pd-header">
                    <div class="pd-avatar"><?php echo $initials; ?></div>
                    <div>
                        <div class="pd-name"><?php echo $full_name; ?></div>
                        <div class="pd-email"><?php echo htmlspecialchars($user['email']??''); ?></div>
                    </div>
                </div>

                <div style="padding: 6px 0;">
                    <a href="<?php echo BASE_URL; ?>profile"  class="pd-item"><i class="ri-user-3-line"></i> My Profile</a>
                    <a href="<?php echo BASE_URL; ?>security" class="pd-item"><i class="ri-shield-check-line"></i> Security Center</a>
                    <a href="<?php echo BASE_URL; ?>receive"  class="pd-item"><i class="ri-bank-line"></i> Banking Details</a>
                    <div class="pd-divider"></div>
                    <a href="<?php echo BASE_URL; ?>support"  class="pd-item">
                        <i class="ri-customer-service-2-line"></i>
                        Support
                        <?php if($unread_support > 0): ?>
                            <span style="background:var(--red);color:#fff;font-size:9px;padding:1px 6px;border-radius:10px;margin-left:auto;font-weight:700;"><?php echo $unread_support; ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="pd-divider"></div>
                    <a href="<?php echo BASE_URL; ?>logout" class="pd-item danger"
                       onclick="return confirm('Sign out of MidwesternBank Online Banking?')">
                        <i class="ri-logout-box-r-line"></i> Sign Out
                    </a>
                </div>

                <!-- Footer -->
                <div style="padding:10px 14px;background:var(--bg);border-top:1px solid var(--border);font-size:10px;color:var(--text-muted);text-align:center;">
                    <span style="font-weight:600;color:var(--navy);">MidwesternBank, N.A.</span> · FDIC Insured ·
                    <a href="<?php echo defined('BASE_URL') ? BASE_URL.'home-security' : '#'; ?>" style="color:var(--gold);">Security Center</a>
                </div>
            </div>
        </div>

    </div>
</header>

<!-- ============================================================ MAIN CONTENT ============================================================ -->
<main class="main-wrap">

    <?php
    // KYC warning banner
    if(($user['status']??'') === 'pending_kyc'):
    ?>
    <div class="alert alert-warning">
        <i class="ri-time-line"></i>
        <span>
            <strong>KYC Verification Pending.</strong>
            Your account is under review by our compliance team. Most features are available — transfers will be enabled after approval (1–2 business days).
            <a href="support" style="color:inherit;font-weight:700;margin-left:6px;">Contact Support →</a>
        </span>
    </div>
    <?php endif; ?>

    <?php if(!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="ri-checkbox-circle-line"></i>
            <span><?php echo $_SESSION['success_message']; ?></span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if(!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <i class="ri-error-warning-line"></i>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if(!empty($_SESSION['info_message'])): ?>
        <div class="alert alert-info">
            <i class="ri-information-line"></i>
            <span><?php echo htmlspecialchars($_SESSION['info_message']); ?></span>
        </div>
        <?php unset($_SESSION['info_message']); ?>
    <?php endif; ?>










    