<?php
require_once __DIR__ . '/config.php';

// If admin already signed in
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

// role can come from GET (tab preselect) or POST (form select)
$role = $_GET['type'] ?? 'admin';
$redirect = $_GET['redirect'] ?? null;

// If a client is already signed in and role is client, go to dashboard
if (!empty($_SESSION['client_logged_in']) && $role === 'client') {
    header('Location: dashboard.php');
    exit;
}
$redirect = $_GET['redirect'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? $role;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Both fields are required.';
    } else {
        $conn = get_db_connection();
        if ($role === 'admin') {
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
            $error = 'Invalid admin username or password';
        } else {
            // Client Portal
            // support numeric client ID or username
            if (is_numeric($username)) {
                $stmt = $conn->prepare('SELECT id, password_hash FROM clients WHERE id = ? LIMIT 1');
                $cid = (int) $username;
                $stmt->bind_param('i', $cid);
            } else {
                $stmt = $conn->prepare('SELECT id, password_hash FROM clients WHERE username = ? LIMIT 1');
                $stmt->bind_param('s', $username);
            }
            $stmt->execute();
            $stmt->bind_result($clientId, $passwordHash);
            if ($stmt->fetch() && password_verify($password, $passwordHash)) {
                $_SESSION['client_logged_in'] = (int) $clientId;
                $redirectTarget = $_POST['redirect'] ?? $redirect ?? 'dashboard.php';
                header('Location: ' . $redirectTarget);
                exit;
            }
            $stmt->close();
            $error = 'Invalid client ID or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Portal Login | Your Wedding</title>
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
                    <div class="column is-half">
                        <div class="tabs is-toggle is-centered">
                            <ul>
                                <li class="<?php echo $role === 'admin' ? 'is-active' : ''; ?>"><a href="login.php?type=admin<?php echo $redirect ? '&redirect=' . urlencode($redirect) : ''; ?>">Admin</a></li>
                                <li class="<?php echo $role === 'client' ? 'is-active' : ''; ?>"><a href="login.php?type=client<?php echo $redirect ? '&redirect=' . urlencode($redirect) : ''; ?>">Client</a></li>
                            </ul>
                        </div>
                        <h1 class="title"><?php echo $role === 'admin' ? 'Company Portal' : 'Client Portal'; ?></h1>
                        <p><?php echo $role === 'admin' ? 'Please enter your credentials.' : 'Please sign in to access your assigned galleries.'; ?></p>
                        <div class="box">
                            <?php if ($notice): ?>
                                    <div class="notification is-info"><?php echo htmlspecialchars($notice); ?></div>
                                <?php endif; ?>
                                <?php if ($error): ?>
                                    <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="post" novalidate>
                                <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>" />
                                <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>" /><?php endif; ?>
                                <div class="field">
                                    <label class="label"><?php echo $role === 'admin' ? 'Username' : 'Client ID or Username'; ?></label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="text" name="username" placeholder="<?php echo $role === 'admin' ? 'admin' : 'john@example.com or 123'; ?>" required />
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
                                    <?php if ($role === 'admin'): ?>
                                        <a href="forgot_password.php">Forgot password?</a>
                                    <?php else: ?>
                                        <a href="/">Can't sign in?</a>
                                    <?php endif; ?>
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