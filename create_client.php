<?php
require_once __DIR__ . '/config.php';
require_admin_session();

$conn = get_db_connection();
$errors = [];
$message = '';
$editing = false;
$clientId = null;
// Defaults for build and form
$title1 = 'Mr';
$title2 = 'Mrs';
$familyName = '';
$username = '';
$email = '';
$displayName = '';

// Check if editing existing client
if (!empty($_GET['id'])) {
    $editing = true;
    $clientId = (int) $_GET['id'];
    $stmt = $conn->prepare('SELECT username, email, display_name, title1, title2, family_name FROM clients WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $stmt->bind_result($username, $email, $displayName, $title1, $title2, $familyName);
    if (!$stmt->fetch()) {
        $errors[] = 'Client not found.';
        $editing = false;
    }
    $stmt->close();
}

// Handle create or update
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
    // Password is required for new clients, optional for edits
    if (!$editing && ($password === '' || $confirm === '')) {
        $errors[] = 'Password fields are required.';
    }
    if ($password !== '' && $password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($errors)) {
        // Construct a standard display name from the titles + family name if not supplied
        if ($displayName === '') {
            $displayName = sprintf('%s & %s %s', $title1 ?: 'Mr', $title2 ?: 'Mrs', $familyName);
        }
        if ($editing) {
            // Update existing client
            if ($password !== '') {
                // Update with new password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($username === null && $email === null) {
                    $stmt = $conn->prepare('UPDATE clients SET username = NULL, email = NULL, display_name = ?, title1 = ?, title2 = ?, family_name = ?, password_hash = ? WHERE id = ?');
                    $stmt->bind_param('sssssi', $displayName, $title1, $title2, $familyName, $hash, $clientId);
                } elseif ($email === null) {
                    $stmt = $conn->prepare('UPDATE clients SET username = ?, email = NULL, display_name = ?, title1 = ?, title2 = ?, family_name = ?, password_hash = ? WHERE id = ?');
                    $stmt->bind_param('ssssssi', $username, $displayName, $title1, $title2, $familyName, $hash, $clientId);
                } elseif ($username === null) {
                    $stmt = $conn->prepare('UPDATE clients SET username = NULL, email = ?, display_name = ?, title1 = ?, title2 = ?, family_name = ?, password_hash = ? WHERE id = ?');
                    $stmt->bind_param('ssssssi', $email, $displayName, $title1, $title2, $familyName, $hash, $clientId);
                } else {
                    $stmt = $conn->prepare('UPDATE clients SET username = ?, email = ?, display_name = ?, title1 = ?, title2 = ?, family_name = ?, password_hash = ? WHERE id = ?');
                    $stmt->bind_param('sssssssi', $username, $email, $displayName, $title1, $title2, $familyName, $hash, $clientId);
                }
            } else {
                // Update without changing password
                if ($username === null && $email === null) {
                    $stmt = $conn->prepare('UPDATE clients SET username = NULL, email = NULL, display_name = ?, title1 = ?, title2 = ?, family_name = ? WHERE id = ?');
                    $stmt->bind_param('ssssi', $displayName, $title1, $title2, $familyName, $clientId);
                } elseif ($email === null) {
                    $stmt = $conn->prepare('UPDATE clients SET username = ?, email = NULL, display_name = ?, title1 = ?, title2 = ?, family_name = ? WHERE id = ?');
                    $stmt->bind_param('sssssi', $username, $displayName, $title1, $title2, $familyName, $clientId);
                } elseif ($username === null) {
                    $stmt = $conn->prepare('UPDATE clients SET username = NULL, email = ?, display_name = ?, title1 = ?, title2 = ?, family_name = ? WHERE id = ?');
                    $stmt->bind_param('sssssi', $email, $displayName, $title1, $title2, $familyName, $clientId);
                } else {
                    $stmt = $conn->prepare('UPDATE clients SET username = ?, email = ?, display_name = ?, title1 = ?, title2 = ?, family_name = ? WHERE id = ?');
                    $stmt->bind_param('ssssssi', $username, $email, $displayName, $title1, $title2, $familyName, $clientId);
                }
            }
            if (!$stmt->execute()) {
                $errors[] = 'Unable to update client: ' . $conn->error;
            } else {
                $message = 'Client updated successfully.';
            }
            $stmt->close();
        } else {
            // Create new client
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($username === null && $email === null) {
                $stmt = $conn->prepare('INSERT INTO clients (display_name, title1, title2, family_name, password_hash) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('sssss', $displayName, $title1, $title2, $familyName, $hash);
            } elseif ($email === null) {
                $stmt = $conn->prepare('INSERT INTO clients (username, display_name, title1, title2, family_name, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssss', $username, $displayName, $title1, $title2, $familyName, $hash);
            } elseif ($username === null) {
                $stmt = $conn->prepare('INSERT INTO clients (email, display_name, title1, title2, family_name, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssss', $email, $displayName, $title1, $title2, $familyName, $hash);
            } else {
                $stmt = $conn->prepare('INSERT INTO clients (username, email, display_name, title1, title2, family_name, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('sssssss', $username, $email, $displayName, $title1, $title2, $familyName, $hash);
            }
            if (!$stmt->execute()) {
                $errors[] = 'Unable to create client: ' . $conn->error;
            } else {
                $message = 'Client created successfully.';
                // reset form values
                $title1 = 'Mr';
                $title2 = 'Mrs';
                $familyName = '';
                $username = '';
                $displayName = '';
            }
            $stmt->close();
        }
    }
}

// Fetch clients for listing and selection in album creation
$clients = [];
$stmt = $conn->prepare("SELECT id, username, COALESCE(display_name, CONCAT(title1, ' & ', title2, ' ', family_name)) AS display_name, created_at FROM clients ORDER BY created_at DESC");
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
    <?php include_once __DIR__ . '/nav.php'; ?>
    <section class="section full-bleed full-height">
        <div class="container is-fluid">
            <h1 class="title"><?php echo $editing ? 'Edit Client Account' : 'Create Client Account'; ?></h1>
            <p class="subtitle"><?php echo $editing ? 'Update client account details.' : 'Create a client account to grant access to multiple galleries.'; ?></p>
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
                            <p class="help" id="displayPreview">Preview</p>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Display Name (optional)</label>
                    <div class="control">
                        <input class="input" type="text" name="display_name" value="<?php echo htmlspecialchars($displayName ?? ''); ?>" placeholder="Auto-generated from titles and family name" />
                    </div>
                    <p class="help">Leave blank to auto-generate from titles and family name.</p>
                </div>
                <div class="field">
                    <label class="label">Username (optional)</label>
                    <div class="control">
                        <input class="input" type="text" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" placeholder="john@example.com" />
                    </div>
                    <p class="help">Optional username/email the client can use to login.</p>
                </div>
                <div class="field">
                    <label class="label">Email Address (optional)</label>
                    <div class="control">
                        <input class="input" type="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" placeholder="john@example.com" />
                    </div>
                    <p class="help">Email address for sending analytics reports.</p>
                </div>
                <div class="field">
                    <label class="label">Password<?php echo $editing ? ' (leave blank to keep current)' : ''; ?></label>
                    <div class="control">
                        <input class="input" type="password" name="password" <?php echo $editing ? '' : 'required'; ?> />
                    </div>
                    <?php if ($editing): ?>
                        <p class="help">Only fill this in if you want to change the password.</p>
                    <?php endif; ?>
                </div>
                <div class="field">
                    <label class="label">Confirm Password</label>
                    <div class="control">
                        <input class="input" type="password" name="confirm_password" <?php echo $editing ? '' : 'required'; ?> />
                    </div>
                </div>
                <div class="field is-grouped">
                    <div class="control">
                        <button class="button is-link" type="submit"><?php echo $editing ? 'Update Client' : 'Create Client'; ?></button>
                    </div>
                    <div class="control">
                        <a class="button is-light" href="<?php echo $editing ? 'create_client.php' : 'dashboard.php'; ?>"><?php echo $editing ? 'Cancel Edit' : 'Cancel'; ?></a>
                    </div>
                </div>
            </form>
            <script>
                function computePreview() {
                    const t1 = document.querySelector('select[name="title1"]').value;
                    const t2 = document.querySelector('select[name="title2"]').value;
                    const fam = document.querySelector('input[name="family_name"]').value.trim();
                    const preview = document.getElementById('displayPreview');
                    if (preview) {
                        preview.textContent = (fam ? (t1 + ' & ' + t2 + ' ' + fam) : 'Preview: ' + t1 + ' & ' + t2);
                    }
                }
                document.querySelectorAll('select[name="title1"], select[name="title2"], input[name="family_name"]').forEach((el) => el.addEventListener('input', computePreview));
                document.addEventListener('DOMContentLoaded', computePreview);
            </script>
            <hr>
            <h2 class="title is-5">Existing Clients</h2>
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr><th>Display</th><th>Username</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($c['display_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['username'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                            <td>
                                <a class="button is-small" href="create_client.php?id=<?php echo (int) $c['id']; ?>">
                                    <span class="icon is-small"><i class="fas fa-edit"></i></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</body>
</html>