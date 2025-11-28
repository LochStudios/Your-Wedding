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
| `login.php` | Unified login page for Admin and Client — add `?type=client` to present client login, or `?type=admin` for admin login. |
| `create_client.php` | Create client accounts for multi-gallery access (admin-only). Includes title dropdowns and family name so display names read like "Mr & Mrs Smith". |
| `login.php?type=client` | Client login page — allows clients to sign-in and access all their assigned galleries. |
| `forgot_password.php` | Request an admin password reset; generates a short-lived reset token. |
| `reset_password.php` | Complete a password reset using a token. |
| `dashboard.php` | Album list with edit/delete controls for authenticated admins and links to update album or password. |
| `create_album.php` | Create or edit an album (slug auto-generation + password visibility). |
| `gallery.php` | Client-facing gatekeeper that lists S3 images and displays them in a responsive grid with a modal viewer. |
| `create_client.php` | Create client accounts for multi-gallery access (admin-only). |
| `login.php?type=client` | Client login page — allows clients to sign-in and access all their assigned galleries. |
| `dashboard.php` | Unified dashboard; admin users get album management and client users see their galleries. |
| `change_client_password.php` | Allow a logged-in client to change their password. |

## Setup

1. Place your credentials in `/home/lochstud/your-wedding-config/config.php` as shown in the provided template.
2. Ensure the AWS SDK autoloader is reachable at `/home/lochstud/vendors/aws-autoloader.php`.
3. Point your webroot to this project so `index.php`, `login.php`, etc., are publicly accessible.
4. Visit `/login.php`, use `admin`/`admin1234` (or update the seeded admin via SQL), then you'll be required to update the admin password before using the dashboard; a change-password link is also available.
5. If you forget the admin password, visit `/forgot_password.php` to generate a single-use reset token. If the admin account has an `email` set and SMTP is configured, the link will be sent by email; otherwise the link will be shown on the page for convenience.
6. Share `/gallery.php?slug=<slug>` and the album password with your clients or create a client account so they can sign in to see all assigned galleries.

## Optional: Custom S3/CDN base URL

If you'd like to use a CDN or a custom domain (e.g., CloudFront) instead of presigned S3 URLs, set the environment variable `YOUR_WEDDING_AWS_S3_URL` in your secure config file to the base URL for object access, e.g. `https://d123abcd.cloudfront.net` or `https://s3.amazonaws.com/my-bucket`.
If you use an S3-compatible provider such as Linode Object Storage, prefer the region-specific endpoint (e.g., `https://us-east-1.linodeobjects.com`) rather than the root domain (`linodeobjects.com`) which will not resolve. Example:

When set, the application will construct object URLs using that base URL instead of generating presigned links.
If `YOUR_WEDDING_AWS_S3_URL` is set to a provider root (e.g. `linodeobjects.com`) and `YOUR_WEDDING_AWS_BUCKET` and `YOUR_WEDDING_AWS_REGION` are set, the app attempts to auto-derive the endpoint as `https://<bucket>.<region>.linodeobjects.com`. For best results, set `YOUR_WEDDING_AWS_S3_ENDPOINT` explicitly.

## Notes

- All SQL uses prepared statements to keep injection risk low.
- Album passwords are stored in plain text so admins can display them when editing.
- Albums can now be assigned to a client account so a client can login and access all their assigned galleries.
- Admin routes show a full-screen **ACCESS DENIED** page for unauthorized requests.
- Update the default admin password immediately after deployment.
- To enable email delivery for password reset links, add an `email` against the admin account in the `admins` table and configure SMTP for PHP on your host.
