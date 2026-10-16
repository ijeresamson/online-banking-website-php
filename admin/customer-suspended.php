<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php
if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}


// Handle reactivate action
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_action'])) {
    $uid    = (int)$_POST['uid'];
    $action = $_POST['account_action'];
    if($uid > 0 && in_array($action,['reactivate','close'])) {
        $new_status = $action === 'reactivate' ? 'active' : 'closed';
        $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$new_status,$uid]);
        $_SESSION['success_message'] = "Account " . ($action==='reactivate' ? 'reactivated' : 'closed') . " successfully.";
    }
    header("Location: customer-suspended"); exit;
}

$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page-1)*$limit;

$where  = ["u.status = 'suspended'"];
$params = [];

if($search !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.account_number LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params,[$sq,$sq,$sq,$sq]);
}

$where_sql = implode(' AND ',$where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where_sql}");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetchColumn();
$total_pages = max(1,ceil($total_rows/$limit));

$limit  = (int)$limit;
$offset = (int)$offset;

$stmt = $pdo->prepare(" SELECT u.*, 
           COALESCE(a.balance, 0) AS balance
    FROM users u
    LEFT JOIN accounts a ON u.id = a.user_id
    WHERE {$where_sql}
    ORDER BY u.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-user-slash mr-2 text-danger"></i> Suspended Customers</h1>
    <span class="badge badge-danger badge-pill" style="font-size:14px;padding:8px 16px;">
        <?php echo number_format($total_rows); ?> Suspended
    </span>
</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="alert alert-danger d-flex align-items-center">
    <i class="fas fa-exclamation-triangle fa-2x mr-3"></i>
    <div>
        <strong>Suspended Accounts</strong><br>
        <small>These customers cannot log in or perform any transactions.
        Review carefully before reactivating. Funds are preserved.</small>
    </div>
</div>

<!-- Search -->
<form method="GET" class="row mb-3">
    <div class="col-md-5">
        <div class="input-group">
            <input type="text" name="q" class="form-control"
                   placeholder="Search name, email, account number..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <div class="input-group-append">
                <button class="btn btn-primary"><i class="fas fa-search"></i></button>
            </div>
        </div>
    </div>
    <?php if($search): ?>
    <div class="col-md-2">
        <a href="customer-suspended" class="btn btn-outline-secondary btn-block">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
    <?php endif; ?>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Account No.</th>
                        <th>Balance</th>
                        <th>Phone</th>
                        <th>Nationality</th>
                        <th>Last Login</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($customers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                                <p class="text-muted">No suspended customers found.</p>
                            </td>
                        </tr>
                    <?php else: foreach($customers as $i => $c): ?>
                    <tr class="table-danger">
                        <td><?php echo $offset+$i+1; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-danger text-white d-flex align-items-center justify-content-center mr-2 flex-shrink-0"
                                     style="width:34px;height:34px;font-size:13px;font-weight:700;">
                                    <?php echo strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1)); ?>
                                </div>
                                <div>
                                    <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($c['email']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><code><?php echo htmlspecialchars($c['account_number']); ?></code></td>
                        <td class="font-weight-bold">$<?php echo number_format($c['balance']??0,2); ?></td>
                        <td><?php echo htmlspecialchars($c['phone']??'—'); ?></td>
                        <td><?php echo htmlspecialchars($c['nationality']??'—'); ?></td>
                        <td>
                            <small class="text-muted">
                                <?php echo !empty($c['last_login']) ? date('d M Y H:i',strtotime($c['last_login'])) : 'Never'; ?>
                            </small>
                        </td>
                        <td><small class="text-muted"><?php echo date('d M Y',strtotime($c['created_at'])); ?></small></td>
                        <td>
                            <a href="customer-detail-<?php echo $c['id']; ?>"
                               class="btn btn-info btn-sm mb-1">
                                <i class="fas fa-eye"></i>
                            </a>

                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Reactivate account for <?php echo htmlspecialchars(addslashes($c['first_name'])); ?>? They will be able to log in again.')">
                                <input type="hidden" name="uid"            value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="account_action" value="reactivate">
                                <button class="btn btn-success btn-sm mb-1">
                                    <i class="fas fa-undo"></i> Reactivate
                                </button>
                            </form>

                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('PERMANENTLY CLOSE this account? This cannot be undone.')">
                                <input type="hidden" name="uid"            value="<?php echo $c['id']; ?>">
                                <input type="hidden" name="account_action" value="close">
                                <button class="btn btn-dark btn-sm mb-1">
                                    <i class="fas fa-times-circle"></i> Close
                                </button>
                            </form>
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
            <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

</section>
</div>
<?php include 'layout/footer.php'; ?>