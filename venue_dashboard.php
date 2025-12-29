<?php
require_once __DIR__ . '/config.php';

// Determine current role and whether admin is acting as venue
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isTeamMember = !empty($_SESSION['venue_team_logged_in']);
$isVenueOwner = !empty($_SESSION['venue_logged_in']);
$actingVenueId = null;
$actingVenueName = null;

if ($isAdmin && !empty($_GET['as_venue'])) {
    // Admin acting as venue
    $actingVenueId = (int) $_GET['as_venue'];
    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT name FROM venues WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $actingVenueId);
    $stmt->execute();
    $stmt->bind_result($actingVenueName);
    $stmt->fetch();
    $stmt->close();
    $venueId = $actingVenueId;
    $venueName = $actingVenueName ?? 'Venue';
    // Admin acting as venue has all permissions
    $canCreateClients = true;
    $canCreateAlbums = true;
    $canUploadPhotos = true;
    $canViewAnalytics = true;
} elseif ($isTeamMember) {
    $venueId = (int) $_SESSION['venue_id'];
    $venueName = $_SESSION['venue_team_name'] ?? 'Team Member';
    $canCreateClients = $_SESSION['can_create_clients'] ?? false;
    $canCreateAlbums = $_SESSION['can_create_albums'] ?? false;
    $canUploadPhotos = $_SESSION['can_upload_photos'] ?? false;
    $canViewAnalytics = $_SESSION['can_view_analytics'] ?? false;
} elseif ($isVenueOwner) {
    $venueId = (int) $_SESSION['venue_logged_in'];
    $venueName = $_SESSION['venue_name'] ?? 'Venue';
    // Venue owners have all permissions
    $canCreateClients = true;
    $canCreateAlbums = true;
    $canUploadPhotos = true;
    $canViewAnalytics = true;
} else {
    header('Location: venue_login.php');
    exit;
}

if (!isset($conn)) {
    $conn = get_db_connection();
}

// Build query parameter for passing venue context in links
$venueParam = ($isAdmin && $actingVenueId !== null) ? '?as_venue=' . $actingVenueId : '';
$venueParamAmp = ($isAdmin && $actingVenueId !== null) ? '&as_venue=' . $actingVenueId : '';

$flashMessage = $_SESSION['venue_flash'] ?? null;
if (isset($_SESSION['venue_flash'])) {
    unset($_SESSION['venue_flash']);
}

// Load venue's clients
$clients = [];
$stmt = $conn->prepare("SELECT id, username, COALESCE(display_name, CONCAT(title1, ' & ', title2, ' ', family_name)) AS display_name, created_at FROM clients WHERE venue_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $venueId);
$stmt->execute();
$stmt->bind_result($id, $username, $displayName, $createdAt);
while ($stmt->fetch()) {
    $clients[] = [
        'id' => $id,
        'username' => $username,
        'display_name' => $displayName,
        'created_at' => $createdAt,
    ];
}
$stmt->close();

// Load venue's albums
$albums = [];
$stmt = $conn->prepare("SELECT a.id, a.client_id, a.client_names, a.slug, a.s3_folder_path, a.created_at, COALESCE(c.display_name, CONCAT(c.title1, ' & ', c.title2, ' ', c.family_name)) AS client_display FROM albums a LEFT JOIN clients c ON c.id = a.client_id WHERE a.venue_id = ? ORDER BY a.created_at DESC");
$stmt->bind_param('i', $venueId);
$stmt->execute();
$stmt->bind_result($albumId, $clientId, $clientNames, $slug, $s3FolderPath, $createdAt, $clientDisplay);
while ($stmt->fetch()) {
    $albums[] = [
        'id' => $albumId,
        'client_id' => $clientId,
        'client_names' => $clientNames,
        'client_display' => $clientDisplay,
        'slug' => $slug,
        's3_folder_path' => $s3FolderPath,
        'created_at' => $createdAt,
    ];
}
$stmt->close();

// Handle delete album
if (!empty($_GET['delete_album'])) {
    $deleteId = (int) $_GET['delete_album'];
    $deleteStmt = $conn->prepare('DELETE FROM albums WHERE id = ? AND venue_id = ?');
    $deleteStmt->bind_param('ii', $deleteId, $venueId);
    $deleteStmt->execute();
    $deleteStmt->close();
    header('Location: venue_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Venue Dashboard | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <nav class="navbar" role="navigation">
            <div class="navbar-brand">
                <a class="navbar-item" href="/"><strong>LochStudios</strong></a>
            </div>
            <div class="navbar-menu">
                <div class="navbar-start">
                    <a class="navbar-item" href="venue_dashboard.php<?php echo $venueParam; ?>">Dashboard</a>
                    <?php if ($canCreateClients): ?>
                        <a class="navbar-item" href="venue_create_client.php<?php echo $venueParam; ?>">Create Client</a>
                    <?php endif; ?>
                    <?php if ($canCreateAlbums): ?>
                        <a class="navbar-item" href="venue_create_album.php<?php echo $venueParam; ?>">Create Gallery</a>
                    <?php endif; ?>
                    <?php if ($canUploadPhotos): ?>
                        <a class="navbar-item" href="venue_upload.php<?php echo $venueParam; ?>">Upload Photos</a>
                    <?php endif; ?>
                    <?php if ($isVenueOwner): ?>
                        <a class="navbar-item" href="venue_team.php<?php echo $venueParam; ?>">Manage Team</a>
                    <?php endif; ?>
                </div>
                <div class="navbar-end">
                    <div class="navbar-item">
                        <div class="buttons">
                            <?php if ($isAdmin && $actingVenueId !== null): ?>
                                <a class="button is-light" href="manage_venues.php">Stop Acting</a>
                            <?php else: ?>
                                <a class="button is-light" href="venue_logout.php">Sign Out</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title">Welcome, <?php echo htmlspecialchars($venueName); ?></h1>
                <p class="subtitle">Manage your clients and galleries</p>
                <?php if ($isAdmin && $actingVenueId !== null): ?>
                    <div class="notification is-info">
                        You are viewing the site as <strong><?php echo htmlspecialchars($venueName); ?></strong> (Venue ID: <?php echo $actingVenueId; ?>). 
                        <a href="manage_venues.php">Stop acting</a>.
                    </div>
                <?php endif; ?>
                <?php if ($flashMessage): ?>
                    <div class="notification is-success"><?php echo htmlspecialchars($flashMessage); ?></div>
                <?php endif; ?>
                <div class="columns">
                    <div class="column">
                        <div class="card">
                            <header class="card-header">
                                <p class="card-header-title">Your Clients (<?php echo count($clients); ?>)</p>
                                <a href="venue_create_client.php" class="card-header-icon">
                                    <span class="icon"><i class="fas fa-plus"></i></span>
                                </a>
                            </header>
                            <div class="card-content">
                                <?php if (empty($clients)): ?>
                                    <p>No clients yet. <a href="venue_create_client.php">Create your first client</a>.</p>
                                <?php else: ?>
                                    <div class="table-container">
                                        <table class="table is-fullwidth is-striped">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Username</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($clients as $client): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($client['display_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($client['username'] ?? '-'); ?></td>
                                                        <td><?php echo htmlspecialchars($client['created_at']); ?></td>
                                                        <td>
                                                            <a class="button is-small" href="venue_create_client.php?id=<?php echo $client['id']; ?>">
                                                                <span class="icon is-small"><i class="fas fa-edit"></i></span>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="columns mt-4">
                    <div class="column">
                        <div class="card">
                            <header class="card-header">
                                <p class="card-header-title">Your Galleries (<?php echo count($albums); ?>)</p>
                                <a href="venue_create_album.php" class="card-header-icon">
                                    <span class="icon"><i class="fas fa-plus"></i></span>
                                </a>
                            </header>
                            <div class="card-content">
                                <?php if (empty($albums)): ?>
                                    <p>No galleries yet. <a href="venue_create_album.php">Create your first gallery</a>.</p>
                                <?php else: ?>
                                    <div class="table-container">
                                        <table class="table is-fullwidth is-striped">
                                            <thead>
                                                <tr>
                                                    <th>Client</th>
                                                    <th>Gallery Link</th>
                                                    <th>Created</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($albums as $album): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($album['client_display'] ?? $album['client_names']); ?></td>
                                                        <td><code><?php echo htmlspecialchars($album['slug']); ?></code></td>
                                                        <td><?php echo htmlspecialchars($album['created_at']); ?></td>
                                                        <td>
                                                            <div class="buttons">
                                                                <a class="button is-small" href="venue_create_album.php?id=<?php echo $album['id']; ?>">
                                                                    <span class="icon is-small"><i class="fas fa-edit"></i></span>
                                                                </a>
                                                                <a class="button is-small is-link" href="gallery.php?slug=<?php echo urlencode($album['slug']); ?>" target="_blank">
                                                                    <span class="icon is-small"><i class="fas fa-eye"></i></span>
                                                                </a>
                                                                <a class="button is-small is-danger" href="?delete_album=<?php echo $album['id']; ?>" onclick="return confirm('Remove gallery permanently?');">
                                                                    <span class="icon is-small"><i class="fas fa-trash"></i></span>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </body>
</html>