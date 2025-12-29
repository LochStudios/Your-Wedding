<?php
require_once __DIR__ . '/config.php';
unset($_SESSION['venue_logged_in'], $_SESSION['venue_name']);
header('Location: venue_login.php');
exit;
