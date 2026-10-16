<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php

// ============================================================
// HANDLE — ADMIN REPLY / STATUS UPDATE
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_reply'])) {
    $ticket_id  = (int)$_POST['ticket_id'];
    $reply      = trim($_POST['admin_reply']    ?? '');
    $new_status = trim($_POST['ticket_status']  ?? 'in_progress');

    try {
        if(empty($reply))   throw new Exception("Reply message cannot be empty.");
        if($ticket_id <= 0) throw new Exception("Invalid ticket.");
        if(!in_array($new_status, ['open','in_progress','resolved','closed']))
            throw new Exception("Invalid status value.");

        $pdo->prepare("
            UPDATE support_tickets
            SET admin_reply       = ?,
                status            = ?,
                replied_at        = NOW(),
                user_seen_reply   = 0,
                updated_at        = NOW()
            WHERE id = ?
        ")->execute([$reply, $new_status, $ticket_id]);

        $_SESSION['success_message'] = "Reply sent! Customer notified. Status: <strong>".ucfirst(str_replace('_',' ',$new_status))."</strong>.";

    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header("Location: support-all.php?view={$ticket_id}"); exit;
}

// ============================================================
// HANDLE — QUICK STATUS CHANGE
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_status'])) {
    $ticket_id  = (int)$_POST['ticket_id'];
    $new_status = trim($_POST['new_status'] ?? '');
    if($ticket_id > 0 && in_array($new_status, ['open','in_progress','resolved','closed'])) {
        $pdo->prepare("
            UPDATE support_tickets
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$new_status, $ticket_id]);
        $_SESSION['success_message'] = "Status updated to: <strong>".ucfirst(str_replace('_',' ',$new_status))."</strong>.";
    }
    header("Location: support-all.php?view={$_POST['ticket_id']}"); exit;
}

// ============================================================
// MARK ADMIN_SEEN_REPLY = 1 when viewing a ticket
// ============================================================
$view_id = (int)($_GET['view'] ?? 0);
if($view_id > 0) {
    $pdo->prepare("UPDATE support_tickets SET admin_seen_reply=1 WHERE id=?")->execute([$view_id]);
}

// ============================================================
// FILTERS
// ============================================================
$status_f   = $_GET['status']   ?? 'all';
$priority_f = $_GET['priority'] ?? 'all';
$search     = trim($_GET['q']   ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$limit      = 15;
$offset     = ($page-1)*$limit;

// Stats (always unfiltered)
$stats = $pdo->query("
    SELECT
        COUNT(*)                                                                         AS total,
        SUM(status='open')                                                               AS open_cnt,
        SUM(status='in_progress')                                                        AS inprog_cnt,
        SUM(status='resolved')                                                           AS resolved_cnt,
        SUM(status='closed')                                                             AS closed_cnt,
        SUM(priority='high' AND status IN('open','in_progress'))                         AS urgent_cnt,
        SUM(admin_reply IS NULL AND status IN('open','in_progress'))                     AS needs_reply_cnt,
        SUM(customer_reply IS NOT NULL AND (admin_seen_reply=0 OR admin_seen_reply IS NULL)) AS cust_unread_cnt
    FROM support_tickets
")->fetch(PDO::FETCH_ASSOC);

// Build WHERE
$where  = ['1=1'];
$params = [];

if($status_f !== 'all')   { $where[] = 'st.status=?';   $params[] = $status_f; }
if($priority_f !== 'all') { $where[] = 'st.priority=?';  $params[] = $priority_f; }
if($search !== '') {
    $where[] = "(st.ticket_no LIKE ? OR st.subject LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params, [$sq,$sq,$sq,$sq,$sq]);
}
$where_sql = implode(' AND ', $where);

$cnt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets st JOIN users u ON st.user_id=u.id WHERE {$where_sql}");
$cnt->execute($params);
$total_rows  = $cnt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $limit));

// Fetch tickets — priority sorted
$stmt = $pdo->prepare("
    SELECT st.*, u.first_name, u.last_name, u.email, u.account_number,
           u.status AS user_status, COALESCE(a.balance,0) AS balance
    FROM support_tickets st
    JOIN users u ON st.user_id = u.id
    LEFT JOIN accounts a ON u.id = a.user_id
    WHERE {$where_sql}
    ORDER BY
        CASE WHEN (st.customer_reply IS NOT NULL AND (st.admin_seen_reply=0 OR st.admin_seen_reply IS NULL)) THEN 0 ELSE 1 END,
        CASE st.priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        CASE st.status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END,
        st.updated_at DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch viewed ticket
$view_ticket = null;
if($view_id > 0) {
    $vt = $pdo->prepare("
        SELECT st.*, u.first_name, u.last_name, u.email, u.phone,
               u.account_number, u.status AS user_status,
               COALESCE(a.balance,0) AS balance
        FROM support_tickets st
        JOIN users u ON st.user_id=u.id
        LEFT JOIN accounts a ON u.id=a.user_id
        WHERE st.id=? LIMIT 1
    ");
    $vt->execute([$view_id]);
    $view_ticket = $vt->fetch(PDO::FETCH_ASSOC);
}

function spUrl($o=[]) {
    return '?'.http_build_query(array_merge(array_filter($_GET, fn($v)=>$v!==''), $o));
}

$templates = [
    'Under Review'    => "Dear {name},\n\nThank you for reaching out to MidwesternBank Support.\n\nWe have received your request and our compliance team is currently reviewing it. We will update you within 24–48 business hours.\n\nKind regards,\nMidwesternBank Support Team\nsupport@midwesternbank.com",
    'KYC Approved'    => "Dear {name},\n\nGreat news! Your KYC verification has been completed and your account is now fully active.\n\nYou now have access to all banking features including wire transfers, card requests, and more.\n\nKind regards,\nMidwesternBank Support Team",
    'Docs Required'   => "Dear {name},\n\nThank you for your submission.\n\nTo proceed, we require the following additional documentation:\n\n1. [Specify required document]\n2. [Specify required document]\n\nPlease reply to this ticket or email us at support@midwesternbank.com.\n\nKind regards,\nMidwesternBank Support Team",
    'Issue Resolved'  => "Dear {name},\n\nWe are pleased to inform you that your issue has been fully resolved.\n\n[Describe the resolution here]\n\nIf you experience any further issues, please do not hesitate to contact us.\n\nKind regards,\nMidwesternBank Support Team",
    'No Action Needed'=> "Dear {name},\n\nThank you for contacting MidwesternBank.\n\nAfter reviewing your account, no further action is required at this time. [Provide brief explanation]\n\nIf you have additional questions, we are always happy to help.\n\nKind regards,\nMidwesternBank Support Team",
    'Fraud Alert'     => "Dear {name},\n\nWe have received your report regarding potential unauthorized activity.\n\nOur fraud prevention team is investigating this immediately. For urgent concerns, please also email fraud@midwesternbank.com.\n\nYour account security is our absolute highest priority.\n\nKind regards,\nMidwesternBank Fraud Prevention Team",
];
?>

<div class="main-content">
<section class="section">

<!-- ── PAGE HEADER ── -->
<div class="section-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <h1><i class="fas fa-headset mr-2"></i> Support Center</h1>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if($stats['urgent_cnt'] > 0): ?>
            <span class="badge badge-danger badge-pill px-3 py-2" style="font-size:12px;">
                <i class="fas fa-exclamation-circle mr-1"></i><?php echo $stats['urgent_cnt']; ?> Urgent
            </span>
        <?php endif; ?>
        <?php if($stats['cust_unread_cnt'] > 0): ?>
            <span class="badge badge-primary badge-pill px-3 py-2" style="font-size:12px;">
                <i class="fas fa-reply mr-1"></i><?php echo $stats['cust_unread_cnt']; ?> Customer Replied
            </span>
        <?php endif; ?>
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

<!-- ── STAT CARDS ── -->
<div class="row mb-4">
    <?php
    foreach([
        ['Total',           $stats['total'],           'bg-primary',   'fas fa-ticket-alt'],
        ['Open',            $stats['open_cnt'],        'bg-danger',    'fas fa-folder-open'],
        ['In Progress',     $stats['inprog_cnt'],      'bg-warning',   'fas fa-spinner'],
        ['Needs Reply',     $stats['needs_reply_cnt'], 'bg-info',      'fas fa-reply'],
        ['Resolved',        $stats['resolved_cnt'],    'bg-success',   'fas fa-check-double'],
        ['Cust. Replied',   $stats['cust_unread_cnt'], 'bg-primary',   'fas fa-comments'],
    ] as [$l,$v,$bg,$ic]):
    ?>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card card-statistic-1 h-100">
            <div class="card-icon <?php echo $bg; ?>" style="width:36px;height:36px;font-size:14px;">
                <i class="<?php echo $ic; ?>"></i>
            </div>
            <div class="card-wrap">
                <div class="card-header"><h4 style="font-size:11px;"><?php echo $l; ?></h4></div>
                <div class="card-body" style="font-size:20px;"><?php echo number_format($v); ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ============================== TICKET DETAIL VIEW ============================== -->
<?php if($view_ticket): ?>
<div class="card mb-4" style="border:2px solid #2563eb;border-radius:12px;overflow:hidden;">

    <!-- Header -->
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap"
         style="background:linear-gradient(135deg,#0c2340,#1a4b8c);padding:16px 22px;gap:10px;">
        <div>
            <div style="font-size:10px;font-family:monospace;color:rgba(255,255,255,.5);margin-bottom:4px;">
                <?php echo htmlspecialchars($view_ticket['ticket_no']); ?>
            </div>
            <h4 class="mb-0 text-white" style="font-size:17px;">
                <?php echo htmlspecialchars($view_ticket['subject']); ?>
            </h4>
        </div>
        <a href="support-all.php<?php echo $status_f !== 'all' ? '?status='.urlencode($status_f) : ''; ?>"
           class="btn btn-light btn-sm">
            <i class="fas fa-times mr-1"></i> Close
        </a>
    </div>

    <div class="card-body" style="padding:22px;">
        <div class="row">

            <!-- LEFT: Conversation + Reply Form -->
            <div class="col-lg-8">

                <!-- Customer Info Bar -->
                <div class="d-flex align-items-center mb-4 p-3 rounded"
                     style="background:#f8f9fa;border:1px solid #e2e8f0;">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                         style="width:48px;height:48px;font-size:16px;font-weight:700;">
                        <?php echo strtoupper(substr($view_ticket['first_name'],0,1).substr($view_ticket['last_name'],0,1)); ?>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:15px;font-weight:700;color:#0c2340;">
                            <?php echo htmlspecialchars($view_ticket['first_name'].' '.$view_ticket['last_name']); ?>
                        </div>
                        <div style="font-size:12px;color:#64748b;"><?php echo htmlspecialchars($view_ticket['email']); ?></div>
                        <div class="mt-1">
                            <code style="font-size:11px;"><?php echo htmlspecialchars($view_ticket['account_number']); ?></code>
                            <span class="badge badge-success ml-2">$<?php echo number_format($view_ticket['balance'],2); ?></span>
                        </div>
                    </div>
                    <div class="text-right ml-3">
                        <?php
                        $sbadge = ['open'=>'primary','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'][$view_ticket['status'] ?? 'open'] ?? 'secondary';
                        $pbadge = ['high'=>'danger','medium'=>'warning','low'=>'success'][$view_ticket['priority'] ?? 'medium'] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?php echo $sbadge; ?> d-block mb-1">
                            <?php echo ucfirst(str_replace('_',' ',$view_ticket['status'] ?? '')); ?>
                        </span>
                        <span class="badge badge-<?php echo $pbadge; ?>">
                            <?php echo ucfirst($view_ticket['priority'] ?? ''); ?> Priority
                        </span>
                        <div class="mt-2">
                            <a href="customer-detail.php?id=<?php echo $view_ticket['user_id']; ?>"
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-user mr-1"></i> Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ══ CONVERSATION THREAD ══ -->
                <div style="display:flex;flex-direction:column;gap:14px;margin-bottom:22px;">

                    <!-- 1. Original customer message -->
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;margin-bottom:8px;">
                            Customer Message · <?php echo date('d M Y, H:i', strtotime($view_ticket['created_at'])); ?>
                        </div>
                        <div style="background:#f8fafc;border-radius:10px;padding:16px;border-left:4px solid #94a3b8;">
                            <div style="font-size:14px;line-height:1.75;color:#1e293b;white-space:pre-wrap;"><?php echo htmlspecialchars($view_ticket['message']); ?></div>
                        </div>
                    </div>

                    <!-- 2. Existing admin reply -->
                    <?php if(!empty($view_ticket['admin_reply'])): ?>
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#2563eb;margin-bottom:8px;">
                            Your Reply
                            <?php if(!empty($view_ticket['replied_at'])): ?>
                                · <?php echo date('d M Y, H:i', strtotime($view_ticket['replied_at'])); ?>
                            <?php endif; ?>
                        </div>
                        <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:10px;padding:16px;border-left:4px solid #2563eb;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <span style="background:#2563eb;color:#fff;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;">✓ Official Reply</span>
                            </div>
                            <div style="font-size:14px;line-height:1.75;color:#1e293b;white-space:pre-wrap;"><?php echo htmlspecialchars($view_ticket['admin_reply']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 3. Customer follow-up reply -->
                    <?php if(!empty($view_ticket['customer_reply'])): ?>
                    <div>
                        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#059669;margin-bottom:8px;display:flex;align-items:center;gap:8px;">
                            Customer Follow-up Reply
                            <?php if(!empty($view_ticket['customer_replied_at'])): ?>
                                · <?php echo date('d M Y, H:i', strtotime($view_ticket['customer_replied_at'])); ?>
                            <?php endif; ?>
                            <?php if(!($view_ticket['admin_seen_reply'] ?? 1)): ?>
                                <span class="badge badge-danger">NEW — Unread</span>
                            <?php endif; ?>
                        </div>
                        <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-radius:10px;padding:16px;border-left:4px solid #22c55e;">
                            <div style="font-size:14px;line-height:1.75;color:#1e293b;white-space:pre-wrap;"><?php echo htmlspecialchars($view_ticket['customer_reply']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <!-- END CONVERSATION THREAD -->

                <!-- ══ ADMIN REPLY FORM ══ -->
                <div style="background:#f8f9fa;border-radius:12px;padding:20px;border:1.5px solid #e2e8f0;">
                    <div style="font-size:14px;font-weight:700;color:#0c2340;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-paper-plane text-primary"></i>
                        <?php echo !empty($view_ticket['admin_reply']) ? 'Update Reply / Send Follow-up' : 'Write Your Reply'; ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="form_reply" value="1">
                        <input type="hidden" name="ticket_id"  value="<?php echo $view_ticket['id']; ?>">

                        <div class="form-group">
                            <textarea name="admin_reply" id="adminReplyTA" class="form-control" rows="8"
                                      style="resize:vertical;"
                                      placeholder="Write your response here. Be clear, professional, and helpful.&#10;&#10;Include:&#10;• Solution or next steps&#10;• Any actions taken on their account&#10;• Contact info for follow-up if needed"
                                      required><?php echo !empty($view_ticket['admin_reply']) ? htmlspecialchars($view_ticket['admin_reply']) : ''; ?></textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">Your reply is visible to the customer immediately after saving.</small>
                                <small class="text-muted" id="adminCC">0 chars</small>
                            </div>
                        </div>

                        <div class="form-row align-items-end">
                            <div class="col-md-5 mb-2">
                                <label class="small font-weight-bold">Set Ticket Status</label>
                                <select name="ticket_status" class="form-control form-control-sm">
                                    <?php
                                    $statuses = ['open'=>'🔵 Open','in_progress'=>'🟡 In Progress','resolved'=>'🟢 Resolved','closed'=>'⚫ Closed'];
                                    foreach($statuses as $v=>$l):
                                    ?>
                                    <option value="<?php echo $v; ?>" <?php echo ($view_ticket['status']??'open')===$v?'selected':''; ?>>
                                        <?php echo $l; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7 mb-2">
                                <button type="submit" class="btn btn-primary btn-block" style="height:38px;">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Send Reply to Customer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>

            <!-- RIGHT: Ticket Info + Templates + Quick Status -->
            <div class="col-lg-4">

                <!-- Ticket Details -->
                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="mb-0" style="font-size:13px;">Ticket Details</h6></div>
                    <div class="card-body p-0">
                        <?php
                        $tinfo = [
                            ['Ticket No.',  $view_ticket['ticket_no']],
                            ['Category',    ucfirst($view_ticket['category'] ?? '')],
                            ['Priority',    ucfirst($view_ticket['priority'] ?? '')],
                            ['Status',      ucfirst(str_replace('_',' ',$view_ticket['status'] ?? ''))],
                            ['Submitted',   date('d M Y H:i', strtotime($view_ticket['created_at']))],
                            ['Updated',     !empty($view_ticket['updated_at']) ? date('d M Y H:i', strtotime($view_ticket['updated_at'])) : '—'],
                        ];
                        foreach($tinfo as [$l,$v]):
                        ?>
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"
                             style="font-size:13px;">
                            <span class="text-muted"><?php echo $l; ?></span>
                            <span class="font-weight-600"><?php echo htmlspecialchars($v); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Status Change -->
                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="mb-0" style="font-size:13px;">Quick Status</h6></div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="form_status" value="1">
                            <input type="hidden" name="ticket_id"   value="<?php echo $view_ticket['id']; ?>">
                            <select name="new_status" class="form-control form-control-sm mb-2">
                                <option value="open">🔵 Open</option>
                                <option value="in_progress">🟡 In Progress</option>
                                <option value="resolved">🟢 Resolved</option>
                                <option value="closed">⚫ Closed</option>
                            </select>
                            <button type="submit" class="btn btn-secondary btn-block btn-sm">Update Status</button>
                        </form>
                    </div>
                </div>

                <!-- Reply Templates -->
                <div class="card">
                    <div class="card-header py-2"><h6 class="mb-0" style="font-size:13px;">Quick Templates</h6></div>
                    <div class="card-body p-2">
                        <?php
                        $cust_first = $view_ticket['first_name'];
                        foreach($templates as $label => $tmpl):
                            $filled = str_replace('{name}', $cust_first, $tmpl);
                        ?>
                        <button type="button" class="btn btn-outline-secondary btn-block btn-sm text-left mb-1"
                                style="font-size:12px;"
                                onclick='fillTemplate(<?php echo json_encode($filled); ?>)'>
                            <i class="fas fa-file-alt mr-1"></i> <?php echo $label; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================== TICKET LIST ============================== -->

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <?php
    $tabs = [
        ['all','All',null,''],
        ['open','Open',$stats['open_cnt'],'badge-danger'],
        ['in_progress','In Progress',$stats['inprog_cnt'],'badge-warning'],
        ['resolved','Resolved',$stats['resolved_cnt'],'badge-success'],
        ['closed','Closed',$stats['closed_cnt'],'badge-secondary'],
    ];
    foreach($tabs as [$val,$label,$cnt,$badge]):
    ?>
    <li class="nav-item">
        <a href="<?php echo spUrl(['status'=>$val,'page'=>1,'view'=>'']); ?>"
           class="nav-link <?php echo $status_f===$val?'active':''; ?>">
            <?php echo $label; ?>
            <?php if($badge && $cnt > 0): ?>
                <span class="badge <?php echo $badge; ?> ml-1"><?php echo $cnt; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Filters -->
<form method="GET" class="row mb-3 align-items-end">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_f); ?>">
    <?php if($view_id): ?><input type="hidden" name="view" value="<?php echo $view_id; ?>"><?php endif; ?>

    <div class="col-md-4">
        <input type="text" name="q" class="form-control form-control-sm"
               placeholder="Search ticket no, subject, customer name or email..."
               value="<?php echo htmlspecialchars($search); ?>">
    </div>
    <div class="col-md-2">
        <select name="priority" class="form-control form-control-sm">
            <option value="all"    <?php echo $priority_f==='all'?'selected':''; ?>>All Priorities</option>
            <option value="high"   <?php echo $priority_f==='high'?'selected':''; ?>>🔴 High</option>
            <option value="medium" <?php echo $priority_f==='medium'?'selected':''; ?>>🟡 Medium</option>
            <option value="low"    <?php echo $priority_f==='low'?'selected':''; ?>>🟢 Low</option>
        </select>
    </div>
    <div class="col-md-1">
        <button type="submit" class="btn btn-primary btn-sm btn-block">
            <i class="fas fa-filter"></i>
        </button>
    </div>
    <?php if($search || $priority_f !== 'all'): ?>
    <div class="col-md-2">
        <a href="<?php echo spUrl(['q'=>'','priority'=>'all','page'=>1]); ?>"
           class="btn btn-outline-secondary btn-sm btn-block">
            <i class="fas fa-times mr-1"></i> Clear
        </a>
    </div>
    <?php endif; ?>
    <div class="col text-right">
        <small class="text-muted"><?php echo number_format($total_rows); ?> ticket(s)</small>
    </div>
</form>

<!-- Tickets Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:13px;">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:30px;">#</th>
                        <th>Ticket / Subject</th>
                        <th>Customer</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Conversation</th>
                        <th>Updated</th>
                        <th style="width:100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($tickets)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-3x d-block mb-3" style="opacity:.2;"></i>
                                No support tickets found.
                            </td>
                        </tr>
                    <?php else: foreach($tickets as $i => $t):
                        $is_viewing   = ($view_id === (int)$t['id']);
                        $is_urgent    = ($t['priority'] === 'high' && in_array($t['status'] ?? 'open', ['open','in_progress']));
                        $has_admin_r  = !empty($t['admin_reply']);
                        $has_cust_r   = !empty($t['customer_reply']);
                        $cust_unread  = $has_cust_r && !($t['admin_seen_reply'] ?? 1);
                        $needs_reply  = !$has_admin_r && in_array($t['status'] ?? 'open', ['open','in_progress']);

                        $row_cls = $is_viewing ? 'table-active' : ($is_urgent ? 'table-warning' : ($cust_unread ? 'table-info' : ''));

                        $cat_badge_color = ['account'=>'primary','transfer'=>'info','card'=>'success','technical'=>'warning','other'=>'secondary'][$t['category'] ?? 'other'] ?? 'secondary';
                        $sbadge = ['open'=>'primary','in_progress'=>'warning','resolved'=>'success','closed'=>'secondary'][$t['status'] ?? 'open'] ?? 'secondary';
                        $pbadge = ['high'=>'danger','medium'=>'warning','low'=>'success'][$t['priority'] ?? 'medium'] ?? 'secondary';
                    ?>
                    <tr class="<?php echo $row_cls; ?>">
                        <td class="text-muted"><?php echo $offset+$i+1; ?></td>

                        <td style="max-width:220px;">
                            <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?php if($is_urgent): ?>
                                    <i class="fas fa-exclamation-circle text-danger mr-1" title="High Priority"></i>
                                <?php endif; ?>
                                <?php if($cust_unread): ?>
                                    <i class="fas fa-comment text-primary mr-1" title="Customer replied — unread"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($t['subject']); ?>
                            </div>
                            <code style="font-size:10px;color:#e67e22;"><?php echo htmlspecialchars($t['ticket_no']); ?></code>
                        </td>

                        <td>
                            <div style="font-weight:600;"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars($t['email']); ?></small>
                        </td>

                        <td>
                            <span class="badge badge-<?php echo $cat_badge_color; ?>" style="font-size:10px;">
                                <?php echo ucfirst($t['category'] ?? ''); ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge badge-<?php echo $pbadge; ?>" style="font-size:10px;">
                                <?php echo ucfirst($t['priority'] ?? ''); ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge badge-<?php echo $sbadge; ?>" style="font-size:10px;">
                                <?php echo ucfirst(str_replace('_',' ',$t['status'] ?? '')); ?>
                            </span>
                        </td>

                        <td>
                            <?php if($cust_unread): ?>
                                <span class="badge badge-primary" style="font-size:10px;">
                                    💬 Customer Replied — NEW
                                </span>
                            <?php elseif($has_cust_r && $has_admin_r): ?>
                                <span class="badge badge-info" style="font-size:10px;">
                                    ↩ Customer Replied
                                </span>
                            <?php elseif($has_admin_r): ?>
                                <span class="badge badge-success" style="font-size:10px;">
                                    ✓ Replied
                                </span>
                            <?php elseif($needs_reply): ?>
                                <span class="badge badge-warning" style="font-size:10px;">
                                    ⏳ Needs Reply
                                </span>
                            <?php else: ?>
                                <span class="badge badge-secondary" style="font-size:10px;">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <small class="text-muted">
                                <?php echo !empty($t['updated_at']) ? date('d M Y', strtotime($t['updated_at'])) : date('d M Y', strtotime($t['created_at'])); ?>
                            </small>
                        </td>

                        <td>
                            <a href="<?php echo spUrl(['view'=>$t['id'],'page'=>$page]); ?>"
                               class="btn btn-<?php echo $is_viewing?'secondary':'primary'; ?> btn-sm mb-1">
                                <i class="fas fa-<?php echo $is_viewing?'times':'reply'; ?>"></i>
                                <?php echo $is_viewing ? 'Close' : 'Reply'; ?>
                            </a>
                            <a href="customer-detail.php?id=<?php echo $t['user_id']; ?>"
                               class="btn btn-info btn-sm mb-1" title="View Customer">
                                <i class="fas fa-user"></i>
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
                <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo spUrl(['page'=>$page-1]); ?>">&laquo;</a>
                    </li>
                <?php endif; ?>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                    <li class="page-item <?php echo $i===$page?'active':''; ?>">
                        <a class="page-link" href="<?php echo spUrl(['page'=>$i]); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo spUrl(['page'=>$page+1]); ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

</section>
</div>

<script>
// ── Reply textarea char counter ──
const ta = document.getElementById('adminReplyTA');
const cc = document.getElementById('adminCC');
if(ta && cc) {
    const update = () => cc.textContent = ta.value.length + ' chars';
    ta.addEventListener('input', update);
    update();
}

// ── Fill template into textarea ──
function fillTemplate(text) {
    const ta = document.getElementById('adminReplyTA');
    if(ta) {
        ta.value = text;
        ta.focus();
        ta.dispatchEvent(new Event('input'));
        ta.scrollIntoView({ behavior:'smooth', block:'center' });
    }
}

// ── Auto-scroll to ticket detail on page load ──
document.addEventListener('DOMContentLoaded', function() {
    const detail = document.querySelector('[style*="border:2px solid #2563eb"]');
    if(detail) {
        setTimeout(() => detail.scrollIntoView({ behavior:'smooth', block:'start' }), 100);
    }
    // Auto-dismiss alerts
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const btn = alert.querySelector('[data-dismiss="alert"]');
            if(btn) btn.click();
        }, 8000);
    });
});
</script>

<?php include 'layout/footer.php'; ?>