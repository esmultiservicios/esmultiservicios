-- ES CMS CORE - PRODUCTIVITY SUITE UPDATE
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS content_drafts (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key), KEY idx_content_drafts_user (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS content_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  snapshot_json LONGTEXT NOT NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_content_versions_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_sections (
  section_key VARCHAR(60) NOT NULL,
  label VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (section_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS media_library (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_media_path (file_path), KEY idx_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  action_type VARCHAR(80) NOT NULL,
  description VARCHAR(500) NOT NULL,
  metadata_json TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_activity_created (created_at), KEY idx_activity_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS admin_notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_type VARCHAR(40) NOT NULL DEFAULT 'info',
  title VARCHAR(180) NOT NULL,
  message VARCHAR(500) NOT NULL,
  action_url VARCHAR(500) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_notifications_read_created (is_read,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS site_backups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  backup_name VARCHAR(180) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_backups_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO site_sections(section_key,label,sort_order,active) VALUES
('home','Home / Hero',10,1),('intro','What We Do',20,1),('about','About Us',30,1),('services','Services',40,1),('gallery','Gallery',50,1),('areas','Service Areas',60,1),('tips','Home Tips',70,1),('estimate','Free Estimate',80,1),('contact','Contact',90,1)
ON DUPLICATE KEY UPDATE label=VALUES(label);
INSERT INTO settings(setting_key,setting_value) VALUES
('seo_title','Your Company | Professional Services'),
('seo_description','Describe your company, services and value proposition here.'),
('seo_social_image',''),('seo_robots','index,follow'),
('developer_credit_enabled','0'),('developer_credit_text','Website by ES MULTISERVICIOS')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- USERS, ROLES, APPROVALS, SALES TRACKING & SECURITY CENTER

CREATE TABLE IF NOT EXISTS admin_roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_key VARCHAR(60) NOT NULL,
  role_name VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_role_key(role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_permissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  permission_key VARCHAR(100) NOT NULL,
  permission_name VARCHAR(160) NOT NULL,
  permission_group VARCHAR(80) NOT NULL DEFAULT 'General',
  PRIMARY KEY(id), UNIQUE KEY uq_admin_permission_key(permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY(role_id,permission_id), KEY idx_role_permission_permission(permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_approvals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submitted_by INT UNSIGNED NOT NULL,
  status ENUM('pending','approved','changes_requested','cancelled') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  reviewer_note TEXT NULL,
  reviewed_by INT UNSIGNED NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY(id), KEY idx_content_approval_status(status,submitted_at), KEY idx_content_approval_submitter(submitted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_estimate_notes_request(estimate_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  last_seen_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  PRIMARY KEY(id), UNIQUE KEY uq_admin_session_hash(session_hash), KEY idx_admin_sessions_user(admin_id,last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_login_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NULL,
  username_attempt VARCHAR(80) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id), KEY idx_login_events_user(admin_id,created_at), KEY idx_login_events_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_notification_reads (
  notification_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NOT NULL,
  read_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(notification_id,admin_id), KEY idx_notification_reads_admin(admin_id,read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_roles(role_key,role_name,description,is_system,active) VALUES
('owner','Owner','Full protected ownership of the website.',1,1),
('administrator','Administrator','Full day-to-day administration except protected owner controls.',1,1),
('editor','Editor','Creates and edits website content; publishing requires approval.',1,1),
('sales','Sales','Works with estimate requests and customer follow-ups.',1,1),
('viewer','Viewer','Read-only operational access.',1,1)
ON DUPLICATE KEY UPDATE role_name=VALUES(role_name),description=VALUES(description),active=1;

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('dashboard.view','View dashboard','General'),
('content.view','View page content','Content'),
('content.edit','Edit drafts','Content'),
('content.publish','Publish content','Content'),
('content.approve','Approve submitted content','Content'),
('sections.manage','Manage landing sections','Content'),
('media.manage','Manage Media Library','Content'),
('services.manage','Manage services','Content'),
('gallery.manage','Manage gallery','Content'),
('areas.manage','Manage service areas','Content'),
('tips.manage','Manage home tips','Content'),
('estimates.view','View estimate requests','Business'),
('estimates.manage_assigned','Manage assigned estimates','Business'),
('estimates.manage_all','Manage all estimates and assignments','Business'),
('email.manage','Manage email configuration','System'),
('integrations.manage','Manage integrations & APIs','System'),
('seo.manage','Manage SEO','Content'),
('health.view','View Website Health','General'),
('notifications.view','View notifications','General'),
('activity.view','View activity log','General'),
('backups.manage','Manage backups','System'),
('settings.manage','Manage site settings','System'),
('users.manage','Manage administrator users','Administration'),
('roles.manage','Manage roles & permissions','Administration'),
('security.manage','Manage active sessions and security','Administration')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.publish' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.approve' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_all' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='email.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='integrations.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='activity.view' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='backups.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='settings.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='users.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='security.manage' WHERE r.role_key='administrator';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.edit' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='sections.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='media.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='services.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='gallery.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='areas.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='tips.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='seo.manage' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='editor';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.manage_assigned' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='sales';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='dashboard.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='content.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='estimates.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='health.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='notifications.view' WHERE r.role_key='viewer';
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p WHERE r.role_key='owner';

ALTER TABLE admin_users
  ADD COLUMN role_id INT UNSIGNED NULL AFTER password_hash,
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role_id,
  ADD COLUMN created_by INT UNSIGNED NULL AFTER active,
  ADD COLUMN last_login_at DATETIME NULL AFTER created_by,
  ADD COLUMN last_login_ip VARCHAR(64) NULL AFTER last_login_at,
  ADD COLUMN last_user_agent VARCHAR(500) NULL AFTER last_login_ip,
  ADD COLUMN two_factor_secret_enc TEXT NULL AFTER last_user_agent,
  ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER two_factor_secret_enc,
  ADD KEY idx_admin_role_active(role_id,active);

ALTER TABLE estimate_requests
  MODIFY COLUMN status ENUM('new','contacted','in_progress','won','lost','closed') NOT NULL DEFAULT 'new',
  ADD COLUMN assigned_to INT UNSIGNED NULL AFTER status,
  ADD COLUMN priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal' AFTER assigned_to,
  ADD COLUMN follow_up_date DATE NULL AFTER priority,
  ADD COLUMN internal_notes TEXT NULL AFTER follow_up_date,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD KEY idx_estimate_assigned(assigned_to,status,follow_up_date);

UPDATE admin_users
SET role_id=(SELECT id FROM admin_roles WHERE role_key='owner' LIMIT 1)
WHERE role_id IS NULL;


-- =========================================================
-- ES MULTISERVICIOS MARKETING WEBSITE
-- Bilingual, product/plan/project/affiliate management
-- =========================================================
CREATE TABLE IF NOT EXISTS landing_content (
  content_key VARCHAR(100) NOT NULL,
  lang ENUM('es','en') NOT NULL DEFAULT 'es',
  content_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key,lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_key VARCHAR(60) NOT NULL,
  name VARCHAR(100) NOT NULL,
  logo_path VARCHAR(500) NULL,
  accent_color VARCHAR(20) NOT NULL DEFAULT '#0A9ED0',
  description_es TEXT NULL,
  description_en TEXT NULL,
  features_es TEXT NULL,
  features_en TEXT NULL,
  cta_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_marketing_product_key (product_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_plans (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_key VARCHAR(60) NOT NULL DEFAULT 'izzy',
  name_es VARCHAR(150) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  description_es TEXT NULL,
  description_en TEXT NULL,
  price_label_es VARCHAR(100) NULL,
  price_label_en VARCHAR(100) NULL,
  features_es TEXT NULL,
  features_en TEXT NULL,
  badge_es VARCHAR(80) NULL,
  badge_en VARCHAR(80) NULL,
  cta_url VARCHAR(500) NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_marketing_plan_product (product_key,active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketing_projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  category_es VARCHAR(150) NULL,
  category_en VARCHAR(150) NULL,
  description_es TEXT NULL,
  description_en TEXT NULL,
  image_path VARCHAR(500) NULL,
  project_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO landing_content(content_key,lang,content_value) VALUES
('hero_kicker','es','TECNOLOGÍA QUE SIMPLIFICA Y HACE CRECER TU NEGOCIO'),
('hero_kicker','en','TECHNOLOGY THAT SIMPLIFIES AND GROWS YOUR BUSINESS'),
('hero_title','es','Más que servicio, construimos soluciones.'),
('hero_title','en','More than service, we build solutions.'),
('hero_text','es','Creamos sistemas, sitios web y soluciones digitales que se adaptan a la forma en que trabaja tu empresa.'),
('hero_text','en','We build systems, websites and digital solutions that adapt to the way your business works.'),
('hero_primary','es','Solicitar demo'),('hero_primary','en','Request a demo'),
('hero_secondary','es','Hablar por WhatsApp'),('hero_secondary','en','Chat on WhatsApp'),
('solutions_title','es','Soluciones propias para negocios que quieren avanzar'),
('solutions_title','en','Our own solutions for businesses ready to move forward'),
('solutions_text','es','Productos desarrollados por ES MULTISERVICIOS para simplificar operaciones reales.'),
('solutions_text','en','Products built by ES MULTISERVICIOS to simplify real operations.'),
('izzy_title','es','IZZY se adapta a tu forma de trabajar'),
('izzy_title','en','IZZY adapts to the way you work'),
('izzy_text','es','Una sola plataforma web con distintas experiencias de facturación y operación según tu negocio y el plan contratado.'),
('izzy_text','en','One web platform with different billing and operating experiences depending on your business and selected plan.'),
('cami_title','es','CAMI simplifica la gestión clínica'),
('cami_title','en','CAMI simplifies clinical management'),
('cami_text','es','Una solución web para organizar pacientes, atenciones, expedientes, seguimiento y procesos de clínicas y consultorios.'),
('cami_text','en','A web solution to organize patients, visits, records, follow-up and workflows for clinics and medical practices.'),
('services_title','es','Tecnología que se adapta a tu empresa'),
('services_title','en','Technology that adapts to your business'),
('services_text','es','No solo ofrecemos productos propios. También diseñamos y desarrollamos soluciones a la medida.'),
('services_text','en','We do more than offer our own products. We also design and build custom solutions.'),
('affiliate_title','es','Crece con nosotros'),('affiliate_title','en','Grow with us'),
('affiliate_text','es','Nuestro programa de afiliados permite a personas y empresas recomendar IZZY y CAMI y generar ingresos según las condiciones del programa.'),
('affiliate_text','en','Our affiliate program lets individuals and companies recommend IZZY and CAMI and earn according to the program terms.'),
('affiliate_cta','es','Quiero ser afiliado'),('affiliate_cta','en','Become an affiliate'),
('projects_title','es','Soluciones que ya hemos construido'),('projects_title','en','Solutions we have already built'),
('why_title','es','¿Por qué ES MULTISERVICIOS?'),('why_title','en','Why ES MULTISERVICIOS?'),
('contact_title','es','Hablemos de lo que tu negocio necesita'),('contact_title','en','Let’s talk about what your business needs'),
('contact_text','es','Cuéntanos qué quieres resolver. Podemos orientarte sobre IZZY, CAMI, desarrollo web o un sistema a la medida.'),
('contact_text','en','Tell us what you need to solve. We can guide you on IZZY, CAMI, web development or custom software.'),
('support_cta','es','Necesito soporte'),('support_cta','en','I need support')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO marketing_products(product_key,name,logo_path,accent_color,description_es,description_en,features_es,features_en,cta_url,sort_order,active) VALUES
('izzy','IZZY','assets/brand/izzy.png','#0A9ED0',
'Sistema web de facturación y gestión para empresas y comercios.','Web-based billing and management system for companies and businesses.',
'Facturación y ventas\nInventario, compras y reportes\nFacturación clásica\nExperiencia visual configurable\nModo restaurante según plan\nAcceso responsive desde navegador',
'Billing and sales\nInventory, purchases and reports\nClassic billing\nConfigurable visual experience\nRestaurant mode depending on plan\nResponsive browser access',
'#izzy',10,1),
('cami','CAMI','assets/brand/cami.png','#16CDB7',
'Sistema web para clínicas y consultorios que centraliza la información y el seguimiento del paciente.','Web system for clinics and practices that centralizes patient information and follow-up.',
'Pacientes\nAtenciones\nExpedientes e historial\nSeguimiento clínico\nDocumentos y reportes\nGestión administrativa',
'Patients\nVisits\nRecords and history\nClinical follow-up\nDocuments and reports\nAdministrative management',
'#cami',20,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),logo_path=VALUES(logo_path),accent_color=VALUES(accent_color),description_es=VALUES(description_es),description_en=VALUES(description_en),features_es=VALUES(features_es),features_en=VALUES(features_en),cta_url=VALUES(cta_url),sort_order=VALUES(sort_order),active=VALUES(active);

INSERT INTO marketing_projects(title,category_es,category_en,description_es,description_en,project_url,sort_order,active)
SELECT 'Castro''s Ready','Sitio corporativo + CMS personalizado','Corporate website + custom CMS',
'Sitio corporativo responsive con administrador propio para contenido, servicios, multimedia, SEO, usuarios, seguridad, respaldos y configuración visual.',
'Responsive corporate website with a custom administrator for content, services, media, SEO, users, security, backups and visual configuration.',
'https://castroready.esmultiservicios.com/',10,1
WHERE NOT EXISTS (SELECT 1 FROM marketing_projects WHERE title='Castro''s Ready');

INSERT INTO settings(setting_key,setting_value) VALUES
('company_name','ES MULTISERVICIOS'),
('admin_brand_name','ES MULTISERVICIOS Admin'),
('admin_logo_path','assets/brand/es-multiservicios.png'),
('phone','+504 8913-6844'),
('phone_digits','50489136844'),
('website','esmultiservicios.com'),
('whatsapp_enabled','1'),
('whatsapp_message','Hola, quiero información sobre las soluciones de ES MULTISERVICIOS.'),
('whatsapp_position','right'),
('seo_title','ES MULTISERVICIOS | IZZY, CAMI y Soluciones Digitales'),
('seo_description','Sistemas web, facturación, soluciones para clínicas, sitios web y desarrollo a la medida. Conoce IZZY, CAMI y los servicios de ES MULTISERVICIOS.'),
('brand_primary','#0A2A4A'),
('brand_secondary','#0A9ED0'),
('brand_accent','#F28C28'),
('brand_surface','#F7F9FC')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('marketing.manage','Manage ES MULTISERVICIOS marketing website','Content')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p
WHERE r.role_key IN ('owner','administrator','editor') AND p.permission_key='marketing.manage';
