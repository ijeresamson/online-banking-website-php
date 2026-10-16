<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login"); 
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$page_title = 'Transaction History';

// ============================================================
// FETCH ACCOUNT
// ============================================================
$account = getAccountFull($pdo, $user_id);
$balance = (float)($account['balance'] ?? 0);
$acc_no  = $account['account_number'] ?? '';

// ============================================================
// FILTERS
// ============================================================
$filter_type  = $_GET['type']   ?? 'all';
$filter_month = $_GET['month']  ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 15;
$offset       = ($page - 1) * $per_page;

// ============================================================
// DETERMINE IF A TRANSACTION IS CREDIT (money IN)
// Key fix: check balance_after > balance_before OR type is credit/deposit/receive
// ============================================================
function isCreditTxn(array $t, int $user_id): bool {
    // Explicit credit types
    $credit_types = ['credit','deposit','receive','received','refund','reversal','wire_received'];
    if(in_array(strtolower($t['type'] ?? ''), $credit_types)) return true;

    // Balance went up = money came in
    $before = (float)($t['balance_before'] ?? 0);
    $after  = (float)($t['balance_after']  ?? 0);
    if($after > $before && $before >= 0) return true;

    // If recipient_account matches our own account = incoming transfer
    if(!empty($t['recipient_account']) && $t['recipient_account'] === ($t['sender_account'] ?? '***NEVER***')) return true;

    return false;
}

// ============================================================
// BUILD WHERE CLAUSE
// ============================================================
$where  = ["t.user_id = ?"];
$params = [$user_id];

// Type filter — group credit types together
if($filter_type === 'credit') {
    $where[]  = "t.type IN('credit','deposit','receive','received','refund','reversal')";
} elseif($filter_type === 'debit') {
    $where[]  = "t.type IN('debit','transfer','withdrawal','payment')";
} elseif($filter_type !== 'all') {
    $where[]  = "t.type = ?";
    $params[] = $filter_type;
}

if(!empty($filter_month)) {
    $where[]  = "DATE_FORMAT(t.created_at,'%Y-%m') = ?";
    $params[] = $filter_month;
}

if(!empty($search)) {
    $where[]  = "(t.description LIKE ? OR t.reference LIKE ? OR t.recipient_name LIKE ? OR t.recipient_account LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params, [$sq,$sq,$sq,$sq]);
}

$where_sql = implode(' AND ', $where);

// Count total
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE {$where_sql}");
$cnt_stmt->execute($params);
$total_rows  = (int)$cnt_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// Fetch transactions
$stmt = $pdo->prepare("
    SELECT t.*,
           COALESCE(t.balance_after, 0)  AS balance_after,
           COALESCE(t.balance_before, 0) AS balance_before
    FROM transactions t
    WHERE {$where_sql}
    ORDER BY t.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Summary stats (current month)
$stats = [];
try {
    $s = $pdo->prepare("SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN type IN('credit','deposit','receive','received') OR balance_after > balance_before THEN amount ELSE 0 END) AS total_in,
            SUM(CASE WHEN type IN('debit','transfer','withdrawal') OR balance_after < balance_before      THEN amount ELSE 0 END) AS total_out,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_cnt
        FROM transactions
        WHERE user_id=? AND DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(NOW(),'%Y-%m')
    ");
    $s->execute([$user_id]);
    $stats = $s->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Available months for filter
$months = [];
try {
    $s = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(created_at,'%Y-%m') AS ym,
                        DATE_FORMAT(created_at,'%M %Y') AS label
        FROM transactions
        WHERE user_id=?
        ORDER BY ym DESC
        LIMIT 24
    ");
    $s->execute([$user_id]);
    $months = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Type config — determines how each type is displayed
function txnDisplay(array $t, int $user_id): array {
    $type = strtolower($t['type'] ?? 'debit');
    $is_credit = isCreditTxn($t, $user_id);

    if($is_credit) {
        return [
            'sign'       => '+',
            'amt_color'  => '#059669',
            'amt_bg'     => '#f0fff4',
            'icon'       => 'ri-arrow-down-circle-fill',
            'icon_color' => '#059669',
            'icon_bg'    => '#d1fae5',
            'label'      => match($type) {
                'deposit'   => 'Deposit',
                'credit'    => 'Credit',
                'refund'    => 'Refund',
                'reversal'  => 'Reversal',
                default     => 'Received',
            },
        ];
    }

    return [
        'sign'       => '−',
        'amt_color'  => '#dc2626',
        'amt_bg'     => '#fff5f5',
        'icon'       => match($type) {
            'transfer'   => 'ri-arrow-up-circle-fill',
            'withdrawal' => 'ri-arrow-right-circle-fill',
            'payment'    => 'ri-secure-payment-line',
            default      => 'ri-arrow-up-circle-fill',
        },
        'icon_color' => '#dc2626',
        'icon_bg'    => '#fee2e2',
        'label'      => match($type) {
            'transfer'   => 'Transfer Sent',
            'withdrawal' => 'Withdrawal',
            'debit'      => 'Debit',
            'payment'    => 'Payment',
            default      => 'Debit',
        },
    ];
}

// Status pill
function txnStatusPill(string $status): string {
    [$bg, $col, $lbl] = match($status) {
        'completed' => ['#d1fae5','#059669','Completed'],
        'pending'   => ['#fef3c7','#d97706','Pending'],
        'failed'    => ['#fee2e2','#dc2626','Failed'],
        'reversed'  => ['#ede9fe','#7c3aed','Reversed'],
        default     => ['#f3f4f6','#6b7280', ucfirst($status)],
    };
    return "<span style='background:{$bg};color:{$col};padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;'>{$lbl}</span>";
}

// Group transactions by date
$grouped = [];
foreach($transactions as $t) {
    $day = date('Y-m-d', strtotime($t['created_at']));
    $grouped[$day][] = $t;
}

$extra_css = '
/* ============================================================
   TRANSACTIONS PAGE — MIDWESTERNBANK
============================================================ */

/* ── STAT CARDS ── */
.stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
@media(max-width:800px){ .stat-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:450px){ .stat-grid{ grid-template-columns:1fr 1fr; gap:10px; } }

.stat-card { background:#fff; border-radius:12px; padding:16px; border:1px solid var(--border); box-shadow:var(--shadow); }
.stat-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:10px; }
.stat-val  { font-size:18px; font-weight:800; color:var(--navy); margin-bottom:2px; line-height:1.1; }
.stat-lbl  { font-size:11px; color:var(--text-muted); font-weight:500; }

/* ── FILTERS ── */
.filter-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
.filter-bar input, .filter-bar select {
    height:38px; border:1.5px solid var(--border); border-radius:9px;
    padding:0 12px; font-size:13px; font-family:"Inter",sans-serif;
    color:var(--text); background:#fff; outline:none; transition:border-color .2s;
}
.filter-bar input:focus, .filter-bar select:focus { border-color:var(--blue); }
.filter-bar .filter-search { flex:1; min-width:180px; padding-left:36px; }
.search-wrap { position:relative; flex:1; min-width:180px; }
.search-wrap i { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:15px; pointer-events:none; }

/* ── TYPE PILLS ── */
.type-filters { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:18px; }
.type-pill { padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; border:1.5px solid var(--border); background:#fff; color:var(--text-muted); text-decoration:none; transition:all .18s; display:inline-flex; align-items:center; gap:5px; white-space:nowrap; }
.type-pill:hover    { border-color:var(--navy); color:var(--navy); }
.type-pill.active   { background:var(--navy); color:#fff; border-color:var(--navy); }
.type-pill.credit.active { background:#059669; border-color:#059669; }
.type-pill.debit.active  { background:#dc2626; border-color:#dc2626; }

/* ── DAY HEADER ── */
.day-header {
    display:flex; align-items:center; gap:10px;
    margin:20px 0 10px; padding:0 4px;
}
.day-label { font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
.day-line  { flex:1; height:1px; background:var(--border); }
.day-total { font-size:11px; color:var(--text-muted); white-space:nowrap; }

/* ── TRANSACTION ROW ── */
.txn-list { display:flex; flex-direction:column; gap:8px; }

.txn-row {
    background:#fff; border:1px solid var(--border); border-radius:12px;
    padding:14px 16px; display:flex; align-items:center; gap:14px;
    transition:all .18s; cursor:pointer; text-decoration:none; color:inherit;
}
.txn-row:hover { border-color:var(--navy); box-shadow:0 2px 12px rgba(12,35,64,.08); transform:translateX(2px); }

.txn-icon { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }

.txn-info { flex:1; min-width:0; }
.txn-desc { font-size:13px; font-weight:700; color:var(--navy); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom:3px; }
.txn-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.txn-ref  { font-size:10px; font-family:monospace; color:var(--gold); background:#fef9e7; padding:1px 7px; border-radius:4px; }
.txn-time { font-size:11px; color:var(--text-muted); }

.txn-right { flex-shrink:0; text-align:right; }
.txn-amount { font-size:16px; font-weight:800; white-space:nowrap; }
.txn-balance { font-size:10px; color:var(--text-muted); margin-top:3px; white-space:nowrap; }

/* ── EXPANDABLE DETAIL ── */
.txn-detail {
    display:none; background:#f8faff; border-top:1px solid var(--border);
    padding:12px 16px; border-radius:0 0 12px 12px;
    font-size:12px; color:var(--text-muted);
}
.txn-detail.open { display:block; }
.txn-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
@media(max-width:500px){ .txn-detail-grid{ grid-template-columns:1fr; } }
.txn-detail-item { display:flex; flex-direction:column; gap:2px; }
.txn-detail-lbl  { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text-muted); }
.txn-detail-val  { font-size:13px; font-weight:600; color:var(--navy); word-break:break-all; }

/* ── EMPTY STATE ── */
.txn-empty { text-align:center; padding:60px 20px; }

/* ── PAGINATION ── */
.pagination-bar { display:flex; justify-content:center; align-items:center; gap:6px; flex-wrap:wrap; margin-top:24px; }
.pg-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:9px; border:1.5px solid var(--border); background:#fff; font-size:13px; font-weight:600; color:var(--navy); text-decoration:none; transition:all .18s; }
.pg-btn:hover  { background:var(--navy); color:#fff; border-color:var(--navy); }
.pg-btn.active { background:var(--navy); color:#fff; border-color:var(--navy); }
.pg-btn.disabled { opacity:.4; pointer-events:none; }

/* ── RESPONSIVE ── */
@media(max-width:600px){
    .txn-row { padding:11px 12px; gap:10px; }
    .txn-icon { width:36px; height:36px; font-size:16px; border-radius:9px; }
    .txn-amount { font-size:14px; }
    .txn-desc { font-size:12px; }
    .filter-bar select { font-size:12px; }
    .stat-val { font-size:16px; }
}

/* ── EXPORT BUTTON ── */
.export-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:9px; border:1.5px solid var(--border); background:#fff; font-size:12px; font-weight:600; color:var(--navy); cursor:pointer; text-decoration:none; transition:all .18s; }
.export-btn:hover { background:var(--navy); color:#fff; border-color:var(--navy); }
';

include 'layout/customer-header.php';
?>

<!-- PAGE HEADER -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:6px;">
    <div>
        <div class="page-title" style="margin-bottom:2px;">Transaction History</div>
        <div class="page-subtitle">
            All transactions on account
            <span style="font-family:monospace;color:var(--gold);font-weight:700;"><?php echo htmlspecialchars($acc_no); ?></span>
        </div>
    </div>
    <a href="statements" class="export-btn">
        <i class="ri-file-download-line"></i> Statements
    </a>
</div>

<!-- STAT CARDS -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#f0f4ff;color:var(--navy);"><i class="ri-wallet-3-line"></i></div>
        <div class="stat-val"><?php echo formatMoney($balance); ?></div>
        <div class="stat-lbl">Current Balance</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f0fff4;color:#059669;"><i class="ri-arrow-down-circle-line"></i></div>
        <div class="stat-val" style="color:#059669;">+<?php echo formatMoney($stats['total_in'] ?? 0); ?></div>
        <div class="stat-lbl">Money In (This Month)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fff5f5;color:#dc2626;"><i class="ri-arrow-up-circle-line"></i></div>
        <div class="stat-val" style="color:#dc2626;">−<?php echo formatMoney($stats['total_out'] ?? 0); ?></div>
        <div class="stat-lbl">Money Out (This Month)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="ri-time-line"></i></div>
        <div class="stat-val" style="color:#d97706;"><?php echo $stats['pending_cnt'] ?? 0; ?></div>
        <div class="stat-lbl">Pending This Month</div>
    </div>
</div>

<!-- TYPE FILTER PILLS -->
<div class="type-filters">
    <?php
    $type_links = [
        ['all',      'All',       '',                    ''],
        ['credit',   '+ Received','credit',              ''],
        ['debit',    '− Sent',    'debit',               ''],
        ['transfer', 'Transfers', '',                    ''],
        ['deposit',  'Deposits',  '',                    ''],
        ['withdrawal','Withdrawals','',                  ''],
    ];
    foreach($type_links as [$val, $lbl, $extra_cls, $_]):
        $params_link = array_merge($_GET, ['type'=>$val, 'page'=>1]);
        $active_cls  = $filter_type === $val ? 'active '.$extra_cls : '';
    ?>
    <a href="?<?php echo http_build_query($params_link); ?>"
       class="type-pill <?php echo $active_cls; ?> <?php echo $extra_cls; ?>">
        <?php echo $lbl; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- FILTERS BAR -->
<form method="GET">
    <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>">
    <div class="filter-bar">
        <!-- Search -->
        <div class="search-wrap">
            <i class="ri-search-line"></i>
            <input type="text" name="q" class="filter-search"
                   placeholder="Search by description, reference, name..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <!-- Month -->
        <select name="month">
            <option value="">All Months</option>
            <?php foreach($months as $m): ?>
                <option value="<?php echo htmlspecialchars($m['ym']); ?>"
                        <?php echo $filter_month===$m['ym']?'selected':''; ?>>
                    <?php echo htmlspecialchars($m['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <!-- Apply -->
        <button type="submit" class="btn btn-primary" style="height:38px;padding:0 16px;font-size:13px;flex-shrink:0;">
            <i class="ri-filter-line"></i> Filter
        </button>
        <!-- Clear -->
        <?php if($search || $filter_month || $filter_type !== 'all'): ?>
        <a href="transactions" class="btn btn-secondary" style="height:38px;padding:0 12px;font-size:13px;flex-shrink:0;">
            <i class="ri-close-line"></i> Clear
        </a>
        <?php endif; ?>
    </div>
</form>

<!-- RESULTS COUNT -->
<div style="font-size:12px;color:var(--text-muted);margin-bottom:16px;">
    Showing <strong><?php echo number_format($total_rows); ?></strong> transaction<?php echo $total_rows != 1 ? 's' : ''; ?>
    <?php if($filter_type !== 'all' || $search || $filter_month): ?>
        (filtered)
    <?php endif; ?>
</div>

<!-- ============================== TRANSACTION LIST ============================== -->
<?php if(empty($transactions)): ?>
<div class="card">
    <div class="txn-empty">
        <div style="font-size:54px;margin-bottom:12px;opacity:.3;">💳</div>
        <div style="font-size:15px;font-weight:700;color:var(--navy);margin-bottom:8px;">No Transactions Found</div>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:20px;max-width:320px;margin-left:auto;margin-right:auto;">
            <?php if($search || $filter_month || $filter_type !== 'all'): ?>
                No transactions match your filters. Try adjusting your search or clearing filters.
            <?php else: ?>
                You have no transactions yet. Start by making a transfer or receiving funds.
            <?php endif; ?>
        </div>
        <?php if($search || $filter_month || $filter_type !== 'all'): ?>
            <a href="transactions" class="btn btn-secondary" style="height:40px;padding:0 20px;">
                <i class="ri-close-line"></i> Clear Filters
            </a>
        <?php else: ?>
            <a href="transfer" class="btn btn-primary" style="height:40px;padding:0 24px;">
                <i class="ri-send-plane-line"></i> Send Money
            </a>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<?php foreach($grouped as $day => $day_txns):
    $date_obj  = new DateTime($day);
    $today     = new DateTime('today');
    $yesterday = new DateTime('yesterday');

    if($date_obj == $today)         $day_label = 'Today';
    elseif($date_obj == $yesterday) $day_label = 'Yesterday';
    else                            $day_label = $date_obj->format('l, d M Y');

    // Day totals
    $day_in  = 0;
    $day_out = 0;
    foreach($day_txns as $dt) {
        if(isCreditTxn($dt, $user_id)) $day_in  += (float)$dt['amount'];
        else                            $day_out += (float)$dt['amount'];
    }
?>

<!-- Day Group Header -->
<div class="day-header">
    <span class="day-label"><?php echo $day_label; ?></span>
    <div class="day-line"></div>
    <span class="day-total">
        <?php if($day_in > 0): ?>
            <span style="color:#059669;font-weight:700;">+<?php echo formatMoney($day_in); ?></span>
        <?php endif; ?>
        <?php if($day_in > 0 && $day_out > 0): ?> / <?php endif; ?>
        <?php if($day_out > 0): ?>
            <span style="color:#dc2626;font-weight:700;">−<?php echo formatMoney($day_out); ?></span>
        <?php endif; ?>
    </span>
</div>

<div class="txn-list">
<?php foreach($day_txns as $t):
    $is_credit = isCreditTxn($t, $user_id);
    $disp      = txnDisplay($t, $user_id);
    $status    = $t['status'] ?? 'completed';
    $txn_id    = 'txn-'.$t['id'];

    // Description: use transaction description, fallback to type label
    $desc = trim($t['description'] ?? '');
    if(empty($desc)) $desc = $disp['label'];

    // Counterparty label
    $counterparty = '';
    if($is_credit && !empty($t['recipient_name'])) {
        $counterparty = 'From: '.$t['recipient_name'];
    } elseif(!$is_credit && !empty($t['recipient_name'])) {
        $counterparty = 'To: '.$t['recipient_name'];
    }

    // Amount display
    $amount_str = $disp['sign'].formatMoney((float)$t['amount']);
?>
<div style="border-radius:12px;border:1px solid var(--border);overflow:hidden;background:#fff;">

    <!-- Main Row -->
    <div class="txn-row" style="border:none;border-radius:0;"
         onclick="toggleDetail('<?php echo $txn_id; ?>')">

        <!-- Icon -->
        <div class="txn-icon"
             style="background:<?php echo $disp['icon_bg']; ?>;color:<?php echo $disp['icon_color']; ?>;">
            <i class="<?php echo $disp['icon']; ?>"></i>
        </div>

        <!-- Info -->
        <div class="txn-info">
            <div class="txn-desc"><?php echo htmlspecialchars($desc); ?></div>
            <div class="txn-meta">
                <!-- Type Label -->
                <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:5px;
                             background:<?php echo $disp['amt_bg']; ?>;color:<?php echo $disp['amt_color']; ?>;">
                    <?php echo $disp['label']; ?>
                </span>
                <?php if(!empty($t['reference'])): ?>
                    <span class="txn-ref"><?php echo htmlspecialchars($t['reference']); ?></span>
                <?php endif; ?>
                <span class="txn-time"><?php echo date('H:i', strtotime($t['created_at'])); ?></span>
                <?php echo txnStatusPill($status); ?>
            </div>
            <?php if(!empty($counterparty)): ?>
            <div style="font-size:11px;color:var(--text-muted);margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?php echo htmlspecialchars($counterparty); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Amount -->
        <div class="txn-right">
            <div class="txn-amount" style="color:<?php echo $disp['amt_color']; ?>;">
                <?php echo $amount_str; ?>
            </div>
            <?php if(!empty($t['balance_after'])): ?>
            <div class="txn-balance">
                Bal: <?php echo formatMoney((float)$t['balance_after']); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Expand arrow -->
        <i class="ri-arrow-down-s-line" id="arr-<?php echo $txn_id; ?>"
           style="font-size:18px;color:var(--text-muted);flex-shrink:0;transition:transform .2s;"></i>

    </div>

    <!-- Expandable Detail -->
    <div class="txn-detail" id="<?php echo $txn_id; ?>">
        <div class="txn-detail-grid">

            <?php if(!empty($t['reference'])): ?>
            <div class="txn-detail-item">
                <div class="txn-detail-lbl">Reference</div>
                <div class="txn-detail-val" style="font-family:monospace;color:var(--gold);">
                    <?php echo htmlspecialchars($t['reference']); ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="txn-detail-item">
                <div class="txn-detail-lbl">Transaction Type</div>
                <div class="txn-detail-val"><?php echo $disp['label']; ?></div>
            </div>

            <div class="txn-detail-item">
                <div class="txn-detail-lbl">Date &amp; Time</div>
                <div class="txn-detail-val"><?php echo date('d M Y, H:i:s', strtotime($t['created_at'])); ?></div>
            </div>

            <div class="txn-detail-item">
                <div class="txn-detail-lbl">Amount</div>
                <div class="txn-detail-val" style="color:<?php echo $disp['amt_color']; ?>;font-size:15px;">
                    <?php echo $amount_str; ?>
                </div>
            </div>

            <?php if(!empty($t['balance_before'])): ?>
            <div class="txn-detail-item">
                <div class="txn-detail-lbl">Balance Before</div>
                <div class="txn-detail-val"><?php echo formatMoney((float)$t['balance_before']); ?></div>
            </div>
            <?php endif; ?>

            <?php if(!empty($t['balance_after'])): ?>
            <div class="txn-detail-item">
                <div class="txn-detail-lbl">Balance After</div>
                <div class="txn-detail-val"><?php echo formatMoney((float)$t['balance_after']); ?></div>
            </div>
            <?php endif; ?>

            <?php if(!empty($t['recipient_name'])): ?>
            <div class="txn-detail-item">
                <div class="txn-detail-lbl"><?php echo $is_credit ? 'Sender' : 'Recipient'; ?></div>
                <div class="txn-detail-val"><?php echo htmlspecialchars($t['recipient_name']); ?></div>
            </div>
            <?php endif; ?>

            <?php if(!empty($t['recipient_account'])): ?>
            <div class="txn-detail-item">
                <div class="txn-detail-lbl">Account</div>
                <div class="txn-detail-val" style="font-family:monospace;">
                    <?php echo htmlspecialchars($t['recipient_account']); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(!empty($t['description'])): ?>
            <div class="txn-detail-item" style="grid-column:1/-1;">
                <div class="txn-detail-lbl">Description</div>
                <div class="txn-detail-val"><?php echo htmlspecialchars($t['description']); ?></div>
            </div>
            <?php endif; ?>

        </div>

        <!-- Actions inside detail -->
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
            <a href="support?subject=<?php echo urlencode('Query on '.$t['reference']); ?>"
               class="btn btn-secondary" style="height:32px;font-size:11px;padding:0 12px;">
                <i class="ri-question-line"></i> Query This Transaction
            </a>
            <?php if($status === 'pending'): ?>
            <span style="font-size:11px;color:#d97706;display:flex;align-items:center;gap:4px;padding:0 4px;">
                <i class="ri-time-line"></i> Processing — balance will update on approval
            </span>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php endforeach; ?>
</div><!-- /.txn-list -->

<?php endforeach; // day groups ?>

<!-- PAGINATION -->
<?php if($total_pages > 1): ?>
<div class="pagination-bar">
    <?php if($page > 1): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>1])); ?>" class="pg-btn" title="First">«</a>
        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1])); ?>" class="pg-btn" title="Prev">‹</a>
    <?php else: ?>
        <span class="pg-btn disabled">«</span>
        <span class="pg-btn disabled">‹</span>
    <?php endif; ?>

    <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$i])); ?>"
           class="pg-btn <?php echo $i===$page?'active':''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php if($page < $total_pages): ?>
        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1])); ?>" class="pg-btn" title="Next">›</a>
        <a href="?<?php echo http_build_query(array_merge($_GET,['page'=>$total_pages])); ?>" class="pg-btn" title="Last">»</a>
    <?php else: ?>
        <span class="pg-btn disabled">›</span>
        <span class="pg-btn disabled">»</span>
    <?php endif; ?>

    <span style="font-size:12px;color:var(--text-muted);margin-left:4px;">
        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
    </span>
</div>
<?php endif; ?>

<?php endif; // end if transactions ?>

<?php
$extra_js = "
// ── Toggle transaction detail
function toggleDetail(id) {
    const detail = document.getElementById(id);
    const arrow  = document.getElementById('arr-'+id);
    if(!detail) return;
    const isOpen = detail.classList.contains('open');
    // Close all others
    document.querySelectorAll('.txn-detail.open').forEach(d => {
        d.classList.remove('open');
        const a = document.getElementById('arr-'+d.id);
        if(a) a.style.transform = '';
    });
    if(!isOpen) {
        detail.classList.add('open');
        if(arrow) arrow.style.transform = 'rotate(180deg)';
    }
}
// Close on ESC
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') {
        document.querySelectorAll('.txn-detail.open').forEach(d => {
            d.classList.remove('open');
            const a = document.getElementById('arr-'+d.id);
            if(a) a.style.transform = '';
        });
    }
});
";
include 'layout/customer-footer.php';
?>