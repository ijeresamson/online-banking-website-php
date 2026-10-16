<?php
/**
 * welcome-email.php — MidwesternBank Welcome Email
 *
 * Included by register.php immediately after account creation.
 *
 * Variables available from parent scope:
 *   $pdo          — PDO connection
 *   $new_user_id  — int, newly created user ID
 *   $account_number — string, generated account number
 *
 * CRITICAL: Do NOT add "use PHPMailer\PHPMailer\Exception"
 * It shadows \Exception and breaks catch blocks.
 * Always use \Exception with backslash in catch.
 */


// to diagnose SMTP issues. REMOVE after fixing.

error_log('[welcome-email] SMTP_HOST='     . (defined('SMTP_HOST')     ? SMTP_HOST     : 'NOT DEFINED'));
error_log('[welcome-email] SMTP_PORT='     . (defined('SMTP_PORT')     ? SMTP_PORT     : 'NOT DEFINED'));
error_log('[welcome-email] SMTP_USERNAME=' . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NOT DEFINED'));
error_log('[welcome-email] SMTP_FROM='     . (defined('SMTP_FROM')     ? SMTP_FROM     : 'NOT DEFINED'));
error_log('[welcome-email] new_user_id='   . ($new_user_id ?? 'NOT SET'));

// ============================================================
// PREVENT DIRECT ACCESS
// ============================================================
if(!defined('BANK_NAME')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// ============================================================
// VALIDATE REQUIRED VARIABLES
// ============================================================
if(empty($new_user_id)) {
    error_log('[welcome-email] ERROR: $new_user_id is not set or empty.');
    return;
}

// ============================================================
// FETCH FRESH USER + ACCOUNT DATA FROM DATABASE
// ============================================================
try {
    $stmt = $pdo->prepare("SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.account_number,
            u.phone,
            u.nationality,
            u.employment_status,
            u.created_at,
            COALESCE(a.account_name,
                CONCAT(UPPER(u.first_name), ' ', UPPER(u.last_name))
            )                              AS account_name,
            COALESCE(a.account_type, 'Checking')  AS account_type,
            COALESCE(a.currency, 'USD')            AS currency,
            COALESCE(a.balance, '0.00')            AS balance,
            COALESCE(a.routing_number, '')         AS routing_number,
            COALESCE(a.wire_routing, '')           AS wire_routing,
            COALESCE(a.swift_code, ?)              AS swift_code,
            COALESCE(a.bank_name, ?)               AS bank_name,
            COALESCE(a.bank_address, ?)            AS bank_address,
            COALESCE(a.bank_country, 'United States') AS bank_country
        FROM users u
        LEFT JOIN accounts a ON u.id = a.user_id
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->execute([
        defined('BANK_SWIFT')   ? BANK_SWIFT   : 'MWBKUS44XXX',
        defined('BANK_NAME')    ? BANK_NAME    : 'MidwesternBank, N.A.',
        defined('BANK_ADDRESS') ? BANK_ADDRESS : '200 Midwestern Plaza, Chicago, IL 60601, USA',
        (int)$new_user_id,
    ]);
    $wu = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$wu || empty($wu['email'])) {
        error_log("[welcome-email] ERROR: Could not fetch user data for user_id={$new_user_id}");
        return;
    }

} catch(\PDOException $e) {
    error_log('[welcome-email] DB ERROR: '.$e->getMessage());
    return;
}

// ============================================================
// PREPARE TEMPLATE VARIABLES
// ============================================================
$first_name   = htmlspecialchars($wu['first_name'],    ENT_QUOTES, 'UTF-8');
$last_name    = htmlspecialchars($wu['last_name'],     ENT_QUOTES, 'UTF-8');
$email        = htmlspecialchars($wu['email'],         ENT_QUOTES, 'UTF-8');
$acct_no      = htmlspecialchars($wu['account_number'],ENT_QUOTES, 'UTF-8');
$acct_name    = htmlspecialchars($wu['account_name'],  ENT_QUOTES, 'UTF-8');
$acct_type    = htmlspecialchars($wu['account_type'],  ENT_QUOTES, 'UTF-8');
$currency     = htmlspecialchars($wu['currency'],      ENT_QUOTES, 'UTF-8');
$routing      = !empty($wu['routing_number']) ? htmlspecialchars($wu['routing_number']) : 'Pending';
$wire         = !empty($wu['wire_routing'])   ? htmlspecialchars($wu['wire_routing'])   : 'Pending';
$swift        = !empty($wu['swift_code'])     ? htmlspecialchars($wu['swift_code'])     : 'MWBKUS44XXX';
$bank_nm      = htmlspecialchars($wu['bank_name']);
$bank_addr    = htmlspecialchars($wu['bank_address']);
$open_date    = date('d F Y', strtotime($wu['created_at']));
$year         = date('Y');
$login_url    = (defined('BASE_URL') ? BASE_URL : 'http://localhost/My_project/online-bank/').'login';
$support_url  = (defined('BASE_URL') ? BASE_URL : 'http://localhost/My_project/online-bank/').'support';

// ============================================================
// BUILD HTML EMAIL BODY
// ============================================================
$html_body = '
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<title>Welcome to MidwesternBank</title>
<!--[if mso]>
<noscript>
<xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
</noscript>
<![endif]-->
<style>
  body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
  body{margin:0;padding:0;background-color:#f4f7fc;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;}
  table{border-spacing:0;}
  img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;}
  .mono{font-family:\'Courier New\',Courier,monospace;}
  @media only screen and (max-width:600px){
    .email-card{width:100%!important;border-radius:0!important;}
    .content-pad{padding:24px 20px!important;}
    .header-pad{padding:28px 20px!important;}
    .acct-no{font-size:18px!important;letter-spacing:2px!important;}
    .grid-cell{display:block!important;width:100%!important;}
    .grid-cell table{width:100%!important;margin-bottom:8px!important;}
  }
</style>
</head>
<body>

<!-- PRE-HEADER (hidden preview text) -->
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
  Welcome to MidwesternBank, '.$first_name.'! Your Checking Account is ready. Account No: '.$acct_no.'.
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="background-color:#f4f7fc;padding:40px 16px;">
<tr>
<td align="center">

<!-- ═══════════════════════════════════════ EMAIL CARD ═══════════════════════════════════════ -->
<table role="presentation" class="email-card" width="600" cellpadding="0" cellspacing="0" border="0"
       style="background-color:#ffffff;border-radius:16px;overflow:hidden;
              box-shadow:0 8px 40px rgba(12,35,64,0.14);">

<!-- ═══ HEADER ═══ -->
<tr>
<td class="header-pad"
    style="background:linear-gradient(135deg,#0c2340 0%,#142d52 55%,#1a4b8c 100%);
           padding:36px 44px;text-align:center;">

  <!-- Logo Text -->
  <table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0">
  <tr>
  <td style="background:rgba(255,255,255,0.07);border:1.5px solid rgba(197,155,61,0.35);
              border-radius:12px;padding:10px 20px;display:inline-block;">
    <span style="font-size:26px;font-weight:800;color:#ffffff;
                 letter-spacing:-0.5px;font-family:\'Segoe UI\',sans-serif;
                 text-decoration:none;">
      Midwestern<span style="color:#c59b3d;">Bank</span>
    </span>
  </td>
  </tr>
  </table>

  <!-- Badge -->
  <div style="margin:16px 0 12px;">
    <span style="display:inline-block;background:rgba(197,155,61,0.15);
                 border:1px solid rgba(197,155,61,0.35);border-radius:20px;
                 padding:5px 18px;">
      <span style="font-size:11px;font-weight:700;color:#c59b3d;
                   letter-spacing:1.5px;text-transform:uppercase;">
        🎉 Account Successfully Created
      </span>
    </span>
  </div>

  <h1 style="font-size:28px;font-weight:800;color:#ffffff;margin:0 0 10px;line-height:1.25;">
    Welcome, '.$first_name.'!
  </h1>
  <p style="font-size:15px;color:rgba(255,255,255,0.7);margin:0;line-height:1.7;">
    Your MidwesternBank Checking Account is now active.<br>
    Your banking details are below — keep them safe.
  </p>

</td>
</tr>

<!-- Gold Accent Line -->
<tr>
<td style="background:linear-gradient(90deg,#c59b3d,#d4a843,#c59b3d);height:4px;padding:0;line-height:0;font-size:0;">&nbsp;</td>
</tr>

<!-- ═══ MAIN BODY ═══ -->
<tr>
<td class="content-pad" style="padding:36px 44px;background:#ffffff;">

  <!-- Greeting -->
  <p style="font-size:15px;color:#475569;line-height:1.85;margin:0 0 28px;">
    Dear <strong style="color:#0c2340;">'.$first_name.' '.$last_name.'</strong>,<br><br>
    Congratulations and welcome to <strong style="color:#0c2340;">MidwesternBank, N.A.</strong>
    Your email has been verified and your Checking Account has been created.
    Below are your complete banking details. Please save or print this email and keep it secure.
  </p>

  <!-- ─────────────── ACCOUNT DETAILS CARD ─────────────── -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
         style="margin-bottom:28px;">
  <tr>
  <td style="background:linear-gradient(135deg,#0c2340 0%,#142d52 50%,#1e3a5f 100%);
              border-radius:14px;padding:26px 28px;">

    <!-- Account Holder -->
    <p style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:2px;
               color:rgba(255,255,255,0.4);margin:0 0 5px;">Account Holder</p>
    <p style="font-size:17px;font-weight:700;color:#ffffff;margin:0 0 3px;">'.$acct_name.'</p>
    <p style="font-size:12px;color:rgba(255,255,255,0.5);margin:0 0 20px;">'.$email.'</p>

    <!-- Account Number Box -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background:rgba(255,255,255,0.06);border-radius:10px;margin-bottom:14px;">
    <tr>
    <td style="padding:16px 18px;">
      <p style="font-size:10px;color:rgba(255,255,255,0.4);text-transform:uppercase;
                 letter-spacing:1.2px;margin:0 0 6px;">Account Number</p>
      <p class="acct-no mono" style="font-size:26px;font-weight:800;color:#c59b3d;
                                     letter-spacing:4px;margin:0;">'.$acct_no.'</p>
    </td>
    </tr>
    </table>

    <!-- Details Grid (2 columns) -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

    <!-- Row 1: Account Type + Currency -->
    <tr>
    <td class="grid-cell" width="50%" style="padding-right:7px;padding-bottom:10px;" valign="top">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:rgba(255,255,255,0.06);border-radius:9px;">
      <tr><td style="padding:12px 15px;">
        <p style="font-size:9px;color:rgba(255,255,255,0.38);text-transform:uppercase;
                   letter-spacing:1px;margin:0 0 4px;">Account Type</p>
        <p style="font-size:13px;font-weight:700;color:#ffffff;margin:0;">'.$acct_type.' Account</p>
      </td></tr>
      </table>
    </td>
    <td class="grid-cell" width="50%" style="padding-left:7px;padding-bottom:10px;" valign="top">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:rgba(255,255,255,0.06);border-radius:9px;">
      <tr><td style="padding:12px 15px;">
        <p style="font-size:9px;color:rgba(255,255,255,0.38);text-transform:uppercase;
                   letter-spacing:1px;margin:0 0 4px;">Currency</p>
        <p style="font-size:13px;font-weight:700;color:#ffffff;margin:0;">'.$currency.' — US Dollar</p>
      </td></tr>
      </table>
    </td>
    </tr>

    <!-- Row 2: Routing + Wire Routing -->
    <tr>
    <td class="grid-cell" width="50%" style="padding-right:7px;padding-bottom:10px;" valign="top">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:rgba(255,255,255,0.06);border-radius:9px;">
      <tr><td style="padding:12px 15px;">
        <p style="font-size:9px;color:rgba(255,255,255,0.38);text-transform:uppercase;
                   letter-spacing:1px;margin:0 0 4px;">Routing Number (ACH)</p>
        <p class="mono" style="font-size:15px;font-weight:800;color:#c59b3d;
                                letter-spacing:2px;margin:0;">'.$routing.'</p>
      </td></tr>
      </table>
    </td>
    <td class="grid-cell" width="50%" style="padding-left:7px;padding-bottom:10px;" valign="top">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:rgba(255,255,255,0.06);border-radius:9px;">
      <tr><td style="padding:12px 15px;">
        <p style="font-size:9px;color:rgba(255,255,255,0.38);text-transform:uppercase;
                   letter-spacing:1px;margin:0 0 4px;">Wire Routing Number</p>
        <p class="mono" style="font-size:15px;font-weight:800;color:#c59b3d;
                                letter-spacing:2px;margin:0;">'.$wire.'</p>
      </td></tr>
      </table>
    </td>
    </tr>

    <!-- Row 3: SWIFT + Opening Date -->
    <tr>
    <td class="grid-cell" width="50%" style="padding-right:7px;padding-bottom:10px;" valign="top">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:rgba(255,255,255,0.06);border-radius:9px;">
      <tr><td style="padding:12px 15px;">
        <p style="font-size:9px;color:rgba(255,255,255,0.38);text-transform:uppercase;
                   letter-spacing:1px;margin:0 0 4px;">SWIFT / BIC Code</p>
        <p class="mono" style="font-size:15px;font-weight:800;color:#c59b3d;
                                letter-spacing:2px;margin:0;">'.$swift.'</p>
      </td></tr>
      </table>
    </td>
    <td class="grid-cell" width="50%" style="padding-left:7px;padding-bottom:10px;" valign="top">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:rgba(255,255,255,0.06);border-radius:9px;">
      <tr><td style="padding:12px 15px;">
        <p style="font-size:9px;color:rgba(255,255,255,0.38);text-transform:uppercase;
                   letter-spacing:1px;margin:0 0 4px;">Account Opened</p>
        <p style="font-size:13px;font-weight:700;color:#ffffff;margin:0;">'.$open_date.'</p>
      </td></tr>
      </table>
    </td>
    </tr>

    <!-- Row 4: Bank Info (full width) -->
    <tr>
    <td colspan="2">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
             style="background:rgba(197,155,61,0.1);border:1px solid rgba(197,155,61,0.25);
                    border-radius:9px;">
      <tr><td style="padding:12px 15px;">
        <p style="font-size:9px;color:rgba(255,255,255,0.38);text-transform:uppercase;
                   letter-spacing:1px;margin:0 0 4px;">Bank Information</p>
        <p style="font-size:13px;font-weight:700;color:#c59b3d;margin:0 0 3px;">'.$bank_nm.'</p>
        <p style="font-size:11px;color:rgba(255,255,255,0.45);margin:0;">'.$bank_addr.'</p>
      </td></tr>
      </table>
    </td>
    </tr>

    </table><!-- /.details grid -->

  </td>
  </tr>
  </table>
  <!-- END ACCOUNT DETAILS CARD -->

  <!-- ─────────────── QUICK GUIDE ─────────────── -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
         style="margin-bottom:26px;">
  <tr>
  <td style="background:#f8faff;border:1px solid #dbeafe;border-radius:12px;padding:22px 24px;">

    <p style="font-size:14px;font-weight:800;color:#0c2340;margin:0 0 18px;">
      📋 What Happens Next?
    </p>

    <!-- Step 1 -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:14px;">
    <tr>
    <td width="36" valign="top">
      <div style="width:30px;height:30px;background:linear-gradient(135deg,#0c2340,#1a4b8c);
                  border-radius:50%;text-align:center;line-height:30px;
                  font-size:13px;font-weight:800;color:#c59b3d;">1</div>
    </td>
    <td style="padding-left:12px;" valign="top">
      <p style="font-size:13px;font-weight:700;color:#0c2340;margin:0 0 3px;">
        KYC Document Review (1–2 Business Days)
      </p>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.7;">
        Our compliance team will review your submitted documents and send you an email
        once your account is fully verified and activated. Most reviews complete within 24 hours.
      </p>
    </td>
    </tr>
    </table>

    <!-- Step 2 -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin-bottom:14px;">
    <tr>
    <td width="36" valign="top">
      <div style="width:30px;height:30px;background:linear-gradient(135deg,#0c2340,#1a4b8c);
                  border-radius:50%;text-align:center;line-height:30px;
                  font-size:13px;font-weight:800;color:#c59b3d;">2</div>
    </td>
    <td style="padding-left:12px;" valign="top">
      <p style="font-size:13px;font-weight:700;color:#0c2340;margin:0 0 3px;">
        Fund Your Account
      </p>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.7;">
        Once approved, deposit funds via wire transfer, ACH, or direct deposit using
        your Routing Number, Wire Routing, or SWIFT code shown above.
      </p>
    </td>
    </tr>
    </table>

    <!-- Step 3 -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
    <td width="36" valign="top">
      <div style="width:30px;height:30px;background:linear-gradient(135deg,#0c2340,#1a4b8c);
                  border-radius:50%;text-align:center;line-height:30px;
                  font-size:13px;font-weight:800;color:#c59b3d;">3</div>
    </td>
    <td style="padding-left:12px;" valign="top">
      <p style="font-size:13px;font-weight:700;color:#0c2340;margin:0 0 3px;">
        Start Banking
      </p>
      <p style="font-size:12px;color:#64748b;margin:0;line-height:1.7;">
        Transfer money globally, request your Visa debit card, view statements,
        and manage everything from your secure online portal.
      </p>
    </td>
    </tr>
    </table>

  </td>
  </tr>
  </table>

  <!-- ─────────────── SECURITY NOTICE ─────────────── -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
         style="margin-bottom:28px;">
  <tr>
  <td style="background:#fff5f5;border:1px solid #fecaca;border-radius:12px;padding:18px 22px;">
    <p style="font-size:14px;font-weight:800;color:#dc2626;margin:0 0 12px;">
      🔒 Important Security Notice
    </p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
    <td>
      <ul style="font-size:13px;color:#7f1d1d;margin:0;padding-left:18px;line-height:1.9;">
        <li>MidwesternBank will <strong>NEVER</strong> call or email asking for your password, PIN, or OTP</li>
        <li>Do <strong>NOT</strong> share your account number with anyone you do not trust</li>
        <li>Always access your account via our <strong>official website only</strong></li>
        <li>If you did not open this account, contact us immediately at
          <a href="mailto:fraud@midwesternbank.com"
             style="color:#dc2626;font-weight:700;">fraud@midwesternbank.com</a>
        </li>
      </ul>
    </td>
    </tr>
    </table>
  </td>
  </tr>
  </table>

  <!-- ─────────────── CTA BUTTON ─────────────── -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
         style="margin-bottom:28px;">
  <tr>
  <td align="center">
    <a href="'.$login_url.'" target="_blank"
       style="display:inline-block;background:linear-gradient(135deg,#c59b3d,#d4a843);
              color:#0c2340;font-size:15px;font-weight:800;text-decoration:none;
              padding:16px 40px;border-radius:10px;letter-spacing:0.3px;
              box-shadow:0 4px 18px rgba(197,155,61,0.35);">
      🏦 &nbsp; Sign In to Online Banking &nbsp; →
    </a>
  </td>
  </tr>
  </table>

  <!-- ─────────────── SUPPORT ─────────────── -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
  <td style="border-top:1px solid #f1f5f9;padding-top:20px;text-align:center;">
    <p style="font-size:13px;color:#94a3b8;margin:0 0 8px;">
      Questions? Our support team is available 24/7
    </p>
    <p style="font-size:13px;margin:0;">
      <a href="mailto:support@midwesternbank.com"
         style="color:#c59b3d;text-decoration:none;font-weight:700;">
        support@midwesternbank.com
      </a>
      &nbsp;&nbsp;·&nbsp;&nbsp;
      <a href="'.$support_url.'"
         style="color:#c59b3d;text-decoration:none;font-weight:700;">
        Open Support Ticket
      </a>
    </p>
  </td>
  </tr>
  </table>

</td>
</tr>
<!-- END MAIN BODY -->

<!-- ═══ FOOTER ═══ -->
<tr>
<td style="background:#070f1c;padding:26px 44px;text-align:center;">
  <p style="font-size:13px;font-weight:700;color:rgba(255,255,255,0.55);margin:0 0 6px;">
    MidwesternBank, N.A.
  </p>
  <p style="font-size:12px;color:rgba(255,255,255,0.35);margin:0 0 10px;line-height:1.8;">
    '.$bank_addr.'<br>
    Member FDIC &nbsp;·&nbsp; Equal Housing Lender &nbsp;·&nbsp; SWIFT: '.$swift.'
  </p>
  <p style="font-size:11px;color:rgba(255,255,255,0.22);margin:0;line-height:1.7;">
    &copy; '.$year.' MidwesternBank, N.A. All rights reserved.<br>
    This email was sent to <strong style="color:rgba(255,255,255,0.35);">'.$email.'</strong>
    because you registered for a MidwesternBank account.
  </p>
</td>
</tr>

</table>
<!-- ═══════════════════════════════════════ END EMAIL CARD ═══════════════════════════════════════ -->

</td>
</tr>
</table>

</body>
</html>';

// ============================================================
// PLAIN TEXT FALLBACK
// ============================================================
$separator = str_repeat('─', 48);

$plain_body = "Welcome to MidwesternBank, {$first_name}!
{$separator}

Your email has been verified and your Checking Account is now ready.
Please save this email — it contains your official banking details.

ACCOUNT DETAILS
{$separator}
Account Holder  : {$acct_name}
Account Number  : {$acct_no}
Account Type    : {$acct_type} Account
Currency        : {$currency} (US Dollar)
Opened On       : {$open_date}

BANKING NUMBERS
{$separator}
Routing Number  : {$routing}
Wire Routing    : {$wire}
SWIFT / BIC     : {$swift}

BANK INFORMATION
{$separator}
{$bank_nm}
{$bank_addr}

WHAT HAPPENS NEXT
{$separator}
1. KYC Review (1-2 business days)
   Our compliance team will review your documents and email you once approved.

2. Fund Your Account
   Use your Routing, Wire Routing, or SWIFT code to receive deposits.

3. Start Banking
   Transfer money, request a Visa card, view statements — all online.

SIGN IN
{$separator}
{$login_url}

SUPPORT
{$separator}
Email   : support@midwesternbank.com
Tickets : {$support_url}

SECURITY NOTICE
{$separator}
MidwesternBank will NEVER ask for your password, PIN, or OTP by phone or email.
If you did not open this account, contact us immediately:
fraud@midwesternbank.com

{$separator}
This email was sent to {$email} because you registered for an account.";

// ============================================================
// SEND VIA PHPMAILER
// ============================================================
try {
    // Use fully qualified class name — DO NOT add "use PHPMailer\PHPMailer\Exception"
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // ── SMTP CONFIG ──
    $mail->isSMTP();
    $mail->Host        = SMTP_HOST;
    $mail->SMTPAuth    = true;
    $mail->Username    = SMTP_USERNAME;
    $mail->Password    = SMTP_PASSWORD;
    $mail->SMTPSecure  = SMTP_ENCRYPTION; // 'tls' or 'ssl'
    $mail->Port        = (int)SMTP_PORT;  // Cast to int — CRITICAL
    $mail->Timeout     = 15;

    // Allow self-signed certs (for Mailtrap sandbox)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    set_time_limit(60);

    // ── SENDER & RECIPIENT ──
    $mail->setFrom(SMTP_FROM, 'MidwesternBank, N.A.');
    $mail->addAddress($wu['email'], trim($wu['first_name'].' '.$wu['last_name']));
    $mail->addReplyTo('support@mdwestern.com', 'MidwesternBank Support');

    // ── HEADERS ──
    $mail->XMailer    = ' '; // Suppress X-Mailer header
    $mail->CharSet    = 'UTF-8';
    $mail->Encoding   = 'base64';

    // ── CONTENT ──
    $mail->isHTML(true);
    $mail->Subject = "Welcome to MidwesternBank, {$first_name}! Your Account is Ready 🏦";
    $mail->Body    = $html_body;
    $mail->AltBody = $plain_body;

    $mail->send();

    error_log("[welcome-email] ✅ Sent successfully to {$wu['email']} (user_id={$new_user_id})");

} catch(\Exception $e) {
    // \Exception with backslash — catches PHPMailer exceptions AND PHP exceptions
    error_log("[welcome-email] ❌ FAILED for user_id={$new_user_id}, email={$wu['email']}: ".$e->getMessage());
    // Do NOT re-throw — registration already succeeded, email failure is non-critical
}