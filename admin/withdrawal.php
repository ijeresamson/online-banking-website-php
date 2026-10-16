<?php


// ============================================================
// AJAX HANDLER — MUST BE FIRST before any HTML output
// ============================================================
if(isset($_GET['ajax_lookup'])) {
    session_start();
    require_once '../config/db-config.php';
    require_once '../config/helpers.php';

    header('Content-Type: application/json');
    header('Cache-Control: no-cache');

    if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

    if(!isset($_SESSION['admin'])) {
        echo json_encode(['error' => 'Session expired. Please log in again.']);
        exit;
    }

    $acc = trim($_GET['acc'] ?? '');
    if(empty($acc)) {
        echo json_encode(['error' => 'Please enter an account number.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT
                u.id, u.first_name, u.last_name, u.email,
                u.account_number, u.status, u.phone,
                COALESCE(a.balance,0) AS balance,
                a.account_type, a.account_name, a.currency
            FROM users u
            LEFT JOIN accounts a ON u.id = a.user_id
            WHERE u.account_number = ?
            LIMIT 1
        ");
        $stmt->execute([$acc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!$row) {
            echo json_encode(['error' => 'No account found with number: '.htmlspecialchars($acc)]);
        } else {
            echo json_encode($row);
        }
    } catch(PDOException $e) {
        error_log('Withdrawal AJAX error: '.$e->getMessage());
        echo json_encode(['error' => 'Database error. Please try again.']);
    }
    exit;
}
?>
<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

// ============================================================
// HANDLE PENDING WITHDRAWAL APPROVE / REJECT
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wd_queue_action'])) {
    $wd_id  = (int)$_POST['wd_id'];
    $action = $_POST['wd_queue_action'];

    try {
        $s = $pdo->prepare("SELECT * FROM withdrawals WHERE id=? AND status='pending' LIMIT 1");
        $s->execute([$wd_id]);
        $wd = $s->fetch(PDO::FETCH_ASSOC);
        if(!$wd) throw new Exception("Withdrawal not found or already processed.");

        $pdo->beginTransaction();

        if($action === 'approve') {
            $cur_bal = getBalance($pdo, $wd['user_id']);
            if($cur_bal < (float)$wd['amount']) {
                throw new Exception("Insufficient funds: balance is $".number_format($cur_bal,2).".");
            }
            $new_bal = $cur_bal - (float)$wd['amount'];
            updateBalance($pdo, $wd['user_id'], $new_bal);

            $pdo->prepare("
                UPDATE transactions
                SET status='completed', balance_before=?, balance_after=?
                WHERE user_id=? AND reference=? AND status='pending'
            ")->execute([$cur_bal, $new_bal, $wd['user_id'], $wd['reference']]);

            $pdo->prepare("UPDATE withdrawals SET status='completed', admin_id=? WHERE id=?")->execute([$_SESSION['admin']['id'], $wd_id]);

            $_SESSION['success_message'] = "✅ Withdrawal of $".number_format($wd['amount'],2)." approved and processed. New balance: $".number_format($new_bal,2).".";

        } elseif($action === 'reject') {
            $pdo->prepare("UPDATE withdrawals SET status='rejected', admin_id=? WHERE id=?")->execute([$_SESSION['admin']['id'], $wd_id]);
            $pdo->prepare("UPDATE transactions SET status='failed' WHERE user_id=? AND reference=? AND status='pending'")->execute([$wd['user_id'], $wd['reference']]);
            $_SESSION['success_message'] = "Withdrawal request rejected. Customer's balance was not affected.";
        }

        $pdo->commit();

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: withdrawal"); exit;
}

// ============================================================
// HANDLE MANUAL WITHDRAWAL FORM
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_withdrawal'])) {
    try {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        $amount     = (float)str_replace([',','$'],'', $_POST['amount'] ?? '0');
        $method     = trim($_POST['method']  ?? 'admin_debit');
        $note       = trim($_POST['note']    ?? '');

        if($target_uid <= 0) throw new Exception("Please search and select a valid customer account first.");
        if($amount   <= 0)   throw new Exception("Withdrawal amount must be greater than $0.00.");
        if($amount   > 500000) throw new Exception("Maximum single withdrawal is $500,000.00.");

        $allowed_methods = ['admin_debit','bank_transfer','wire_transfer','check'];
        if(!in_array($method, $allowed_methods)) throw new Exception("Invalid withdrawal method.");

        $s = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.account_number, u.status,
                   COALESCE(a.balance,0) AS balance
            FROM users u LEFT JOIN accounts a ON u.id=a.user_id
            WHERE u.id=? LIMIT 1
        ");
        $s->execute([$target_uid]);
        $target = $s->fetch(PDO::FETCH_ASSOC);

        if(!$target)                     throw new Exception("Customer not found.");
        if($target['status'] !== 'active') throw new Exception("Cannot withdraw from a {$target['status']} account. Account must be active.");
        if((float)$target['balance'] < $amount) {
            throw new Exception("Insufficient funds: account balance is $".number_format($target['balance'],2)." but $".number_format($amount,2)." was requested.");
        }

        $pdo->beginTransaction();

        $cur_bal = (float)$target['balance'];
        $new_bal = $cur_bal - $amount;
        $ref = generateRef('WDR');
        $method_labels = [
            'admin_debit'   => 'Admin Debit',
            'bank_transfer' => 'Bank Transfer / ACH',
            'wire_transfer' => 'Wire Transfer',
            'check'         => 'Check Issued',
        ];
        $desc = ($method_labels[$method] ?? ucfirst($method)) . ($note ? " — {$note}" : '');

        updateBalance($pdo, $target_uid, $new_bal);
        recordTransaction($pdo, $target_uid, 'withdrawal', $amount, $cur_bal, $new_bal, $desc, $ref, 'completed');
        $pdo->prepare("INSERT INTO withdrawals (user_id, amount, method, destination, reference, note, status, admin_id, created_at)
            VALUES (?, ?, ?, 'Admin Processed', ?, ?, 'completed', ?, NOW())
        ")->execute([$target_uid, $amount, $method, $ref, $note, $_SESSION['admin']['id']]);

        $pdo->commit();

        $_SESSION['success_message'] =
            "✅ Withdrawal of <strong>\$".number_format($amount,2)."</strong> processed from " .
            "<strong>{$target['first_name']} {$target['last_name']}</strong>. " .
            "New balance: <strong>\$".number_format($new_bal,2)."</strong>. " .
            "Reference: <code>{$ref}</code>";

        header("Location: withdrawal");
        exit;

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Pending withdrawal queue
$pending_wds = [];
try {
    $pending_wds = $pdo->query("SELECT w.*, u.first_name, u.last_name, u.account_number,
               COALESCE(a.balance,0) AS balance
        FROM withdrawals w
        JOIN users u ON w.user_id=u.id
        LEFT JOIN accounts a ON u.id=a.user_id
        WHERE w.status='pending'
        ORDER BY w.created_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Recent processed
$recent_wds = [];
try {
    $recent_wds = $pdo->query("SELECT w.*, u.first_name, u.last_name, u.account_number
        FROM withdrawals w JOIN users u ON w.user_id=u.id
        WHERE w.status IN('completed','rejected')
        ORDER BY w.created_at DESC LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-arrow-circle-up mr-2 text-danger"></i> Process Withdrawals</h1>
    <div>
        <?php if(!empty($pending_wds)): ?>
        <span style="background:#fef3c7;color:#d97706;padding:7px 16px;border-radius:20px;font-weight:600;font-size:13px;margin-right:8px;">
            <i class="fas fa-clock mr-1"></i>
            <?php echo count($pending_wds); ?> Customer Requests Pending
        </span>
        <?php endif; ?>
        <a href="transactions-all" class="btn btn-secondary btn-sm">
            <i class="fas fa-list mr-1"></i> All Transactions
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

<!-- ============================================================ PENDING QUEUE ============================================================ -->
<?php if(!empty($pending_wds)): ?>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
        <i class="fas fa-clock text-warning fa-lg mr-2"></i>
        <h4 class="mb-0 text-dark">Customer Withdrawal Requests — Pending Review (<?php echo count($pending_wds); ?>)</h4>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:13px;">
                <thead>
                    <tr style="background:#f8f9fa;">
                        <th style="padding:12px 16px;">Customer</th>
                        <th style="padding:12px 16px;">Amount Requested</th>
                        <th style="padding:12px 16px;">Account Balance</th>
                        <th style="padding:12px 16px;">Method</th>
                        <th style="padding:12px 16px;">Destination</th>
                        <th style="padding:12px 16px;">Submitted</th>
                        <th style="padding:12px 16px;min-width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_wds as $wd):
                        $can_pay   = (float)$wd['balance'] >= (float)$wd['amount'];
                        $days_wait = max(0, floor((time()-strtotime($wd['created_at']))/86400));
                    ?>
                    <tr style="border-bottom:1px solid #f1f3f5;<?php echo !$can_pay?'background:#fff5f5;':''; ?>">
                        <td style="padding:13px 16px;">
                            <div style="font-weight:600;"><?php echo htmlspecialchars($wd['first_name'].' '.$wd['last_name']); ?></div>
                            <code style="font-size:11px;"><?php echo htmlspecialchars($wd['account_number']); ?></code>
                        </td>
                        <td style="padding:13px 16px;font-size:16px;font-weight:800;color:#dc3545;">
                            -$<?php echo number_format($wd['amount']??0,2); ?>
                        </td>
                        <td style="padding:13px 16px;">
                            <span style="font-weight:700;color:<?php echo $can_pay?'#28a745':'#dc3545'; ?>;">
                                $<?php echo number_format($wd['balance']??0,2); ?>
                            </span>
                            <?php if(!$can_pay): ?>
                                <span style="background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;margin-left:4px;">INSUFFICIENT</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:13px 16px;">
                            <?php echo ucfirst(str_replace('_',' ',$wd['method']??'')); ?>
                        </td>
                        <td style="padding:13px 16px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <small class="text-muted"><?php echo htmlspecialchars($wd['destination']??'—'); ?></small>
                        </td>
                        <td style="padding:13px 16px;">
                            <div style="font-size:12px;"><?php echo date('d M Y',strtotime($wd['created_at'])); ?></div>
                            <?php if($days_wait > 1): ?>
                                <span style="background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;">
                                    <?php echo $days_wait; ?>d waiting
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:13px 16px;">
                            <?php if($can_pay): ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Approve withdrawal of $<?php echo number_format($wd['amount'],2); ?> for <?php echo htmlspecialchars(addslashes($wd['first_name'].' '.$wd['last_name'])); ?>?\n\nThis will immediately deduct their balance.')">
                                <input type="hidden" name="wd_id"            value="<?php echo $wd['id']; ?>">
                                <input type="hidden" name="wd_queue_action"  value="approve">
                                <button class="btn btn-success btn-sm mr-1">
                                    <i class="fas fa-check mr-1"></i> Approve
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Reject this withdrawal? The balance will NOT be affected.')">
                                <input type="hidden" name="wd_id"            value="<?php echo $wd['id']; ?>">
                                <input type="hidden" name="wd_queue_action"  value="reject">
                                <button class="btn btn-danger btn-sm mr-1">
                                    <i class="fas fa-times mr-1"></i> Reject
                                </button>
                            </form>
                            <a href="customer-detail-<?php echo $wd['user_id']; ?>"
                               class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================ MANUAL WITHDRAWAL FORM ============================================================ -->
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <i class="fas fa-minus-circle text-danger mr-2 fa-lg"></i>
                <h4 class="mb-0">Process Manual Withdrawal</h4>
            </div>
            <div class="card-body">

                <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="wdForm" autocomplete="off">
                    <input type="hidden" name="form_withdrawal" value="1">
                    <input type="hidden" name="target_user_id" id="wdTargetId" value="">

                    <!-- STEP 1: FIND ACCOUNT -->
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px;margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:12px;">
                            <span style="background:#dc3545;color:#fff;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px;">1</span>
                            Find Customer Account
                        </div>
                        <div class="input-group">
                            <input type="text" id="wdAccSearch" class="form-control"
                                   placeholder="Enter 10-digit account number..."
                                   maxlength="20" style="font-family:monospace;font-size:15px;letter-spacing:2px;">
                            <div class="input-group-append">
                                <button type="button" id="wdSearchBtn" class="btn btn-primary"
                                        onclick="wdLookup()">
                                    <i class="fas fa-search mr-1"></i> Search
                                </button>
                            </div>
                        </div>
                        <small class="text-muted mt-1 d-block">Press Enter or click Search to verify the account.</small>
                    </div>

                    <!-- Account Preview -->
                    <div id="wdPreview" style="display:none;margin-bottom:20px;"></div>

                    <!-- STEP 2: AMOUNT -->
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px;margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:12px;">
                            <span style="background:#dc3545;color:#fff;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px;">2</span>
                            Withdrawal Amount (USD)
                        </div>
                        <div class="input-group input-group-lg">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold" style="font-size:18px;">$</span>
                            </div>
                            <input type="number" name="amount" id="wdAmount"
                                   class="form-control" style="font-size:20px;font-weight:700;"
                                   placeholder="0.00" step="0.01" min="0.01" max="500000"
                                   oninput="wdCheckBalance()">
                        </div>
                        <!-- Balance warning -->
                        <div id="wdBalanceWarn" style="display:none;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:10px 14px;margin-top:10px;">
                            <i class="fas fa-exclamation-triangle text-danger mr-2"></i>
                            <span style="color:#dc2626;font-weight:600;">Amount exceeds account balance!</span>
                        </div>
                        <div id="wdBalanceOk" style="display:none;background:#f0fff4;border:1px solid #86efac;border-radius:8px;padding:10px 14px;margin-top:10px;">
                            <i class="fas fa-check-circle text-success mr-2"></i>
                            <span style="color:#059669;font-weight:600;">Sufficient balance available.</span>
                        </div>
                    </div>

                    <!-- STEP 3: METHOD -->
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="font-weight-bold" style="font-size:12px;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;">
                            <span style="background:#dc3545;color:#fff;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px;">3</span>
                            Withdrawal Method
                        </label>
                        <select name="method" class="form-control">
                            <option value="admin_debit">Admin Debit (Direct)</option>
                            <option value="bank_transfer">Bank Transfer / ACH</option>
                            <option value="wire_transfer">Wire Transfer</option>
                            <option value="check">Check Issued</option>
                        </select>
                    </div>

                    <!-- STEP 4: NOTE -->
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="font-weight-bold">
                            Internal Note
                            <small class="text-muted font-weight-normal">(Optional)</small>
                        </label>
                        <input type="text" name="note" class="form-control"
                               placeholder="e.g. Customer request via phone, Account closure withdrawal..."
                               maxlength="200">
                    </div>

                    <!-- WARNING -->
                    <div style="background:#fff5f5;border:1px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:12px;">
                        <i class="fas fa-exclamation-triangle text-danger mr-2"></i>
                        <strong>Caution:</strong> This will <strong>immediately deduct</strong> the entered amount from the customer's balance.
                        Double-check the account and amount before confirming. This cannot be undone from the dashboard.
                    </div>

                    <!-- SUBMIT -->
                    <button type="submit" id="wdSubmitBtn" class="btn btn-danger btn-block btn-lg" disabled
                            style="height:50px;font-size:15px;font-weight:600;border-radius:8px;">
                        <i class="fas fa-minus-circle mr-2"></i>
                        Confirm & Process Withdrawal
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Withdrawals -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h4><i class="fas fa-history mr-2"></i> Recently Processed</h4></div>
            <div class="card-body p-0">
                <?php if(empty($recent_wds)): ?>
                    <p class="text-center text-muted py-4">No processed withdrawals yet.</p>
                <?php else: ?>
                    <div style="padding:0 16px;">
                        <?php foreach($recent_wds as $wd):
                            $is_done = ($wd['status']==='completed');
                        ?>
                        <div style="display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid #f1f3f5;">
                            <div style="width:36px;height:36px;background:<?php echo $is_done?'#fee2e2':'#f3f4f6'; ?>;color:<?php echo $is_done?'#dc3545':'#6c757d'; ?>;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                                <i class="fas fa-<?php echo $is_done?'arrow-up':'times'; ?>"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?php echo htmlspecialchars($wd['first_name'].' '.$wd['last_name']); ?>
                                </div>
                                <div style="font-size:11px;color:#adb5bd;"><?php echo htmlspecialchars($wd['account_number']); ?></div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="font-size:14px;font-weight:700;color:<?php echo $is_done?'#dc3545':'#6c757d'; ?>;">
                                    -$<?php echo number_format($wd['amount']??0,2); ?>
                                </div>
                                <span style="background:<?php echo $is_done?'#fee2e2':'#f3f4f6'; ?>;color:<?php echo $is_done?'#dc3545':'#6c757d'; ?>;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;">
                                    <?php echo ucfirst($wd['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</section>
</div>

<script>
let wdUser = null;

document.getElementById('wdAccSearch').addEventListener('keydown', function(e) {
    if(e.key === 'Enter') { e.preventDefault(); wdLookup(); }
});

function wdLookup() {
    const acc  = document.getElementById('wdAccSearch').value.trim();
    if(!acc) return;

    const preview = document.getElementById('wdPreview');
    const btn     = document.getElementById('wdSearchBtn');

    preview.style.display = 'block';
    preview.innerHTML = `
        <div style="background:#f8f9fa;border-radius:10px;padding:20px;text-align:center;border:2px dashed #dee2e6;">
            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2 d-block"></i>
            <span class="text-muted">Searching account...</span>
        </div>
    `;
    btn.disabled    = true;
    wdUser          = null;
    document.getElementById('wdTargetId').value  = '';
    document.getElementById('wdSubmitBtn').disabled = true;
    document.getElementById('wdBalanceWarn').style.display = 'none';
    document.getElementById('wdBalanceOk').style.display   = 'none';

    fetch('withdrawal.php?ajax_lookup=1&acc=' + encodeURIComponent(acc))
        .then(r => {
            if(!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(data => {
            btn.disabled = false;

            if(data.error) {
                preview.innerHTML = `
                    <div style="background:#fff5f5;border:1.5px solid #fca5a5;border-radius:10px;padding:14px 16px;">
                        <i class="fas fa-exclamation-circle text-danger mr-2"></i>
                        <span style="color:#dc2626;font-weight:500;">${data.error}</span>
                    </div>
                `;
                return;
            }

            if(data.status !== 'active') {
                preview.innerHTML = `
                    <div style="background:#fff5f5;border:1.5px solid #fca5a5;border-radius:10px;padding:14px 16px;">
                        <i class="fas fa-ban text-danger mr-2"></i>
                        <span style="color:#dc2626;font-weight:500;">Cannot withdraw from a <strong>${data.status.replace('_',' ')}</strong> account. The account must be Active.</span>
                    </div>
                `;
                return;
            }

            wdUser = data;
            document.getElementById('wdTargetId').value = data.id;

            const initials = data.first_name.charAt(0).toUpperCase() + data.last_name.charAt(0).toUpperCase();

            preview.innerHTML = `
                <div style="border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:16px;border:2px solid #dc3545;background:#fff5f5;">
                    <div style="width:52px;height:52px;border-radius:13px;background:linear-gradient(135deg,#dc3545,#c82333);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0;">
                        ${initials}
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:16px;font-weight:700;color:#212529;">${data.first_name} ${data.last_name}</div>
                        <div style="font-size:12px;color:#6c757d;margin-top:2px;">${data.email}</div>
                        <code style="font-size:12px;">${data.account_number}</code>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:11px;color:#6c757d;text-transform:uppercase;">Available Balance</div>
                        <div style="font-size:26px;font-weight:800;color:#dc3545;" id="wdBalDisplay">$${parseFloat(data.balance).toFixed(2)}</div>
                    </div>
                </div>
            `;
            wdCheckBalance();
        })
        .catch(err => {
            btn.disabled = false;
            preview.innerHTML = `
                <div style="background:#fff5f5;border:1.5px solid #fca5a5;border-radius:10px;padding:14px 16px;">
                    <i class="fas fa-wifi text-danger mr-2"></i>
                    <span style="color:#dc2626;">Connection error. Please try again.</span>
                </div>
            `;
            console.error(err);
        });
}

function wdCheckBalance() {
    const amt  = parseFloat(document.getElementById('wdAmount').value) || 0;
    const warn = document.getElementById('wdBalanceWarn');
    const ok   = document.getElementById('wdBalanceOk');
    const btn  = document.getElementById('wdSubmitBtn');

    if(!wdUser || amt <= 0) {
        warn.style.display = 'none';
        ok.style.display   = 'none';
        btn.disabled       = true;
        return;
    }

    if(amt > parseFloat(wdUser.balance)) {
        warn.style.display = 'block';
        ok.style.display   = 'none';
        btn.disabled       = true;
    } else {
        warn.style.display = 'none';
        ok.style.display   = 'block';
        btn.disabled       = false;
    }
}

document.getElementById('wdForm').addEventListener('submit', function(e) {
    const uid = document.getElementById('wdTargetId').value;
    const amt = parseFloat(document.getElementById('wdAmount').value) || 0;
    if(!uid || amt <= 0) {
        e.preventDefault();
        alert('Please search for a customer and enter a valid amount first.');
        return;
    }
    const btn = document.getElementById('wdSubmitBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing withdrawal...';
});
</script>

<?php include 'layout/footer.php'; ?>