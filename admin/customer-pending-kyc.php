<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}


// ============================================================
// HANDLE KYC APPROVE / REJECT
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kyc_action'])) {
    try {
        $uid    = (int)$_POST['uid'];
        $action = $_POST['kyc_action'];

        if($uid <= 0) throw new Exception("Invalid customer.");

        if($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE id=? AND status='pending_kyc'");
            $stmt->execute([$uid]);
            $_SESSION['success_message'] = "KYC approved. Account is now active.";

        } elseif($action === 'reject') {
            $note = trim($_POST['reject_note'] ?? '');
            $stmt = $pdo->prepare("UPDATE users SET status='suspended' WHERE id=? AND status='pending_kyc'");
            $stmt->execute([$uid]);
            $_SESSION['success_message'] = "KYC rejected. Account suspended pending document resubmission.";

        } else {
            throw new Exception("Invalid action.");
        }

        header("Location: customer-pending-kyc");
         exit;

    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: customer-pending-kyc");
         exit;
    }
}

// ============================================================
// FETCH PENDING KYC CUSTOMERS
// ============================================================
$search = trim($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page-1)*$limit;

$where  = ["u.status = 'pending_kyc'"];
$params = [];

if($search !== '') {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.account_number LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params,[$sq,$sq,$sq,$sq]);
}

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where_sql}");
$count_stmt->execute($params);
$total_rows  = $count_stmt->fetchColumn();
$total_pages = max(1,ceil($total_rows/$limit));

$limit  = (int)$limit;
$offset = (int)$offset;

$stmt = $pdo->prepare("SELECT u.*, 
           COALESCE(a.balance, 0) AS balance,
           DATEDIFF(NOW(), u.created_at) AS days_waiting
    FROM users u
    LEFT JOIN accounts a ON u.id = a.user_id
    WHERE {$where_sql}
    ORDER BY u.created_at ASC
    LIMIT {$limit} OFFSET {$offset}
");

$stmt->execute($params);

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$id_labels = [
    'passport'        => 'International Passport',
    'national_id'     => 'National ID',
    'drivers_license' => "Driver's License",
];
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-clock mr-2 text-warning"></i> Pending KYC Verification</h1>
    <div>
        <span class="badge badge-warning badge-pill" style="font-size:15px;padding:8px 18px;">
            <?php echo number_format($total_rows); ?> Pending
        </span>
    </div>
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

<div class="alert alert-warning d-flex align-items-center">
    <i class="fas fa-info-circle fa-2x mr-3"></i>
    <div>
        <strong>KYC Review Queue</strong><br>
        <small>These customers have submitted registration forms and are awaiting document verification.
        Click <strong>View Details</strong> to review uploaded ID and proof of address documents before approving or rejecting.</small>
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
        <a href="customer-pending-kyc" class="btn btn-outline-secondary btn-block">
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
                        <th>ID Type</th>
                        <th>Employment</th>
                        <th>Nationality</th>
                        <th>DOB</th>
                        <th>Applied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($customers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3 d-block"></i>
                                <p class="text-muted">All KYC verifications are up to date! 🎉</p>
                            </td>
                        </tr>
                    <?php else: foreach($customers as $i => $c): ?>
                        <tr class="<?php echo ($c['days_waiting']??0) > 2 ? 'table-warning' : ''; ?>">
                            <td><?php echo $offset+$i+1; ?></td>
                            <td>
                                <div style="font-size:13px;font-weight:600;">
                                    <?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?>
                                </div>
                                <small class="text-muted"><?php echo htmlspecialchars($c['email']); ?></small><br>
                                <small class="text-muted"><?php echo htmlspecialchars($c['phone']??''); ?></small>
                            </td>
                            <td><code><?php echo htmlspecialchars($c['account_number']); ?></code></td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo $id_labels[$c['id_type']] ?? htmlspecialchars($c['id_type']??''); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst(str_replace('_',' ',$c['employment_status']??'')); ?></td>
                            <td><?php echo htmlspecialchars($c['nationality']??''); ?></td>
                            <td><?php echo !empty($c['date_of_birth']) ? date('d M Y',strtotime($c['date_of_birth'])) : '—'; ?></td>
                            <td>
                                <small class="text-muted"><?php echo date('d M Y',strtotime($c['created_at'])); ?></small><br>
                                <?php if(($c['days_waiting']??0) > 2): ?>
                                    <span class="badge badge-danger">Overdue (<?php echo $c['days_waiting']; ?>d)</span>
                                <?php else: ?>
                                    <small class="text-muted"><?php echo $c['days_waiting']; ?>d ago</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="customer-detail-<?php echo $c['id']; ?>"
                                   class="btn btn-info btn-sm mb-1">
                                    <i class="fas fa-eye"></i> Review
                                </a>

                                <!-- Quick Approve -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Approve KYC for <?php echo htmlspecialchars(addslashes($c['first_name'])); ?>? This will activate their account.')">
                                    <input type="hidden" name="uid"        value="<?php echo $c['id']; ?>">
                                    <input type="hidden" name="kyc_action" value="approve">
                                    <button class="btn btn-success btn-sm mb-1">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>

                                <!-- Quick Reject -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Reject KYC for <?php echo htmlspecialchars(addslashes($c['first_name'])); ?>? This will suspend their account.')">
                                    <input type="hidden" name="uid"        value="<?php echo $c['id']; ?>">
                                    <input type="hidden" name="kyc_action" value="reject">
                                    <button class="btn btn-danger btn-sm mb-1">
                                        <i class="fas fa-times"></i> Reject
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