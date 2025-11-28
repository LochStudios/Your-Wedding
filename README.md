# Your Wedding Viewer

A lightweight, custom PHP gallery for wedding clients. Admins create albums that point to existing AWS S3 folders, manage access, and let couples unlock their memories via a slug+password combination.

## Key Features

- **Bulma + FontAwesome UI** for marketing site, admin forms, and gallery layout.
- **MySQLi with auto-schema creation** plus a seeded admin user.
- **AWS S3 browser** via the provided SDK loader (`/home/lochstud/vendors/aws-autoloader.php`) to list album assets and generate signed URLs.
- **Password-protected galleries** per slug with a lightbox-style viewer.
- **Simple config inclusion** from `/home/lochstud/your-wedding-config/config.php` (cPanel-compatible) for database and AWS credentials.

## Files

| File | Purpose |
| --- | --- |
| `config.php` | Central DB/S3 helpers, schema bootstrapping, and session gate. Requires the secure config file from `/home/lochstud/your-wedding-config/config.php`. |
| `index.php` | Marketing landing page with hero, services, pricing, and contact sections. |
| `style.css` | Custom Bulma overrides and gallery/lightbox styling. |
| `login.php` | Admin login using bcrypt + sessions. |
| `dashboard.php` | Album list with edit/delete controls for authenticated admins and links to update album or password. |
| `create_album.php` | Create or edit an album (slug auto-generation + password visibility). |
| `gallery.php` | Client-facing gatekeeper that lists S3 images and displays them in a responsive grid with a modal viewer. |

## Setup

1. Place your credentials in `/home/lochstud/your-wedding-config/config.php` as shown in the provided template.
2. Ensure the AWS SDK autoloader is reachable at `/home/lochstud/vendors/aws-autoloader.php`.
3. Point your webroot to this project so `index.php`, `login.php`, etc., are publicly accessible.
4. Visit `/login.php`, use `admin`/`admin1234` (or update the seeded admin via SQL), then you'll be required to update the admin password before using the dashboard; a change-password link is also available.
5. Share `/gallery.php?slug=<slug>` and the album password with your clients.

## Notes

- All SQL uses prepared statements to keep injection risk low.
- Album passwords are stored in plain text so admins can display them when editing.
- Admin routes show a full-screen **ACCESS DENIED** page for unauthorized requests.
- Update the default admin password immediately after deployment.
