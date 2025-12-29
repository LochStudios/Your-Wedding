<?php
require_once __DIR__ . '/config.php';

// Determine current role and whether admin is acting as venue
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isTeamMember = !empty($_SESSION['venue_team_logged_in']);
$isVenueOwner = !empty($_SESSION['venue_logged_in']);
$actingVenueId = null;

if ($isAdmin && !empty($_GET['as_venue'])) {
    // Admin acting as venue
    $actingVenueId = (int) $_GET['as_venue'];
    $venueId = $actingVenueId;
} elseif ($isTeamMember) {
    // Check permissions for team members
    if (empty($_SESSION['can_create_clients'])) {
        $_SESSION['venue_flash'] = 'You do not have permission to create or edit clients.';
        header('Location: venue_dashboard.php');
        exit;
    }
    $venueId = (int) $_SESSION['venue_id'];
} elseif ($isVenueOwner) {
    $venueId = (int) $_SESSION['venue_logged_in'];
} else {
    header('Location: venue_login.php');
    exit;
}

// Build query parameter for passing venue context in links
$venueParam = ($isAdmin && $actingVenueId !== null) ? '?as_venue=' . $actingVenueId : '';
$venueParamAmp = ($isAdmin && $actingVenueId !== null) ? '&as_venue=' . $actingVenueId : '';

$conn = get_db_connection();
$errors = [];
$message = '';
$editing = false;
$clientId = null;
$title1 = 'Mr';
$title2 = 'Mrs';
$familyName = '';
$username = '';
$email = '';
$displayName = '';

// Check if editing
if (!empty($_GET['id'])) {
    $editing = true;
    $clientId = (int) $_GET['id'];
    $stmt = $conn->prepare('SELECT username, email, display_name, title1, title2, family_name FROM clients WHERE id = ? AND venue_id = ? LIMIT 1');
    $stmt->bind_param('ii', $clientId, $venueId);
    $stmt->execute();
    $stmt->bind_result($username, $email, $displayName, $title1, $title2, $familyName);
    if (!$stmt->fetch()) {
        $errors[] = 'Client not found or access denied.';
        $editing = false;
    }
    $stmt->close();
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editing = !empty($_POST['client_id']);
    if ($editing) {
        $clientId = (int) $_POST['client_id'];
    }
    $title1 = trim($_POST['title1'] ?? 'Mr');
    $title2 = trim($_POST['title2'] ?? 'Mrs');
    $familyName = trim($_POST['family_name'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $username = trim($_POST['username'] ?? '') ?: null;
    $email = trim($_POST['email'] ?? '') ?: null;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($familyName === '') {
        $errors[] = 'Family name is required.';
    }
    if (!$editing && ($password === '' || $confirm === '')) {
        $errors[] = 'Password fields are required.';
    }
    if ($password !== '' && $password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($errors)) {
        if ($displayName === '') {
            $displayName = sprintf('%s & %s %s', $title1 ?: 'Mr', $title2 ?: 'Mrs', $familyName);
        }
        if ($editing) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE clients SET username = ?, email = ?, display_name = ?, title1 = ?, title2 = ?, family_name = ?, password_hash = ? WHERE id = ? AND venue_id = ?');
                $stmt->bind_param('sssssssii', $username, $email, $displayName, $title1, $title2, $familyName, $hash, $clientId, $venueId);
            } else {
                $stmt = $conn->prepare('UPDATE clients SET username = ?, email = ?, display_name = ?, title1 = ?, title2 = ?, family_name = ? WHERE id = ? AND venue_id = ?');
                $stmt->bind_param('ssssssii', $username, $email, $displayName, $title1, $title2, $familyName, $clientId, $venueId);
            }
            if (!$stmt->execute()) {
                $errors[] = 'Unable to update client: ' . $conn->error;
            } else {
                $message = 'Client updated successfully.';
            }
            $stmt->close();
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO clients (venue_id, username, email, display_name, title1, title2, family_name, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isssssss', $venueId, $username, $email, $displayName, $title1, $title2, $familyName, $hash);
            if (!$stmt->execute()) {
                $errors[] = 'Unable to create client: ' . $conn->error;
            } else {
                $_SESSION['venue_flash'] = 'Client created successfully.';
                header('Location: venue_dashboard.php' . $venueParam);
                exit;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php echo $editing ? 'Edit Client' : 'Create Client'; ?> | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <nav class="navbar">
            <div class="navbar-brand">
                <a class="navbar-item" href="/"><strong>LochStudios</strong></a>
            </div>
            <div class="navbar-menu">
                <div class="navbar-start">
                    <a class="navbar-item" href="venue_dashboard.php">Dashboard</a>
                </div>
            </div>
        </nav>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title"><?php echo $editing ? 'Edit Client' : 'Create Client'; ?></h1>
                <p class="subtitle"><?php echo $editing ? 'Update client details' : 'Add a new client to your venue'; ?></p>
                <?php if ($message): ?>
                    <div class="notification is-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="notification is-danger">
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?php echo htmlspecialchars($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="box">
                    <form method="post">
                        <?php if ($editing): ?>
                            <input type="hidden" name="client_id" value="<?php echo (int) $clientId; ?>" />
                        <?php endif; ?>
                        <div class="field is-horizontal">
                            <div class="field-body">
                                <div class="field">
                                    <label class="label">Title</label>
                                    <div class="control">
                                        <div class="select">
                                            <select name="title1">
                                                <option value="Mr" <?php echo $title1 === 'Mr' ? 'selected' : ''; ?>>Mr</option>
                                                <option value="Mrs" <?php echo $title1 === 'Mrs' ? 'selected' : ''; ?>>Mrs</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">&amp;</label>
                                    <div class="control">
                                        <div class="select">
                                            <select name="title2">
                                                <option value="Mr" <?php echo $title2 === 'Mr' ? 'selected' : ''; ?>>Mr</option>
                                                <option value="Mrs" <?php echo $title2 === 'Mrs' ? 'selected' : ''; ?>>Mrs</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">Family Name</label>
                                    <div class="control">
                                        <input class="input" type="text" name="family_name" value="<?php echo htmlspecialchars($familyName); ?>" placeholder="Smith" required />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Display Name (optional)</label>
                            <div class="control">
                                <input class="input" type="text" name="display_name" value="<?php echo htmlspecialchars($displayName ?? ''); ?>" placeholder="Auto-generated" />
                            </div>
                            <p class="help">Leave blank to auto-generate.</p>
                        </div>
                        <div class="field">
                            <label class="label">Username/Email (optional)</label>
                            <div class="control">
                                <input class="input" type="text" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" placeholder="client@example.com" />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Email Address (optional)</label>
                            <div class="control">
                                <input class="input" type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="client@example.com" />
                            </div>
                            <p class="help">Email address for sending analytics reports.</p>
                        </div>
                        <div class="field">
                            <label class="label">Password<?php echo $editing ? ' (leave blank to keep current)' : ''; ?></label>
                            <div class="control">
                                <input class="input" type="password" name="password" <?php echo $editing ? '' : 'required'; ?> />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Confirm Password</label>
                            <div class="control">
                                <input class="input" type="password" name="confirm_password" <?php echo $editing ? '' : 'required'; ?> />
                            </div>
                        </div>
                        <div class="field is-grouped">
                            <div class="control">
                                <button class="button is-link" type="submit"><?php echo $editing ? 'Update' : 'Create'; ?> Client</button>
                            </div>
                            <div class="control">
                                <a class="button is-light" href="venue_dashboard.php">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </body>
</html>