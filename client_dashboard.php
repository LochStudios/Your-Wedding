<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['client_logged_in'])) {
    header('Location: login.php?type=client&redirect=client_dashboard.php');
    exit;
}
$clientId = (int) $_SESSION['client_logged_in'];
$conn = get_db_connection();
// Fetch client display name
$clientDisplay = '';
$cstmt = $conn->prepare("SELECT COALESCE(display_name, CONCAT(title1, ' & ', title2, ' ', family_name)) FROM clients WHERE id = ? LIMIT 1");
$cstmt->bind_param('i', $clientId);
$cstmt->execute();
$cstmt->bind_result($clientDisplayName);
$cstmt->fetch();
$cstmt->close();
// Fetch albums belonging to this client
$albums = [];
$stmt = $conn->prepare('SELECT id, client_names, slug, s3_folder_path, created_at FROM albums WHERE client_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $clientId);
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
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Your Galleries | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section">
            <div class="container">
                <div class="columns is-centered">
                    <div class="column is-10">
                        <div class="card dashboard-card">
                            <div class="card-content">
                                <div class="level">
                                    <div class="level-left">
                                                    <div>
                                                        <h1 class="title">Your Galleries</h1>
                                                        <p class="subtitle">All galleries assigned to <strong><?php echo htmlspecialchars($clientDisplayName ?? 'you'); ?></strong>.</p>
                                                    </div>
                                                </div>
                                    <div class="level-right">
                                        <a class="button is-light" href="change_client_password.php">Change Password</a>
                                        <a class="button is-light" href="logout.php">Sign Out</a>
                                    </div>
                                </div>
                                <div class="table-container">
                                    <table class="table is-fullwidth is-striped is-hoverable">
                                        <thead>
                                            <tr>
                                                <th>Client</th>
                                                <th>Slug</th>
                                                <th>S3 Folder</th>
                                                <th>Created</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($albums)): ?>
                                                <tr>
                                                    <td colspan="5">No galleries assigned to your account yet.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($albums as $album): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($album['client_names']); ?></td>
                                                        <td><code><?php echo htmlspecialchars($album['slug']); ?></code></td>
                                                        <td><?php echo htmlspecialchars($album['s3_folder_path']); ?></td>
                                                        <td><?php echo htmlspecialchars($album['created_at']); ?></td>
                                                        <td>
                                                            <a class="button is-link is-small" href="gallery.php?slug=<?php echo urlencode($album['slug']); ?>">View</a>
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
