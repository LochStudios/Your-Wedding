<?php
require_once __DIR__ . '/config.php';

$error = '';
$message = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
        $error = 'Please provide the username.';
    } else {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT id, email FROM admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($adminId, $email);
        if ($stmt->fetch()) {
            $stmt->close();
            // Generate token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $update = $conn->prepare('UPDATE admins SET password_reset_token_hash = ?, password_reset_expires_at = ? WHERE id = ?');
            $update->bind_param('ssi', $tokenHash, $expiresAt, $adminId);
            $update->execute();
            $update->close();
            // Try to email (if email available); otherwise show link on page
            $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
            $resetLink = $base . 'reset_password.php?token=' . $token;
            // Don't reveal whether the username exists to the user; show a safe, non-identifying message
            $message = 'If an account with that username exists, a password reset link has been generated and (if possible) emailed to the account email.';
            if (!empty($email)) {
                // Attempt to send an email using PHP's mail() if configured
                $subject = 'Your Wedding - Admin Password Reset';
                $body = "A password reset was requested. Use the following link to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in 60 minutes.";
                @mail($email, $subject, $body, 'From: no-reply@your-wedding.local');
                // Do not display the link on the page if an email was sent
                $resetLink = '';
            }
        } else {
            // Do not disclose whether the user exists
            $stmt->close();
            $message = 'If an account with that username exists, a password reset link has been generated and (if possible) emailed to the account email.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Forgot Password | Your Wedding</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <div class="columns is-centered">
                    <div class="column is-half">
                        <h1 class="title">Forgot Password</h1>
                        <p>Enter the admin username to request a password reset.</p>
                        <div class="box">
                            <?php if ($error): ?>
                                <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <?php if ($message): ?>
                                <div class="notification is-success"><?php echo htmlspecialchars($message); ?></div>
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
                                    <div class="control">
                                        <button class="button is-link is-fullwidth" type="submit">Request Reset</button>
                                    </div>
                                </div>
                            </form>
                            <?php if (!empty($resetLink)): ?>
                                <div class="notification is-info">
                                    <p><strong>Reset Link (for now):</strong></p>
                                    <p><code><?php echo htmlspecialchars($resetLink); ?></code></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="has-text-grey"><a href="login.php">Back to login</a></p>
                    </div>
                </div>
            </div>
        </section>
    </body>
</html>
