<?php
require_once __DIR__ . '/config.php';
require_admin_session();

$conn = get_db_connection();
 $errors = [];
 $message = '';
 // Defaults for build and form
 $title1 = 'Mr';
 $title2 = 'Mrs';
 $familyName = '';
 $username = '';

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title1 = trim($_POST['title1'] ?? 'Mr');
    $title2 = trim($_POST['title2'] ?? 'Mrs');
    $familyName = trim($_POST['family_name'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '');
    $username = trim($_POST['username'] ?? '') ?: null;
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($familyName === '') {
        $errors[] = 'Family name is required.';
    }
    if ($password === '' || $confirm === '') {
        $errors[] = 'Password fields are required.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($errors)) {
        // Construct a standard display name from the titles + family name if not supplied
        if ($displayName === '') {
            $displayName = sprintf('%s & %s %s', $title1 ?: 'Mr', $title2 ?: 'Mrs', $familyName);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($username === null) {
            $stmt = $conn->prepare('INSERT INTO clients (display_name, title1, title2, family_name, password_hash) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $displayName, $title1, $title2, $familyName, $hash);
        } else {
            $stmt = $conn->prepare('INSERT INTO clients (username, display_name, title1, title2, family_name, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssss', $username, $displayName, $title1, $title2, $familyName, $hash);
        }
        if (!$stmt->execute()) {
            $errors[] = 'Unable to create client: ' . $conn->error;
        } else {
            $message = 'Client created.';
            // reset form values
            $title1 = 'Mr';
            $title2 = 'Mrs';
            $familyName = '';
            $username = '';
        }
        $stmt->close();
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
        <title>Create Client | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title">Create Client Account</h1>
                <p class="subtitle">Create a client account to grant access to multiple galleries.</p>
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
                        <label class="label">Username (optional)</label>
                        <div class="control">
                            <input class="input" type="text" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" placeholder="john@example.com" />
                        </div>
                        <p class="help">Optional username/email the client can use to login.</p>
                    </div>
                    <div class="field">
                        <label class="label">Password</label>
                        <div class="control">
                            <input class="input" type="password" name="password" required />
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Confirm Password</label>
                        <div class="control">
                            <input class="input" type="password" name="confirm_password" required />
                        </div>
                    </div>
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-link" type="submit">Create Client</button>
                        </div>
                        <div class="control">
                            <a class="button is-light" href="dashboard.php">Cancel</a>
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
                        <tr><th>Display</th><th>Username</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['display_name']); ?></td>
                                <td><?php echo htmlspecialchars($c['username'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </body>
    </html>


