<?php
session_start();
require_once 'config/db-config.php';
require_once 'config/helpers.php';
$page_title = 'Beneficiaries — MidwesternBank';

require_once "front_extra_css/extra_beneficiaries.php";


// ============================================================
// HANDLE ADD BENEFICIARY
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_add_benef'])) {
    try {
        if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security check failed.");
        }

        $benef_type    = $_POST['benef_type']    ?? 'internal';
        $name          = trim($_POST['benef_name']    ?? '');
        $account_num   = trim($_POST['benef_account'] ?? '');
        $bank_n        = trim($_POST['benef_bank']    ?? BANK_NAME);
        $swift_b       = trim($_POST['benef_swift']   ?? '');
        $routing_b     = trim($_POST['benef_routing'] ?? '');
        $country_b     = trim($_POST['benef_country'] ?? '');
         $user_id   = $_SESSION['user']['id'];

        if(empty($name))        throw new Exception("Beneficiary name is required.");
        if(empty($account_num)) throw new Exception("Account number is required.");
        if(strlen($name) > 100) throw new Exception("Name is too long (max 100 characters).");

        if($benef_type === 'internal') {

            // Validate account exists in our system
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, status FROM users WHERE account_number=? LIMIT 1");
            $stmt->execute([$account_num]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$found) throw new Exception("No MidwesternBank account found with number: {$account_num}.");
            if($found['status'] !== 'active') throw new Exception("That account is not currently active.");
            if($account_num === $user['account_number']) throw new Exception("You cannot add your own account as a beneficiary.");
            $bank_n = BANK_NAME;
        }

        // Check duplicate
        $stmt = $pdo->prepare("SELECT id FROM beneficiaries WHERE user_id=? AND account_number=? LIMIT 1");
        $stmt->execute([$user_id, $account_num]);
        if($stmt->fetch()) throw new Exception("This account is already in your saved beneficiaries.");

        $stmt = $pdo->prepare("INSERT INTO beneficiaries (user_id, name, account_number, bank_name, routing_number, swift_code, bank_country, beneficiary_type, created_at)
            VALUES (?,?,?,?,?,?,?,?,NOW())
        ");
        $stmt->execute([
            $user_id, $name,
            $account_num, $bank_n ?: BANK_NAME,
            $routing_b ?: null, $swift_b   ?: null,
            $country_b ?: null, $benef_type,
        ]);

        $_SESSION['success_message'] = "Beneficiary '{$name}' added successfully.";
        header("Location:".BASE_URL. "beneficiaries");
        exit;

    } catch(Exception $e) {
        $add_error = $e->getMessage();
    }
}

// ============================================================
// HANDLE DELETE
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_delete_benef'])) {
    try {
        if(empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security check failed.");
        }
         $user_id   = $_SESSION['user']['id'];
        $bid = (int)$_POST['benef_id'];
        $stmt = $pdo->prepare("DELETE FROM beneficiaries WHERE id=? AND user_id=?");
        $stmt->execute([$bid, $user_id]);
        $_SESSION['success_message'] = "Beneficiary removed.";
        header("Location:".BASE_URL. "beneficiaries");
        exit;
    } catch(Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
       header("Location:".BASE_URL. "beneficiaries");
        exit;
    }
}

include 'layout/customer-header.php';

// ============================================================
// FETCH
// ============================================================
$filter_type = $_GET['type']   ?? 'all';
$search      = trim($_GET['q'] ?? '');

$where  = ['user_id = ?'];
$params = [$user_id];

if($filter_type === 'internal') { $where[] = "beneficiary_type='internal'"; }
if($filter_type === 'external') { $where[] = "beneficiary_type='external'"; }
if($search) {
    $where[] = "(name LIKE ? OR account_number LIKE ? OR bank_name LIKE ?)";
    $sq = "%{$search}%";
    $params = array_merge($params, [$sq,$sq,$sq]);
}

$where_sql = 'WHERE '.implode(' AND ',$where);
$stmt = $pdo->prepare("SELECT * FROM beneficiaries {$where_sql} ORDER BY name ASC");
$stmt->execute($params);
$beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$counts = $pdo->prepare("SELECT SUM(1) AS total, SUM(beneficiary_type='internal') AS internal_cnt, SUM(beneficiary_type='external') AS external_cnt FROM beneficiaries WHERE user_id=?");
$counts->execute([$user_id]);
$counts = $counts->fetch(PDO::FETCH_ASSOC);
?>

<div class="page-title">My Beneficiaries</div>
<div class="page-subtitle">Manage your saved payment recipients for fast transfers</div>

<?php if(!empty($_SESSION['success_message'])): ?>
    <div class="alert alert-success"><i class="ri-checkbox-circle-line"></i><span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span></div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
<?php if(!empty($_SESSION['error_message'])): ?>
    <div class="alert alert-danger"><i class="ri-error-warning-line"></i><span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span></div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Header Bar -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <!-- Filter Pills -->
        <a href="?type=all" class="filter-pill <?php echo $filter_type==='all'?'active':''; ?>">
            All (<?php echo $counts['total']??0; ?>)
        </a>
        <a href="?type=internal" class="filter-pill <?php echo $filter_type==='internal'?'active':''; ?>">
            <i class="ri-bank-line mr-1"></i> MidwesternBank (<?php echo $counts['internal_cnt']??0; ?>)
        </a>
        <a href="?type=external" class="filter-pill <?php echo $filter_type==='external'?'active':''; ?>">
            <i class="ri-global-line mr-1"></i> Other Banks (<?php echo $counts['external_cnt']??0; ?>)
        </a>
    </div>
    <div style="display:flex;gap:10px;">
        <form method="GET" style="display:flex;gap:8px;">
            <input type="hidden" name="type" value="<?php echo htmlspecialchars($filter_type); ?>">
            <input type="text" name="q" class="form-control" style="width:200px;"
                   placeholder="Search beneficiaries..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-secondary" style="height:44px;padding:0 14px;">
                <i class="ri-search-line"></i>
            </button>
        </form>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
            <i class="ri-user-add-line"></i> Add Beneficiary
        </button>
    </div>
</div>

<!-- Beneficiary Cards -->
<?php if(empty($beneficiaries)): ?>
    <div class="empty-state">
        <i class="ri-user-star-line"></i>
        <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">
            <?php echo $search ? 'No results found' : 'No Beneficiaries Yet'; ?>
        </h3>
        <p style="margin-bottom:24px;">
            <?php echo $search ? "Try a different search term." : "Add recipients to make future transfers faster and easier."; ?>
        </p>
        <?php if(!$search): ?>
            <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">
                <i class="ri-user-add-line"></i> Add Your First Beneficiary
            </button>
        <?php else: ?>
            <a href="beneficiaries" class="btn btn-secondary">Clear Search</a>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="benef-grid">
    <?php foreach($beneficiaries as $b):
        $btype = $b['beneficiary_type'] ?? 'internal';
        $parts = explode(' ', $b['name']);
        $bi    = strtoupper(substr($parts[0],0,1).(isset($parts[1])?substr($parts[1],0,1):''));
    ?>
    <div class="benef-card <?php echo $btype; ?>">
        <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:14px;">
            <div class="benef-av <?php echo $btype; ?>"><?php echo $bi; ?></div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <div class="benef-name"><?php echo htmlspecialchars($b['name']); ?></div>
                    <span class="benef-type-badge <?php echo $btype==='external'?'bt-external':'bt-internal'; ?>">
                        <?php echo $btype==='external'?'🌐 External':'🏦 Internal'; ?>
                    </span>
                </div>
                <div class="benef-detail">Account: <span><?php echo htmlspecialchars($b['account_number']); ?></span></div>
                <div class="benef-detail">Bank: <span><?php echo htmlspecialchars($b['bank_name']); ?></span></div>
                <?php if(!empty($b['routing_number'])): ?>
                    <div class="benef-detail">Routing: <span><?php echo htmlspecialchars($b['routing_number']); ?></span></div>
                <?php endif; ?>
                <?php if(!empty($b['swift_code'])): ?>
                    <div class="benef-detail">SWIFT: <span><?php echo htmlspecialchars($b['swift_code']); ?></span></div>
                <?php endif; ?>
                <?php if(!empty($b['bank_country'])): ?>
                    <div class="benef-detail">Country: <span><?php echo htmlspecialchars($b['bank_country']); ?></span></div>
                <?php endif; ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                    Added <?php echo date('d M Y', strtotime($b['created_at'])); ?>
                </div>
            </div>
        </div>

        <div class="benef-actions">
            <a href="transfer?account=<?php echo urlencode($b['account_number']); ?>&type=<?php echo $btype; ?>"
               class="btn btn-primary" style="flex:1;height:36px;font-size:12px;">
                <i class="ri-send-plane-line"></i> Send Money
            </a>
            <form method="POST" class="d-inline"
                  onsubmit="return confirm('Remove <?php echo htmlspecialchars(addslashes($b['name'])); ?>?')">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="form_delete_benef" value="1">
                <input type="hidden" name="benef_id" value="<?php echo $b['id']; ?>">
                <button class="btn btn-secondary" style="height:36px;font-size:12px;width:42px;padding:0;">
                    <i class="ri-delete-bin-line" style="color:var(--red);"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>


<!-- ADD BENEFICIARY MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-title">
            <i class="ri-user-add-line" style="color:var(--accent);"></i>
            Add New Beneficiary
        </div>

        <?php if(!empty($add_error)): ?>
            <div class="alert alert-danger" style="margin-bottom:14px;">
                <i class="ri-error-warning-line"></i><span><?php echo htmlspecialchars($add_error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Beneficiary Type Tabs -->
        <div class="tab-strip">
            <button class="active" id="modal-tab-internal" onclick="switchModalTab('internal')">
                <i class="ri-bank-line mr-1"></i> MidwesternBank Account
            </button>
            <button id="modal-tab-external" onclick="switchModalTab('external')">
                <i class="ri-global-line mr-1"></i> Other Bank / International
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token"     value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="form_add_benef" value="1">
            <input type="hidden" name="benef_type"     id="modalBenefType" value="internal">

            <!-- Beneficiary Name (always shown) -->
            <div class="form-group" style="margin-bottom:14px;">
                <label>Beneficiary Full Name <span style="color:var(--red);">*</span></label>
                <input type="text" name="benef_name" class="form-control"
                       placeholder="e.g. John Michael Smith" required maxlength="100">
            </div>

            <!-- Account Number (always shown) -->
            <div class="form-group" style="margin-bottom:14px;">
                <label id="accLabel">MidwesternBank Account Number <span style="color:var(--red);">*</span></label>
                <input type="text" name="benef_account" class="form-control"
                       id="benefAccInput"
                       placeholder="e.g. 00984567231" required maxlength="50">
            </div>

            <!-- INTERNAL ONLY fields (bank name is auto) -->
            <div id="modal-internal-note" style="background:var(--accent-light);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--accent);margin-bottom:14px;">
                <i class="ri-information-line mr-1"></i>
                Enter the recipient's MidwesternBank account number. We will verify it automatically.
            </div>

            <!-- EXTERNAL ONLY fields -->
            <div id="modal-external-fields" style="display:none;">
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Bank Name <span style="color:var(--red);">*</span></label>
                    <input type="text" name="benef_bank" class="form-control"
                           placeholder="e.g. Chase Bank, Barclays, HSBC" maxlength="150">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Routing Number <span style="color:var(--text-muted);">(US)</span></label>
                    <input type="text" name="benef_routing" class="form-control"
                           placeholder="e.g. 021000021" maxlength="20"
                           style="font-family:monospace;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>SWIFT / BIC Code <span style="color:var(--text-muted);">(International)</span></label>
                    <input type="text" name="benef_swift" class="form-control"
                           placeholder="e.g. CHASUS33XXX" maxlength="30"
                           style="font-family:monospace;letter-spacing:1px;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label>Bank Country</label>
                    <input type="text" name="benef_country" class="form-control"
                           placeholder="e.g. United Kingdom" maxlength="100">
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="button" class="btn btn-secondary" style="flex:1;"
                        onclick="document.getElementById('addModal').classList.remove('active')">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="flex:2;">
                    <i class="ri-check-line"></i> Add Beneficiary
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$extra_js = '
function switchModalTab(tab) {
    const isExt = tab === "external";
    document.getElementById("modal-tab-internal").classList.toggle("active", !isExt);
    document.getElementById("modal-tab-external").classList.toggle("active",  isExt);
    document.getElementById("modal-internal-note").style.display   = isExt ? "none"  : "block";
    document.getElementById("modal-external-fields").style.display = isExt ? "block" : "none";
    document.getElementById("modalBenefType").value = tab;
    document.getElementById("accLabel").textContent = isExt
        ? "Account / IBAN Number *"
        : "MidwesternBank Account Number *";
    document.getElementById("benefAccInput").placeholder = isExt
        ? "e.g. 1234567890 or GB29NWBK60161331926819"
        : "e.g. 00984567231";
}

// Close modal on overlay click
document.getElementById("addModal").addEventListener("click", function(e) {
    if(e.target === this) this.classList.remove("active");
});

// Auto-open modal if there was a validation error
'.(!empty($add_error) ? 'document.getElementById("addModal").classList.add("active");' : '').'

// Handle direct link from transfer page
const urlParams = new URLSearchParams(window.location.search);
const preAcc  = urlParams.get("account");
const preType = urlParams.get("type");
if(preAcc) {
    document.querySelector("[name=\"benef_account\"]").value = preAcc;
    if(preType === "external") switchModalTab("external");
    document.getElementById("addModal").classList.add("active");
}
';
include 'layout/customer-footer.php';
?>