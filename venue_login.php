<?php
require_once __DIR__ . '/config.php';

// If venue already signed in, redirect to venue dashboard
if (!empty($_SESSION['venue_logged_in'])) {
    header('Location: venue_dashboard.php');
    exit;
}

$error = '';
$notice = $_SESSION['venue_flash'] ?? '';
if (isset($_SESSION['venue_flash'])) {
    unset($_SESSION['venue_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Both fields are required.';
    } else {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT id, password_hash, venue_name FROM venues WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($venueId, $passwordHash, $venueName);
        if ($stmt->fetch() && password_verify($password, $passwordHash)) {
            $_SESSION['venue_logged_in'] = (int) $venueId;
            $_SESSION['venue_name'] = $venueName;
            header('Location: venue_dashboard.php');
            exit;
        }
        $stmt->close();
        $error = 'Invalid venue username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Venue Login | Your Wedding</title>
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
                        <div class="box">
                            <h1 class="title">Venue Portal Login</h1>
                            <p class="subtitle">Access your venue dashboard</p>
                            <?php if ($notice): ?>
                                <div class="notification is-success"><?php echo htmlspecialchars($notice); ?></div>
                            <?php endif; ?>
                            <?php if ($error): ?>
                                <div class="notification is-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            <form method="post">
                                <div class="field">
                                    <label class="label">Username</label>
                                    <div class="control">
                                        <input class="input" type="text" name="username" placeholder="Venue username" required autofocus />
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label">Password</label>
                                    <div class="control">
                                        <input class="input" type="password" name="password" placeholder="Password" required />
                                    </div>
                                </div>
                                <div class="field">
                                    <div class="control">
                                        <button class="button is-link is-fullwidth" type="submit">Sign In</button>
                                    </div>
                                </div>
                            </form>
                            <hr />
                            <p class="has-text-centered">
                                <a href="venue_team_login.php">Team Member Login</a>
                            </p>
                            <p class="has-text-grey mt-4 has-text-centered">
                                <a href="/">Back to Home</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </body>
</html>