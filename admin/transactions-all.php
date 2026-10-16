<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

// ============================================================
// FILTERS
// ============================================================
$type_f    = $_GET['type']   ?? 'all';
$status_f  = $_GET['status'] ?? 'all';
$date_from = $_GET['from']   ?? '';
$date_to   = $_GET['to']     ?? '';
$search    = trim($_GET['q'] ?? '');
$page      = max(1,(int)($_GET['page'] ?? 1));
$limit     = 25;
$offset    = ($page-1)*$limit;

$where  = ['1=1'];
$params = [];

if($type_f   !== 'all') { $where[] = 't.type=?';               $params[] = $type_f; }
if($status_f !== 'all') { $where[] = 't.status=?';              $params[] = $status_f; }
if($date_from)          { $where[] = 'DATE(t.created_at) >= ?'; $params[] = $date_from; }
if($date_to)            { $where[] = 'DATE(t.created_at) <= ?'; $params[] = $date_to; }
if($search) {
    $where[] = "(t.reference LIKE ? OR t.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.account_number LIKE ? OR u.email LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params, [$sq,$sq,$sq,$sq,$sq,$sq]);
}

$where_sql = implode(' AND ', $where);

// ============================================================
// STATS — always unfiltered
// ============================================================
$global = $pdo->query("SELECT
        COUNT(*)                                                                              AS total,
        COALESCE(SUM(CASE WHEN type='deposit' AND status='completed' THEN amount END), 0)    AS total_credits,
        COALESCE(SUM(CASE WHEN type IN('withdrawal','transfer','payment') AND status='completed' THEN amount END), 0) AS total_debits,
        COALESCE(SUM(CASE WHEN status='pending' THEN 1 END), 0)                              AS pending_cnt
    FROM transactions
")->fetch(PDO::FETCH_ASSOC);

// ============================================================
// PAGINATED FETCH
// ============================================================
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE {$where_sql}
");
$cnt_stmt->execute($params);
$total_rows  = $cnt_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $limit));

$limit  = (int) $limit;
$offset = (int) $offset;

$stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.account_number
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE {$where_sql}
    ORDER BY t.created_at DESC
     LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute(array_merge($params));
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

function txnUrl($o=[]) { return '?'.http_build_query(array_merge(array_filter($_GET,'strlen'),$o)); }
?>

<div class="main-content">
<section class="section">

<div class="section-header">
    <h1><i class="fas fa-exchange-alt mr-2"></i> All Transactions</h1>
    <div class="section-header-button">
        <a href="deposit"    class="btn btn-success btn-sm mr-1"><i class="fas fa-arrow-down mr-1"></i> Deposit</a>
        <a href="withdrawal" class="btn btn-danger btn-sm"><i class="fas fa-arrow-up mr-1"></i> Withdraw</a>
    </div>
</div>

<!-- ============================================================ STAT CARDS ============================================================ -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-statistic-1">
            <div class="card-icon bg-primary"><i class="fas fa-list-alt"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>Total Transactions</h4></div>
                <div class="card-body"><?php echo number_format($global['total']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-statistic-1">
            <div class="card-icon bg-success"><i class="fas fa-arrow-down"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>Total Credits</h4></div>
                <div class="card-body">$<?php echo number_format($global['total_credits'],2); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-statistic-1">
            <div class="card-icon bg-danger"><i class="fas fa-arrow-up"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>Total Debits</h4></div>
                <div class="card-body">$<?php echo number_format($global['total_debits'],2); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-statistic-1">
            <div class="card-icon" style="background:#f59e0b;"><i class="fas fa-clock" style="color:#fff;"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>Pending</h4></div>
                <div class="card-body"><?php echo number_format($global['pending_cnt']); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ FILTER BAR ============================================================ -->
<form method="GET">
    <div class="row mb-3 align-items-center" style="background:#fff;padding:14px 16px;border-radius:8px;margin-left:0;margin-right:0;border:1px solid #e9ecef;">
        <div class="col-md-3 px-1 mb-2 mb-md-0">
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="Search name, account, reference..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-2 px-1 mb-2 mb-md-0">
            <select name="type" class="form-control form-control-sm">
                <option value="all"        <?php echo $type_f==='all'?'selected':''; ?>>All Types</option>
                <option value="deposit"    <?php echo $type_f==='deposit'?'selected':''; ?>>Deposit (Credit)</option>
                <option value="withdrawal" <?php echo $type_f==='withdrawal'?'selected':''; ?>>Withdrawal (Debit)</option>
                <option value="transfer"   <?php echo $type_f==='transfer'?'selected':''; ?>>Transfer (Debit)</option>
                <option value="payment"    <?php echo $type_f==='payment'?'selected':''; ?>>Payment (Debit)</option>
            </select>
        </div>
        <div class="col-md-2 px-1 mb-2 mb-md-0">
            <select name="status" class="form-control form-control-sm">
                <option value="all"       <?php echo $status_f==='all'?'selected':''; ?>>All Status</option>
                <option value="completed" <?php echo $status_f==='completed'?'selected':''; ?>>Completed</option>
                <option value="pending"   <?php echo $status_f==='pending'?'selected':''; ?>>Pending</option>
                <option value="failed"    <?php echo $status_f==='failed'?'selected':''; ?>>Failed</option>
                <option value="cancelled" <?php echo $status_f==='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-md-2 px-1 mb-2 mb-md-0">
            <input type="date" name="from" class="form-control form-control-sm"
                   value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="col-md-2 px-1 mb-2 mb-md-0">
            <input type="date" name="to" class="form-control form-control-sm"
                   value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <div class="col-md-1 px-1">
            <button type="submit" class="btn btn-primary btn-sm btn-block">
                <i class="fas fa-filter"></i>
            </button>
        </div>
    </div>
    <?php if($search || $type_f!=='all' || $status_f!=='all' || $date_from || $date_to): ?>
    <div class="mb-2">
        <a href="transactions-all" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-times mr-1"></i> Clear Filters
        </a>
        <small class="text-muted ml-2">
            Showing <?php echo number_format($total_rows); ?> result(s) · Page <?php echo $page; ?>/<?php echo $total_pages; ?>
        </small>
    </div>
    <?php endif; ?>
</form>

<!-- ============================================================ TABLE ============================================================ -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:13px;">
                <thead>
                    <tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">
                        <th style="width:40px;padding:12px 16px;color:#6c757d;font-weight:600;">#</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Customer</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Type</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Amount</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Description</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Reference</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Date & Time</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Status</th>
                        <th style="padding:12px 16px;color:#6c757d;font-weight:600;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($transactions)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;padding:48px 16px;color:#adb5bd;">
                                <i class="fas fa-receipt" style="font-size:40px;display:block;margin-bottom:12px;"></i>
                                No transactions found matching your criteria.
                            </td>
                        </tr>
                    <?php else: foreach($transactions as $i => $t):
                        $is_credit = ($t['type'] === 'deposit');

                        // Type badge
                        if($is_credit) {
                            $type_badge = '<span style="background:#dc3545;color:#fff;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:600;white-space:nowrap;"><i class="fas fa-arrow-down mr-1"></i>Credit</span>';
                        } else {
                            $type_badge = '<span style="background:#dc3545;color:#fff;padding:4px 10px;border-radius:5px;font-size:11px;font-weight:600;white-space:nowrap;"><i class="fas fa-arrow-up mr-1"></i>Debit</span>';
                        }

                        // Amount color
                        $amt_color = $is_credit ? '#28a745' : '#dc3545';
                        $amt_sign  = $is_credit ? '+' : '-';

                        // Status pill
                        $status = $t['status'] ?? 'completed';
                        $status_style = match($status) {
                            'completed' => 'background:#d1fae5;color:#059669;',
                            'pending'   => 'background:#fef3c7;color:#d97706;',
                            'failed'    => 'background:#fee2e2;color:#dc2626;',
                            'cancelled' => 'background:#f3f4f6;color:#6b7280;',
                            default     => 'background:#f3f4f6;color:#6b7280;',
                        };
                        $status_label = match($status) {
                            'pending'   => '⏳ Pending',
                            'completed' => '✓ Completed',
                            'failed'    => '✕ Failed',
                            'cancelled' => '⊘ Cancelled',
                            default     => ucfirst($status),
                        };
                    ?>
                    <tr style="border-bottom:1px solid #f1f3f5;">
                        <!-- # -->
                        <td style="padding:14px 16px;color:#adb5bd;"><?php echo $offset+$i+1; ?></td>

                        <!-- Customer -->
                        <td style="padding:14px 16px;">
                            <div style="font-weight:600;color:#212529;">
                                <?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?>
                            </div>
                            <div style="font-size:11px;color:#adb5bd;margin-top:2px;">
                                <?php echo htmlspecialchars($t['account_number']); ?>
                            </div>
                        </td>

                        <!-- Type -->
                        <td style="padding:14px 16px;"><?php echo $type_badge; ?></td>

                        <!-- Amount -->
                        <td style="padding:14px 16px;font-weight:700;font-size:14px;color:<?php echo $amt_color; ?>;">
                            <?php echo $amt_sign; ?>$<?php echo number_format($t['amount']??0,2); ?>
                        </td>

                        <!-- Description -->
                        <td style="padding:14px 16px;max-width:220px;">
                            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#495057;">
                                <?php echo htmlspecialchars($t['description']??'—'); ?>
                            </div>
                            <?php if(!empty($t['recipient_name'])): ?>
                                <div style="font-size:11px;color:#adb5bd;margin-top:2px;">
                                    → <?php echo htmlspecialchars($t['recipient_name']); ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Reference -->
                        <td style="padding:14px 16px;">
                            <span style="font-family:monospace;font-size:11px;color:#e67e22;font-weight:600;">
                                <?php echo htmlspecialchars($t['reference']??'—'); ?>
                            </span>
                        </td>

                        <!-- Date & Time -->
                        <td style="padding:14px 16px;">
                            <div style="font-weight:500;color:#212529;">
                                <?php echo date('d M Y', strtotime($t['created_at'])); ?>
                            </div>
                            <div style="font-size:11px;color:#adb5bd;margin-top:2px;">
                                <?php echo date('H:i:s', strtotime($t['created_at'])); ?>
                            </div>
                        </td>

                        <!-- Status -->
                        <td style="padding:14px 16px;">
                            <span style="<?php echo $status_style; ?>padding:5px 13px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;">
                                <?php echo $status_label; ?>
                            </span>
                        </td>

                        <!-- Action -->
                        <td style="padding:14px 16px;">
                            <a href="customer-detail-<?php echo $t['user_id']; ?>"
                               style="width:34px;height:34px;background:#007bff;color:#fff;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;"
                               title="View Customer">
                                <i class="fas fa-eye" style="font-size:13px;"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
    <div class="card-footer" style="background:#f8f9fa;padding:12px 16px;">
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <?php if($page>1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo txnUrl(['page'=>$page-1]); ?>">&laquo; Prev</a>
                    </li>
                <?php endif; ?>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                    <li class="page-item <?php echo $i===$page?'active':''; ?>">
                        <a class="page-link" href="<?php echo txnUrl(['page'=>$i]); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if($page<$total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo txnUrl(['page'=>$page+1]); ?>">Next &raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="text-center mt-2">
            <small class="text-muted">
                Showing <?php echo $offset+1; ?>–<?php echo min($offset+$limit,$total_rows); ?> of <?php echo number_format($total_rows); ?> transactions
            </small>
        </div>
    </div>
    <?php endif; ?>
</div>

</section>
</div>
<?php include 'layout/footer.php'; ?>