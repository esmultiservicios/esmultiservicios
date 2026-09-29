ES CMS CORE — CLEAN REUSABLE BASE

PURPOSE
Reusable single-site PHP/MySQL CMS core extracted from the stable Phase 1 implementation.
No client credentials, database connection, encryption key, logo or banner are included.

INCLUDED
- /install/ first-time installer
- Complete database.sql for fresh installations
- Administrator setup/login
- Users, roles and permissions
- Two-factor authentication and session security
- Page Content section editor with preview
- Section Manager
- Media Library
- Services, Gallery, Service Areas and Helpful Tips
- Estimate Requests
- SMTP / Microsoft Graph email configuration
- Integrations & APIs
- SEO Manager
- Website Health
- Activity Center / notifications
- Backup & Restore
- Appearance: presets, colors, typography, banner and navigation
- Settings: branding, public contact, maintenance mode and WhatsApp
- Responsive public site and responsive administrator

FRESH INSTALL
1. Upload/extract the core.
2. Create a MySQL database/user in cPanel and grant privileges.
3. Open /install/.
4. Complete the 4-step assistant: Database, Administrator, Email and Confirmation.
5. On normal cPanel hosting leave automatic DB creation OFF unless CREATE DATABASE is allowed.
6. The installer imports database.sql, creates config/config.php and config/app.key, and creates config/install.lock only after every required step succeeds.
7. The first Owner account is created by the installer itself.
8. After completion you are redirected to /admin/login.php?installed=1.
9. To perform a clean reinstall, remove only config/install.lock. Existing config is reused, only project-owned tables are recreated, and DROP DATABASE is never used.

IMPORTANT
- config/config.php, config/app.key and config/install.lock are intentionally excluded and ignored by Git.
- database.sql is the only SQL needed for a fresh installation.
- database-update.sql is retained only for controlled upgrades from older compatible builds.
- This core is SINGLE-TENANT / SINGLE-SITE today.
- The next phase can add tenant isolation and a master tenant dashboard on this clean base.
