<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}


// ============================================================
// HANDLE ACCOUNT ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_action'])) {
    try {
        $uid    = (int)$_POST['uid'];
        $action = $_POST['account_action'];

        if($uid <= 0) throw new Exception("Invalid customer.");

        $map = [
            'freeze'   => ['suspended', 'Account frozen successfully.'],
            'unfreeze' => ['active',    'Account activated successfully.'],
            'close'    => ['closed',    'Account closed.'],
        ];

        if(!isset($map[$action])) throw new Exception("Invalid action.");
        [$new_status, $msg] = $map[$action];

        $stmt = $pdo->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->execute([$new_status, $uid]);
        $_SESSION['success_message'] = $msg;

    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: accounts-all");
     exit;
}

// ============================================================
// FILTERS
// ============================================================
$status_f = $_GET['status'] ?? 'all';
$search   = trim($_GET['q'] ?? '');
$page     = max(1,(int)($_GET['page'] ?? 1));
$limit    = 20;
$offset   = ($page-1)*$limit;

$where  = ['1=1'];
$params = [];

if($status_f !== 'all') {
    // Map filter value to user status
    $status_map_filter = [
        'active'      => 'active',
        'frozen'      => 'suspended',
        'closed'      => 'closed',
        'pending_kyc' => 'pending_kyc',
    ];
    if(isset($status_map_filter[$status_f])) {
        $where[]  = 'u.status = ?';
        $params[] = $status_map_filter[$status_f];
    }
}

if($search !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.account_number LIKE ? OR a.routing_number LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params,[$sq,$sq,$sq,$sq,$sq]);
}

$where_sql = implode(' AND ',$where);

// Stats
$stats = $pdo->query(" SELECT
        COUNT(u.id)                            AS total,
        SUM(u.status='active')                 AS active,
        SUM(u.status='suspended')              AS frozen,
        SUM(u.status='closed')                 AS closed,
        SUM(u.status='pending_kyc')            AS pending,
        COALESCE(SUM(a.balance),0)             AS total_balance,
        COALESCE(AVG(a.balance),0)             AS avg_balance,
        COALESCE(MAX(a.balance),0)             AS max_balance
    FROM users u
    LEFT JOIN accounts a ON u.id = a.user_id
")->fetch(PDO::FETCH_ASSOC);

// Count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN accounts a ON u.id = a.user_id
    WHERE {$where_sql} ");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetchColumn();
$total_pages = max(1,ceil($total_rows/$limit));

// Fetch accounts
$limit  = (int)$limit;
$offset = (int)$offset;

$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.email, u.account_number,
           u.status AS user_status, u.last_login, u.created_at,
           a.balance, a.account_type, a.currency,
           a.routing_number, a.wire_routing, a.swift_code,
           a.bank_name
    FROM users u
    LEFT JOIN accounts a ON u.id = a.user_id
    WHERE {$where_sql}
    ORDER BY a.balance DESC, u.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildAccUrl($o=[]) { return '?'.http_build_query(array_merge(array_filter($_GET,'strlen'),$o)); }
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-university mr-2"></i> All Bank Accounts</h1>
    <a href="add-customer" class="btn btn-primary">
        <i class="fas fa-plus mr-2"></i> New Account
    </a>
</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>
<?php if(!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="row mb-4">
    <?php
    foreach([
        ['Total Accounts',    number_format($stats['total']),                        'bg-primary',   'fas fa-university'],
        ['Total Balance',     '$'.number_format($stats['total_balance'],2),          'bg-success',   'fas fa-dollar-sign'],
        ['Avg Balance',       '$'.number_format($stats['avg_balance'],2),            'bg-info',      'fas fa-chart-bar'],
        ['Highest Balance',   '$'.number_format($stats['max_balance'],2),            'bg-warning',   'fas fa-trophy'],
    ] as [$l,$v,$bg,$ic]):
    ?>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-statistic-1 h-100">
            <div class="card-icon <?php echo $bg; ?>"><i class="<?php echo $ic; ?>"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4><?php echo $l; ?></h4></div>
                <div class="card-body"><?php echo $v; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <?php
    foreach([
        ['all','All Accounts',  null],
        ['active','Active',     $stats['active']],
        ['frozen','Frozen',     $stats['frozen']],
        ['pending_kyc','Pending KYC',$stats['pending']],
        ['closed','Closed',     $stats['closed']],
    ] as [$val,$label,$cnt]):
    ?>
    <li class="nav-item">
        <a href="<?php echo buildAccUrl(['status'=>$val,'page'=>1]); ?>"
           class="nav-link <?php echo $status_f===$val?'active':''; ?>">
            <?php echo $label; ?>
            <?php if($cnt !== null): ?>
                <span class="badge badge-light ml-1"><?php echo number_format($cnt); ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Search -->
<form method="GET" class="row mb-3 align-items-end">
    <?php if($status_f !== 'all'): ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_f); ?>">
    <?php endif; ?>
    <div class="col-md-5">
        <div class="input-group">
            <input type="text" name="q" class="form-control"
                   placeholder="Search name, email, account number, routing..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <div class="input-group-append">
                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
            </div>
        </div>
    </div>
    <?php if($search): ?>
    <div class="col-md-2">
        <a href="<?php echo buildAccUrl(['q'=>'','page'=>1]); ?>" class="btn btn-outline-secondary btn-block">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
    <?php endif; ?>
    <div class="col-md-2 ml-auto text-right">
        <small class="text-muted"><?php echo number_format($total_rows); ?> accounts</small>
    </div>
</form>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Account Holder</th>
                        <th>Account Number</th>
                        <th>Balance</th>
                        <th>Type</th>
                        <th>Routing No.</th>
                        <th>Wire Routing</th>
                        <th>SWIFT</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($accounts)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">No accounts found.</td></tr>
                    <?php else: foreach($accounts as $i => $a):
                        $sbadge = match($a['user_status']??''){
                            'active'=>'success','suspended'=>'info','closed'=>'secondary','pending_kyc'=>'warning',default=>'light'
                        };
                        $slabel = match($a['user_status']??''){
                            'suspended'=>'Frozen','pending_kyc'=>'Pending KYC',default=>ucfirst($a['user_status']??'')
                        };
                    ?>
                    <tr>
                        <td><?php echo $offset+$i+1; ?></td>
                        <td>
                            <div style="font-size:13px;font-weight:600;">
                                <?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?>
                            </div>
                            <small class="text-muted"><?php echo htmlspecialchars($a['email']); ?></small>
                        </td>
                        <td><code><?php echo htmlspecialchars($a['account_number']); ?></code></td>
                        <td class="font-weight-bold <?php echo ($a['balance']??0)>0?'text-success':'text-muted'; ?>">
                            $<?php echo number_format($a['balance']??0,2); ?>
                        </td>
                        <td>
                            <span class="badge badge-primary">
                                <?php echo htmlspecialchars($a['account_type']??'Checking'); ?>
                            </span>
                        </td>
                        <td>
                            <code style="font-size:11px;"><?php echo htmlspecialchars($a['routing_number']??'—'); ?></code>
                        </td>
                        <td>
                            <code style="font-size:11px;"><?php echo htmlspecialchars($a['wire_routing']??'—'); ?></code>
                        </td>
                        <td>
                            <code style="font-size:11px;"><?php echo htmlspecialchars($a['swift_code']??BANK_SWIFT); ?></code>
                        </td>
                        <td><span class="badge badge-<?php echo $sbadge; ?>"><?php echo $slabel; ?></span></td>
                        <td>
                            <a href="customer-detail-<?php echo $a['id']; ?>"
                               class="btn btn-info btn-sm mb-1" title="View">
                                <i class="fas fa-eye"></i>
                            </a>

                            <?php if($a['user_status'] === 'active'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Freeze this account?')">
                                <input type="hidden" name="uid"            value="<?php echo $a['id']; ?>">
                                <input type="hidden" name="account_action" value="freeze">
                                <button class="btn btn-warning btn-sm mb-1" title="Freeze">
                                    <i class="fas fa-pause"></i>
                                </button>
                            </form>
                            <?php elseif($a['user_status'] === 'suspended'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Unfreeze this account?')">
                                <input type="hidden" name="uid"            value="<?php echo $a['id']; ?>">
                                <input type="hidden" name="account_action" value="unfreeze">
                                <button class="btn btn-success btn-sm mb-1" title="Unfreeze">
                                    <i class="fas fa-play"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if($a['user_status'] !== 'closed'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Permanently close this account?')">
                                <input type="hidden" name="uid"            value="<?php echo $a['id']; ?>">
                                <input type="hidden" name="account_action" value="close">
                                <button class="btn btn-danger btn-sm mb-1" title="Close Account">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if($total_pages > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination justify-content-center mb-0">
            <?php if($page>1): ?>
                <li class="page-item"><a class="page-link" href="<?php echo buildAccUrl(['page'=>$page-1]); ?>">&laquo;</a></li>
            <?php endif; ?>
            <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link" href="<?php echo buildAccUrl(['page'=>$i]); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <?php if($page<$total_pages): ?>
                <li class="page-item"><a class="page-link" href="<?php echo buildAccUrl(['page'=>$page+1]); ?>">&raquo;</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

</section>
</div>
<?php include 'layout/footer.php'; ?>