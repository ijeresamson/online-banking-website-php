<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

// ============================================================
// HANDLE REVERSE
// ============================================================
if(isset($_POST['reverse_transfer'])) {
    try {
        $transfer_id = (int)$_POST['transfer_id'];
        $s = $pdo->prepare("SELECT * FROM transfers
            WHERE id=? AND status='completed' AND transfer_type='internal' AND recipient_id > 0
            LIMIT 1
        ");
        $s->execute([$transfer_id]);
        $tf = $s->fetch(PDO::FETCH_ASSOC);
        if(!$tf) throw new Exception("Transfer not found or cannot be auto-reversed (may be an external wire transfer).");

        $pdo->beginTransaction();
        $amount    = (float)$tf['amount'];
        $recip_bal = getBalance($pdo, $tf['recipient_id']);
        $sender_bal= getBalance($pdo, $tf['sender_id']);

        if($recip_bal < $amount) {
            throw new Exception("Cannot reverse: recipient's balance (".formatMoney($recip_bal).") is less than ".formatMoney($amount).".");
        }

        updateBalance($pdo, $tf['recipient_id'], $recip_bal - $amount);
        updateBalance($pdo, $tf['sender_id'],    $sender_bal + $amount);
        $pdo->prepare("UPDATE transfers SET status='reversed' WHERE id=?")->execute([$transfer_id]);

        $rev_ref = 'REV'.strtoupper(bin2hex(random_bytes(5)));
        recordTransaction($pdo, $tf['sender_id'],    'deposit',    $amount, $sender_bal, $sender_bal+$amount, 'Transfer Reversal — Funds Returned', $rev_ref.'_S', 'completed');
        recordTransaction($pdo, $tf['recipient_id'], 'withdrawal', $amount, $recip_bal,  $recip_bal-$amount,  'Transfer Reversal — Funds Recovered', $rev_ref.'_R', 'completed');

        $pdo->commit();
        $_SESSION['success_message'] = "Transfer reversed. ".formatMoney($amount)." returned to sender. Ref: <code>{$rev_ref}</code>";

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: transfers-completed"); exit;
}

// ============================================================
// FILTERS
// ============================================================
$type_filter = $_GET['type']   ?? 'all';
$search      = trim($_GET['search'] ?? '');
$date_from   = $_GET['from']   ?? date('Y-m-01');
$date_to     = $_GET['to']     ?? date('Y-m-d');
$page        = max(1,(int)($_GET['page'] ?? 1));
$limit       = 20;
$offset      = ($page-1)*$limit;

$where  = ["tf.status = 'completed'"];
$params = [];

if($type_filter !== 'all') { $where[] = 'tf.transfer_type=?'; $params[] = $type_filter; }
if($search) {
    $where[] = "(tf.reference LIKE ? OR su.first_name LIKE ? OR su.last_name LIKE ? OR su.account_number LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params, [$sq,$sq,$sq,$sq]);
}
if($date_from) { $where[] = 'DATE(tf.created_at) >= ?'; $params[] = $date_from; }
if($date_to)   { $where[] = 'DATE(tf.created_at) <= ?'; $params[] = $date_to; }

$where_sql = implode(' AND ', $where);

// Stats for period
$s_stmt = $pdo->prepare("SELECT
        COUNT(*) AS total,
        COALESCE(SUM(tf.amount),0)                                                         AS total_vol,
        COALESCE(SUM(CASE WHEN tf.transfer_type='internal' THEN tf.amount END),0)          AS internal_vol,
        COALESCE(SUM(CASE WHEN tf.transfer_type='external' THEN tf.amount END),0)          AS external_vol,
        SUM(tf.transfer_type='internal')                                                   AS internal_cnt,
        SUM(tf.transfer_type='external')                                                   AS external_cnt
    FROM transfers tf
    JOIN users su ON tf.sender_id = su.id
    WHERE {$where_sql}
");
$s_stmt->execute($params);
$stats = $s_stmt->fetch(PDO::FETCH_ASSOC);

// Count
$cnt = $pdo->prepare("SELECT COUNT(*) FROM transfers tf
    JOIN users su ON tf.sender_id = su.id
    LEFT JOIN users ru ON tf.recipient_id = ru.id AND tf.recipient_id > 0
    WHERE {$where_sql}
");
$cnt->execute($params);
$total_rows  = $cnt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $limit));

// Fetch
$stmt = $pdo->prepare("SELECT tf.*,
           su.first_name AS sfn, su.last_name AS sln, su.account_number AS s_accno,
           ru.first_name AS rfn, ru.last_name AS rln, ru.account_number AS r_accno
    FROM transfers tf
    JOIN users su ON tf.sender_id = su.id
    LEFT JOIN users ru ON tf.recipient_id = ru.id AND tf.recipient_id > 0
    WHERE {$where_sql}
    ORDER BY tf.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function compUrl($o=[]) {
    return '?'.http_build_query(array_merge(array_filter($_GET,'strlen'),$o));
}
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-check-double mr-2 text-success"></i> Completed Transfers</h1>
    <div>
        <a href="transfers-pending" class="btn btn-warning btn-sm mr-1">
            <i class="fas fa-clock mr-1"></i> Pending
        </a>
        <a href="transfers-all" class="btn btn-secondary btn-sm">
            <i class="fas fa-list mr-1"></i> All Transfers
        </a>
    </div>
</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle mr-2"></i>
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>
<?php if(!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="row mb-4">
    <?php
    foreach([
        ['Completed',         number_format($stats['total']),                 'bg-success',   'fas fa-check-circle'],
        ['Total Volume',      '$'.number_format($stats['total_vol'],2),       'bg-primary',   'fas fa-dollar-sign'],
        ['Internal Volume',   '$'.number_format($stats['internal_vol'],2),    'bg-info',      'fas fa-university'],
        ['Wire/External Vol.','$'.number_format($stats['external_vol'],2),    'bg-secondary', 'fas fa-globe'],
    ] as [$lbl,$val,$bg,$ic]):
    ?>
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card card-statistic-1">
            <div class="card-icon <?php echo $bg; ?>"><i class="<?php echo $ic; ?>"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4><?php echo $lbl; ?></h4></div>
                <div class="card-body"><?php echo $val; ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- FILTERS -->
<form method="GET" class="row mb-3 align-items-end">
    <div class="col-md-3">
        <label class="small font-weight-bold">Search</label>
        <input type="text" name="search" class="form-control"
               placeholder="Reference, sender name, account..."
               value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2">
        <label class="small font-weight-bold">Type</label>
        <select name="type" class="form-control">
            <option value="all"      <?php echo $type_filter==='all'?'selected':''; ?>>All Types</option>
            <option value="internal" <?php echo $type_filter==='internal'?'selected':''; ?>>Internal</option>
            <option value="external" <?php echo $type_filter==='external'?'selected':''; ?>>Wire/External</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="small font-weight-bold">From</label>
        <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
    </div>
    <div class="col-md-2">
        <label class="small font-weight-bold">To</label>
        <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
    </div>
    <?php if($search||$type_filter!=='all'): ?>
    <div class="col-md-1">
        <a href="<?php echo compUrl(['search'=>'','type'=>'all']); ?>"
           class="btn btn-outline-secondary btn-block">
            <i class="fas fa-times"></i>
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- TABLE -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">
            <?php echo number_format($total_rows); ?> completed transfer(s)
        </h4>
        <small class="text-muted">
            Period: <?php echo date('d M Y',strtotime($date_from)); ?> —
                    <?php echo date('d M Y',strtotime($date_to)); ?>
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Sender</th>
                        <th>Recipient</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($transfers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x mb-3 d-block" style="opacity:.2;"></i>
                                No completed transfers found for this period.
                            </td>
                        </tr>
                    <?php else: foreach($transfers as $i => $tf):
                        $is_ext = ($tf['transfer_type'] === 'external');

                        if($is_ext) {
                            $recip_name = htmlspecialchars($tf['recipient_name_ext'] ?? 'External');
                            $recip_acct = htmlspecialchars($tf['recipient_account']  ?? '—');
                        } else {
                            $recip_name = htmlspecialchars(($tf['rfn']??'').' '.($tf['rln']??''));
                            $recip_acct = htmlspecialchars($tf['r_accno'] ?? $tf['recipient_account'] ?? '—');
                        }
                    ?>
                    <tr>
                        <td><?php echo $offset+$i+1; ?></td>

                        <!-- Sender -->
                        <td>
                            <div class="font-weight-bold">
                                <?php echo htmlspecialchars($tf['sfn'].' '.$tf['sln']); ?>
                            </div>
                            <small class="text-muted"><?php echo htmlspecialchars($tf['s_accno']); ?></small>
                        </td>

                        <!-- Recipient -->
                        <td>
                            <div class="font-weight-bold"><?php echo $recip_name; ?></div>
                            <small class="text-muted"><?php echo $recip_acct; ?></small>
                            <?php if($is_ext && !empty($tf['recipient_bank_name'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($tf['recipient_bank_name']); ?></small>
                            <?php endif; ?>
                        </td>

                        <!-- Amount -->
                        <td class="font-weight-bold text-success">
                            $<?php echo number_format($tf['amount']??0,2); ?>
                        </td>

                        <!-- Type -->
                        <td>
                            <span class="badge badge-<?php echo $is_ext?'info':'primary'; ?>">
                                <?php echo $is_ext ? '🌐 Wire' : '🏦 Internal'; ?>
                            </span>
                        </td>

                        <!-- Reference -->
                        <td>
                            <code style="font-size:10px;color:#e67e22;">
                                <?php echo htmlspecialchars($tf['reference']??'—'); ?>
                            </code>
                        </td>

                        <!-- Description -->
                        <td>
                            <small class="text-muted">
                                <?php echo htmlspecialchars(substr($tf['description']??'—',0,30)); ?>
                            </small>
                        </td>

                        <!-- Date -->
                        <td>
                            <small><?php echo date('d M Y',strtotime($tf['created_at'])); ?></small><br>
                            <small class="text-muted"><?php echo date('H:i',strtotime($tf['created_at'])); ?></small>
                        </td>

                        <!-- Actions -->
                        <td>
                            <?php if(!$is_ext && $tf['recipient_id'] > 0): ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Reverse this transfer?\n\nFunds will be returned to sender.\nRecipient must have sufficient balance.\nThis will create a reversal transaction.')">
                                <input type="hidden" name="transfer_id"      value="<?php echo $tf['id']; ?>">
                                <button name="reverse_transfer" class="btn btn-warning btn-sm mr-1" title="Reverse Transfer">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="btn btn-secondary btn-sm disabled mr-1" title="External transfers cannot be auto-reversed" style="opacity:.5;">
                                    <i class="fas fa-undo"></i>
                                </span>
                            <?php endif; ?>

                            <a href="customer-detail-<?php echo $tf['sender_id']; ?>"
                               class="btn btn-info btn-sm" title="View Sender">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($total_pages > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <?php if($page>1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo compUrl(['page'=>$page-1]); ?>">&laquo;</a>
                    </li>
                <?php endif; ?>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                    <li class="page-item <?php echo $i===$page?'active':''; ?>">
                        <a class="page-link" href="<?php echo compUrl(['page'=>$i]); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if($page<$total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo compUrl(['page'=>$page+1]); ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

</section>
</div>
<?php include 'layout/footer.php'; ?>