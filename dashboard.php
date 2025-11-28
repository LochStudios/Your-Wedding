<?php
require_once __DIR__ . '/config.php';

$flashMessage = $_SESSION['admin_flash'] ?? null;
if (isset($_SESSION['admin_flash'])) {
    unset($_SESSION['admin_flash']);
}

$conn = get_db_connection();

// Determine current role and whether the admin is acting as a client
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isClient = !empty($_SESSION['client_logged_in']);
$actingClientId = null;
if ($isAdmin && !empty($_GET['as_client'])) {
    $actingClientId = (int) $_GET['as_client'];
} elseif ($isClient) {
    $actingClientId = (int) $_SESSION['client_logged_in'];
}

// Admin-only actions (delete) are only permitted when not acting as client
if ($isAdmin && $actingClientId === null && !empty($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $deleteStmt = $conn->prepare('DELETE FROM albums WHERE id = ?');
    $deleteStmt->bind_param('i', $deleteId);
    $deleteStmt->execute();
    $deleteStmt->close();
    header('Location: dashboard.php');
    exit;
}

// Load data for admin or client view
$albums = [];
if ($isAdmin && $actingClientId === null) {
    // Admin (global) view - list all albums with edit/delete controls
    $stmt = $conn->prepare("SELECT a.id, a.client_id, a.client_names, a.slug, a.s3_folder_path, a.created_at, COALESCE(c.display_name, CONCAT(c.title1, ' & ', c.title2, ' ', c.family_name)) AS display_name FROM albums a LEFT JOIN clients c ON c.id = a.client_id ORDER BY a.created_at DESC");
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
} else {
    // Client view - list albums for acting client
    if ($actingClientId !== null) {
        $stmt = $conn->prepare('SELECT id, client_names, slug, s3_folder_path, created_at FROM albums WHERE client_id = ? ORDER BY created_at DESC');
        $stmt->bind_param('i', $actingClientId);
        $stmt->execute();
        $stmt->bind_result($albumId, $clientNames, $slug, $s3FolderPath, $createdAt);
        while ($stmt->fetch()) {
            $albums[] = [
                'id' => $albumId,
                'client_names' => $clientNames,
                'slug' => $slug,
                's3_folder_path' => $s3FolderPath,
                'created_at' => $createdAt,
            ];
        }
        $stmt->close();
    }
}

// If acting as client or viewing as client, fetch client display name
$actingClientDisplay = null;
if ($actingClientId !== null) {
    $cstmt = $conn->prepare("SELECT COALESCE(display_name, CONCAT(title1, ' & ', title2, ' ', family_name)) FROM clients WHERE id = ? LIMIT 1");
    $cstmt->bind_param('i', $actingClientId);
    $cstmt->execute();
    $cstmt->bind_result($actingClientDisplay);
    $cstmt->fetch();
    $cstmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Dashboard | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <div class="columns is-centered">
                    <div class="column is-12">
                        <div class="card dashboard-card">
                            <div class="card-content">
                                <div class="level">
                                    <div class="level-left">
                                        <div>
                                            <?php if ($actingClientId !== null): ?>
                                                <h1 class="title">Galleries for <?php echo htmlspecialchars($actingClientDisplay ?: 'Client'); ?></h1>
                                                <p class="subtitle">Viewing galleries assigned to this client.</p>
                                                <?php if ($isAdmin): ?>
                                                    <div class="notification is-info">You are viewing the site as <strong><?php echo htmlspecialchars($actingClientDisplay ?: 'client'); ?></strong>. <a href="dashboard.php">Stop acting</a>.</div>
                                                <?php endif; ?>
                                            <?php elseif ($isAdmin): ?>
                                                <h1 class="title">Album Management</h1>
                                                <p class="subtitle">List of configured client galleries.</p>
                                            <?php else: ?>
                                                <h1 class="title">Your Galleries</h1>
                                                <p class="subtitle">All galleries assigned to your account.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="level-right">
                                        <?php if ($isAdmin && $actingClientId === null): ?>
                                            <a class="button is-primary" href="create_album.php"><i class="fas fa-plus"></i>&nbsp;<span>Create Album</span></a>
                                            <a class="button is-primary is-light" href="create_client.php"><i class="fas fa-user"></i>&nbsp;<span>Create Client</span></a>
                                            <a class="button is-light" href="change_password.php"><i class="fas fa-key"></i>&nbsp;<span>Change password</span></a>
                                        <?php elseif ($isClient && $actingClientId === null): ?>
                                            <a class="button is-light" href="change_client_password.php"><i class="fas fa-key"></i>&nbsp;<span>Change password</span></a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($flashMessage): ?>
                                    <div class="notification is-success">
                                        <?php echo htmlspecialchars($flashMessage); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($_SESSION['requires_password_reset'])): ?>
                                    <div class="notification is-warning">
                                        A password reset is required for this account. <a href="change_password.php">Update it now</a> before continuing.
                                    </div>
                                <?php endif; ?>
                                <div class="table-container">
                                    <table class="table is-fullwidth is-striped is-hoverable">
                                        <thead>
                                            <tr>
                                                <?php if ($isAdmin && $actingClientId === null): ?>
                                                    <th>Client</th>
                                                <?php endif; ?>
                                                <th>Slug</th>
                                                <th>S3 Folder</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($albums)): ?>
                                                <tr>
                                                    <td colspan="5">No albums yet. Click create to get started.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($albums as $album): ?>
                                                    <tr>
                                                        <?php if ($isAdmin && $actingClientId === null): ?>
                                                            <td><?php echo htmlspecialchars($album['client_display'] ?? ($album['client_names'] ?? '')); ?></td>
                                                        <?php endif; ?>
                                                        <td><code><?php echo htmlspecialchars($album['slug']); ?></code></td>
                                                        <td><?php echo htmlspecialchars($album['s3_folder_path']); ?></td>
                                                        <td><?php echo htmlspecialchars($album['created_at']); ?></td>
                                                        <td>
                                                            <div class="buttons">
                                                                <?php if ($isAdmin && $actingClientId === null): ?>
                                                                    <a class="button is-small" href="create_album.php?id=<?php echo $album['id']; ?>">
                                                                        <span class="icon is-small"><i class="fas fa-edit"></i></span>
                                                                    </a>
                                                                    <a class="button is-small is-danger" href="?delete_id=<?php echo $album['id']; ?>" onclick="return confirm('Remove album permanently?');">
                                                                        <span class="icon is-small"><i class="fas fa-trash"></i></span>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <a class="button is-small is-link" href="gallery.php?slug=<?php echo urlencode($album['slug']); ?>" target="_blank">
                                                                    <span class="icon is-small"><i class="fas fa-eye"></i></span>
                                                                </a>
                                                                <?php if ($isAdmin && $album['client_id']): ?>
                                                                    <a class="button is-small is-primary" href="dashboard.php?as_client=<?php echo (int) $album['client_id']; ?>" title="Act as this client">
                                                                        <span class="icon is-small"><i class="fas fa-user-secret"></i></span>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </body>
</html>