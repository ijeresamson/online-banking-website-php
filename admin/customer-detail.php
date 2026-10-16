<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}


$id = (int)($_GET['id'] ?? 0);
if($id <= 0) { header("Location: customer-all"); 
exit; }

// ============================================================
// HANDLE QUICK ACTIONS
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_action'])) {
    $action = $_POST['quick_action'];
    try {
        $map = [
            'activate'  => 'active',
            'suspend'   => 'suspended',
            'close'     => 'closed',
            'kyc_approve' => 'active',
            'kyc_reject'  => 'suspended',
        ];
        if(!isset($map[$action])) throw new Exception("Invalid action.");
        $pdo->prepare("UPDATE users SET status=? WHERE id=?")->execute([$map[$action], $id]);
        $_SESSION['success_message'] = "Account status updated to: ".ucfirst($map[$action]).".";
    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: customer-detail-{$id}"); 
    exit;
}

// ============================================================
// FETCH CUSTOMER DATA
// ============================================================
$stmt = $pdo->prepare("SELECT u.*, COALESCE(a.balance,0) AS balance,
           a.account_name, a.account_type, a.currency,
           a.routing_number, a.wire_routing, a.swift_code,
           a.bank_name, a.bank_address, a.bank_country
        FROM users u
        LEFT JOIN accounts a ON u.id = a.user_id
        WHERE u.id = ? LIMIT 1
");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: customer-all"); exit;
}

// Recent transactions
$txns = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 15");
$txns->execute([$id]);
$transactions = $txns->fetchAll(PDO::FETCH_ASSOC);

// Transfers
$trnsfrs = $pdo->prepare("SELECT t.*, 
           su.first_name AS sender_fn, su.last_name AS sender_ln,
           ru.first_name AS recip_fn,  ru.last_name AS recip_ln
    FROM transfers t
    LEFT JOIN users su ON t.sender_id    = su.id
    LEFT JOIN users ru ON t.recipient_id = ru.id
    WHERE t.sender_id=? OR t.recipient_id=?
    ORDER BY t.created_at DESC LIMIT 10
");
$trnsfrs->execute([$id,$id]);
$transfers = $trnsfrs->fetchAll(PDO::FETCH_ASSOC);

// Cards
$cards_stmt = $pdo->prepare("SELECT * FROM cards WHERE user_id=? ORDER BY created_at DESC");
$cards_stmt->execute([$id]);
$cards = $cards_stmt->fetchAll(PDO::FETCH_ASSOC);

// Support tickets
$tickets_stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$tickets_stmt->execute([$id]);
$tickets = $tickets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Login attempts
$logins = [];
try {
    $ls = $pdo->prepare("SELECT * FROM login_attempts WHERE email=? ORDER BY attempted_at DESC LIMIT 8");
    $ls->execute([$customer['email']]);
    $logins = $ls->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

$initials = strtoupper(substr($customer['first_name'],0,1).substr($customer['last_name'],0,1));
$id_labels = ['passport'=>'International Passport','national_id'=>'National ID Card','drivers_license'=>"Driver's License"];

$sbadge_color = match($customer['status']??''){
    'active'=>'success','pending_kyc'=>'warning','suspended'=>'danger','closed'=>'secondary',default=>'light'
};
$slabel = match($customer['status']??''){
    'pending_kyc'=>'Pending KYC',default=>ucfirst($customer['status']??'')
};
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="<?php echo ADMIN_URL;  ?>customer-all" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="mb-0">
                <?php echo htmlspecialchars($customer['first_name'].' '.$customer['last_name']); ?>
            </h1>
            <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
        </div>
        <span class="badge badge-<?php echo $sbadge_color; ?> badge-pill" style="font-size:13px;padding:6px 14px;">
            <?php echo $slabel; ?>
        </span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="deposit?user_id=<?php echo $id; ?>" class="btn btn-success btn-sm">
            <i class="fas fa-plus-circle mr-1"></i> Deposit
        </a>
        <a href="withdrawal?user_id=<?php echo $id; ?>" class="btn btn-danger btn-sm">
            <i class="fas fa-minus-circle mr-1"></i> Withdraw
        </a>
        <a  href="edit-customer-<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-exchange-alt mr-1"></i> Edit customer
        </a>
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

<div class="row">

    <!-- ===== LEFT COLUMN ===== -->
    <div class="col-lg-4">

        <!-- Profile Card -->
        <div class="card">
            <div class="card-body text-center py-4" style="background:linear-gradient(135deg,#1e3a5f,#2563eb);border-radius:10px 10px 0 0;">
                <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center mx-auto mb-3"
                     style="width:72px;height:72px;font-size:26px;font-weight:700;">
                    <?php echo $initials; ?>
                </div>
                <h4 class="text-white mb-1"><?php echo htmlspecialchars($customer['first_name'].' '.$customer['last_name']); ?></h4>
                <p class="text-white-50 mb-2" style="font-size:13px;"><?php echo htmlspecialchars($customer['email']); ?></p>
                <span class="badge badge-<?php echo $sbadge_color; ?> badge-pill px-3 py-2"><?php echo $slabel; ?></span>
            </div>
            <div class="card-body p-0">
                <?php
                $rows = [
                    ['fas fa-phone',     'Phone',        $customer['phone']??'—'],
                    ['fas fa-globe',     'Nationality',  $customer['nationality']??'—'],
                    ['fas fa-birthday-cake','Date of Birth', !empty($customer['date_of_birth'])?date('d M Y',strtotime($customer['date_of_birth'])):'—'],
                    ['fas fa-briefcase', 'Employment',   ucfirst(str_replace('_',' ',$customer['employment_status']??''))],
                    ['fas fa-wallet',    'Source of Funds', ucfirst(str_replace('_',' ',$customer['source_of_funds']??''))],
                    ['fas fa-calendar',  'Registered',   date('d M Y',strtotime($customer['created_at']))],
                    ['fas fa-clock',     'Last Login',   !empty($customer['last_login'])?date('d M Y H:i',strtotime($customer['last_login'])):'Never'],
                ];
                foreach($rows as [$ic,$lbl,$val]):
                ?>
                <div class="d-flex align-items-center px-3 py-2 border-bottom">
                    <i class="<?php echo $ic; ?> text-muted mr-3 fa-fw"></i>
                    <div style="flex:1;">
                        <small class="text-muted d-block"><?php echo $lbl; ?></small>
                        <span style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($val); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Account Balance -->
        <div class="card" style="background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff;">
            <div class="card-body">
                <div style="font-size:11px;opacity:.6;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">
                    Account Balance
                </div>
                <div style="font-size:32px;font-weight:700;margin-bottom:10px;">
                    $<?php echo number_format($customer['balance']??0,2); ?>
                </div>
                <hr style="border-color:rgba(255,255,255,.15);">
                <?php
                $acct_rows = [
                    ['Account No.',  $customer['account_number']],
                    ['Account Name', $customer['account_name']??strtoupper($customer['first_name'].' '.$customer['last_name'])],
                    ['Type',         ($customer['account_type']??'Checking').' Account'],
                    ['Routing',      $customer['routing_number']??'—'],
                    ['Wire Routing', $customer['wire_routing']??'—'],
                    ['SWIFT',        $customer['swift_code']??BANK_SWIFT],
                    ['Currency',     $customer['currency']??'USD'],
                ];
                foreach($acct_rows as [$l,$v]):
                ?>
                <div class="d-flex justify-content-between py-1" style="font-size:12px;">
                    <span style="opacity:.6;"><?php echo $l; ?></span>
                    <span style="font-weight:600;font-family:monospace;"><?php echo htmlspecialchars($v); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- KYC Documents -->
        <div class="card">
            <div class="card-header"><h4><i class="fas fa-id-card mr-2"></i> KYC Documents</h4></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-id-badge text-primary mr-2"></i>
                        <strong style="font-size:13px;"><?php echo $id_labels[$customer['id_type']??''] ?? 
                        htmlspecialchars($customer['id_type']??'—'); ?></strong>
                    </div>
                    <?php if(!empty($customer['id_document'])): ?>
                       <a href="kyc-view-document?type=id&user_id=<?php echo (int)$id; ?>"
                        class="btn btn-primary btn-sm"
                        target="_blank"
                        rel="noopener noreferrer">
                            <i class="fas fa-eye mr-1"></i> View ID Document
                        </a>
                    <?php else: ?>
                        <span class="badge badge-warning">Not Uploaded</span>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-home text-info mr-2"></i>
                        <strong style="font-size:13px;">Proof of Address</strong>
                    </div>
                    <?php if(!empty($customer['address_document'])): ?>
                         <a href="kyc-view-document?type=address&user_id=<?php echo (int)$id; ?>"
                         class="btn btn-info btn-sm"
                          class="btn btn-info btn-sm" target="_blank">
                            <i class="fas fa-eye mr-1"></i> View Address Doc
                        </a>
                    <?php else: ?>
                        <span class="badge badge-warning">Not Uploaded</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header"><h4><i class="fas fa-bolt mr-2"></i> Quick Actions</h4></div>
            <div class="card-body">
                <?php if($customer['status'] === 'pending_kyc'): ?>
                <form method="POST" class="mb-2" onsubmit="return confirm('Approve KYC and activate this account?')">
                    <input type="hidden" name="quick_action" value="kyc_approve">
                    <button class="btn btn-success btn-block">
                        <i class="fas fa-check-circle mr-2"></i> Approve KYC
                    </button>
                </form>
                <form method="POST" class="mb-2" onsubmit="return confirm('Reject KYC and suspend this account?')">
                    <input type="hidden" name="quick_action" value="kyc_reject">
                    <button class="btn btn-danger btn-block">
                        <i class="fas fa-times-circle mr-2"></i> Reject KYC
                    </button>
                </form>
                <?php elseif($customer['status'] === 'active'): ?>
                <form method="POST" class="mb-2" onsubmit="return confirm('Suspend this account?')">
                    <input type="hidden" name="quick_action" value="suspend">
                    <button class="btn btn-warning btn-block">
                        <i class="fas fa-ban mr-2"></i> Suspend Account
                    </button>
                </form>
                <?php elseif($customer['status'] === 'suspended'): ?>
                <form method="POST" class="mb-2" onsubmit="return confirm('Reactivate this account?')">
                    <input type="hidden" name="quick_action" value="activate">
                    <button class="btn btn-success btn-block">
                        <i class="fas fa-check mr-2"></i> Reactivate Account
                    </button>
                </form>
                <form method="POST" class="mb-2" onsubmit="return confirm('PERMANENTLY close this account?')">
                    <input type="hidden" name="quick_action" value="close">
                    <button class="btn btn-dark btn-block">
                        <i class="fas fa-times-circle mr-2"></i> Close Account
                    </button>
                </form>
                <?php endif; ?>

                <hr>
                <a href="deposit?user_id=<?php echo $id; ?>"    class="btn btn-success btn-block mb-2">
                    <i class="fas fa-plus-circle mr-2"></i> Make Deposit
                </a>
                <a href="withdrawal?user_id=<?php echo $id; ?>"  class="btn btn-danger btn-block mb-2">
                    <i class="fas fa-minus-circle mr-2"></i> Process Withdrawal
                </a>
                <a href="admin-transfer?user_id=<?php echo $id; ?>" class="btn btn-primary btn-block">
                    <i class="fas fa-exchange-alt mr-2"></i> Admin Transfer
                </a>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT COLUMN ===== -->
    <div class="col-lg-8">

        <!-- Transactions -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-history mr-2"></i> Transaction History</h4>
                <span class="badge badge-secondary"><?php echo count($transactions); ?> recent</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Reference</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Bal. After</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($transactions)): ?>
                                <tr><td colspan="7" class="text-center py-3 text-muted">No transactions found.</td></tr>
                            <?php else: foreach($transactions as $t):
                                $is_cr  = ($t['type']==='deposit');
                                $tbadge = match($t['type']??''){'deposit'=>'success','withdrawal'=>'danger','transfer'=>'primary',default=>'secondary'};
                                $sbadge = match($t['status']??''){'completed'=>'success','pending'=>'warning','failed'=>'danger',default=>'secondary'};
                            ?>
                            <tr>
                                <td><code style="font-size:10px;"><?php echo htmlspecialchars(substr($t['reference']??'—',0,16)); ?></code></td>
                                <td><span class="badge badge-<?php echo $tbadge; ?>" style="font-size:10px;"><?php echo ucfirst($t['type']??''); ?></span></td>
                                <td class="font-weight-bold <?php echo $is_cr?'text-success':'text-danger'; ?>">
                                    <?php echo $is_cr?'+':'-'; ?>$<?php echo number_format($t['amount']??0,2); ?>
                                </td>
                                <td class="text-muted small">$<?php echo number_format($t['balance_after']??0,2); ?></td>
                                <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <small><?php echo htmlspecialchars($t['description']??'—'); ?></small>
                                </td>
                                <td><span class="badge badge-<?php echo $sbadge; ?>" style="font-size:10px;"><?php echo ucfirst($t['status']??''); ?></span></td>
                                <td><small class="text-muted"><?php echo date('d M Y',strtotime($t['created_at'])); ?></small></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transfers -->
        <div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-exchange-alt mr-2"></i> Transfer History</h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Reference</th>
                                <th>Direction</th>
                                <th>Counterparty</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($transfers)): ?>
                                <tr><td colspan="7" class="text-center py-3 text-muted">No transfers found.</td></tr>
                            <?php else: foreach($transfers as $t):
                                $is_sender  = ((int)$t['sender_id'] === $id);
                                $sbadge     = match($t['status']??''){'completed'=>'success','pending'=>'warning','failed'=>'danger','reversed'=>'secondary',default=>'light'};
                                if($is_sender) {
                                    $counterparty = $t['transfer_type']==='external' ? htmlspecialchars($t['recipient_name_ext']??'External') : htmlspecialchars(($t['recip_fn']??'').' '.($t['recip_ln']??''));
                                    $dir = '<span class="badge badge-danger">Sent</span>';
                                } else {
                                    $counterparty = htmlspecialchars(($t['sender_fn']??'').' '.($t['sender_ln']??''));
                                    $dir = '<span class="badge badge-success">Received</span>';
                                }
                            ?>
                            <tr>
                                <td><code style="font-size:10px;"><?php echo htmlspecialchars(substr($t['reference']??'—',0,14)); ?></code></td>
                                <td><?php echo $dir; ?></td>
                                <td style="font-size:12px;"><?php echo $counterparty; ?></td>
                                <td class="font-weight-bold"><?php echo $is_sender?'- ':'+'; ?>$<?php echo number_format($t['amount']??0,2); ?></td>
                                <td><span class="badge badge-secondary" style="font-size:10px;"><?php echo ucfirst($t['transfer_type']??''); ?></span></td>
                                <td><span class="badge badge-<?php echo $sbadge; ?>" style="font-size:10px;"><?php echo ucfirst($t['status']??''); ?></span></td>
                                <td><small class="text-muted"><?php echo date('d M Y',strtotime($t['created_at'])); ?></small></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Cards -->
        <div class="card mb-3">
            <div class="card-header"><h4 class="mb-0"><i class="fas fa-credit-card mr-2"></i> Cards</h4></div>
            <div class="card-body">
                <?php if(empty($cards)): ?>
                    <p class="text-muted text-center py-2">No cards issued.</p>
                <?php else: foreach($cards as $c):
                    $c_exp = strtotime($c['expiry_date']) < time();
                    $c_stat = $c_exp ? 'expired' : $c['status'];
                    $cbadge = match($c_stat){'active'=>'success','frozen'=>'info','blocked'=>'danger','expired'=>'secondary',default=>'light'};
                ?>
                <div class="d-flex align-items-center p-2 mb-2 border rounded">
                    <div class="mr-3" style="width:52px;height:32px;background:linear-gradient(135deg,#1a1a2e,#0f3460);border-radius:5px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;">
                        <?php echo strtoupper($c['network'])==='VISA'?'VISA':'MC'; ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;">
                            <?php echo strtoupper($c['card_type']); ?> •••• <?php echo substr($c['card_number'],-4); ?>
                        </div>
                        <small class="text-muted">Exp: <?php echo date('m/Y',strtotime($c['expiry_date'])); ?> · Limit: $<?php echo number_format($c['daily_limit'],0); ?>/day</small>
                    </div>
                    <span class="badge badge-<?php echo $cbadge; ?>"><?php echo ucfirst($c_stat); ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Support Tickets -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-headset mr-2"></i> Support Tickets</h4>
                <a href="support-all?user_id=<?php echo $id; ?>" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if(empty($tickets)): ?>
                    <p class="text-muted text-center py-3">No tickets submitted.</p>
                <?php else: foreach($tickets as $tk):
                    $tbadge = match($tk['status']??''){'open'=>'primary','in_progress'=>'warning','resolved'=>'success',default=>'secondary'};
                ?>
                <div class="d-flex align-items-start px-3 py-2 border-bottom">
                    <div style="flex:1;">
                        <div style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($tk['subject']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($tk['ticket_no']); ?> · <?php echo date('d M Y',strtotime($tk['created_at'])); ?></small>
                    </div>
                    <div class="ml-2">
                        <span class="badge badge-<?php echo $tbadge; ?>"><?php echo ucfirst($tk['status']??''); ?></span>
                        <span class="badge badge-light ml-1"><?php echo ucfirst($tk['priority']??''); ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Login History -->
        <?php if(!empty($logins)): ?>
        <div class="card">
            <div class="card-header"><h4 class="mb-0"><i class="fas fa-sign-in-alt mr-2"></i> Login History</h4></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logins as $lg): ?>
                            <tr>
                                <td><small><?php echo date('d M Y H:i',strtotime($lg['attempted_at']??$lg['created_at']??'now')); ?></small></td>
                                <td><code style="font-size:11px;"><?php echo htmlspecialchars($lg['ip_address']??'—'); ?></code></td>
                                <td>
                                    <?php if(($lg['success']??$lg['status']??1)): ?>
                                        <span class="badge badge-success">Success</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

</section>
</div>
<?php include 'layout/footer.php'; ?>