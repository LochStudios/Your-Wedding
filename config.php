<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '/home/lochstud/your-wedding-config/config.php';

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
    $connection = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database'],
        $dbConfig['port']
    );
    if ($connection->connect_errno) {
        die('Database connection failed: ' . $connection->connect_error);
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
    $conn->query($albumSql);
    ensure_admin_column($conn);
    ensure_default_admin($conn);
}

function ensure_admin_column(mysqli $conn): void
{
    $result = $conn->query("SHOW COLUMNS FROM admins LIKE 'force_password_reset'");
    if (!$result) {
        return;
    }
    if ($result->num_rows === 0) {
        $conn->query('ALTER TABLE admins ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0');
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
        return new S3Client([
            'version' => 'latest',
            'region' => $aws['region'],
            'credentials' => [
                'key' => $aws['key'],
                'secret' => $aws['secret'],
            ],
        ]);
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