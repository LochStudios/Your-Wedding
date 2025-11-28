<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$notice = '';
if (!empty($_SESSION['admin_flash'])) {
    $notice = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Both fields are required.';
    } else {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT id, password_hash, force_password_reset FROM admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($adminId, $passwordHash, $forcePasswordReset);
        if ($stmt->fetch() && password_verify($password, $passwordHash)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $adminId;
            $_SESSION['requires_password_reset'] = (bool) $forcePasswordReset;
            if ($forcePasswordReset) {
                header('Location: change_password.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
        $stmt->close();
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Admin Login | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section">
            <div class="container">
                <div class="columns is-centered">
                    <div class="column is-half">
                        <h1 class="title">Admin Portal</h1>
                        <p>Please enter your credentials.</p>
                        <div class="box">
                            <?php if ($notice): ?>
                                    <div class="notification is-info"><?php echo htmlspecialchars($notice); ?></div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="post" novalidate>
                                <div class="field">
                                    <label class="label">Username</label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="text" name="username" placeholder="admin" required />
                                        <span class="icon is-small is-left"><i class="fas fa-user"></i></span>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">Password</label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="password" name="password" placeholder="********" required />
                                        <span class="icon is-small is-left"><i class="fas fa-lock"></i></span>
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="control">
                                        <button class="button is-link is-fullwidth" type="submit">
                                            <span class="icon"><i class="fas fa-right-to-bracket"></i></span>
                                            <span>Sign In</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="has-text-centered">
                                    <a href="forgot_password.php">Forgot password?</a>
                                </div>
                            </form>
                        </div>
                        <p class="has-text-grey">Need help? Contact media@lochstudios.com</p>
                    </div>
                </div>
            </div>
        </section>
    </body>
</html>