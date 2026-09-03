ES MULTISERVICIOS — Premium Readability + MySQL Compatibility Patch
===================================================================

This package contains ONLY the files changed in this correction.

CURRENT INSTALLATION
--------------------
1. Back up the website and database.
2. Replace the files in this ZIP, preserving their folder paths.
3. Run database-update.sql ONCE from phpMyAdmin on the existing ES MULTISERVICIOS database.
4. Do NOT import database.sql over an existing database.
5. Hard-refresh the browser after deployment.

NEW INSTALLATION
----------------
Use database.sql (the complete synchronized database) through the installer.

WHAT THIS PATCH CORRECTS
------------------------
- High-contrast text in dark Services, Affiliate and Contact sections.
- Larger, readable public typography and navigation.
- Larger IZZY screenshots with one generous desktop showcase row per experience.
- Uniform responsive cards and media proportions.
- CAMI logo display optimized without changing the original source logo.
- Castro's Ready project logo synchronization.
- Favicon for public site, maintenance page, installer and administrator/auth screens.
- Admin readability refinements using the ES MULTISERVICIOS navy/blue/orange palette.
- Removal of whitespace inside dynamic HTML attribute values in affected admin screens.
- database-update.sql no longer uses ADD COLUMN IF NOT EXISTS, INFORMATION_SCHEMA,
  or stored-procedure requirements. It is suitable for the existing ES MULTISERVICIOS
  schema and can be re-run safely after the previous partial failed attempt.
