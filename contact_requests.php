<?php
require_once __DIR__ . '/config.php';
require_admin_session();

$conn = get_db_connection();

// Handle delete
if (!empty($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    $stmt = $conn->prepare('DELETE FROM contact_requests WHERE id = ?');
    $stmt->bind_param('i', $deleteId);
    $stmt->execute();
    $stmt->close();
    header('Location: contact_requests.php');
    exit;
}

// Get all contact requests
$requests = [];
$result = $conn->query('SELECT id, studio_name, email, message, submitted_at FROM contact_requests ORDER BY submitted_at DESC');
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Contact Requests | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include 'nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title">Contact Requests</h1>
                <p class="subtitle">Messages submitted through the contact form</p>
                <?php if (empty($requests)): ?>
                    <div class="notification is-info">
                        No contact requests yet.
                    </div>
                <?php else: ?>
                    <div class="box">
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable">
                                <thead>
                                    <tr>
                                        <th>Submitted</th>
                                        <th>Studio Name</th>
                                        <th>Email</th>
                                        <th>Message</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $req): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($req['submitted_at']))); ?></td>
                                            <td><?php echo htmlspecialchars($req['studio_name']); ?></td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($req['email']); ?>">
                                                    <?php echo htmlspecialchars($req['email']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div style="max-width: 400px; white-space: pre-wrap;">
                                                    <?php echo htmlspecialchars($req['message']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="?delete_id=<?php echo (int) $req['id']; ?>" 
                                                   class="button is-small is-danger" 
                                                   onclick="return confirm('Delete this contact request?');">
                                                    <span class="icon"><i class="fas fa-trash"></i></span>
                                                </a>
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