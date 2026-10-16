<?php
session_start();
require_once 'config/db-config.php';
// ============================================================
// CHECK CUSTOMER LOGIN
// ============================================================

if (!isset($_SESSION['user']['id'])) {
    header('Location: login');
    exit;
}

// Get logged-in customer ID
$user_id = (int) $_SESSION['user']['id'];

$page_title = 'Security Center — MidwesternBank';

require_once "front_extra_css/extra_security.php";


// ============================================================
// CHANGE PASSWORD
// ============================================================
$pass_error   = '';
$pass_success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_password'])) {
    try {
        if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security check failed.");
        }
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if(empty($current)) throw new Exception("Current password is required.");
        if(empty($new))     throw new Exception("New password is required.");
        if(strlen($new) < 8)          throw new Exception("Password must be at least 8 characters.");
        if(!preg_match('/[A-Z]/', $new)) throw new Exception("Password must contain at least one uppercase letter.");
        if(!preg_match('/[0-9]/', $new)) throw new Exception("Password must contain at least one number.");
        if(!preg_match('/[\W_]/', $new)) throw new Exception("Password must contain at least one special character.");
        if($new !== $confirm)         throw new Exception("Passwords do not match.");
        if($current === $new)         throw new Exception("New password must differ from current password.");

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!password_verify($current, $row['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$hash, $user_id]);

        $_SESSION['success_message'] = "Password changed successfully.";
        header("Location: security");
        exit;

    } catch(Exception $e) {
        $pass_error = $e->getMessage();
    }
}

// ============================================================
// FREEZE / CLOSE ACCOUNT
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_danger'])) {
    try {
        if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security check failed.");
        }
        $action   = $_POST['danger_action'] ?? '';
        $pin_confirm = $_POST['danger_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if(!password_verify($pin_confirm, $row['password'])) {
            throw new Exception("Password incorrect. Action cancelled for security.");
        }

        if($action === 'freeze') {
            $stmt = $pdo->prepare("UPDATE users SET status='suspended' WHERE id=?");
            $stmt->execute([$user_id]);
            session_destroy();
            header("Location: login?msg=account_frozen");
            exit;
        } elseif($action === 'close') {
            $stmt = $pdo->prepare("UPDATE users SET status='closed' WHERE id=?");
            $stmt->execute([$user_id]);
            session_destroy();
            header("Location: login?msg=account_closed");
            exit;
        }
    } catch(Exception $e) {
        $danger_error = $e->getMessage();
    }
}

include 'layout/customer-header.php';

// ============================================================
// FETCH LOGIN HISTORY
// ============================================================
$login_history = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM login_attempts
        WHERE email = ?
        ORDER BY attempted_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['email']]);
    $login_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {}
?>

<div class="page-title">Security Center</div>
<div class="page-subtitle">Protect your account with strong security settings</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><i class="ri-checkbox-circle-line"></i><span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if(!empty($danger_error)): ?>
    <div class="alert alert-danger"><i class="ri-error-warning-line"></i><span><?php echo htmlspecialchars($danger_error); ?></span></div>
<?php endif; ?>

<div class="security-layout">

    <!-- LEFT COLUMN -->
    <div>

        <!-- Change Password -->
        <div class="card">
            <div class="card-title"><i class="ri-lock-password-line"></i> Change Password</div>

            <?php if($pass_error): ?>
                <div class="alert alert-danger"><i class="ri-error-warning-line"></i><span><?php echo htmlspecialchars($pass_error); ?></span></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token"    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="form_password" value="1">

                <?php
                $pw_fields = [
                    ['current_password','Current Password','current-password'],
                    ['new_password',    'New Password',    'new-password'],
                    ['confirm_password','Confirm Password','new-password'],
                ];
                foreach($pw_fields as [$fname, $label, $ac]):
                ?>
                <div class="form-group" style="margin-bottom:14px;position:relative;">
                    <label><?php echo $label; ?></label>
                    <div style="position:relative;">
                        <input type="password"
                               name="<?php echo $fname; ?>"
                               id="<?php echo $fname; ?>"
                               class="form-control"
                               autocomplete="<?php echo $ac; ?>"
                               placeholder="<?php echo $label; ?>"
                               <?php if($fname==='new_password') echo 'oninput="checkStrength(this.value)"'; ?>>
                        <button type="button"
                                onclick="togglePw('<?php echo $fname; ?>','icon_<?php echo $fname; ?>')"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-muted);">
                            <i class="ri-eye-off-line" id="icon_<?php echo $fname; ?>"></i>
                        </button>
                    </div>
                    <?php if($fname === 'new_password'): ?>
                        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                        <div style="font-size:11px;color:var(--text-muted);" id="strengthLabel"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <div style="background:var(--bg);border-radius:10px;padding:12px;margin-bottom:16px;font-size:12px;color:var(--text-muted);">
                    <strong>Requirements:</strong> 8+ characters · Uppercase letter · Number · Special character (!@#$%)
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="ri-shield-check-line"></i> Update Password
                </button>
            </form>
        </div>

        <!-- Security Settings Toggles -->
        <div class="card">
            <div class="card-title"><i class="ri-settings-3-line"></i> Security Preferences</div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <h4>Login Alert Emails</h4>
                    <p>Email on every new device login</p>
                </div>
                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <h4>Transaction Confirmation Emails</h4>
                    <p>Email receipt for every transaction</p>
                </div>
                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <h4>Two-Factor Authentication (2FA)</h4>
                    <p>Extra code required on login</p>
                </div>
                <label class="switch"><input type="checkbox"><span class="slider"></span></label>
            </div>
            <div class="toggle-row">
                <div class="toggle-info">
                    <h4>Suspicious Activity Alerts</h4>
                    <p>Alert on unusual login location or device</p>
                </div>
                <label class="switch"><input type="checkbox" checked><span class="slider"></span></label>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="danger-zone">
            <div class="card-title" style="color:var(--red);margin-bottom:16px;">
                <i class="ri-alert-line"></i> Danger Zone
            </div>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
                These actions require your password to confirm. They cannot easily be undone.
            </p>
            <div class="danger-action">
                <div>
                    <h4>Freeze Account</h4>
                    <p>Temporarily suspend all activity. Contact support to unfreeze.</p>
                </div>
                <button class="btn btn-danger" style="height:36px;font-size:12px;"
                        onclick="openDanger('freeze','Freeze your account?')">
                    <i class="ri-lock-2-line"></i> Freeze
                </button>
            </div>
            <div class="danger-action">
                <div>
                    <h4>Close Account</h4>
                    <p>Permanently close your account. This cannot be undone.</p>
                </div>
                <button class="btn btn-danger" style="height:36px;font-size:12px;"
                        onclick="openDanger('close','Permanently close your account?')">
                    <i class="ri-close-circle-line"></i> Close Account
                </button>
            </div>

            <!-- Hidden danger confirm form -->
            <form method="POST" id="dangerForm" style="display:none;">
                <input type="hidden" name="csrf_token"    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="form_danger"   value="1">
                <input type="hidden" name="danger_action" id="dangerAction">
                <div style="margin-top:14px;">
                    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;" id="dangerLabel"></label>
                    <input type="password" name="danger_password" class="form-control"
                           placeholder="Enter your password to confirm" style="margin-bottom:10px;">
                    <div style="display:flex;gap:10px;">
                        <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeDanger()">Cancel</button>
                        <button type="submit" class="btn btn-danger" style="flex:1;">Confirm</button>
                    </div>
                </div>
            </form>
        </div>

    </div>

    <!-- RIGHT COLUMN -->
    <div>

        <!-- Login History -->
        <div class="card">
            <div class="card-title"><i class="ri-history-line"></i> Recent Login Activity</div>
            <?php if(empty($login_history)): ?>
                <div style="text-align:center;padding:30px;color:var(--text-muted);">
                    <i class="ri-shield-line" style="font-size:40px;display:block;margin-bottom:10px;"></i>
                    No login history found.
                </div>
            <?php else: ?>
                <?php foreach($login_history as $log): ?>
                    <div class="log-row">
                        <div class="log-icon <?php echo $log['success'] ? 'ok' : 'bad'; ?>">
                            <i class="<?php echo $log['success'] ? 'ri-check-line' : 'ri-close-line'; ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:13px;font-weight:600;">
                                <?php echo date('d M Y, H:i:s', strtotime($log['attempted_at'])); ?>
                            </div>
                            <div style="font-size:12px;color:var(--text-muted);font-family:monospace;">
                                IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                            </div>
                        </div>
                        <span class="log-badge <?php echo $log['success'] ? 'ok' : 'bad'; ?>">
                            <?php echo $log['success'] ? 'Success' : 'Failed'; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Security Tips -->
        <div class="card">
            <div class="card-title"><i class="ri-lightbulb-line"></i> Security Tips</div>
            <?php
            $tips = [
                ['ri-lock-password-line','Use a strong, unique password','Never reuse passwords from other sites.'],
                ['ri-phone-line','Enable Two-Factor Authentication','2FA adds a critical second layer of protection.'],
                ['ri-eye-close-line','Never share your credentials','Bank staff will never ask for your password.'],
                ['ri-wifi-line','Avoid public Wi-Fi for banking','Use a secure, private network or VPN.'],
                ['ri-notification-3-line','Keep alerts on','Login and transaction alerts help detect fraud early.'],
            ];
            foreach($tips as [$icon,$title,$desc]):
            ?>
                <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
                    <div style="width:36px;height:36px;background:var(--accent-light);color:var(--accent);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
                        <i class="<?php echo $icon; ?>"></i>
                    </div>
                    <div>
                        <div style="font-size:13px;font-weight:600;margin-bottom:2px;"><?php echo $title; ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?php echo $desc; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<?php
$extra_js = '
function togglePw(id, iconId) {
    const el = document.getElementById(id);
    const ic = document.getElementById(iconId);
    el.type   = el.type === "password" ? "text" : "password";
    ic.className = el.type === "password" ? "ri-eye-off-line" : "ri-eye-line";
}

function checkStrength(val) {
    let score = 0;
    if(val.length >= 8)      score++;
    if(/[A-Z]/.test(val))   score++;
    if(/[0-9]/.test(val))   score++;
    if(/[\W_]/.test(val))   score++;
    const maps = [
        {w:"0%",   c:"transparent",  t:""},
        {w:"25%",  c:"#ef4444",      t:"Weak"},
        {w:"50%",  c:"#f59e0b",      t:"Fair"},
        {w:"75%",  c:"#3b82f6",      t:"Good"},
        {w:"100%", c:"#10b981",      t:"Strong ✓"},
    ];
    const m = maps[score];
    document.getElementById("strengthFill").style.width      = m.w;
    document.getElementById("strengthFill").style.background = m.c;
    document.getElementById("strengthLabel").textContent     = m.t;
    document.getElementById("strengthLabel").style.color     = m.c;
}

function openDanger(action, label) {
    document.getElementById("dangerAction").value = action;
    document.getElementById("dangerLabel").textContent = label + " Enter your password to confirm:";
    document.getElementById("dangerForm").style.display = "block";
    document.getElementById("dangerForm").scrollIntoView({behavior:"smooth"});
}

function closeDanger() {
    document.getElementById("dangerForm").style.display = "none";
}
';
include 'layout/customer-footer.php';
?>