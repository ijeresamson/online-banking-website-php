<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';

if(!isset($_SESSION['user_id'])) { header("Location: login"); exit; }
$user_id = (int)$_SESSION['user_id'];
$page_title = 'Statements';

$account  = getAccountFull($pdo, $user_id);
$balance  = (float)($account['balance'] ?? 0);
$acc_no   = $account['account_number'] ?? '';
$acc_name = $account['account_name'] ?? trim(($account['first_name']??'').' '.($account['last_name']??''));
$routing  = $account['routing_number'] ?? '—';

function stmtIsCredit(array $t): bool {
    $type = strtolower($t['type'] ?? '');
    if(in_array($type, ['credit','deposit','receive','received','refund','reversal'])) return true;
    return (float)($t['balance_after'] ?? 0) > (float)($t['balance_before'] ?? 0);
}

// Selected month
$sel_month = $_GET['month'] ?? date('Y-m');
$dt_from   = $sel_month.'-01';
$dt_to     = date('Y-m-t', strtotime($dt_from));

// All months with data
$months = [];
try {
    $s = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(created_at,'%Y-%m') AS ym, DATE_FORMAT(created_at,'%M %Y') AS label FROM transactions WHERE user_id=? ORDER BY ym DESC LIMIT 36");
    $s->execute([$user_id]); $months = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Fetch transactions for selected month
$txns = [];
try {
    $s = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? AND DATE(created_at) BETWEEN ? AND ? ORDER BY created_at ASC");
    $s->execute([$user_id, $dt_from, $dt_to]); $txns = $s->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}

// Monthly totals
$total_in    = 0;
$total_out   = 0;
$open_bal    = null;
$close_bal   = null;

foreach($txns as $t) {
    $amt = (float)$t['amount'];
    if(stmtIsCredit($t)) $total_in  += $amt;
    else                  $total_out += $amt;

    if($open_bal  === null && !empty($t['balance_before'])) $open_bal  = (float)$t['balance_before'];
    if(!empty($t['balance_after'])) $close_bal = (float)$t['balance_after'];
}

if($open_bal  === null) $open_bal  = $balance;
if($close_bal === null) $close_bal = $balance;

$month_label = empty($months) ? date('F Y', strtotime($dt_from)) : '';
foreach($months as $m) { if($m['ym']===$sel_month) { $month_label=$m['label']; break; } }

$extra_css = '
/* ============================================================
   STATEMENTS PAGE
============================================================ */
.stmt-layout { display:grid; grid-template-columns:240px 1fr; gap:22px; align-items:start; }
@media(max-width:900px){ .stmt-layout{ grid-template-columns:1fr; } }

/* Month List */
.month-list { background:#fff; border-radius:14px; border:1px solid var(--border); box-shadow:var(--shadow); overflow:hidden; }
.month-list-header { padding:14px 16px; border-bottom:1px solid var(--border); font-size:13px; font-weight:700; color:var(--navy); }
.month-item { display:flex; align-items:center; justify-content:space-between; padding:11px 16px; border-bottom:1px solid var(--border); text-decoration:none; color:var(--text); font-size:13px; font-weight:500; transition:all .15s; cursor:pointer; }
.month-item:last-child { border-bottom:none; }
.month-item:hover { background:#f0f4ff; }
.month-item.active { background:linear-gradient(90deg,#f0f4ff,#fff); border-left:3px solid var(--navy); font-weight:700; color:var(--navy); }
.month-item.active .mi-arrow { color:var(--navy); }
.mi-arrow { font-size:16px; color:var(--border); }
.mi-dot   { width:7px; height:7px; border-radius:50%; background:var(--border); }
.mi-dot.active { background:var(--navy); }

/* Statement Card */
.stmt-header {
    background:linear-gradient(135deg,var(--navy),#142d52,#1a4b8c);
    border-radius:14px; padding:24px; color:#fff; margin-bottom:20px;
    display:flex; flex-wrap:wrap; justify-content:space-between; gap:16px; align-items:flex-end;
}
.stmt-acct-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid rgba(255,255,255,.1); font-size:13px; }
.stmt-acct-row:last-child { border-bottom:none; }
.stmt-acct-lbl { color:rgba(255,255,255,.55); }
.stmt-acct-val { font-weight:700; color:#fff; font-family:monospace; }

/* Summary Grid */
.stmt-summary { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:12px; margin-bottom:20px; }
@media(max-width:700px){ .stmt-summary{ grid-template-columns:1fr 1fr; } }
.stmt-sum-card { background:#fff; border-radius:11px; border:1px solid var(--border); padding:14px; text-align:center; }
.stmt-sum-val  { font-size:17px; font-weight:800; margin-bottom:3px; }
.stmt-sum-lbl  { font-size:11px; color:var(--text-muted); }

/* Statement Table */
.stmt-table { width:100%; border-collapse:collapse; font-size:13px; }
.stmt-table th { background:var(--navy); color:#fff; padding:10px 12px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
.stmt-table th:last-child { text-align:right; }
.stmt-table td { padding:11px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
.stmt-table tr:last-child td { border-bottom:none; }
.stmt-table tr:hover td { background:#f8faff; }
.stmt-amt-credit { color:#059669; font-weight:800; font-size:14px; }
.stmt-amt-debit  { color:#dc2626; font-weight:800; font-size:14px; }
.stmt-amt-cell   { text-align:right; white-space:nowrap; }

/* Print Btn */
.print-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:9px; border:1.5px solid var(--border); background:#fff; font-size:12px; font-weight:600; cursor:pointer; color:var(--navy); transition:all .18s; text-decoration:none; }
.print-btn:hover { background:var(--navy); color:#fff; border-color:var(--navy); }

/* Empty */
.stmt-empty { text-align:center; padding:50px 20px; }

/* Print styles */
@media print {
    .customer-sidebar, .customer-topbar, .print-btn, .month-list,
    .page-title, .page-subtitle, nav, .card-title a { display:none!important; }
    .stmt-layout { grid-template-columns:1fr!important; }
    body { background:#fff!important; }
    .stmt-table th { background:#0c2340!important; -webkit-print-color-adjust:exact; color-adjust:exact; }
}

@media(max-width:600px){
    .stmt-table th:nth-child(4), .stmt-table td:nth-child(4) { display:none; }
    .stmt-summary { grid-template-columns:1fr 1fr; }
}
';

include 'layout/customer-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:6px;">
    <div>
        <div class="page-title" style="margin-bottom:2px;">Account Statements</div>
        <div class="page-subtitle">Monthly transaction history &amp; account summary</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="window.print()" class="print-btn">
            <i class="ri-printer-line"></i> Print / Save PDF
        </button>
        <a href="transactions" class="print-btn">
            <i class="ri-arrow-left-line"></i> All Transactions
        </a>
    </div>
</div>

<div class="stmt-layout">

    <!-- ── Month Selector ── -->
    <div>
        <div class="month-list">
            <div class="month-list-header"><i class="ri-calendar-line mr-1" style="color:var(--gold);"></i> Select Month</div>
            <?php if(empty($months)): ?>
                <div style="padding:20px;text-align:center;font-size:13px;color:var(--text-muted);">No statement data yet.</div>
            <?php else: foreach($months as $m): $is_active = $m['ym']===$sel_month; ?>
            <a href="statements?month=<?php echo urlencode($m['ym']); ?>"
               class="month-item <?php echo $is_active?'active':''; ?>">
                <div style="display:flex;align-items:center;gap:8px;">
                    <div class="mi-dot <?php echo $is_active?'active':''; ?>"></div>
                    <span><?php echo htmlspecialchars($m['label']); ?></span>
                </div>
                <i class="ri-arrow-right-s-line mi-arrow"></i>
            </a>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- ── Statement Main ── -->
    <div>
        <!-- Statement Header -->
        <div class="stmt-header" id="stmtPrintHeader">
            <div style="flex:1;min-width:0;">
                <div style="font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Statement Period</div>
                <div style="font-size:22px;font-weight:800;margin-bottom:14px;"><?php echo htmlspecialchars($month_label); ?></div>

                <div style="background:rgba(255,255,255,.07);border-radius:10px;padding:14px 16px;">
                    <div class="stmt-acct-row">
                        <span class="stmt-acct-lbl">Account Holder</span>
                        <span class="stmt-acct-val"><?php echo htmlspecialchars($acc_name); ?></span>
                    </div>
                    <div class="stmt-acct-row">
                        <span class="stmt-acct-lbl">Account Number</span>
                        <span class="stmt-acct-val"><?php echo htmlspecialchars($acc_no); ?></span>
                    </div>
                    <div class="stmt-acct-row">
                        <span class="stmt-acct-lbl">Routing Number</span>
                        <span class="stmt-acct-val"><?php echo htmlspecialchars($routing); ?></span>
                    </div>
                    <div class="stmt-acct-row">
                        <span class="stmt-acct-lbl">Statement Date</span>
                        <span class="stmt-acct-val"><?php echo date('d M Y'); ?></span>
                    </div>
                </div>
            </div>
            <div style="text-align:right;min-width:160px;">
                <div style="font-size:10px;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Current Balance</div>
                <div style="font-size:28px;font-weight:800;color:var(--gold);"><?php echo formatMoney($balance); ?></div>
                <div style="font-size:11px;color:rgba(255,255,255,.4);margin-top:4px;">MIDWESTERNBANK, N.A.</div>
                <div style="font-size:10px;color:rgba(255,255,255,.35);margin-top:2px;"><?php echo BANK_SWIFT; ?></div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="stmt-summary">
            <div class="stmt-sum-card">
                <div class="stmt-sum-val"><?php echo count($txns); ?></div>
                <div class="stmt-sum-lbl">Transactions</div>
            </div>
            <div class="stmt-sum-card" style="border-color:#bbf7d0;">
                <div class="stmt-sum-val" style="color:#059669;">+<?php echo formatMoney($total_in); ?></div>
                <div class="stmt-sum-lbl">Total Money In</div>
            </div>
            <div class="stmt-sum-card" style="border-color:#fecaca;">
                <div class="stmt-sum-val" style="color:#dc2626;">−<?php echo formatMoney($total_out); ?></div>
                <div class="stmt-sum-lbl">Total Money Out</div>
            </div>
            <div class="stmt-sum-card" style="border-color:#c8b6ff;">
                <div class="stmt-sum-val" style="color:var(--navy);"><?php echo formatMoney($close_bal); ?></div>
                <div class="stmt-sum-lbl">Closing Balance</div>
            </div>
        </div>

        <!-- ── Transaction Table ── -->
        <div class="card" style="padding:0;overflow:hidden;">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div style="font-size:14px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:7px;">
                    <i class="ri-file-list-3-line" style="color:var(--gold);"></i>
                    Transactions — <?php echo htmlspecialchars($month_label); ?>
                </div>
                <span style="font-size:12px;color:var(--text-muted);"><?php echo count($txns); ?> records</span>
            </div>

            <?php if(empty($txns)): ?>
            <div class="stmt-empty">
                <div style="font-size:48px;margin-bottom:12px;opacity:.25;">📋</div>
                <div style="font-size:15px;font-weight:700;color:var(--navy);margin-bottom:8px;">No Transactions</div>
                <p style="font-size:13px;color:var(--text-muted);">There are no transactions for <?php echo htmlspecialchars($month_label); ?>.</p>
                <?php if(!empty($months)): ?>
                <a href="statements?month=<?php echo urlencode($months[0]['ym']); ?>" class="btn btn-secondary" style="height:38px;margin-top:12px;font-size:13px;">
                    View Latest Month
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="stmt-table">
                    <thead>
                        <tr>
                            <th style="width:130px;">Date &amp; Time</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th style="text-align:right;">Debit (−)</th>
                            <th style="text-align:right;">Credit (+)</th>
                            <th style="text-align:right;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance Row -->
                        <tr style="background:#f8faff;">
                            <td style="color:var(--text-muted);font-size:11px;">
                                <?php echo date('d M Y', strtotime($dt_from)); ?>
                            </td>
                            <td colspan="3" style="font-weight:700;color:var(--navy);">Opening Balance</td>
                            <td class="stmt-amt-cell">—</td>
                            <td class="stmt-amt-cell">—</td>
                            <td class="stmt-amt-cell">
                                <strong style="color:var(--navy);"><?php echo formatMoney($open_bal); ?></strong>
                            </td>
                        </tr>

                        <?php foreach($txns as $t):
                            $is_crd  = stmtIsCredit($t);
                            $amount  = (float)$t['amount'];
                            $bal_aft = (float)($t['balance_after'] ?? 0);
                            $desc    = trim($t['description'] ?? '') ?: ucfirst($t['type']??'Transaction');
                            $status  = $t['status'] ?? 'completed';
                            $type_lbl= match(strtolower($t['type']??'')) {
                                'credit','receive','received' => 'Credit',
                                'deposit'                     => 'Deposit',
                                'refund'                      => 'Refund',
                                'reversal'                    => 'Reversal',
                                'transfer'                    => 'Transfer',
                                'withdrawal'                  => 'Withdrawal',
                                'debit'                       => 'Debit',
                                default                       => ucfirst($t['type']??''),
                            };
                            $row_bg = match($status) {
                                'pending'  => 'background:#fffdf5;',
                                'failed'   => 'background:#fff5f5;',
                                'reversed' => 'background:#faf5ff;',
                                default    => '',
                            };
                        ?>
                        <tr style="<?php echo $row_bg; ?>">
                            <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                                <?php echo date('d M Y', strtotime($t['created_at'])); ?><br>
                                <span style="font-size:10px;"><?php echo date('H:i', strtotime($t['created_at'])); ?></span>
                            </td>
                            <td>
                                <div style="font-weight:600;color:var(--navy);font-size:13px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?php echo htmlspecialchars($desc); ?>
                                </div>
                                <?php if(!empty($t['recipient_name'])): ?>
                                <div style="font-size:11px;color:var(--text-muted);">
                                    <?php echo $is_crd?'From: ':'To: '; echo htmlspecialchars($t['recipient_name']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if($status!=='completed'): ?>
                                <span style="font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;background:<?php echo $status==='pending'?'#fef3c7':($status==='failed'?'#fee2e2':'#ede9fe'); ?>;color:<?php echo $status==='pending'?'#d97706':($status==='failed'?'#dc2626':'#7c3aed'); ?>;">
                                    <?php echo strtoupper($status); ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-family:monospace;font-size:10px;color:var(--gold);">
                                    <?php echo htmlspecialchars($t['reference'] ?? '—'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size:11px;font-weight:700;background:<?php echo $is_crd?'#d1fae5':'#fee2e2'; ?>;color:<?php echo $is_crd?'#059669':'#dc2626'; ?>;padding:2px 9px;border-radius:5px;">
                                    <?php echo $type_lbl; ?>
                                </span>
                            </td>
                            <!-- Debit column: show amount if outgoing, else dash -->
                            <td class="stmt-amt-cell">
                                <?php if(!$is_crd): ?>
                                    <span class="stmt-amt-debit">−<?php echo formatMoney($amount); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- Credit column: show amount if incoming, else dash -->
                            <td class="stmt-amt-cell">
                                <?php if($is_crd): ?>
                                    <span class="stmt-amt-credit">+<?php echo formatMoney($amount); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <!-- Balance -->
                            <td class="stmt-amt-cell">
                                <?php if($bal_aft > 0): ?>
                                    <strong style="color:var(--navy);"><?php echo formatMoney($bal_aft); ?></strong>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Closing Balance Row -->
                        <tr style="background:#f0fff4;">
                            <td style="color:var(--text-muted);font-size:11px;">
                                <?php echo date('d M Y', strtotime($dt_to)); ?>
                            </td>
                            <td colspan="3" style="font-weight:800;color:#059669;">Closing Balance</td>
                            <td class="stmt-amt-cell">
                                <span style="color:#dc2626;font-weight:700;">−<?php echo formatMoney($total_out); ?></span>
                            </td>
                            <td class="stmt-amt-cell">
                                <span style="color:#059669;font-weight:700;">+<?php echo formatMoney($total_in); ?></span>
                            </td>
                            <td class="stmt-amt-cell">
                                <strong style="font-size:15px;color:#059669;"><?php echo formatMoney($close_bal); ?></strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Disclaimer -->
        <div style="margin-top:16px;padding:12px 16px;background:#f8faff;border-radius:10px;font-size:11px;color:var(--text-muted);line-height:1.7;border:1px solid var(--border);">
            <strong style="color:var(--navy);">MidwesternBank, N.A.</strong> ·
            <?php echo BANK_ADDRESS; ?> ·
            Member FDIC · SWIFT: <?php echo BANK_SWIFT; ?><br>
            This statement is for informational purposes. For disputes, contact
            <a href="mailto:support@midwesternbank.com" style="color:var(--gold);">support@midwesternbank.com</a>
            within 60 days.
        </div>
    </div>

</div><!-- /.stmt-layout -->

<?php
$extra_js = "
// Auto-select current month if nothing selected
window.addEventListener('DOMContentLoaded', () => {
    const active = document.querySelector('.month-item.active');
    if(active) active.scrollIntoView({block:'center', behavior:'smooth'});
});
";
include 'layout/customer-footer.php';
?>