<?php
require_once __DIR__ . '/config.php';
// Prefer context if both set; clear both just in case
$wasAdmin = !empty($_SESSION['admin_logged_in']);
unset($_SESSION['admin_logged_in'], $_SESSION['admin_user_id'], $_SESSION['requires_password_reset']);
if ($wasAdmin) {
    // redirect to login for admins
    header('Location: login.php');
    exit;
}
unset($_SESSION['client_logged_in']);
// fallback redirect
header('Location: index.php');
exit;
