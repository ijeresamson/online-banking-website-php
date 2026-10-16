<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

if(!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
$user_id = (int)$_SESSION['user_id'];

if(empty($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ============================================================
// HANDLE ALL CARD ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))
            throw new Exception("Security check failed. Please refresh and try again.");

        // ── FREEZE / UNFREEZE / BLOCK
        if(in_array($action, ['freeze','unfreeze','block'])) {
            $card_id = (int)($_POST['card_id'] ?? 0);
            if($card_id <= 0) throw new Exception("Invalid card.");

            $s = $pdo->prepare("SELECT * FROM cards WHERE id=? AND user_id=? LIMIT 1");
            $s->execute([$card_id, $user_id]);
            $card = $s->fetch(PDO::FETCH_ASSOC);
            if(!$card) throw new Exception("Card not found.");

            $new_status = ['freeze'=>'frozen','unfreeze'=>'active','block'=>'blocked'][$action];
            $pdo->prepare("UPDATE cards SET status=?, updated_at=NOW() WHERE id=? AND user_id=?")
                ->execute([$new_status, $card_id, $user_id]);

            $last4  = substr(str_replace(['-',' '], '', $card['card_number'] ?? ''), -4);
            $labels = ['frozen'=>'frozen ❄','active'=>'unfrozen ✓','blocked'=>'blocked ✕'];
            $_SESSION['success_message'] = "Card ending in <strong>•••• {$last4}</strong> has been <strong>{$labels[$new_status]}</strong>.";
            header("Location: cards"); exit;
        }

        // ── UPDATE DAILY LIMIT
        if($action === 'update_limit') {
            $card_id   = (int)($_POST['card_id'] ?? 0);
            $new_limit = (float)($_POST['new_limit'] ?? 0);

            if($card_id <= 0) throw new Exception("Invalid card.");

            $s = $pdo->prepare("SELECT * FROM cards WHERE id=? AND user_id=? LIMIT 1");
            $s->execute([$card_id, $user_id]);
            $card = $s->fetch(PDO::FETCH_ASSOC);
            if(!$card) throw new Exception("Card not found.");
            if(($card['status']??'') === 'blocked')
                throw new Exception("Cannot update limit on a blocked card.");

            if($new_limit < 100)     throw new Exception("Minimum daily limit is \$100.00.");
            if($new_limit > 50000)   throw new Exception("Maximum daily limit is \$50,000.00.");

            $pdo->prepare("UPDATE cards SET daily_limit=?, updated_at=NOW() WHERE id=? AND user_id=?")
                ->execute([$new_limit, $card_id, $user_id]);

            $last4 = substr(str_replace(['-',' '], '', $card['card_number'] ?? ''), -4);
            $_SESSION['success_message'] = "Daily limit for card ending in <strong>•••• {$last4}</strong> updated to <strong>\$".number_format($new_limit, 2)."</strong>.";
            header("Location: cards"); exit;
        }

        // ── DELETE CARD
        if($action === 'delete_card') {
            $card_id = (int)($_POST['card_id'] ?? 0);
            $confirm = trim($_POST['delete_confirm'] ?? '');

            if($card_id <= 0) throw new Exception("Invalid card.");
            if(strtoupper($confirm) !== 'DELETE')
                throw new Exception("Please type DELETE exactly to confirm card removal.");

            $s = $pdo->prepare("SELECT * FROM cards WHERE id=? AND user_id=? LIMIT 1");
            $s->execute([$card_id, $user_id]);
            $card = $s->fetch(PDO::FETCH_ASSOC);
            if(!$card) throw new Exception("Card not found.");

            $pdo->prepare("DELETE FROM cards WHERE id=? AND user_id=?")->execute([$card_id, $user_id]);
            $last4 = substr(str_replace(['-',' '], '', $card['card_number'] ?? ''), -4);
            $_SESSION['success_message'] = "Card ending in <strong>•••• {$last4}</strong> has been permanently removed.";
            header("Location: cards"); exit;
        }

        // ── REQUEST NEW CARD
        if($action === 'request_card') {
            $card_type = trim($_POST['card_type'] ?? 'debit');
            $network   = trim($_POST['network']   ?? 'Visa');
            $reason    = trim(strip_tags($_POST['reason'] ?? ''));

            if(!in_array($card_type, ['debit','credit']))
                throw new Exception("Invalid card type.");
            if(!in_array($network, ['Visa','Mastercard','Verve']))
                throw new Exception("Invalid network.");
            if(strlen($reason) > 300)
                throw new Exception("Reason too long.");

            $s = $pdo->prepare("SELECT id FROM card_requests WHERE user_id=? AND status='pending' LIMIT 1");
            $s->execute([$user_id]);
            if($s->fetch())
                throw new Exception("You already have a pending card request. Please wait for it to be processed.");

            $pdo->prepare("INSERT INTO card_requests (user_id,card_type,network,reason,status,created_at) VALUES (?,?,?,?,'pending',NOW())")
                ->execute([$user_id, $card_type, $network, $reason]);

            $_SESSION['success_message'] = "Card request submitted! Our team will review it within 1–2 business days.";
            header("Location: cards?tab=request"); exit;
        }

    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: cards"); exit;
    }
}

// ============================================================
// FETCH DATA
// ============================================================
$page_title = 'My Cards';
$active_tab = $_GET['tab'] ?? 'cards';

$cards = [];
try {
    $s = $pdo->prepare("
        SELECT * FROM cards WHERE user_id=?
        ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'frozen' THEN 2 WHEN 'blocked' THEN 3 ELSE 4 END, created_at DESC
    ");
    $s->execute([$user_id]);
    $cards = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$requests = [];
try {
    $s = $pdo->prepare("SELECT * FROM card_requests WHERE user_id=? ORDER BY created_at DESC");
    $s->execute([$user_id]);
    $requests = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$has_pending = !empty(array_filter($requests, fn($r)=>($r['status']??'')==='pending'));

$account  = getAccountFull($pdo, $user_id);
$acc_name = $account['account_name'] ?? trim(($account['first_name']??'').' '.($account['last_name']??''));

// Helpers
function cGrad(string $s, string $t): string {
    if($s==='blocked') return 'linear-gradient(135deg,#1c0505,#450a0a,#7f1d1d)';
    if($s==='frozen')  return 'linear-gradient(135deg,#1c2b3a,#1e3a5f,#374151)';
    if($t==='credit')  return 'linear-gradient(135deg,#1a0533,#4c1d95,#6d28d9)';
    return 'linear-gradient(135deg,#0c2340,#142d52,#1a4b8c)';
}
function cStatusInfo(string $s): array {
    return match($s) {
        'active'  => ['#d1fae5','#059669','✓ Active'],
        'frozen'  => ['#dbeafe','#2563eb','❄ Frozen'],
        'blocked' => ['#fee2e2','#dc2626','✕ Blocked'],
        default   => ['#f3f4f6','#6b7280','Unknown'],
    };
}
function netHtml(string $n): string {
    return match(strtolower($n)) {
        'mastercard' => '<span style="display:inline-flex;align-items:center;"><span style="width:20px;height:20px;background:#eb001b;border-radius:50%;display:inline-block;"></span><span style="width:20px;height:20px;background:#f79e1b;border-radius:50%;display:inline-block;margin-left:-9px;opacity:.9;"></span></span>',
        'verve'      => '<span style="background:#059669;color:#fff;font-size:10px;font-weight:800;padding:2px 6px;border-radius:4px;">VERVE</span>',
        default      => '<span style="font-size:17px;font-style:italic;font-weight:900;letter-spacing:-.5px;color:#fff;">VISA</span>',
    };
}

$extra_css = '
/* =====================================================
   CARDS PAGE — MIDWESTERNBANK
===================================================== */
.c-tabs { display:flex; gap:5px; background:#f4f7fc; border-radius:10px; padding:5px; margin-bottom:24px; }
.c-tab  { flex:1; height:40px; border-radius:8px; border:none; font-size:13px; font-weight:600;
          font-family:"Inter",sans-serif; cursor:pointer; transition:all .2s; background:transparent;
          color:var(--text-muted); display:flex; align-items:center; justify-content:center; gap:5px; }
.c-tab.active { background:#fff; color:var(--navy); box-shadow:0 2px 8px rgba(12,35,64,.1); }

/* ── Card Grid ── */
.card-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:24px; }
@media(max-width:660px){ .card-grid{ grid-template-columns:1fr; } }

/* ── Bank Card Visual ── */
.bank-card-wrap { display:flex; flex-direction:column; }
.bank-card {
    position:relative; border-radius:20px; padding:24px; color:#fff;
    min-height:190px; overflow:hidden;
    display:flex; flex-direction:column; justify-content:space-between;
    box-shadow:0 12px 32px rgba(12,35,64,.22); transition:transform .25s, box-shadow .25s; user-select:none;
}
.bank-card:hover  { transform:translateY(-4px); box-shadow:0 20px 44px rgba(12,35,64,.3); }
.bank-card.frozen { filter:grayscale(.55) brightness(.9); }
.bank-card.blocked{ filter:grayscale(1) brightness(.8); }
.bc-deco { position:absolute; border-radius:50%; background:rgba(255,255,255,.07); pointer-events:none; }

/* Chip */
.card-chip { width:40px; height:30px; border-radius:6px;
             background:linear-gradient(135deg,#d4a843,#f0cc6d,#c59b3d); margin:14px 0 0; }
.chip-inner { width:100%;height:100%;display:grid;grid-template-rows:1fr 1fr 1fr;gap:2px;padding:5px 7px; }
.chip-row   { background:rgba(0,0,0,.18); border-radius:1px; }

.card-num { font-family:"Courier New",monospace; font-size:18px; font-weight:700; letter-spacing:3px; margin:14px 0 6px; }
@media(max-width:360px){ .card-num{ font-size:14px; letter-spacing:1.5px; } }

/* ── Footer Panel ── */
.card-footer-panel {
    background:#fff; border:1px solid var(--border); border-top:none;
    border-radius:0 0 18px 18px; padding:14px 16px;
}

/* ── CVV Section ── */
.cvv-row {
    display:flex; align-items:center; justify-content:space-between;
    background:#f4f7fc; border-radius:9px; padding:10px 14px; margin-bottom:12px;
    border:1px solid var(--border);
}
.cvv-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--text-muted); }
.cvv-value { font-family:"Courier New",monospace; font-size:18px; font-weight:800; color:var(--navy); letter-spacing:4px; }
.cvv-toggle { background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:16px; padding:0; margin-left:8px; transition:color .2s; flex-shrink:0; }
.cvv-toggle:hover { color:var(--navy); }
.cvv-mask { font-family:"Courier New",monospace; font-size:18px; font-weight:800; color:var(--text-muted); letter-spacing:4px; }

/* ── Limit Section ── */
.limit-row {
    display:flex; align-items:center; justify-content:space-between;
    padding:10px 0; border-bottom:1px solid var(--border); margin-bottom:10px;
}
.limit-label { font-size:11px; color:var(--text-muted); font-weight:600; }
.limit-val   { font-size:14px; font-weight:800; color:var(--navy); }
.limit-edit-btn {
    background:none; border:1.5px solid var(--border); cursor:pointer;
    border-radius:7px; padding:3px 10px; font-size:11px; font-weight:700;
    color:var(--navy); font-family:"Inter",sans-serif; transition:all .18s;
}
.limit-edit-btn:hover { background:var(--navy); color:#fff; border-color:var(--navy); }

/* ── Action Buttons ── */
.card-actions { display:flex; gap:6px; flex-wrap:wrap; }
.cact { flex:1; min-width:68px; height:36px; border-radius:9px; border:none; font-size:11px;
        font-weight:700; font-family:"Inter",sans-serif; cursor:pointer; transition:all .14s;
        display:flex; align-items:center; justify-content:center; gap:4px; text-decoration:none; white-space:nowrap; }
.cact i { font-size:13px; }
.cact-freeze   { background:#dbeafe; color:#2563eb; }
.cact-freeze:hover  { background:#bfdbfe; }
.cact-unfreeze { background:#d1fae5; color:#059669; }
.cact-unfreeze:hover{ background:#a7f3d0; }
.cact-block    { background:#fee2e2; color:#dc2626; }
.cact-block:hover   { background:#fecaca; }
.cact-delete   { background:#fff1f2; color:#e11d48; border:1px solid #fecdd3; }
.cact-delete:hover  { background:#ffe4e6; border-color:#fda4af; }
.cact-support  { background:#f3f4f6; color:#64748b; }
.cact-support:hover { background:#e5e7eb; }

/* ── Status Pill ── */
.status-pill { display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700; }

/* ── MODALS ── */
.modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(12,35,64,.65);
    z-index:700; align-items:center; justify-content:center; backdrop-filter:blur(4px);
}
.modal-overlay.show { display:flex; }
.modal-box { background:#fff; border-radius:18px; width:100%; margin:16px; overflow:hidden;
             animation:popIn .25s ease; box-shadow:0 24px 64px rgba(0,0,0,.22); }
@keyframes popIn { from{transform:scale(.9);opacity:0;} to{transform:scale(1);opacity:1;} }

/* ── Limit Slider ── */
.limit-slider {
    width:100%; -webkit-appearance:none; appearance:none; height:6px;
    border-radius:3px; background:linear-gradient(to right, var(--navy) var(--pct,20%), var(--border) var(--pct,20%));
    outline:none; margin:12px 0;
}
.limit-slider::-webkit-slider-thumb {
    -webkit-appearance:none; appearance:none;
    width:22px; height:22px; border-radius:50%;
    background:var(--navy); cursor:pointer;
    box-shadow:0 2px 8px rgba(12,35,64,.3);
    border:3px solid #fff;
}
.limit-slider::-moz-range-thumb {
    width:22px; height:22px; border-radius:50%;
    background:var(--navy); cursor:pointer; border:3px solid #fff;
    box-shadow:0 2px 8px rgba(12,35,64,.3);
}
.limit-marks { display:flex; justify-content:space-between; font-size:10px; color:var(--text-muted); margin-top:4px; }

/* ── Delete Confirm Input ── */
.del-confirm-input {
    width:100%; height:46px; padding:0 14px; border:2px solid #fecaca; border-radius:9px;
    font-size:15px; font-weight:700; font-family:"Courier New",monospace;
    text-align:center; outline:none; transition:all .2s;
    background:#fff5f5; color:#dc2626; letter-spacing:3px;
}
.del-confirm-input:focus { border-color:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,.1); }
.del-confirm-input.valid { border-color:#059669; background:#f0fff4; color:#059669; box-shadow:0 0 0 3px rgba(5,150,105,.1); }

/* Request form */
.req-form  { background:#fff; border-radius:14px; border:1px solid var(--border); box-shadow:var(--shadow); padding:24px; }
.type-opts { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.type-opt  { border:2px solid var(--border); border-radius:12px; padding:16px 14px; cursor:pointer; transition:all .18s; display:flex; align-items:center; gap:12px; background:#fafafa; }
.type-opt:hover { border-color:var(--navy); }
.type-opt.selected { border-color:var(--navy); background:#eff3ff; }
.type-opt input { display:none; }
.net-opts  { display:flex; gap:10px; flex-wrap:wrap; }
.net-opt   { flex:1; min-width:80px; border:2px solid var(--border); border-radius:10px; padding:14px 10px; text-align:center; cursor:pointer; transition:all .18s; background:#fafafa; }
.net-opt:hover { border-color:var(--navy); }
.net-opt.selected { border-color:var(--navy); background:#eff3ff; }
.net-opt input { display:none; }
.net-icon  { font-size:26px; display:block; margin-bottom:4px; }
.net-name  { font-size:12px; font-weight:700; color:var(--navy); }

/* History rows */
.req-row { display:flex; align-items:center; gap:14px; padding:14px 16px; border:1px solid var(--border); border-radius:12px; margin-bottom:10px; background:#fff; transition:box-shadow .18s; }
.req-row:hover { box-shadow:0 2px 12px rgba(12,35,64,.07); }
.req-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }

/* Empty state */
.empty-state { text-align:center; padding:48px 20px; }
.empty-icon  { width:72px; height:72px; background:var(--bg); border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:32px; color:var(--border); margin:0 auto 16px; }

/* Mobile */
@media(max-width:600px){
    .bank-card{ min-height:170px; padding:18px; } .card-num{ font-size:14px; letter-spacing:1px; }
    .cact{ font-size:10px; min-width:60px; height:34px; }
    .type-opts{ grid-template-columns:1fr; } .card-footer-panel{ padding:12px; }
    .req-form{ padding:16px; } .c-tab{ font-size:11px; }
}
@media(max-width:380px){ .c-tabs{ gap:3px; padding:4px; } .card-grid{ gap:16px; } }

/* CVV security note */
.cvv-warning { font-size:10px; color:#d97706; margin-top:4px; display:flex; align-items:center; gap:4px; }
';

include 'layout/customer-header.php';
?>

<!-- FLASH MESSAGES -->
<?php if(!empty($_SESSION['success_message'])): ?>
<div class="alert alert-success">
    <i class="ri-checkbox-circle-line"></i>
    <span><?php echo $_SESSION['success_message']; ?></span>
</div>
<?php unset($_SESSION['success_message']); endif; ?>
<?php if(!empty($_SESSION['error_message'])): ?>
<div class="alert alert-danger">
    <i class="ri-error-warning-line"></i>
    <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
</div>
<?php unset($_SESSION['error_message']); endif; ?>

<div class="page-title">My Cards</div>
<div class="page-subtitle">Manage your MidwesternBank debit &amp; credit cards</div>

<!-- TABS -->
<div class="c-tabs">
    <button class="c-tab <?php echo $active_tab==='cards'?'active':''; ?>" onclick="location.href='cards?tab=cards'">
        <i class="ri-bank-card-line"></i> My Cards
        <?php if(count($cards)>0): ?><span style="background:var(--navy);color:#fff;padding:1px 7px;border-radius:9px;font-size:10px;"><?php echo count($cards); ?></span><?php endif; ?>
    </button>
    <button class="c-tab <?php echo $active_tab==='request'?'active':''; ?>" onclick="location.href='cards?tab=request'">
        <i class="ri-add-circle-line"></i> Request Card
    </button>
    <button class="c-tab <?php echo $active_tab==='history'?'active':''; ?>" onclick="location.href='cards?tab=history'">
        <i class="ri-file-list-line"></i> Requests
        <?php if($has_pending): ?><span style="background:#f59e0b;color:#fff;padding:1px 7px;border-radius:9px;font-size:10px;">1</span><?php endif; ?>
    </button>
</div>

<!-- ==================== MY CARDS TAB ==================== -->
<?php if($active_tab === 'cards'): ?>

<?php if(empty($cards)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon"><i class="ri-bank-card-line"></i></div>
        <div style="font-size:16px;font-weight:700;color:var(--navy);margin-bottom:8px;">No Cards Linked</div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:22px;max-width:360px;margin-left:auto;margin-right:auto;line-height:1.6;">
            You don't have any cards yet. Request a Visa or Mastercard debit card to get started.
        </p>
        <a href="cards?tab=request" class="btn btn-primary" style="height:44px;padding:0 28px;">
            <i class="ri-add-line"></i> Request Your First Card
        </a>
    </div>
</div>

<?php else: ?>

<div class="card-grid">
    <?php foreach($cards as $c):
        $status   = $c['status']    ?? 'active';
        $ctype    = $c['card_type'] ?? 'debit';
        $network  = $c['network']   ?? 'Visa';
        $raw_num  = $c['card_number'];
        $last4    = substr($raw_num, -4);
        $expiry   = !empty($c['expiry_date']) ? date('m/Y', strtotime($c['expiry_date'])) : '••/••';
        $is_exp   = !empty($c['expiry_date']) && strtotime($c['expiry_date']) < time();
        $limit    = (float)($c['daily_limit'] ?? 1000);
        $issued   = !empty($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '—';
        $gradient = cGrad($status, $ctype);
        [$stbg,$stcol,$stlbl] = cStatusInfo($status);

        // CVV: plain if ≤ 4 chars, masked/unknown if hashed
        $cvv_raw   = $c['cvv'] ?? '';
        $has_cvv   = strlen($cvv_raw) <= 4 && strlen($cvv_raw) >= 3;
        $cvv_display = $has_cvv ? $cvv_raw : '•••';
        $cvv_input_id = 'cvvVal'.$c['id'];
        $cvv_icon_id  = 'cvvEye'.$c['id'];

        $limit_pct = min(100, max(0, (($limit - 100) / (50000 - 100)) * 100));
    ?>
    <div class="bank-card-wrap">

        <!-- ── VISUAL CARD ── -->
        <div class="bank-card <?php echo $status; ?>" style="background:<?php echo $gradient; ?>;">
            <div class="bc-deco" style="width:220px;height:220px;top:-80px;right:-60px;"></div>
            <div class="bc-deco" style="width:140px;height:140px;bottom:-50px;left:-40px;"></div>

            <!-- Top -->
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                    <div style="font-size:15px;font-weight:800;letter-spacing:-.3px;">
                        Midwestern<span style="color:#c59b3d;">Bank</span>
                    </div>
                    <div style="font-size:10px;opacity:.5;margin-top:2px;text-transform:uppercase;letter-spacing:.8px;">
                        <?php echo ucfirst($ctype); ?> Card
                    </div>
                </div>
                <!-- Contactless -->
                <div style="display:flex;flex-direction:column;gap:2px;align-items:center;opacity:.6;">
                    <?php for($r=1;$r<=3;$r++): ?><div style="border:1.5px solid rgba(255,255,255,.8);border-radius:50%;width:<?php echo $r*9; ?>px;height:<?php echo $r*9; ?>px;border-left-color:transparent;transform:rotate(45deg);"></div><?php endfor; ?>
                </div>
            </div>

            <!-- Chip -->
            <div class="card-chip"><div class="chip-inner"><?php for($i=0;$i<3;$i++): ?><div class="chip-row"></div><?php endfor; ?></div></div>

            <!-- Card Number -->
            <div class="card-num"> <?php echo htmlspecialchars($raw_num); ?></div>

            <!-- Bottom -->
            <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:8px;">
                <div>
                    <div style="font-size:9px;opacity:.45;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;">Card Holder</div>
                    <div style="font-size:12px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?php echo htmlspecialchars($acc_name); ?>
                    </div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:9px;opacity:.45;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;">Valid Thru</div>
                    <div style="font-size:13px;font-family:monospace;font-weight:700;">
                        <?php echo $expiry; ?>
                        <?php if($is_exp): ?><span style="font-size:8px;background:#dc2626;color:#fff;padding:1px 4px;border-radius:3px;font-family:Inter,sans-serif;margin-left:3px;">EXPIRED</span><?php endif; ?>
                    </div>
                </div>
                <div><?php echo netHtml($network); ?></div>
            </div>
        </div>

        <!-- ── FOOTER PANEL ── -->
        <div class="card-footer-panel">

            <!-- Status & Meta row -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:6px;">
                <span class="status-pill" style="background:<?php echo $stbg; ?>;color:<?php echo $stcol; ?>;"><?php echo $stlbl; ?></span>
                <span style="font-size:11px;color:var(--text-muted);">Issued <?php echo $issued; ?></span>
            </div>

            <!-- CVV Section -->
            <div class="cvv-row">
                <div>
                    <div class="cvv-label">Security Code (CVV)</div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:3px;">
                        <?php if($has_cvv): ?>
                        <span class="cvv-mask" id="<?php echo $cvv_input_id; ?>_mask">• • •</span>
                        <span class="cvv-value" id="<?php echo $cvv_input_id; ?>_val" style="display:none;">
                            <?php echo htmlspecialchars($cvv_display); ?>
                        </span>
                        <?php else: ?>
                        <span class="cvv-mask">— — —</span>
                        <?php endif; ?>
                    </div>
                    <?php if($has_cvv): ?>
                    <div class="cvv-warning">
                        <i class="ri-shield-keyhole-line"></i>
                        Never share your CVV with anyone
                    </div>
                    <?php endif; ?>
                </div>
                <?php if($has_cvv): ?>
                <button class="cvv-toggle" id="<?php echo $cvv_icon_id; ?>"
                        onclick="toggleCVV('<?php echo $cvv_input_id; ?>','<?php echo $cvv_icon_id; ?>')"
                        title="Show/Hide CVV">
                    <i class="ri-eye-off-line"></i>
                </button>
                <?php else: ?>
                <span style="font-size:11px;color:var(--text-muted);max-width:100px;text-align:right;line-height:1.4;">
                    Contact support for CVV
                </span>
                <?php endif; ?>
            </div>

            <!-- Limit Section -->
            <div class="limit-row">
                <div>
                    <div class="limit-label">Daily Transaction Limit</div>
                    <div class="limit-val">$<?php echo number_format($limit, 2); ?> / day</div>
                </div>
                <?php if($status !== 'blocked'): ?>
                <button class="limit-edit-btn"
                        onclick="openLimitModal(<?php echo $c['id']; ?>, <?php echo $limit; ?>, '<?php echo htmlspecialchars($last4); ?>')">
                    <i class="ri-edit-line"></i> Edit Limit
                </button>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="card-actions">
                <?php if($status === 'active'): ?>
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="card_id"   value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="action"    value="freeze">
                    <button type="submit" class="cact cact-freeze"
                            onclick="return confirm('Freeze card ending in <?php echo $last4; ?>?\nNo transactions will be possible while frozen.')">
                        <i class="ri-pause-circle-line"></i> Freeze
                    </button>
                </form>
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="card_id"   value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="action"    value="block">
                    <button type="submit" class="cact cact-block"
                            onclick="return confirm('Block this card permanently?\n\nContact support to unblock it.')">
                        <i class="ri-forbid-line"></i> Block
                    </button>
                </form>
                <?php elseif($status === 'frozen'): ?>
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="card_id"   value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="action"    value="unfreeze">
                    <button type="submit" class="cact cact-unfreeze"
                            onclick="return confirm('Unfreeze this card?')">
                        <i class="ri-play-circle-line"></i> Unfreeze
                    </button>
                </form>
                <form method="POST" style="display:contents;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="card_id"   value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="action"    value="block">
                    <button type="submit" class="cact cact-block"
                            onclick="return confirm('Block this card permanently?')">
                        <i class="ri-forbid-line"></i> Block
                    </button>
                </form>
                <?php elseif($status === 'blocked'): ?>
                <a href="support?subject=<?php echo urlencode('Unblock card ending in '.$last4); ?>"
                   class="cact cact-support" style="text-decoration:none;">
                    <i class="ri-customer-service-2-line"></i> Unblock via Support
                </a>
                <?php endif; ?>

                <!-- DELETE -->
                <button type="button" class="cact cact-delete"
                        onclick="openDeleteModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($last4); ?>', '<?php echo htmlspecialchars($network); ?>')">
                    <i class="ri-delete-bin-6-line"></i> Delete
                </button>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; // end empty check ?>

<!-- ==================== REQUEST CARD TAB ==================== -->
<?php elseif($active_tab === 'request'): ?>

<?php if($has_pending): ?>
<div class="alert alert-info">
    <i class="ri-time-line"></i>
    <div>
        <strong>Pending Request.</strong> You have a card request under review. Please wait for it to be processed.
        <a href="cards?tab=history" style="color:inherit;font-weight:700;margin-left:6px;">View Status →</a>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:22px;align-items:start;">

    <div class="req-form">
        <div class="card-title" style="margin-bottom:20px;"><i class="ri-add-circle-line" style="color:var(--gold);"></i> Request a New Card</div>

        <?php if($has_pending): ?>
        <div style="text-align:center;padding:28px 0;">
            <div style="font-size:50px;margin-bottom:12px;">⏳</div>
            <div style="font-size:15px;font-weight:700;color:var(--navy);margin-bottom:6px;">Request Pending</div>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">Your card request is being reviewed.</p>
            <a href="cards?tab=history" class="btn btn-primary" style="height:40px;">Check Status</a>
        </div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action"     value="request_card">

            <div style="margin-bottom:18px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy);display:block;margin-bottom:8px;">Card Type <span style="color:var(--red);">*</span></label>
                <div class="type-opts">
                    <label class="type-opt selected" onclick="this.parentElement.querySelectorAll('.type-opt').forEach(e=>e.classList.remove('selected'));this.classList.add('selected');this.querySelector('input').checked=true;">
                        <input type="radio" name="card_type" value="debit" checked>
                        <div style="width:40px;height:40px;background:#dbeafe;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">💳</div>
                        <div><div style="font-size:14px;font-weight:700;color:var(--navy);">Debit Card</div><div style="font-size:11px;color:var(--text-muted);">Linked to your balance</div></div>
                    </label>
                    <label class="type-opt" onclick="this.parentElement.querySelectorAll('.type-opt').forEach(e=>e.classList.remove('selected'));this.classList.add('selected');this.querySelector('input').checked=true;">
                        <input type="radio" name="card_type" value="credit">
                        <div style="width:40px;height:40px;background:#ede9fe;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">🟣</div>
                        <div><div style="font-size:14px;font-weight:700;color:var(--navy);">Credit Card</div><div style="font-size:11px;color:var(--text-muted);">Subject to approval</div></div>
                    </label>
                </div>
            </div>

            <div style="margin-bottom:18px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy);display:block;margin-bottom:8px;">Network <span style="color:var(--red);">*</span></label>
                <div class="net-opts">
                    <?php foreach(['Visa'=>['💳','Classic'],'Mastercard'=>['🔴','Worldwide'],'Verve'=>['🟢','Local']] as $net=>[$ico,$sub]): ?>
                    <label class="net-opt <?php echo $net==='Visa'?'selected':''; ?>"
                           onclick="document.querySelectorAll('.net-opt').forEach(e=>e.classList.remove('selected'));this.classList.add('selected');this.querySelector('input').checked=true;">
                        <input type="radio" name="network" value="<?php echo $net; ?>" <?php echo $net==='Visa'?'checked':''; ?>>
                        <span class="net-icon"><?php echo $ico; ?></span>
                        <div class="net-name"><?php echo $net; ?></div>
                        <div style="font-size:10px;color:var(--text-muted);"><?php echo $sub; ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy);display:block;margin-bottom:6px;">Reason <small style="text-transform:none;font-weight:400;color:var(--text-muted);">(Optional)</small></label>
                <textarea name="reason" rows="3" class="form-control" style="height:auto;padding:10px 12px;resize:none;" placeholder="e.g. Online shopping, international travel, business use..." maxlength="300"></textarea>
            </div>

            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:9px;padding:12px 14px;font-size:12px;color:#1d4ed8;margin-bottom:16px;">
                <i class="ri-information-line mr-1"></i>
                Reviewed within <strong>1–2 business days</strong>. A 3-digit CVV will be issued with your card.
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="height:48px;font-size:15px;">
                <i class="ri-send-plane-fill"></i> Submit Card Request
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div>
        <div class="card">
            <div class="card-title" style="margin-bottom:14px;"><i class="ri-shield-check-line" style="color:var(--gold);"></i> Card Safety</div>
            <?php foreach([['🔐','Keep CVV private','Your 3-digit CVV is for online purchases only. Never share it.'],['📵','Ignore OTP requests','MidwesternBank will never call to ask for your code.'],['❄️','Freeze if misplaced','Instantly freeze your card from this page.'],['🌐','Secure sites only','Always check https:// before entering card details.'],['🚨','Report fraud fast','Contact support immediately for unauthorized charges.']] as [$ic,$t,$d]): ?>
            <div style="display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--border);">
                <div style="width:30px;height:30px;background:var(--bg);border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px;"><?php echo $ic; ?></div>
                <div><div style="font-size:12px;font-weight:700;color:var(--navy);margin-bottom:2px;"><?php echo $t; ?></div><div style="font-size:11px;color:var(--text-muted);line-height:1.5;"><?php echo $d; ?></div></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ==================== REQUEST HISTORY TAB ==================== -->
<?php elseif($active_tab === 'history'): ?>

<?php if(empty($requests)): ?>
<div class="card">
    <div class="empty-state">
        <div class="empty-icon"><i class="ri-file-list-line"></i></div>
        <div style="font-size:15px;font-weight:700;color:var(--navy);margin-bottom:8px;">No Requests Yet</div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">You haven't submitted any card requests.</p>
        <a href="cards?tab=request" class="btn btn-primary" style="height:40px;padding:0 24px;"><i class="ri-add-line"></i> Request a Card</a>
    </div>
</div>
<?php else: ?>
<div class="card" style="padding:20px;">
    <div class="card-title" style="margin-bottom:18px;"><i class="ri-file-list-3-line" style="color:var(--gold);"></i> Card Request History</div>
    <?php foreach($requests as $r):
        [$rbg,$rcol,$rlbl] = match($r['status']??'pending') {
            'pending'  => ['#fef3c7','#d97706','⏳ Pending Review'],
            'approved' => ['#d1fae5','#059669','✓ Approved'],
            'rejected' => ['#fee2e2','#dc2626','✕ Rejected'],
            default    => ['#f3f4f6','#6b7280','Unknown'],
        };
        $ricon = match($r['status']??'pending') { 'approved'=>['#d1fae5','✅'], 'rejected'=>['#fee2e2','❌'], default=>['#fef3c7','⏳'] };
        $days  = (int)floor((time()-strtotime($r['created_at']))/86400);
    ?>
    <div class="req-row" style="<?php echo ($r['status']??'')==='pending'?'border-color:#f59e0b;background:#fffdf5;':''; ?>">
        <div class="req-icon" style="background:<?php echo $ricon[0]; ?>;"><?php echo $ricon[1]; ?></div>
        <div style="flex:1;min-width:0;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                <span style="font-size:14px;font-weight:700;color:var(--navy);"><?php echo htmlspecialchars($r['network']??'Visa'); ?> <?php echo ucfirst($r['card_type']??'Debit'); ?> Card</span>
                <span style="background:<?php echo $rbg; ?>;color:<?php echo $rcol; ?>;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;"><?php echo $rlbl; ?></span>
            </div>
            <?php if(!empty($r['reason'])): ?>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:3px;">"<?php echo htmlspecialchars(substr($r['reason'],0,80)); ?><?php echo strlen($r['reason'])>80?'…':''; ?>"</div>
            <?php endif; ?>
            <div style="font-size:11px;color:var(--text-muted);">Submitted: <?php echo date('d M Y, H:i',strtotime($r['created_at'])); ?><?php if($days>0): ?> · <?php echo $days; ?>d ago<?php endif; ?></div>
        </div>
        <?php if(($r['status']??'')==='approved'): ?>
            <a href="cards?tab=cards" class="btn btn-secondary" style="height:34px;font-size:12px;flex-shrink:0;white-space:nowrap;"><i class="ri-credit-card-line"></i> View Card</a>
        <?php elseif(($r['status']??'')==='pending'): ?>
            <div style="flex-shrink:0;font-size:11px;color:#d97706;font-weight:700;text-align:right;white-space:nowrap;">Under<br>Review</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; // tabs ?>

<!-- ============================================================ LIMIT EDIT MODAL ============================================================ -->
<div class="modal-overlay" id="limitModal">
    <div class="modal-box" style="max-width:420px;">
        <div style="background:linear-gradient(135deg,var(--navy),#1a4b8c);padding:22px 24px;text-align:center;">
            <div style="font-size:18px;font-weight:700;color:#fff;margin-bottom:4px;">
                <i class="ri-shield-line mr-2" style="color:var(--gold);"></i>Update Daily Limit
            </div>
            <div style="font-size:12px;color:rgba(255,255,255,.6);" id="limitModalSub">Card •••• ????</div>
        </div>
        <div style="padding:24px;">
            <form method="POST" id="limitForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action"     value="update_limit">
                <input type="hidden" name="card_id"   id="limitCardId" value="">

                <!-- Current & New Amount Display -->
                <div style="text-align:center;margin-bottom:20px;">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">New Daily Limit</div>
                    <div style="font-size:36px;font-weight:800;color:var(--navy);">
                        $<span id="limitDisplay">1,000</span>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">per day</div>
                </div>

                <!-- Slider -->
                <input type="range" name="new_limit" id="limitSlider" class="limit-slider"
                       min="100" max="50000" step="100" value="1000"
                       oninput="updateLimitDisplay(this.value)">
                <div class="limit-marks">
                    <span>$100</span><span>$10K</span><span>$25K</span><span>$50K</span>
                </div>

                <!-- Quick presets -->
                <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
                    <?php foreach([500,1000,5000,10000,25000] as $p): ?>
                    <button type="button" class="btn btn-secondary"
                            style="flex:1;height:34px;font-size:12px;min-width:60px;"
                            onclick="setLimit(<?php echo $p; ?>)">
                        $<?php echo number_format($p); ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Manual input -->
                <div style="margin-top:14px;">
                    <label style="font-size:11px;font-weight:700;color:var(--navy);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">
                        Or type exact amount:
                    </label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:18px;font-weight:700;color:var(--navy);">$</span>
                        <input type="number" id="limitManual" min="100" max="50000" step="100"
                               class="form-control" style="padding-left:30px;height:44px;font-size:16px;font-weight:700;"
                               placeholder="Enter amount..."
                               oninput="manualLimitInput(this.value)">
                    </div>
                </div>

                <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;font-size:12px;color:#1d4ed8;margin:14px 0;">
                    <i class="ri-information-line mr-1"></i>
                    Daily limit applies to all transactions on this card. Min: <strong>$100</strong> · Max: <strong>$50,000</strong>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="closeLimitModal()" class="btn btn-secondary" style="flex:1;height:46px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:2;height:46px;font-size:14px;">
                        <i class="ri-save-line"></i> Save New Limit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ DELETE CARD MODAL ============================================================ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="max-width:400px;">
        <div style="background:linear-gradient(135deg,#7f1d1d,#dc2626);padding:22px 24px;text-align:center;">
            <div style="font-size:32px;margin-bottom:8px;">🗑️</div>
            <div style="font-size:18px;font-weight:800;color:#fff;margin-bottom:4px;">Delete Card</div>
            <div style="font-size:13px;color:rgba(255,255,255,.7);" id="delModalSub">•••• •••• •••• ????</div>
        </div>
        <div style="padding:24px;">
            <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:10px;padding:14px;margin-bottom:20px;font-size:13px;color:#dc2626;line-height:1.6;">
                <strong><i class="ri-error-warning-line mr-1"></i>Warning:</strong>
                This is <strong>permanent and cannot be undone</strong>. The card will be removed from your account entirely.
            </div>
            <div style="margin-bottom:18px;">
                <label style="font-size:12px;font-weight:700;color:var(--navy);display:block;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px;">
                    Type <strong style="background:#dc2626;color:#fff;padding:1px 6px;border-radius:4px;letter-spacing:2px;">DELETE</strong> to confirm
                </label>
                <input type="text" class="del-confirm-input" id="delConfirmInput"
                       placeholder="Type DELETE here"
                       oninput="checkDelConfirm(this)"
                       autocomplete="off" spellcheck="false">
            </div>
            <form method="POST" id="deleteCardForm">
                <input type="hidden" name="csrf_token"     value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action"         value="delete_card">
                <input type="hidden" name="card_id"        id="delCardId" value="">
                <input type="hidden" name="delete_confirm" id="delConfirmHidden" value="">
                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary" style="flex:1;height:44px;">Cancel</button>
                    <button type="submit" id="delSubmitBtn" class="btn btn-danger" style="flex:1;height:44px;" disabled>
                        <i class="ri-delete-bin-6-line"></i> Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_js = "
// ── CVV Toggle ──
function toggleCVV(inputId, iconId) {
    const mask = document.getElementById(inputId + '_mask');
    const val  = document.getElementById(inputId + '_val');
    const icon = document.getElementById(iconId);
    if(!mask || !val || !icon) return;
    const showing = val.style.display !== 'none';
    mask.style.display = showing ? '' : 'none';
    val.style.display  = showing ? 'none' : '';
    icon.innerHTML = showing ? '<i class=\"ri-eye-off-line\"></i>' : '<i class=\"ri-eye-line\"></i>';
    // Auto-hide after 10 seconds
    if(!showing) {
        clearTimeout(window['cvvTimer_'+inputId]);
        window['cvvTimer_'+inputId] = setTimeout(() => {
            mask.style.display = '';
            val.style.display  = 'none';
            icon.innerHTML = '<i class=\"ri-eye-off-line\"></i>';
        }, 10000);
    }
}

// ── Limit Modal ──
function openLimitModal(cardId, currentLimit, last4) {
    document.getElementById('limitCardId').value = cardId;
    document.getElementById('limitModalSub').textContent = 'Card •••• ' + last4;
    document.getElementById('limitSlider').value = currentLimit;
    document.getElementById('limitManual').value = currentLimit;
    updateLimitDisplay(currentLimit);
    document.getElementById('limitModal').classList.add('show');
}
function closeLimitModal() {
    document.getElementById('limitModal').classList.remove('show');
}
function updateLimitDisplay(val) {
    const v = parseFloat(val) || 100;
    const clamped = Math.max(100, Math.min(50000, v));
    document.getElementById('limitDisplay').textContent = clamped.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('limitSlider').value  = clamped;
    document.getElementById('limitManual').value  = clamped;
    // Update slider gradient
    const pct = ((clamped - 100) / (50000 - 100)) * 100;
    document.getElementById('limitSlider').style.setProperty('--pct', pct+'%');
}
function setLimit(v) {
    updateLimitDisplay(v);
}
function manualLimitInput(v) {
    const n = parseFloat(v) || 100;
    updateLimitDisplay(n);
}

// ── Delete Modal ──
function openDeleteModal(cardId, last4, network) {
    document.getElementById('delCardId').value    = cardId;
    document.getElementById('delModalSub').textContent = '•••• •••• •••• ' + last4 + ' · ' + network;
    document.getElementById('delConfirmInput').value   = '';
    document.getElementById('delConfirmHidden').value  = '';
    document.getElementById('delConfirmInput').classList.remove('valid');
    document.getElementById('delSubmitBtn').disabled = true;
    document.getElementById('deleteModal').classList.add('show');
    setTimeout(() => document.getElementById('delConfirmInput').focus(), 300);
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
}
function checkDelConfirm(el) {
    const valid = el.value.trim().toUpperCase() === 'DELETE';
    el.classList.toggle('valid', valid);
    document.getElementById('delConfirmHidden').value = el.value.trim();
    document.getElementById('delSubmitBtn').disabled = !valid;
}

// Close modals on overlay click
['limitModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if(e.target === this) {
            if(id === 'limitModal')  closeLimitModal();
            if(id === 'deleteModal') closeDeleteModal();
        }
    });
});
// ESC to close
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') {
        closeLimitModal(); closeDeleteModal();
    }
});
// Prevent delete form double-submit
document.getElementById('deleteCardForm').addEventListener('submit', function() {
    const btn = document.getElementById('delSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class=\"ri-loader-4-line\"></i> Deleting...';
});
";

include 'layout/customer-footer.php';
?>