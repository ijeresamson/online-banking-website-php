<?php include 'layout/top.php'; ?>

<?php

if(!isset($_SESSION['admin'])) {
    header('Location:' .ADMIN_URL. 'login');
    exit;
}

if (isset($_POST['form_update'])) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Make sure request is POST
        |--------------------------------------------------------------------------
        */

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Invalid request.");
        }


        // /*
        // |--------------------------------------------------------------------------
        // | CSRF Protection
        // |--------------------------------------------------------------------------
        // */

        if (
            !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
            !hash_equals(
                $_SESSION['csrf_token'],
                $_POST['csrf_token']
            )
        ) {
            throw new Exception(
                "Invalid request. Please refresh the page and try again."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Get admin ID
        |--------------------------------------------------------------------------
        */

        if (!isset($_SESSION['admin']['id'])) {
            throw new Exception("Your session has expired. Please login again.");
        }

        $adminId = (int) $_SESSION['admin']['id'];


        /*
        |--------------------------------------------------------------------------
        | Get and clean name/email
        |--------------------------------------------------------------------------
        */

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Validate name
        |--------------------------------------------------------------------------
        */

        if ($name === '') {
            throw new Exception("Full name cannot be empty.");
        }

        if (strlen($name) < 2) {
            throw new Exception("Full name must contain at least 2 characters.");
        }

        if (strlen($name) > 100) {
            throw new Exception("Full name is too long.");
        }


        /*
        |--------------------------------------------------------------------------
        | Validate email
        |--------------------------------------------------------------------------
        */

        if ($email === '') {
            throw new Exception("Email cannot be empty.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }


        // /*
        // |--------------------------------------------------------------------------
        // | Check if email belongs to another admin
        // |--------------------------------------------------------------------------
        // */

        // $statement = $pdo->prepare("SELECT id FROM admins
        //                             WHERE email = ? AND id != ? LIMIT 1 ");

        // $statement->execute([ $email, $adminId ]);

        // if ($statement->fetch()) {
        //     throw new Exception(
        //         "This email address is already being used by another admin."
        //     );
        // }


        /*
        |--------------------------------------------------------------------------
        | Get current admin information
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare("SELECT id, name, email, password, photo
                                    FROM admins WHERE id = ?  LIMIT 1 ");

        $statement->execute([$adminId]);

        $currentAdmin = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$currentAdmin) {
            throw new Exception("Admin account was not found.");
        }


        /*
        |--------------------------------------------------------------------------
        | Start database transaction
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Update name and email
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare("UPDATE admins SET name = ?, email = ?
                                     WHERE id = ? ");

        $statement->execute([
            $name,
            $email,
            $adminId
        ]);


        /*
        |--------------------------------------------------------------------------
        | Update password
        |--------------------------------------------------------------------------
        */

        $password = $_POST['password'] ?? '';
        $retypePassword = $_POST['retype_password'] ?? '';

        if ($password !== '' || $retypePassword !== '') {

            if ($password === '' || $retypePassword === '') {
                throw new Exception(
                    "Please enter both password fields."
                );
            }

            if (strlen($password) < 8) {
                throw new Exception(
                    "Password must be at least 8 characters long."
                );
            }

            if ($password !== $retypePassword) {
                throw new Exception(
                    "Passwords do not match."
                );
            }

            /*
            | Password strength
            */

            if (
                !preg_match('/[A-Z]/', $password) ||
                !preg_match('/[a-z]/', $password) ||
                !preg_match('/[0-9]/', $password) ||
                !preg_match('/[^A-Za-z0-9]/', $password)
            ) {
                throw new Exception(
                    "Password must contain an uppercase letter, lowercase letter, number and special character."
                );
            }

            /*
            | Prevent using the same password
            */

            if (password_verify($password, $currentAdmin['password'])) {
                throw new Exception(
                    "Your new password must be different from your current password."
                );
            }

            $passwordHash = password_hash( $password, PASSWORD_DEFAULT );

            if ($passwordHash === false) {
                throw new Exception(
                    "Unable to secure the new password."
                );
            }

            $statement = $pdo->prepare("UPDATE admins SET password = ?
                                         WHERE id = ? ");

            $statement->execute([
                $passwordHash,
                $adminId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Handle profile photo
        |--------------------------------------------------------------------------
        */

        $newPhoto = $currentAdmin['photo'];
        $oldPhoto = $currentAdmin['photo'];

        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(
                    "There was a problem uploading the photo."
                );
            }

            /*
            | Maximum size: 2MB
            */

            $maxSize = 2 * 1024 * 1024;

            if ($_FILES['photo']['size'] > $maxSize) {
                throw new Exception(
                    "Profile photo must not exceed 2MB."
                );
            }


            /*
            | Verify that the uploaded file is actually an image
            */

            $tmpName = $_FILES['photo']['tmp_name'];

            $imageInfo = getimagesize($tmpName);

            if ($imageInfo === false) {
                throw new Exception(
                    "Please upload a valid image."
                );
            }


            /*
            | Check MIME type
            */

            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if (!$finfo) {
                throw new Exception(
                    "Unable to validate the uploaded image."
                );
            }

            $mime = finfo_file(
                $finfo,
                $tmpName
            );

            finfo_close($finfo);


            $allowedMimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];


            if (!array_key_exists($mime, $allowedMimeTypes)) {
                throw new Exception(
                    "Only JPG, PNG and WEBP images are allowed."
                );
            }


            /*
            | Generate secure random filename
            */

            $extension = $allowedMimeTypes[$mime];

            $newPhoto = bin2hex(
                random_bytes(16)
            ) . '.' . $extension;


            /*
            | Upload directory
            */

            $uploadDirectory = __DIR__ . '/../uploads/';

            if (!is_dir($uploadDirectory)) {
                throw new Exception(
                    "Upload directory does not exist."
                );
            }


            $destination = $uploadDirectory . $newPhoto;


            /*
            | Move new image
            */

            if (!move_uploaded_file(
                $tmpName,
                $destination
            )) {
                throw new Exception(
                    "Unable to save the profile photo."
                );
            }


            /*
            | Update database
            */

            $statement = $pdo->prepare("UPDATE admins SET photo = ?
                                         WHERE id = ? ");

            $statement->execute([
                $newPhoto,
                $adminId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Commit database changes
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | Delete old photo AFTER successful database update
        |--------------------------------------------------------------------------
        */

        if (
            $newPhoto !== $oldPhoto && $oldPhoto !== ''
        ) {

            $oldPhotoPath =
                __DIR__ . '/../uploads/' . basename($oldPhoto);

            if (
                is_file($oldPhotoPath)
            ) {
                @unlink($oldPhotoPath);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Update session information
        |--------------------------------------------------------------------------
        */

        $_SESSION['admin']['name'] = $name;
        $_SESSION['admin']['email'] = $email;

        if ($newPhoto !== $oldPhoto) {
            $_SESSION['admin']['photo'] = $newPhoto;
        }


        /*
        |--------------------------------------------------------------------------
        | Regenerate session ID after sensitive changes
        |--------------------------------------------------------------------------
        */

        session_regenerate_id(true);


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        $_SESSION['success_message'] =
            "Your profile has been updated successfully.";

        header(
            'Location: ' . ADMIN_URL . 'profile'
        );

        exit;


    } catch (Exception $e) {


        /*
        |--------------------------------------------------------------------------
        | Rollback database transaction
        |--------------------------------------------------------------------------
        */

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        /*
        |--------------------------------------------------------------------------
        | Delete newly uploaded image if database update failed
        |--------------------------------------------------------------------------
        */

        if (
            isset($destination) &&
            is_file($destination)
        ) {
            @unlink($destination);
        }


        /*
        |--------------------------------------------------------------------------
        | Show error
        |--------------------------------------------------------------------------
        */

        $_SESSION['error_message'] =
            $e->getMessage();

    }
}

?>

<body>
<div id="app">
    <div class="main-wrapper">

             <?php include "layout/navbar.php"; ?>

        <?php include "layout/sidebar.php"; ?>

<div class="main-content">
    <section class="section">
        <div class="section-header">
            <h1>Edit Profile</h1>
        </div>
        <div class="section-body">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <?php
                            if(isset($error_message)) {
                                ?><script>alert("<?php echo $error_message; ?>")</script><?php
                            }
                            if(isset($success_message)) {
                                ?><script>alert("<?php echo $success_message; ?>")</script><?php
                            }
                            ?>

                          <form action="" method="POST" enctype="multipart/form-data">

                                <div class="row g-4">

                                    <!-- =========================================
                                        PROFILE PHOTO
                                    ========================================== -->
                                    <div class="col-lg-4 col-md-5">

                                        <div
                                            style="
                                                background:#ffffff;
                                                border:1px solid #e5e7eb;
                                                border-radius:14px;
                                                padding:30px 20px;
                                                text-align:center;
                                                height:100%;
                                            "
                                        >

                                            <h5
                                                style="
                                                    margin-bottom:6px;
                                                    font-weight:700;
                                                    color:#111827;
                                                "
                                            >
                                                Profile Photo
                                            </h5>

                                            <p
                                                style="
                                                    color:#6b7280;
                                                    font-size:13px;
                                                    margin-bottom:20px;
                                                "
                                            >
                                                Update your administrator profile picture
                                            </p>


                                            <!-- PROFILE IMAGE -->
                                            <div
                                                style="
                                                    width:150px;
                                                    height:150px;
                                                    margin:0 auto 20px;
                                                    position:relative;
                                                "
                                            >

                                                <?php if (empty($_SESSION['admin']['photo'])): ?>

                                                    <img
                                                        id="profilePreview"
                                                        src="<?php echo ADMIN_URL; ?>../uploads/default.png"
                                                        alt="Admin Profile"
                                                        style="
                                                            width:150px;
                                                            height:150px;
                                                            object-fit:cover;
                                                            border-radius:50%;
                                                            border:4px solid #f3f4f6;
                                                            box-shadow:0 5px 20px rgba(0,0,0,.08);
                                                        "
                                                    >

                                                <?php else: ?>

                                                    <img
                                                        id="profilePreview"
                                                        src="<?php echo ADMIN_URL; ?>../uploads/<?php echo htmlspecialchars($_SESSION['admin']['photo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        alt="Admin Profile"
                                                        style="
                                                            width:150px;
                                                            height:150px;
                                                            object-fit:cover;
                                                            border-radius:50%;
                                                            border:4px solid #f3f4f6;
                                                            box-shadow:0 5px 20px rgba(0,0,0,.08);
                                                        "
                                                    >

                                                <?php endif; ?>

                                            </div>


                                            <!-- FILE INPUT -->
                                            <label
                                                for="profilePhoto"
                                                style="
                                                    display:inline-block;
                                                    padding:10px 18px;
                                                    background:#f3f4f6;
                                                    color:#374151;
                                                    border-radius:8px;
                                                    cursor:pointer;
                                                    font-size:14px;
                                                    font-weight:600;
                                                    transition:.2s;
                                                "
                                            >
                                                <i class="fas fa-camera me-1"></i>
                                                Choose Photo
                                            </label>

                                            <input
                                                type="file"
                                                id="profilePhoto"
                                                name="photo"
                                                accept="image/jpeg,image/png,image/webp"
                                                style="display:none;"
                                            >

                                            <div
                                                style="
                                                    margin-top:12px;
                                                    color:#9ca3af;
                                                    font-size:12px;
                                                    line-height:1.5;
                                                "
                                            >
                                                JPG, PNG or WEBP<br>
                                                Maximum size: 2MB
                                            </div>

                                        </div>

                                    </div>


                                    <!-- =========================================
                                        ACCOUNT INFORMATION
                                    ========================================== -->
                                    <div class="col-lg-8 col-md-7">

                                        <div
                                            style="
                                                background:#ffffff;
                                                border:1px solid #e5e7eb;
                                                border-radius:14px;
                                                padding:30px;
                                            "
                                        >

                                            <div style="margin-bottom:25px;">

                                                <h5
                                                    style="
                                                        margin:0 0 5px;
                                                        font-weight:700;
                                                        color:#111827;
                                                    "
                                                >
                                                    Account Information
                                                </h5>

                                                <p
                                                    style="
                                                        margin:0;
                                                        color:#6b7280;
                                                        font-size:13px;
                                                    "
                                                >
                                                    Update your administrator account details.
                                                </p>

                                            </div>


                                            <!-- NAME -->
                                            <div class="mb-4">

                                                <label
                                                    class="form-label"
                                                    style="
                                                        font-weight:600;
                                                        color:#374151;
                                                    "
                                                >
                                                    Full Name <span style="color:#dc2626;">*</span>
                                                </label>

                                                <div style="position:relative;">

                                                    <i
                                                        class="fas fa-user"
                                                        style="
                                                            position:absolute;
                                                            left:14px;
                                                            top:50%;
                                                            transform:translateY(-50%);
                                                            color:#9ca3af;
                                                        "
                                                    ></i>

                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="name"
                                                        value="<?php echo htmlspecialchars(
                                                            $_SESSION['admin']['name'] ?? '',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>"
                                                        placeholder="Enter your full name"
                                                        autocomplete="name"
                                                        required
                                                        style="
                                                            padding-left:42px;
                                                            height:46px;
                                                            border-radius:8px;
                                                        "
                                                    >

                                                </div>

                                            </div>


                                            <!-- EMAIL -->
                                            <div class="mb-4">

                                                <label
                                                    class="form-label"
                                                    style="
                                                        font-weight:600;
                                                        color:#374151;
                                                    "
                                                >
                                                    Email Address <span style="color:#dc2626;">*</span>
                                                </label>

                                                <div style="position:relative;">

                                                    <i
                                                        class="fas fa-envelope"
                                                        style="
                                                            position:absolute;
                                                            left:14px;
                                                            top:50%;
                                                            transform:translateY(-50%);
                                                            color:#9ca3af;
                                                        "
                                                    ></i>

                                                    <input
                                                        type="email"
                                                        class="form-control"
                                                        name="email"
                                                        value="<?php echo htmlspecialchars(
                                                            $_SESSION['admin']['email'] ?? '',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ); ?>"
                                                        placeholder="Enter your email"
                                                        autocomplete="email"
                                                        required
                                                        style="
                                                            padding-left:42px;
                                                            height:46px;
                                                            border-radius:8px;
                                                        "
                                                    >

                                                </div>

                                            </div>


                                            <!-- =========================================
                                                PASSWORD SECTION
                                            ========================================== -->

                                            <div
                                                style="
                                                    border-top:1px solid #e5e7eb;
                                                    margin:25px 0;
                                                    padding-top:25px;
                                                "
                                            >

                                                <h6
                                                    style="
                                                        font-weight:700;
                                                        color:#111827;
                                                        margin-bottom:5px;
                                                    "
                                                >
                                                    Change Password
                                                </h6>

                                                <p
                                                    style="
                                                        color:#6b7280;
                                                        font-size:13px;
                                                        margin-bottom:20px;
                                                    "
                                                >
                                                    Leave these fields empty if you don't want to change
                                                    your password.
                                                </p>


                                                <div class="row">

                                                    <!-- PASSWORD -->
                                                    <div class="col-md-6 mb-4">

                                                        <label
                                                            class="form-label"
                                                            style="
                                                                font-weight:600;
                                                                color:#374151;
                                                            "
                                                        >
                                                            New Password
                                                        </label>

                                                        <div style="position:relative;">

                                                            <i
                                                                class="fas fa-lock"
                                                                style="
                                                                    position:absolute;
                                                                    left:14px;
                                                                    top:50%;
                                                                    transform:translateY(-50%);
                                                                    color:#9ca3af;
                                                                "
                                                            ></i>

                                                            <input
                                                                type="password"
                                                                class="form-control"
                                                                name="password"
                                                                placeholder="Enter new password"
                                                                autocomplete="new-password"
                                                                style="
                                                                    padding-left:42px;
                                                                    height:46px;
                                                                    border-radius:8px;
                                                                "
                                                            >

                                                        </div>

                                                    </div>


                                                    <!-- RETYPE PASSWORD -->
                                                    <div class="col-md-6 mb-4">

                                                        <label
                                                            class="form-label"
                                                            style="
                                                                font-weight:600;
                                                                color:#374151;
                                                            "
                                                        >
                                                            Confirm Password
                                                        </label>

                                                        <div style="position:relative;">

                                                            <i
                                                                class="fas fa-lock"
                                                                style="
                                                                    position:absolute;
                                                                    left:14px;
                                                                    top:50%;
                                                                    transform:translateY(-50%);
                                                                    color:#9ca3af;
                                                                "
                                                            ></i>

                                                            <input
                                                                type="password"
                                                                class="form-control"
                                                                name="retype_password"
                                                                placeholder="Confirm new password"
                                                                autocomplete="new-password"
                                                                style="
                                                                    padding-left:42px;
                                                                    height:46px;
                                                                    border-radius:8px;
                                                                "
                                                            >

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>


                                            <!-- =========================================
                                                UPDATE BUTTON
                                            ========================================== -->

                                            <div
                                                style="
                                                    display:flex;
                                                    justify-content:flex-end;
                                                    gap:10px;
                                                    padding-top:5px;
                                                "
                                            >

                                                <button
                                                    type="submit"
                                                    name="form_update"
                                                    class="btn btn-primary"
                                                    style="
                                                        min-width:150px;
                                                        height:46px;
                                                        border-radius:8px;
                                                        font-weight:600;
                                                    "
                                                >
                                                    <i class="fas fa-save me-1"></i>
                                                    Save Changes
                                                </button>

                                            </div>

                                        </div>

                                    </div>

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