<?php
$page_title = 'Careers';
$meta_desc  = 'Join the MidwesternBank team. We are hiring talented professionals in banking, technology, compliance, and customer support. View open positions.';
require_once 'layout/frontend-header.php';

$jobs=[
    ['Technology',  'Senior Backend Engineer',          'Remote',     'Full-time', 'Lead backend infrastructure for our digital banking platform. PHP/Python/Node.js experience required.'],
    ['Technology',  'Mobile App Developer (iOS/Android)','Hybrid',    'Full-time', 'Build and maintain our customer-facing mobile banking application. React Native experience preferred.'],
    ['Technology',  'Cybersecurity Analyst',            'Chicago, IL','Full-time', 'Monitor, detect, and respond to security threats across our banking infrastructure. CISSP/CEH preferred.'],
    ['Compliance',  'AML Compliance Officer',           'Chicago, IL','Full-time', 'Oversee anti-money laundering programs, conduct investigations, and ensure regulatory compliance.'],
    ['Compliance',  'KYC / Identity Verification Specialist','Remote', 'Full-time', 'Review customer verification documents, approve accounts, and ensure regulatory KYC compliance.'],
    ['Operations',  'Senior Relationship Manager',      'Chicago, IL','Full-time', 'Build and maintain relationships with high-value clients. Banking or wealth management experience required.'],
    ['Operations',  'Branch Operations Manager',        'Chicago, IL','Full-time', 'Oversee daily banking operations, staff management, and customer service excellence.'],
    ['Support',     'Customer Support Specialist',      'Remote',     'Full-time', 'Provide exceptional support to banking customers via ticket, email, and chat. Banking experience a plus.'],
    ['Finance',     'Financial Analyst',                'Chicago, IL','Full-time', 'Analyze financial data, prepare reports, and support strategic decision-making for leadership.'],
    ['Marketing',   'Digital Marketing Manager',        'Hybrid',     'Full-time', 'Lead digital marketing campaigns, SEO strategy, and brand growth for MidwesternBank.'],
];
?>

<!-- Page Hero -->
<div class="page-hero">
    <div class="breadcrumb-mw">
        <a href="index">Home</a><span>›</span>
        <span style="color:rgba(255,255,255,.6);">Careers</span>
    </div>
    <h1>Build Your Career at MidwesternBank</h1>
    <p>Join a team of passionate professionals dedicated to making banking better. We offer competitive pay, remote options, and a culture built on trust.</p>
</div>

<!-- Why Work With Us -->
<section class="section section-bg">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">Why MidwesternBank</div>
            <h2 class="section-title">More Than a Job. A Career Worth Building.</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:48px;">
            <?php
            $perks=[
                ['ri-money-dollar-circle-line','Competitive Compensation','Market-leading salaries, annual performance bonuses, and equity participation for qualifying roles. We believe in rewarding great work.'],
                ['ri-heart-line','Health & Wellness','Comprehensive medical, dental, and vision coverage for you and your family from day one. Mental health resources included.'],
                ['ri-home-4-line','Remote-First Options','Many of our positions are fully remote or hybrid. We trust our team to do great work from anywhere.'],
                ['ri-graduation-cap-line','Learning & Development','$3,000 annual education stipend, conference attendance, and in-house training programs. We invest in your growth.'],
                ['ri-time-line','Flexible Time Off','Generous PTO policy, 12 paid holidays, and paid parental leave. We believe in rest as a productivity tool.'],
                ['ri-team-line','Inclusive Culture','A diverse, equitable, and inclusive workplace where every voice is heard and every background is valued.'],
            ];
            foreach($perks as [$ic,$title,$desc]):
            ?>
            <div class="feat-card" style="text-align:center;">
                <div class="feat-icon" style="margin:0 auto 20px;">
                    <i class="<?php echo $ic; ?>"></i>
                </div>
                <div class="feat-title"><?php echo $title; ?></div>
                <p class="feat-desc"><?php echo $desc; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Open Positions -->
<section class="section">
    <div class="container">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:36px;flex-wrap:wrap;gap:16px;">
            <div>
                <div class="section-label">Open Positions</div>
                <h2 class="section-title" style="margin-bottom:0;"><?php echo count($jobs); ?> Open Roles Across All Departments</h2>
            </div>
            <!-- Filter buttons -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;" id="filterBtns">
                <button onclick="filterJobs('All')" class="btn-mw btn-mw-navy btn-mw-sm active-filter" id="f-All">All Departments</button>
                <?php
                $depts=array_unique(array_column($jobs,0));
                foreach($depts as $d):
                ?>
                <button onclick="filterJobs('<?php echo $d; ?>')" class="btn-mw btn-mw-sm"
                        id="f-<?php echo $d; ?>"
                        style="background:#f4f7fc;color:var(--mw-navy);border-color:var(--mw-border);">
                    <?php echo $d; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="job-list" id="jobList">
            <?php foreach($jobs as $j):
                [$dept,$title,$location,$type,$desc]=$j;
                $dept_colors=['Technology'=>'#dbeafe #2563eb','Compliance'=>'#d1fae5 #059669','Operations'=>'#ede9fe #7c3aed','Support'=>'#fef3c7 #d97706','Finance'=>'#f0fdf4 #16a34a','Marketing'=>'#fce7f3 #db2777'];
                [$dbg,$dc]=explode(' ',$dept_colors[$dept]??'#f3f4f6 #6b7280');
            ?>
            <div class="job-card" data-dept="<?php echo $dept; ?>">
                <div style="flex:1;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
                        <div class="job-title"><?php echo htmlspecialchars($title); ?></div>
                        <span class="job-dept-badge" style="background:<?php echo $dbg; ?>;color:<?php echo $dc; ?>;">
                            <?php echo $dept; ?>
                        </span>
                    </div>
                    <div class="job-meta">
                        <i class="ri-map-pin-line" style="margin-right:4px;"></i><?php echo $location; ?>
                        <span style="margin:0 8px;color:var(--mw-border);">·</span>
                        <i class="ri-time-line" style="margin-right:4px;"></i><?php echo $type; ?>
                    </div>
                    <p style="font-size:13px;margin-top:8px;margin-bottom:0;"><?php echo htmlspecialchars($desc); ?></p>
                </div>
                <a href="mailto:careers@midwesternbank.com?subject=Application: <?php echo urlencode($title); ?>"
                   class="btn-mw btn-mw-primary btn-mw-sm" style="flex-shrink:0;">
                    Apply Now →
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- No results message -->
        <div id="noJobs" style="display:none;text-align:center;padding:48px;color:var(--mw-muted);">
            <i class="ri-search-line" style="font-size:40px;display:block;margin-bottom:12px;opacity:.3;"></i>
            <p>No open positions in this department right now. Check back soon!</p>
        </div>
    </div>
</section>

<!-- Application Process -->
<section class="section section-bg">
    <div class="container">
        <div class="text-center-block">
            <div class="section-label">How to Apply</div>
            <h2 class="section-title">Our Hiring Process is Simple and Transparent</h2>
        </div>
        <div class="steps-grid" style="margin-top:48px;">
            <?php
            foreach([
                ['Apply Online','Click "Apply Now" on any role. Email your resume and cover letter to careers@midwesternbank.com. We review every application within 5 business days.'],
                ['Phone Screen','A 30-minute call with our HR team to learn more about you, discuss the role, and answer your questions. We want this to be mutual.'],
                ['Interview & Offer','A technical or role-specific interview (may be virtual or in-person). Successful candidates receive a competitive offer within 48 hours.'],
            ] as $i=>[$title,$desc]):
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

<!-- CTA -->
<section class="section-sm section-navy">
    <div class="container" style="text-align:center;">
        <h2 class="section-title light">Don't See Your Perfect Role?</h2>
        <p style="color:rgba(255,255,255,.75);font-size:17px;max-width:500px;margin:0 auto 28px;">We are always looking for exceptional talent. Send us your resume and we will keep you in mind for future openings.</p>
        <a href="mailto:careers@midwesternbank.com?subject=General Application — MidwesternBank"
           class="btn-mw btn-mw-primary" style="font-size:16px;padding:16px 36px;">
            <i class="ri-mail-send-line"></i> Send Us Your Resume
        </a>
    </div>
</section>

<script>
function filterJobs(dept){
    // Update button styles
    document.querySelectorAll('#filterBtns button').forEach(btn => {
        btn.style.background   = '#f4f7fc';
        btn.style.color        = 'var(--mw-navy)';
        btn.style.borderColor  = 'var(--mw-border)';
    });
    const active = document.getElementById('f-'+dept);
    if(active){ active.style.background='var(--mw-navy)'; active.style.color='#fff'; active.style.borderColor='var(--mw-navy)'; }

    let visible=0;
    document.querySelectorAll('.job-card').forEach(card => {
        const show = dept==='All' || card.dataset.dept===dept;
        card.style.display = show ? 'flex' : 'none';
        if(show) visible++;
    });
    document.getElementById('noJobs').style.display = visible===0 ? 'block' : 'none';
}
</script>

<?php require_once 'layout/frontend-footer.php'; ?>