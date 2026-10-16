
<?php 
if(!isset($_SESSION['admin'])) {
header('Location: login');
exit;
}

?>
        <div class="navbar-bg"></div>
        <nav class="navbar navbar-expand-lg main-navbar">
            <form class="form-inline mr-auto">
                <ul class="navbar-nav mr-3">
                    <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg"><i class="fas fa-bars"></i></a></li>
                </ul>
            </form>
            <ul class="navbar-nav navbar-right justify-content-end rightsidetop">
                <li class="nav-link">
                    <a href="<?php echo BASE_URL;  ?>" target="_blank" class="btn btn-warning">Front End</a>
                </li>
         <li class="nav-link">
    Logged in as:
    <?php echo htmlspecialchars($_SESSION['admin']['name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?>
</li>

<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle d-flex align-items-center"
       href="#"
       data-bs-toggle="dropdown">

        <?php
        $photo = !empty($_SESSION['admin']['photo'])
            ? basename($_SESSION['admin']['photo'])
            : 'default.png';
        ?>

        <img
            src="<?php echo BASE_URL; ?>uploads/<?php echo rawurlencode($photo); ?>"
            alt="Admin"
            width="38"
            height="38"
            style="
                width:38px;
                height:38px;
                object-fit:cover;
                border-radius:50%;
                border:2px solid #eee;
                margin-right:5px;
            "
        >

    </a>

    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <a class="dropdown-item"
               href="<?php echo ADMIN_URL; ?>profile">
                <i class="fas fa-user"></i> Profile
            </a>
        </li>

        <li>
            <a class="dropdown-item text-danger"
               href="<?php echo ADMIN_URL; ?>admin-logout"
               onclick="return confirm('Are you sure you want to logout?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</li>
            </ul>
        </nav>