<?php
$page_title = 'Security Center';
$meta_desc  = 'Learn how MidwesternBank protects your money and data with bank-grade encryption, fraud monitoring, KYC compliance, and 24/7 security teams.';
require_once 'layout/frontend-header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="breadcrumb-mw">
        <a href="<?php echo BASE_URL; ?>">Home</a>
        <span>›</span>
        <span style="color:rgba(255,255,255,.6);">Security</span>
    </div>
    <h1>🔒 Security Center</h1>
    <p>Your security is our highest priority. Discover the layers of protection we have built to keep your money and data safe.</p>
</div>

<!-- Security Overview -->
<section class="section">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">Our Security Stack</div>
            <h2 class="section-title">Bank-Grade Protection. Every Layer, Every Day.</h2>
            <p class="section-sub">We employ the same security infrastructure used by the world's largest financial institutions — combined with real-time monitoring by our dedicated security team.</p>
        </div>

        <div class="sec-grid" style="margin-top:48px;">
            <?php
            $security=[
                ['ri-shield-check-line', '256-bit AES Encryption','All data transmitted between your device and our servers is encrypted using AES-256, the same standard used by the US military and major financial institutions. Your data is completely unreadable to anyone intercepting the connection.'],
                ['ri-fingerprint-line',  'Multi-Factor Authentication','We require multi-factor authentication on all account logins. Even if someone obtains your password, they cannot access your account without the second verification code sent to your device.'],
                ['ri-scan-line',         'KYC / AML Compliance','Every MidwesternBank customer undergoes rigorous Know Your Customer (KYC) and Anti-Money Laundering (AML) verification before their account is activated. We comply with all US federal banking regulations.'],
                ['ri-alarm-warning-line','Real-Time Fraud Monitoring','Our automated fraud detection system monitors every transaction in real time, 24 hours a day. Suspicious activity triggers instant alerts and temporary holds to protect your funds.'],
                ['ri-server-line',       'Secure Data Centers','Your data is stored in SSAE 18 SOC 2 certified data centers with redundant backups, physical security controls, and 99.98% uptime guarantees.','ISO 27001 Certified'],
                ['ri-time-line',         'Session Security','Banking sessions automatically expire after 20 minutes of inactivity. All sessions are tied to your device fingerprint and IP. Unusual access locations trigger immediate alerts.'],
            ];
            foreach($security as $s):
                $icon=$s[0]; $title=$s[1]; $desc=$s[2]; $badge=$s[3]??null;
            ?>
            <div class="sec-card">
                <?php if($badge): ?>
                    <div class="sec-badge">✓ <?php echo $badge; ?></div><br>
                <?php else: ?>
                    <div class="sec-badge">✓ Active Protection</div><br>
                <?php endif; ?>
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
                    <div style="width:48px;height:48px;background:linear-gradient(135deg,var(--mw-navy),var(--mw-blue));border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--mw-gold);flex-shrink:0;">
                        <i class="<?php echo $icon; ?>"></i>
                    </div>
                    <div class="sec-title" style="margin-bottom:0;"><?php echo $title; ?></div>
                </div>
                <p style="font-size:14px;margin:0;"><?php echo $desc; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- What We Will Never Do -->
<section class="section section-bg">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start;">
            <div>
                <div class="section-label">Our Commitments</div>
                <h2 class="section-title">What MidwesternBank Will <span style="color:#dc2626;">NEVER</span> Do</h2>
                <p style="margin-bottom:24px;">Protect yourself by knowing what we will never ask of you. Any communication claiming to be from MidwesternBank that violates these commitments is fraudulent.</p>
                <div id="fraud" style="background:#fff5f5;border:2px solid #fca5a5;border-radius:12px;padding:20px;">
                    <div style="font-size:15px;font-weight:700;color:#dc2626;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
                        <i class="ri-alarm-warning-fill"></i> Fraud Warning
                    </div>
                    <p style="font-size:13px;color:#dc2626;margin-bottom:12px;">If you receive a suspicious message, call, or email claiming to be from MidwesternBank, do NOT respond.</p>
                    <a href="mailto:fraud@midwesternbank.com" style="display:inline-flex;align-items:center;gap:6px;background:#dc2626;color:#fff;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;">
                        <i class="ri-mail-line"></i> Report Fraud Now
                    </a>
                </div>
            </div>
            <div>
                <?php
                $nevers=[
                    'Ask for your password, PIN, or security code via email, phone, or text',
                    'Request remote access to your computer or device',
                    'Ask you to transfer money to a "safe" account to protect your funds',
                    'Send you unsolicited links to verify your account',
                    'Ask for your full debit card number, expiry, or CVV by phone',
                    'Pressure you to act immediately or threaten account closure',
                    'Contact you on unofficial social media channels',
                ];
                foreach($nevers as $n):
                ?>
                <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid var(--mw-border);">
                    <div style="width:24px;height:24px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;color:#dc2626;flex-shrink:0;font-weight:700;margin-top:1px;">✕</div>
                    <span style="font-size:14px;color:var(--mw-text);"><?php echo $n; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Security Tips -->
<section class="section">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">Stay Protected</div>
            <h2 class="section-title">Security Tips for Customers</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-top:48px;">
            <?php
            $tips=[
                ['ri-lock-password-line','Strong Passwords','Use a unique password of 12+ characters with uppercase, lowercase, numbers, and symbols. Never reuse passwords across sites.'],
                ['ri-smartphone-line','Enable MFA','Always use multi-factor authentication. This single step prevents 99.9% of unauthorized account access attempts.'],
                ['ri-wifi-off-line','Avoid Public WiFi','Never access your bank account on public or unsecured WiFi networks. Use a VPN if you must access banking on the go.'],
                ['ri-eye-close-line','Monitor Statements','Review your account statements weekly. Report any unrecognized transactions to us immediately — early detection is critical.'],
            ];
            foreach($tips as [$ic,$title,$desc]):
            ?>
            <div style="background:var(--mw-white);border-radius:var(--mw-radius);padding:28px;border:1px solid var(--mw-border);text-align:center;">
                <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--mw-navy),var(--mw-blue));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--mw-gold);margin:0 auto 16px;">
                    <i class="<?php echo $ic; ?>"></i>
                </div>
                <div style="font-size:15px;font-weight:700;color:var(--mw-navy);margin-bottom:10px;"><?php echo $title; ?></div>
                <p style="font-size:13px;"><?php echo $desc; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Report Fraud CTA -->
<section class="section-sm section-navy">
    <div class="container" style="text-align:center;">
        <div class="section-label" style="justify-content:center;">Emergency Contact</div>
        <h2 class="section-title light">Suspect Fraud? Act Immediately.</h2>
        <p style="color:rgba(255,255,255,.75);font-size:17px;max-width:560px;margin:0 auto 32px;">Our fraud response team is available 24/7. If you see unauthorized transactions or suspect your account has been compromised, contact us right now.</p>
        <div style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;">
            <a href="mailto:fraud@midwesternbank.com" class="btn-mw btn-mw-primary" style="font-size:16px;padding:16px 36px;">
                <i class="ri-mail-send-line"></i> Email Fraud Team
            </a>
            <a href="<?php echo BASE_URL; ?>support" class="btn-mw btn-mw-outline" style="font-size:16px;padding:16px 36px;">
                <i class="ri-customer-service-2-line"></i> Open Support Ticket
            </a>
        </div>
    </div>
</section>

<?php require_once 'layout/frontend-footer.php'; ?>