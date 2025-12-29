<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = get_db_connection();
$errors = [];
$message = '';
$editing = false;
$venueId = null;
$venueName = '';
$username = '';
$contactEmail = '';
$contactPhone = '';
$s3FolderPrefix = '';

// Check if editing
if (!empty($_GET['id'])) {
    $editing = true;
    $venueId = (int) $_GET['id'];
    $stmt = $conn->prepare('SELECT venue_name, username, contact_email, contact_phone, s3_folder_prefix FROM venues WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $venueId);
    $stmt->execute();
    $stmt->bind_result($venueName, $username, $contactEmail, $contactPhone, $s3FolderPrefix);
    if (!$stmt->fetch()) {
        $errors[] = 'Venue not found.';
        $editing = false;
    }
    $stmt->close();
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_venue_id'])) {
    $deleteId = (int) $_POST['delete_venue_id'];
    $stmt = $conn->prepare('DELETE FROM venues WHERE id = ?');
    $stmt->bind_param('i', $deleteId);
    if ($stmt->execute()) {
        $_SESSION['admin_flash'] = 'Venue deleted successfully.';
    } else {
        $_SESSION['admin_flash'] = 'Error deleting venue: ' . $conn->error;
    }
    $stmt->close();
    header('Location: create_venue.php');
    exit;
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['delete_venue_id'])) {
    $editing = !empty($_POST['venue_id']);
    if ($editing) {
        $venueId = (int) $_POST['venue_id'];
    }
    $venueName = trim($_POST['venue_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $contactEmail = trim($_POST['contact_email'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $s3FolderPrefix = trim($_POST['s3_folder_prefix'] ?? '');
    if ($venueName === '') {
        $errors[] = 'Venue name is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if (!$editing && ($password === '' || $confirm === '')) {
        $errors[] = 'Password fields are required when creating a venue.';
    }
    if ($password !== '' && $password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($errors)) {
        if ($editing) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE venues SET venue_name = ?, username = ?, password_hash = ?, contact_email = ?, contact_phone = ?, s3_folder_prefix = ? WHERE id = ?');
                $stmt->bind_param('ssssssi', $venueName, $username, $hash, $contactEmail, $contactPhone, $s3FolderPrefix, $venueId);
            } else {
                $stmt = $conn->prepare('UPDATE venues SET venue_name = ?, username = ?, contact_email = ?, contact_phone = ?, s3_folder_prefix = ? WHERE id = ?');
                $stmt->bind_param('sssssi', $venueName, $username, $contactEmail, $contactPhone, $s3FolderPrefix, $venueId);
            }
            if (!$stmt->execute()) {
                $errors[] = 'Unable to update venue: ' . $conn->error;
            } else {
                $message = 'Venue updated successfully.';
            }
            $stmt->close();
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('INSERT INTO venues (venue_name, username, password_hash, contact_email, contact_phone, s3_folder_prefix) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssss', $venueName, $username, $hash, $contactEmail, $contactPhone, $s3FolderPrefix);
            if (!$stmt->execute()) {
                $errors[] = 'Unable to create venue: ' . $conn->error;
            } else {
                $_SESSION['admin_flash'] = 'Venue created successfully.';
                header('Location: create_venue.php');
                exit;
            }
            $stmt->close();
        }
    }
}

// Get all venues for table
$allVenues = [];
$result = $conn->query('SELECT id, venue_name, username, contact_email, contact_phone, created_at FROM venues ORDER BY venue_name');
while ($row = $result->fetch_assoc()) {
    $allVenues[] = $row;
}

// Flash message
$flash = $_SESSION['admin_flash'] ?? '';
unset($_SESSION['admin_flash']);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php echo $editing ? 'Edit Venue' : 'Create Venue'; ?> | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include 'nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title"><?php echo $editing ? 'Edit Venue' : 'Create Venue'; ?></h1>
                <p class="subtitle"><?php echo $editing ? 'Update venue account details' : 'Add a new venue account'; ?></p>
                <?php if ($flash): ?>
                    <div class="notification is-success"><?php echo htmlspecialchars($flash); ?></div>
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
                    <form method="post">
                        <?php if ($editing): ?>
                            <input type="hidden" name="venue_id" value="<?php echo (int) $venueId; ?>" />
                        <?php endif; ?>
                        <div class="field">
                            <label class="label">Venue Name</label>
                            <div class="control">
                                <input class="input" type="text" name="venue_name" value="<?php echo htmlspecialchars($venueName); ?>" placeholder="Downtown Event Center" required />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Username</label>
                            <div class="control">
                                <input class="input" type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="venue_username" required />
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
                            <label class="label">Contact Email (optional)</label>
                            <div class="control">
                                <input class="input" type="email" name="contact_email" value="<?php echo htmlspecialchars($contactEmail); ?>" placeholder="contact@venue.com" />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Contact Phone (optional)</label>
                            <div class="control">
                                <input class="input" type="text" name="contact_phone" value="<?php echo htmlspecialchars($contactPhone); ?>" placeholder="555-1234" />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">S3 Folder Prefix (optional)</label>
                            <div class="control">
                                <input class="input" type="text" name="s3_folder_prefix" value="<?php echo htmlspecialchars($s3FolderPrefix); ?>" placeholder="venue-name/" />
                            </div>
                            <p class="help">Root folder for this venue's photos in S3.</p>
                        </div>
                        <div class="field is-grouped">
                            <div class="control">
                                <button class="button is-link" type="submit">
                                    <?php echo $editing ? 'Update' : 'Create'; ?> Venue
                                </button>
                            </div>
                            <div class="control">
                                <a class="button is-light" href="create_venue.php">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
                <h2 class="title is-4 mt-6">All Venues</h2>
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable">
                        <thead>
                            <tr>
                                <th>Venue Name</th>
                                <th>Username</th>
                                <th>Contact Email</th>
                                <th>Contact Phone</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allVenues)): ?>
                                <tr>
                                    <td colspan="6" class="has-text-centered">No venues yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allVenues as $v): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($v['venue_name']); ?></td>
                                        <td><?php echo htmlspecialchars($v['username']); ?></td>
                                        <td><?php echo htmlspecialchars($v['contact_email'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($v['contact_phone'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($v['created_at']); ?></td>
                                        <td>
                                            <a href="create_venue.php?id=<?php echo (int) $v['id']; ?>" class="button is-small is-info">Edit</a>
                                            <form method="post" class="inline-form" onsubmit="return confirm('Delete this venue? This will NOT delete their clients or galleries.');">
                                                <input type="hidden" name="delete_venue_id" value="<?php echo (int) $v['id']; ?>" />
                                                <button type="submit" class="button is-small is-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </body>
</html>