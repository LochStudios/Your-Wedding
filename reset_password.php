<?php
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
$token = trim($token);
$error = '';
$message = '';

if ($token === '') {
    $error = 'Missing or invalid token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    if ($password === '' || $confirmPassword === '') {
        $error = 'Both password fields are required.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $conn = get_db_connection();
        $tokenHash = hash('sha256', $token);
        $stmt = $conn->prepare('SELECT id, password_reset_expires_at FROM admins WHERE password_reset_token_hash = ? LIMIT 1');
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $stmt->bind_result($adminId, $expiresAt);
        if ($stmt->fetch()) {
            $stmt->close();
            $expiry = DateTime::createFromFormat('Y-m-d H:i:s', $expiresAt);
            $now = new DateTime();
            if ($expiry !== false && $expiry > $now) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE admins SET password_hash = ?, force_password_reset = 0, password_reset_token_hash = NULL, password_reset_expires_at = NULL WHERE id = ?');
                $update->bind_param('si', $hash, $adminId);
                $update->execute();
                $update->close();
                $_SESSION['admin_flash'] = 'Password updated. You can now log in.';
                header('Location: login.php');
                exit;
            } else {
                $error = 'This reset link has expired. Please request a new password reset.';
            }
        } else {
            $stmt->close();
            $error = 'Invalid token. Please request a new password reset.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Reset Password | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <section class="section">
            <div class="container is-fluid">
                <div class="columns is-centered">
                    <div class="column is-half">
                        <h1 class="title">Reset Password</h1>
                        <p>Enter a new password for the admin account.</p>
                        <div class="box">
                            <?php if ($error): ?>
                                <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <?php if ($message): ?>
                                <div class="notification is-success"><?php echo htmlspecialchars($message); ?></div>
                            <?php endif; ?>
                            <?php if (!$message): ?>
                                <form method="post" novalidate>
                                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />
                                    <div class="field">
                                        <label class="label">New Password</label>
                                        <div class="control">
                                            <input class="input" type="password" name="password" placeholder="New password" required />
                                        </div>
                                        <p class="help">Minimum 8 characters.</p>
                                    </div>
                                    <div class="field">
                                        <label class="label">Confirm Password</label>
                                        <div class="control">
                                            <input class="input" type="password" name="confirm_password" placeholder="Confirm password" required />
                                        </div>
                                    </div>
                                    <div class="field">
                                        <div class="control">
                                            <button class="button is-link is-fullwidth" type="submit">Update Password</button>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        <p class="has-text-grey"><a href="login.php">Back to login</a></p>
                    </div>
                </div>
            </div>
        </section>
    </body>
</html>