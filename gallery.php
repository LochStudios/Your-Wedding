<?php
require_once __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');

// If accessed without a slug, present an inline client login that logs a user in by Client ID and password
$clientLoginError = '';
if ($slug === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $clientIdInput = trim($_POST['client_id'] ?? '');
        $password = $_POST['client_password'] ?? '';
        if ($clientIdInput === '' || $password === '') {
            $clientLoginError = 'Both fields are required.';
        } else {
            $conn = get_db_connection();
            // Support numeric ID or username/email input
            if (is_numeric($clientIdInput)) {
                $stmt = $conn->prepare('SELECT id, password_hash FROM clients WHERE id = ? LIMIT 1');
                $cid = (int) $clientIdInput;
                $stmt->bind_param('i', $cid);
            } else {
                $stmt = $conn->prepare('SELECT id, password_hash FROM clients WHERE username = ? LIMIT 1');
                $stmt->bind_param('s', $clientIdInput);
            }
            $stmt->execute();
            $stmt->bind_result($foundId, $passwordHash);
            if ($stmt->fetch() && password_verify($password, $passwordHash)) {
                $_SESSION['client_logged_in'] = (int) $foundId;
                header('Location: client_dashboard.php');
                exit;
            }
            $stmt->close();
            $clientLoginError = 'Invalid client ID or password.';
        }
    }
    // Render the inline client login UI
    ?>
    <!DOCTYPE html>
    <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Access Your Galleries | Your Wedding</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
            <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
        </head>
        <body>
            <?php include_once __DIR__ . '/nav.php'; ?>
            <section class="section">
                <div class="container">
                    <div class="columns is-centered">
                        <div class="column is-6">
                            <div class="box" style="text-align:center;">
                                <h1 class="title">Enter Client ID</h1>
                                <p class="subtitle">Enter the Client ID and password to view your assigned galleries.</p>
                                <?php if ($clientLoginError): ?>
                                    <div class="notification is-danger"><?php echo htmlspecialchars($clientLoginError); ?></div>
                                <?php endif; ?>
                                <form method="post">
                                    <div class="field">
                                        <label class="label">Client ID or Username</label>
                                        <div class="control">
                                            <input class="input" type="text" name="client_id" placeholder="Client ID or username" required />
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="label">Password</label>
                                        <div class="control">
                                            <input class="input" type="password" name="client_password" placeholder="Password" required />
                                        </div>
                                    </div>
                                    <div class="field">
                                        <div class="control">
                                            <button class="button is-link is-fullwidth" type="submit">Sign In</button>
                                        </div>
                                    </div>
                                    <p class="has-text-grey">Don't have a Client ID? Contact the person who delivered your gallery to set one up.</p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </body>
    </html>
    <?php
    exit;
}

$conn = get_db_connection();
$stmt = $conn->prepare("SELECT a.client_id, a.client_names, a.album_password, a.s3_folder_path, COALESCE(c.display_name, CONCAT(c.title1, ' & ', c.title2, ' ', c.family_name)) AS client_display_name FROM albums a LEFT JOIN clients c ON c.id = a.client_id WHERE a.slug = ? LIMIT 1");
$stmt->bind_param('s', $slug);
$stmt->execute();
$stmt->bind_result($clientId, $clientNames, $albumPassword, $s3FolderPath, $clientDisplayName);
$album = null;
if ($stmt->fetch()) {
    $album = [
        'client_id' => $clientId,
        'client_names' => $clientNames,
        'client_display_name' => $clientDisplayName,
        'album_password' => $albumPassword,
        's3_folder_path' => $s3FolderPath,
    ];
}
$stmt->close();

if ($album === null) {
    // Provide a friendly UI for invalid slug: allow searching by slug or signing in with Client ID
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Client login post
        if (!empty($_POST['client_id']) && isset($_POST['client_password'])) {
            $clientIdInput = trim($_POST['client_id'] ?? '');
            $password = $_POST['client_password'] ?? '';
            if ($clientIdInput !== '' && $password !== '') {
                $conn = get_db_connection();
                if (is_numeric($clientIdInput)) {
                    $stmt = $conn->prepare('SELECT id, password_hash FROM clients WHERE id = ? LIMIT 1');
                    $cid = (int) $clientIdInput;
                    $stmt->bind_param('i', $cid);
                } else {
                    $stmt = $conn->prepare('SELECT id, password_hash FROM clients WHERE username = ? LIMIT 1');
                    $stmt->bind_param('s', $clientIdInput);
                }
                $stmt->execute();
                $stmt->bind_result($foundId, $passwordHash);
                if ($stmt->fetch() && password_verify($password, $passwordHash)) {
                    $_SESSION['client_logged_in'] = (int) $foundId;
                    header('Location: client_dashboard.php');
                    exit;
                }
                $stmt->close();
                $clientLoginError = 'Invalid client ID or password.';
            } else {
                $clientLoginError = 'Both fields are required.';
            }
        }
        // Slug search post
        if (!empty($_POST['search_slug'])) {
            $searchSlug = trim($_POST['search_slug']);
            if ($searchSlug !== '') {
                header('Location: gallery.php?slug=' . urlencode($searchSlug));
                exit;
            }
        }
    }
    // Render the album-not-found page with helpful actions
    ?>
    <!DOCTYPE html>
    <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Album not found | Your Wedding</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
            <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
        </head>
        <body>
            <?php include_once __DIR__ . '/nav.php'; ?>
            <section class="section">
                <div class="container">
                    <div class="columns is-centered">
                        <div class="column is-6">
                            <div class="box" style="text-align:center;">
                                <h1 class="title">Album not found</h1>
                                <p class="subtitle">We can't find that gallery. Try these options below:</p>
                                <?php if (!empty($clientLoginError)): ?>
                                    <div class="notification is-danger"><?php echo htmlspecialchars($clientLoginError); ?></div>
                                <?php endif; ?>
                                <form method="post" style="margin-bottom:1rem;">
                                    <div class="field">
                                        <label class="label">Gallery Slug</label>
                                        <div class="control has-icons-right">
                                            <input class="input" type="text" name="search_slug" placeholder="Enter gallery slug (e.g. john-jane)" />
                                            <button class="button is-link mt-2" type="submit">Find Gallery</button>
                                        </div>
                                    </div>
                                </form>
                                <hr />
                                <p class="subtitle">Or, sign in with your Client ID to access all your galleries:</p>
                                <form method="post">
                                    <div class="field">
                                        <label class="label">Client ID or Username</label>
                                        <div class="control">
                                            <input class="input" type="text" name="client_id" placeholder="Client ID or username" />
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="label">Password</label>
                                        <div class="control">
                                            <input class="input" type="password" name="client_password" placeholder="Password" />
                                        </div>
                                    </div>
                                    <div class="field">
                                        <div class="control">
                                            <button class="button is-link is-fullwidth" type="submit">Sign In</button>
                                        </div>
                                    </div>
                                </form>
                                <p class="has-text-grey">If you don't have a Client ID, contact the person who delivered your gallery to set one up.</p>
                                <div class="mt-4">
                                    <a href="/" class="button is-light">Back to Home</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </body>
    </html>
    <?php
    exit;
}

if (!isset($_SESSION['album_access'])) {
    $_SESSION['album_access'] = [];
}

$accessGranted = !empty($_SESSION['album_access'][$slug]);
// If this album is assigned to a client, allow access when that client is logged in
if (!$accessGranted && !empty($album['client_id']) && !empty($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === $album['client_id']) {
    $accessGranted = true;
}
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempt = trim($_POST['album_password'] ?? '');
    if ($attempt === $album['album_password']) {
        $_SESSION['album_access'][$slug] = true;
        header('Location: gallery.php?slug=' . urlencode($slug));
        exit;
    }
    $passwordError = 'Password does not match.';
}

if ($accessGranted) {
    $s3 = get_s3_client();
    $bucket = get_aws_bucket();
    $galleryImages = [];
    $s3Error = '';
    if ($s3 === null || $bucket === '') {
        $s3Error = 'AWS S3 credentials are not configured.';
    } else {
        $prefix = rtrim($album['s3_folder_path'], '/') . '/';
        try {
            $params = ['Bucket' => $bucket, 'Prefix' => ltrim($prefix, '/')];
            do {
                $result = $s3->listObjectsV2($params);
                foreach ($result['Contents'] ?? [] as $object) {
                    if (str_ends_with($object['Key'], '/')) {
                        continue;
                    }
                    $cmd = $s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $object['Key']]);
                    $request = $s3->createPresignedRequest($cmd, '+15 minutes');
                    $galleryImages[] = (string) $request->getUri();
                }
                $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
            } while (!empty($params['ContinuationToken']));
        } catch (\Aws\Exception\AwsException $exception) {
            $s3Error = 'Unable to list gallery. ' . $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php echo htmlspecialchars($album['client_display_name'] ?: $album['client_names']); ?> | Your Wedding Gallery</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <?php if (!$accessGranted): ?>
            <section class="section">
                <div class="container">
                    <div class="box access-card">
                        <h1 class="title">Access Gallery</h1>
                                        <p class="subtitle">Enter the password for <span class="has-text-weight-bold"><?php echo htmlspecialchars($album['client_display_name'] ?: $album['client_names']); ?></span>.</p>
                                        <?php if (!empty($album['client_id'])): ?>
                                            <p class="help">Or <a href="client_login.php?redirect=<?php echo urlencode('gallery.php?slug=' . $slug); ?>">Sign in as the assigned client</a> to view this and other galleries.</p>
                                        <?php endif; ?>
                        <?php if ($passwordError): ?>
                            <div class="notification is-danger"><?php echo htmlspecialchars($passwordError); ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="field">
                                <label class="label">Password</label>
                                <div class="control">
                                    <input class="input" type="password" name="album_password" placeholder="Album password" required />
                                </div>
                            </div>
                            <div class="field">
                                <div class="control">
                                    <button type="submit" class="button is-link">Unlock Gallery</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <section class="section">
                <div class="container">
                    <div class="columns is-vcentered">
                        <div class="column">
                            <h1 class="title"><?php echo htmlspecialchars($album['client_display_name'] ?: $album['client_names']); ?></h1>
                            <p class="subtitle">Enjoy your celebration captured and stored securely on S3.</p>
                        </div>
                        <div class="column has-text-right">
                            <?php if (!empty($_SESSION['client_logged_in'])): ?>
                                <a class="button is-light" href="client_logout.php">Sign out</a>
                            <?php endif; ?>
                            <a class="button is-light" href="/">Back to Landing</a>
                        </div>
                    </div>
                    <?php if (!empty($s3Error)): ?>
                        <div class="notification is-danger"><?php echo htmlspecialchars($s3Error); ?></div>
                    <?php elseif (empty($galleryImages)): ?>
                        <div class="notification is-info">No images found in this folder yet.</div>
                    <?php else: ?>
                        <div class="columns is-multiline gallery-grid">
                            <?php foreach ($galleryImages as $imageUrl): ?>
                                <div class="column is-one-third">
                                    <div class="card">
                                        <div class="card-image">
                                            <figure class="image is-4by3">
                                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" data-full="<?php echo htmlspecialchars($imageUrl); ?>" alt="Gallery photo" />
                                            </figure>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <div class="lightbox-overlay" id="lightbox">
                <img src="" alt="Full size" />
            </div>
            <script>
                const overlay = document.getElementById('lightbox');
                const overlayImage = overlay.querySelector('img');
                document.querySelectorAll('.gallery-grid img').forEach((thumb) => {
                    thumb.addEventListener('click', () => {
                        overlayImage.src = thumb.dataset.full;
                        overlay.classList.add('active');
                    });
                });
                overlay.addEventListener('click', () => overlay.classList.remove('active'));
            </script>
        <?php endif; ?>
    </body>
</html>