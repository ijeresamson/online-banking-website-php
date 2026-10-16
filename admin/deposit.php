<?php
// ============================================================
// AJAX HANDLER — MUST BE FIRST, before any HTML output
// ============================================================
if(isset($_GET['ajax_lookup'])) {
    session_start();
    require_once '../config/db-config.php';
    require_once '../config/helpers.php';

    header('Content-Type: application/json');
    header('Cache-Control: no-cache');

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
        $stmt = $pdo->prepare(" SELECT
                u.id, u.first_name, u.last_name, u.email,
                u.account_number, u.status, u.phone,
                COALESCE(a.balance,0)      AS balance,
                a.account_type,
                a.account_name,
                a.routing_number,
                a.currency
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
        error_log('Deposit AJAX lookup error: '.$e->getMessage());
        echo json_encode(['error' => 'Database error. Please try again.']);
    }
    exit;
}
?>
<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

// ============================================================
// HANDLE DEPOSIT FORM SUBMISSION
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_deposit'])) {
    try {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        $amount     = (float)str_replace([',','$'],'', $_POST['amount'] ?? '0');
        $method     = trim($_POST['method']  ?? 'admin_credit');
        $note       = trim($_POST['note']    ?? '');

        // Validate
        if($target_uid <= 0) throw new Exception("Please search and select a valid customer account first.");
        if($amount   <= 0)   throw new Exception("Deposit amount must be greater than $0.00.");
        if($amount   > 1000000) throw new Exception("Maximum single deposit is $1,000,000.00.");

        $allowed_methods = ['admin_credit','bank_transfer','wire_transfer','cash_deposit','check'];
        if(!in_array($method, $allowed_methods)) throw new Exception("Invalid deposit method.");

        // Verify customer exists
        $s = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, u.account_number, u.status,
                   COALESCE(a.balance,0) AS balance
            FROM users u
            LEFT JOIN accounts a ON u.id=a.user_id
            WHERE u.id=? LIMIT 1
        ");
        $s->execute([$target_uid]);
        $target = $s->fetch(PDO::FETCH_ASSOC);

        if(!$target) throw new Exception("Customer not found. Please search again.");
        if($target['status'] === 'closed') {
            throw new Exception("Cannot deposit to a closed account.");
        }
        if($target['status'] === 'suspended') {
            throw new Exception("Account is suspended. Activate the account first before depositing.");
        }

        $pdo->beginTransaction();

        $cur_bal = (float)$target['balance'];
        $new_bal = $cur_bal + $amount;
        $ref     = generateRef('DEP');

        $method_labels = [
            'admin_credit'  => 'Admin Credit',
            'bank_transfer' => 'Bank Transfer / ACH',
            'wire_transfer' => 'Wire Transfer',
            'cash_deposit'  => 'Cash Deposit',
            'check'         => 'Check Deposit',
        ];
        $method_label = $method_labels[$method] ?? ucfirst($method);
        $desc = $method_label.($note ? " — {$note}" : '');

        // Update balance
        updateBalance($pdo, $target_uid, $new_bal);

        // Record transaction
        recordTransaction(
            $pdo, $target_uid, 'deposit', $amount,
            $cur_bal, $new_bal,
            $desc, $ref, 'completed'
        );

        // Insert into deposits table
        $pdo->prepare("INSERT INTO deposits (user_id, amount, method, reference, note, status, admin_id, created_at)
            VALUES (?, ?, ?, ?, ?, 'completed', ?, NOW())
        ")->execute([$target_uid, $amount, $method, $ref, $note, $_SESSION['admin']['id']]);

        $pdo->commit();

        $_SESSION['success_message'] =
            "✅ Deposit of <strong>\$".number_format($amount,2)."</strong> successfully credited to " .
            "<strong>{$target['first_name']} {$target['last_name']}</strong> " .
            "({$target['account_number']}). " .
            "New balance: <strong>\$".number_format($new_bal,2)."</strong>. " .
            "Reference: <code>{$ref}</code>";

        header("Location: deposit");
        exit;

    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

// Recent deposits
$recent_deposits = [];
try {
    $recent_deposits = $pdo->query("SELECT d.*, u.first_name, u.last_name, u.account_number
        FROM deposits d
        JOIN users u ON d.user_id = u.id
        ORDER BY d.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$total_today = $pdo->query("SELECT COALESCE(SUM(amount),0)
    FROM deposits
    WHERE DATE(created_at) = CURDATE() AND status='completed'
")->fetchColumn();
?>

<style>
.dep-lookup-input { font-family:monospace; font-size:15px; letter-spacing:2px; }
.dep-account-card {
    border-radius:12px; padding:18px 20px;
    display:flex; align-items:center; gap:16px;
    border:2px solid #28a745; background:#f0fff4;
}
.dep-initials {
    width:52px; height:52px; border-radius:13px;
    background:linear-gradient(135deg,#28a745,#20c997);
    color:#fff; display:flex; align-items:center;
    justify-content:center; font-size:20px; font-weight:700;
    flex-shrink:0;
}
.dep-method-option {
    display:flex; align-items:center; gap:10px;
    padding:12px 14px; border:1.5px solid #e9ecef;
    border-radius:8px; cursor:pointer; transition:all .15s;
}
.dep-method-option:hover { border-color:#28a745; background:#f0fff4; }
.dep-method-option.selected { border-color:#28a745; background:#f0fff4; }
.dep-method-option input { flex-shrink:0; accent-color:#28a745; }
.dep-recent-item {
    display:flex; align-items:center; gap:12px;
    padding:11px 0; border-bottom:1px solid #f1f3f5;
}
.dep-recent-item:last-child { border-bottom:none; }
</style>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-arrow-circle-down mr-2 text-success"></i> Make a Deposit</h1>
    <div>
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

<!-- Today's Total -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card card-statistic-1">
            <div class="card-icon bg-success"><i class="fas fa-arrow-down"></i></div>
            <div class="card-wrap">
                <div class="card-header"><h4>Today's Deposits</h4></div>
                <div class="card-body">$<?php echo number_format($total_today,2); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row">

    <!-- ===== DEPOSIT FORM ===== -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <i class="fas fa-plus-circle text-success mr-2 fa-lg"></i>
                <h4 class="mb-0">Credit Customer Account</h4>
            </div>
            <div class="card-body">

                <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="depositForm" autocomplete="off">
                    <input type="hidden" name="form_deposit"    value="1">
                    <input type="hidden" name="target_user_id"  id="targetUserId" value="">

                    <!-- ======= STEP 1: FIND ACCOUNT ======= -->
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px;margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:12px;">
                            <span style="background:#007bff;color:#fff;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px;">1</span>
                            Find Customer Account
                        </div>
                        <div class="input-group">
                            <input type="text" id="accSearch" class="form-control dep-lookup-input"
                                   placeholder="Enter 10-digit account number..."
                                   maxlength="20">
                            <div class="input-group-append">
                                <button type="button" id="searchBtn" class="btn btn-primary"
                                        onclick="lookupAccount()">
                                    <i class="fas fa-search mr-1"></i> Search
                                </button>
                            </div>
                        </div>
                        <small class="text-muted mt-1 d-block">Press Enter or click Search to verify the account.</small>
                    </div>

                    <!-- Account Preview -->
                    <div id="accountPreview" style="display:none;margin-bottom:20px;">
                        <!-- Filled by JS -->
                    </div>

                    <!-- ======= STEP 2: AMOUNT ======= -->
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px;margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:12px;">
                            <span style="background:#007bff;color:#fff;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px;">2</span>
                            Deposit Amount (USD)
                        </div>
                        <div class="input-group input-group-lg">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold" style="font-size:18px;">$</span>
                            </div>
                            <input type="number" name="amount" id="amountInput"
                                   class="form-control" style="font-size:20px;font-weight:700;"
                                   placeholder="0.00" step="0.01" min="0.01" max="1000000"
                                   oninput="updatePreview()">
                        </div>
                        <!-- Quick Amount Buttons -->
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <?php foreach([100,500,1000,5000,10000,50000] as $q): ?>
                            <button type="button" class="btn btn-outline-success btn-sm"
                                    onclick="setAmount(<?php echo $q; ?>)">
                                $<?php echo number_format($q); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted mt-1 d-block">
                            Minimum: $0.01 · Maximum per deposit: $1,000,000.00
                        </small>
                    </div>

                    <!-- ======= STEP 3: METHOD ======= -->
                    <div style="background:#f8f9fa;border-radius:10px;padding:18px;margin-bottom:20px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:14px;">
                            <span style="background:#007bff;color:#fff;width:20px;height:20px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px;">3</span>
                            Deposit Method
                        </div>
                        <div class="row">
                            <?php
                            $methods = [
                                ['admin_credit',  'fas fa-shield-alt', 'Admin Credit',       'bg-primary', 'Direct admin credit'],
                                ['bank_transfer', 'fas fa-university',  'Bank Transfer (ACH)','bg-info',    'ACH / bank wire'],
                                ['wire_transfer', 'fas fa-globe',       'Wire Transfer',      'bg-purple',  'International wire'],
                                ['cash_deposit',  'fas fa-money-bill-wave','Cash Deposit',    'bg-success', 'Physical cash'],
                                ['check',         'fas fa-file-alt',    'Check Deposit',      'bg-secondary','Check received'],
                            ];
                            foreach($methods as [$val,$ic,$label,$bg,$desc]):
                            ?>
                            <div class="col-md-6 mb-2">
                                <label class="dep-method-option w-100" id="method_<?php echo $val; ?>"
                                       onclick="selectMethod('<?php echo $val; ?>')">
                                    <input type="radio" name="method" value="<?php echo $val; ?>"
                                           <?php echo $val==='admin_credit'?'checked':''; ?>
                                           style="accent-color:#28a745;">
                                    <i class="<?php echo $ic; ?> text-success fa-fw"></i>
                                    <div>
                                        <div style="font-size:13px;font-weight:600;color:#212529;"><?php echo $label; ?></div>
                                        <div style="font-size:11px;color:#6c757d;"><?php echo $desc; ?></div>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ======= STEP 4: NOTE ======= -->
                    <div class="form-group mb-4">
                        <label class="font-weight-bold">
                            Internal Note
                            <small class="text-muted font-weight-normal">(Optional)</small>
                        </label>
                        <input type="text" name="note" class="form-control"
                               placeholder="e.g. Account opening balance, Wire received from Chase Bank..."
                               maxlength="200">
                    </div>

                    <!-- DEPOSIT SUMMARY -->
                    <div id="depositSummary" style="display:none;background:#f0fff4;border:1.5px solid #86efac;border-radius:10px;padding:16px 20px;margin-bottom:20px;">
                        <div style="font-size:13px;font-weight:700;color:#059669;margin-bottom:10px;">
                            <i class="fas fa-check-circle mr-1"></i> Deposit Summary
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div style="font-size:13px;color:#6c757d;">Crediting to:</div>
                                <div style="font-size:15px;font-weight:700;" id="summaryName">—</div>
                                <div style="font-size:12px;color:#6c757d;" id="summaryAccount">—</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:11px;color:#6c757d;text-transform:uppercase;">Amount</div>
                                <div style="font-size:28px;font-weight:800;color:#059669;" id="summaryAmount">$0.00</div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <button type="submit" id="submitBtn" class="btn btn-success btn-block btn-lg" disabled
                            style="height:50px;font-size:15px;font-weight:600;border-radius:8px;">
                        <i class="fas fa-check-circle mr-2"></i>
                        Confirm & Process Deposit
                    </button>
                    <p class="text-center text-muted mt-2" style="font-size:12px;">
                        <i class="fas fa-lock mr-1"></i>
                        This will immediately update the customer's account balance.
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== RECENT DEPOSITS ===== -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-history mr-2"></i> Recent Deposits</h4>
            </div>
            <div class="card-body p-0">
                <?php if(empty($recent_deposits)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                        No deposits yet.
                    </div>
                <?php else: ?>
                    <div style="padding:0 16px;">
                        <?php foreach($recent_deposits as $d): ?>
                        <div class="dep-recent-item">
                            <div style="width:36px;height:36px;background:#d1fae5;color:#059669;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?php echo htmlspecialchars($d['first_name'].' '.$d['last_name']); ?>
                                </div>
                                <div style="font-size:11px;color:#adb5bd;">
                                    <?php echo htmlspecialchars($d['account_number']); ?> ·
                                    <?php echo ucfirst(str_replace('_',' ',$d['method']??'')); ?>
                                </div>
                                <div>
                                    <span style="font-size:10px;font-family:monospace;color:#e67e22;">
                                        <?php echo htmlspecialchars($d['reference']??''); ?>
                                    </span>
                                </div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="font-size:15px;font-weight:700;color:#28a745;">
                                    +$<?php echo number_format($d['amount']??0,2); ?>
                                </div>
                                <div style="font-size:11px;color:#adb5bd;">
                                    <?php echo date('d M Y',strtotime($d['created_at'])); ?>
                                </div>
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
// ============================================================
// ACCOUNT LOOKUP
// ============================================================
let selectedUser = null;

// Allow Enter key in search field
document.getElementById('accSearch').addEventListener('keydown', function(e) {
    if(e.key === 'Enter') {
        e.preventDefault();
        lookupAccount();
    }
});

function lookupAccount() {
    const acc = document.getElementById('accSearch').value.trim();
    if(!acc) {
        showError('Please enter an account number.');
        return;
    }

    const preview  = document.getElementById('accountPreview');
    const searchBtn = document.getElementById('searchBtn');

    // Loading state
    preview.style.display = 'block';
    preview.innerHTML = `
        <div style="background:#f0f4ff;border-radius:12px;padding:20px;text-align:center;border:2px dashed #93c5fd;">
            <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2 d-block"></i>
            <span class="text-muted">Looking up account...</span>
        </div>
    `;
    searchBtn.disabled = true;
    selectedUser = null;
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('targetUserId').value = '';

    fetch('deposit.php?ajax_lookup=1&acc=' + encodeURIComponent(acc))
        .then(function(response) {
            if(!response.ok) throw new Error('Network error: ' + response.status);
            return response.json();
        })
        .then(function(data) {
            searchBtn.disabled = false;

            if(data.error) {
                preview.innerHTML = `
                    <div style="background:#fff5f5;border:1.5px solid #fca5a5;border-radius:10px;padding:14px 16px;display:flex;align-items:center;gap:10px;">
                        <i class="fas fa-exclamation-circle text-danger fa-lg"></i>
                        <span style="font-size:14px;color:#dc2626;font-weight:500;">${data.error}</span>
                    </div>
                `;
                return;
            }

            selectedUser = data;
            document.getElementById('targetUserId').value = data.id;

            const initials = data.first_name.charAt(0).toUpperCase() + data.last_name.charAt(0).toUpperCase();
            const statusColor = data.status === 'active' ? '#28a745' : (data.status === 'pending_kyc' ? '#f59e0b' : '#dc3545');
            const statusLabel = data.status === 'pending_kyc' ? 'Pending KYC' : data.status.charAt(0).toUpperCase() + data.status.slice(1);
            const borderColor = data.status === 'active' ? '#28a745' : '#f59e0b';

            preview.innerHTML = `
                <div style="border-radius:12px;padding:18px 20px;display:flex;align-items:center;gap:16px;border:2px solid ${borderColor};background:${data.status==='active'?'#f0fff4':'#fffbeb'};">
                    <div style="width:52px;height:52px;border-radius:13px;background:linear-gradient(135deg,#28a745,#20c997);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0;">
                        ${initials}
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:16px;font-weight:700;color:#212529;">${data.first_name} ${data.last_name}</div>
                        <div style="font-size:12px;color:#6c757d;margin-top:2px;">${data.email}</div>
                        <div style="margin-top:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <code style="font-size:12px;">${data.account_number}</code>
                            <span style="background:${statusColor};color:#fff;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;">${statusLabel}</span>
                            <span style="background:#e9ecef;color:#495057;padding:2px 10px;border-radius:20px;font-size:11px;">${data.account_type || 'Checking'}</span>
                        </div>
                    </div>
                    <div style="text-align:right;flex-shrink:0;">
                        <div style="font-size:11px;color:#6c757d;text-transform:uppercase;letter-spacing:.5px;">Balance</div>
                        <div style="font-size:24px;font-weight:800;color:#28a745;">$${parseFloat(data.balance).toFixed(2)}</div>
                        <div style="font-size:10px;color:#adb5bd;">${data.currency || 'USD'}</div>
                    </div>
                </div>
                ${data.status === 'suspended' ? '<div class="alert alert-warning mt-2 py-2 mb-0"><i class="fas fa-exclamation-triangle mr-1"></i>This account is suspended. Deposits may be restricted.</div>' : ''}
            `;

            if(data.status !== 'closed') {
                document.getElementById('submitBtn').disabled = false;
            }
            updatePreview();
        })
        .catch(function(err) {
            searchBtn.disabled = false;
            preview.innerHTML = `
                <div style="background:#fff5f5;border:1.5px solid #fca5a5;border-radius:10px;padding:14px 16px;">
                    <i class="fas fa-exclamation-circle text-danger mr-2"></i>
                    <span style="color:#dc2626;">Connection error. Please try again.</span>
                </div>
            `;
            console.error('AJAX error:', err);
        });
}

function setAmount(val) {
    document.getElementById('amountInput').value = val;
    updatePreview();
}

function selectMethod(val) {
    document.querySelectorAll('.dep-method-option').forEach(el => el.classList.remove('selected'));
    const el = document.getElementById('method_' + val);
    if(el) el.classList.add('selected');
    document.querySelector('[name="method"][value="' + val + '"]').checked = true;
}

function updatePreview() {
    const amt  = parseFloat(document.getElementById('amountInput').value) || 0;
    const summary = document.getElementById('depositSummary');

    if(amt > 0 && selectedUser) {
        document.getElementById('summaryName').textContent    = selectedUser.first_name + ' ' + selectedUser.last_name;
        document.getElementById('summaryAccount').textContent = selectedUser.account_number;
        document.getElementById('summaryAmount').textContent  = '$' + amt.toFixed(2);
        summary.style.display = 'block';
        document.getElementById('submitBtn').disabled = false;
    } else {
        summary.style.display = 'none';
        if(!selectedUser) document.getElementById('submitBtn').disabled = true;
    }
}

function showError(msg) {
    document.getElementById('accountPreview').style.display = 'block';
    document.getElementById('accountPreview').innerHTML = `
        <div style="background:#fff5f5;border:1.5px solid #fca5a5;border-radius:10px;padding:12px 16px;">
            <i class="fas fa-exclamation-circle text-danger mr-2"></i>
            <span style="color:#dc2626;">${msg}</span>
        </div>
    `;
}

// Prevent double submit
document.getElementById('depositForm').addEventListener('submit', function(e) {
    const uid = document.getElementById('targetUserId').value;
    const amt = parseFloat(document.getElementById('amountInput').value) || 0;

    if(!uid) {
        e.preventDefault();
        showError('Please search and select a customer account first.');
        return;
    }
    if(amt <= 0) {
        e.preventDefault();
        showError('Please enter a valid deposit amount greater than $0.');
        return;
    }

    const btn = document.getElementById('submitBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing deposit...';
});

// Initialize default method styling
selectMethod('admin_credit');
</script>

<?php include 'layout/footer.php'; ?>