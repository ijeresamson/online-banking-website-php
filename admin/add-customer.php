<?php include 'layout/top.php'; ?>
<?php require_once '../config/helpers.php'; ?>
<?php
if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_add_customer'])) {
    try {
        $first_name        = trim($_POST['first_name']        ?? '');
        $last_name         = trim($_POST['last_name']         ?? '');
        $email             = strtolower(trim($_POST['email']  ?? ''));
        $phone             = trim($_POST['phone']             ?? '');
        $date_of_birth     = trim($_POST['date_of_birth']     ?? '');
        $nationality       = trim($_POST['nationality']       ?? '');
        $employment_status = trim($_POST['employment_status'] ?? '');
        $source_of_funds   = trim($_POST['source_of_funds']   ?? '');
        $id_type           = trim($_POST['id_type']           ?? '');
        $initial_balance   = (float)($_POST['initial_balance']?? 0);
        $status            = trim($_POST['status']            ?? 'pending_kyc');
        $password          = trim($_POST['password']          ?? '');

        // ---- VALIDATION ----
        if(empty($first_name)) throw new Exception("First name is required.");
        if(empty($last_name))  throw new Exception("Last name is required.");
        if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception("Valid email is required.");
        if(empty($phone))      throw new Exception("Phone number is required.");
        if(empty($date_of_birth)) throw new Exception("Date of birth is required.");
        if(empty($nationality)) throw new Exception("Nationality is required.");
        if(empty($employment_status)) throw new Exception("Employment status is required.");
        if(empty($source_of_funds))   throw new Exception("Source of funds is required.");
        if(empty($id_type))    throw new Exception("ID type is required.");
        if(empty($password) || strlen($password) < 8) throw new Exception("Password must be at least 8 characters.");
        if($initial_balance < 0) throw new Exception("Initial balance cannot be negative.");

        // Check existing email
        $s = $pdo->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $s->execute([$email]);
        if($s->fetch()) throw new Exception("An account with this email already exists.");

        // Check DOB
        $dob = DateTime::createFromFormat('Y-m-d', $date_of_birth);
        if(!$dob) throw new Exception("Invalid date of birth.");
        $age = (new DateTime())->diff($dob)->y;
        if($age < 18) throw new Exception("Customer must be at least 18 years old.");

        // Generate unique account number
        $account_number = '';
        do {
            $account_number = '30'.mt_rand(10000000,99999999);
            $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE account_number=?");
            $s->execute([$account_number]);
        } while($s->fetchColumn() > 0);

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        // Insert user
        $stmt = $pdo->prepare(" INSERT INTO users
                (account_number,first_name,last_name,email,phone,
                 date_of_birth,nationality,employment_status,source_of_funds,
                 id_type,id_document,address_document,password,status,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,'','',?,?,NOW())
        ");
        $stmt->execute([
            $account_number, $first_name, $last_name, $email, $phone,
            $date_of_birth, $nationality, $employment_status, $source_of_funds,
            $id_type, $hashed_password, $status
        ]);
        $new_user_id = (int)$pdo->lastInsertId();

        // Create account with banking details
        createAccountForUser($pdo, $new_user_id, $first_name, $last_name, 'Checking');

        // Add initial balance if provided
        if($initial_balance > 0) {
            updateBalance($pdo, $new_user_id, $initial_balance);
            $ref = generateRef('INIT');
            recordTransaction(
                $pdo, $new_user_id, 'deposit', $initial_balance,
                0.00, $initial_balance,
                'Initial Deposit — Account Opening',
                $ref, 'completed'
            );
            // Record deposit
            $d = $pdo->prepare("INSERT INTO deposits (user_id,amount,method,reference,note,status,admin_id,created_at) 
            VALUES (?,?,'admin_credit',?,'Account opening balance','completed',?,NOW())");
            $d->execute([$new_user_id,$initial_balance,$ref,$_SESSION['admin']['id']]);
        }

        $pdo->commit();

        $_SESSION['success_message'] = "Customer <strong>{$first_name} {$last_name}</strong> created successfully. Account: <code>{$account_number}</code>";
        header("Location: customer-detail-{$new_user_id}");
         exit;

    } catch(PDOException $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        error_log('Add customer error: '.$e->getMessage());
        $error_message = "Database error. Please try again.";
    } catch(Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}
?>

<div class="main-content">
<section class="section">

<div class="section-header d-flex justify-content-between align-items-center">
    <h1><i class="fas fa-user-plus mr-2"></i> Add New Customer</h1>
    <a href="customer-all" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-2"></i> Back to Customers
    </a>
</div>

<?php if(!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h4>Customer Information</h4></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="form_add_customer" value="1">

                    <!-- SECTION 1: Personal Info -->
                    <div class="section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #f8f9fa;">
                        <i class="fas fa-user mr-2"></i> Personal Information
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                   placeholder="John" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                   placeholder="Doe" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="john@example.com" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                   placeholder="+1 555 0192834" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Nationality <span class="text-danger">*</span></label>
                            <select name="nationality" class="form-control">
                                <option value="">Select Country...</option>
                                <?php
                                $nats = ['United States','United Kingdom','Canada','Nigeria','Ghana','Kenya','South Africa','Australia','Germany','France','India','Other'];
                                foreach($nats as $n):
                                    $sel = ($_POST['nationality']??'')===$n?'selected':'';
                                ?>
                                <option value="<?php echo $n; ?>" <?php echo $sel; ?>><?php echo $n; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- SECTION 2: Financial Info -->
                    <div class="section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin:20px 0 16px;padding-bottom:8px;border-bottom:2px solid #f8f9fa;">
                        <i class="fas fa-briefcase mr-2"></i> Financial & Compliance
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Employment Status <span class="text-danger">*</span></label>
                            <select name="employment_status" class="form-control">
                                <option value="">Select...</option>
                                <?php
                                foreach(['employed'=>'Employed','self_employed'=>'Self-Employed','freelancer'=>'Freelancer','student'=>'Student','unemployed'=>'Unemployed','retired'=>'Retired'] as $v=>$l):
                                    $sel=($_POST['employment_status']??'')===$v?'selected':'';
                                ?>
                                <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Source of Funds <span class="text-danger">*</span></label>
                            <select name="source_of_funds" class="form-control">
                                <option value="">Select...</option>
                                <?php
                                foreach(['salary'=>'Salary','business'=>'Business','investment'=>'Investment','inheritance'=>'Inheritance','savings'=>'Savings','other'=>'Other'] as $v=>$l):
                                    $sel=($_POST['source_of_funds']??'')===$v?'selected':'';
                                ?>
                                <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">ID Document Type <span class="text-danger">*</span></label>
                        <select name="id_type" class="form-control">
                            <option value="">Select ID Type...</option>
                            <?php
                            foreach(['passport'=>'International Passport','drivers_license'=>"Driver's License",'national_id'=>'National ID Card'] as $v=>$l):
                                $sel=($_POST['id_type']??'')===$v?'selected':'';
                            ?>
                            <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Note: KYC documents can be uploaded later via the customer detail page.</small>
                    </div>

                    <!-- SECTION 3: Account Setup -->
                    <div class="section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin:20px 0 16px;padding-bottom:8px;border-bottom:2px solid #f8f9fa;">
                        <i class="fas fa-university mr-2"></i> Account Setup
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Account Status</label>
                            <select name="status" class="form-control">
                                <option value="pending_kyc" <?php echo ($_POST['status']??'pending_kyc')==='pending_kyc'?'selected':''; ?>>Pending KYC</option>
                                <option value="active"      <?php echo ($_POST['status']??'')==='active'?'selected':''; ?>>Active (KYC Approved)</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Initial Balance (USD)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                </div>
                                <input type="number" name="initial_balance" class="form-control"
                                       placeholder="0.00" step="0.01" min="0"
                                       value="<?php echo htmlspecialchars($_POST['initial_balance'] ?? '0'); ?>">
                            </div>
                            <small class="text-muted">Leave 0 if no opening deposit.</small>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 mb-3" style="font-size:12px;">
                        <i class="fas fa-info-circle mr-2"></i>
                        Account number, routing number, and wire routing will be <strong>automatically generated</strong> upon creation.
                        Account type is always <strong>Checking</strong>.
                    </div>

                    <!-- SECTION 4: Password -->
                    <div class="section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin:20px 0 16px;padding-bottom:8px;border-bottom:2px solid #f8f9fa;">
                        <i class="fas fa-lock mr-2"></i> Account Security
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Temporary Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="password" class="form-control" id="genPassword"
                                       placeholder="Min. 8 characters" required>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" onclick="generatePassword()">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted">Customer can change this on first login.</small>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 mb-4" style="font-size:12px;">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Ensure you share the temporary password securely with the customer.
                        Recommend they change it on first login.
                    </div>

                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary btn-lg mr-3">
                            <i class="fas fa-user-plus mr-2"></i> Create Customer Account
                        </button>
                        <a href="customer-all" class="btn btn-secondary btn-lg">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Info Panel -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h4><i class="fas fa-info-circle mr-2"></i> What Happens Next</h4></div>
            <div class="card-body">
                <?php
                $steps = [
                    ['fas fa-database',   'Account Created',         'A unique account number, routing number, and wire routing number are generated automatically.'],
                    ['fas fa-shield-alt', 'KYC Review',              'If status is set to Pending KYC, the customer appears in the KYC queue for document verification.'],
                    ['fas fa-envelope',   'Customer Notified',       'You should notify the customer of their account number and temporary password.'],
                    ['fas fa-check-circle','Account Activation',     'Once KYC is approved, set status to Active. The customer can then log in and use all features.'],
                ];
                foreach($steps as $i => [$icon,$title,$desc]):
                ?>
                <div class="d-flex mb-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3 flex-shrink-0"
                         style="width:30px;height:30px;font-size:12px;font-weight:700;"><?php echo $i+1; ?></div>
                    <div>
                        <div style="font-size:13px;font-weight:600;"><?php echo $title; ?></div>
                        <div style="font-size:12px;color:#6c757d;margin-top:2px;"><?php echo $desc; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4><i class="fas fa-key mr-2"></i> Auto-Generated Details</h4></div>
            <div class="card-body">
                <div class="text-center py-3" id="generatedPreview">
                    <i class="fas fa-magic fa-2x text-muted mb-3 d-block"></i>
                    <p class="text-muted" style="font-size:13px;">
                        After creation, the following will be auto-generated:
                    </p>
                    <ul class="list-unstyled text-left" style="font-size:13px;">
                        <li><i class="fas fa-check text-success mr-2"></i> Account Number (30XXXXXXXX)</li>
                        <li><i class="fas fa-check text-success mr-2"></i> Routing Number (9-digit)</li>
                        <li><i class="fas fa-check text-success mr-2"></i> Wire Routing (9-digit)</li>
                        <li><i class="fas fa-check text-success mr-2"></i> SWIFT: <?php echo BANK_SWIFT; ?></li>
                        <li><i class="fas fa-check text-success mr-2"></i> Account Type: Checking</li>
                        <li><i class="fas fa-check text-success mr-2"></i> Currency: USD</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

</section>
</div>

<script>
function generatePassword() {
    const chars   = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
    const special = '!@#$%';
    const upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower   = 'abcdefghjkmnpqrstuvwxyz';
    const digits  = '23456789';

    let pw = upper[Math.floor(Math.random()*upper.length)]
           + lower[Math.floor(Math.random()*lower.length)]
           + digits[Math.floor(Math.random()*digits.length)]
           + special[Math.floor(Math.random()*special.length)];

    for(let i = 0; i < 8; i++) {
        pw += chars[Math.floor(Math.random()*chars.length)];
    }

    // Shuffle
    pw = pw.split('').sort(()=>0.5-Math.random()).join('');
    document.getElementById('genPassword').value = pw;
    document.getElementById('genPassword').type = 'text';
}
</script>

<?php include 'layout/footer.php'; ?>