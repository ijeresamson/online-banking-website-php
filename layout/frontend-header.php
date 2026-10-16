<?php
require_once 'config/db-config.php';
// Determine current page for active nav
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// SVG Logo helper
function mwLogo($size = 48) {
    return '
    <svg width="'.$size.'" height="'.$size.'" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
        <!-- Shield base -->
        <path d="M24 3 L43 11 L43 27 Q43 39 24 45 Q5 39 5 27 L5 11 Z" fill="#0c2340"/>
        <!-- Gold inner shield outline -->
        <path d="M24 6 L40 13 L40 27 Q40 37.5 24 43 Q8 37.5 8 27 L8 13 Z" fill="none" stroke="#c59b3d" stroke-width="1.5"/>
        <!-- Bank building columns -->
        <rect x="13" y="22" width="3"  height="12" fill="#c59b3d" rx="0.5"/>
        <rect x="18" y="19" width="3"  height="15" fill="#c59b3d" rx="0.5"/>
        <rect x="23" y="22" width="3"  height="12" fill="#c59b3d" rx="0.5"/>
        <rect x="28" y="19" width="3"  height="15" fill="#c59b3d" rx="0.5"/>
        <rect x="33" y="22" width="3"  height="12" fill="#c59b3d" rx="0.5"/>
        <!-- Base line -->
        <rect x="12" y="34" width="25" height="2" fill="#c59b3d" rx="1"/>
        <!-- Pediment top -->
        <rect x="12" y="18" width="25" height="2" fill="#c59b3d" rx="1"/>
        <!-- Triangle pediment -->
        <polygon points="24,10 37,18 11,18" fill="#c59b3d"/>
        <!-- Door -->
        <rect x="21" y="28" width="7" height="8" fill="#0c2340" rx="1"/>
    </svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title.' — MidwesternBank' : 'MidwesternBank — Rooted in the Midwest. Built for Your Future.'; ?></title>
    <meta name="description" content="<?php echo isset($meta_desc) ? $meta_desc : 'MidwesternBank offers secure personal and business banking, with competitive rates, digital tools, and a team that puts you first.'; ?>">

    <!-- Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="style/frontend.css">

    <!-- Inline favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<?php echo urlencode(mwLogo(32)); ?>">
</head>
<body>

<!-- ============================================================ NAVBAR ============================================================ -->
<nav class="mw-nav" id="navbar">
    <div class="mw-nav-inner">
        <!-- Logo -->
        <a href="<?php echo BASE_URL; ?>" class="mw-logo">
            <?php echo mwLogo(40); ?>
            <div class="mw-logo-text">Midwestern<span>Bank</span></div>
        </a>

        <!-- Desktop Nav Links -->
        <ul class="mw-nav-links">
            <li><a href=" <?php echo BASE_URL; ?>index"    class="<?php echo $current_page==='index'?'active':''; ?>">Home</a></li>
            <li><a href="<?php echo BASE_URL; ?>about"    class="<?php echo $current_page==='about'?'active':''; ?>">About</a></li>
            <li><a href="<?php echo BASE_URL; ?>home-security" class="<?php echo $current_page==='home-security'?'active':''; ?>">Security</a></li>
            <li><a href="<?php echo BASE_URL; ?>careers"  class="<?php echo $current_page==='careers'?'active':''; ?>">Careers</a></li>
        </ul>

        <!-- CTA Buttons -->
        <div class="mw-nav-cta">
            <a href="<?php echo BASE_URL; ?>login"    class="mw-nav-login">Sign In</a>
            <a href="<?php echo BASE_URL; ?>register" class="btn-mw btn-mw-primary btn-mw-sm">Open Account</a>
        </div>

        <!-- Mobile Hamburger -->
        <button class="mw-hamburger" id="hamburger" onclick="toggleMobileMenu()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <!-- Mobile Menu -->
    <div class="mw-mobile-menu" id="mobileMenu">
        <a href="<?php echo BASE_URL; ?>">Home</a>
        <a href="<?php echo BASE_URL; ?>about">About</a>
        <a href="<?php echo BASE_URL; ?>home-security">Security</a>
        <a href="<?php echo BASE_URL; ?>careers">Careers</a>
        <div style="height:1px;background:rgba(255,255,255,.1);margin:10px 0;"></div>
        <a href="<?php echo BASE_URL; ?>login">Sign In</a>
        <a href="<?php echo BASE_URL; ?>register" style="background:var(--mw-gold);color:var(--mw-navy);font-weight:700;margin-top:4px;">Open Account →</a>
    </div>
</nav>

<script>
// Nav scroll
window.addEventListener('scroll', function(){
    const nav = document.getElementById('navbar');
    if(window.scrollY > 50) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
});
// Mobile toggle
function toggleMobileMenu(){
    document.getElementById('mobileMenu').classList.toggle('open');
}
</script>