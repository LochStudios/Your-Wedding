<?php
require_once __DIR__ . '/config.php';

// Only admins can access
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$conn = get_db_connection();
$flashMessage = $_SESSION['admin_flash'] ?? null;
if (isset($_SESSION['admin_flash'])) {
    unset($_SESSION['admin_flash']);
}

// Load all venues with their client/album counts
$venues = [];
$stmt = $conn->query("
    SELECT 
        v.id, 
        v.venue_name, 
        v.username, 
        v.s3_folder_prefix,
        v.created_at,
        (SELECT COUNT(*) FROM clients WHERE venue_id = v.id) AS client_count,
        (SELECT COUNT(*) FROM albums WHERE venue_id = v.id) AS album_count
    FROM venues v 
    ORDER BY v.created_at DESC
");
while ($row = $stmt->fetch_assoc()) {
    $venues[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Manage Venues | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <div class="level">
                    <div class="level-left">
                        <div>
                            <h1 class="title">Manage Venues</h1>
                            <p class="subtitle">View and act as venue accounts</p>
                        </div>
                    </div>
                    <div class="level-right">
                        <a class="button is-primary" href="create_venue.php">
                            <span class="icon"><i class="fas fa-plus"></i></span>
                            <span>Create Venue</span>
                        </a>
                    </div>
                </div>
                
                <?php if ($flashMessage): ?>
                    <div class="notification is-success">
                        <?php echo htmlspecialchars($flashMessage); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($venues)): ?>
                    <div class="notification is-info">
                        No venues yet. <a href="create_venue.php">Create your first venue</a>.
                    </div>
                <?php else: ?>
                    <div class="box">
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Venue Name</th>
                                        <th>Username</th>
                                        <th>S3 Prefix</th>
                                        <th>Clients</th>
                                        <th>Albums</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($venues as $venue): ?>
                                        <tr>
                                            <td><?php echo (int) $venue['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($venue['venue_name']); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($venue['username']); ?></code></td>
                                            <td><?php echo htmlspecialchars($venue['s3_folder_prefix'] ?: 'N/A'); ?></td>
                                            <td><?php echo (int) $venue['client_count']; ?></td>
                                            <td><?php echo (int) $venue['album_count']; ?></td>
                                            <td><?php echo htmlspecialchars(date('M j, Y', strtotime($venue['created_at']))); ?></td>
                                            <td>
                                                <div class="buttons">
                                                    <a href="venue_dashboard.php?as_venue=<?php echo (int) $venue['id']; ?>" 
                                                       class="button is-small is-primary" 
                                                       title="Act as this venue">
                                                        <span class="icon is-small"><i class="fas fa-user-secret"></i></span>
                                                        <span>Act As</span>
                                                    </a>
                                                    <a href="create_venue.php?id=<?php echo (int) $venue['id']; ?>" 
                                                       class="button is-small is-info"
                                                       title="Edit venue">
                                                        <span class="icon is-small"><i class="fas fa-edit"></i></span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </body>
</html>
