# ES MULTISERVICIOS — Corporate CMS & Marketing Website

A bilingual, responsive corporate website and administration platform for **ES MULTISERVICIOS**, focused on presenting digital solutions, proprietary products, services, commercial plans, projects, affiliate opportunities and direct contact channels.

The public website is designed for non-technical visitors, while the administration area allows authorized users to manage content without editing source code.

---

## Main public experience

- Spanish and English website (`ES / EN`).
- Premium responsive navigation for desktop, tablet and mobile.
- ES MULTISERVICIOS corporate branding.
- Hero section with direct demo and WhatsApp calls to action.
- Proprietary product presentation for **IZZY** and **CAMI**.
- Large, uniform product screenshots instead of compressed thumbnail layouts.
- IZZY classic billing, configurable visual experience and responsive web presentation.
- CAMI dashboard, clinical workflow and access presentation using real product screenshots.
- Services and custom-development positioning.
- Structured IZZY commercial plan catalog with responsive pricing cards, featured-plan emphasis and a dedicated visual-sales / restaurant option.
- Affiliate program presentation with editable bilingual benefit rules, process explanation and direct WhatsApp conversion CTA.
- Project / case-study section with logo or cover image.
- Optional video showcase using YouTube, Vimeo, MP4 or WEBM.
- Optional corporate artwork gallery.
- Responsive contact and WhatsApp experience.
- SEO title, description, social image and favicon support.
- Maintenance mode with private administrator preview.

---

## Administration

The administrator is based on the reusable CMS core, but uses a dedicated ES MULTISERVICIOS visual identity instead of client-specific styling.

### Marketing website ES / EN

The **Marketing site ES/EN** workspace manages:

- Hero copy.
- Solutions copy.
- IZZY copy and feature list.
- CAMI copy and feature list.
- Services introduction.
- Affiliate content.
- Project section heading.
- Contact copy.
- Commercial plans.
- Projects and case studies.
- Project logo / cover upload.

A real embedded preview is included so administrators can inspect Home, Solutions, IZZY, IZZY Plans, CAMI, Services, Projects, Affiliates and Contact without leaving the administrator.

Commercial plans can be created, edited, featured, published, hidden or deleted from the same workspace. Affiliate benefit copy is bilingual and editable without touching source code.

### Video manager

The **Videos** module supports:

- Multiple videos.
- YouTube URLs, including Shorts.
- Vimeo URLs.
- Direct MP4 / WEBM upload.
- Optional poster / cover image.
- Drag & drop.
- Clipboard paste.
- File chooser.
- Display order.
- Published / hidden state.
- Real administrator preview.
- Uniform public two-column presentation on desktop and one-column presentation on smaller screens.

### Flexible corporate artwork

The Page Content / About workspace supports:

- One combined corporate artwork.
- Two separate graphics.
- Multiple approved graphics.
- Drag & drop, paste and file chooser.
- Ordering and visibility controls.
- Automatic public display only when artwork is published.

### Existing CMS capabilities preserved

- Dashboard and action center.
- Page Content editor.
- Section Manager.
- Media Library.
- Services.
- Gallery.
- Service Areas.
- Home Tips.
- Estimate Requests.
- SMTP / Microsoft Graph email configuration.
- Integrations & APIs.
- SEO Manager.
- Website Health.
- Notifications and activity history.
- Backup & Restore.
- Users.
- Roles & Permissions.
- Approval Queue.
- Security Center and session revocation.
- Profile and password management.
- Two-factor authentication.
- Appearance settings.
- Site settings and maintenance mode.

---

## Visual system

The public and administrator interfaces use a dedicated ES MULTISERVICIOS palette:

- Deep navy: technology, trust and structure.
- Corporate blue: interactive and product accents.
- Restrained orange: brand emphasis and active states.
- White / soft blue-gray surfaces: readability and visual breathing room.

The interface intentionally avoids decorative gradients and crowded layouts.

When cards or screenshots share the same row, they use uniform dimensions to keep the presentation clean and professional.

---

## Responsive rules

The website is designed from a minimum practical width of approximately 320px and scales through mobile, tablet, laptop and large desktop displays.

Important responsive behaviors include:

- No horizontal layout dependency for mobile use.
- Navigation changes to a clear mobile panel.
- Product screenshots move from two columns to one column when required.
- Adjacent cards retain equal geometry on desktop.
- Video cards use a consistent 16:9 frame.
- Forms and administrator controls stack cleanly on smaller screens.
- Long text, URLs and uploaded-file names are allowed to wrap instead of overlapping nearby elements.

---

## Installation

### New installation

1. Upload the complete project to the target directory.
2. Make sure PHP can write inside `config/` during installation.
3. Open `/install/` in the browser.
4. Follow the **3-step setup assistant**:
   - Database connection.
   - CMS configuration.
   - Owner administrator creation.
5. Use `database.sql` only for a new installation.

The installer can create the database when the hosting account permits it. On cPanel environments where PHP cannot create databases, create the database and user in cPanel first and run the installer with database creation disabled.

### Existing compatible installation

Run:

```text
database-update.sql
```

The cumulative update uses `CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`, upserts and `ADD COLUMN IF NOT EXISTS` where schema additions are required.

Do **not** run `database.sql` over an existing production database.

---

## Database files

- `database.sql` — complete schema and initial ES MULTISERVICIOS data for a new installation.
- `database-update.sql` — cumulative update for an existing compatible installation.

The update includes the Video module, flexible artwork support, permissions and current branding values.

---

## Important folders

```text
/admin/                  CMS administration
/assets/                 Public styles, scripts, brand assets and product screenshots
/assets/brand/           ES MULTISERVICIOS, IZZY and CAMI branding
/assets/products/        Real product screenshots used by the landing page
/assets/projects/        Project / case-study brand assets
/config/                 Application configuration
/core/                   Shared services and email logic
/install/                Installation wizard
/uploads/                Administrator uploads
```

---

## Security notes

- Keep `config/database.php` private.
- Keep `config/app.key` private.
- Use HTTPS in production.
- Use unique administrator passwords.
- Enable two-factor authentication for Owner / Administrator accounts when practical.
- Review active sessions in Security Center.
- Create a backup before major production changes.

---

## Development principles

This package intentionally keeps PHP, HTML, CSS, JavaScript and SQL readable and maintainable. Source files should not be minified as part of normal project delivery.

The website remains a dedicated ES MULTISERVICIOS implementation built on the reusable CMS architecture. Client-specific material should appear only where it is intentionally presented as a project or case study.
