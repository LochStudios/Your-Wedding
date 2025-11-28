<?php
require_once __DIR__ . '/config.php';
require_admin_session();

$conn = get_db_connection();
$adminId = $_SESSION['admin_user_id'] ?? null;
if ($adminId === null) {
    header('Location: login.php');
    exit;
}

$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    if ($password === '' || $confirmPassword === '') {
        $errors[] = 'Both password fields are required.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE admins SET password_hash = ?, force_password_reset = 0 WHERE id = ?');
        $update->bind_param('si', $hash, $adminId);
        $update->execute();
        $update->close();
        $_SESSION['requires_password_reset'] = false;
        $_SESSION['admin_flash'] = 'Password updated. You can now continue to the dashboard.';
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Change Password | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-MkM9+dU5CPtz+VRrx7tIw6V0Tp9SHFExi+b0dYV16zJZyrUxjlX+8llc8frlJYe1jKhh598MBXEDqUS1bJXgBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css" />
    </head>
    <body>
        <section class="section">
            <div class="container">
                <h1 class="title">Change Admin Password</h1>
                <p class="subtitle">Choose a strong password for the admin account.</p>
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
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-link" type="submit">Update Password</button>
                        </div>
                        <div class="control">
                            <a class="button is-light" href="dashboard.php">Back to Dashboard</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </body>
</html>
