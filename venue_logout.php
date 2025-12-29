<?php
require_once __DIR__ . '/config.php';
unset($_SESSION['venue_logged_in'], $_SESSION['venue_name'], $_SESSION['venue_team_logged_in'], $_SESSION['venue_team_name'], $_SESSION['venue_id'], $_SESSION['can_create_clients'], $_SESSION['can_create_albums'], $_SESSION['can_upload_photos'], $_SESSION['can_view_analytics']);
header('Location: venue_login.php');
exit;
