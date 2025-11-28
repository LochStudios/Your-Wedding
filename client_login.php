<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['client_logged_in'])) {
    $redir = $_GET['redirect'] ?? 'index.php';
    header('Location: ' . $redir);
    exit;
}

$error = '';
$notice = '';
$redirectTarget = $_GET['redirect'] ?? 'client_dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Both fields are required.';
    } else {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT id, password_hash FROM clients WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($clientId, $passwordHash);
        if ($stmt->fetch() && password_verify($password, $passwordHash)) {
            $_SESSION['client_logged_in'] = (int) $clientId;
            header('Location: ' . $redirectTarget);
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
        <title>Client Login | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section">
            <div class="container">
                <div class="columns is-centered">
                    <div class="column is-half">
                        <h1 class="title">Client login</h1>
                        <p>Please sign in with your provided username to access your galleries.</p>
                        <div class="box">
                            <?php if ($notice): ?>
                                <div class="notification is-info"><?php echo htmlspecialchars($notice); ?></div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="post" novalidate>
                                <div class="field">
                                    <label class="label">Username / Email</label>
                                    <div class="control has-icons-left">
                                        <input class="input" type="text" name="username" placeholder="john@example.com" required />
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
                                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirectTarget); ?>">
                                <div class="field">
                                    <div class="control">
                                        <button class="button is-link is-fullwidth" type="submit">Sign In</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <p class="has-text-grey">If you cannot sign in, contact the person who delivered your gallery for an account or password.</p>
                    </div>
                </div>
            </div>
        </section>
    </body>
    </html>
