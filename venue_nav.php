<?php
/* Reusable navigation bar for venue pages */
// This file assumes the following variables are set in the calling page:
// $isAdmin, $isVenueOwner, $isTeamMember, $actingVenueId, $venueParam
// $canCreateClients, $canCreateAlbums, $canUploadPhotos

// Set defaults for permission variables if not set
$canCreateClients = $canCreateClients ?? true;
$canCreateAlbums = $canCreateAlbums ?? true;
$canUploadPhotos = $canUploadPhotos ?? true;
$isVenueOwner = $isVenueOwner ?? false;
$isAdmin = $isAdmin ?? false;
$actingVenueId = $actingVenueId ?? null;
$venueParam = $venueParam ?? '';
?>
<nav class="navbar" role="navigation">
    <div class="navbar-brand">
        <a class="navbar-item" href="/"><strong>LochStudios</strong></a>
        <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="venueNav">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>
    <div id="venueNav" class="navbar-menu">
        <div class="navbar-start">
            <a class="navbar-item" href="venue_dashboard.php<?php echo $venueParam; ?>">Dashboard</a>
            <?php if ($canCreateClients): ?>
                <a class="navbar-item" href="venue_create_client.php<?php echo $venueParam; ?>">Create Client</a>
            <?php endif; ?>
            <?php if ($canCreateAlbums): ?>
                <a class="navbar-item" href="venue_create_album.php<?php echo $venueParam; ?>">Create Gallery</a>
            <?php endif; ?>
            <?php if ($canUploadPhotos): ?>
                <a class="navbar-item" href="venue_upload.php<?php echo $venueParam; ?>">Upload Photos</a>
            <?php endif; ?>
            <?php if ($isVenueOwner): ?>
                <a class="navbar-item" href="venue_team.php<?php echo $venueParam; ?>">Manage Team</a>
            <?php endif; ?>
        </div>
        <div class="navbar-end">
            <div class="navbar-item">
                <div class="buttons">
                    <?php if ($isAdmin && $actingVenueId !== null): ?>
                        <a class="button is-light" href="manage_venues.php">Stop Acting</a>
                    <?php else: ?>
                        <a class="button is-light" href="venue_logout.php">Sign Out</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>
