</main>
</div><!-- /.shell -->

<script>
/* ============================================================
   MIDWESTERNBANK — PORTAL JAVASCRIPT
============================================================ */

// ── SIDEBAR ──
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

// Close sidebar on ESC
document.addEventListener('keydown', e => {
    if(e.key === 'Escape') closeSidebar();
});

// ── PROFILE DROPDOWN ──
const profileBtn      = document.getElementById('userProfileBtn');
const profileDropdown = document.getElementById('profileDropdown');

if(profileBtn && profileDropdown) {
    profileBtn.addEventListener('click', e => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });
    document.addEventListener('click', e => {
        if(!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
    });
}

// ── AUTO-DISMISS ALERTS ──
document.querySelectorAll('.alert').forEach(alert => {
    // Skip warning alerts (KYC) — keep those visible
    if(alert.classList.contains('alert-warning')) return;
    setTimeout(() => {
        alert.style.transition = 'opacity .4s ease, transform .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
        alert.style.opacity    = '0';
        alert.style.transform  = 'translateY(-8px)';
        alert.style.maxHeight  = '0';
        alert.style.overflow   = 'hidden';
        alert.style.marginBottom = '0';
        alert.style.padding    = '0';
        setTimeout(() => alert.remove(), 400);
    }, 6000);
});

// ── SESSION ACTIVITY KEEP-ALIVE ──
// Ping every 10 minutes to prevent session timeout on active pages
let activityTimer;
function resetActivityTimer() {
    clearTimeout(activityTimer);
    activityTimer = setTimeout(() => {
        // Silent fetch to reset session timer
        fetch(window.location.href, { method: 'HEAD', credentials: 'same-origin' }).catch(() => {});
    }, 10 * 60 * 1000);
}
['mousemove','keydown','click','scroll','touchstart'].forEach(evt =>
    document.addEventListener(evt, resetActivityTimer, { passive: true })
);
resetActivityTimer();

// ── MOBILE: Active nav highlight fix ──
(function(){
    const current = window.location.pathname.split('/').pop().split('?')[0];
    document.querySelectorAll('.sb-nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if(href && href.split('?')[0] === current) {
            item.classList.add('active');
        }
    });
})();

// ── PAGE-SPECIFIC JS FROM EACH PAGE ──
<?php echo $extra_js ?? ''; ?>
</script>
</body>
</html>