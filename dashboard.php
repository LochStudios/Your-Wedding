<?php
require_once __DIR__ . '/config.php';
require_admin_session();

$flashMessage = $_SESSION['admin_flash'] ?? null;
if (isset($_SESSION['admin_flash'])) {
    unset($_SESSION['admin_flash']);
}

$conn = get_db_connection();

if (!empty($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $deleteStmt = $conn->prepare('DELETE FROM albums WHERE id = ?');
    $deleteStmt->bind_param('i', $deleteId);
    $deleteStmt->execute();
    $deleteStmt->close();
    header('Location: dashboard.php');
    exit;
}

$albums = [];
$stmt = $conn->prepare('SELECT id, client_names, slug, s3_folder_path, created_at FROM albums ORDER BY created_at DESC');
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
        <title>Dashboard | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-MkM9+dU5CPtz+VRrx7tIw6V0Tp9SHFExi+b0dYV16zJZyrUxjlX+8llc8frlJYe1jKhh598MBXEDqUS1bJXgBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <section class="section">
            <div class="container">
                <div class="columns is-centered">
                    <div class="column is-10">
                        <div class="card dashboard-card">
                            <div class="card-content">
                                <div class="level">
                                    <div class="level-left">
                                        <div>
                                            <h1 class="title">Album Management</h1>
                                            <p class="subtitle">List of configured client galleries.</p>
                                        </div>
                                    </div>
                                    <div class="level-right">
                                        <a class="button is-primary" href="create_album.php"><i class="fas fa-plus"></i>&nbsp;<span>Create Album</span></a>
                                        <a class="button is-light" href="change_password.php"><i class="fas fa-key"></i>&nbsp;<span>Change password</span></a>
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
                                                <th>Client</th>
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
                                                        <td><?php echo htmlspecialchars($album['client_names']); ?></td>
                                                        <td><code><?php echo htmlspecialchars($album['slug']); ?></code></td>
                                                        <td><?php echo htmlspecialchars($album['s3_folder_path']); ?></td>
                                                        <td><?php echo htmlspecialchars($album['created_at']); ?></td>
                                                        <td>
                                                            <div class="buttons">
                                                                <a class="button is-small" href="create_album.php?id=<?php echo $album['id']; ?>">
                                                                    <span class="icon is-small"><i class="fas fa-edit"></i></span>
                                                                </a>
                                                                <a class="button is-small is-danger" href="?delete_id=<?php echo $album['id']; ?>" onclick="return confirm('Remove album permanently?');">
                                                                    <span class="icon is-small"><i class="fas fa-trash"></i></span>
                                                                </a>
                                                                <a class="button is-small is-link" href="gallery.php?slug=<?php echo urlencode($album['slug']); ?>" target="_blank">
                                                                    <span class="icon is-small"><i class="fas fa-eye"></i></span>
                                                                </a>
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