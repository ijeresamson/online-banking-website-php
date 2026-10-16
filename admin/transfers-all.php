<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

// ============================================================
// HANDLE REVERSE TRANSFER
// ============================================================
if(isset($_POST['reverse_transfer'])) {
    try {
        $transfer_id = (int)$_POST['transfer_id'];

        $stmt = $pdo->prepare("SELECT * FROM transfers
            WHERE id=? AND status='completed' AND transfer_type='internal' AND recipient_id > 0
            LIMIT 1
        ");
        $stmt->execute([$transfer_id]);
        $tf = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$tf) throw new Exception("Transfer not found, already reversed, or is an external transfer (cannot auto-reverse).");

        $pdo->beginTransaction();

        $sender_bal = getBalance($pdo, $tf['sender_id']);
        $recip_bal  = getBalance($pdo, $tf['recipient_id']);
        $amount     = (float)$tf['amount'];

        // Check recipient has enough to reverse
        if($recip_bal < $amount) {
            throw new Exception("Cannot reverse: recipient's current balance (".formatMoney($recip_bal).") is less than the transfer amount (".formatMoney($amount).").");
        }

        // Deduct from recipient
        updateBalance($pdo, $tf['recipient_id'], $recip_bal - $amount);

        // Credit back to sender
        updateBalance($pdo, $tf['sender_id'], $sender_bal + $amount);

        // Mark transfer as reversed
        $pdo->prepare("UPDATE transfers SET status='reversed' WHERE id=?")->execute([$transfer_id]);

        // Record reversal transactions
        $rev_ref = 'REV'.strtoupper(bin2hex(random_bytes(5)));

        recordTransaction(
            $pdo, $tf['sender_id'], 'deposit', $amount,
            $sender_bal, $sender_bal + $amount,
            'Transfer Reversal — Funds Returned',
            $rev_ref.'_S', 'completed'
        );
        recordTransaction(
            $pdo, $tf['recipient_id'], 'withdrawal', $amount,
            $recip_bal, $recip_bal - $amount,
            'Transfer Reversal — Funds Recovered',
            $rev_ref.'_R', 'completed'
        );

        $pdo->commit();
        $_SESSION['success_message'] = "Transfer reversed successfully. ".formatMoney($amount)." returned to sender. Reference: <code>{$rev_ref}</code>";

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: transfers-all"); exit;
}

// ============================================================
// FILTERS
// ============================================================
$filter_status = $_GET['status'] ?? 'all';
$type_filter   = $_GET['type']   ?? 'all';
$search        = trim($_GET['search'] ?? '');
$date_from     = $_GET['from']   ?? '';
$date_to       = $_GET['to']     ?? '';
$page          = max(1,(int)($_GET['page'] ?? 1));
$limit         = 20;
$offset        = ($page-1)*$limit;

$where  = ['1=1'];
$params = [];

if($filter_status !== 'all') { $where[] = 'tf.status=?';        $params[] = $filter_status; }
if($type_filter   !== 'all') { $where[] = 'tf.transfer_type=?'; $params[] = $type_filter; }
if($search) {
    $where[] = "(tf.reference LIKE ? OR tf.description LIKE ? OR su.first_name LIKE ? OR su.last_name LIKE ? OR su.account_number LIKE ? OR tf.recipient_name_ext LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params, [$sq,$sq,$sq,$sq,$sq,$sq]);
}
if($date_from) { $where[] = 'DATE(tf.created_at) >= ?'; $params[] = $date_from; }
if($date_to)   { $where[] = 'DATE(tf.created_at) <= ?'; $params[] = $date_to; }

$where_sql = implode(' AND ', $where);

// Global stats
$stats = $pdo->query("SELECT
        COUNT(*)                                                                        AS total,
        COALESCE(SUM(CASE WHEN status='completed' THEN amount END),0)                  AS vol_completed,
        SUM(status='pending')                                                          AS cnt_pending,
        SUM(status='processing')                                                       AS cnt_processing,
        SUM(status='completed')                                                        AS cnt_completed,
        SUM(status='failed')                                                           AS cnt_failed,
        SUM(status='reversed')                                                         AS cnt_reversed
    FROM transfers
")->fetch(PDO::FETCH_ASSOC);

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

function trAllUrl($o=[]) {
    return '?'.http_build_query(array_merge(array_filter($_GET,'strlen'),$o));
}
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-exchange-alt mr-2"></i> All Transfers</h1>
    <div>
        <a href="transfers-pending" class="btn btn-warning btn-sm mr-1">
            <i class="fas fa-clock mr-1"></i>
            Pending (<?php echo ($stats['cnt_pending']+$stats['cnt_processing']); ?>)
        </a>
        <a href="transfers-completed" class="btn btn-success btn-sm">
            <i class="fas fa-check-circle mr-1"></i>
            Completed
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
        ['Total Transfers',    number_format($stats['total']),                          'bg-primary',   'fas fa-exchange-alt'],
        ['Total Volume',       '$'.number_format($stats['vol_completed'],2),            'bg-success',   'fas fa-dollar-sign'],
        ['Pending/Processing', ($stats['cnt_pending']+$stats['cnt_processing']),        'bg-warning',   'fas fa-clock'],
        ['Failed/Reversed',    ($stats['cnt_failed']+$stats['cnt_reversed']),           'bg-danger',    'fas fa-times-circle'],
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

<!-- STATUS TABS -->
<ul class="nav nav-tabs mb-3">
    <?php
    $tabs = [
        ['all',        'All',        null,                    ''],
        ['pending',    'Pending',    $stats['cnt_pending'],   'badge-warning'],
        ['processing', 'Processing', $stats['cnt_processing'],'badge-primary'],
        ['completed',  'Completed',  $stats['cnt_completed'], 'badge-success'],
        ['failed',     'Failed',     $stats['cnt_failed'],    'badge-danger'],
        ['reversed',   'Reversed',   $stats['cnt_reversed'],  'badge-secondary'],
    ];
    foreach($tabs as [$val,$label,$cnt,$badge]):
    ?>
    <li class="nav-item">
        <a href="<?php echo trAllUrl(['status'=>$val,'page'=>1]); ?>"
           class="nav-link <?php echo $filter_status===$val?'active':''; ?>">
            <?php echo $label; ?>
            <?php if($badge && $cnt > 0): ?>
                <span class="badge <?php echo $badge; ?> ml-1"><?php echo $cnt; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- FILTERS -->
<form method="GET" class="row mb-3 align-items-end">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control"
               placeholder="Search reference, name, account..."
               value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2">
        <select name="type" class="form-control">
            <option value="all"      <?php echo $type_filter==='all'?'selected':''; ?>>All Types</option>
            <option value="internal" <?php echo $type_filter==='internal'?'selected':''; ?>>Internal</option>
            <option value="external" <?php echo $type_filter==='external'?'selected':''; ?>>External/Wire</option>
        </select>
    </div>
    <div class="col-md-2">
        <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
    </div>
    <div class="col-md-2">
        <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
    </div>
    <div class="col-md-1">
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-filter"></i>
        </button>
    </div>
    <?php if($search||$date_from||$date_to||$type_filter!=='all'): ?>
    <div class="col-md-2">
        <a href="<?php echo trAllUrl(['search'=>'','from'=>'','to'=>'','type'=>'all','page'=>1]); ?>"
           class="btn btn-outline-secondary btn-block">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- TABLE -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">
            <?php echo number_format($total_rows); ?> transfer(s) found
        </h4>
        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></small>
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
                        <th>Status</th>
                        <th style="width:100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($transfers)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-exchange-alt fa-3x mb-3 d-block" style="opacity:.2;"></i>
                                No transfers found matching your criteria.
                            </td>
                        </tr>
                    <?php else: foreach($transfers as $i => $tf):
                        $is_ext = ($tf['transfer_type'] === 'external');

                        // Recipient display
                        if($is_ext) {
                            $recip_name = htmlspecialchars($tf['recipient_name_ext'] ?? 'External');
                            $recip_acct = htmlspecialchars($tf['recipient_account']  ?? '—');
                        } else {
                            $recip_name = htmlspecialchars(($tf['rfn']??'').' '.($tf['rln']??''));
                            $recip_acct = htmlspecialchars($tf['r_accno'] ?? $tf['recipient_account'] ?? '—');
                        }

                        // Status badge
                        $sbadge = match($tf['status']??'') {
                            'completed'  => 'success',
                            'pending'    => 'warning',
                            'processing' => 'primary',
                            'failed'     => 'danger',
                            'reversed'   => 'secondary',
                            default      => 'light',
                        };
                        $slabel = match($tf['status']??'') {
                            'processing' => '🔒 Reserved',
                            'pending'    => '⏳ Pending',
                            'completed'  => '✓ Completed',
                            'failed'     => '✕ Failed',
                            'reversed'   => '↩ Reversed',
                            default      => ucfirst($tf['status']??''),
                        };

                        $row_bg = match($tf['status']??'') {
                            'pending'    => '',
                            'processing' => 'table-info',
                            'failed'     => 'table-danger',
                            'reversed'   => 'table-secondary',
                            default      => '',
                        };
                    ?>
                    <tr class="<?php echo $row_bg; ?>">
                        <td><?php echo $offset+$i+1; ?></td>
                        <td>
                            <div class="font-weight-bold"><?php echo htmlspecialchars($tf['sfn'].' '.$tf['sln']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($tf['s_accno']); ?></small>
                        </td>
                        <td>
                            <div class="font-weight-bold"><?php echo $recip_name; ?></div>
                            <small class="text-muted"><?php echo $recip_acct; ?></small>
                            <?php if($is_ext && !empty($tf['recipient_bank_name'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($tf['recipient_bank_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="font-weight-bold text-success">
                            $<?php echo number_format($tf['amount']??0,2); ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $is_ext?'info':'primary'; ?>">
                                <?php echo $is_ext ? '🌐 Wire' : '🏦 Internal'; ?>
                            </span>
                        </td>
                        <td>
                            <code style="font-size:11px;color:#e67e22;">
                                <?php echo htmlspecialchars(substr($tf['reference']??'—',0,16)); ?>
                            </code>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php echo htmlspecialchars(substr($tf['description']??'—',0,30)); ?>
                            </small>
                        </td>
                        <td>
                            <small><?php echo date('d M Y',strtotime($tf['created_at'])); ?></small><br>
                            <small class="text-muted"><?php echo date('H:i',strtotime($tf['created_at'])); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $sbadge; ?>"><?php echo $slabel; ?></span>
                        </td>
                        <td>
                            <!-- Reverse (only completed internal) -->
                            <?php if($tf['status']==='completed' && !$is_ext && $tf['recipient_id']>0): ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Reverse this transfer?\n\nFunds will be returned to sender.\nRecipient must have sufficient balance.')">
                                <input type="hidden" name="transfer_id"      value="<?php echo $tf['id']; ?>">
                                <button name="reverse_transfer" class="btn btn-warning btn-sm mb-1" title="Reverse">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if($tf['status']==='pending'||$tf['status']==='processing'): ?>
                            <a href="transfers-pending" class="btn btn-warning btn-sm mb-1" title="Review">
                                <i class="fas fa-clock"></i>
                            </a>
                            <?php endif; ?>

                            <a href="customer-detail-<?php echo $tf['sender_id']; ?>"
                               class="btn btn-info btn-sm mb-1" title="View Sender">
                                <i class="fas fa-eye"></i>
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
    <div class="card-footer">
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <?php if($page>1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo trAllUrl(['page'=>$page-1]); ?>">&laquo;</a>
                    </li>
                <?php endif; ?>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                    <li class="page-item <?php echo $i===$page?'active':''; ?>">
                        <a class="page-link" href="<?php echo trAllUrl(['page'=>$i]); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if($page<$total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo trAllUrl(['page'=>$page+1]); ?>">&raquo;</a>
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