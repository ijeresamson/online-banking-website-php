<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

// ============================================================
// DASHBOARD STATS
// ============================================================
try {

    // User stats
    $user_stats = $pdo->query(" SELECT
            COUNT(*)                              AS total,
            SUM(status = 'active')                AS active,
            SUM(status = 'pending_kyc')           AS pending_kyc,
            SUM(status = 'suspended')             AS suspended,
            SUM(status = 'closed')                AS closed,
            SUM(DATE(created_at) = CURDATE())     AS new_today
        FROM users
    ")->fetch(PDO::FETCH_ASSOC);

    // Financial stats
    $fin_stats = $pdo->query("SELECT
            COALESCE(SUM(a.balance), 0)                                                      AS total_balance,
            COALESCE(SUM(CASE WHEN t.type='deposit'    AND t.status='completed' THEN t.amount ELSE 0 END), 0) AS total_deposits,
            COALESCE(SUM(CASE WHEN t.type='withdrawal' AND t.status='completed' THEN t.amount ELSE 0 END), 0) AS total_withdrawals,
            COALESCE(SUM(CASE WHEN t.type='transfer'   AND t.status='completed' THEN t.amount ELSE 0 END), 0) AS total_transfers
        FROM accounts a
        LEFT JOIN transactions t ON t.user_id = a.user_id
    ")->fetch(PDO::FETCH_ASSOC);

    // Transaction stats
    $txn_stats = $pdo->query("SELECT
            COUNT(*)                             AS total,
            SUM(status = 'pending')              AS pending,
            SUM(status = 'completed')            AS completed,
            SUM(status = 'failed')               AS failed,
            COALESCE(SUM(CASE WHEN DATE(created_at)=CURDATE()  THEN amount ELSE 0 END), 0) AS today_vol,
            COALESCE(SUM(CASE WHEN MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) THEN amount ELSE 0 END), 0) AS month_vol
        FROM transactions
    ")->fetch(PDO::FETCH_ASSOC);

    // Pending actions counts
    $pending_transfers   = $pdo->query("SELECT COUNT(*) FROM transfers   WHERE status='pending'")->fetchColumn();
    $pending_withdrawals = $pdo->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
    $pending_kyc_count   = $pdo->query("SELECT COUNT(*) FROM users WHERE status='pending_kyc'")->fetchColumn();
    $pending_cards       = $pdo->query("SELECT COUNT(*) FROM card_requests WHERE status='pending'")->fetchColumn();

    $total_pending = $pending_transfers + $pending_withdrawals + $pending_kyc_count + $pending_cards;

    // Recent transactions
    $recent_txns = $pdo->query("SELECT t.*, u.first_name, u.last_name, u.account_number
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 8
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent registrations
    $recent_users = $pdo->query("SELECT u.*, COALESCE(a.balance, 0) AS balance
        FROM users u
        LEFT JOIN accounts a ON u.id = a.user_id
        ORDER BY u.created_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 6-month chart
    $monthly_data = $pdo->query("SELECT
            DATE_FORMAT(created_at,'%b')    AS mon,
            DATE_FORMAT(created_at,'%Y-%m') AS mon_key,
            COALESCE(SUM(CASE WHEN type='deposit'    AND status='completed' THEN amount ELSE 0 END), 0) AS deposits,
            COALESCE(SUM(CASE WHEN type='withdrawal' AND status='completed' THEN amount ELSE 0 END), 0) AS withdrawals,
            COALESCE(SUM(CASE WHEN type='transfer'   AND status='completed' THEN amount ELSE 0 END), 0) AS transfers
        FROM transactions
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY mon_key, mon
        ORDER BY mon_key ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Top accounts by balance
    $top_accounts = $pdo->query("SELECT u.first_name, u.last_name, u.account_number, a.balance
        FROM accounts a
        JOIN users u ON a.user_id = u.id
        WHERE u.status = 'active'
        ORDER BY a.balance DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log('Admin dashboard error: ' . $e->getMessage());
    $user_stats = $fin_stats = $txn_stats = [];
    $pending_transfers = $pending_withdrawals = $pending_kyc_count = $pending_cards = $total_pending = 0;
    $recent_txns = $recent_users = $monthly_data = $top_accounts = [];
}

$admin_name = $_SESSION['admin']['name'] ?? 'Admin';
$hour       = (int)date('H');
$greeting   = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <div>
        <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($admin_name); ?>!</h1>
        <div class="text-muted" style="font-size:14px;">
            <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('l, d F Y · H:i'); ?>
        </div>
    </div>
    <div>
        <a href="customer-pending-kyc" class="btn btn-warning btn-sm mr-2">
            <i class="fas fa-clock mr-1"></i> Pending KYC (<?php echo $pending_kyc_count; ?>)
        </a>
        <a href="transfers-pending" class="btn btn-danger btn-sm">
            <i class="fas fa-exchange-alt mr-1"></i> Pending Transfers (<?php echo $pending_transfers; ?>)
        </a>
    </div>
</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- ============================================================ ALERT BANNER ============================================================ -->
<?php if($total_pending > 0): ?>
<div class="alert alert-warning d-flex align-items-center flex-wrap gap-2 mb-4">
    <i class="fas fa-bell fa-lg mr-3"></i>
    <strong class="mr-3">Action Required:</strong>
    <?php if($pending_kyc_count > 0): ?>
        <a href="customer-pending-kyc" class="badge badge-warning badge-pill mr-2" style="font-size:13px;padding:6px 14px;text-decoration:none;">
            <?php echo $pending_kyc_count; ?> Pending KYC
        </a>
    <?php endif; ?>
    <?php if($pending_transfers > 0): ?>
        <a href="transfers-pending" class="badge badge-danger badge-pill mr-2" style="font-size:13px;padding:6px 14px;text-decoration:none;">
            <?php echo $pending_transfers; ?> Pending Transfers
        </a>
    <?php endif; ?>
    <?php if($pending_withdrawals > 0): ?>
        <a href="withdrawal" class="badge badge-info badge-pill mr-2" style="font-size:13px;padding:6px 14px;text-decoration:none;">
            <?php echo $pending_withdrawals; ?> Pending Withdrawals
        </a>
    <?php endif; ?>
    <?php if($pending_cards > 0): ?>
        <a href="cards-all?tab=requests" class="badge badge-secondary badge-pill" style="font-size:13px;padding:6px 14px;text-decoration:none;">
            <?php echo $pending_cards; ?> Card Requests
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============================================================ MAIN STAT CARDS ============================================================ -->
<div class="row mb-4">
    <?php
    $main_stats = [
        ['Total Customers',   number_format($user_stats['total'] ?? 0),               'bg-primary', 'fas fa-users',         'customer-all'],
        ['Total Balance',     '$'.number_format($fin_stats['total_balance'] ?? 0, 2), 'bg-success',  'fas fa-dollar-sign',   'accounts-all'],
        ['All Transactions',  number_format($txn_stats['total'] ?? 0),                'bg-info',     'fas fa-exchange-alt',  'transactions-all'],
        ['Pending Actions',   $total_pending,                                          'bg-warning',  'fas fa-exclamation',   'transfers-pending'],
    ];
    foreach($main_stats as [$label,$val,$bg,$icon,$link]):
    ?>
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="<?php echo $link; ?>" style="text-decoration:none;">
            <div class="card card-statistic-1 h-100" style="transition:transform .2s;cursor:pointer;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                <div class="card-icon <?php echo $bg; ?>"><i class="<?php echo $icon; ?>"></i></div>
                <div class="card-wrap">
                    <div class="card-header"><h4><?php echo $label; ?></h4></div>
                    <div class="card-body"><?php echo $val; ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- ============================================================ SECONDARY STATS ============================================================ -->
<div class="row mb-4">
    <?php
    $sec = [
        ['Active Customers',  number_format($user_stats['active']      ?? 0), 'bg-success',   'fas fa-user-check'],
        ['Pending KYC',       number_format($user_stats['pending_kyc'] ?? 0), 'bg-warning',   'fas fa-clock'],
        ['Suspended',         number_format($user_stats['suspended']   ?? 0), 'bg-danger',    'fas fa-ban'],
        ['New Today',         number_format($user_stats['new_today']   ?? 0), 'bg-primary',   'fas fa-user-plus'],
        ['Month Volume',      '$'.number_format($txn_stats['month_vol']?? 0, 2), 'bg-info',   'fas fa-chart-bar'],
        ['Today Volume',      '$'.number_format($txn_stats['today_vol']?? 0, 2), 'bg-secondary','fas fa-calendar-day'],
    ];
    foreach($sec as [$label,$val,$bg,$icon]):
    ?>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card card-statistic-1 h-100">
            <div class="card-icon <?php echo $bg; ?>" style="font-size:16px;width:36px;height:36px;"><i class="<?php echo $icon; ?>"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4 style="font-size:11px;"><?php echo $label; ?></h4></div>
                <div class="card-body" style="font-size:18px;"><?php echo $val; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ============================================================ CHARTS + QUICK ACTIONS ============================================================ -->
<div class="row mb-4">

    <!-- Bar Chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4>Transaction Volume — Last 6 Months</h4>
                <div class="d-flex gap-3" style="font-size:12px;">
                    <span><span style="display:inline-block;width:10px;height:10px;background:#28a745;border-radius:2px;margin-right:4px;"></span>Deposits</span>
                    <span><span style="display:inline-block;width:10px;height:10px;background:#007bff;border-radius:2px;margin-right:4px;"></span>Transfers</span>
                    <span><span style="display:inline-block;width:10px;height:10px;background:#dc3545;border-radius:2px;margin-right:4px;"></span>Withdrawals</span>
                </div>
            </div>
            <div class="card-body">
                <?php if(!empty($monthly_data)): ?>
                    <?php
                    $max_v = 1;
                    foreach($monthly_data as $m) {
                        $max_v = max($max_v, $m['deposits'], $m['transfers'], $m['withdrawals']);
                    }
                    ?>
                    <div style="display:flex;align-items:flex-end;gap:14px;height:160px;padding-bottom:28px;position:relative;">
                        <div style="position:absolute;bottom:28px;left:0;right:0;height:1px;background:#eee;"></div>
                        <?php foreach($monthly_data as $m):
                            $dh = max(4, round($m['deposits']    / $max_v * 120));
                            $th = max(4, round($m['transfers']   / $max_v * 120));
                            $wh = max(4, round($m['withdrawals'] / $max_v * 120));
                        ?>
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;">
                            <div style="display:flex;gap:2px;align-items:flex-end;justify-content:center;width:100%;flex:1;">
                                <div style="width:12px;height:<?php echo $dh; ?>px;background:#28a745;border-radius:3px 3px 0 0;" title="Deposits: $<?php echo number_format($m['deposits'],2); ?>"></div>
                                <div style="width:12px;height:<?php echo $th; ?>px;background:#007bff;border-radius:3px 3px 0 0;" title="Transfers: $<?php echo number_format($m['transfers'],2); ?>"></div>
                                <div style="width:12px;height:<?php echo $wh; ?>px;background:#dc3545;border-radius:3px 3px 0 0;" title="Withdrawals: $<?php echo number_format($m['withdrawals'],2); ?>"></div>
                            </div>
                            <div style="font-size:11px;color:#aaa;margin-top:6px;"><?php echo htmlspecialchars($m['mon']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">No transaction data available yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h4>Quick Actions</h4></div>
            <div class="card-body p-2">
                <?php
                $actions = [
                    ['deposit',            'fas fa-arrow-down text-success',  'Manual Deposit',        'Credit a customer account'],
                    ['withdrawal',          'fas fa-arrow-up text-danger',     'Process Withdrawal',    'Debit a customer account'],
                    ['admin-transfer',      'fas fa-exchange-alt text-primary','Admin Transfer',        'Move funds between accounts'],
                    ['transfers-pending',   'fas fa-clock text-warning',       'Approve Transfers',     $pending_transfers.' awaiting approval'],
                    ['customer-pending-kyc','fas fa-id-card text-info',        'Review KYC',            $pending_kyc_count.' pending verification'],
                    ['add-customer',        'fas fa-user-plus text-secondary', 'Add Customer',          'Manually create an account'],
                    ['cards-all',           'fas fa-credit-card text-purple',  'Manage Cards',          $pending_cards.' card request(s)'],
                ];
                foreach($actions as [$url,$icon,$title,$sub]):
                ?>
                <a href="<?php echo $url; ?>" class="d-flex align-items-center p-2 mb-1 rounded"
                   style="background:#f8f9fa;text-decoration:none;transition:background .2s;"
                   onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='#f8f9fa'">
                    <i class="<?php echo $icon; ?> fa-fw mr-3" style="width:20px;text-align:center;"></i>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;color:#212529;"><?php echo $title; ?></div>
                        <div style="font-size:11px;color:#6c757d;"><?php echo $sub; ?></div>
                    </div>
                    <i class="fas fa-chevron-right text-muted" style="font-size:10px;"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ RECENT TRANSACTIONS + USERS ============================================================ -->
<div class="row">

    <!-- Recent Transactions -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4>Recent Transactions</h4>
                <a href="transactions-all" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($recent_txns)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No transactions yet.</td></tr>
                            <?php else: foreach($recent_txns as $t):
                                $tbadge = match($t['type']??''){     'deposit'=>'success','withdrawal'=>'danger','transfer'=>'primary','payment'=>'warning',default=>'secondary'};
                                $sbadge = match($t['status']??''){   'completed'=>'success','pending'=>'warning','failed'=>'danger',default=>'secondary'};
                                $is_cr  = $t['type']==='deposit';
                            ?>
                            <tr>
                                <td>
                                    <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($t['account_number']); ?></small>
                                </td>
                                <td><span class="badge badge-<?php echo $tbadge; ?>"><?php echo ucfirst($t['type']??''); ?></span></td>
                                <td class="font-weight-bold <?php echo $is_cr?'text-success':'text-danger'; ?>">
                                    <?php echo $is_cr?'+':'-'; ?>$<?php echo number_format($t['amount']??0,2); ?>
                                </td>
                                <td><span class="badge badge-<?php echo $sbadge; ?>"><?php echo ucfirst($t['status']??''); ?></span></td>
                                <td><small class="text-muted"><?php echo date('d M, H:i',strtotime($t['created_at'])); ?></small></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Users + Top Balances -->
    <div class="col-lg-5">
        <!-- Recent Registrations -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4>New Customers</h4>
                <a href="customer-all" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php foreach($recent_users as $u):
                    $sbadge = match($u['status']??''){ 'active'=>'success','pending_kyc'=>'warning','suspended'=>'danger',default=>'secondary'};
                    $slabel = match($u['status']??''){ 'pending_kyc'=>'KYC',default=>ucfirst($u['status']??'')};
                ?>
                <div class="d-flex align-items-center px-3 py-2 border-bottom">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                         style="width:34px;height:34px;font-size:13px;font-weight:700;">
                        <?php echo strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)); ?>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?>
                        </div>
                        <small class="text-muted"><?php echo date('d M Y',strtotime($u['created_at'])); ?></small>
                    </div>
                    <div class="text-right ml-2">
                        <span class="badge badge-<?php echo $sbadge; ?>"><?php echo $slabel; ?></span>
                        <div style="font-size:11px;color:#6c757d;">$<?php echo number_format($u['balance'],2); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Top Balances -->
        <div class="card">
            <div class="card-header"><h4>Top Account Balances</h4></div>
            <div class="card-body p-0">
                <?php foreach($top_accounts as $i => $a): ?>
                <div class="d-flex align-items-center px-3 py-2 border-bottom">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                         style="width:28px;height:28px;font-size:12px;font-weight:700;background:<?php echo ['#007bff','#28a745','#ffc107','#dc3545','#6c757d'][$i]; ?>;">
                        <?php echo $i+1; ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($a['account_number']); ?></small>
                    </div>
                    <div class="font-weight-bold text-success">
                        $<?php echo number_format($a['balance'],2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</section>
</div>
<?php include 'layout/footer.php'; ?>