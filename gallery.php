<?php
require_once __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    echo 'Album not found.';
    exit;
}

$conn = get_db_connection();
$stmt = $conn->prepare('SELECT client_names, album_password, s3_folder_path FROM albums WHERE slug = ? LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$stmt->bind_result($clientNames, $albumPassword, $s3FolderPath);
$album = null;
if ($stmt->fetch()) {
    $album = [
        'client_names' => $clientNames,
        'album_password' => $albumPassword,
        's3_folder_path' => $s3FolderPath,
    ];
}
$stmt->close();

if ($album === null) {
    http_response_code(404);
    echo 'Album not found.';
    exit;
}

if (!isset($_SESSION['album_access'])) {
    $_SESSION['album_access'] = [];
}

$accessGranted = !empty($_SESSION['album_access'][$slug]);
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
        <title><?php echo htmlspecialchars($album['client_names']); ?> | Your Wedding Gallery</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-MkM9+dU5CPtz+VRrx7tIw6V0Tp9SHFExi+b0dYV16zJZyrUxjlX+8llc8frlJYe1jKhh598MBXEDqUS1bJXgBA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php if (!$accessGranted): ?>
            <section class="section">
                <div class="container">
                    <div class="box access-card">
                        <h1 class="title">Access Gallery</h1>
                        <p class="subtitle">Enter the password for <strong><?php echo htmlspecialchars($album['client_names']); ?></strong>.</p>
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
                            <h1 class="title"><?php echo htmlspecialchars($album['client_names']); ?></h1>
                            <p class="subtitle">Enjoy your celebration captured and stored securely on S3.</p>
                        </div>
                        <div class="column has-text-right">
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