<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}


// ============================================================
// HANDLE STATUS ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_action'])) {
    try {
        $uid    = (int)$_POST['uid'];
        $action = $_POST['account_action'];
        $map    = ['activate'=>'active','suspend'=>'suspended','close'=>'closed'];

        if(!isset($map[$action])) throw new Exception("Invalid action.");
        if($uid <= 0)            throw new Exception("Invalid customer.");

        $stmt = $pdo->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->execute([$map[$action], $uid]);

        $_SESSION['success_message'] = "Customer account ".ucfirst($map[$action])."d successfully.";
    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: customer-all"); exit;
}

// ============================================================
// FILTERS & SEARCH
// ============================================================
$search  = trim($_GET['q']      ?? '');
$status  = trim($_GET['status'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$limit   = 20;
$offset  = ($page-1)*$limit;

$where  = ['1=1'];
$params = [];

if($status !== '') {
    $where[]  = 'u.status = ?';
    $params[] = $status;
}
if($search !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.account_number LIKE ? OR u.phone LIKE ?)';
    $sq = "%{$search}%";
    $params = array_merge($params,[$sq,$sq,$sq,$sq,$sq]);
}

$where_sql = implode(' AND ',$where);

// Counts
$counts_stmt = $pdo->query(" SELECT
        COUNT(*)                     AS total,
        SUM(status='active')         AS active,
        SUM(status='pending_kyc')    AS pending,
        SUM(status='suspended')      AS suspended,
        SUM(status='closed')         AS closed
    FROM users
");
$counts = $counts_stmt->fetch(PDO::FETCH_ASSOC);

// Total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where_sql}");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetchColumn();
$total_pages = max(1,ceil($total_rows/$limit));

// Fetch customers
$limit  = (int)$limit;
$offset = (int)$offset;

$stmt = $pdo->prepare("SELECT u.*, COALESCE(a.balance, 0) AS balance
    FROM users u
    LEFT JOIN accounts a ON u.id = a.user_id
    WHERE {$where_sql}
    ORDER BY u.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildUrl($o=[]) { return '?'.http_build_query(array_merge(array_filter($_GET,'strlen'),$o)); }
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-users mr-2"></i> All Customers</h1>
    <a href="add-customer" class="btn btn-primary">
        <i class="fas fa-user-plus mr-2"></i> Add Customer
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
        ['Total',       $counts['total'],   'bg-primary',  'fas fa-users',      ''],
        ['Active',      $counts['active'],  'bg-success',  'fas fa-user-check', 'active'],
        ['Pending KYC', $counts['pending'], 'bg-warning',  'fas fa-clock',      'pending_kyc'],
        ['Suspended',   $counts['suspended'],'bg-danger',  'fas fa-ban',        'suspended'],
    ] as [$label,$val,$bg,$icon,$fstatus]):
    ?>
    <div class="col-lg-3 col-md-6 mb-3">
        <a href="<?php echo buildUrl(['status'=>$fstatus,'page'=>1]); ?>" style="text-decoration:none;">
            <div class="card card-statistic-1 h-100">
                <div class="card-icon <?php echo $bg; ?>"><i class="<?php echo $icon; ?>"></i></div>
                <div class="card-wrap">
                    <div class="card-header"><h4><?php echo $label; ?></h4></div>
                    <div class="card-body"><?php echo number_format($val); ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <?php
    foreach([
        ['','All',         $counts['total']],
        ['active','Active',$counts['active']],
        ['pending_kyc','Pending KYC',$counts['pending']],
        ['suspended','Suspended',$counts['suspended']],
        ['closed','Closed',$counts['closed']],
    ] as [$val,$label,$cnt]):
    ?>
    <li class="nav-item">
        <a href="<?php echo buildUrl(['status'=>$val,'page'=>1]); ?>"
           class="nav-link <?php echo $status===$val?'active':''; ?>">
            <?php echo $label; ?>
            <span class="badge badge-light ml-1"><?php echo number_format($cnt); ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Search + Filter -->
<form method="GET" class="row mb-3 align-items-end">
    <?php if($status): ?>
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
    <?php endif; ?>
    <div class="col-md-6">
        <div class="input-group">
            <input type="text" name="q" class="form-control"
                   placeholder="Search by name, email, account number, phone..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <div class="input-group-append">
                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
            </div>
        </div>
    </div>
    <?php if($search): ?>
    <div class="col-md-2">
        <a href="<?php echo buildUrl(['q'=>'','page'=>1]); ?>" class="btn btn-outline-secondary btn-block">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
    <?php endif; ?>
    <div class="col-md-2 ml-auto text-right">
        <span class="text-muted" style="font-size:13px;"><?php echo number_format($total_rows); ?> record(s)</span>
    </div>
</form>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Customer</th>
                        <th>Account No.</th>
                        <th>Balance</th>
                        <th>Phone</th>
                        <th>Nationality</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($customers)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-search fa-2x mb-3 d-block opacity-50"></i>
                                No customers found.
                            </td>
                        </tr>
                    <?php else: foreach($customers as $i => $c):
                        $sbadge = match($c['status']??''){
                            'active'=>'success','pending_kyc'=>'warning','suspended'=>'danger','closed'=>'secondary',default=>'light'
                        };
                        $slabel = match($c['status']??''){
                            'pending_kyc'=>'Pending KYC',default=>ucfirst($c['status']??'')
                        };
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo $offset+$i+1; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-2 flex-shrink-0"
                                     style="width:34px;height:34px;font-size:13px;font-weight:700;">
                                    <?php echo strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1)); ?>
                                </div>
                                <div>
                                    <div style="font-size:13px;font-weight:600;">
                                        <?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?>
                                    </div>
                                    <small class="text-muted"><?php echo htmlspecialchars($c['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code><?php echo htmlspecialchars($c['account_number']); ?></code></td>
                        <td class="font-weight-bold <?php echo ($c['balance']??0)>0?'text-success':'text-muted'; ?>">
                            $<?php echo number_format($c['balance']??0,2); ?>
                        </td>
                        <td><?php echo htmlspecialchars($c['phone']??'—'); ?></td>
                        <td><?php echo htmlspecialchars($c['nationality']??'—'); ?></td>
                        <td><span class="badge badge-<?php echo $sbadge; ?>"><?php echo $slabel; ?></span></td>
                        <td><small class="text-muted"><?php echo date('d M Y',strtotime($c['created_at'])); ?></small></td>
                        <td>
                            <small class="text-muted">
                                <?php echo !empty($c['last_login']) ? date('d M Y',strtotime($c['last_login'])) : 'Never'; ?>
                            </small>
                        </td>
                        <td>
                            <a href="customer-detail-<?php echo (int)$c['id']; ?>"
                                class="btn btn-info btn-sm mb-1" title="View Details">
                                <i class="fas fa-eye"></i>
                                </a>

                            <?php if($c['status'] !== 'active'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Activate this account?')">
                                <input type="hidden" name="uid"            value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="account_action" value="activate">
                                <button class="btn btn-success btn-sm mb-1" title="Activate">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if($c['status'] === 'active'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Suspend this account?')">
                                <input type="hidden" name="uid"            value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="account_action" value="suspend">
                                <button class="btn btn-warning btn-sm mb-1" title="Suspend">
                                    <i class="fas fa-ban"></i>
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

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination justify-content-center mb-0">
            <?php if($page>1): ?>
                <li class="page-item"><a class="page-link" href="<?php echo buildUrl(['page'=>$page-1]); ?>">&laquo;</a></li>
            <?php endif; ?>
            <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link" href="<?php echo buildUrl(['page'=>$i]); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
            <?php if($page<$total_pages): ?>
                <li class="page-item"><a class="page-link" href="<?php echo buildUrl(['page'=>$page+1]); ?>">&raquo;</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

</section>
</div>
<?php include 'layout/footer.php'; ?>