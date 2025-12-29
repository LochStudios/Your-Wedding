<?php
require_once __DIR__ . '/config.php';

// Determine current role and whether admin is acting as venue
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isTeamMember = !empty($_SESSION['venue_team_logged_in']);
$isVenueOwner = !empty($_SESSION['venue_logged_in']);
$actingVenueId = null;
$venueName = '';

if ($isAdmin && !empty($_GET['as_venue'])) {
    // Admin acting as venue
    $actingVenueId = (int) $_GET['as_venue'];
    $venueId = $actingVenueId;
    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT venue_name FROM venues WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $actingVenueId);
    $stmt->execute();
    $stmt->bind_result($venueName);
    $stmt->fetch();
    $stmt->close();
    $venueName = $venueName ?? 'Venue';
} elseif ($isTeamMember) {
    // Check permissions for team members
    if (empty($_SESSION['can_upload_photos'])) {
        $_SESSION['venue_flash'] = 'You do not have permission to upload photos.';
        header('Location: venue_dashboard.php');
        exit;
    }
    $venueId = (int) $_SESSION['venue_id'];
    $venueName = $_SESSION['venue_team_name'] ?? 'Team Member';
} elseif ($isVenueOwner) {
    $venueId = (int) $_SESSION['venue_logged_in'];
    $venueName = $_SESSION['venue_name'] ?? 'Venue';
} else {
    header('Location: venue_login.php');
    exit;
}

// Build query parameter for passing venue context in links
$venueParam = ($isAdmin && $actingVenueId !== null) ? '?as_venue=' . $actingVenueId : '';
$venueParamAmp = ($isAdmin && $actingVenueId !== null) ? '&as_venue=' . $actingVenueId : '';

if (!isset($conn)) {
    $conn = get_db_connection();
}

// Get venue's s3_folder_prefix
$venuePrefix = '';
$stmt = $conn->prepare('SELECT s3_folder_prefix FROM venues WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $venueId);
$stmt->execute();
$stmt->bind_result($venuePrefix);
$stmt->fetch();
$stmt->close();

// If no prefix set, use venue ID
if (empty($venuePrefix)) {
    $venuePrefix = 'venue-' . $venueId;
}

// Get venue's galleries for dropdown
$venueGalleries = [];
$stmt = $conn->prepare('SELECT id, client_names, s3_folder_path FROM albums WHERE venue_id = ? ORDER BY client_names');
$stmt->bind_param('i', $venueId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $venueGalleries[] = $row;
}
$stmt->close();

// Handle upload
$uploadResult = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    $albumId = !empty($_POST['album_id']) ? (int) $_POST['album_id'] : null;
    if ($albumId === null) {
        $errors[] = 'Please select a gallery.';
    } else {
        // Verify venue owns this gallery
        $stmt = $conn->prepare('SELECT s3_folder_path FROM albums WHERE id = ? AND venue_id = ? LIMIT 1');
        $stmt->bind_param('ii', $albumId, $venueId);
        $stmt->execute();
        $stmt->bind_result($s3Folder);
        if (!$stmt->fetch()) {
            $errors[] = 'Gallery not found or access denied.';
            $albumId = null;
        }
        $stmt->close();
        if ($albumId !== null && !empty($_FILES['photos']['name'][0])) {
            $s3 = get_s3_client();
            $bucket = S3_BUCKET;
            $uploaded = 0;
            $failed = 0;
            $fileCount = count($_FILES['photos']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                    $failed++;
                    continue;
                }
                $tmpPath = $_FILES['photos']['tmp_name'][$i];
                $originalName = $_FILES['photos']['name'][$i];
                $fileSize = $_FILES['photos']['size'][$i];
                $mimeType = $_FILES['photos']['type'][$i];
                // Validate image and video
                $allowedMimes = [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic', 'image/heif',
                    'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/x-matroska'
                ];
                if (!in_array(strtolower($mimeType), $allowedMimes)) {
                    $failed++;
                    continue;
                }
                // Generate unique filename
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $isVideo = str_starts_with(strtolower($mimeType), 'video/');
                $prefix = $isVideo ? 'video_' : 'photo_';
                $uniqueName = uniqid($prefix, true) . '.' . $ext;
                // Build S3 path: venuePrefix/year/album-folder/filename
                $currentYear = date('Y');
                $venueFolderPath = rtrim($venuePrefix, '/') . '/' . $currentYear . '/' . rtrim($s3Folder, '/');
                $s3Key = $venueFolderPath . '/' . $uniqueName;
                try {
                    $s3->putObject([
                        'Bucket' => $bucket,
                        'Key' => $s3Key,
                        'SourceFile' => $tmpPath,
                        'ContentType' => $mimeType,
                        'ACL' => 'private'
                    ]);
                    $uploaded++;
                } catch (Exception $e) {
                    $failed++;
                    error_log('S3 upload failed for ' . $originalName . ': ' . $e->getMessage());
                }
            }
            $uploadResult = sprintf('%d file(s) uploaded successfully. %d failed.', $uploaded, $failed);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Upload Photos | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/venue_nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title">Upload Photos & Videos</h1>
                <p class="subtitle">Add photos and videos to your galleries</p>
                <?php if ($uploadResult): ?>
                    <div class="notification is-success"><?php echo htmlspecialchars($uploadResult); ?></div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="notification is-danger">
                        <ul>
                            <?php foreach ($errors as $e): ?>
                                <li><?php echo htmlspecialchars($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="box">
                    <form id="uploadForm" method="post" enctype="multipart/form-data">
                        <div class="field">
                            <label class="label">Select Gallery</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="album_id" id="gallerySelect" required>
                                        <option value="">-- Choose a gallery --</option>
                                        <?php foreach ($venueGalleries as $vg): ?>
                                            <option value="<?php echo (int) $vg['id']; ?>">
                                                <?php echo htmlspecialchars($vg['client_names']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Choose Photos & Videos</label>
                            <div class="drop-zone" id="dropZone">
                                <i class="fas fa-cloud-upload-alt fa-3x" style="color: #3273dc;"></i>
                                <p class="mt-3"><strong>Drag and drop photos & videos here</strong></p>
                                <p class="has-text-grey">or click to browse</p>
                                <input type="file" name="photos[]" id="fileInput" multiple accept="image/*,video/*" style="display: none;" />
                            </div>
                        </div>
                        <div id="previewContainer" class="preview-grid"></div>
                        <div class="upload-progress" id="uploadProgress">
                            <progress class="progress is-primary" max="100" id="progressBar">0%</progress>
                            <p class="has-text-centered" id="progressText">Uploading...</p>
                        </div>
                        <div class="field is-grouped mt-4">
                            <div class="control">
                                <button class="button is-link" type="submit" id="uploadBtn" disabled>
                                    <span class="icon"><i class="fas fa-upload"></i></span>
                                    <span>Upload Files</span>
                                </button>
                            </div>
                            <div class="control">
                                <button class="button is-light" type="button" id="clearBtn" disabled>Clear All</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
        <script>
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const previewContainer = document.getElementById('previewContainer');
            const uploadBtn = document.getElementById('uploadBtn');
            const clearBtn = document.getElementById('clearBtn');
            const uploadForm = document.getElementById('uploadForm');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            let selectedFiles = [];
            // Click to browse
            dropZone.addEventListener('click', () => fileInput.click());
            // Drag and drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                const files = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/') || f.type.startsWith('video/'));
                addFiles(files);
            });
            // File input change
            fileInput.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                addFiles(files);
            });
            // Clear all
            clearBtn.addEventListener('click', () => {
                selectedFiles = [];
                previewContainer.innerHTML = '';
                fileInput.value = '';
                uploadBtn.disabled = true;
                clearBtn.disabled = true;
            });
            // Add files to preview
            function addFiles(files) {
                files.forEach(file => {
                    if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                        return; // Skip duplicates
                    }
                    selectedFiles.push(file);
                    const div = document.createElement('div');
                    div.className = 'preview-item';
                    
                    if (file.type.startsWith('video/')) {
                        // Video preview
                        const video = document.createElement('video');
                        video.src = URL.createObjectURL(file);
                        video.style.width = '100%';
                        video.style.height = '100%';
                        video.style.objectFit = 'cover';
                        div.appendChild(video);
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'remove-btn';
                        removeBtn.dataset.name = file.name;
                        removeBtn.dataset.size = file.size;
                        removeBtn.textContent = '×';
                        div.appendChild(removeBtn);
                        previewContainer.appendChild(div);
                        removeBtn.addEventListener('click', function() {
                            const name = this.dataset.name;
                            const size = parseInt(this.dataset.size);
                            selectedFiles = selectedFiles.filter(f => !(f.name === name && f.size === size));
                            div.remove();
                            updateButtons();
                        });
                    } else {
                        // Image preview
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="${file.name}" />
                                <button type="button" class="remove-btn" data-name="${file.name}" data-size="${file.size}">×</button>
                            `;
                                div.querySelector('.remove-btn').addEventListener('click', function() {
                                const name = this.dataset.name;
                                const size = parseInt(this.dataset.size);
                                selectedFiles = selectedFiles.filter(f => !(f.name === name && f.size === size));
                                div.remove();
                                updateButtons();
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                });
                updateButtons();
            }
            function updateButtons() {
                uploadBtn.disabled = selectedFiles.length === 0;
                clearBtn.disabled = selectedFiles.length === 0;
            }
            // Form submit
            uploadForm.addEventListener('submit', (e) => {
                if (selectedFiles.length === 0) {
                    e.preventDefault();
                    return;
                }
                // Update file input with selected files
                const dt = new DataTransfer();
                selectedFiles.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;
                // Show progress
                uploadProgress.style.display = 'block';
                uploadBtn.disabled = true;
                clearBtn.disabled = true;
            });
        </script>
    </body>
</html>