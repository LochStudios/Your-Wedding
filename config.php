<?php
declare(strict_types=1);

/* Ensure the configured session save directory exists so PHP can write session files. */
function ensure_session_directory(): void
{
    $sessionSavePath = ini_get('session.save_path');
    $sessionDir = '';
    if ($sessionSavePath) {
        $segments = explode(';', $sessionSavePath);
        $sessionDir = trim(end($segments));
    }
    if ($sessionDir === '' || !create_session_directory($sessionDir)) {
        $sessionDir = '/home/lochstud/php_sessions';
        create_session_directory($sessionDir);
    }
    if ($sessionDir !== '') {
        session_save_path($sessionDir);
    }
}

function create_session_directory(string $path): bool
{
    if ($path === '') {
        return false;
    }
    if (is_dir($path)) {
        return true;
    }
    return @mkdir($path, 0700, true);
}

ensure_session_directory();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '/home/lochstud/your-wedding-config/config.php';

// Application configuration read from the secure config file and environment
$config = [
    'db' => [
        'host' => $YOUR_WEDDING_DB_HOST ?: '127.0.0.1',
        'username' => $YOUR_WEDDING_DB_USER ?: 'root',
        'password' => $YOUR_WEDDING_DB_PASSWORD ?: '',
        'database' => $YOUR_WEDDING_DB_NAME ?: 'your_wedding',
        'port' => (int) (3306),
    ],
    'aws' => [
        'key' => $YOUR_WEDDING_AWS_KEY ?: '',
        'secret' => $YOUR_WEDDING_AWS_SECRET ?: '',
        'region' => $YOUR_WEDDING_AWS_REGION ?: 'ap-southeast-2',
        'bucket' => $YOUR_WEDDING_AWS_BUCKET ?: '',
        's3_url' => $YOUR_WEDDING_AWS_S3_URL ?: '',
        's3_endpoint' => $YOUR_WEDDING_AWS_S3_ENDPOINT ?: '',
        's3_url_includes_bucket' => filter_var($YOUR_WEDDING_AWS_S3_URL_INCLUDES_BUCKET ?? false, FILTER_VALIDATE_BOOL),
        'sign_urls' => filter_var($YOUR_WEDDING_AWS_S3_SIGN_URLS ?? true, FILTER_VALIDATE_BOOL),
    ],
    'mail' => [
        'host' => $YOUR_WEDDING_MAIL_HOST ?? '',
        'username' => $YOUR_WEDDING_MAIL_USERNAME ?? '',
        'password' => $YOUR_WEDDING_MAIL_PASSWORD ?? '',
        'from_email' => 'no-reply@lochstudios.com',
        'from_name' => 'Your Wedding Gallery',
    ],
    'features' => [
        'admin_portal_visible' => filter_var($YOUR_WEDDING_ADMIN_PORTAL_VISIBLE ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
    ],
];

require_once '/home/lochstud/vendors/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

function get_db_connection(): mysqli
{
    static $connection;
    if ($connection instanceof mysqli) {
        return $connection;
    }
    global $config;
    $dbConfig = $config['db'];
    try {
        $connection = new mysqli(
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database'],
            $dbConfig['port']
        );
    } catch (mysqli_sql_exception $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Database Error</title>';
        echo '<style>body{font-family:"Segoe UI",sans-serif;background:#f4f6fb;color:#1a1a1a;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:1rem;}div{max-width:460px;padding:2rem;background:#fff;border-radius:12px;box-shadow:0 20px 40px rgba(4,12,32,0.12);}</style>';
        echo '<body><div><h1>Database Unreachable</h1><p>Please verify the database credentials in the configuration. Contact LochStudios if the problem persists.</p></div></body></html>';
        exit;
    }
    $connection->set_charset('utf8mb4');
    initialize_schema($connection);
    return $connection;
}

function initialize_schema(mysqli $conn): void
{
    $adminSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(191) DEFAULT NULL,
    password_reset_token_hash VARCHAR(128) DEFAULT NULL,
    password_reset_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    force_password_reset TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    $albumSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_names VARCHAR(255) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    album_password VARCHAR(191) NOT NULL,
    s3_folder_path VARCHAR(511) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    $conn->query($adminSql);
    // Create venues table for enterprise customers who manage their own clients
    $venueSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_name VARCHAR(255) NOT NULL,
    username VARCHAR(191) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    contact_email VARCHAR(191) DEFAULT NULL,
    contact_phone VARCHAR(50) DEFAULT NULL,
    s3_folder_prefix VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    $conn->query($venueSql);
    
    // Create analytics table to track gallery and photo views
    $analyticsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    view_type ENUM('gallery', 'photo') NOT NULL,
    photo_key VARCHAR(511) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_album_viewed (album_id, viewed_at),
    INDEX idx_album_type (album_id, view_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    $conn->query($analyticsSql);
    // Create contact requests table for storing contact form submissions
    $contactSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS contact_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    studio_name VARCHAR(255) NOT NULL,
    email VARCHAR(191) NOT NULL,
    message TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    $conn->query($contactSql);
    // Create clients table for multi-gallery support (associates albums to clients)
    $clientSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(191) UNIQUE DEFAULT NULL,
    display_name VARCHAR(255) NOT NULL,
    title1 VARCHAR(16) DEFAULT 'Mr',
    title2 VARCHAR(16) DEFAULT 'Mrs',
    family_name VARCHAR(255) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_reset_token_hash VARCHAR(128) DEFAULT NULL,
    password_reset_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    $conn->query($clientSql);
    ensure_client_columns($conn);
    // Ensure clients have venue_id column to link clients to venues
    try {
        $result = $conn->query("SHOW COLUMNS FROM clients LIKE 'venue_id'");
        if ($result && $result->num_rows === 0) {
            $conn->query('ALTER TABLE clients ADD COLUMN venue_id INT DEFAULT NULL');
        }
    } catch (mysqli_sql_exception $e) {
        // ignore
    }
    $conn->query($albumSql);
    // Ensure albums have client_id column to link galleries to a client account (nullable)
    try {
        $result = $conn->query("SHOW COLUMNS FROM albums LIKE 'client_id'");
        if ($result && $result->num_rows === 0) {
            $conn->query('ALTER TABLE albums ADD COLUMN client_id INT DEFAULT NULL');
        }
    } catch (mysqli_sql_exception $e) {
        // ignore
    }
    // Ensure albums have venue_id column to link galleries to venues
    try {
        $result = $conn->query("SHOW COLUMNS FROM albums LIKE 'venue_id'");
        if ($result && $result->num_rows === 0) {
            $conn->query('ALTER TABLE albums ADD COLUMN venue_id INT DEFAULT NULL');
        }
    } catch (mysqli_sql_exception $e) {
        // ignore
    }
    // Ensure slug has UNIQUE constraint (critical for site-wide URL routing)
    try {
        $indexCheck = $conn->query("SHOW INDEXES FROM albums WHERE Key_name = 'slug' AND Non_unique = 0");
        if ($indexCheck && $indexCheck->num_rows === 0) {
            $conn->query('ALTER TABLE albums ADD UNIQUE INDEX slug (slug)');
        }
    } catch (mysqli_sql_exception $e) {
        // Index may already exist or column may already be unique
    }
    ensure_admin_column($conn);
    ensure_default_admin($conn);
}

function ensure_admin_column(mysqli $conn): void
{
    $columnsToEnsure = [
        'force_password_reset' => "TINYINT(1) NOT NULL DEFAULT 0",
        'email' => "VARCHAR(191) DEFAULT NULL",
        'password_reset_token_hash' => "VARCHAR(128) DEFAULT NULL",
        'password_reset_expires_at' => "DATETIME DEFAULT NULL",
    ];
    foreach ($columnsToEnsure as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM admins LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$result) {
            continue;
        }
        if ($result->num_rows === 0) {
            $conn->query('ALTER TABLE admins ADD COLUMN ' . $conn->real_escape_string($col) . ' ' . $definition);
        }
    }
}

function ensure_default_admin(mysqli $conn): void
{
    $stmt = $conn->prepare('SELECT COUNT(*) FROM admins');
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count === 0) {
        $defaultUser = 'admin';
        $defaultPasswordHash = password_hash('admin1234', PASSWORD_DEFAULT);
        $insert = $conn->prepare('INSERT INTO admins (username, password_hash, force_password_reset) VALUES (?, ?, 1)');
        $insert->bind_param('ss', $defaultUser, $defaultPasswordHash);
        $insert->execute();
        $insert->close();
    }
}

function get_s3_client(): ?S3Client
{
    global $config;
    $aws = $config['aws'];
    if (empty($aws['key']) || empty($aws['secret']) || empty($aws['bucket'])) {
        return null;
    }
    try {
        $clientConfig = [
            'version' => 'latest',
            'region' => $aws['region'],
            'credentials' => [
                'key' => $aws['key'],
                'secret' => $aws['secret'],
            ],
        ];
        // If an S3-compatible endpoint is configured explicitly, use it and default to
        // path-style addressing so non-AWS providers (Linode, MinIO) work correctly.
        $endpoint = trim($aws['s3_endpoint'] ?? '');
        // If endpoint is not provided, try to use s3_url as an endpoint fallback
        // for S3-compatible providers when the URL doesn't look like an AWS or CDN URL.
        if ($endpoint === '') {
            $maybe = trim($aws['s3_url'] ?? '');
            if ($maybe !== '' && !preg_match('/cloudfront|s3\.amazonaws|amazonaws|s3-/', strtolower($maybe))) {
                $endpoint = $maybe;
            }
        }
        // If the supplied endpoint is a generic provider root such as `linodeobjects.com`
        // and both bucket and region are set, derive a bucket-specific host like
        // `yourwedding.au-mel-1.linodeobjects.com` and use that as the endpoint.
        $derived = get_derived_s3_endpoint();
        if ($derived !== null) {
            $endpoint = $derived;
        }
        if ($endpoint !== '') {
            if (!preg_match('#^https?://#', $endpoint)) {
                $endpoint = 'https://' . $endpoint;
            }
            // Normalize the endpoint trimmed value
            $endpoint = rtrim($endpoint, '/');
            $host = parse_url($endpoint, PHP_URL_HOST) ?: '';
            $path = parse_url($endpoint, PHP_URL_PATH) ?: '';
            $bucket = (string)($aws['bucket'] ?? '');
            $endpointIncludesBucket = false;
            if ($bucket !== '' && $host !== '') {
                if (stripos($host, $bucket . '.') === 0) {
                    $endpointIncludesBucket = true;
                }
                if ($path !== '' && stripos($path, '/' . $bucket) !== false) {
                    $endpointIncludesBucket = true;
                }
            }
            // If we detected the endpoint host includes the bucket as prefix
            // (e.g., yourwedding.au-mel-1.linodeobjects.com), configure the
            // S3 client to use the provider root endpoint and virtual-host
            // addressing so the effective request host is correct (bucket.host).
            if ($endpointIncludesBucket && $host !== '' && $bucket !== '') {
                // Remove the bucket prefix from host to derive the root host
                if (stripos($host, $bucket . '.') === 0) {
                    $rootHost = substr($host, strlen($bucket) + 1);
                    if ($rootHost !== false && $rootHost !== '') {
                        $endpoint = (parse_url($endpoint, PHP_URL_SCHEME) ?: 'https') . '://' . $rootHost;
                    }
                }
                $clientConfig['endpoint'] = $endpoint;
                // Use virtual-host addressing to place the bucket on the host
                $clientConfig['use_path_style_endpoint'] = false;
            } else {
                // If the endpoint did not include the bucket prefix, default to
                // path-style addressing for non-AWS endpoints so bucket appears
                // in the path, e.g. https://endpoint/bucket/obj
                $clientConfig['endpoint'] = $endpoint;
                $clientConfig['use_path_style_endpoint'] = true;
            }
        }
        // If we have an endpoint, perform a quick DNS resolution check to avoid
        // the AWS SDK throwing cURL error 6 when an invalid host is configured.
        if (!empty($clientConfig['endpoint'])) {
            $host = parse_url($clientConfig['endpoint'], PHP_URL_HOST) ?: $clientConfig['endpoint'];
            $resolved = @gethostbyname($host);
            if ($resolved === $host || $resolved === false) {
                error_log('Configured S3 endpoint does not resolve: ' . $host);
                return null;
            }
        }
        return new S3Client($clientConfig);
    } catch (AwsException $exception) {
        error_log('S3 client initialization failed: ' . $exception->getMessage());
        return null;
    }
}

function get_aws_bucket(): string
{
    global $config;
    return $config['aws']['bucket'];
}

/**
 * Try to derive a compatible S3 endpoint when the configured host is a generic
 * provider root (for example `linodeobjects.com`) and we have the bucket/region
 * details in configuration.
 */
function get_derived_s3_endpoint(): ?string
{
    global $config;
    $aws = $config['aws'];
    $maybe = trim((string)($aws['s3_endpoint'] ?? '')) ?: trim((string)($aws['s3_url'] ?? ''));
    if ($maybe === '') {
        return null;
    }
    if (!preg_match('#^https?://#', $maybe)) {
        $maybe = 'https://' . $maybe;
    }
    $host = parse_url($maybe, PHP_URL_HOST) ?: $maybe;
    if ($host === '') {
        return null;
    }
    // Only attempt smart derivation for Linode-style domains
    if (stripos($host, 'linodeobjects.com') !== false) {
        // If the host is exactly the root (linodeobjects.com) or doesn't contain
        // the bucket prefix (no more than two components before 'linodeobjects'),
        // and we have bucket + region, build bucket.region.linodeobjects.com.
        $segments = explode('.', $host);
        $bucket = trim((string)($aws['bucket'] ?? ''));
        $region = trim((string)($aws['region'] ?? ''));
        // If the host is exactly 'linodeobjects.com' or lacks the bucket+region
        // part, and we have both bucket and region, construct the host.
        if (($host === 'linodeobjects.com' || count($segments) < 3) && $bucket !== '' && $region !== '') {
            return 'https://' . $bucket . '.' . $region . '.linodeobjects.com';
        }
    }
    return null;
}

/**
 * Return a custom S3 base URL (cloudfront/CNAME or S3 endpoint) if configured.
 * If empty, null is returned and calling code should fallback to presigned URLs or
 * SDK-generated defaults.
 */
function get_s3_base_url(): ?string
{
    global $config;
    $url = trim($config['aws']['s3_url'] ?? '');
    if ($url === '') {
        return null;
    }
    // Ensure scheme is present so URL joins work correctly
    if (!preg_match('#^https?://#', $url)) {
        $url = 'https://' . $url;
    }
    // If the user provided only a generic provider domain for Linode, replace
    // the base URL with the derived endpoint (bucket.region.linodeobjects.com)
    // when possible, so direct object URLs work without the user changing config.
    if (stripos($url, 'linodeobjects.com') !== false) {
        $derived = get_derived_s3_endpoint();
        if ($derived !== null) {
            $url = $derived;
        }
    }
    return $url === '' ? null : rtrim($url, '/');
}

function get_s3_url_includes_bucket(): bool
{
    global $config;
    return !empty($config['aws']['s3_url_includes_bucket']);
}

function get_s3_signing_enabled(): bool
{
    global $config;
    return !empty($config['aws']['sign_urls']);
}

/**
 * Diagnose common S3 misconfiguration issues and return a helpful message if one
 * is found, or null if no obvious problems are detected. This is used to present
 * actionable errors to the admin instead of raw cURL errors caused by misconfigured
 * endpoints (e.g. missing region in `linodeobjects.com`).
 */
function get_s3_diagnosis(): ?string
{
    global $config;
    $aws = $config['aws'];
    if (empty($aws['key']) || empty($aws['secret']) || empty($aws['bucket'])) {
        return 'AWS S3 credentials are not configured (key/secret/bucket).';
    }
    // Build a candidate endpoint if configured
    $endpoint = trim($aws['s3_endpoint'] ?? '');
    if ($endpoint === '') {
        $maybe = trim($aws['s3_url'] ?? '');
        if ($maybe !== '' && !preg_match('/cloudfront|s3\.amazonaws|amazonaws|s3-/', strtolower($maybe))) {
            $endpoint = $maybe;
        }
    }
    if ($endpoint === '') {
        // No custom endpoint configured - assume AWS defaults will be used
        return null;
    }
    // Ensure it's a valid URL and extract the host
    if (!preg_match('#^https?://#', $endpoint)) {
        $endpoint = 'https://' . $endpoint;
    }
    $host = parse_url($endpoint, PHP_URL_HOST) ?: $endpoint;
    if ($host === '') {
        return 'Configured S3 endpoint is invalid: ' . htmlspecialchars($endpoint);
    }
    // Attempt a DNS resolution check; gethostbyname returns the input string
    // unchanged if the host can't be resolved.
    $resolved = @gethostbyname($host);
    if ($resolved === $host || $resolved === false) {
        // If this host is Linode root, see if we can derive the bucket-specific
        // host from the configuration (bucket + region) and the derived host
        // resolves — in that case we won't show a diagnostic because the code
        // will auto-use the derived host.
        $derived = get_derived_s3_endpoint();
        if ($derived !== null) {
            $derivedHost = parse_url($derived, PHP_URL_HOST) ?: $derived;
            $derivedResolved = @gethostbyname($derivedHost);
            if ($derivedResolved !== $derivedHost && $derivedResolved !== false) {
                // Derived host resolves; don't treat this as an error.
                return null;
            }
        }
        if (stripos($host, 'linodeobjects.com') !== false && strtolower($host) === 'linodeobjects.com') {
            $bucket = trim($aws['bucket'] ?? '');
            $suggest = $bucket !== '' ? htmlspecialchars($bucket) . '.<region>.linodeobjects.com' : '<bucket>.<region>.linodeobjects.com';
            return 'Configured S3 endpoint does not resolve: ' . htmlspecialchars($host) . '. For Linode Object Storage, use the region-specific host including your bucket: e.g. ' . $suggest . ' (replace <region> with your region identifier, e.g. au-mel-1).';
        }
        return 'Configured S3 endpoint does not resolve: ' . htmlspecialchars($host) . '. Please verify the endpoint or set the correct region-specific endpoint (for example, use the full cluster URL for Linode Object Storage, not just "linodeobjects.com").';
    }
    return null;
}

function require_admin_session(): void
{
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>ACCESS DENIED</title>';
        echo '<style>body{margin:0;background:#000;color:#f00;display:flex;align-items:center;justify-content:center;height:100vh;font-family:Arial, sans-serif;font-size:3rem;}h1{text-transform:uppercase;}</style>';
        echo '<body><h1>ACCESS DENIED</h1></body></html>';
        exit;
    }
}

function uuidv4(): string
{
    return bin2hex(random_bytes(4));
}

function is_admin_portal_visible(): bool
{
    global $config;
    return !empty($config['features']['admin_portal_visible']);
}

function ensure_client_columns(mysqli $conn): void
{
    $columnsToEnsure = [
        'title1' => "VARCHAR(16) DEFAULT 'Mr'",
        'title2' => "VARCHAR(16) DEFAULT 'Mrs'",
        'family_name' => "VARCHAR(255) DEFAULT NULL",
        'display_name' => "VARCHAR(255) NOT NULL",
        'email' => "VARCHAR(191) DEFAULT NULL",
    ];
    foreach ($columnsToEnsure as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM clients LIKE '" . $conn->real_escape_string($col) . "'");
        if (!$result) {
            continue;
        }
        if ($result->num_rows === 0) {
            $conn->query('ALTER TABLE clients ADD COLUMN ' . $conn->real_escape_string($col) . ' ' . $definition);
        }
    }
}

/**
 * Return the endpoint host the SDK will use for S3 operations. This mirrors
 * the logic in get_s3_client() so we can show diagnostics without creating a
 * client connection.
 */
function get_s3_effective_endpoint(): ?string
{
    global $config;
    $aws = $config['aws'];
    $endpoint = trim($aws['s3_endpoint'] ?? '') ?: trim($aws['s3_url'] ?? '');
    if ($endpoint === '') {
        return null;
    }
    if (!preg_match('#^https?://#', $endpoint)) {
        $endpoint = 'https://' . $endpoint;
    }
    $derived = get_derived_s3_endpoint();
    if ($derived !== null) {
        // If derived endpoint included a bucket host, the SDK will use the
        // provider root (bucket removed) for its endpoint. We should still
        // return the SDK-apparent endpoint for diagnostics, which is the
        // provider root with scheme.
        $derivedHost = parse_url($derived, PHP_URL_HOST) ?: $derived;
        if (stripos($derivedHost, (string)$aws['bucket'] . '.') === 0) {
            // remove bucket prefix
            $rootHost = substr($derivedHost, strlen((string)$aws['bucket']) + 1);
            return 'https://' . $rootHost;
        }
    }
    return $endpoint;
}

function get_s3_effective_base_url(): ?string
{
    // This is the URL used to build public object links (CDN or bucket-host).
    global $config;
    $aws = $config['aws'];
    $url = trim($aws['s3_url'] ?? '');
    if ($url !== '') {
        if (!preg_match('#^https?://#', $url)) {
            $url = 'https://' . $url;
        }
        $derived = get_derived_s3_endpoint();
        if ($derived !== null) {
            return $derived;
        }
        return rtrim($url, '/');
    }
    // If s3_url isn't set, try deriving a bucket-based host
    $derived = get_derived_s3_endpoint();
    if ($derived !== null) {
        return $derived;
    }
    return null;
}