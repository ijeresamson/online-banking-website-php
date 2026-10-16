<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';
$page_title = 'Dashboard — MidwesternBank';

require_once "front_extra_css/extra_dashboard.php";

include 'layout/customer-header.php';

function dashIsCredit(array $t): bool {
    $type = strtolower($t['type'] ?? '');
    if(in_array($type, ['credit','deposit','receive','received','refund','reversal'])) return true;
    $before = (float)($t['balance_before'] ?? 0);
    $after  = (float)($t['balance_after']  ?? 0);
    if($after > $before && $before >= 0) return true;
    return false;
}

// ============================================================
// FETCH ALL DASHBOARD DATA
// ============================================================

// Full account details
$acc = getAccountFull($pdo, $user_id);

// Monthly income (deposits this month)
$m_in = 0; $m_out = 0;
try {
    $s = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='deposit' AND status='completed' THEN amount ELSE 0 END),0) AS m_in,
            COALESCE(SUM(CASE WHEN type IN('withdrawal','transfer','payment') AND status='completed'
            THEN amount ELSE 0 END),0) AS m_out FROM transactions
            WHERE user_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) ");

            $s->execute([$user_id]);
            $ms   = $s->fetch(PDO::FETCH_ASSOC);
            $m_in  = (float)$ms['m_in'];
            $m_out = (float)$ms['m_out'];
} catch(PDOException $e) {}

$net = $m_in - $m_out;

// Active card
$card = null;
try {
    $s = $pdo->prepare("SELECT * FROM cards WHERE user_id=? AND status='active' ORDER BY created_at DESC LIMIT 1");
    $s->execute([$user_id]);
    $card = $s->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Recent 7 transactions
$recent_txns = [];
try {
    $s = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 7");
    $s->execute([$user_id]);
    $recent_txns = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}




// 6-month chart data
$chart_data = [];
try {
    $s = $pdo->prepare(" SELECT DATE_FORMAT(created_at,'%b') AS mon, DATE_FORMAT(created_at,'%Y-%m') AS key_m,
            COALESCE(SUM(CASE WHEN type='deposit' AND status='completed' THEN amount ELSE 0 END),0) AS income,
            COALESCE(SUM(CASE WHEN type IN('withdrawal','transfer','payment') AND status='completed' 
            THEN amount ELSE 0 END),0) AS expense FROM transactions
            WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY key_m, mon ORDER BY key_m ASC
    ");
    $s->execute([$user_id]);
    $chart_data = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Pending transfers count
$pending_count = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM transfers WHERE sender_id=? AND status='pending'");
    $s->execute([$user_id]);
    $pending_count = (int)$s->fetchColumn();
} catch(PDOException $e) {}

$hour     = (int)date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$balance  = getBalance($pdo, $user_id);
?>

<div class="page-title"><?php echo $greeting; ?>, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</div>
<div class="page-subtitle">
    Here's your financial overview for <?php echo date('l, d F Y'); ?>
</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><i class="ri-checkbox-circle-line"></i><span><?php echo $_SESSION['success_message']; ?></span></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if($user['status'] === 'pending_kyc'): ?>
    <div class="alert alert-warning">
        <i class="ri-time-line"></i>
        <span>Your account is pending KYC approval. Some features are limited until our team reviews your documents (1–2 business days).
        <a href="<?php echo BASE_URL; ?>support" style="color:inherit;font-weight:700;">Contact Support →</a></span>
    </div>
<?php endif; ?>

<?php if($pending_count > 0): ?>
    <div class="alert alert-info">
        <i class="ri-information-line"></i>
        <span>You have <strong><?php echo $pending_count; ?></strong> pending transfer<?php echo $pending_count > 1 ? 's' : ''; ?> being processed.
        <a href="transactions?status=pending" style="color:inherit;font-weight:700;">View Status →</a></span>
    </div>
<?php endif; ?>

<!-- ============================================================ STAT CARDS ============================================================ -->
<div class="stat-grid">
    <div class="stat-card sc-green">
        <div class="sc-icon"><i class="ri-wallet-3-line"></i></div>
        <div class="sc-lbl">Available Balance</div>
        <div class="sc-val"><?php echo formatMoney($balance); ?></div>
        <div class="sc-sub"><?php echo htmlspecialchars($user['account_number']); ?></div>
    </div>
    <div class="stat-card sc-blue">
        <div class="sc-icon"><i class="ri-arrow-down-circle-line"></i></div>
        <div class="sc-lbl">Monthly Received</div>
        <div class="sc-val" style="color:var(--green);">+<?php echo formatMoney($m_in); ?></div>
        <div class="sc-sub"><?php echo date('F Y'); ?></div>
    </div>
    <div class="stat-card sc-red">
        <div class="sc-icon"><i class="ri-arrow-up-circle-line"></i></div>
        <div class="sc-lbl">Monthly Sent</div>
        <div class="sc-val" style="color:var(--red);">-<?php echo formatMoney($m_out); ?></div>
        <div class="sc-sub"><?php echo date('F Y'); ?></div>
    </div>
    <div class="stat-card sc-purple">
        <div class="sc-icon"><i class="ri-bar-chart-2-line"></i></div>
        <div class="sc-lbl">Net This Month</div>
        <div class="sc-val" style="color:<?php echo $net >= 0 ? 'var(--green)' : 'var(--red)'; ?>;">
            <?php echo ($net >= 0 ? '+' : '-') . formatMoney(abs($net)); ?>
        </div>
        <div class="sc-sub"><?php echo $net >= 0 ? 'Surplus' : 'Deficit'; ?> this month</div>
    </div>
</div>

<!-- ============================================================ MAIN GRID ============================================================ -->
<div class="dash-grid">

    <!-- LEFT -->
    <div class="left-col">

        <!-- Bank Card Visual -->
        <?php if($card): ?>
        <div class="bank-card-vis" onclick="window.location='cards'">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;z-index:1;position:relative;">
                <div>
                    <div style="font-size:10px;opacity:.6;text-transform:uppercase;letter-spacing:1px;">
                        <?php echo strtoupper($card['card_type'].' · '.$card['network']); ?>
                    </div>
                    <div style="font-size:11px;margin-top:5px;background:rgba(16,185,129,.25);color:#6ee7b7;padding:2px 8px;border-radius:4px;display:inline-block;font-weight:600;">
                        ● ACTIVE
                    </div>
                </div>
                <span style="font-size:20px;font-weight:800;font-style:italic;opacity:.7;">
                    <?php echo strtoupper($card['network']) === 'VISA' ? 'VISA' : 'MC'; ?>
                </span>
            </div>
            <div class="bcv-num">
                <?php echo substr($card['card_number'],0,4).' **** **** '.substr($card['card_number'],-4); ?>
            </div>
            <div class="bcv-bot">
                <div>
                    <div class="bcv-lbl">Cardholder</div>
                    <div class="bcv-val"><?php echo htmlspecialchars(strtoupper($full_name)); ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="bcv-lbl">Expires</div>
                    <div class="bcv-val"><?php echo date('m/Y',strtotime($card['expiry_date'])); ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="bcv-lbl">Daily Limit</div>
                    <div class="bcv-val"><?php echo formatMoney($card['daily_limit']); ?></div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="bank-card-vis" style="align-items:center;justify-content:center;text-align:center;" onclick="window.location='cards'">
            <i class="ri-bank-card-line" style="font-size:48px;opacity:.4;"></i>
            <div style="font-size:14px;margin-top:10px;opacity:.7;">No Active Card</div>
            <div style="font-size:12px;opacity:.5;margin-top:4px;">Tap to request a card</div>
        </div>
        <?php endif; ?>

        <!-- Account Info -->
        <div class="acct-box">
            <div style="font-size:12px;opacity:.7;font-weight:700;margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px;">
                Account Details
            </div>
            <?php
            $ai_rows = [
                ['Account No.',    $user['account_number']],
                ['Account Name',   $acc['account_name']   ?? strtoupper($full_name)],
                ['Type',           ($acc['account_type']  ?? 'Checking') . ' Account'],
                ['Routing',        $acc['routing_number'] ?? '—'],
                ['Wire Routing',   $acc['wire_routing']   ?? '—'],
                ['SWIFT',          $acc['swift_code']     ?? BANK_SWIFT],
                ['Status',         ucfirst($user['status'])],
            ];
            foreach($ai_rows as [$l,$v]):
            ?>
            <div class="ai-row">
                <span class="ai-lbl"><?php echo $l; ?></span>
                <span class="ai-val"><?php echo htmlspecialchars($v); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT -->
    <div class="right-col">

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-title"><i class="ri-apps-line"></i> Quick Actions</div>
            <div class="qa-grid">
                <?php
                $actions = [
                    ['transfer',      'ri-send-plane-line',         'Send Money'],
                    ['receive',       'ri-download-2-line',         'Receive'],
                   
                    ['transactions',  'ri-file-list-3-line',        'History'],
                    ['cards',         'ri-bank-card-line',          'My Cards'],
                    ['beneficiaries', 'ri-user-star-line',          'Beneficiaries'],
                   
                    ['support',       'ri-customer-service-2-line', 'Support'],
                ];
                foreach($actions as [$url,$icon,$label]):
                ?>
                <a href="<?php echo $url; ?>" class="qa-btn">
                    <i class="<?php echo $icon; ?>"></i>
                    <span><?php echo $label; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Monthly Chart -->
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div class="card-title" style="margin:0;"><i class="ri-bar-chart-line"></i> Monthly Activity</div>
                <div style="display:flex;gap:14px;font-size:11px;color:var(--text-muted);">
                    <span><span style="display:inline-block;width:10px;height:10px;background:var(--green);border-radius:2px;margin-right:4px;"></span>In</span>
                    <span><span style="display:inline-block;width:10px;height:10px;background:var(--red);border-radius:2px;margin-right:4px;"></span>Out</span>
                </div>
            </div>


            <?php if(empty($chart_data)): ?>
                <div style="text-align:center;padding:32px;color:var(--text-muted);font-size:13px;">
                    <i class="ri-bar-chart-line" style="font-size:32px;display:block;margin-bottom:8px;color:var(--border);"></i>
                    No transaction data yet
                </div>
            <?php else: ?>
                <?php
                $max_v = 1;
                foreach($chart_data as $d) { $max_v = max($max_v, $d['income'], $d['expense']); }
                ?>
                <div class="chart-wrap">
                    <?php foreach($chart_data as $d):
                        $ih = max(4, round(($d['income']  / $max_v) * 110));
                        $eh = max(4, round(($d['expense'] / $max_v) * 110));
                    ?>
                    <div class="bar-grp">
                        <div class="bar-pair">
                            <div class="bar-in"  style="height:<?php echo $ih; ?>px;" title="+<?php echo formatMoney($d['income']); ?>"></div>
                            <div class="bar-out" style="height:<?php echo $eh; ?>px;" title="-<?php echo formatMoney($d['expense']); ?>"></div>
                        </div>
                        <div class="bar-lbl"><?php echo htmlspecialchars($d['mon']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>




        </div>

    </div>
</div>

<!-- ============================================================ RECENT TRANSACTIONS ============================================================ -->

<?php
$recent_txns = [];
try {
    $s = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 8");
    $s->execute([$user_id]);
    $recent_txns = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}
?>

<div class="card">
    <div class="card-title d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <span><i class="ri-exchange-line" style="color:var(--gold);"></i> Recent Transactions</span>
        <a href="transactions" style="font-size:12px;color:var(--blue);font-weight:600;text-decoration:none;">
            View All <i class="ri-arrow-right-s-line"></i>
        </a>
    </div>

    <?php if(empty($recent_txns)): ?>
        <div style="text-align:center;padding:32px;color:var(--text-muted);">
            <i class="ri-inbox-line" style="font-size:38px;display:block;margin-bottom:10px;opacity:.3;"></i>
            <div style="font-size:13px;">No transactions yet.</div>
            <a href="transfer" class="btn btn-primary" style="height:36px;margin-top:12px;font-size:12px;padding:0 16px;">
                <i class="ri-send-plane-line"></i> Send Money
            </a>
        </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1px;">
        <?php foreach($recent_txns as $t):
            $is_credit = dashIsCredit($t);
            $amount    = (float)$t['amount'];
            $sign      = $is_credit ? '+' : '−';
            $color     = $is_credit ? '#059669' : '#dc2626';
            $bg        = $is_credit ? '#d1fae5' : '#fee2e2';
            $icon      = $is_credit ? 'ri-arrow-down-circle-fill' : 'ri-arrow-up-circle-fill';
            $type_lbl  = match(strtolower($t['type']??'')) {
                'credit','receive','received' => 'Received',
                'deposit'                     => 'Deposit',
                'refund'                      => 'Refund',
                'transfer'                    => 'Transfer',
                'withdrawal'                  => 'Withdrawal',
                'debit'                       => 'Debit',
                default                       => ucfirst($t['type']??'Transaction'),
            };
            $desc = trim($t['description'] ?? '') ?: $type_lbl;
            $status = $t['status'] ?? 'completed';
        ?>
        <div style="display:flex;align-items:center;gap:12px;padding:11px 4px;border-bottom:1px solid var(--border);">
            <!-- Icon -->
            <div style="width:38px;height:38px;border-radius:10px;background:<?php echo $bg; ?>;
                        color:<?php echo $color; ?>;display:flex;align-items:center;justify-content:center;
                        font-size:17px;flex-shrink:0;">
                <i class="<?php echo $icon; ?>"></i>
            </div>
            <!-- Info -->
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:700;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-bottom:2px;">
                    <?php echo htmlspecialchars($desc); ?>
                </div>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                    <span style="font-size:10px;font-weight:700;background:<?php echo $bg; ?>;color:<?php echo $color; ?>;padding:1px 8px;border-radius:5px;">
                        <?php echo $type_lbl; ?>
                    </span>
                    <span style="font-size:10px;color:var(--text-muted);">
                        <?php echo date('d M, H:i', strtotime($t['created_at'])); ?>
                    </span>
                    <?php if($status === 'pending'): ?>
                        <span style="font-size:10px;background:#fef3c7;color:#d97706;padding:1px 7px;border-radius:5px;font-weight:700;">Pending</span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Amount — green + for received, red − for sent -->
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:15px;font-weight:800;color:<?php echo $color; ?>;white-space:nowrap;">
                    <?php echo $sign.formatMoney($amount); ?>
                </div>
                <?php if(!empty($t['balance_after'])): ?>
                <div style="font-size:10px;color:var(--text-muted);">
                    Bal: <?php echo formatMoney((float)$t['balance_after']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="padding:12px 0 0;text-align:center;">
        <a href="transactions" class="btn btn-secondary btn-block" style="height:38px;font-size:13px;">
            <i class="ri-file-list-3-line"></i> View All Transactions
        </a>
    </div>
    <?php endif; ?>
</div>




<?php include 'layout/customer-footer.php'; ?>