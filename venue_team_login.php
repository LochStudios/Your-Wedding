<?php
require_once __DIR__ . '/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $conn = get_db_connection();
        $stmt = $conn->prepare('SELECT id, venue_id, name, password_hash, can_create_clients, can_create_albums, can_upload_photos, can_view_analytics FROM venue_team WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->bind_result($teamId, $venueId, $teamName, $hash, $canCreateClients, $canCreateAlbums, $canUploadPhotos, $canViewAnalytics);
        if ($stmt->fetch()) {
            if (password_verify($password, $hash)) {
                $_SESSION['venue_team_logged_in'] = $teamId;
                $_SESSION['venue_team_name'] = $teamName;
                $_SESSION['venue_id'] = $venueId;
                $_SESSION['can_create_clients'] = (bool) $canCreateClients;
                $_SESSION['can_create_albums'] = (bool) $canCreateAlbums;
                $_SESSION['can_upload_photos'] = (bool) $canUploadPhotos;
                $_SESSION['can_view_analytics'] = (bool) $canViewAnalytics;
                
                $stmt->close();
                header('Location: venue_dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Team Login | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-max-desktop">
                <div class="box">
                    <h1 class="title">Venue Team Login</h1>
                    <p class="subtitle">Sign in with your team member account</p>
                    <?php if ($error): ?>
                        <div class="notification is-danger">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="field">
                            <label class="label">Username</label>
                            <div class="control has-icons-left">
                                <input class="input" type="text" name="username" placeholder="Username" required autofocus />
                                <span class="icon is-small is-left">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Password</label>
                            <div class="control has-icons-left">
                                <input class="input" type="password" name="password" placeholder="Password" required />
                                <span class="icon is-small is-left">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                        </div>
                        <div class="field">
                            <div class="control">
                                <button class="button is-primary is-fullwidth" type="submit">
                                    <span class="icon">
                                        <i class="fas fa-sign-in-alt"></i>
                                    </span>
                                    <span>Sign In</span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <hr />
                    <p class="has-text-centered">
                        <a href="venue_login.php">Venue Owner Login</a>
                    </p>
                </div>
            </div>
        </section>
    </body>
</html>