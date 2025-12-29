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
    if (empty($_SESSION['can_create_albums'])) {
        $_SESSION['venue_flash'] = 'You do not have permission to create or edit galleries.';
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
$albumId = null;
$albumName = '';
$slug = '';
$slugPassword = '';
$s3Folder = '';
$clientId = null;

// Check if editing
if (!empty($_GET['id'])) {
    $editing = true;
    $albumId = (int) $_GET['id'];
    $stmt = $conn->prepare('SELECT album_name, slug, slug_password, s3_folder, client_id FROM albums WHERE id = ? AND venue_id = ? LIMIT 1');
    $stmt->bind_param('ii', $albumId, $venueId);
    $stmt->execute();
    $stmt->bind_result($albumName, $slug, $slugPassword, $s3Folder, $clientId);
    if (!$stmt->fetch()) {
        $errors[] = 'Gallery not found or access denied.';
        $editing = false;
    }
    $stmt->close();
}

// Get venue's clients for dropdown
$venueClients = [];
$stmt = $conn->prepare('SELECT id, display_name FROM clients WHERE venue_id = ? ORDER BY display_name');
$stmt->bind_param('i', $venueId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $venueClients[] = $row;
}
$stmt->close();

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editing = !empty($_POST['album_id']);
    if ($editing) {
        $albumId = (int) $_POST['album_id'];
    }
    $albumName = trim($_POST['album_name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $slugPassword = trim($_POST['slug_password'] ?? '');
    $s3Folder = trim($_POST['s3_folder'] ?? '');
    $clientId = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;
    if ($albumName === '') {
        $errors[] = 'Gallery name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Personalized link is required.';
    }
    if ($s3Folder === '') {
        $errors[] = 'S3 folder is required.';
    }
    // Check slug uniqueness
    if (!empty($slug)) {
        if ($editing) {
            $stmt = $conn->prepare('SELECT id FROM albums WHERE slug = ? AND id != ? LIMIT 1');
            $stmt->bind_param('si', $slug, $albumId);
        } else {
            $stmt = $conn->prepare('SELECT id FROM albums WHERE slug = ? LIMIT 1');
            $stmt->bind_param('s', $slug);
        }
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'That personalized link is already in use.';
        }
        $stmt->close();
    }
    if (empty($errors)) {
        if ($editing) {
            if ($clientId === null) {
                $stmt = $conn->prepare('UPDATE albums SET album_name = ?, slug = ?, slug_password = ?, s3_folder = ?, client_id = NULL WHERE id = ? AND venue_id = ?');
                $stmt->bind_param('ssssii', $albumName, $slug, $slugPassword, $s3Folder, $albumId, $venueId);
            } else {
                $stmt = $conn->prepare('UPDATE albums SET album_name = ?, slug = ?, slug_password = ?, s3_folder = ?, client_id = ? WHERE id = ? AND venue_id = ?');
                $stmt->bind_param('ssssiiii', $albumName, $slug, $slugPassword, $s3Folder, $clientId, $albumId, $venueId);
            }
            if (!$stmt->execute()) {
                $errors[] = 'Unable to update gallery: ' . $conn->error;
            } else {
                $message = 'Gallery updated successfully.';
            }
            $stmt->close();
        } else {
            if ($clientId === null) {
                $stmt = $conn->prepare('INSERT INTO albums (venue_id, album_name, slug, slug_password, s3_folder) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('issss', $venueId, $albumName, $slug, $slugPassword, $s3Folder);
            } else {
                $stmt = $conn->prepare('INSERT INTO albums (venue_id, client_id, album_name, slug, slug_password, s3_folder) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iissss', $venueId, $clientId, $albumName, $slug, $slugPassword, $s3Folder);
            }
            if (!$stmt->execute()) {
                $errors[] = 'Unable to create gallery: ' . $conn->error;
            } else {
                $_SESSION['venue_flash'] = 'Gallery created successfully.';
                header('Location: venue_dashboard.php');
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
        <title><?php echo $editing ? 'Edit Gallery' : 'Create Gallery'; ?> | Your Wedding</title>
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
                <h1 class="title"><?php echo $editing ? 'Edit Gallery' : 'Create Gallery'; ?></h1>
                <p class="subtitle"><?php echo $editing ? 'Update gallery details' : 'Add a new gallery to your venue'; ?></p>
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
                            <input type="hidden" name="album_id" value="<?php echo (int) $albumId; ?>" />
                        <?php endif; ?>
                        <div class="field">
                            <label class="label">Gallery Name</label>
                            <div class="control">
                                <input class="input" type="text" name="album_name" value="<?php echo htmlspecialchars($albumName); ?>" placeholder="Wedding Day Photos" required />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Personalized Link</label>
                            <div class="control">
                                <input class="input" type="text" name="slug" value="<?php echo htmlspecialchars($slug); ?>" placeholder="smith-wedding-2025" required />
                            </div>
                            <p class="help">This will be part of the gallery URL.</p>
                        </div>
                        <div class="field">
                            <label class="label">Password (optional)</label>
                            <div class="control">
                                <input class="input" type="text" name="slug_password" value="<?php echo htmlspecialchars($slugPassword); ?>" placeholder="Leave blank for no password" />
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">S3 Folder</label>
                            <div class="control">
                                <input class="input" type="text" name="s3_folder" value="<?php echo htmlspecialchars($s3Folder); ?>" placeholder="smith-wedding/" required />
                            </div>
                            <p class="help">Folder path in your S3 bucket where photos are stored.</p>
                        </div>
                        <div class="field">
                            <label class="label">Assign to Client (optional)</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="client_id">
                                        <option value="">-- None --</option>
                                        <?php foreach ($venueClients as $vc): ?>
                                            <option value="<?php echo (int) $vc['id']; ?>" <?php echo $clientId == $vc['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($vc['display_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="field is-grouped">
                            <div class="control">
                                <button class="button is-link" type="submit"><?php echo $editing ? 'Update' : 'Create'; ?> Gallery</button>
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