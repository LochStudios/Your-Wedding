<?php
require_once __DIR__ . '/config.php';
require_admin_session();

$conn = get_db_connection();

// Get album filter
$albumId = !empty($_GET['album_id']) ? (int) $_GET['album_id'] : null;
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Default to start of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Load all albums for filter dropdown
$albums = [];
$stmt = $conn->prepare("SELECT id, client_names, slug FROM albums ORDER BY created_at DESC");
$stmt->execute();
$stmt->bind_result($id, $clientNames, $slug);
while ($stmt->fetch()) {
    $albums[] = ['id' => $id, 'client_names' => $clientNames, 'slug' => $slug];
}
$stmt->close();

// Get analytics data
$analyticsData = [];
if ($albumId) {
    // Get gallery views
    $stmt = $conn->prepare("SELECT DATE(viewed_at) as date, COUNT(*) as views FROM analytics WHERE album_id = ? AND view_type = 'gallery' AND viewed_at BETWEEN ? AND ? GROUP BY DATE(viewed_at) ORDER BY date DESC");
    $stmt->bind_param('iss', $albumId, $dateFrom, $dateTo);
    $stmt->execute();
    $stmt->bind_result($date, $views);
    while ($stmt->fetch()) {
        $analyticsData[] = ['date' => $date, 'views' => $views, 'type' => 'Gallery'];
    }
    $stmt->close();
    // Get total stats
    $stmt = $conn->prepare("SELECT COUNT(*) FROM analytics WHERE album_id = ? AND viewed_at BETWEEN ? AND ?");
    $stmt->bind_param('iss', $albumId, $dateFrom, $dateTo);
    $stmt->execute();
    $stmt->bind_result($totalViews);
    $stmt->fetch();
    $stmt->close();
    // Get unique visitors (by IP)
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT ip_address) FROM analytics WHERE album_id = ? AND viewed_at BETWEEN ? AND ?");
    $stmt->bind_param('iss', $albumId, $dateFrom, $dateTo);
    $stmt->execute();
    $stmt->bind_result($uniqueVisitors);
    $stmt->fetch();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Analytics | Your Wedding</title>
        <link rel="icon" href="4803712.png" type="image/png" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="stylesheet" href="style.css?v=<?php echo uuidv4(); ?>" />
    </head>
    <body>
        <?php include_once __DIR__ . '/nav.php'; ?>
        <section class="section full-bleed full-height">
            <div class="container is-fluid">
                <h1 class="title">Gallery Analytics</h1>
                <p class="subtitle">View gallery and photo statistics</p>
                <div class="box">
                    <form method="get">
                        <div class="columns">
                            <div class="column">
                                <div class="field">
                                    <label class="label">Select Gallery</label>
                                    <div class="control">
                                        <div class="select is-fullwidth">
                                            <select name="album_id" required>
                                                <option value="">Choose a gallery...</option>
                                                <?php foreach ($albums as $a): ?>
                                                    <option value="<?php echo $a['id']; ?>" <?php echo $albumId === $a['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($a['client_names'] . ' (' . $a['slug'] . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="column">
                                <div class="field">
                                    <label class="label">From Date</label>
                                    <div class="control">
                                        <input class="input" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="column">
                                <div class="field">
                                    <label class="label">To Date</label>
                                    <div class="control">
                                        <input class="input" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="column is-narrow">
                                <div class="field">
                                    <label class="label">&nbsp;</label>
                                    <div class="control">
                                        <button class="button is-link" type="submit">View Report</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <?php if ($albumId && isset($totalViews)): ?>
                    <div class="columns">
                        <div class="column">
                            <div class="box has-text-centered">
                                <p class="heading">Total Views</p>
                                <p class="title"><?php echo number_format($totalViews); ?></p>
                            </div>
                        </div>
                        <div class="column">
                            <div class="box has-text-centered">
                                <p class="heading">Unique Visitors</p>
                                <p class="title"><?php echo number_format($uniqueVisitors); ?></p>
                            </div>
                        </div>
                        <div class="column">
                            <div class="box has-text-centered">
                                <p class="heading">Date Range</p>
                                <p class="title"><?php echo date('M j', strtotime($dateFrom)) . ' - ' . date('M j', strtotime($dateTo)); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="box">
                        <h2 class="title is-5">Daily Views</h2>
                        <?php if (empty($analyticsData)): ?>
                            <p>No views recorded in this date range.</p>
                        <?php else: ?>
                            <table class="table is-fullwidth is-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Views</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analyticsData as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('F j, Y', strtotime($row['date']))); ?></td>
                                            <td><?php echo number_format($row['views']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div class="buttons">
                        <a href="send_analytics_report.php?album_id=<?php echo $albumId; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="button is-link">
                            <span class="icon"><i class="fas fa-envelope"></i></span>
                            <span>Email This Report to Client</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </body>
</html>