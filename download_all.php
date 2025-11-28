<?php
require_once __DIR__ . '/config.php';

// Basic security: must be an admin or a client with access to the specified gallery.
session_start();

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(400);
    echo 'Missing slug';
    exit;
}

$conn = get_db_connection();
$stmt = $conn->prepare('SELECT id, client_id, s3_folder_path FROM albums WHERE slug = ? LIMIT 1');
$stmt->bind_param('s', $slug);
$stmt->execute();
$stmt->bind_result($albumId, $clientId, $s3FolderPath);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo 'Album not found';
    exit;
}
$stmt->close();

// Determine if the current user is allowed to download
$isAdmin = !empty($_SESSION['admin_logged_in']);
$isClient = !empty($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === $clientId;
// Also allow if the album was previously unlocked with password in session
$isUnlocked = !empty($_SESSION['album_access'][$slug]);
if (!$isAdmin && !$isClient && !$isUnlocked) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$s3 = get_s3_client();
$bucket = get_aws_bucket();
if ($s3 === null || $bucket === '') {
    http_response_code(500);
    echo 'S3 not configured';
    exit;
}

// List objects
try {
    $prefix = rtrim($s3FolderPath, '/') . '/';
    $params = ['Bucket' => $bucket, 'Prefix' => ltrim($prefix, '/')];
    $objects = [];
    do {
        $result = $s3->listObjectsV2($params);
        foreach ($result['Contents'] ?? [] as $object) {
            if (!str_ends_with($object['Key'], '/')) {
                $objects[] = $object;
            }
        }
        $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
    } while (!empty($params['ContinuationToken']));
} catch (\Aws\Exception\AwsException $e) {
    http_response_code(500);
    echo 'Unable to list objects: ' . htmlspecialchars($e->getMessage());
    exit;
}

if (count($objects) === 0) {
    http_response_code(404);
    echo 'No objects to download';
    exit;
}

// Create a temporary zip file
$tmpZip = tempnam(sys_get_temp_dir(), 'yw_zip_');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Unable to create zip';
    exit;
}

// Impose a reasonable per-request size limit to avoid server trouble (e.g., 1.5GB)
$totalBytes = 0;
foreach ($objects as $object) { $totalBytes += (int)$object['Size']; }
if ($totalBytes > 1500 * 1024 * 1024) {
    $zip->close();
    unlink($tmpZip);
    http_response_code(413);
    echo 'Album too large to zip via web UI';
    exit;
}

// Download each object to a temporary file and add to zip
foreach ($objects as $object) {
    $key = $object['Key'];
    $tmpImagePath = tempnam(sys_get_temp_dir(), 'yw_img_');
    try {
        // Save to file (SDK supports SaveAs)
        $s3->getObject(['Bucket' => $bucket, 'Key' => $key, 'SaveAs' => $tmpImagePath]);
        $filename = basename($key);
        $zip->addFile($tmpImagePath, $filename);
        unlink($tmpImagePath);
    } catch (\Aws\Exception\AwsException $e) {
        // Skip the file on error and log it
        error_log('download_all: failed to fetch ' . $key . ': ' . $e->getMessage());
        if (file_exists($tmpImagePath)) unlink($tmpImagePath);
    }
}

$zip->close();

// Stream the zip to the client with proper headers
header('Content-Type: application/zip');
header('Content-Transfer-Encoding: Binary');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9._-]/i', '-', ($slug ?: 'gallery')) . '.zip"');
header('Content-Length: ' . filesize($tmpZip));
ob_clean();
flush();
readfile($tmpZip);
unlink($tmpZip);
exit;
