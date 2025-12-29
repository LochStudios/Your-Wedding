<?php
require_once __DIR__ . '/config.php';

// Determine current role and whether admin is acting as venue
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isVenueOwner = !empty($_SESSION['venue_logged_in']);
$actingVenueId = null;
$venueName = '';

if ($isAdmin && !empty($_GET['as_venue'])) {
    // Admin acting as venue
    $actingVenueId = (int) $_GET['as_venue'];
    $venueId = $actingVenueId;
    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT name FROM venues WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $actingVenueId);
    $stmt->execute();
    $stmt->bind_result($venueName);
    $stmt->fetch();
    $stmt->close();
    $venueName = $venueName ?? 'Venue';
} elseif ($isVenueOwner) {
    $venueId = (int) $_SESSION['venue_logged_in'];
    $venueName = $_SESSION['venue_name'] ?? 'Venue';
} else {
    header('Location: venue_login.php');
    exit;
}

// Build query parameter for passing venue context in links
$venueParam = ($isAdmin && $actingVenueId !== null) ? '?as_venue=' . $actingVenueId : '';
$venueParamAmp = ($isAdmin && $actingVenueId !== null) ? '&as_venue=' . $actingVenueId : '';

if (!isset($conn)) {
    $conn = get_db_connection();
}
$errors = [];
$message = '';
$editing = false;
$teamId = null;
$name = '';
$email = '';
$username = '';
$canCreateClients = 1;
$canCreateAlbums = 1;
$canUploadPhotos = 1;
$canViewAnalytics = 0;

// Check if editing
if (!empty($_GET['id'])) {
    $editing = true;
    $teamId = (int) $_GET['id'];
    $stmt = $conn->prepare('SELECT name, email, username, can_create_clients, can_create_albums, can_upload_photos, can_view_analytics FROM venue_team WHERE id = ? AND venue_id = ? LIMIT 1');
    $stmt->bind_param('ii', $teamId, $venueId);
    $stmt->execute();
    $stmt->bind_result($name, $email, $username, $canCreateClients, $canCreateAlbums, $canUploadPhotos, $canViewAnalytics);
    if (!$stmt->fetch()) {
        $errors[] = 'Team member not found or access denied.';
        $editing = false;
    }
    $stmt->close();
}

// Handle delete
if (!empty($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $stmt = $conn->prepare('DELETE FROM venue_team WHERE id = ? AND venue_id = ?');
    $stmt->bind_param('ii', $deleteId, $venueId);
    $stmt->execute();
    $stmt->close();
    $_SESSION['venue_flash'] = 'Team member deleted successfully.';
    header('Location: venue_team.php' . $venueParam);
    exit;
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editing = !empty($_POST['team_id']);
    if ($editing) {
        $teamId = (int) $_POST['team_id'];
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $canCreateClients = !empty($_POST['can_create_clients']) ? 1 : 0;
    $canCreateAlbums = !empty($_POST['can_create_albums']) ? 1 : 0;
    $canUploadPhotos = !empty($_POST['can_upload_photos']) ? 1 : 0;
    $canViewAnalytics = !empty($_POST['can_view_analytics']) ? 1 : 0;
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if (!$editing && ($password === '' || $confirm === '')) {
        $errors[] = 'Password fields are required.';
    }
    if ($password !== '' && $password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    // Check username uniqueness
    if (!empty($username)) {
        if ($editing) {
            $stmt = $conn->prepare('SELECT id FROM venue_team WHERE username = ? AND id != ? LIMIT 1');
            $stmt->bind_param('si', $username, $teamId);
        } else {
            $stmt = $conn->prepare('SELECT id FROM venue_team WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'That username is already taken.';
        }
        $stmt->close();
    }
    if (empty($errors)) {
        if ($editing) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE venue_team SET name = ?, email = ?, username = ?, password_hash = ?, can_create_clients = ?, can_create_albums = ?, can_upload_photos = ?, can_view_analytics = ? WHERE id = ? AND venue_id = ?');
                $stmt->bind_param('ssssiiiii', $name, $email, $username, $hash, $canCreateClients, $canCreateAlbums, $canUploadPhotos, $canViewAnalytics, $teamId, $venueId);
            } else {
                $stmt = $conn->prepare('UPDATE venue_team SET name = ?, email = ?, username = ?, can_create_clients = ?, can_create_albums = ?, can_upload_photos = ?, can_view_analytics = ? WHERE id = ? AND venue_id = ?');
                $stmt->bind_param('sssiiiii', $name, $email, $username, $canCreateClients, $canCreateAlbums, $canUploadPhotos, $canViewAnalytics, $teamId, $venueId);
            }
            if (!$stmt->execute()) {
                $errors[] = 'Unable to update team member: ' . $conn->error;
            } else {
                $message = 'Team member updated successfully.';
            }
            $stmt->close();
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO venue_team (venue_id, name, email, username, password_hash, can_create_clients, can_create_albums, can_upload_photos, can_view_analytics) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('issssiii', $venueId, $name, $email, $username, $hash, $canCreateClients, $canCreateAlbums, $canUploadPhotos, $canViewAnalytics);
            if (!$stmt->execute()) {
                $errors[] = 'Unable to create team member: ' . $conn->error;
            } else {
                $_SESSION['venue_flash'] = 'Team member created successfully.';
                header('Location: venue_team.php' . $venueParam);
                exit;
            }
            $stmt->close();
        }
    }
}

// Get all team members for this venue
$teamMembers = [];
$stmt = $conn->prepare('SELECT id, name, email, username, can_create_clients, can_create_albums, can_upload_photos, can_view_analytics, created_at FROM venue_team WHERE venue_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $venueId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $teamMembers[] = $row;
}
$stmt->close();

$flashMessage = $_SESSION['venue_flash'] ?? null;
if (isset($_SESSION['venue_flash'])) {
    unset($_SESSION['venue_flash']);
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php echo $editing ? 'Edit Team Member' : 'Manage Team'; ?> | Your Wedding</title>
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
                <h1 class="title"><?php echo $editing ? 'Edit Team Member' : 'Manage Team'; ?></h1>
                <p class="subtitle">Control who can access your venue account</p>
                <?php if ($flashMessage): ?>
                    <div class="notification is-success"><?php echo htmlspecialchars($flashMessage); ?></div>
                <?php endif; ?>
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
                    <h2 class="title is-5"><?php echo $editing ? 'Edit Team Member' : 'Add Team Member'; ?></h2>
                    <form method="post">
                        <?php if ($editing): ?>
                            <input type="hidden" name="team_id" value="<?php echo (int) $teamId; ?>" />
                        <?php endif; ?>
                        <div class="field">
                            <label class="label">Name</label>
                            <div class="control">
                                <input class="input" type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="John Doe" required />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Email</label>
                            <div class="control">
                                <input class="input" type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="john@example.com" required />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Username</label>
                            <div class="control">
                                <input class="input" type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="johndoe" required />
                            </div>
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
                        <div class="field">
                            <label class="label">Permissions</label>
                            <div class="control">
                                <label class="checkbox">
                                    <input type="checkbox" name="can_create_clients" value="1" <?php echo $canCreateClients ? 'checked' : ''; ?> />
                                    Can create/edit clients
                                </label>
                            </div>
                            <div class="control mt-2">
                                <label class="checkbox">
                                    <input type="checkbox" name="can_create_albums" value="1" <?php echo $canCreateAlbums ? 'checked' : ''; ?> />
                                    Can create/edit galleries
                                </label>
                            </div>
                            <div class="control mt-2">
                                <label class="checkbox">
                                    <input type="checkbox" name="can_upload_photos" value="1" <?php echo $canUploadPhotos ? 'checked' : ''; ?> />
                                    Can upload photos
                                </label>
                            </div>
                            <div class="control mt-2">
                                <label class="checkbox">
                                    <input type="checkbox" name="can_view_analytics" value="1" <?php echo $canViewAnalytics ? 'checked' : ''; ?> />
                                    Can view analytics
                                </label>
                            </div>
                        </div>
                        <div class="field is-grouped">
                            <div class="control">
                                <button class="button is-link" type="submit"><?php echo $editing ? 'Update' : 'Create'; ?> Team Member</button>
                            </div>
                            <div class="control">
                                <a class="button is-light" href="venue_team.php">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
                <h2 class="title is-4 mt-6">Team Members</h2>
                <?php if (empty($teamMembers)): ?>
                    <div class="notification is-info">
                        No team members yet. Add your first team member above.
                    </div>
                <?php else: ?>
                    <div class="box">
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Permissions</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamMembers as $tm): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tm['name']); ?></td>
                                            <td><?php echo htmlspecialchars($tm['username']); ?></td>
                                            <td><?php echo htmlspecialchars($tm['email']); ?></td>
                                            <td>
                                                <?php
                                                $perms = [];
                                                if ($tm['can_create_clients']) $perms[] = 'Clients';
                                                if ($tm['can_create_albums']) $perms[] = 'Albums';
                                                if ($tm['can_upload_photos']) $perms[] = 'Upload';
                                                if ($tm['can_view_analytics']) $perms[] = 'Analytics';
                                                echo htmlspecialchars(implode(', ', $perms) ?: 'None');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($tm['created_at']); ?></td>
                                            <td>
                                                <div class="buttons">
                                                    <a href="venue_team.php?id=<?php echo (int) $tm['id']; ?>" class="button is-small is-info">Edit</a>
                                                    <a href="venue_team.php?delete_id=<?php echo (int) $tm['id']; ?>" 
                                                       class="button is-small is-danger" 
                                                       onclick="return confirm('Delete this team member?');">Delete</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </body>
</html>