<?php
/* Reusable navigation bar partial */
// Ensure config is loaded in caller; this file assumes session started and config.php loaded already.
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isClient = !empty($_SESSION['client_logged_in']);
?>
<nav class="navbar" role="navigation" aria-label="main navigation">
    <div class="navbar-brand">
        <a class="navbar-item" href="/">
            <strong>LochStudios</strong>
        </a>
        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="mainNav">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>
    <div id="mainNav" class="navbar-menu">
        <div class="navbar-start">
            <a class="navbar-item" href="/">Home</a>
            <?php if ($isAdmin): ?>
                <a class="navbar-item" href="dashboard.php">Dashboard</a>
                <a class="navbar-item" href="create_album.php">Create Album</a>
                <a class="navbar-item" href="create_client.php">Create Client</a>
            <?php endif; ?>
            <?php if ($isClient): ?>
                <a class="navbar-item" href="client_dashboard.php">My Galleries</a>
            <?php endif; ?>
            <a class="navbar-item" href="gallery.php">Find Gallery</a>
        </div>
        <div class="navbar-end">
            <?php if ($isAdmin): ?>
                <div class="navbar-item">
                    <div class="buttons">
                        <a class="button is-light" href="change_password.php">Change password</a>
                        <a class="button is-danger" href="logout.php">Sign out</a>
                    </div>
                </div>
            <?php elseif ($isClient): ?>
                <div class="navbar-item">
                    <div class="buttons">
                        <a class="button is-light" href="change_client_password.php">Change Password</a>
                        <a class="button is-danger" href="client_logout.php">Sign out</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="navbar-item">
                    <div class="buttons">
                        <?php if (is_admin_portal_visible()): ?>
                        <a class="button is-primary" href="login.php">Admin Portal</a>
                        <?php endif; ?>
                        <a class="button is-light" href="client_login.php">Client Login</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const burgers = Array.from(document.querySelectorAll('.navbar-burger'));
        burgers.forEach((b) => {
            b.addEventListener('click', () => {
                const target = document.getElementById(b.dataset.target);
                b.classList.toggle('is-active');
                if (target) target.classList.toggle('is-active');
            });
        });
    });
</script>
