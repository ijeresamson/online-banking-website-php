<?php
// ============================================================
// MIDWESTERNBANK — CONSTANTS
// ============================================================
if(!defined('BANK_NAME'))     define('BANK_NAME',     'MidwesternBank, N.A.');
if(!defined('BANK_SWIFT'))    define('BANK_SWIFT',    'MWBKUS44XXX');
if(!defined('BANK_ADDRESS'))  define('BANK_ADDRESS',  '200 Midwestern Plaza, Chicago, IL 60601, USA');
if(!defined('BANK_CURRENCY')) define('BANK_CURRENCY', 'USD');
if(!defined('BANK_COUNTRY'))  define('BANK_COUNTRY',  'United States');

// ============================================================
// MIDWESTERNBANK SVG LOGO
// ============================================================
function mwLogo($size = 48) {
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
        <path d="M24 3 L43 11 L43 27 Q43 39 24 45 Q5 39 5 27 L5 11 Z" fill="#0c2340"/>
        <path d="M24 6 L40 13 L40 27 Q40 37.5 24 43 Q8 37.5 8 27 L8 13 Z" fill="none" stroke="#c59b3d" stroke-width="1.5"/>
        <rect x="13" y="22" width="3"  height="12" fill="#c59b3d" rx="0.5"/>
        <rect x="18" y="19" width="3"  height="15" fill="#c59b3d" rx="0.5"/>
        <rect x="23" y="22" width="3"  height="12" fill="#c59b3d" rx="0.5"/>
        <rect x="28" y="19" width="3"  height="15" fill="#c59b3d" rx="0.5"/>
        <rect x="33" y="22" width="3"  height="12" fill="#c59b3d" rx="0.5"/>
        <rect x="12" y="34" width="25" height="2" fill="#c59b3d" rx="1"/>
        <rect x="12" y="18" width="25" height="2" fill="#c59b3d" rx="1"/>
        <polygon points="24,10 37,18 11,18" fill="#c59b3d"/>
        <rect x="21" y="28" width="7" height="8" fill="#0c2340" rx="1"/>
    </svg>';
}

// ============================================================
// ALL EXISTING HELPER FUNCTIONS BELOW (unchanged)
// ============================================================

function createAccountForUser($pdo, $user_id, $first_name, $last_name, $account_type = 'Checking') {
    do {
        $routing_number = str_pad((string)mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT id FROM accounts WHERE routing_number = ? LIMIT 1");
        $chk->execute([$routing_number]);
    } while($chk->fetch(PDO::FETCH_ASSOC));

    do {
        $wire_routing = str_pad((string)mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $chk2 = $pdo->prepare("SELECT id FROM accounts WHERE wire_routing = ? LIMIT 1");
        $chk2->execute([$wire_routing]);
    } while($chk2->fetch(PDO::FETCH_ASSOC));

    $account_name = strtoupper(trim($first_name . ' ' . $last_name));

    $stmt = $pdo->prepare("INSERT INTO accounts (user_id, balance, account_name, account_type, currency,
            routing_number, wire_routing, swift_code, bank_name, bank_country, bank_address, status, created_at) 
            VALUES (?, 0.00, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW()) ");
    $stmt->execute([
        $user_id, $account_name, $account_type,
        BANK_CURRENCY, $routing_number, $wire_routing,
        BANK_SWIFT, BANK_NAME, BANK_COUNTRY, BANK_ADDRESS,
    ]);
    return true;
}

function getBalance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT balance FROM accounts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $bal = $stmt->fetchColumn();
    return $bal !== false ? (float)$bal : 0.00;
}

function getAccountFull($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name, u.email,
               u.account_number, u.status AS user_status,
               u.phone, u.nationality, u.employment_status,
               u.source_of_funds, u.id_type, u.date_of_birth,
               u.last_login, u.created_at AS registered_at
        FROM accounts a
        JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ? LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $acc = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$acc) {
        $u = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $u->execute([$user_id]);
        $u = $u->fetch(PDO::FETCH_ASSOC);
        if($u) {
            createAccountForUser($pdo, $user_id, $u['first_name'], $u['last_name']);
            $stmt->execute([$user_id]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    return $acc;
}

function updateBalance($pdo, $user_id, $new_balance) {
    $pdo->prepare("UPDATE accounts SET balance = ? WHERE user_id = ?")->execute([(float)$new_balance, $user_id]);
}

function generateRef($prefix = 'TXN') {
    return $prefix . date('Ymd') . strtoupper(bin2hex(random_bytes(4)));
}

function recordTransaction($pdo, $user_id, $type, $amount, $balance_before, $balance_after,
    $description, $reference, $status = 'completed',
    $recipient_name = null, $recipient_account = null, $sender_account = null) {
    $pdo->prepare("INSERT INTO transactions (
            user_id, reference, type, amount, balance_before, balance_after,
            description, status, recipient_name, recipient_account, sender_account, created_at ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$user_id, $reference, $type, (float)$amount, (float)$balance_before,
        (float)$balance_after, $description, $status, $recipient_name, $recipient_account, $sender_account]);
}

function formatMoney($amount) {
    return '$' . number_format((float)$amount, 2);
}

function isCredit($type) {
    return in_array($type, ['deposit', 'received'], true);
}

function txnConfig($type) {
    $map = [
        'deposit'    => ['icon'=>'ri-arrow-down-circle-fill', 'color'=>'#059669','bg'=>'#d1fae5','label'=>'Deposit',   'sign'=>'+'],
        'withdrawal' => ['icon'=>'ri-arrow-up-circle-fill',   'color'=>'#dc2626','bg'=>'#fee2e2','label'=>'Withdrawal','sign'=>'-'],
        'transfer'   => ['icon'=>'ri-send-plane-fill',        'color'=>'#0c2340','bg'=>'#dbeafe','label'=>'Transfer',  'sign'=>'-'],
        'received'   => ['icon'=>'ri-arrow-down-circle-fill', 'color'=>'#059669','bg'=>'#d1fae5','label'=>'Received',  'sign'=>'+'],
        'payment'    => ['icon'=>'ri-bill-fill',               'color'=>'#c59b3d','bg'=>'#fef9e7','label'=>'Payment',  'sign'=>'-'],
    ];
    return $map[$type] ?? ['icon'=>'ri-exchange-fill','color'=>'#6b7280','bg'=>'#f3f4f6','label'=>ucfirst($type),'sign'=>''];
}