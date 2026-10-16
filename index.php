<?php
$page_title = 'MidwesternBank — Rooted in the Midwest. Built for Your Future.';
$meta_desc  = 'MidwesternBank offers secure personal and business banking, digital transfers,
 Visa debit cards, and 24/7 online access. Open your account in minutes.';

 
require_once 'layout/frontend-header.php';
?>

<!-- ============================================================ HERO ============================================================ -->
<section class="mw-hero">
    <div class="hero-inner">
        <div class="hero-content">
            <div class="hero-eyebrow">
                🏦 FDIC Insured · Member Since 1989
            </div>
            <h1 class="hero-title">
                Banking That Works <span>For You</span>
            </h1>
            <p class="hero-desc">
                Open a fully insured Checking Account in minutes. Send money worldwide, manage cards, and track every dollar — all in one secure place.
            </p>
            <div class="hero-cta">
                <a href="<?php echo BASE_URL; ?>register" class="btn-mw btn-mw-primary">
                    <i class="ri-arrow-right-line"></i> Open Free Account
                </a>
                <a href="#features" class="btn-mw btn-mw-outline">
                    <i class="ri-play-circle-line"></i> Learn More
                </a>
            </div>
            <div class="hero-trust">
                <div class="hero-trust-dot"></div> 50,000+ Customers
                <div class="hero-trust-dot" style="margin-left:8px;"></div> $2.4B in Assets
                <div class="hero-trust-dot" style="margin-left:8px;"></div> FDIC Insured
            </div>
        </div>

        <!-- Floating Dashboard Card -->
        <div>
            <div class="hero-card">
                <div class="hero-card-balance">Available Balance</div>
                <div class="hero-card-amount">$24,830.00</div>

                <div class="hero-card-grid">
                    <div class="hero-mini-card">
                        <div class="hero-mini-label">This Month In</div>
                        <div class="hero-mini-val" style="color:#86efac;">+$6,200</div>
                    </div>
                    <div class="hero-mini-card">
                        <div class="hero-mini-label">This Month Out</div>
                        <div class="hero-mini-val" style="color:#fca5a5;">-$2,840</div>
                    </div>
                </div>

                <hr class="hero-card-divider" style="border:none;border-top:1px solid rgba(255,255,255,.1);">

                <div class="hero-txn">
                    <div class="hero-txn-dot" style="background:#d1fae5;color:#059669;">↓</div>
                    <div style="flex:1;font-size:13px;">Salary Deposit</div>
                    <div style="font-size:14px;font-weight:700;color:#86efac;">+$4,500</div>
                </div>
                <div class="hero-txn">
                    <div class="hero-txn-dot" style="background:#fee2e2;color:#dc2626;">↑</div>
                    <div style="flex:1;font-size:13px;">Wire Transfer</div>
                    <div style="font-size:14px;font-weight:700;color:#fca5a5;">-$1,200</div>
                </div>
                <div class="hero-txn">
                    <div class="hero-txn-dot" style="background:#d1fae5;color:#059669;">↓</div>
                    <div style="flex:1;font-size:13px;">ACH Received</div>
                    <div style="font-size:14px;font-weight:700;color:#86efac;">+$850</div>
                </div>

                <!-- Fake card visual -->
                <div style="margin-top:20px;background:linear-gradient(135deg,rgba(255,255,255,.12),rgba(255,255,255,.05));border-radius:12px;padding:16px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:10px;opacity:.5;letter-spacing:1px;margin-bottom:4px;">CHECKING ACCOUNT</div>
                        <div style="font-size:14px;font-family:monospace;letter-spacing:2px;">•••• •••• 4821</div>
                    </div>
                    <div style="font-size:20px;font-weight:800;font-style:italic;opacity:.7;">VISA</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================ TRUST BAR ============================================================ -->
<section class="section-sm section-navy">
    <div class="container">
        <div class="trust-bar">
            <?php
            $trust = [
                ['ri-shield-check-fill', 'FDIC Insured'],
                ['ri-lock-fill',         '256-bit SSL Encryption'],
                ['ri-bank-fill',         'Regulated National Bank'],
                ['ri-customer-service-2-fill','24/7 Customer Support'],
                ['ri-award-fill',        'AML & KYC Compliant'],
            ];
            foreach($trust as [$ic,$lbl]):
            ?>
            <div class="trust-item">
                <div class="trust-icon"><i class="<?php echo $ic; ?>"></i></div>
                <span><?php echo $lbl; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================ FEATURES ============================================================ -->
<section class="section section-bg" id="features">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">What We Offer</div>
            <h2 class="section-title">Everything You Need. Nothing You Don't.</h2>
            <p class="section-sub">Modern banking tools designed for how you actually live and work — from your first paycheck to your legacy.</p>
        </div>

        <div class="feat-grid">
            <?php
            $features = [
                ['ri-bank-card-2-line',        'Visa Debit Cards',           'Virtual and physical Visa cards issued instantly. Freeze, unfreeze, and set daily limits from your phone.'],
                ['ri-exchange-funds-line',      'Global Wire Transfers',      'Send money anywhere in the world using SWIFT/BIC. Domestic and international transfers processed quickly.'],
                ['ri-shield-keyhole-line',      'Bank-Grade Security',        'AES-256 encryption, two-factor authentication, real-time fraud monitoring, and KYC verification.'],
                ['ri-file-list-3-line',         'Instant Statements',         'Generate and download account statements for any date range. Print-ready PDFs in seconds.'],
                ['ri-customer-service-2-line',  '24/7 Support Center',        'Dedicated support team available around the clock. Submit tickets, chat, or email — we always respond.'],
                ['ri-smartphone-line',          'Mobile Banking',             'Full-featured online banking from any device. Check balances, pay bills, and transfer funds on the go.'],
            ];
            foreach($features as [$icon,$title,$desc]):
            ?>
            <div class="feat-card">
                <div class="feat-icon"><i class="<?php echo $icon; ?>"></i></div>
                <div class="feat-title"><?php echo $title; ?></div>
                <p class="feat-desc"><?php echo $desc; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================ STATS ============================================================ -->
<section class="section section-navy">
    <div class="container">
        <div class="text-center-block" style="margin-bottom:56px;">
            <div class="section-label" style="justify-content:center;">By The Numbers</div>
            <h2 class="section-title light">Trusted by Thousands Across America</h2>
        </div>
        <div class="stats-grid">
            <?php
            $stats = [
                ['50,000+', 'Active Customers'],
                ['$2.4B',   'Assets Under Management'],
                ['99.98%',  'Platform Uptime'],
                ['35+',     'Years of Banking'],
            ];
            foreach($stats as [$num,$lbl]):
            ?>
            <div class="stat-item">
                <div class="stat-num"><?php echo $num; ?></div>
                <div class="stat-lbl"><?php echo $lbl; ?></div>
                <div class="stat-divider"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================ HOW IT WORKS ============================================================ -->
<section class="section">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">Getting Started</div>
            <h2 class="section-title">Open Your Account in 3 Simple Steps</h2>
            <p class="section-sub">From application to active account in minutes. No branch visit required.</p>
        </div>
        <div class="steps-grid">
            <?php
            $steps=[
                ['Apply Online','Fill out our secure digital application with your personal and financial information. No paperwork, no waiting rooms.'],
                ['KYC Verification','Upload your Government ID and Proof of Address. Our compliance team reviews and approves within 1–2 business days.'],
                ['Start Banking','Your account number, routing number, and wire routing are generated instantly. Deposit funds and start banking immediately.'],
            ];
            foreach($steps as $i=>[$title,$desc]):
            ?>
            <div class="step-card">
                <div class="step-num"><?php echo $i+1; ?></div>
                <div class="step-title"><?php echo $title; ?></div>
                <p class="step-desc"><?php echo $desc; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================ CTA BANNER ============================================================ -->
<section class="section-sm section-bg">
    <div class="container">
        <div class="cta-banner">
            <div class="pill-mw pill-gold" style="margin-bottom:20px;">Free to open · No monthly fees</div>
            <h2>Ready to Start Banking Smarter?</h2>
            <p>Join over 50,000 customers who trust MidwesternBank with their financial future. Open your free Checking Account today — it takes less than 5 minutes.</p>
            <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
                <a href="<?php echo BASE_URL; ?>register" class="btn-mw btn-mw-primary" style="padding:16px 36px;font-size:16px;">
                    <i class="ri-arrow-right-line"></i> Open Your Free Account
                </a>
                <a href="<?php echo BASE_URL; ?>login" class="btn-mw btn-mw-navy" style="padding:16px 36px;font-size:16px;">
                    Sign In to Online Banking
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once 'layout/frontend-footer.php'; ?>