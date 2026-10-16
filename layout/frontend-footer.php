<!-- ============================================================ FOOTER ============================================================ -->
<footer class="mw-footer">
    <div class="container">
        <div class="mw-footer-grid">

            <!-- Brand -->
            <div>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                    <?php echo mwLogo(40); ?>
                    <div style="font-size:20px;font-weight:800;color:#fff;">Midwestern<span style="color:#c59b3d;">Bank</span></div>
                </div>
                <p class="mw-footer-desc">
                    Rooted in the Midwest. Built for your future.<br>
                    We provide secure, innovative banking solutions for individuals and businesses across the nation.
                </p>
                <div style="display:flex;align-items:center;gap:16px;margin-top:20px;">
                    <div class="fdic-badge">🏛 FDIC Insured</div>
                    <div class="fdic-badge">🔒 256-bit SSL</div>
                </div>
            </div>

            <!-- Products -->
            <div>
                <h4>Banking</h4>
                <a href="<?php echo BASE_URL; ?>register">Personal Checking</a>
                <a href="<?php echo BASE_URL; ?>register">Savings Accounts</a>
                <a href="<?php echo BASE_URL; ?>register">Debit Cards</a>
                <a href="<?php echo BASE_URL; ?>register">Wire Transfers</a>
                
            </div>

            <!-- Company -->
            <div>
                <h4>Company</h4>
                <a href="<?php echo BASE_URL; ?>about">About Us</a>
                <a href="<?php echo BASE_URL; ?>careers">Careers</a>
                <a href="<?php echo BASE_URL; ?>home-security">Security Center</a>
                <a href="about#leadership">Leadership</a>
                <a href="about#press">Newsroom</a>
            </div>

            <!-- Support -->
            <div>
                <h4>Support</h4>
                <a href="<?php echo BASE_URL; ?>">Help Center</a>
                <a href="<?php echo BASE_URL; ?>mailto:support@midwesternbank.com">Contact Us</a>
                <a href="<?php echo BASE_URL; ?>home-security#fraud">Report Fraud</a>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="mw-footer-bottom">
            <div>&copy; <?php echo date('Y'); ?> MidwesternBank, N.A. All rights reserved. Member FDIC. Equal Housing Lender.</div>
            <div style="display:flex;gap:20px;flex-wrap:wrap;">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Accessibility</a>
                <a href="#">Cookie Preferences</a>
            </div>
        </div>
    </div>
</footer>

</body>
</html>