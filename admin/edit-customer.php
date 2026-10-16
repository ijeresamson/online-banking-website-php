<?php include "layout/top.php";
if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

$error_message = '';
$success_message = '';


// ============================================================
// CSRF TOKEN
// ============================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ============================================================
// GET CUSTOMER ID
// ============================================================

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$user_id) {
    $_SESSION['error_message'] = "Invalid customer ID.";
    header("Location: customer-all");
    exit;
}


// ============================================================
// FETCH CUSTOMER
// ============================================================

$stmt = $pdo->prepare(" SELECT *
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$user_id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['error_message'] = "Customer not found.";
    header("Location: customer-all");
    exit;
}


// ============================================================
// UPDATE CUSTOMER
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {

    try {

        // --------------------------------------------------------
        // CSRF
        // --------------------------------------------------------

        if (
            empty($_POST['csrf_token']) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_token'],
                $_POST['csrf_token']
            )
        ) {
            throw new Exception(
                "Security check failed. Please refresh the page."
            );
        }


        // --------------------------------------------------------
        // GET FORM DATA
        // --------------------------------------------------------

        $first_name        = trim($_POST['first_name'] ?? '');
        $last_name         = trim($_POST['last_name'] ?? '');
        $email             = strtolower(trim($_POST['email'] ?? ''));
        $phone             = trim($_POST['phone'] ?? '');
        $date_of_birth     = trim($_POST['date_of_birth'] ?? '');
        $nationality       = trim($_POST['nationality'] ?? '');
        $employment_status = trim($_POST['employment_status'] ?? '');
        $source_of_funds   = trim($_POST['source_of_funds'] ?? '');
        $status             = trim($_POST['status'] ?? '');
        $password           = $_POST['password'] ?? '';
        $confirm_password   = $_POST['confirm_password'] ?? '';


        // --------------------------------------------------------
        // VALIDATION
        // --------------------------------------------------------

        if ($first_name === '') {
            throw new Exception("First name is required.");
        }

        if (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $first_name)) {
            throw new Exception("Invalid first name.");
        }


        if ($last_name === '') {
            throw new Exception("Last name is required.");
        }

        if (!preg_match("/^[a-zA-Z\s'-]{2,50}$/", $last_name)) {
            throw new Exception("Invalid last name.");
        }


        if ($email === '') {
            throw new Exception("Email address is required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }


        // --------------------------------------------------------
        // CHECK EMAIL
        // Make sure another customer doesn't already use it.
        // --------------------------------------------------------

        $stmt = $pdo->prepare("SELECT id
            FROM users WHERE email = ? AND id != ?  LIMIT 1");

        $stmt->execute([$email, $user_id]);

        if ($stmt->fetch()) {
            throw new Exception(
                "Another customer already uses this email address."
            );
        }


        // --------------------------------------------------------
        // PHONE
        // --------------------------------------------------------

        if ($phone === '') {
            throw new Exception("Phone number is required.");
        }


        if (!preg_match('/^[0-9+\-\(\)\s]{7,20}$/', $phone)) {
            throw new Exception("Please enter a valid phone number.");
        }


        // --------------------------------------------------------
        // DATE OF BIRTH
        // --------------------------------------------------------

        if ($date_of_birth !== '') {

            $dob = DateTime::createFromFormat(
                'Y-m-d',
                $date_of_birth
            );

            if (
                !$dob ||
                $dob->format('Y-m-d') !== $date_of_birth
            ) {
                throw new Exception(
                    "Please enter a valid date of birth."
                );
            }

            $age = (new DateTime())->diff($dob)->y;

            if ($age < 18) {
                throw new Exception(
                    "Customer must be at least 18 years old."
                );
            }

            if ($age > 120) {
                throw new Exception(
                    "Please enter a valid date of birth."
                );
            }
        }


        // --------------------------------------------------------
        // EMPLOYMENT
        // --------------------------------------------------------

        $allowed_employment = [
            'employed',
            'self_employed',
            'unemployed',
            'student',
            'retired'
        ];

        if (
            $employment_status !== '' &&
            !in_array(
                $employment_status,
                $allowed_employment,
                true
            )
        ) {
            throw new Exception(
                "Invalid employment status."
            );
        }


        // --------------------------------------------------------
        // SOURCE OF FUNDS
        // --------------------------------------------------------

        $allowed_sources = [
            'salary',
            'business',
            'investment',
            'inheritance',
            'savings',
            'other'
        ];

        if (
            $source_of_funds !== '' && !in_array($source_of_funds,$allowed_sources,true)
        ) {
            throw new Exception(
                "Invalid source of funds."
            );
        }


        // --------------------------------------------------------
        // STATUS
        // --------------------------------------------------------

        $allowed_statuses = [
            'active',
            'pending_kyc',
            'kyc_rejected',
            'suspended',
            'closed'
        ];

        if (
            !in_array(
                $status,
                $allowed_statuses,
                true
            )
        ) {
            throw new Exception(
                "Invalid account status."
            );
        }


        // --------------------------------------------------------
        // UPDATE WITHOUT PASSWORD
        // --------------------------------------------------------

        if ($password === '') {

            $stmt = $pdo->prepare(" UPDATE users SET first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    date_of_birth = ?,
                    nationality = ?,
                    employment_status = ?,
                    source_of_funds = ?,
                    status = ?

                WHERE id = ?
            ");

            $stmt->execute([

                $first_name,
                $last_name,
                $email,
                $phone,
                $date_of_birth ?: null,
                $nationality,
                $employment_status,
                $source_of_funds,
                $status,
                $user_id

            ]);

        } else {

            // ----------------------------------------------------
            // PASSWORD VALIDATION
            // ----------------------------------------------------

            if (strlen($password) < 8) {
                throw new Exception(
                    "Password must contain at least 8 characters."
                );
            }

            if (strlen($password) > 128) {
                throw new Exception(
                    "Password is too long."
                );
            }

            if ($password !== $confirm_password) {
                throw new Exception(
                    "Passwords do not match."
                );
            }


            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            $stmt = $pdo->prepare(" UPDATE users SET

                    first_name = ?,
                    last_name = ?,
                    email = ?,
                    phone = ?,
                    date_of_birth = ?,
                    nationality = ?,
                    employment_status = ?,
                    source_of_funds = ?,
                    status = ?,
                    password = ?

                WHERE id = ?
            ");

            $stmt->execute([

                $first_name,
                $last_name,
                $email,
                $phone,
                $date_of_birth ?: null,
                $nationality,
                $employment_status,
                $source_of_funds,
                $status,
                $hashed_password,
                $user_id

            ]);
        }


        // --------------------------------------------------------
        // SUCCESS
        // --------------------------------------------------------

        $_SESSION['success_message'] =
            "Customer information updated successfully.";

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));

        header("Location: customer-detail-" . $user_id  );

        exit;


    } catch (PDOException $e) {

        error_log(
            "Edit Customer DB Error: " .
            $e->getMessage()
        );

        $error_message =
            "Unable to update customer. Please try again.";

    } catch (Exception $e) {

        $error_message = $e->getMessage();
    }
}


// ============================================================
// REFRESH CUSTOMER DATA AFTER UPDATE
// ============================================================

$stmt = $pdo->prepare(" SELECT * FROM users WHERE id = ? LIMIT 1");

$stmt->execute([$user_id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: customer-all");
    exit;
}

?>

    <div class="main-content">
        <section class="section">   
            <div class="section-body">
               <div class="row">
                <div class="container-fluid">

    <!-- PAGE HEADER -->

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">  Edit Customer</h3>
            <p class="text-muted mb-0">
                Update customer account information.
            </p>
        </div>

        <a href="customer-detail-<?php echo (int)$user_id; ?>"
            class="btn btn-secondary">
            <i class="ri-arrow-left-line"></i>   Back </a> </div>


    <!-- ERROR -->

    <?php if (!empty($error_message)): ?>

        <div class="alert alert-danger">

            <i class="ri-error-warning-line"></i>
            <?php echo htmlspecialchars( $error_message, ENT_QUOTES, 'UTF-8' ); ?> </div>
             <?php endif; ?>


    <!-- SUCCESS -->

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="ri-checkbox-circle-line"></i>
            <?php echo htmlspecialchars(  $_SESSION['success_message'], ENT_QUOTES, 'UTF-8' );
             unset($_SESSION['success_message']); ?>  </div>    <?php endif; ?>


    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="">

                <!-- CSRF -->

                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars( $_SESSION['csrf_token'],  ENT_QUOTES, 'UTF-8' ); ?>" >


                <!-- PERSONAL INFORMATION -->

                <h5 class="mb-3"> <i class="ri-user-line"></i>  Personal Information </h5>


                <div class="row">
                    <!-- FIRST NAME -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First Name * </label>

                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($customer['first_name'] ?? '',ENT_QUOTES,'UTF-8'); ?>" required></div>


                    <!-- LAST NAME -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Last Name *
                        </label>

                        <input type="text" name="last_name" class="form-control" 
                        value="<?php echo htmlspecialchars($customer['last_name'] ?? '',ENT_QUOTES,'UTF-8'); ?>"
                        required>

                    </div>


                    <!-- EMAIL -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Email Address *
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?php echo htmlspecialchars(
                                $customer['email'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>


                    <!-- PHONE -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Phone Number *
                        </label>

                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?php echo htmlspecialchars(
                                $customer['phone'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            required
                        >

                    </div>


                    <!-- DOB -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Date of Birth
                        </label>

                        <input
                            type="date"
                            name="date_of_birth"
                            class="form-control"
                            value="<?php echo htmlspecialchars(
                                $customer['date_of_birth'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >

                    </div>


                    <!-- NATIONALITY -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Nationality
                        </label>

                        <input
                            type="text"
                            name="nationality"
                            class="form-control"
                            value="<?php echo htmlspecialchars(
                                $customer['nationality'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >

                    </div>

                </div>


                <hr>


                <!-- ACCOUNT INFORMATION -->

                <h5 class="mb-3">

                    <i class="ri-bank-line"></i>

                    Account Information

                </h5>


                <div class="row">

                    <!-- ACCOUNT NUMBER -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Account Number
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?php echo htmlspecialchars(
                                $customer['account_number'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            readonly
                        >

                        <small class="text-muted">
                            Account numbers cannot be changed.
                        </small>

                    </div>


                    <!-- STATUS -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Account Status *
                        </label>

                        <select
                            name="status"
                            class="form-select"
                            required
                        >

                            <option
                                value="active"
                                <?php echo $customer['status'] === 'active'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Active
                            </option>

                            <option
                                value="pending_kyc"
                                <?php echo $customer['status'] === 'pending_kyc'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Pending KYC
                            </option>

                            <option
                                value="kyc_rejected"
                                <?php echo $customer['status'] === 'kyc_rejected'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                KYC Rejected
                            </option>

                            <option
                                value="suspended"
                                <?php echo $customer['status'] === 'suspended'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Suspended
                            </option>

                            <option
                                value="closed"
                                <?php echo $customer['status'] === 'closed'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Closed
                            </option>

                        </select>

                    </div>

                </div>


                <hr>


                <!-- FINANCIAL INFORMATION -->

                <h5 class="mb-3">

                    <i class="ri-briefcase-line"></i>

                    Financial & Employment Information

                </h5>


                <div class="row">

                    <!-- EMPLOYMENT -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Employment Status
                        </label>

                        <select
                            name="employment_status"
                            class="form-select"
                        >

                            <option value="">
                                Select employment status
                            </option>

                            <option
                                value="employed"
                                <?php echo $customer['employment_status'] === 'employed'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Employed
                            </option>

                            <option
                                value="self_employed"
                                <?php echo $customer['employment_status'] === 'self_employed'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Self Employed
                            </option>

                            <option
                                value="unemployed"
                                <?php echo $customer['employment_status'] === 'unemployed'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Unemployed
                            </option>

                            <option
                                value="student"
                                <?php echo $customer['employment_status'] === 'student'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Student
                            </option>

                            <option
                                value="retired"
                                <?php echo $customer['employment_status'] === 'retired'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Retired
                            </option>

                        </select>

                    </div>


                    <!-- SOURCE OF FUNDS -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Source of Funds
                        </label>

                        <select
                            name="source_of_funds"
                            class="form-select"
                        >

                            <option value="">
                                Select source
                            </option>

                            <option
                                value="salary"
                                <?php echo $customer['source_of_funds'] === 'salary'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Salary
                            </option>

                            <option
                                value="business"
                                <?php echo $customer['source_of_funds'] === 'business'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Business
                            </option>

                            <option
                                value="investment"
                                <?php echo $customer['source_of_funds'] === 'investment'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Investment
                            </option>

                            <option
                                value="inheritance"
                                <?php echo $customer['source_of_funds'] === 'inheritance'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Inheritance
                            </option>

                            <option
                                value="savings"
                                <?php echo $customer['source_of_funds'] === 'savings'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Savings
                            </option>

                            <option
                                value="other"
                                <?php echo $customer['source_of_funds'] === 'other'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                Other
                            </option>

                        </select>

                    </div>

                </div>


                <hr>


                <!-- PASSWORD -->

                <h5 class="mb-3">

                    <i class="ri-lock-password-line"></i>

                    Change Password

                </h5>

                <div class="alert alert-info">

                    <i class="ri-information-line"></i>

                    Leave the password fields empty if you do not
                    want to change the customer's password.

                </div>


                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            New Password
                        </label>

                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            autocomplete="new-password"
                        >

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            name="confirm_password"
                            class="form-control"
                            autocomplete="new-password"
                        >

                    </div>

                </div>


                <!-- BUTTONS -->

                <div class="d-flex justify-content-end gap-2 mt-4">

                    <a
                        href="customer-detail-<?php echo (int)$user_id; ?>"
                        class="btn btn-light"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        name="update_customer"
                        class="btn btn-primary"
                    >
                        <i class="ri-save-line"></i>
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
        </div>
        </div>
    </section>
</div>
<?php include "layout/footer.php"; ?>