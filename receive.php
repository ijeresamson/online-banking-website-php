<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';
$page_title = 'Receive Money — MidwesternBank';

require_once "front_extra_css/extra_receive.php";
include 'layout/customer-header.php';

$acc = getAccountFull($pdo, $user_id);

$account_name   = $acc['account_name']   ?? strtoupper($full_name);
$account_number = $user['account_number'] ?? ($acc['account_number'] ?? '—');
$account_type   = ($acc['account_type']  ?? 'Checking') . ' Account';
$currency       = ($acc['currency']      ?? 'USD') . ' — US Dollar';
$routing        = $acc['routing_number'] ?? '—';
$wire_routing   = $acc['wire_routing']   ?? '—';
$swift          = $acc['swift_code']     ?? BANK_SWIFT;
$bank_name_v    = $acc['bank_name']      ?? BANK_NAME;
$bank_address_v = $acc['bank_address']   ?? BANK_ADDRESS;
$balance        = getBalance($pdo, $user_id);

$received = [];
try {
    $s = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? AND type='deposit' AND status='completed' ORDER BY created_at DESC LIMIT 5");
    $s->execute([$user_id]);
    $received = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Receive page transaction query failed: ' . $e->getMessage());
}
?>

<div class="page-title">Receive Money</div>
<div class="page-subtitle">Share your banking details with someone who needs to send you money</div>

<div class="receive-grid">
    <div>
        <div class="bank-slip">
            <div class="slip-header">
                <div class="slip-logo"><i class="ri-bank-line"></i></div>
                <div>
                    <div class="slip-bank"><?php echo htmlspecialchars((string)$bank_name_v, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="slip-sub">Official Account Details · <?php echo date('d M Y'); ?></div>
                </div>
            </div>

            <div class="slip-body">
                <?php
                $rows = [
                    ['Bank Name',      $bank_name_v,    '',      false],
                    ['Account Name',   $account_name,   '',      false],
                    ['Account Number', $account_number, 'hi',    false],
                    ['Account Type',   $account_type,   '',      false],
                    ['Currency',       $currency,       '',      false],
                    ['Routing Number', $routing,        'mono',  false],
                    ['Wire Routing',   $wire_routing,   'mono',  false],
                    ['SWIFT / BIC',    $swift,          'swift', false],
                    ['Bank Address',   $bank_address_v, '',      true],
                ];
                foreach ($rows as [$lbl, $val, $cls, $small]):
                ?>
                    <div class="slip-row">
                        <span class="slip-lbl"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="slip-val <?php echo htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'); ?>" 
                        style="<?php echo $small ? 'font-size:11px;opacity:.85;max-width:220px;line-height:1.5;' : ''; ?>">
                            <?php echo htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="slip-footer">
                <button type="button" class="slip-btn" id="copyAllBtn" onclick="copyAll()">
                    <i class="ri-file-copy-2-line"></i><span>Copy All</span>
                </button>
                <button type="button" class="slip-btn" onclick="shareWhatsApp()">
                    <i class="ri-whatsapp-line"></i><span>WhatsApp</span>
                </button>
                <button type="button" class="slip-btn" onclick="shareEmail()">
                    <i class="ri-mail-send-line"></i><span>Email</span>
                </button>
            </div>
        </div>

        <div class="qc-grid">
            <?php
            $tiles = [
                ['Account No.',  $account_number, 'acc'],
                ['Routing',      $routing,         'rtn'],
                ['Wire Routing', $wire_routing,    'wrt'],
                ['SWIFT/BIC',    $swift,           'swf'],
                ['Acct Name',    $account_name,    'nam'],
                ['Acct Type',    $account_type,    'typ'],
            ];
            foreach ($tiles as [$lbl, $val, $id]):
            ?>
                <div class="qc-tile" role="button" tabindex="0"
                     onclick="copyField(<?php echo htmlspecialchars(json_encode((string)$val), ENT_QUOTES, 'UTF-8'); ?>, 'qc_<?php echo $id; ?>')"
                     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();
                     copyField(<?php echo htmlspecialchars(json_encode((string)$val), ENT_QUOTES, 'UTF-8'); ?>, 'qc_<?php echo $id; ?>');}">
                    <div class="qc-lbl"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="qc-val"><?php echo htmlspecialchars(strlen((string)$val) > 15 ? substr((string)$val, 0, 14) . '…' : (string)$val, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="qc-copy" id="qc_<?php echo $id; ?>"><i class="ri-file-copy-line"></i> Copy</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-title"><i class="ri-information-line"></i> How to Use These Details</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div style="background:#d1fae5;border-radius:10px;padding:14px;">
                    <div style="font-size:12px;font-weight:700;color:#059669;margin-bottom:8px;">🇺🇸 US Domestic Transfer</div>
                    <div style="font-size:12px;color:#065f46;line-height:1.6;">Account Name<br>Account Number<br><strong>Routing Number</strong></div>
                </div>
                <div style="background:#dbeafe;border-radius:10px;padding:14px;">
                    <div style="font-size:12px;font-weight:700;color:#2563eb;margin-bottom:8px;">🌐 International Wire</div>
                    <div style="font-size:12px;color:#1e40af;line-height:1.6;">Account Name<br>Account Number<br><strong>SWIFT/BIC + Wire Routing</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="background:linear-gradient(135deg,#059669,#047857);color:#fff;margin-bottom:16px;">
            <div style="font-size:11px;opacity:.8;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;">Available Balance</div>
            <div style="font-size:36px;font-weight:700;"><?php echo formatMoney($balance); ?></div>
            <div style="font-size:13px;opacity:.8;margin-top:4px;"><?php echo htmlspecialchars((string)$account_number, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="card">
            <div class="card-title"><i class="ri-share-forward-line"></i> Share My Details</div>

            <div class="share-item" role="button" tabindex="0" onclick="shareWhatsApp()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();shareWhatsApp();}">
                <div class="share-icon" style="background:#d1fae5;color:#16a34a;"><i class="ri-whatsapp-line"></i></div>
                <div style="flex:1;"><div style="font-size:14px;font-weight:600;">Share via WhatsApp</div>
                <div style="font-size:12px;color:var(--text-muted);">Open WhatsApp with your payment details</div></div>
                <i class="ri-arrow-right-s-line" style="color:var(--text-muted);"></i>
            </div>

            <div class="share-item" role="button" tabindex="0" onclick="shareEmail()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();shareEmail();}">
                <div class="share-icon" style="background:#dbeafe;color:#2563eb;"><i class="ri-mail-send-line"></i></div>
                <div style="flex:1;"><div style="font-size:14px;font-weight:600;">Share via Email</div>
                <div style="font-size:12px;color:var(--text-muted);">Open your email app with the details</div></div>
                <i class="ri-arrow-right-s-line" style="color:var(--text-muted);"></i>
            </div>

            <div class="share-item" role="button" tabindex="0" onclick="shareSMS()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();shareSMS();}">
                <div class="share-icon" style="background:#fef3c7;color:#d97706;"><i class="ri-message-2-line"></i></div>
                <div style="flex:1;"><div style="font-size:14px;font-weight:600;">Share via SMS</div>
                <div style="font-size:12px;color:var(--text-muted);">Open your messaging app with a short message</div></div>
                <i class="ri-arrow-right-s-line" style="color:var(--text-muted);"></i>
            </div>

            <div class="share-item" role="button" tabindex="0" onclick="copyAll()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();copyAll();}">
                <div class="share-icon" style="background:#ede9fe;color:#7c3aed;"><i class="ri-clipboard-line"></i></div>
                <div style="flex:1;"><div style="font-size:14px;font-weight:600;">Copy to Clipboard</div>
                <div style="font-size:12px;color:var(--text-muted);">Copy all details as formatted text</div></div>
                <i class="ri-arrow-right-s-line" style="color:var(--text-muted);"></i>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="ri-arrow-down-circle-line" style="color:var(--green);"></i> Recently Received</div>
            <?php if (empty($received)): ?>
                <div style="text-align:center;padding:24px;color:var(--text-muted);">
                    <i class="ri-inbox-line" style="font-size:36px;display:block;margin-bottom:8px;color:var(--border);"></i>
                    <p>No received payments yet. Share your details above!</p>
                </div>
            <?php else: ?>
                <?php foreach ($received as $r): ?>
                    <div class="recv-item">
                        <div style="width:38px;height:38px;background:#d1fae5;color:#059669;border-radius:10px;display:flex;
                        align-items:center;justify-content:center;font-size:17px;flex-shrink:0;"><i class="ri-arrow-down-line"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($r['description'] ?? 'Money Received', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div style="font-size:11px;color:var(--text-muted);">
                                <?php echo !empty($r['created_at']) ? htmlspecialchars(date('d M Y · h:i A', strtotime($r['created_at'])), ENT_QUOTES, 'UTF-8') : '—'; ?>
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:16px;font-weight:700;color:#059669;">+<?php echo formatMoney($r['amount'] ?? 0); ?></div>
                            <div style="font-size:10px;background:#d1fae5;color:#059669;padding:2px 7px;border-radius:4px;font-weight:600;margin-top:2px;">✓ Received</div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="transactions?type=deposit" class="btn btn-secondary btn-block" style="margin-top:12px;height:36px;font-size:12px;">View All Received Payments</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$js_data = [
    'name' => (string)$account_name,
    'num'  => (string)$account_number,
    'rtn'  => (string)$routing,
    'wire' => (string)$wire_routing,
    'swift'=> (string)$swift,
    'bank' => (string)$bank_name_v,
    'type' => (string)$account_type,
    'curr' => (string)$currency,
    'addr' => (string)$bank_address_v,
];

$js_json = json_encode($js_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$extra_js = <<<JS
const D = {$js_json};

const FULL = [
    'BANK PAYMENT DETAILS',
    '─────────────────────────────────────',
    'Bank Name:       ' + D.bank,
    'Account Name:    ' + D.name,
    'Account Number:  ' + D.num,
    'Account Type:    ' + D.type,
    'Currency:        ' + D.curr,
    'Routing Number:  ' + D.rtn,
    'Wire Routing:    ' + D.wire,
    'SWIFT / BIC:     ' + D.swift,
    'Bank Address:    ' + D.addr,
    '─────────────────────────────────────'
].join('\\n');

function fallbackCopy(text) {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.left = '-9999px';
    document.body.appendChild(area);
    area.select();
    area.setSelectionRange(0, area.value.length);

    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    area.remove();
    return ok;
}

async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (e) {}
    }
    return fallbackCopy(text);
}

async function copyAll() {
    const btn = document.getElementById('copyAllBtn');
    if (btn) btn.disabled = true;

    const ok = await copyText(FULL);
    showToast(ok ? 'All banking details copied!' : 'Could not copy automatically. Please select and copy the details manually.');

    if (btn) {
        const original = btn.innerHTML;
        if (ok) {
            btn.innerHTML = '<i class="ri-check-line"></i><span>Copied!</span>';
            setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 1800);
        } else {
            btn.disabled = false;
        }
    }
}

async function copyField(val, elId) {
    const ok = await copyText(String(val));
    const el = document.getElementById(elId);

    if (el && ok) {
        const previous = el.innerHTML;
        el.innerHTML = '✓ Copied!';
        el.classList.add('copy-ok');
        setTimeout(() => {
            el.innerHTML = previous;
            el.classList.remove('copy-ok');
        }, 1800);
    }

    showToast(ok ? 'Copied to clipboard!' : 'Could not copy automatically.');
}

function shareWhatsApp() {
    const text = 'Please use these bank details to send the payment:\\n\\n' + FULL;
    const url = 'https://wa.me/?text=' + encodeURIComponent(text);
    window.open(url, '_blank', 'noopener,noreferrer');
}

function shareEmail() {
    const subject = 'Bank Details — ' + D.name;
    const body = 'Hello,\\n\\nPlease use the following bank details for the payment:\\n\\n' + FULL + '\\n\\nThank you.';
    window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
}

function shareSMS() {
    const body = 'Bank: ' + D.bank + '\\nAccount Name: ' + D.name + '\\nAccount Number: ' + D.num + '\\nRouting: ' + D.rtn;
    window.location.href = 'sms:?body=' + encodeURIComponent(body);
}

async function nativeShare() {
    if (!navigator.share) {
        await copyAll();
        return;
    }

    try {
        await navigator.share({
            title: 'My Bank Details',
            text: FULL
        });
    } catch (e) {
        if (e.name !== 'AbortError') showToast('Sharing was not available.');
    }
}

function showToast(msg) {
    const old = document.getElementById('receiveToast');
    if (old) old.remove();

    const d = document.createElement('div');
    d.id = 'receiveToast';
    d.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:600;background:#d1fae5;color:#059669;box-shadow:0 4px 16px rgba(0,0,0,.15);max-width:360px;';
    d.textContent = '✓ ' + msg;
    document.body.appendChild(d);

    setTimeout(() => {
        d.style.opacity = '0';
        d.style.transition = 'opacity .3s';
        setTimeout(() => d.remove(), 300);
    }, 3000);
}
JS;

include 'layout/customer-footer.php';
?>