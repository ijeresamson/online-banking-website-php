<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login"); 
    exit;
}
$user_id = (int)$_SESSION['user_id'];

if(empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// AJAX — MARK TICKET AS READ (fires when modal opens)
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    header('Content-Type: application/json');
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    if($ticket_id > 0) {
        $pdo->prepare("
            UPDATE support_tickets
            SET user_seen_reply = 1
            WHERE id = ? AND user_id = ?
        ")->execute([$ticket_id, $user_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// ============================================================
// HANDLE — SUBMIT NEW TICKET
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_ticket'])) {
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))
            throw new Exception("Security check failed. Please refresh and try again.");

        $subject  = trim($_POST['subject']  ?? '');
        $message  = trim($_POST['message']  ?? '');
        $category = trim($_POST['category'] ?? 'other');
        $priority = trim($_POST['priority'] ?? 'medium');

        if(empty($subject))           throw new Exception("Subject is required.");
        if(strlen($subject) > 255)    throw new Exception("Subject is too long (max 255 chars).");
        if(empty($message))           throw new Exception("Please describe your issue.");
        if(strlen($message) < 10)     throw new Exception("Please provide more detail (at least 10 characters).");
        if(!in_array($category, ['account','transfer','card','technical','other']))
            throw new Exception("Invalid category selected.");
        if(!in_array($priority, ['low','medium','high']))
            throw new Exception("Invalid priority selected.");

        $ticket_no = 'MWB-'.strtoupper(bin2hex(random_bytes(4)));

        $pdo->prepare("
            INSERT INTO support_tickets
                (user_id, ticket_no, subject, message, category, priority,
                 status, user_seen_reply, admin_seen_reply, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'open', 1, 1, NOW())
        ")->execute([$user_id, $ticket_no, $subject, $message, $category, $priority]);

        $_SESSION['success_message'] = "Ticket <strong>{$ticket_no}</strong> submitted! We respond within 24 hours.";
        header("Location: support"); 
        exit;

    } catch(Exception $e) {
        $ticket_error = $e->getMessage();
    }
}

// ============================================================
// HANDLE — CUSTOMER REPLY TO ADMIN MESSAGE
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_reply'])) {
    try {
        if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? ''))
            throw new Exception("Security check failed.");

        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $reply     = trim($_POST['reply_message'] ?? '');

        if($ticket_id <= 0)        throw new Exception("Invalid ticket.");
        if(empty($reply))          throw new Exception("Reply cannot be empty.");
        if(strlen($reply) < 3)     throw new Exception("Reply is too short.");
        if(strlen($reply) > 3000)  throw new Exception("Reply is too long (max 3000 characters).");

        $s = $pdo->prepare("
            SELECT * FROM support_tickets
            WHERE id = ? AND user_id = ? AND status NOT IN('closed')
            LIMIT 1
        ");
        $s->execute([$ticket_id, $user_id]);
        $ticket = $s->fetch(PDO::FETCH_ASSOC);

        if(!$ticket)
            throw new Exception("Ticket not found or is already closed.");
        if(empty($ticket['admin_reply']))
            throw new Exception("Please wait for our support team to respond before sending a follow-up.");

        $pdo->prepare("
            UPDATE support_tickets
            SET customer_reply          = ?,
                customer_replied_at     = NOW(),
                admin_seen_reply        = 0,
                user_seen_reply         = 1,
                status                  = IF(status = 'resolved', 'in_progress', status),
                updated_at              = NOW()
            WHERE id = ? AND user_id = ?
        ")->execute([$reply, $ticket_id, $user_id]);

        $_SESSION['success_message'] = "Your reply has been sent! Our team will get back to you shortly.";
        header("Location: support?open_id={$ticket_id}"); exit;

    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: support");
         exit;
    }
}

// ============================================================
// FETCH TICKETS
// ============================================================
$page_title = 'Support Center';

$tickets      = [];
$unread_count = 0;

try {
    $s = $pdo->prepare("
        SELECT * FROM support_tickets
        WHERE user_id = ?
        ORDER BY
            CASE WHEN (user_seen_reply = 0 AND admin_reply IS NOT NULL) THEN 0 ELSE 1 END,
            updated_at DESC
    ");
    $s->execute([$user_id]);
    $tickets = $s->fetchAll(PDO::FETCH_ASSOC);

    foreach($tickets as $t) {
        if(!empty($t['admin_reply']) && ($t['user_seen_reply'] ?? 1) == 0) {
            $unread_count++;
        }
    }
} catch(PDOException $e) {}

// Auto-open ticket from redirect
$auto_open_id = (int)($_GET['open_id'] ?? 0);

// Build JS tickets data
$js_tickets = [];
foreach($tickets as $t) {
    $js_tickets[(int)$t['id']] = [
        'id'                  => (int)$t['id'],
        'ticket_no'           => $t['ticket_no'],
        'subject'             => $t['subject'],
        'message'             => $t['message'],
        'category'            => ucfirst($t['category'] ?? 'other'),
        'priority'            => ucfirst($t['priority'] ?? 'medium'),
        'status'              => $t['status'] ?? 'open',
        'created_at'          => date('d M Y, H:i', strtotime($t['created_at'])),
        'updated_at'          => !empty($t['updated_at']) ? date('d M Y, H:i', strtotime($t['updated_at'])) : '',
        'admin_reply'         => $t['admin_reply'] ?? '',
        'replied_at'          => !empty($t['replied_at']) ? date('d M Y, H:i', strtotime($t['replied_at'])) : '',
        'customer_reply'      => $t['customer_reply'] ?? '',
        'customer_replied_at' => !empty($t['customer_replied_at']) ? date('d M Y, H:i', strtotime($t['customer_replied_at'])) : '',
        'has_admin_reply'     => !empty($t['admin_reply']),
        'is_unread'           => !empty($t['admin_reply']) && ($t['user_seen_reply'] ?? 1) == 0,
        'can_reply'           => !empty($t['admin_reply']) && !in_array($t['status'] ?? 'open', ['closed']),
    ];
}

$cat_icons = [
    'account'   => 'ri-user-line',
    'transfer'  => 'ri-exchange-line',
    'card'      => 'ri-bank-card-line',
    'technical' => 'ri-settings-3-line',
    'other'     => 'ri-question-line',
];

$extra_css = '
/* ============================================================
   SUPPORT PAGE — MIDWESTERNBANK
============================================================ */
.sp-layout { display:grid; grid-template-columns:1fr 320px; gap:22px; align-items:start; }
@media(max-width:900px){ .sp-layout{ grid-template-columns:1fr; } }

/* ── Unread Banner ── */
.unread-banner {
    background:linear-gradient(135deg,#0c2340,#1a4b8c);
    border-radius:14px; padding:18px 22px;
    display:flex; align-items:center; gap:16px; color:#fff;
    margin-bottom:22px; box-shadow:0 8px 24px rgba(12,35,64,.2);
    transition:all .4s ease;
}
.unread-banner.hiding { opacity:0; transform:translateY(-10px); max-height:0; overflow:hidden; margin-bottom:0; padding:0; }

/* ── Ticket List ── */
.ticket-list { background:#fff; border-radius:14px; border:1px solid var(--border); overflow:hidden; box-shadow:var(--shadow); }
.ticket-list-header { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }

.ticket-item {
    padding:16px 20px; border-bottom:1px solid var(--border);
    cursor:pointer; transition:all .18s; position:relative;
    display:flex; align-items:flex-start; gap:14px;
}
.ticket-item:last-child { border-bottom:none; }
.ticket-item:hover { background:#f8faff; }
.ticket-item.is-unread {
    background:linear-gradient(90deg,#f0f9ff,#fafeff);
    border-left:4px solid #2563eb;
}
.ticket-item.is-unread:hover { background:linear-gradient(90deg,#e0f0ff,#f0f8ff); }

.ticket-cat-icon {
    width:40px; height:40px; border-radius:10px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:17px;
    background:var(--bg); color:var(--text-muted); transition:all .18s;
}
.ticket-item.is-unread .ticket-cat-icon { background:#dbeafe; color:#2563eb; }

/* Unread pulse dot */
.unread-dot {
    width:10px; height:10px; background:#ef4444; border-radius:50%;
    position:absolute; top:14px; right:16px;
    animation:pulse-dot 1.5s infinite;
}
@keyframes pulse-dot {
    0%,100%{ transform:scale(1); opacity:1; }
    50%{ transform:scale(1.4); opacity:.6; }
}

/* Status & Priority pills */
.status-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.s-open        { background:#dbeafe; color:#2563eb; }
.s-in_progress { background:#fef3c7; color:#d97706; }
.s-resolved    { background:#d1fae5; color:#059669; }
.s-closed      { background:#f3f4f6; color:#6b7280; }
.p-high        { background:#fee2e2; color:#dc2626; }
.p-medium      { background:#fef3c7; color:#d97706; }
.p-low         { background:#d1fae5; color:#059669; }

/* ── MODAL ── */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(12,35,64,.6); z-index:600;
    align-items:center; justify-content:center;
    backdrop-filter:blur(4px);
}
.modal-overlay.active { display:flex; }

.modal-box {
    background:#fff; border-radius:18px;
    width:100%; max-width:660px; margin:16px;
    display:flex; flex-direction:column;
    max-height:90vh; overflow:hidden;
    box-shadow:0 24px 64px rgba(0,0,0,.2);
    animation:modal-in .25s ease;
}
@keyframes modal-in { from{transform:translateY(20px);opacity:0;} to{transform:translateY(0);opacity:1;} }

.modal-head { flex-shrink:0; padding:0; }
.modal-head-inner {
    background:linear-gradient(135deg,#0c2340,#1a4b8c);
    padding:20px 24px;
    display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
}
.modal-close-btn {
    width:30px; height:30px; border-radius:8px; border:1px solid rgba(255,255,255,.25);
    background:rgba(255,255,255,.1); color:#fff; cursor:pointer; font-size:16px;
    display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all .18s;
}
.modal-close-btn:hover { background:rgba(255,255,255,.25); }

/* ── Conversation Thread ── */
.conv-scroll { flex:1; overflow-y:auto; padding:20px 24px; }
.conv-scroll::-webkit-scrollbar { width:4px; }
.conv-scroll::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }

.conv-msg { border-radius:12px; padding:16px 18px; margin-bottom:16px; position:relative; }
.conv-msg-customer { background:#f1f5f9; border-left:4px solid #94a3b8; }
.conv-msg-admin    { background:linear-gradient(135deg,#eff6ff,#dbeafe); border-left:4px solid #2563eb; }
.conv-msg-customer-reply { background:linear-gradient(135deg,#f0fdf4,#dcfce7); border-left:4px solid #22c55e; }
.conv-msg-header { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.conv-avatar { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; }
.conv-msg-body { font-size:13px; line-height:1.75; color:#1e293b; white-space:pre-wrap; word-break:break-word; }

/* Awaiting reply box */
.awaiting-box { background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:14px 16px; display:flex; gap:12px; align-items:flex-start; margin-bottom:16px; }

/* ── Reply Form in Modal ── */
.reply-form-area { flex-shrink:0; padding:16px 24px; border-top:1px solid var(--border); background:#fafafa; }
.reply-textarea {
    width:100%; height:90px; padding:12px 14px; resize:none;
    border:1.5px solid var(--border); border-radius:9px;
    font-size:13px; font-family:"Inter",sans-serif;
    color:var(--text); background:#fff; outline:none; transition:all .2s;
    line-height:1.6;
}
.reply-textarea:focus { border-color:var(--blue); box-shadow:0 0 0 3px rgba(26,75,140,.07); }

/* ── Right sidebar cards ── */
.sp-sidebar-card { background:#fff; border-radius:14px; border:1px solid var(--border); box-shadow:var(--shadow); padding:20px; margin-bottom:18px; }
.contact-row { display:flex; align-items:center; gap:12px; padding:11px; background:var(--bg); border-radius:10px; margin-bottom:8px; border:1px solid var(--border); }
.contact-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }

/* ── FAQ ── */
.faq-item { border:1px solid var(--border); border-radius:10px; margin-bottom:7px; overflow:hidden; }
.faq-q { display:flex; justify-content:space-between; align-items:center; padding:12px 15px; cursor:pointer; font-size:13px; font-weight:600; transition:background .15s; }
.faq-q:hover { background:var(--bg); }
.faq-a { display:none; padding:11px 15px; font-size:13px; color:var(--text-muted); line-height:1.7; border-top:1px solid var(--border); }
.faq-a.open { display:block; }

/* ── New ticket form ── */
.sp-form-card { background:#fff; border-radius:14px; border:1px solid var(--border); box-shadow:var(--shadow); padding:22px; margin-bottom:18px; }
.char-counter { font-size:11px; color:var(--text-muted); }
';

include 'layout/customer-header.php';
?>

<div class="page-title">
    Support Center
    <?php if($unread_count > 0): ?>
        <span id="unreadBadge" style="background:#ef4444;color:#fff;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;vertical-align:middle;margin-left:10px;transition:all .3s;">
            <?php echo $unread_count; ?> New <?php echo $unread_count === 1 ? 'Reply' : 'Replies'; ?>
        </span>
    <?php else: ?>
        <span id="unreadBadge" style="display:none;background:#ef4444;color:#fff;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;vertical-align:middle;margin-left:10px;"></span>
    <?php endif; ?>
</div>
<div class="page-subtitle">Get help from MidwesternBank specialists · We respond within 24 hours</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><i class="ri-checkbox-circle-line"></i><span><?php echo $_SESSION['success_message']; ?></span></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if(!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><i class="ri-error-warning-line"></i><span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span></div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- ── UNREAD BANNER ── -->
<div id="unreadBanner" class="unread-banner" style="<?php echo $unread_count > 0 ? '' : 'display:none;'; ?>">
    <div style="width:50px;height:50px;background:rgba(255,255,255,.15);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">💬</div>
    <div style="flex:1;">
        <div style="font-size:15px;font-weight:700;margin-bottom:3px;" id="bannerTitle">
            You have <?php echo $unread_count; ?> unread <?php echo $unread_count === 1 ? 'reply' : 'replies'; ?> from our support team!
        </div>
        <div style="font-size:12px;opacity:.8;">Click the highlighted ticket<?php echo $unread_count > 1 ? 's' : ''; ?> below to read and respond.</div>
    </div>
    <div style="background:rgba(255,255,255,.15);padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;white-space:nowrap;" id="bannerCount">
        <?php echo $unread_count; ?> Unread
    </div>
</div>

<div class="sp-layout">

<!-- ============================== LEFT: FORM + TICKETS ============================== -->
<div>

    <!-- New Ticket Form -->
    <div class="sp-form-card">
        <div class="card-title"><i class="ri-mail-send-line"></i> Submit a Support Ticket</div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
            Our team responds within <strong>24 hours</strong>. High priority tickets are handled first.
        </p>

        <?php if(!empty($ticket_error)): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($ticket_error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="form_ticket" value="1">

            <div class="form-group" style="margin-bottom:12px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy);">
                    Subject <span style="color:var(--red);">*</span>
                </label>
                <div style="position:relative;margin-top:5px;">
                    <i class="ri-file-text-line" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:16px;pointer-events:none;"></i>
                    <input type="text" name="subject" class="form-control" style="padding-left:40px;"
                           placeholder="e.g. Transfer not received, KYC question, Card issue..."
                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                           maxlength="255" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div class="form-group">
                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy);">Category</label>
                    <select name="category" class="form-control" style="margin-top:5px;">
                        <?php foreach(['account'=>'💼 Account','transfer'=>'↔ Transfer','card'=>'💳 Card','technical'=>'🔧 Technical','other'=>'❓ Other'] as $v=>$l): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($_POST['category'] ?? 'account') === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy);">Priority</label>
                    <select name="priority" class="form-control" style="margin-top:5px;">
                        <option value="low"    <?php echo ($_POST['priority'] ?? '') === 'low'    ? 'selected' : ''; ?>>🟢 Low</option>
                        <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                        <option value="high"   <?php echo ($_POST['priority'] ?? '') === 'high'   ? 'selected' : ''; ?>>🔴 High — Urgent</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--navy);">
                    Message <span style="color:var(--red);">*</span>
                </label>
                <textarea name="message" rows="4" id="ticketMsg"
                          class="form-control" style="height:auto;padding:12px;resize:vertical;margin-top:5px;"
                          placeholder="Describe your issue. Include:&#10;• What happened?&#10;• When?&#10;• Transaction reference if applicable"
                          required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                <div style="text-align:right;margin-top:3px;">
                    <span class="char-counter" id="msgCount">0 / 2000</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="height:44px;">
                <i class="ri-send-plane-fill"></i> Submit Support Ticket
            </button>
        </form>
    </div>

    <!-- Ticket List -->
    <div class="ticket-list">
        <div class="ticket-list-header">
            <div style="font-size:14px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:8px;">
                <i class="ri-file-list-3-line" style="color:var(--gold);"></i>
                My Tickets
            </div>
            <span style="font-size:12px;color:var(--text-muted);"><?php echo count($tickets); ?> total</span>
        </div>

        <?php if(empty($tickets)): ?>
            <div style="text-align:center;padding:40px;color:var(--text-muted);">
                <i class="ri-inbox-line" style="font-size:42px;display:block;margin-bottom:12px;color:var(--border);"></i>
                <p>No tickets yet. Use the form above to get help.</p>
            </div>
        <?php else: ?>
            <?php foreach($tickets as $t):
                $is_unread  = !empty($t['admin_reply']) && ($t['user_seen_reply'] ?? 1) == 0;
                $has_admin  = !empty($t['admin_reply']);
                $has_cust   = !empty($t['customer_reply']);
                $cat_icon   = $cat_icons[$t['category'] ?? 'other'] ?? 'ri-question-line';
                $stat_class = 's-'.str_replace('_','_', $t['status'] ?? 'open');
                $pri_class  = 'p-'.($t['priority'] ?? 'medium');
            ?>
            <div class="ticket-item <?php echo $is_unread ? 'is-unread' : ''; ?>"
                 id="ticket-item-<?php echo $t['id']; ?>"
                 onclick="openTicket(<?php echo $t['id']; ?>)">

                <?php if($is_unread): ?>
                    <div class="unread-dot" id="dot-<?php echo $t['id']; ?>"></div>
                <?php else: ?>
                    <div class="unread-dot" id="dot-<?php echo $t['id']; ?>" style="display:none;"></div>
                <?php endif; ?>

                <!-- Icon -->
                <div class="ticket-cat-icon">
                    <i class="<?php echo $cat_icon; ?>"></i>
                </div>

                <!-- Content -->
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                        <span style="font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;">
                            <?php echo htmlspecialchars($t['subject']); ?>
                        </span>
                        <?php if($is_unread): ?>
                            <span style="background:#ef4444;color:#fff;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;flex-shrink:0;">💬 New Reply!</span>
                        <?php elseif($has_cust): ?>
                            <span style="background:#d1fae5;color:#059669;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;flex-shrink:0;">✓ You Replied</span>
                        <?php elseif($has_admin): ?>
                            <span style="background:#d1fae5;color:#059669;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:700;flex-shrink:0;">✓ Replied</span>
                        <?php else: ?>
                            <span style="background:#fef3c7;color:#d97706;padding:2px 9px;border-radius:20px;font-size:10px;font-weight:600;flex-shrink:0;">⏳ Awaiting Reply</span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">
                        <span style="font-family:monospace;color:var(--gold);"><?php echo htmlspecialchars($t['ticket_no']); ?></span>
                        · <?php echo ucfirst($t['category'] ?? ''); ?>
                        · <?php echo date('d M Y', strtotime($t['created_at'])); ?>
                    </div>

                    <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                        <span class="status-pill <?php echo $stat_class; ?>">
                            <?php
                            $stat_labels = ['open'=>'● Open','in_progress'=>'◉ In Progress','resolved'=>'✓ Resolved','closed'=>'⊘ Closed'];
                            echo $stat_labels[$t['status'] ?? 'open'] ?? ucfirst($t['status'] ?? '');
                            ?>
                        </span>
                        <span class="status-pill <?php echo $pri_class; ?>"><?php echo ucfirst($t['priority'] ?? ''); ?></span>
                    </div>

                    <?php if($is_unread && !empty($t['admin_reply'])): ?>
                    <div style="margin-top:10px;padding:9px 12px;background:#dbeafe;border-radius:8px;border-left:3px solid #2563eb;font-size:12px;color:#1e40af;">
                        <div style="font-weight:700;margin-bottom:2px;"><i class="ri-customer-service-2-line"></i> Support Team replied:</div>
                        <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars(substr($t['admin_reply'], 0, 90)); ?>…</div>
                        <div style="margin-top:5px;font-size:11px;font-weight:700;">Tap to read &amp; reply →</div>
                    </div>
                    <?php endif; ?>
                </div>

                <i class="ri-arrow-right-s-line" style="font-size:20px;color:var(--text-muted);flex-shrink:0;margin-top:2px;"></i>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- ============================== RIGHT: FAQ + CONTACT ============================== -->
<div>

    <!-- Contact Options -->
    <div class="sp-sidebar-card">
        <div class="card-title"><i class="ri-customer-service-2-line"></i> Contact Us</div>
        <?php
        $contacts = [
            ['ri-whatsapp-line','#d1fae5','#059669','WhatsApp Support','Mon–Fri, 9am–6pm EST','https://wa.me/+15550192834','Chat Now'],
            ['ri-mail-line','#dbeafe','#2563eb','Email Support','support@midwesternbank.com','mailto:support@midwesternbank.com','Email'],
            ['ri-alarm-warning-line','#fee2e2','#dc2626','Fraud &amp; Emergency','24/7 urgent matters','mailto:fraud@midwesternbank.com','Report'],
        ];
        foreach($contacts as [$ic,$bg,$color,$title,$sub,$href,$btn]):
        ?>
        <div class="contact-row">
            <div class="contact-icon" style="background:<?php echo $bg; ?>;color:<?php echo $color; ?>;"><i class="<?php echo $ic; ?>"></i></div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;"><?php echo $title; ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?php echo $sub; ?></div>
            </div>
            <a href="<?php echo $href; ?>" class="btn btn-secondary" style="height:30px;font-size:12px;padding:0 10px;flex-shrink:0;" target="_blank"><?php echo $btn; ?></a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FAQ -->
    <div class="sp-sidebar-card">
        <div class="card-title"><i class="ri-question-answer-line"></i> FAQ</div>
        <input type="text" class="form-control" id="faqSearch"
               placeholder="Search FAQ..." style="height:36px;margin-bottom:12px;"
               oninput="filterFaq(this.value)">
        <div id="faqList">
            <?php
            $faqs = [
                ['How long does KYC take?','KYC review takes 1–2 business days. You will receive an email once approved. Open a ticket if it has been more than 3 days.'],
                ['How do I transfer to another bank?','Go to Send Money → Wire/External → fill in recipient details including Routing or SWIFT code.'],
                ['My card is blocked — what do I do?','Go to Cards page and tap Unblock if you blocked it yourself. If blocked by us, open a ticket.'],
                ['How do I receive money?','Go to Receive Money for your Account Number, Routing, Wire Routing, and SWIFT code.'],
                ['How long do withdrawals take?','Withdrawals are reviewed within 1–3 business days after compliance review.'],
                ['What is your SWIFT code?','Our SWIFT/BIC is <strong>'.BANK_SWIFT.'</strong>. Also shown on your Receive Money page.'],
                ['How do I report fraud?','Email fraud@midwesternbank.com or open a HIGH priority ticket immediately.'],
            ];
            foreach($faqs as $i => [$q, $a]):
            ?>
            <div class="faq-item" data-q="<?php echo htmlspecialchars(strtolower($q)); ?>">
                <div class="faq-q" onclick="toggleFaq(<?php echo $i; ?>)">
                    <span style="font-size:13px;"><?php echo htmlspecialchars($q); ?></span>
                    <i class="ri-arrow-down-s-line" id="fqi<?php echo $i; ?>" style="font-size:18px;flex-shrink:0;transition:transform .2s;"></i>
                </div>
                <div class="faq-a" id="fqa<?php echo $i; ?>"><?php echo $a; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="faqEmpty" style="display:none;text-align:center;padding:16px;color:var(--text-muted);font-size:13px;">
            No FAQ matches. Submit a ticket for help.
        </div>
    </div>

</div>
</div><!-- /.sp-layout -->

<!-- ============================================================ CONVERSATION MODAL ============================================================ -->
<div class="modal-overlay" id="ticketModal">
    <div class="modal-box">

        <!-- Header -->
        <div class="modal-head">
            <div class="modal-head-inner">
                <div style="flex:1;min-width:0;">
                    <div style="font-size:10px;font-family:monospace;color:rgba(255,255,255,.5);margin-bottom:3px;" id="modalTicketNo">—</div>
                    <div style="font-size:16px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" id="modalSubject">—</div>
                    <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;" id="modalPills">—</div>
                </div>
                <button class="modal-close-btn" onclick="closeModal()">✕</button>
            </div>
        </div>

        <!-- Conversation Thread (scrollable) -->
        <div class="conv-scroll" id="convThread">
            <!-- Filled by JS -->
        </div>

        <!-- Reply Form or Close Button -->
        <div class="reply-form-area" id="replyArea">
            <!-- Filled by JS -->
        </div>

    </div>
</div>

<?php
$JS_CSRF        = json_encode($_SESSION['csrf_token']);
$JS_TICKETS     = json_encode(array_values($js_tickets));
$JS_TICKETS_MAP = json_encode($js_tickets);
$JS_AUTO_OPEN   = $auto_open_id;
$JS_UNREAD_CNT  = $unread_count;

$extra_js = "
const CSRF        = {$JS_CSRF};
const TICKETS_MAP = {$JS_TICKETS_MAP};
let unreadCount   = {$JS_UNREAD_CNT};

// ============================================================
// OPEN CONVERSATION MODAL
// ============================================================
function openTicket(id) {
    const t = TICKETS_MAP[id];
    if(!t) return;

    // ── Mark as read via AJAX ──
    if(t.is_unread) {
        markAsRead(id);
    }

    // ── Fill modal header ──
    document.getElementById('modalTicketNo').textContent = t.ticket_no;
    document.getElementById('modalSubject').textContent  = t.subject;

    const sColors  = { open:'s-open', in_progress:'s-in_progress', resolved:'s-resolved', closed:'s-closed' };
    const sLabels  = { open:'● Open', in_progress:'◉ In Progress', resolved:'✓ Resolved', closed:'⊘ Closed' };
    const pColors  = { high:'p-high', medium:'p-medium', low:'p-low' };
    const sClass   = sColors[t.status]  || 's-open';
    const pClass   = pColors[t.priority.toLowerCase()] || 'p-medium';
    const sLabel   = sLabels[t.status]  || t.status;

    document.getElementById('modalPills').innerHTML =
        '<span class=\"status-pill '+sClass+'\">'+esc(sLabel)+'</span> '+
        '<span class=\"status-pill '+pClass+'\">'+esc(t.priority)+'</span> '+
        '<span style=\"font-size:11px;color:rgba(255,255,255,.5);\">'+esc(t.category)+'</span>';

    // ── Build conversation thread ──
    let html = '';

    // 1. Original customer message
    html += '<div class=\"conv-msg conv-msg-customer\">';
    html += '<div class=\"conv-msg-header\">';
    html += '<div class=\"conv-avatar\" style=\"background:#64748b;\">You</div>';
    html += '<div style=\"flex:1;\"><div style=\"font-size:13px;font-weight:600;color:#475569;\">Your Message</div><div style=\"font-size:11px;color:#6b7280;\">'+esc(t.created_at)+'</div></div>';
    html += '</div>';
    html += '<div class=\"conv-msg-body\">'+esc(t.message)+'</div>';
    html += '</div>';

    // 2. Admin reply
    if(t.has_admin_reply && t.admin_reply) {
        html += '<div class=\"conv-msg conv-msg-admin\">';
        html += '<div class=\"conv-msg-header\">';
        html += '<div class=\"conv-avatar\" style=\"background:linear-gradient(135deg,#0c2340,#1a4b8c);font-size:10px;letter-spacing:-.3px;\">SP</div>';
        html += '<div style=\"flex:1;\">';
        html += '<div style=\"font-size:13px;font-weight:700;color:#1e3a5f;\">MidwesternBank Support</div>';
        html += '<div style=\"font-size:11px;color:#6b7280;\">'+esc(t.replied_at)+'</div>';
        html += '</div>';
        html += '<span style=\"background:#d1fae5;color:#059669;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;\">✓ Official Reply</span>';
        html += '</div>';
        html += '<div class=\"conv-msg-body\">'+esc(t.admin_reply)+'</div>';
        html += '</div>';
    } else {
        html += '<div class=\"awaiting-box\">';
        html += '<i class=\"ri-time-line\" style=\"font-size:20px;color:#d97706;flex-shrink:0;\"></i>';
        html += '<div><div style=\"font-size:13px;font-weight:600;color:#92400e;\">Awaiting Support Response</div>';
        html += '<div style=\"font-size:12px;color:#b45309;margin-top:2px;\">Our team typically replies within 24 hours. High priority tickets are handled first.</div></div>';
        html += '</div>';
    }

    // 3. Customer follow-up reply (if exists)
    if(t.customer_reply) {
        html += '<div class=\"conv-msg conv-msg-customer-reply\">';
        html += '<div class=\"conv-msg-header\">';
        html += '<div class=\"conv-avatar\" style=\"background:#22c55e;font-size:11px;\">You</div>';
        html += '<div style=\"flex:1;\"><div style=\"font-size:13px;font-weight:600;color:#14532d;\">Your Follow-up Reply</div><div style=\"font-size:11px;color:#6b7280;\">'+esc(t.customer_replied_at)+'</div></div>';
        html += '<span style=\"background:#d1fae5;color:#059669;padding:2px 10px;border-radius:20px;font-size:10px;font-weight:700;\">Sent ✓</span>';
        html += '</div>';
        html += '<div class=\"conv-msg-body\">'+esc(t.customer_reply)+'</div>';
        html += '</div>';
    }

    document.getElementById('convThread').innerHTML = html;

    // ── Reply form or close ──
    let replyHtml = '';
    if(t.can_reply && t.has_admin_reply) {
        replyHtml = '<form method=\"POST\" id=\"inlineReplyForm\">';
        replyHtml += '<input type=\"hidden\" name=\"csrf_token\"     value=\"'+CSRF+'\">';
        replyHtml += '<input type=\"hidden\" name=\"form_reply\"     value=\"1\">';
        replyHtml += '<input type=\"hidden\" name=\"ticket_id\"      value=\"'+t.id+'\">';
        replyHtml += '<div style=\"font-size:12px;font-weight:700;color:var(--navy);margin-bottom:8px;display:flex;align-items:center;gap:6px;\">';
        replyHtml += '<i class=\"ri-reply-line\" style=\"color:var(--gold);\"></i> Reply to Support Team';
        replyHtml += '</div>';
        replyHtml += '<textarea name=\"reply_message\" id=\"inlineReplyTA\" class=\"reply-textarea\" ';
        replyHtml += 'placeholder=\"Type your reply here — be as detailed as possible...\" required></textarea>';
        replyHtml += '<div style=\"display:flex;justify-content:flex-end;margin-top:4px;margin-bottom:10px;\">';
        replyHtml += '<span style=\"font-size:11px;color:var(--text-muted);\" id=\"replyCharCount\">0 chars</span>';
        replyHtml += '</div>';
        replyHtml += '<div style=\"display:flex;gap:10px;\">';
        replyHtml += '<button type=\"button\" onclick=\"closeModal()\" class=\"btn btn-secondary\" style=\"flex:1;height:40px;\">Close</button>';
        replyHtml += '<button type=\"submit\" class=\"btn btn-primary\" style=\"flex:2;height:40px;\" id=\"replySubmitBtn\">';
        replyHtml += '<i class=\"ri-send-plane-line\"></i> Send Reply';
        replyHtml += '</button>';
        replyHtml += '</div>';
        replyHtml += '</form>';
    } else {
        replyHtml = '<div style=\"display:flex;gap:10px;\">';
        replyHtml += '<button onclick=\"closeModal()\" class=\"btn btn-secondary\" style=\"flex:1;height:40px;\">Close</button>';
        if(t.status === 'closed') {
            replyHtml += '<div style=\"flex:2;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--text-muted);\">';
            replyHtml += '⊘ This ticket is closed';
            replyHtml += '</div>';
        } else if(!t.has_admin_reply) {
            replyHtml += '<div style=\"flex:2;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--text-muted);\">';
            replyHtml += '⏳ Awaiting our response before you can reply';
            replyHtml += '</div>';
        } else {
            replyHtml += '<a href=\"mailto:support@midwesternbank.com?subject=Re: '+esc(t.ticket_no)+' - '+esc(t.subject)+'\" class=\"btn btn-primary\" style=\"flex:1;height:40px;display:flex;align-items:center;justify-content:center;text-decoration:none;gap:6px;\">';
            replyHtml += '<i class=\"ri-mail-send-line\"></i> Follow Up by Email';
            replyHtml += '</a>';
        }
        replyHtml += '</div>';
    }
    document.getElementById('replyArea').innerHTML = replyHtml;

    // Wire up char counter and submit prevention
    const rta = document.getElementById('inlineReplyTA');
    const rcc = document.getElementById('replyCharCount');
    if(rta && rcc) {
        rta.addEventListener('input', function() {
            rcc.textContent = this.value.length + ' chars';
            rcc.style.color = this.value.length > 2800 ? 'var(--red)' : 'var(--text-muted)';
        });
    }
    const rForm = document.getElementById('inlineReplyForm');
    if(rForm) {
        rForm.addEventListener('submit', function() {
            const btn = document.getElementById('replySubmitBtn');
            if(btn) { btn.disabled = true; btn.innerHTML = '<i class=\"ri-loader-4-line\"></i> Sending...'; }
        });
    }

    // Scroll thread to bottom
    const thread = document.getElementById('convThread');
    setTimeout(() => thread.scrollTop = thread.scrollHeight, 80);

    document.getElementById('ticketModal').classList.add('active');
}

// ============================================================
// MARK TICKET AS READ — Remove notification immediately
// ============================================================
function markAsRead(ticketId) {
    const t = TICKETS_MAP[ticketId];
    if(!t || !t.is_unread) return;

    // Optimistically update UI first
    t.is_unread = false;
    unreadCount = Math.max(0, unreadCount - 1);

    // Remove dot from ticket item
    const dot = document.getElementById('dot-' + ticketId);
    if(dot) dot.style.display = 'none';

    // Remove unread class from ticket item
    const item = document.getElementById('ticket-item-' + ticketId);
    if(item) {
        item.classList.remove('is-unread');
        // Update the badge label in the ticket
        const newBadge = item.querySelector('[style*=\"ef4444\"]');
        if(newBadge && newBadge.textContent.includes('New Reply')) {
            newBadge.style.background   = '#d1fae5';
            newBadge.style.color        = '#059669';
            newBadge.textContent        = '✓ Replied';
        }
        // Remove the preview box
        const preview = item.querySelector('[style*=\"background:#dbeafe\"]');
        if(preview) preview.remove();
    }

    // Update global unread count
    updateUnreadUI();

    // AJAX to server
    const body = new FormData();
    body.append('action',    'mark_read');
    body.append('ticket_id', ticketId);
    fetch(window.location.pathname, { method:'POST', body, credentials:'same-origin' })
        .catch(() => {}); // silent fail — UI already updated
}

function updateUnreadUI() {
    const badge  = document.getElementById('unreadBadge');
    const banner = document.getElementById('unreadBanner');

    if(unreadCount <= 0) {
        // Hide badge
        if(badge) badge.style.display = 'none';
        // Animate banner out
        if(banner) {
            banner.style.transition = 'all .4s ease';
            banner.style.opacity    = '0';
            banner.style.maxHeight  = '0';
            banner.style.overflow   = 'hidden';
            banner.style.padding    = '0';
            banner.style.margin     = '0';
            setTimeout(() => banner.style.display = 'none', 400);
        }
    } else {
        // Update count
        if(badge) {
            badge.textContent = unreadCount + ' New ' + (unreadCount === 1 ? 'Reply' : 'Replies');
            badge.style.display = '';
        }
        const bannerTitle = document.getElementById('bannerTitle');
        const bannerCount = document.getElementById('bannerCount');
        if(bannerTitle) bannerTitle.textContent = 'You have ' + unreadCount + ' unread ' + (unreadCount === 1 ? 'reply' : 'replies') + ' from our support team!';
        if(bannerCount) bannerCount.textContent = unreadCount + ' Unread';
    }
}

function closeModal() {
    document.getElementById('ticketModal').classList.remove('active');
    document.getElementById('convThread').innerHTML = '';
    document.getElementById('replyArea').innerHTML  = '';
}

// Close modal on overlay click
document.getElementById('ticketModal').addEventListener('click', function(e) {
    if(e.target === this) closeModal();
});

// ESC key
document.addEventListener('keydown', e => {
    if(e.key === 'Escape' && document.getElementById('ticketModal').classList.contains('active')) closeModal();
});

// ============================================================
// FAQ
// ============================================================
function toggleFaq(i) {
    const a  = document.getElementById('fqa'+i);
    const ic = document.getElementById('fqi'+i);
    const open = a.classList.contains('open');
    document.querySelectorAll('.faq-a').forEach(x => x.classList.remove('open'));
    document.querySelectorAll('[id^=fqi]').forEach(x => x.style.transform = '');
    if(!open) { a.classList.add('open'); ic.style.transform = 'rotate(180deg)'; }
}
function filterFaq(q) {
    q = q.toLowerCase().trim();
    let n = 0;
    document.querySelectorAll('.faq-item').forEach(item => {
        const show = !q || item.dataset.q.includes(q);
        item.style.display = show ? '' : 'none';
        if(show) n++;
    });
    document.getElementById('faqEmpty').style.display = n===0 ? 'block' : 'none';
}

// Message char counter
const msgTA = document.getElementById('ticketMsg');
const msgCC = document.getElementById('msgCount');
if(msgTA && msgCC) {
    msgTA.addEventListener('input', function() {
        const l = this.value.length;
        msgCC.textContent = l + ' / 2000';
        msgCC.style.color = l > 1800 ? 'var(--red)' : 'var(--text-muted)';
        if(l > 2000) this.value = this.value.substring(0,2000);
    });
}

// Escape for XSS
function esc(s) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(s||'')));
    return d.innerHTML;
}

// Auto-open from redirect
".($auto_open_id > 0 ? "window.addEventListener('DOMContentLoaded',()=>setTimeout(()=>openTicket({$auto_open_id}),150));" : "");

include 'layout/customer-footer.php';
?>