<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

// ============================================================
// HANDLE ALL ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_action = trim($_POST['admin_action'] ?? '');
    $card_id      = (int)($_POST['card_id']    ?? 0);
    $req_id       = (int)($_POST['req_id']     ?? 0);

    try {
        // ── STATUS CHANGES
        if(in_array($admin_action, ['activate','freeze','unfreeze','block','unblock'])) {
            if($card_id <= 0) throw new Exception("Invalid card ID.");
            $s = $pdo->prepare("SELECT id, card_number FROM cards WHERE id=? LIMIT 1");
            $s->execute([$card_id]);
            $card = $s->fetch(PDO::FETCH_ASSOC);
            if(!$card) throw new Exception("Card not found.");

            $new_status = match($admin_action) {
                'activate','unblock','unfreeze' => 'active',
                'freeze'                        => 'frozen',
                'block'                         => 'blocked',
            };
            $pdo->prepare("UPDATE cards SET status=?, updated_at=NOW() WHERE id=?")
                ->execute([$new_status, $card_id]);

            $last4 = substr(str_replace(['-',' '],'', $card['card_number']??''), -4);
            $_SESSION['success_message'] = "Card •••• {$last4} → <strong>".ucfirst($new_status)."</strong>.";
        }

        // ── UPDATE LIMIT (admin can set any limit)
        elseif($admin_action === 'set_limit') {
            if($card_id <= 0) throw new Exception("Invalid card ID.");
            $new_limit = (float)($_POST['new_limit'] ?? 0);
            if($new_limit < 0 || $new_limit > 1000000) throw new Exception("Invalid limit value.");
            $pdo->prepare("UPDATE cards SET daily_limit=?, updated_at=NOW() WHERE id=?")->execute([$new_limit, $card_id]);
            $_SESSION['success_message'] = "Daily limit updated to <strong>\$".number_format($new_limit,2)."</strong>.";
        }

        // ── DELETE CARD
        elseif($admin_action === 'delete_card') {
            if($card_id <= 0) throw new Exception("Invalid card ID.");
            $s = $pdo->prepare("SELECT id, card_number, user_id FROM cards WHERE id=? LIMIT 1");
            $s->execute([$card_id]);
            $card = $s->fetch(PDO::FETCH_ASSOC);
            if(!$card) throw new Exception("Card not found.");
            $pdo->prepare("DELETE FROM cards WHERE id=?")->execute([$card_id]);
            $last4 = substr(str_replace(['-',' '],'', $card['card_number']??''), -4);
            $_SESSION['success_message'] = "Card •••• {$last4} has been <strong>permanently deleted</strong>.";
        }

        // ── APPROVE REQUEST (generates real 3-digit CVV, stored as plain text)
        elseif($admin_action === 'approve_req') {
            if($req_id <= 0) throw new Exception("Invalid request ID.");
            $s = $pdo->prepare("SELECT * FROM card_requests WHERE id=? LIMIT 1");
            $s->execute([$req_id]);
            $req = $s->fetch(PDO::FETCH_ASSOC);
            if(!$req) throw new Exception("Request not found.");
            if(($req['status']??'')==='approved') throw new Exception("Request already approved.");

            $pdo->beginTransaction();

            // Generate card details
            $expiry  = date('Y-m-t', strtotime('+3 years'));
            // Generate card number (16 digits grouped as ####-####-####-####)
            $num_parts = [];
            for($i=0;$i<4;$i++) $num_parts[] = str_pad((string)random_int(0,9999),4,'0',STR_PAD_LEFT);
            $card_num = implode('-', $num_parts);
            // 3-digit CVV — stored as plain text for display
            $cvv_plain = str_pad((string)random_int(0,999),3,'0',STR_PAD_LEFT);

            $pdo->prepare("INSERT INTO cards
                    (user_id, card_number, card_type, network, cvv, status, daily_limit, expiry_date, created_at)
                VALUES (?,?,?,?,?,'active',1000.00,?,NOW())
            ")->execute([
                $req['user_id'],
                $card_num,
                $req['card_type'] ?? 'debit',
                $req['network']   ?? 'Visa',
                $cvv_plain,
                $expiry,
            ]);

            $pdo->prepare("UPDATE card_requests SET status='approved', updated_at=NOW() WHERE id=?")
                ->execute([$req_id]);

            $pdo->commit();
            $_SESSION['success_message'] = "Card request approved. New card issued with CVV <strong>{$cvv_plain}</strong>.";
        }

        // ── REJECT REQUEST
        elseif($admin_action === 'reject_req') {
            if($req_id <= 0) throw new Exception("Invalid request ID.");
            $pdo->prepare("UPDATE card_requests SET status='rejected', updated_at=NOW() WHERE id=?")->execute([$req_id]);
            $_SESSION['success_message'] = "Card request rejected.";
        }

        // ── DELETE REQUEST
        elseif($admin_action === 'delete_req') {
            if($req_id <= 0) throw new Exception("Invalid request ID.");
            $pdo->prepare("DELETE FROM card_requests WHERE id=?")->execute([$req_id]);
            $_SESSION['success_message'] = "Card request permanently deleted.";
        }

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }

    $go_tab = in_array($admin_action, ['approve_req','reject_req','delete_req']) ? 'requests' : 'cards';
    header("Location: cards-all.php?tab={$go_tab}"); exit;
}

// ============================================================
// FILTERS & FETCH
// ============================================================
$active_tab = $_GET['tab']    ?? 'cards';
$status_f   = $_GET['status'] ?? 'all';
$search     = trim($_GET['q'] ?? '');
$page       = max(1,(int)($_GET['page']??1));
$per        = 20;
$offset     = ($page-1)*$per;

// Stats
$stats = $req_stats = [];
try {
    $stats = $pdo->query("
        SELECT COUNT(*) total, SUM(status='active') active_cnt,
               SUM(status='frozen') frozen_cnt, SUM(status='blocked') blocked_cnt,
               SUM(card_type='debit') debit_cnt, SUM(card_type='credit') credit_cnt
        FROM cards
    ")->fetch(PDO::FETCH_ASSOC);
    $req_stats = $pdo->query("
        SELECT COUNT(*) total, SUM(status='pending') pending_cnt,
               SUM(status='approved') approved_cnt, SUM(status='rejected') rejected_cnt
        FROM card_requests
    ")->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Cards
$cards = []; $total_cards = 0;
if($active_tab === 'cards') {
    $w=['1=1']; $p=[];
    if($status_f!=='all'){ $w[]='c.status=?'; $p[]=$status_f; }
    if($search){
        $w[]="(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.account_number LIKE ? OR c.card_number LIKE ?)";
        $sq="%{$search}%"; $p=array_merge($p,[$sq,$sq,$sq,$sq,$sq]);
    }
    $ws=implode(' AND ',$w);
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM cards c JOIN users u ON c.user_id=u.id WHERE {$ws}");
    $cnt->execute($p); $total_cards=(int)$cnt->fetchColumn();
    $stmt=$pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.email, u.account_number
        FROM cards c JOIN users u ON c.user_id=u.id WHERE {$ws}
        ORDER BY CASE c.status WHEN 'blocked' THEN 1 WHEN 'frozen' THEN 2 WHEN 'active' THEN 3 ELSE 4 END, c.created_at DESC
        LIMIT {$per} OFFSET {$offset}
    ");
    $stmt->execute($p); $cards=$stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Requests
$requests = []; $total_reqs = 0;
if($active_tab === 'requests') {
    $w=['1=1']; $p=[];
    if($status_f!=='all'){ $w[]='cr.status=?'; $p[]=$status_f; }
    if($search){
        $w[]="(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $sq="%{$search}%"; $p=array_merge($p,[$sq,$sq,$sq]);
    }
    $ws=implode(' AND ',$w);
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM card_requests cr JOIN users u ON cr.user_id=u.id WHERE {$ws}");
    $cnt->execute($p); $total_reqs=(int)$cnt->fetchColumn();
    $stmt=$pdo->prepare("
        SELECT cr.*, u.first_name, u.last_name, u.email, u.account_number
        FROM card_requests cr JOIN users u ON cr.user_id=u.id WHERE {$ws}
        ORDER BY CASE cr.status WHEN 'pending' THEN 1 ELSE 2 END, cr.created_at DESC
        LIMIT {$per} OFFSET {$offset}
    ");
    $stmt->execute($p); $requests=$stmt->fetchAll(PDO::FETCH_ASSOC);
}

$total_pages = max(1, ceil(($active_tab==='cards'?$total_cards:$total_reqs)/$per));

function aUrl($o=[]): string {
    return '?'.http_build_query(array_merge(array_filter($_GET,fn($v)=>$v!==''), $o));
}
function spill(string $status): string {
    [$bg,$col,$lbl] = match($status) {
        'active'  =>['#d1fae5','#059669','✓ Active'],
        'frozen'  =>['#dbeafe','#2563eb','❄ Frozen'],
        'blocked' =>['#fee2e2','#dc2626','✕ Blocked'],
        'pending' =>['#fef3c7','#d97706','⏳ Pending'],
        'approved'=>['#d1fae5','#059669','✓ Approved'],
        'rejected'=>['#fee2e2','#dc2626','✕ Rejected'],
        default   =>['#f3f4f6','#6b7280',ucfirst($status)],
    };
    return "<span style='display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;background:{$bg};color:{$col};white-space:nowrap;'>{$lbl}</span>";
}
?>

<!-- Page CSS -->
<style>
.adm-tbl { width:100%;border-collapse:collapse; }
.adm-tbl th { background:#0c2340;color:#fff;padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap; }
.adm-tbl td { padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle; }
.adm-tbl tr:hover td { background:#f8faff; }
.adm-tbl tr.tr-blocked td { background:#fff5f5; }
.adm-tbl tr.tr-frozen  td { background:#f0f9ff; }
.adm-tbl tr.tr-pending td { background:#fffdf5; }

.act-btns { display:flex;gap:4px;flex-wrap:wrap; }
.act-btns .btn { height:28px;padding:0 8px;font-size:11px;white-space:nowrap; }

/* ── Global Delete Modal ── */
.g-del-overlay {
    display:none;position:fixed;inset:0;
    background:rgba(12,35,64,.7);z-index:9999;
    align-items:center;justify-content:center;
    backdrop-filter:blur(4px);
}
.g-del-overlay.show { display:flex; }
.g-del-modal {
    background:#fff;border-radius:16px;
    width:100%;max-width:400px;margin:16px;
    overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);
    animation:gPop .22s ease;
}
@keyframes gPop { from{transform:scale(.9);opacity:0;} to{transform:scale(1);opacity:1;} }
.g-del-head { padding:22px 24px;text-align:center; }
.g-del-body { padding:22px 24px; }

/* Mobile responsive table */
@media(max-width:860px){
    .adm-tbl thead{display:none;}
    .adm-tbl,.adm-tbl tbody,.adm-tbl tr,.adm-tbl td{display:block;width:100%;}
    .adm-tbl tr{border:1px solid #e5e7eb;border-radius:10px;margin-bottom:12px;background:#fff;overflow:hidden;}
    .adm-tbl td{border-bottom:1px solid #f3f4f6;padding:8px 12px;}
    .adm-tbl td::before{content:attr(data-label);display:block;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:3px;}
    .adm-tbl td:last-child{border-bottom:none;}
}
@media(max-width:600px){
    .stat-mini-grid{grid-template-columns:repeat(2,1fr)!important;}
    .tab-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}
    .tab-scroll .nav{flex-wrap:nowrap;}
    .tab-scroll .nav-link{white-space:nowrap;}
}
</style>

<div class="main-content">
<section class="section">

<!-- HEADER -->
<div class="section-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <h1><i class="fas fa-credit-card mr-2"></i> Cards Management</h1>
    <?php if(!empty($req_stats['pending_cnt'])): ?>
    <span style="background:#fef3c7;color:#d97706;border:1px solid #fde68a;padding:7px 16px;border-radius:20px;font-size:13px;font-weight:600;">
        <i class="fas fa-clock mr-1"></i> <?php echo $req_stats['pending_cnt']; ?> Pending Request<?php echo $req_stats['pending_cnt']>1?'s':''; ?>
    </span>
    <?php endif; ?>
</div>

<!-- FLASH -->
<?php if(!empty($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>
<?php if(!empty($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
    <button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="row mb-4" style="gap:0;">
    <?php foreach([
        ['Total Cards', $stats['total']??0,'bg-primary','fas fa-credit-card'],
        ['Active',      $stats['active_cnt']??0,'bg-success','fas fa-check-circle'],
        ['Frozen',      $stats['frozen_cnt']??0,'bg-info','fas fa-snowflake'],
        ['Blocked',     $stats['blocked_cnt']??0,'bg-danger','fas fa-ban'],
        ['Pending Req.',   $req_stats['pending_cnt']??0,'bg-warning','fas fa-clock'],
        ['Approved Req.',  $req_stats['approved_cnt']??0,'bg-success','fas fa-check-double'],
    ] as [$l,$v,$bg,$ic]): ?>
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card card-statistic-1 h-100">
            <div class="card-icon <?php echo $bg; ?>" style="width:36px;height:36px;font-size:14px;"><i class="<?php echo $ic; ?>"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4 style="font-size:11px;"><?php echo $l; ?></h4></div>
                <div class="card-body" style="font-size:20px;"><?php echo number_format($v); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- TABS -->
<div class="tab-scroll">
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a href="<?php echo aUrl(['tab'=>'cards','status'=>'all','page'=>1,'q'=>'']); ?>"
               class="nav-link <?php echo $active_tab==='cards'?'active':''; ?>">
                <i class="fas fa-credit-card mr-1"></i> All Cards
                <span class="badge badge-secondary ml-1"><?php echo $stats['total']??0; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo aUrl(['tab'=>'requests','status'=>'all','page'=>1,'q'=>'']); ?>"
               class="nav-link <?php echo $active_tab==='requests'?'active':''; ?>">
                <i class="fas fa-list mr-1"></i> Card Requests
                <?php if(!empty($req_stats['pending_cnt'])): ?>
                    <span class="badge badge-warning ml-1"><?php echo $req_stats['pending_cnt']; ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>
</div>

<!-- FILTERS -->
<form method="GET" class="mb-3">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
    <div class="d-flex flex-wrap" style="gap:8px;align-items:center;">
        <input type="text" name="q" class="form-control form-control-sm"
               style="flex:1;min-width:180px;"
               placeholder="Search name, email, account, card..."
               value="<?php echo htmlspecialchars($search); ?>">
        <select name="status" class="form-control form-control-sm" style="width:auto;">
            <option value="all" <?php echo $status_f==='all'?'selected':''; ?>>All Status</option>
            <?php if($active_tab==='cards'): ?>
                <option value="active"  <?php echo $status_f==='active'?'selected':''; ?>>Active</option>
                <option value="frozen"  <?php echo $status_f==='frozen'?'selected':''; ?>>Frozen</option>
                <option value="blocked" <?php echo $status_f==='blocked'?'selected':''; ?>>Blocked</option>
            <?php else: ?>
                <option value="pending"  <?php echo $status_f==='pending'?'selected':''; ?>>Pending</option>
                <option value="approved" <?php echo $status_f==='approved'?'selected':''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_f==='rejected'?'selected':''; ?>>Rejected</option>
            <?php endif; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
        <?php if($search||$status_f!=='all'): ?>
        <a href="<?php echo aUrl(['q'=>'','status'=>'all','page'=>1]); ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
        <small class="text-muted ml-auto"><?php echo number_format($active_tab==='cards'?$total_cards:$total_reqs); ?> found</small>
    </div>
</form>

<!-- ============================== CARDS TABLE ============================== -->
<?php if($active_tab === 'cards'): ?>

<?php if(!empty($req_stats['pending_cnt'])): ?>
<div style="background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:12px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <span style="font-size:16px;">⏳</span>
    <div style="flex:1;"><strong style="color:#92400e;"><?php echo $req_stats['pending_cnt']; ?> card request<?php echo $req_stats['pending_cnt']>1?'s':''; ?> await approval.</strong></div>
    <a href="<?php echo aUrl(['tab'=>'requests','status'=>'pending']); ?>" class="btn btn-warning btn-sm">Review →</a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="adm-tbl">
                <thead>
                    <tr>
                        <th>#</th><th>Customer</th><th>Card Number</th><th>CVV</th>
                        <th>Type</th><th>Expiry</th><th>Daily Limit</th><th>Status</th>
                        <th>Issued</th><th style="min-width:220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($cards)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted" data-label="">
                        <i class="fas fa-credit-card fa-3x d-block mb-2" style="opacity:.1;"></i>No cards found.
                    </td></tr>
                    <?php else: foreach($cards as $i=>$c):
                        $cstat  = $c['status'] ?? 'active';
                        $card_number = $c['card_number'];
                        $last4  = substr(str_replace(['-',' '],'', $c['card_number']??''), -4);
                        $is_exp = !empty($c['expiry_date']) && strtotime($c['expiry_date']) < time();
                        $limit  = number_format((float)($c['daily_limit']??1000), 2);
                        $cvv    = $c['cvv'] ?? '';
                        $cvv_ok = strlen($cvv) <= 4;
                        $row_cls= match($cstat){ 'blocked'=>'tr-blocked','frozen'=>'tr-frozen',default=>'' };
                    ?>
                    <tr class="<?php echo $row_cls; ?>">
                        <td data-label="#"><?php echo $offset+$i+1; ?></td>
                        <td data-label="Customer">
                            <div style="font-weight:700;"><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($c['email']); ?></small><br>
                            <code style="font-size:10px;"><?php echo htmlspecialchars($c['account_number']); ?></code>
                        </td>
                        <td data-label="Card Number">
                            <span style="font-family:monospace;font-weight:700;letter-spacing:1px;color:#0c2340;">
                                 <?php echo htmlspecialchars($card_number); ?>
                            </span>
                        </td>
                        <td data-label="CVV">
                            <?php if($cvv_ok && $cvv): ?>
                            <span style="font-family:monospace;font-weight:800;font-size:15px;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:5px;letter-spacing:2px;">
                                <?php echo htmlspecialchars($cvv); ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted" style="font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Type">
                            <span class="badge badge-primary" style="font-size:10px;"><?php echo ucfirst($c['card_type']??'Debit'); ?></span><br>
                            <small class="text-muted"><?php echo htmlspecialchars($c['network']??'Visa'); ?></small>
                        </td>
                        <td data-label="Expiry">
                            <?php if($is_exp): ?>
                                <span class="badge badge-danger" style="font-size:9px;">EXPIRED</span><br>
                                <small><?php echo !empty($c['expiry_date'])?date('m/Y',strtotime($c['expiry_date'])):''; ?></small>
                            <?php else: ?>
                                <span style="font-family:monospace;"><?php echo !empty($c['expiry_date'])?date('m/Y',strtotime($c['expiry_date'])):'—'; ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Daily Limit">
                            <strong>$<?php echo $limit; ?></strong>
                            <br>
                            <!-- Quick limit update -->
                            <button type="button" class="btn btn-sm btn-secondary mt-1" style="font-size:10px;height:22px;padding:0 6px;"
                                    onclick="openAdmLimit(<?php echo $c['id']; ?>, <?php echo (float)($c['daily_limit']??1000); ?>, '<?php echo htmlspecialchars($last4); ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                        <td data-label="Status"><?php echo spill($cstat); ?></td>
                        <td data-label="Issued">
                            <small class="text-muted"><?php echo !empty($c['created_at'])?date('d M Y',strtotime($c['created_at'])):'—'; ?></small>
                        </td>
                        <td data-label="Actions">
                            <div class="act-btns">
                                <?php if($cstat==='active'): ?>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="admin_action" value="freeze">
                                    <input type="hidden" name="card_id"      value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Freeze this card?')">
                                        <i class="fas fa-snowflake"></i> Freeze
                                    </button>
                                </form>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="admin_action" value="block">
                                    <input type="hidden" name="card_id"      value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Block this card?')">
                                        <i class="fas fa-ban"></i> Block
                                    </button>
                                </form>
                                <?php elseif($cstat==='frozen'): ?>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="admin_action" value="unfreeze">
                                    <input type="hidden" name="card_id"      value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Unfreeze this card?')">
                                        <i class="fas fa-play"></i> Unfreeze
                                    </button>
                                </form>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="admin_action" value="block">
                                    <input type="hidden" name="card_id"      value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Block this card?')">
                                        <i class="fas fa-ban"></i> Block
                                    </button>
                                </form>
                                <?php elseif($cstat==='blocked'): ?>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="admin_action" value="activate">
                                    <input type="hidden" name="card_id"      value="<?php echo $c['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Reactivate this card?')">
                                        <i class="fas fa-unlock"></i> Activate
                                    </button>
                                </form>
                                <?php endif; ?>

                                <a href="customer-detail-<?php echo $c['user_id']; ?>"
                                   class="btn btn-primary btn-sm" title="View Customer">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- DELETE — calls global modal -->
                                <button type="button"
                                        class="btn btn-sm"
                                        style="background:#7f1d1d;border-color:#7f1d1d;color:#fff;"
                                        onclick="openAdmDelCard(
                                            <?php echo $c['id']; ?>,
                                            '<?php echo htmlspecialchars($last4); ?>',
                                            '<?php echo htmlspecialchars($c['network']??'Visa'); ?> <?php echo ucfirst($c['card_type']??'Debit'); ?>',
                                            '<?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?>'
                                        )"
                                        title="Delete Card Permanently">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if($total_pages>1): ?>
    <div class="card-footer">
        <nav><ul class="pagination justify-content-center mb-0 flex-wrap">
            <?php if($page>1): ?><li class="page-item"><a class="page-link" href="<?php echo aUrl(['page'=>$page-1]); ?>">‹</a></li><?php endif; ?>
            <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="<?php echo aUrl(['page'=>$i]); ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <?php if($page<$total_pages): ?><li class="page-item"><a class="page-link" href="<?php echo aUrl(['page'=>$page+1]); ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- ============================== REQUESTS TABLE ============================== -->
<?php elseif($active_tab === 'requests'): ?>

<?php if(!empty($req_stats['pending_cnt'])): ?>
<div style="background:#fef3c7;border:1px solid #fde68a;border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
    <div style="width:40px;height:40px;background:#f59e0b;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;flex-shrink:0;">⏳</div>
    <div>
        <div style="font-weight:700;color:#92400e;"><?php echo $req_stats['pending_cnt']; ?> Request<?php echo $req_stats['pending_cnt']>1?'s':''; ?> Awaiting Review</div>
        <div style="font-size:12px;color:#b45309;">Approving auto-creates a card with a 3-digit CVV.</div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="adm-tbl">
                <thead>
                    <tr><th>#</th><th>Customer</th><th>Type / Network</th><th>Reason</th><th>Requested</th><th>Status</th><th style="min-width:200px;">Actions</th></tr>
                </thead>
                <tbody>
                    <?php if(empty($requests)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted" data-label="">
                        <i class="fas fa-inbox fa-3x d-block mb-2" style="opacity:.1;"></i>No card requests found.
                    </td></tr>
                    <?php else: foreach($requests as $i=>$r):
                        $rstat = $r['status'] ?? 'pending';
                        $days_wait = (int)floor((time()-strtotime($r['created_at']))/86400);
                        $row_cls = match($rstat){ 'pending'=>'tr-pending','rejected'=>'tr-blocked',default=>'' };
                    ?>
                    <tr class="<?php echo $row_cls; ?>">
                        <td data-label="#"><?php echo $offset+$i+1; ?></td>
                        <td data-label="Customer">
                            <div style="font-weight:700;"><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($r['email']); ?></small><br>
                            <code style="font-size:10px;"><?php echo htmlspecialchars($r['account_number']); ?></code>
                        </td>
                        <td data-label="Type">
                            <div style="font-weight:600;"><?php echo ucfirst($r['card_type']??'Debit'); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($r['network']??'Visa'); ?></small>
                        </td>
                        <td data-label="Reason">
                            <small class="text-muted"><?php echo htmlspecialchars(substr($r['reason']??$r['note']??'—',0,55)); ?></small>
                        </td>
                        <td data-label="Requested">
                            <small class="text-muted"><?php echo date('d M Y',strtotime($r['created_at'])); ?></small>
                            <?php if($rstat==='pending'&&$days_wait>0): ?>
                                <br><span class="badge badge-warning" style="font-size:9px;"><?php echo $days_wait; ?>d waiting</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><?php echo spill($rstat); ?></td>
                        <td data-label="Actions">
                            <div class="act-btns">
                                <?php if($rstat==='pending'): ?>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="admin_action" value="approve_req">
                                    <input type="hidden" name="req_id"       value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('Approve and issue a new card?\nA 3-digit CVV will be generated automatically.')">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="display:contents;">
                                    <input type="hidden" name="admin_action" value="reject_req">
                                    <input type="hidden" name="req_id"       value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-sm"
                                            onclick="return confirm('Reject this card request?')">
                                        <i class="fas fa-times mr-1"></i> Reject
                                    </button>
                                </form>
                                <?php endif; ?>

                                <a href="customer-detail-<?php echo $r['user_id']; ?>"
                                   class="btn btn-info btn-sm" title="View Customer">
                                    <i class="fas fa-user"></i>
                                </a>

                                <!-- DELETE REQUEST — global modal -->
                                <button type="button"
                                        class="btn btn-sm"
                                        style="background:#7f1d1d;border-color:#7f1d1d;color:#fff;"
                                        onclick="openAdmDelReq(
                                            <?php echo $r['id']; ?>,
                                            '<?php echo htmlspecialchars($r['network']??'Visa'); ?> <?php echo ucfirst($r['card_type']??'Debit'); ?>',
                                            '<?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?>',
                                            '<?php echo ucfirst($rstat); ?>'
                                        )"
                                        title="Delete Request">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if($total_pages>1): ?>
    <div class="card-footer">
        <nav><ul class="pagination justify-content-center mb-0 flex-wrap">
            <?php if($page>1): ?><li class="page-item"><a class="page-link" href="<?php echo aUrl(['page'=>$page-1]); ?>">‹</a></li><?php endif; ?>
            <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="<?php echo aUrl(['page'=>$i]); ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <?php if($page<$total_pages): ?><li class="page-item"><a class="page-link" href="<?php echo aUrl(['page'=>$page+1]); ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php endif; // end tab ?>

</section>
</div>

<!-- ============================================================
     GLOBAL ADMIN DELETE CARD MODAL
     Lives OUTSIDE the table — fixes the submission bug
============================================================ -->
<div class="g-del-overlay" id="gDelCardOverlay">
    <div class="g-del-modal">
        <div class="g-del-head" style="background:linear-gradient(135deg,#7f1d1d,#dc2626);">
            <div style="font-size:28px;margin-bottom:6px;">🗑️</div>
            <div style="font-size:17px;font-weight:800;color:#fff;">Delete Card Permanently</div>
            <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:3px;" id="gDelCardInfo"></div>
        </div>
        <div class="g-del-body">
            <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:9px;padding:12px;margin-bottom:16px;font-size:13px;color:#dc2626;line-height:1.6;">
                <strong>⚠ Permanent Action:</strong> This cannot be undone.
                The card will be immediately removed from the customer's account.
            </div>
            <div style="font-size:13px;margin-bottom:16px;" id="gDelCardCustomer"></div>
            <form method="POST" action="cards-all.php" id="gDelCardForm">
                <input type="hidden" name="admin_action" value="delete_card">
                <input type="hidden" name="card_id"      id="gDelCardId" value="">
                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="closeAdmDelCard()"
                            class="btn btn-secondary" style="flex:1;height:42px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="flex:1;height:42px;"
                            onclick="return confirm('Are you 100% sure? This card will be PERMANENTLY deleted and cannot be recovered.')">
                        <i class="fas fa-trash-alt mr-1"></i> Confirm Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- GLOBAL ADMIN DELETE REQUEST MODAL -->
<div class="g-del-overlay" id="gDelReqOverlay">
    <div class="g-del-modal">
        <div class="g-del-head" style="background:linear-gradient(135deg,#78350f,#d97706);">
            <div style="font-size:28px;margin-bottom:6px;">🗑️</div>
            <div style="font-size:17px;font-weight:800;color:#fff;">Delete Card Request</div>
            <div style="font-size:12px;color:rgba(255,255,255,.7);margin-top:3px;" id="gDelReqInfo"></div>
        </div>
        <div class="g-del-body">
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:9px;padding:12px;margin-bottom:16px;font-size:13px;color:#92400e;line-height:1.6;">
                <strong>⚠ Warning:</strong> This will permanently delete the card request.
                The customer will not be notified automatically.
            </div>
            <div style="font-size:13px;margin-bottom:16px;" id="gDelReqCustomer"></div>
            <form method="POST" action="cards-all.php" id="gDelReqForm">
                <input type="hidden" name="admin_action" value="delete_req">
                <input type="hidden" name="req_id"       id="gDelReqId" value="">
                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="closeAdmDelReq()"
                            class="btn btn-secondary" style="flex:1;height:42px;">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="flex:1;height:42px;"
                            onclick="return confirm('Delete this card request permanently?')">
                        <i class="fas fa-trash-alt mr-1"></i> Delete Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- GLOBAL ADMIN LIMIT MODAL -->
<div class="g-del-overlay" id="gLimitOverlay">
    <div class="g-del-modal">
        <div class="g-del-head" style="background:linear-gradient(135deg,#0c2340,#1a4b8c);">
            <div style="font-size:17px;font-weight:800;color:#fff;">Update Daily Limit</div>
            <div style="font-size:12px;color:rgba(255,255,255,.6);margin-top:3px;" id="gLimitInfo"></div>
        </div>
        <div class="g-del-body">
            <form method="POST" action="cards-all.php" id="gLimitForm">
                <input type="hidden" name="admin_action" value="set_limit">
                <input type="hidden" name="card_id"      id="gLimitCardId" value="">
                <div style="margin-bottom:16px;">
                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#374151;display:block;margin-bottom:6px;">New Daily Limit (USD)</label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:18px;font-weight:700;color:#0c2340;">$</span>
                        <input type="number" name="new_limit" id="gLimitInput" min="0" max="1000000" step="100"
                               class="form-control" style="padding-left:28px;height:46px;font-size:18px;font-weight:700;"
                               placeholder="e.g. 5000">
                    </div>
                    <small class="text-muted">Enter 0 to disable card transactions. Admin can set any value.</small>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" onclick="closeAdmLimit()"
                            class="btn btn-secondary" style="flex:1;height:42px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex:1;height:42px;">
                        <i class="fas fa-save mr-1"></i> Save Limit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Delete Card Modal ──
function openAdmDelCard(cardId, last4, type, customer) {
    document.getElementById('gDelCardId').value    = cardId;
    document.getElementById('gDelCardInfo').textContent = '•••• •••• •••• ' + last4 + ' · ' + type;
    document.getElementById('gDelCardCustomer').innerHTML = 'Customer: <strong>' + customer + '</strong>';
    document.getElementById('gDelCardOverlay').classList.add('show');
}
function closeAdmDelCard() {
    document.getElementById('gDelCardOverlay').classList.remove('show');
}

// ── Delete Request Modal ──
function openAdmDelReq(reqId, type, customer, status) {
    document.getElementById('gDelReqId').value     = reqId;
    document.getElementById('gDelReqInfo').textContent = type + ' · ' + status;
    document.getElementById('gDelReqCustomer').innerHTML = 'Customer: <strong>' + customer + '</strong>';
    document.getElementById('gDelReqOverlay').classList.add('show');
}
function closeAdmDelReq() {
    document.getElementById('gDelReqOverlay').classList.remove('show');
}

// ── Limit Modal ──
function openAdmLimit(cardId, currentLimit, last4) {
    document.getElementById('gLimitCardId').value = cardId;
    document.getElementById('gLimitInput').value  = currentLimit;
    document.getElementById('gLimitInfo').textContent = 'Card •••• ' + last4 + ' · Current: $' + parseFloat(currentLimit).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) + '/day';
    document.getElementById('gLimitOverlay').classList.add('show');
    setTimeout(() => document.getElementById('gLimitInput').focus(), 200);
}
function closeAdmLimit() {
    document.getElementById('gLimitOverlay').classList.remove('show');
}

// Close all overlays on backdrop click
['gDelCardOverlay','gDelReqOverlay','gLimitOverlay'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if(e.target === this) this.classList.remove('show');
    });
});

// ESC closes all
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') {
        ['gDelCardOverlay','gDelReqOverlay','gLimitOverlay'].forEach(id => {
            document.getElementById(id).classList.remove('show');
        });
    }
});

// Auto-dismiss alerts
document.querySelectorAll('.alert-dismissible').forEach(a => {
    setTimeout(() => { const b=a.querySelector('[data-dismiss="alert"]'); if(b) b.click(); }, 7000);
});
</script>

<?php include 'layout/footer.php'; ?>