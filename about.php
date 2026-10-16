<?php
$page_title = 'About Us';
$meta_desc  = 'Learn the story of MidwesternBank — founded in the heart of America, built on trust, integrity, and community values since 1989.';
require_once 'layout/frontend-header.php';
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="breadcrumb-mw">
        <a href="<?php echo BASE_URL; ?>">Home</a>
        <span>›</span>
        <span style="color:rgba(255,255,255,.6);">About Us</span>
    </div>
    <h1>About MidwesternBank</h1>
    <p>Rooted in the Midwest. Built on trust, community, and a commitment to your financial future since 1989.</p>
</div>

<!-- Mission -->
<section class="section">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;">
            <div>
                <div class="section-label">Our Mission</div>
                <h2 class="section-title">We Exist to Make Banking Simple, Safe, and Fair</h2>
                <p style="margin-bottom:16px;">MidwesternBank was founded in 1989 in Chicago, Illinois with a single belief: that every person deserves access to secure, transparent, and fair banking — regardless of their background or income.</p>
                <p style="margin-bottom:24px;">Over 35 years later, we serve more than 50,000 customers across the United States, offering full-service checking accounts, wire transfers, debit cards, and digital banking tools built for the modern world.</p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="<?php echo BASE_URL; ?>register" class="btn-mw btn-mw-navy">Open an Account</a>
                    <a href="<?php echo BASE_URL; ?>careers" class="btn-mw btn-mw-primary">Join Our Team</a>
                </div>
            </div>
            <div>
                <!-- Visual: Values Grid -->
                <div class="value-grid">
                    <?php
                    $values=[
                        ['ri-shield-star-line','Trust','Every action we take is built on transparency and integrity. We never compromise on your security.'],
                        ['ri-community-line','Community','From our Midwest roots to coast-to-coast reach, community always comes first.'],
                        ['ri-lightbulb-flash-line','Innovation','We blend 35 years of banking expertise with cutting-edge digital tools.'],
                        ['ri-scales-3-line','Fairness','No hidden fees. No fine print. Just honest, straightforward banking for everyone.'],
                    ];
                    foreach($values as [$icon,$title,$desc]):
                    ?>
                    <div class="value-card">
                        <div class="value-icon-box"><i class="<?php echo $icon; ?>"></i></div>
                        <div>
                            <div style="font-size:15px;font-weight:700;color:var(--mw-navy);margin-bottom:6px;"><?php echo $title; ?></div>
                            <p style="font-size:13px;margin:0;"><?php echo $desc; ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats -->
<section class="section section-navy">
    <div class="container">
        <div class="stats-grid">
            <?php
            foreach([['1989','Founded'],['50,000+','Customers Served'],['$2.4B','Assets Managed'],['35+','Years of Service']] as [$n,$l]):
            ?>
            <div class="stat-item"><div class="stat-num"><?php echo $n; ?></div><div class="stat-lbl"><?php echo $l; ?></div><div class="stat-divider"></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Leadership -->
<section class="section section-bg" id="leadership">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">Our Leadership</div>
            <h2 class="section-title">Meet the Team Behind MidwesternBank</h2>
            <p class="section-sub">Experienced banking professionals dedicated to building the financial institution you deserve.</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:28px;margin-top:48px;">
            <?php
            $leaders=[
                ['William H. Carter','Chief Executive Officer','35 years in financial services. Formerly VP at Northern Trust. MBA Harvard Business School.'],
                ['Margaret T. Sullivan','Chief Financial Officer','CPA, CFA. Former Controller at Wells Fargo Regional. Expert in compliance and risk management.'],
                ['Daniel J. Okonkwo','Chief Technology Officer','Led digital banking transformations at two top-10 US banks. Expert in fintech and cybersecurity.'],
            ];
            foreach($leaders as [$name,$role,$bio]):
            $initials=implode('',array_map(fn($w)=>strtoupper($w[0]),explode(' ',$name)));
            $initials=substr($initials,0,2);
            ?>
            <div style="background:#fff;border-radius:var(--mw-radius);padding:32px;border:1px solid var(--mw-border);text-align:center;">
                <div style="width:80px;height:80px;background:linear-gradient(135deg,var(--mw-navy),var(--mw-blue));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--mw-gold);margin:0 auto 16px;"><?php echo $initials; ?></div>
                <div style="font-size:17px;font-weight:700;color:var(--mw-navy);"><?php echo $name; ?></div>
                <div style="font-size:13px;color:var(--mw-gold);font-weight:600;margin:4px 0 12px;"><?php echo $role; ?></div>
                <p style="font-size:13px;"><?php echo $bio; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Press / Newsroom -->
<section class="section" id="press">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">Newsroom</div>
            <h2 class="section-title">Latest from MidwesternBank</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:48px;">
            <?php
            $news=[
                ['MidwesternBank Launches Full Digital Banking Platform','We are proud to announce the launch of our complete online banking portal, enabling customers to manage their finances entirely from any device.','Technology · June 2026'],
                ['MidwesternBank Surpasses $2.4 Billion in Assets Under Management','A major milestone achieved through the trust of over 50,000 customers across the United States.','Finance · March 2026'],
                ['MidwesternBank Ranked Top Regional Bank for Customer Satisfaction','J.D. Power recognized our commitment to customer experience, placing us among the top regional banks in the nation.','Awards · January 2026'],
            ];
            foreach($news as [$title,$summary,$meta]):
            ?>
            <div style="background:#fff;border-radius:var(--mw-radius);padding:28px;border:1px solid var(--mw-border);border-top:3px solid var(--mw-gold);">
                <div style="font-size:11px;color:var(--mw-gold);font-weight:600;margin-bottom:12px;"><?php echo $meta; ?></div>
                <div style="font-size:16px;font-weight:700;color:var(--mw-navy);margin-bottom:10px;line-height:1.4;"><?php echo $title; ?></div>
                <p style="font-size:13px;"><?php echo $summary; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="section-sm section-bg">
    <div class="container">
        <div class="cta-banner">
            <h2>Become Part of Our Story</h2>
            <p>35 years of banking history, and the best is yet to come. Open your MidwesternBank account today.</p>
            <a href="online-bank/register" class="btn-mw btn-mw-primary" style="font-size:16px;padding:16px 36px;">
                <i class="ri-arrow-right-line"></i> Open Your Account Today
            </a>
        </div>
    </div>
</section>

<?php require_once 'layout/frontend-footer.php'; ?>