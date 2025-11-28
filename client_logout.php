<?php
require_once __DIR__ . '/config.php';
unset($_SESSION['client_logged_in']);
header('Location: index.php');
exit;
