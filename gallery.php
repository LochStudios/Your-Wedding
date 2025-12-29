<?php
require_once __DIR__ . '/config.php';

$slug = trim($_GET['slug'] ?? '');

// If accessed without a slug, present an inline client login that logs a user in by Client ID and password
// However, if a client is already logged in, redirect them to their dashboard instead
if ($slug === '' && !empty($_SESSION['client_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$clientLoginError = '';
if ($slug === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $clientIdInput = trim($_POST['client_id'] ?? '');
        $password = $_POST['client_password'] ?? '';
        if ($clientIdInput === '' || $password === '') {
            $clientLoginError = 'Both fields are required.';
        } else {
            $conn = get_db_connection();
            // First try album slug authentication
            $slugStmt = $conn->prepare('SELECT slug, client_id, album_password FROM albums WHERE slug = ? LIMIT 1');
            $slugStmt->bind_param('s', $clientIdInput);
            $slugStmt->execute();
            $slugStmt->bind_result($foundSlug, $albumClientId, $albumPassword);
            if ($slugStmt->fetch() && $password === $albumPassword) {
                $_SESSION['album_access'][$foundSlug] = true;
                if ($albumClientId !== null) {
                    $_SESSION['client_logged_in'] = (int) $albumClientId;
                }
                $slugStmt->close();
                header('Location: gallery.php?slug=' . urlencode($foundSlug));
                exit;
            }
            $slugStmt->close();
            // Fall back to client ID or username authentication
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
                header('Location: dashboard.php');
                exit;
            }
            $stmt->close();
            $clientLoginError = 'Invalid credentials.';
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
            <link rel="icon" href="4803712.png" type="image/png" />
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
            <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
        </head>
        <body>
            <?php include_once __DIR__ . '/nav.php'; ?>
            <section class="section full-bleed full-height">
                <div class="container is-fluid">
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
                                        <label class="label">Client ID, Username, or Gallery Slug</label>
                                        <div class="control">
                                            <input class="input" type="text" name="client_id" placeholder="Client ID, username, or gallery slug" required />
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
$stmt = $conn->prepare("SELECT a.id, a.client_id, a.client_names, a.album_password, a.s3_folder_path, COALESCE(c.display_name, CONCAT(c.title1, ' & ', c.title2, ' ', c.family_name)) AS client_display_name FROM albums a LEFT JOIN clients c ON c.id = a.client_id WHERE a.slug = ? LIMIT 1");
$stmt->bind_param('s', $slug);
$stmt->execute();
$stmt->bind_result($albumId, $clientId, $clientNames, $albumPassword, $s3FolderPath, $clientDisplayName);
$album = null;
if ($stmt->fetch()) {
    $album = [
        'id' => $albumId,
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
                // First try album slug authentication
                $slugStmt = $conn->prepare('SELECT slug, client_id, album_password FROM albums WHERE slug = ? LIMIT 1');
                $slugStmt->bind_param('s', $clientIdInput);
                $slugStmt->execute();
                $slugStmt->bind_result($foundSlug, $albumClientId, $albumPassword);
                if ($slugStmt->fetch() && $password === $albumPassword) {
                    $_SESSION['album_access'][$foundSlug] = true;
                    if ($albumClientId !== null) {
                        $_SESSION['client_logged_in'] = (int) $albumClientId;
                    }
                    $slugStmt->close();
                    header('Location: gallery.php?slug=' . urlencode($foundSlug));
                    exit;
                }
                $slugStmt->close();
                // Fall back to client ID or username authentication
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
                    header('Location: dashboard.php');
                    exit;
                }
                $stmt->close();
                $clientLoginError = 'Invalid credentials.';
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
            <link rel="icon" href="4803712.png" type="image/png" />
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
            <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
        </head>
        <body>
            <?php include_once __DIR__ . '/nav.php'; ?>
            <section class="section full-bleed full-height">
                <div class="container is-fluid">
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
                                        <label class="label">Client ID, Username, or Gallery Slug</label>
                                        <div class="control">
                                            <input class="input" type="text" name="client_id" placeholder="Client ID, username, or gallery slug" />
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
    // Track gallery view
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $trackStmt = $conn->prepare('INSERT INTO analytics (album_id, view_type, ip_address, user_agent) VALUES (?, ?, ?, ?)');
    $viewType = 'gallery';
    $trackStmt->bind_param('isss', $album['id'], $viewType, $ipAddress, $userAgent);
    $trackStmt->execute();
    $trackStmt->close();
    $s3 = get_s3_client();
    $bucket = get_aws_bucket();
    $galleryImages = [];
    $s3Error = '';
    $diagnostic = get_s3_diagnosis();
    if ($diagnostic !== null) {
        $s3Error = $diagnostic;
    } else if ($s3 === null || $bucket === '') {
        $s3Error = 'AWS S3 credentials are not configured.';
    } else {
        $prefix = rtrim($album['s3_folder_path'], '/') . '/';
        try {
            $params = ['Bucket' => $bucket, 'Prefix' => ltrim($prefix, '/')];
            $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'tif', 'tiff', 'bmp', 'heic', 'heif', 'svg', 'avif'];
            do {
                $result = $s3->listObjectsV2($params);
                $signEnabled = get_s3_signing_enabled();
                foreach ($result['Contents'] ?? [] as $object) {
                    if (str_ends_with($object['Key'], '/')) {
                        continue;
                    }
                    $extension = strtolower(pathinfo($object['Key'], PATHINFO_EXTENSION));
                    if ($extension === '' || !in_array($extension, $imageExtensions, true)) {
                        continue;
                    }
                    // If an S3 base URL is configured (e.g. CloudFront/CNAME), build direct
                    // URLs to objects on that domain. Otherwise, fall back to presigned URLs.
                    $s3BaseUrl = get_s3_base_url();
                    $shouldSign = $signEnabled && $s3 !== null;
                    if ($shouldSign) {
                        try {
                            $cmd = $s3->getCommand('GetObject', ['Bucket' => get_aws_bucket(), 'Key' => $object['Key']]);
                            $request = $s3->createPresignedRequest($cmd, '+15 minutes');
                            $galleryImages[] = (string) $request->getUri();
                            continue;
                        } catch (\Aws\Exception\AwsException $ex) {
                            error_log('Failed to create presigned URL: ' . $ex->getMessage());
                            // fallthrough to other fallbacks
                        }
                    }
                    if (!empty($s3BaseUrl)) {
                        $bucket = get_aws_bucket();
                        $baseContainsBucket = stripos($s3BaseUrl, '/' . $bucket) !== false || stripos($s3BaseUrl, $bucket . '/') !== false || stripos($s3BaseUrl, $bucket) !== false;
                        if ($baseContainsBucket || get_s3_url_includes_bucket()) {
                            $galleryImages[] = $s3BaseUrl . '/' . ltrim($object['Key'], '/');
                        } else {
                            $galleryImages[] = $s3BaseUrl . '/' . $bucket . '/' . ltrim($object['Key'], '/');
                        }
                    } else {
                        // If we couldn't sign, but have a valid S3 client, try create presigned URL again (best effort)
                        if ($s3 !== null) {
                            try {
                                $cmd = $s3->getCommand('GetObject', ['Bucket' => $bucket, 'Key' => $object['Key']]);
                                $request = $s3->createPresignedRequest($cmd, '+15 minutes');
                                $galleryImages[] = (string) $request->getUri();
                                continue;
                            } catch (\Aws\Exception\AwsException $ex) {
                                error_log('Fallback presigned URL failed: ' . $ex->getMessage());
                            }
                        }
                        // No signing available and no base URL to use — add null placeholder, not local path
                        $galleryImages[] = null;
                    }
                }
                $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
            } while (!empty($params['ContinuationToken']));
        } catch (\Aws\Exception\AwsException $exception) {
            $awsCode = method_exists($exception, 'getAwsErrorCode') ? $exception->getAwsErrorCode() : null;
            if ($awsCode === 'NoSuchKey') {
                $s3Error = 'NoSuchKey: There were no objects found at the specified prefix. Please verify the album S3 folder path and that objects have been uploaded to the configured bucket/prefix.';
            } else {
                $s3Error = 'Unable to list gallery. ' . $exception->getMessage();
            }
            // If the user is an admin, append diagnostics showing the endpoint and base URL
            if (!empty($_SESSION['admin_logged_in'])) {
                $endpoint = get_s3_effective_endpoint();
                $baseUrl = get_s3_effective_base_url();
                if ($endpoint) {
                    $s3Error .= "\n\nEffective S3 SDK endpoint: {$endpoint}";
                }
                if ($baseUrl) {
                    $s3Error .= "\nDirect object base URL: {$baseUrl}";
                }
            }
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
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <?php if (!$accessGranted): ?>
            <section class="section full-bleed full-height">
                <div class="container is-fluid">
                    <div class="box access-card">
                        <h1 class="title">Welcome <?php echo htmlspecialchars($album['client_display_name'] ?: $album['client_names']); ?></h1>
                        <p class="subtitle">Please enter your gallery password<?php if (!empty($album['client_id'])): ?> or <a href="login.php?type=client&redirect=<?php echo urlencode('gallery.php?slug=' . $slug); ?>">sign into your client profile</a><?php endif; ?> to view this and your other galleries.</p>
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
            <section class="section full-bleed full-height">
                <div class="container is-fluid">
                    <div class="columns is-vcentered">
                        <div class="column">
                            <h1 class="title"><?php echo htmlspecialchars($album['client_display_name'] ?: $album['client_names']); ?></h1>
                            <p class="subtitle">Enjoy your celebration!</p>
                        </div>
                            <div class="column has-text-right">
                            <?php if (!empty($_SESSION['client_logged_in'])): ?>
                                <a class="button is-light" href="logout.php">Sign out</a>
                            <?php endif; ?>
                                <?php if (!empty($galleryImages) && count(array_filter($galleryImages)) > 0): ?>
                                    <a id="downloadAllBtn" class="button is-link" href="download_all.php?slug=<?php echo urlencode($slug); ?>" download="<?php echo htmlspecialchars(($slug ?: 'gallery') . '.zip'); ?>">Download All</a>
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
                                                <?php if (!empty($imageUrl)): ?>
                                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" data-full="<?php echo htmlspecialchars($imageUrl); ?>" alt="Gallery photo" />
                                                <?php else: ?>
                                                    <div class="has-text-centered has-text-grey">[Private image — no accessible URL]</div>
                                                <?php endif; ?>
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
                <button class="lightbox-close" id="lightboxClose" aria-label="Close">×</button>
                <button class="lightbox-prev" id="lightboxPrev" aria-label="Previous">‹</button>
                <button class="lightbox-next" id="lightboxNext" aria-label="Next">›</button>
                <a class="button lightbox-download" id="lightboxDownload" href="#" target="_blank" rel="noopener">Download</a>
                <img src="" alt="Full size" />
            </div>
            <script>
                const overlay = document.getElementById('lightbox');
                const overlayImage = overlay.querySelector('img');
                const nextBtn = document.getElementById('lightboxNext');
                const prevBtn = document.getElementById('lightboxPrev');
                const closeBtn = document.getElementById('lightboxClose');
                const downloadBtn = document.getElementById('lightboxDownload');
                const downloadAllBtn = document.getElementById('downloadAllBtn');
                const thumbs = Array.from(document.querySelectorAll('.gallery-grid img[data-full]'));
                const images = thumbs.map(t => t.dataset.full).filter(Boolean);
                let currentIndex = 0;
                const imagesLen = images.length;
                thumbs.forEach((thumb, idx) => {
                    thumb.addEventListener('click', () => {
                        currentIndex = idx;
                        overlayImage.src = images[currentIndex];
                        downloadBtn.href = images[currentIndex] || '#';
                        overlay.classList.add('active');
                    });
                });
                function showIndex(i) {
                    if (imagesLen === 0) return;
                    let idx = i;
                    if (idx < 0) idx = (idx % imagesLen + imagesLen) % imagesLen;
                    if (idx >= imagesLen) idx = idx % imagesLen;
                    currentIndex = idx;
                    overlayImage.src = images[currentIndex];
                    downloadBtn.href = images[currentIndex] || '#';
                }
                nextBtn.addEventListener('click', (e) => { e.stopPropagation(); showIndex(currentIndex + 1); });
                prevBtn.addEventListener('click', (e) => { e.stopPropagation(); showIndex(currentIndex - 1); });
                closeBtn.addEventListener('click', () => overlay.classList.remove('active'));
                downloadBtn.setAttribute('download', '');
                downloadBtn.addEventListener('click', (e) => {
                    if (!images[currentIndex]) { e.preventDefault(); return; }
                    // Let the link act as download; for cross origin signed URLs this should download.
                });
                // Download All handled by server-side zip via anchor `download_all.php`
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay || e.target === overlayImage) {
                        overlay.classList.remove('active');
                    }
                });
                document.addEventListener('keydown', (e) => {
                    if (!overlay.classList.contains('active')) return;
                    if (e.key === 'ArrowRight') { showIndex((currentIndex + 1) % images.length); }
                    if (e.key === 'ArrowLeft') { showIndex((currentIndex - 1 + images.length) % images.length); }
                    if (e.key === 'Escape') { overlay.classList.remove('active'); }
                });
            </script>
        <?php endif; ?>
    </body>
</html>