<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

// ============================================================
// HANDLE TRANSFER ACTIONS
// ============================================================
if(isset($_POST['transfer_action'])) {
    $transfer_id = (int)$_POST['transfer_id'];
    $action      = $_POST['transfer_action'];

    try {
        if($transfer_id <= 0) throw new Exception("Invalid transfer ID.");

        $s = $pdo->prepare("SELECT * FROM transfers WHERE id=? AND status IN('pending','processing') LIMIT 1");
        $s->execute([$transfer_id]);
        $tf = $s->fetch(PDO::FETCH_ASSOC);
        if(!$tf) throw new Exception("Transfer not found or already processed.");

        $amount    = (float)$tf['amount'];
        $sender_id = (int)$tf['sender_id'];

        $pdo->beginTransaction();

        switch($action) {

            // ======================================================
            // RESERVE FUNDS
            // Deducts sender balance — funds held for compliance review
            // Recipient is NOT credited yet
            // ======================================================
            case 'reserve':
                if($tf['status'] !== 'pending') throw new Exception("Only pending transfers can be reserved.");
                $cur_bal = getBalance($pdo, $sender_id);
                if($cur_bal < $amount) {
                    throw new Exception("Insufficient funds: sender's balance is ".formatMoney($cur_bal)." but ".formatMoney($amount)." is required.");
                }
                updateBalance($pdo, $sender_id, $cur_bal - $amount);

                // Update the pending transaction
                $pdo->prepare("UPDATE transactions SET status='processing', balance_before=?,
                        balance_after=?, description = CONCAT(description, ' [Funds Reserved — Pending Release]')
                    WHERE user_id=? AND reference=? AND status='pending'
                ")->execute([$cur_bal, $cur_bal-$amount, $sender_id, $tf['reference']]);

                $pdo->prepare("UPDATE transfers SET status='processing' WHERE id=?")->execute([$transfer_id]);

                $_SESSION['success_message'] = "✅ Funds Reserved! ".formatMoney($amount)." 
                deducted from sender's account and held pending your review. Reference: <code>{$tf['reference']}</code>";
                break;

            // ======================================================
            // RELEASE FUNDS
            // Credits recipient — completes the transfer
            // Only works on 'processing' (reserved) transfers
            // ======================================================
            case 'release':
                if($tf['status'] !== 'processing') throw new Exception("Only reserved (processing) transfers can be released.");

                if($tf['transfer_type'] === 'internal' && $tf['recipient_id'] > 0) {
                    $recip_id  = (int)$tf['recipient_id'];
                    $recip_bal = getBalance($pdo, $recip_id);
                    updateBalance($pdo, $recip_id, $recip_bal + $amount);


                    // recordTransaction(
                    //     $pdo, $recip_id, 'deposit', $amount,
                    //     $recip_bal, $recip_bal + $amount,
                    //     'Transfer Received from account '.$tf['sender_account'],
                    //     $tf['reference'], 'completed',
                    //     null, $tf['sender_account']
                    // );


                      recordTransaction(
                        $pdo, $recip_id,  'deposit', $amount, $recip_bal, $recip_bal + $amount,
                        'Transfer Received from account '.$tf['sender_account'],
                        $tf['reference'] . '_RCV',
                        'completed',  null,  $tf['sender_account']
                    );

                }

                $pdo->prepare("UPDATE transfers SET status='completed' WHERE id=?")->execute([$transfer_id]);
                $pdo->prepare("   UPDATE transactions SET status='completed'
                    WHERE user_id=? AND reference=? AND status='processing'
                ")->execute([$sender_id, $tf['reference']]);

                $_SESSION['success_message'] = "✅ Funds Released! Transfer of ".formatMoney($amount)."
                 completed. Recipient has been credited. Reference: <code>{$tf['reference']}</code>";
                break;

            // ======================================================
            // RETURN FUNDS
            // Returns reserved funds back to sender — reverses the reservation
            // ======================================================
            case 'return':
                if($tf['status'] !== 'processing') throw new Exception("Only reserved transfers can have funds returned.");

                $cur_bal = getBalance($pdo, $sender_id);
                updateBalance($pdo, $sender_id, $cur_bal + $amount);

                $pdo->prepare(" UPDATE transactions SET status='failed'
                    WHERE user_id=? AND reference=? AND status='processing'
                ")->execute([$sender_id, $tf['reference']]);

                recordTransaction(
                    $pdo, $sender_id, 'deposit', $amount,
                    $cur_bal, $cur_bal + $amount,
                    'Transfer Reversed — Reserved Funds Returned to Account',
                    $tf['reference'].'_REV', 'completed'
                );

              

                $pdo->prepare("UPDATE transfers SET status='reversed' WHERE id=?")->execute([$transfer_id]);

                $_SESSION['success_message'] = "⚠️ Funds Returned! ".formatMoney($amount)." returned to sender's account.
                 Transfer marked as reversed.";
                break;

            // ======================================================
            // APPROVE (One-step: reserve + release immediately)
            // ======================================================
            case 'approve':
                if($tf['status'] !== 'pending') throw new Exception("Only pending transfers can be approved.");
                $cur_bal = getBalance($pdo, $sender_id);
                if($cur_bal < $amount) {
                    throw new Exception("Insufficient funds: sender's balance is ".formatMoney($cur_bal).".");
                }
                updateBalance($pdo, $sender_id, $cur_bal - $amount);

                $pdo->prepare(" UPDATE transactions
                    SET status='completed', balance_before=?, balance_after=?
                    WHERE user_id=? AND reference=? AND status='pending'
                ")->execute([$cur_bal, $cur_bal-$amount, $sender_id, $tf['reference']]);

                if($tf['transfer_type'] === 'internal' && $tf['recipient_id'] > 0) {
                    $recip_id  = (int)$tf['recipient_id'];
                    $recip_bal = getBalance($pdo, $recip_id);
                    updateBalance($pdo, $recip_id, $recip_bal + $amount);

                     recordTransaction(
                        $pdo, $recip_id,  'received', $amount, $recip_bal, $recip_bal + $amount,
                        'Transfer Received from account '.$tf['sender_account'],
                        $tf['reference'] . '_RCV',
                        'completed',  null,  $tf['sender_account']
                    );
                }

                $pdo->prepare("UPDATE transfers SET status='completed' WHERE id=?")->execute([$transfer_id]);

                $_SESSION['success_message'] = "✅ Transfer Approved & Completed! ".formatMoney($amount)."
                 processed. Reference: <code>{$tf['reference']}</code>";
                break;

            // ======================================================
            // REJECT — No balance changes
            // ======================================================
            case 'reject':
                if($tf['status'] !== 'pending') throw new Exception("Only pending transfers can be rejected.");
                $pdo->prepare("UPDATE transfers SET status='failed' WHERE id=?")->execute([$transfer_id]);
                $pdo->prepare(" UPDATE transactions SET status='failed'
                    WHERE user_id=? AND reference=? AND status='pending'
                ")->execute([$sender_id, $tf['reference']]);
                $_SESSION['success_message'] = "Transfer rejected. Sender's balance was not affected.";
                break;

            default:
                throw new Exception("Unknown action: {$action}");
        }

        $pdo->commit();

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: transfers-pending"); 
    exit;
}

// ============================================================
// FILTERS
// ============================================================
$filter = $_GET['filter'] ?? 'all'; // all | pending | processing
$search = trim($_GET['search'] ?? '');
$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page-1)*$limit;

// Stats for pending/processing
$stats = $pdo->query("SELECT
        SUM(status='pending')                                             AS cnt_pending,
        SUM(status='processing')                                          AS cnt_processing,
        COALESCE(SUM(CASE WHEN status='pending'    THEN amount END), 0)   AS vol_pending,
        COALESCE(SUM(CASE WHEN status='processing' THEN amount END), 0)   AS vol_processing,
        SUM(DATEDIFF(NOW(),created_at) > 1 AND status IN('pending','processing')) AS cnt_overdue
    FROM transfers
    WHERE status IN('pending','processing')
")->fetch(PDO::FETCH_ASSOC);

$where  = ["tf.status IN('pending','processing')"];
$params = [];

if($filter === 'pending')    { $where[0] = "tf.status = 'pending'"; }
if($filter === 'processing') { $where[0] = "tf.status = 'processing'"; }

if($search !== '') {
    $where[] = "(tf.reference LIKE ? OR su.first_name LIKE ? OR su.last_name LIKE ? OR su.account_number LIKE ? OR tf.recipient_name_ext LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params, [$sq,$sq,$sq,$sq,$sq]);
}

$where_sql = implode(' AND ', $where);

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
           su.first_name  AS sfn, su.last_name AS sln,
           su.email       AS semail,
           su.account_number AS s_accno,
           COALESCE(sa.balance,0) AS sender_balance,
           ru.first_name  AS rfn, ru.last_name AS rln,
           ru.account_number AS r_accno,
           DATEDIFF(NOW(), tf.created_at) AS days_waiting
    FROM transfers tf
    JOIN users su ON tf.sender_id = su.id
    LEFT JOIN users ru ON tf.recipient_id = ru.id AND tf.recipient_id > 0
    LEFT JOIN accounts sa ON su.id = sa.user_id
    WHERE {$where_sql}
    ORDER BY
        CASE tf.status WHEN 'processing' THEN 1 ELSE 2 END,
        tf.created_at ASC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function pendUrl($o=[]) {
    return '?'.http_build_query(array_merge(array_filter($_GET,'strlen'),$o));
}
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-hourglass-half mr-2 text-warning"></i> Pending Transfers</h1>
    <div>
        <a href="transfers-all"       class="btn btn-secondary btn-sm mr-1">
            <i class="fas fa-list mr-1"></i> All Transfers
        </a>
        <a href="transfers-completed" class="btn btn-success btn-sm">
            <i class="fas fa-check-circle mr-1"></i> Completed
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
        ['Awaiting Review',  number_format($stats['cnt_pending']),              'bg-warning',  'fas fa-clock'],
        ['Funds Reserved',   number_format($stats['cnt_processing']),           'bg-primary',  'fas fa-lock'],
        ['Pending Volume',   '$'.number_format($stats['vol_pending'],2),        'bg-warning',  'fas fa-dollar-sign'],
        ['Reserved Volume',  '$'.number_format($stats['vol_processing'],2),     'bg-primary',  'fas fa-dollar-sign'],
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

<!-- RESERVE FLOW INFO -->
<div class="alert alert-info d-flex align-items-start mb-4">
    <i class="fas fa-info-circle fa-lg mr-3 mt-1 flex-shrink-0"></i>
    <div>
        <strong>Transfer Review Flow:</strong><br>
        <small>
            <b>Reserve Funds</b> → Deducts sender's balance &amp; holds it. Customer sees "Processing". Recipient NOT credited yet.<br>
            <b>Release Funds</b> → Credits recipient and completes the transfer.<br>
            <b>Return Funds</b> → Sends the reserved amount back to the sender and reverses the transfer.<br>
            <em>Or use <b>Approve</b> to do Reserve + Release in one click, or <b>Reject</b> to cancel without touching balances.</em>
        </small>
    </div>
</div>

<!-- STATUS TABS -->
<ul class="nav nav-tabs mb-3">
    <?php
    $total_both = ($stats['cnt_pending'] + $stats['cnt_processing']);
    foreach([
        ['all',         "All ({$total_both})",                      ''],
        ['pending',     "Pending ({$stats['cnt_pending']})",         'badge-warning'],
        ['processing',  "Reserved ({$stats['cnt_processing']})",     'badge-primary'],
    ] as [$val,$label,$badge]):
    ?>
    <li class="nav-item">
        <a href="<?php echo pendUrl(['filter'=>$val,'page'=>1]); ?>"
           class="nav-link <?php echo $filter===$val?'active':''; ?>">
            <?php echo $label; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- SEARCH -->
<form method="GET" class="row mb-3 align-items-end">
    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
    <div class="col-md-5">
        <input type="text" name="search" class="form-control"
               placeholder="Search reference, sender name, account number..."
               value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-search mr-1"></i> Search
        </button>
    </div>
    <?php if($search): ?>
    <div class="col-md-2">
        <a href="<?php echo pendUrl(['search'=>'','page'=>1]); ?>" class="btn btn-outline-secondary btn-block">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
    <?php endif; ?>
    <div class="col text-right">
        <small class="text-muted"><?php echo number_format($total_rows); ?> transfer(s)</small>
    </div>
</form>

<!-- TABLE -->
<?php if(empty($transfers)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-check-double fa-4x text-success mb-3 d-block" style="opacity:.3;"></i>
        <h4 class="text-muted">No Pending Transfers</h4>
        <p class="text-muted">All transfers have been reviewed. Great work!</p>
        <a href="transfers-all" class="btn btn-primary">View All Transfers</a>
    </div>
</div>
<?php else: ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Sender</th>
                        <th>Sender Balance</th>
                        <th>Amount</th>
                        <th>Recipient</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th style="min-width:280px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transfers as $i => $tf):
                        $is_ext   = ($tf['transfer_type'] === 'external');
                        $is_proc  = ($tf['status'] === 'processing');
                        $amount   = (float)$tf['amount'];
                        $bal      = (float)$tf['sender_balance'];
                        $can_pay  = $bal >= $amount;
                        $overdue  = ($tf['days_waiting'] ?? 0) > 1;

                        // Recipient display
                        if($is_ext) {
                            $recip_name = htmlspecialchars($tf['recipient_name_ext'] ?? 'External');
                            $recip_acct = htmlspecialchars($tf['recipient_account']  ?? '—');
                            $recip_bank = htmlspecialchars($tf['recipient_bank_name'] ?? '—');
                        } else {
                            $recip_name = htmlspecialchars(($tf['rfn']??'Unknown').' '.($tf['rln']??''));
                            $recip_acct = htmlspecialchars($tf['r_accno'] ?? $tf['recipient_account'] ?? '—');
                            $recip_bank = BANK_NAME; // 'MidwesternBank, N.A.'
                        }

                        // Row styling
                        if($is_proc) {
                            $row_class = 'table-info';
                        } elseif(!$can_pay) {
                            $row_class = 'table-danger';
                        } elseif($overdue) {
                            $row_class = 'table-warning';
                        } else {
                            $row_class = '';
                        }
                    ?>
                    <tr class="<?php echo $row_class; ?>">

                        <!-- # -->
                        <td><?php echo $offset+$i+1; ?></td>

                        <!-- Sender -->
                        <td>
                            <div class="font-weight-bold">
                                <?php echo htmlspecialchars($tf['sfn'].' '.$tf['sln']); ?>
                            </div>
                            <small class="text-muted"><?php echo htmlspecialchars($tf['s_accno']); ?></small><br>
                            <small class="text-muted"><?php echo htmlspecialchars($tf['semail']); ?></small>
                        </td>

                        <!-- Sender Balance -->
                        <td>
                            <span class="font-weight-bold <?php echo $can_pay?'text-success':'text-danger'; ?>">
                                $<?php echo number_format($bal,2); ?>
                            </span>
                            <?php if(!$can_pay && !$is_proc): ?>
                                <br><span class="badge badge-danger" style="font-size:9px;">INSUFFICIENT</span>
                            <?php endif; ?>
                            <?php if($is_proc): ?>
                                <br><small class="text-primary"><i class="fas fa-lock mr-1"></i>Funds held</small>
                            <?php endif; ?>
                        </td>

                        <!-- Amount -->
                        <td class="font-weight-bold text-danger" style="font-size:15px;">
                            $<?php echo number_format($amount,2); ?>
                            <?php if(!empty($tf['description'])): ?>
                                <br><small class="text-muted font-weight-normal">
                                    "<?php echo htmlspecialchars(substr($tf['description'],0,25)); ?>"
                                </small>
                            <?php endif; ?>
                        </td>

                        <!-- Recipient -->
                        <td>
                            <div class="font-weight-bold"><?php echo $recip_name; ?></div>
                            <small class="text-muted"><?php echo $recip_bank; ?></small><br>
                            <code style="font-size:11px;"><?php echo $recip_acct; ?></code>
                            <?php if($is_ext && !empty($tf['recipient_bank_swift'])): ?>
                                <br><small class="text-muted">SWIFT: <?php echo htmlspecialchars($tf['recipient_bank_swift']); ?></small>
                            <?php endif; ?>
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
                                <?php echo htmlspecialchars(substr($tf['reference']??'—',0,16)); ?>
                            </code>
                        </td>

                        <!-- Date -->
                        <td>
                            <small><?php echo date('d M Y',strtotime($tf['created_at'])); ?></small><br>
                            <small class="text-muted"><?php echo date('H:i',strtotime($tf['created_at'])); ?></small>
                            <?php if($overdue): ?>
                                <br><span class="badge badge-danger" style="font-size:9px;">⏰ <?php echo $tf['days_waiting']; ?>d</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td>
                            <?php if($is_proc): ?>
                                <span class="badge badge-primary">🔒 Reserved</span>
                            <?php else: ?>
                                <span class="badge badge-warning">⏳ Pending</span>
                            <?php endif; ?>
                        </td>

                        <!-- ACTIONS -->
                        <td>
                            <?php if(!$is_proc): ?>
                            <!-- ======= PENDING ACTIONS ======= -->
                            <div class="d-flex flex-wrap" style="gap:4px;">

                                <?php if($can_pay): ?>
                                <!-- RESERVE -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('RESERVE $<?php echo number_format($amount,2); ?> from <?php echo htmlspecialchars(addslashes($tf['sfn'].' '.$tf['sln'])); ?>?\n\nThis will deduct the amount from their balance and HOLD it.\nThe recipient will NOT be credited yet.')">
                                    <input type="hidden" name="transfer_id"     value="<?php echo $tf['id']; ?>">
                                    <input type="hidden" name="transfer_action" value="reserve">
                                    <button class="btn btn-sm mb-1"
                                            style="background:#0ea5e9;color:#fff;border:none;padding:4px 10px;border-radius:5px;"
                                            title="Reserve Funds — Hold amount from sender">
                                        <i class="fas fa-lock mr-1"></i> Reserve
                                    </button>
                                </form>

                                <!-- APPROVE (1-step) -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('APPROVE this transfer directly?\n\nThis will deduct from sender AND credit recipient immediately.')">
                                    <input type="hidden" name="transfer_id"     value="<?php echo $tf['id']; ?>">
                                    <input type="hidden" name="transfer_action" value="approve">
                                    <button class="btn btn-success btn-sm mb-1" style="padding:4px 10px;"
                                            title="Approve — Deduct sender & credit recipient now">
                                        <i class="fas fa-check mr-1"></i> Approve
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="btn btn-secondary btn-sm mb-1 disabled" style="font-size:11px;opacity:.6;">
                                    <i class="fas fa-ban mr-1"></i> No Funds
                                </span>
                                <?php endif; ?>

                                <!-- REJECT -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('REJECT this transfer?\n\nThe sender\'s balance will NOT be affected.\nThe transfer will be marked as failed.')">
                                    <input type="hidden" name="transfer_id"     value="<?php echo $tf['id']; ?>">
                                    <input type="hidden" name="transfer_action" value="reject">
                                    <button class="btn btn-danger btn-sm mb-1" style="padding:4px 10px;"
                                            title="Reject — Cancel without touching balance">
                                        <i class="fas fa-times mr-1"></i> Reject
                                    </button>
                                </form>

                            </div>
                            <?php else: ?>
                            <!-- ======= PROCESSING ACTIONS ======= -->
                            <div class="mb-1 px-2 py-1 rounded" style="background:#dbeafe;font-size:11px;color:#1e40af;">
                                <i class="fas fa-info-circle mr-1"></i> Funds are reserved
                            </div>
                            <div class="d-flex flex-wrap" style="gap:4px;">

                                <!-- RELEASE -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('RELEASE funds to recipient?\n\nThis credits the recipient and COMPLETES the transfer.\nThis action cannot be undone.')">
                                    <input type="hidden" name="transfer_id"     value="<?php echo $tf['id']; ?>">
                                    <input type="hidden" name="transfer_action" value="release">
                                    <button class="btn btn-sm mb-1"
                                            style="background:#10b981;color:#fff;border:none;padding:4px 10px;border-radius:5px;"
                                            title="Release Funds — Credit recipient">
                                        <i class="fas fa-unlock mr-1"></i> Release
                                    </button>
                                </form>

                                <!-- RETURN -->
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('RETURN funds to sender?\n\nThis reverses the reservation and returns the amount to the sender.\nThe transfer will be marked as reversed.')">
                                    <input type="hidden" name="transfer_id"     value="<?php echo $tf['id']; ?>">
                                    <input type="hidden" name="transfer_action" value="return">
                                    <button class="btn btn-sm mb-1"
                                            style="background:#f59e0b;color:#fff;border:none;padding:4px 10px;border-radius:5px;"
                                            title="Return Funds — Send back to sender">
                                        <i class="fas fa-undo mr-1"></i> Return
                                    </button>
                                </form>

                            </div>
                            <?php endif; ?>

                            <!-- View Sender -->
                            <a href="customer-detail-<?php echo $tf['sender_id']; ?>"
                               class="btn btn-info btn-sm mt-1" style="padding:4px 10px;">
                                <i class="fas fa-eye mr-1"></i> Sender
                            </a>
                        </td>

                    </tr>
                    <?php endforeach; ?>
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
                        <a class="page-link" href="<?php echo pendUrl(['page'=>$page-1]); ?>">&laquo;</a>
                    </li>
                <?php endif; ?>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                    <li class="page-item <?php echo $i===$page?'active':''; ?>">
                        <a class="page-link" href="<?php echo pendUrl(['page'=>$i]); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if($page<$total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo pendUrl(['page'=>$page+1]); ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

</section>
</div>
<?php include 'layout/footer.php'; ?>