<?php
require_once __DIR__ . '/config.php';
require_admin_session();

$conn = get_db_connection();
$editing = false;
$message = '';
$errors = [];
$album = [
    'client_id' => null,
    'client_names' => '',
    'slug' => '',
    'album_password' => '',
    's3_folder_path' => '',
];

if (!empty($_GET['id'])) {
    $editing = true;
    $albumId = (int) $_GET['id'];
    $stmt = $conn->prepare('SELECT client_id, client_names, slug, album_password, s3_folder_path FROM albums WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $albumId);
    $stmt->execute();
    $stmt->bind_result($clientId, $clientNames, $slug, $albumPassword, $s3Path);
    if ($stmt->fetch()) {
        $album = [
            'client_id' => $clientId,
            'client_names' => $clientNames,
            'slug' => $slug,
            'album_password' => $albumPassword,
            's3_folder_path' => $s3Path,
        ];
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $album['client_id'] = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;
    $album['client_names'] = trim($_POST['client_names'] ?? '');
    $album['slug'] = trim($_POST['slug'] ?? '');
    $album['album_password'] = trim($_POST['album_password'] ?? '');
    $album['s3_folder_path'] = trim($_POST['s3_folder_path'] ?? '');
    if ($album['client_names'] === '') {
        $errors[] = 'Client names are required.';
    }
    if ($album['s3_folder_path'] === '') {
        $errors[] = 'S3 folder path is required.';
    }
    if ($album['album_password'] === '') {
        $errors[] = 'Album password is required.';
    }
    if ($album['slug'] === '') {
        $album['slug'] = slugify($album['client_names']);
    } else {
        $album['slug'] = slugify($album['slug']);
    }
    if ($album['slug'] === '') {
        $errors[] = 'Slug cannot be empty.';
    }
    if (empty($errors)) {
        $checkStmt = $conn->prepare('SELECT id FROM albums WHERE slug = ? LIMIT 1');
        $checkStmt->bind_param('s', $album['slug']);
        $checkStmt->execute();
        $checkStmt->bind_result($existingId);
        if ($checkStmt->fetch() && (!$editing || $existingId !== $albumId)) {
            $errors[] = 'Slug already exists.';
        }
        $checkStmt->close();
    }
    if (empty($errors)) {
        if ($editing) {
            if ($album['client_id'] === null) {
                $update = $conn->prepare('UPDATE albums SET client_id = NULL, client_names = ?, slug = ?, album_password = ?, s3_folder_path = ? WHERE id = ?');
                $update->bind_param('ssssi', $album['client_names'], $album['slug'], $album['album_password'], $album['s3_folder_path'], $albumId);
            } else {
                $update = $conn->prepare('UPDATE albums SET client_id = ?, client_names = ?, slug = ?, album_password = ?, s3_folder_path = ? WHERE id = ?');
                $update->bind_param('issssi', $album['client_id'], $album['client_names'], $album['slug'], $album['album_password'], $album['s3_folder_path'], $albumId);
            }
            $update->execute();
            $update->close();
            $message = 'Album updated successfully.';
        } else {
            if ($album['client_id'] === null) {
                $insert = $conn->prepare('INSERT INTO albums (client_names, slug, album_password, s3_folder_path) VALUES (?, ?, ?, ?)');
                $insert->bind_param('ssss', $album['client_names'], $album['slug'], $album['album_password'], $album['s3_folder_path']);
            } else {
                $insert = $conn->prepare('INSERT INTO albums (client_id, client_names, slug, album_password, s3_folder_path) VALUES (?, ?, ?, ?, ?)');
                $insert->bind_param('issss', $album['client_id'], $album['client_names'], $album['slug'], $album['album_password'], $album['s3_folder_path']);
            }
            $insert->execute();
            $insert->close();
            $message = 'Album created.';
            $album = [
                'client_names' => '',
                'slug' => '',
                'album_password' => '',
                's3_folder_path' => '',
            ];
        }
    }
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\r\n\t\f\na-zA-Z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return strtolower(preg_replace('~-+~', '-', $text));
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php echo $editing ? 'Edit Album' : 'Create Album'; ?> | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title"><?php echo $editing ? 'Edit Album' : 'Create Album'; ?></h1>
                <p class="subtitle">Point the album to an existing S3 folder and share the slug with clients.</p>
                <?php if ($message): ?>
                    <div class="notification is-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="notification is-danger">
                        <ul>
                            <?php foreach ($errors as $errorItem): ?>
                                <li><?php echo htmlspecialchars($errorItem); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="post">
                    <div class="field">
                        <label class="label">Client Names</label>
                        <div class="control">
                                <input class="input" type="text" name="client_names" value="<?php echo htmlspecialchars($album['client_names']); ?>" placeholder="John &amp; Jane" required />
                        </div>
                    </div>
                        <div class="field">
                            <label class="label">Assign to Client (optional)</label>
                            <div class="control">
                                <div class="select">
                                    <select name="client_id">
                                        <option value="">-- None --</option>
                                        <?php
                                        // fetch clients for selection (compute the display label if needed)
                                        $stmt = $conn->prepare("SELECT id, username, COALESCE(display_name, CONCAT(title1, ' & ', title2, ' ', family_name)) AS display_name FROM clients ORDER BY created_at DESC");
                                        $stmt->execute();
                                        $stmt->bind_result($cid, $cusername, $dname);
                                        while ($stmt->fetch()): ?>
                                                <option value="<?php echo (int) $cid; ?>" <?php echo ($album['client_id'] === (int) $cid) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dname) . ($cusername ? ' (' . htmlspecialchars($cusername) . ')' : ''); ?></option>
                                        <?php endwhile; $stmt->close(); ?>
                                    </select>
                                </div>
                            </div>
                            <p class="help">Assign this album to a client account so they can login and access multiple galleries.</p>
                        </div>
                    <div class="field">
                        <label class="label">URL Slug</label>
                        <div class="control">
                            <input class="input" type="text" name="slug" value="<?php echo htmlspecialchars($album['slug']); ?>" placeholder="john-jane" />
                            <p class="help">Leave blank to auto-generate from the names.</p>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Access Password</label>
                        <div class="control">
                            <input class="input" type="text" name="album_password" value="<?php echo htmlspecialchars($album['album_password']); ?>" placeholder="Secure password" required />
                        </div>
                        <p class="help">Clients will use this password along with the URL slug.</p>
                    </div>
                    <div class="field">
                        <label class="label">S3 Folder Path</label>
                        <div class="control">
                            <input class="input" type="text" name="s3_folder_path" value="<?php echo htmlspecialchars($album['s3_folder_path']); ?>" placeholder="2024/john-jane/" required />
                        </div>
                    </div>
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-link" type="submit">Save</button>
                        </div>
                        <div class="control">
                            <a class="button is-light" href="dashboard.php">Cancel</a>
                        </div>
                    </div>
                </form>
                <script>
                    const clientSelect = document.querySelector('select[name="client_id"]');
                    const clientNamesInput = document.querySelector('input[name="client_names"]');
                    if (clientSelect && clientNamesInput) {
                        clientSelect.addEventListener('change', () => {
                            const opt = clientSelect.options[clientSelect.selectedIndex];
                            if (!opt || !opt.value) return;
                            // Remove any username in parentheses
                            const display = opt.textContent.replace(/\s*\([^)]*\)\s*$/, '');
                            if (!clientNamesInput.value || clientNamesInput.value.trim() === '') {
                                clientNamesInput.value = display;
                            }
                        });
                    }
                </script>
            </div>
        </section>
    </body>
</html>