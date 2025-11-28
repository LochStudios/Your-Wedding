<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['client_logged_in'])) {
    header('Location: login.php?type=client');
    exit;
}
$clientId = (int) $_SESSION['client_logged_in'];
$conn = get_db_connection();
$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if ($current === '' || $new === '' || $confirm === '') {
        $error = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $stmt = $conn->prepare('SELECT password_hash FROM clients WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $clientId);
        $stmt->execute();
        $stmt->bind_result($storedHash);
        if ($stmt->fetch() && password_verify($current, $storedHash)) {
            $stmt->close();
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare('UPDATE clients SET password_hash = ? WHERE id = ?');
            $update->bind_param('si', $hash, $clientId);
            $update->execute();
            $update->close();
            $message = 'Password updated.';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Change Password | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <div class="columns is-centered">
                    <div class="column is-half">
                        <h1 class="title">Change password</h1>
                        <?php if ($message): ?>
                            <div class="notification is-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <div class="box">
                            <form method="post" novalidate>
                                <div class="field">
                                    <label class="label">Current Password</label>
                                    <div class="control">
                                        <input class="input" type="password" name="current_password" required />
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">New Password</label>
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
                                <div class="field">
                                    <div class="control">
                                        <button class="button is-link" type="submit">Update Password</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <p><a href="dashboard.php">Back to your galleries</a></p>
                    </div>
                </div>
            </div>
        </section>
    </body>
    </html>
