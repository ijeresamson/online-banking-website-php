<?php 
if(!isset($_SESSION['admin'])) {
    header('Location: login');
    exit;
}

?>

<div class="main-sidebar">
    <aside id="sidebar-wrapper">

        <!-- ================================================
             BRAND LOGO
        ================================================ -->
        <div class="sidebar-brand">
            <a href="index">
                <i class="fas fa-university"></i> Smart Post Bank
            </a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="index">
                <i class="fas fa-university"></i>
            </a>
        </div>

        <!-- ================================================
             ADMIN PROFILE QUICK INFO
        ================================================ -->
        <div class="sidebar-user">
            <div class="sidebar-user-picture">
                <i class="fas fa-user-shield fa-2x"></i>
            </div>
            <div class="sidebar-user-details">
                <div class="user-name">
                    <?php echo htmlspecialchars($_SESSION['admin']['name'] ?? 'Administrator'); ?>
                </div>
                <div class="user-role">Super Admin</div>
            </div>
        </div>

        <ul class="sidebar-menu">

            <!-- ============================================
                 DASHBOARD
            ============================================ -->
            <li class="menu-header">Main</li>

            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index' ? 'active' : ''; ?>">
                <a class="nav-link" href="index">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>


            <!-- ============================================
                 CUSTOMER MANAGEMENT
            ============================================ -->
            <li class="menu-header">Customer Management</li>

            <li class="nav-item dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']),
                ['customer-all', 'customer-pending-kyc', 'customer-approved',
                 'customer-suspended', 'customer-detail', 'add-customer']) ? 'active' : ''; ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
                <ul class="dropdown-menu">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'customer-all' ? 'active' : ''; ?>">
                        <a class="nav-link" href="customer-all">
                            <i class="fas fa-angle-right"></i> All Customers
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'add-customer' ? 'active' : ''; ?>">
                        <a class="nav-link" href="add-customer">
                            <i class="fas fa-angle-right"></i> Add Customer
                        </a>
                    </li>
            
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'customer-pending-kyc' ? 'active' : ''; ?>">
                        <a class="nav-link" href="customer-pending-kyc">
                            <i class="fas fa-angle-right"></i> Pending KYC
                            <?php
                            // Show count badge of pending KYC
                            $kyc_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status='pending_kyc'");
                            $kyc_stmt->execute();
                            $kyc_count = $kyc_stmt->fetchColumn();
                            if($kyc_count > 0):
                            ?>
                                <span class="badge badge-warning badge-pill ml-auto">
                                    <?php echo $kyc_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'customer-approved' ? 'active' : ''; ?>">
                        <a class="nav-link" href="customer-approved">
                            <i class="fas fa-angle-right"></i> Approved Customers
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'customer-suspended' ? 'active' : ''; ?>">
                        <a class="nav-link" href="customer-suspended">
                            <i class="fas fa-angle-right"></i> Suspended Accounts
                        </a>
                    </li>
                </ul>
            </li>


            <!-- ============================================
                 KYC VERIFICATION
            ============================================ -->
            <!-- <li class="nav-item dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']),
                ['kyc-pending', 'kyc-approved', 'kyc-rejected']) ? 'active' : ''; ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-id-card"></i>
                    <span>KYC Verification</span>
                </a>
                <ul class="dropdown-menu">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kyc-pending.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="kyc-pending.php">
                            <i class="fas fa-angle-right"></i> Pending Review
                            <?php if($kyc_count > 0): ?>
                                <span class="badge badge-danger badge-pill ml-auto">
                                    <?php echo $kyc_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kyc-approved.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="kyc-approved.php">
                            <i class="fas fa-angle-right"></i> Approved
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'kyc-rejected.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="kyc-rejected.php">
                            <i class="fas fa-angle-right"></i> Rejected
                        </a>
                    </li>
                </ul>
            </li> -->


            <!-- ============================================
                 ACCOUNTS
            ============================================ -->
            <li class="menu-header">Banking</li>

             <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'accounts-all' ? 'active' : ''; ?>">
                        <a class="nav-link" href="accounts-all">
                            <i class="fas fa-tachometer-alt"></i> All Accounts
                        </a>
                    </li>


            <!-- ============================================
                 TRANSACTIONS
            ============================================ --> 

             <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'transactions-all' ? 'active' : ''; ?>">
                        <a class="nav-link" href="transactions-all">
                            <i class="fas fa-tachometer-alt"></i> Transactions
                        </a>
                    </li>


            <!-- ============================================
                 TRANSFERS
            ============================================ -->
            <li class="nav-item dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']),
                ['transfers-all', 'transfers-pending',
                 'transfers-completed', 'transfers-international']) ? 'active' : ''; ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-paper-plane"></i>
                    <span>Transfers</span>
                </a>
                <ul class="dropdown-menu">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'transfers-all' ? 'active' : ''; ?>">
                        <a class="nav-link" href="transfers-all">
                            <i class="fas fa-angle-right"></i> All Transfers
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'transfers-pending' ? 'active' : ''; ?>">
                        <a class="nav-link" href="transfers-pending">
                            <i class="fas fa-angle-right"></i> Pending Transfers
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'transfers-completed' ? 'active' : ''; ?>">
                        <a class="nav-link" href="transfers-completed">
                            <i class="fas fa-angle-right"></i> Completed Transfer
                        </a>
                    </li>
                </ul>
            </li>


            <!-- ============================================
                FINANCES
            ============================================ -->
            <li class="nav-item dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']),
                ['deposit', 'transfer',
                 'withdrawal',]) ? 'active' : ''; ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Finances</span>
                </a>
                <ul class="dropdown-menu">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'deposit' ? 'active' : ''; ?>">
                        <a class="nav-link" href="deposit">
                            <i class="fas fa-angle-right"></i> Deposit
                        </a>
                    </li>
                   
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'withdrawal' ? 'active' : ''; ?>">
                        <a class="nav-link" href="withdrawal">
                            <i class="fas fa-angle-right"></i> Withdrawal
                        </a>
                    </li>
                   
                   
                </ul>
            </li>


            <!-- ============================================
                 CARDS
            ============================================ -->

               <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'cards-all' ? 'active' : ''; ?>">
                        <a class="nav-link" href="cards-all">
                            <i class="fas fa-tachometer-alt"></i> Card
                        </a>
                    </li>



            <!-- ============================================
                 SECURITY & COMPLIANCE
            ============================================ -->
            <!-- <li class="menu-header">Security</li>

            <li class="nav-item dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']),
                ['security-login-logs.php', 'security-failed-logins.php',
                 'security-flagged.php']) ? 'active' : ''; ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-shield-alt"></i>
                    <span>Security Logs</span>
                </a>
                <ul class="dropdown-menu">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'security-login-logs.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="security-login-logs.php">
                            <i class="fas fa-angle-right"></i> Login Logs
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'security-failed-logins.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="security-failed-logins.php">
                            <i class="fas fa-angle-right"></i> Failed Logins
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'security-flagged.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="security-flagged.php">
                            <i class="fas fa-angle-right"></i> Flagged Accounts
                        </a>
                    </li>
                </ul>
            </li>

            <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'aml-monitor.php' ? 'active' : ''; ?>">
                <a class="nav-link" href="aml-monitor.php">
                    <i class="fas fa-search-dollar"></i>
                    <span>AML Monitor</span>
                </a>
            </li>
 -->

            <!-- ============================================
                 SUPPORT TICKETS
            ============================================ -->
           <li class="menu-header">Support</li>

              <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'support-all' ? 'active' : ''; ?>">
                        <a class="nav-link" href="support-all">
                            <i class="fas fa-tachometer-alt"></i> Support Tickets
                        </a>
                    </li>

           <!--   <li class="nav-item dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']),
                ['support-all.php', 'support-open.php',
                 'support-resolved.php']) ? 'active' : ''; ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-headset"></i>
                    <span>Support Tickets</span>
                </a>
                <ul class="dropdown-menu">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'support-all.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="support-all.php">
                            <i class="fas fa-angle-right"></i> All Tickets
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'support-open.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="support-open.php">
                            <i class="fas fa-angle-right"></i> Open Tickets
                            <?php
                            try {
                                $s_stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE status='open'");
                                $s_stmt->execute();
                                $s_count = $s_stmt->fetchColumn();
                                if($s_count > 0):
                            ?> -->
                                <!-- <span class="badge badge-danger badge-pill ml-auto">
                                    <?php echo $s_count; ?>
                                </span>
                            <?php endif; } catch(Exception $e) {} ?>
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'support-resolved.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="support-resolved.php">
                            <i class="fas fa-angle-right"></i> Resolved
                        </a>
                    </li>
                </ul>
            </li> -->


        
          


            <!-- ============================================
                 SETTINGS
            ============================================ -->
            <li class="menu-header">System</li>

            <li class="nav-item dropdown <?php echo in_array(basename($_SERVER['PHP_SELF']),
                ['settings-general.php', 'settings-email.php',
                 'settings-security.php', 'settings-logo.php',
                 'settings-fees.php']) ? 'active' : ''; ?>">
                <a href="#" class="nav-link has-dropdown">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <ul class="dropdown-menu">
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings-general.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="settings-general.php">
                            <i class="fas fa-angle-right"></i> General Settings
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings-email.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="settings-email.php">
                            <i class="fas fa-angle-right"></i> Email Settings
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings-security.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="settings-security.php">
                            <i class="fas fa-angle-right"></i> Security Settings
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings-logo.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="settings-logo.php">
                            <i class="fas fa-angle-right"></i> Logo &amp; Branding
                        </a>
                    </li>
                    <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings-fees.php' ? 'active' : ''; ?>">
                        <a class="nav-link" href="settings-fees.php">
                            <i class="fas fa-angle-right"></i> Transaction Fees
                        </a>
                    </li>
                </ul>
            </li>

        
            <!-- ============================================
                 LOGOUT
            ============================================ -->
            <li class="menu-header">Account</li>

            <li>
                <a class="nav-link" href="admin-logout"
                   onclick="return confirm('Are you sure you want to logout?')">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>

        </ul>

    </aside>
</div>
