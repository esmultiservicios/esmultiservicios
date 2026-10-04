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
('seo_social_image',''),('seo_robots','index,follow'),('seo_google_verification',''),
('developer_credit_enabled','0'),('developer_credit_text','')
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

-- The ES MULTISERVICIOS base database already contains the current
-- admin_users and estimate_requests columns. No ALTER TABLE is required here.
-- This intentionally avoids ADD COLUMN IF NOT EXISTS, INFORMATION_SCHEMA,
-- and CREATE ROUTINE dependencies for maximum shared-host compatibility.

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
('contact_text','es','Cuéntanos qué quieres resolver. Podemos orientarte sobre IZZY, CAMI, ZYNKO, desarrollo web o una solución a la medida.'),
('contact_text','en','Tell us what you need to solve. We can guide you on IZZY, CAMI, ZYNKO, web development or a custom solution.'),
('support_cta','es','Necesito soporte'),('support_cta','en','I need support')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO marketing_products(product_key,name,logo_path,accent_color,description_es,description_en,features_es,features_en,cta_url,sort_order,active) VALUES
('izzy','IZZY','assets/brand/izzy-solution.png','#0A9ED0',
'Sistema web de facturación y gestión para empresas y comercios.','Web-based billing and management system for companies and businesses.',
'Facturación y ventas\nInventario, compras y reportes\nFacturación clásica\nExperiencia visual configurable\nModo restaurante según plan\nAcceso responsive desde navegador',
'Billing and sales\nInventory, purchases and reports\nClassic billing\nConfigurable visual experience\nRestaurant mode depending on plan\nResponsive browser access',
'#izzy',10,1),
('cami','CAMI','assets/brand/cami-solution.png','#16CDB7',
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
('admin_logo_path','assets/brand/es-mark.png'),
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

-- ============================================================
-- ES MULTISERVICIOS PREMIUM LANDING + MEDIA UPDATE
-- Safe to run more than once on compatible MySQL/MariaDB hosts.
-- ============================================================
CREATE TABLE IF NOT EXISTS videos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  video_type ENUM('youtube','vimeo','upload') NOT NULL DEFAULT 'youtube',
  video_url VARCHAR(700) NULL,
  file_path VARCHAR(500) NULL,
  poster_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_videos_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS about_artworks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(180) NULL,
  image_path VARCHAR(500) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_about_artworks_active_order (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO site_sections(section_key,label,sort_order,active)
VALUES ('videos','Videos',50,1)
ON DUPLICATE KEY UPDATE label=VALUES(label);

UPDATE site_sections SET sort_order=60 WHERE section_key='gallery' AND sort_order=50;
UPDATE site_sections SET sort_order=70 WHERE section_key='areas' AND sort_order=60;
UPDATE site_sections SET sort_order=80 WHERE section_key='tips' AND sort_order=70;
UPDATE site_sections SET sort_order=90 WHERE section_key='estimate' AND sort_order=80;
UPDATE site_sections SET sort_order=100 WHERE section_key='contact' AND sort_order=90;

INSERT INTO admin_permissions(permission_key,permission_name,permission_group)
VALUES ('videos.manage','Manage website videos','Content')
ON DUPLICATE KEY UPDATE
  permission_name=VALUES(permission_name),
  permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id)
SELECT r.id,p.id
FROM admin_roles r
JOIN admin_permissions p ON p.permission_key='videos.manage'
WHERE r.role_key IN ('administrator','editor');

UPDATE marketing_projects
SET image_path='assets/projects/castros-ready-logo.jpg'
WHERE title='Castro''s Ready' AND (image_path IS NULL OR image_path='');

INSERT INTO settings(setting_key,setting_value) VALUES
('brand_primary','#0B2E59'),
('brand_secondary','#0A9ED0'),
('brand_accent','#F28C28'),
('brand_surface','#F4F7FB')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);



-- ============================================================
-- FINAL ES MULTISERVICIOS BRAND / FAVICON / PROJECT MEDIA SYNC
-- Safe after a partial previous migration.
-- ============================================================
UPDATE marketing_products
SET logo_path='assets/brand/cami-solution.png'
WHERE product_key='cami';

UPDATE marketing_projects
SET image_path='assets/projects/castros-ready-logo.jpg'
WHERE title='Castro''s Ready'
  AND (image_path IS NULL OR TRIM(image_path)='');

INSERT INTO settings(setting_key,setting_value)
VALUES ('admin_brand_name','ES MULTISERVICIOS Admin')
ON DUPLICATE KEY UPDATE
setting_value=IF(setting_value IS NULL OR TRIM(setting_value)='' OR setting_value='ES CMS Core Admin',VALUES(setting_value),setting_value);

INSERT INTO settings(setting_key,setting_value)
VALUES ('admin_logo_path','assets/brand/es-mark.png')
ON DUPLICATE KEY UPDATE
setting_value=IF(setting_value IS NULL OR TRIM(setting_value)='',VALUES(setting_value),setting_value);

INSERT INTO settings(setting_key,setting_value)
VALUES ('favicon_path','assets/brand/favicon.png')
ON DUPLICATE KEY UPDATE
setting_value=IF(setting_value IS NULL OR TRIM(setting_value)='',VALUES(setting_value),setting_value);

-- =========================================================
-- ES MULTISERVICIOS PREMIUM CONTACT / PRESENTATION V3
-- Safe cumulative settings: no schema ALTER required.
-- =========================================================
INSERT INTO settings(setting_key,setting_value) VALUES
('contact_map_query','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- ============================================================
-- ES MULTISERVICIOS V4 - IZZY PLANS + AFFILIATE PROGRAM
-- Idempotent content update. No schema changes are required.
-- ============================================================

INSERT INTO landing_content(content_key,lang,content_value) VALUES
('affiliate_title','es','Convierte tus contactos en una oportunidad de ingresos'),
('affiliate_title','en','Turn your contacts into an income opportunity'),
('affiliate_text','es','Personas y empresas pueden recomendar IZZY y CAMI. Cuando el cliente se incorpora, ES MULTISERVICIOS se encarga de la implementación, soporte y capacitación según el servicio contratado.'),
('affiliate_text','en','Individuals and companies can recommend IZZY and CAMI. When the customer joins, ES MULTISERVICIOS handles implementation, support and training according to the contracted service.'),
('affiliate_cta','es','Quiero ser afiliado'),
('affiliate_cta','en','I want to become an affiliate'),
('affiliate_single_title','es','1 cliente en el mes'),
('affiliate_single_title','en','1 client in the month'),
('affiliate_single_text','es','Ganas el 100% del valor del primer mes de ese cliente.'),
('affiliate_single_text','en','Earn 100% of that client''s first-month value.'),
('affiliate_team_title','es','3 clientes o más en el mismo mes'),
('affiliate_team_title','en','3 or more clients in the same month'),
('affiliate_team_text','es','Ganas el 200% del valor del primer mes, conforme a las condiciones vigentes del programa.'),
('affiliate_team_text','en','Earn 200% of the first-month value, subject to the current program terms.'),
('affiliate_support_text','es','Tú te enfocas en recomendar y conectar. Nuestro equipo se encarga de la instalación, acompañamiento, soporte y capacitación del cliente.'),
('affiliate_support_text','en','You focus on recommending and connecting. Our team handles installation, onboarding, support and customer training.'),
('affiliate_disclaimer','es','El beneficio aplica únicamente al primer mes de cada cliente y está sujeto a validación y a las condiciones vigentes del programa de afiliados.'),
('affiliate_disclaimer','en','The benefit applies only to each client''s first month and is subject to validation and the current affiliate program terms.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

-- Keep the official IZZY plan catalog deterministic when this update is run again.
DELETE FROM marketing_plans
WHERE product_key='izzy'
  AND name_es IN ('Emprendedor','Básico','Regular','Estándar','Premium','Restaurantes');

UPDATE marketing_plans SET featured=0 WHERE product_key='izzy';

INSERT INTO marketing_plans(
    product_key,name_es,name_en,description_es,description_en,
    price_label_es,price_label_en,features_es,features_en,
    badge_es,badge_en,cta_url,featured,sort_order,active
) VALUES
(
    'izzy','Emprendedor','Entrepreneur',
    'Todo lo esencial para comenzar a facturar y controlar tu operación desde la web.',
    'Everything you need to start billing and controlling your operation from the web.',
    'L. 599 / mes','L. 599 / month',
    'Facturación electrónica con el SAR\nControl de caja\nFormato de factura ticket y carta\nReporte de ventas\nRegistro de productos\n1 punto de venta\n1 usuario administrador\n2 usuarios adicionales\nSoporte técnico',
    'Electronic invoicing with SAR\nCash control\nTicket and letter invoice formats\nSales report\nProduct registry\n1 point of sale\n1 administrator user\n2 additional users\nTechnical support',
    'Ideal para comenzar','Ideal to start','',0,10,1
),
(
    'izzy','Básico','Basic',
    'Más control para inventario, clientes y facturación en una operación que comienza a crecer.',
    'More control for inventory, customers and billing as your operation starts to grow.',
    'L. 1,099 / mes','L. 1,099 / month',
    'Facturación electrónica con el SAR\nControl de caja\nFormato de factura ticket y carta\nReportes de ventas\nRegistro de productos e inventario\nCuentas por cobrar a clientes\n1 punto de venta\n1 usuario administrador\n2 usuarios adicionales\nSoporte técnico',
    'Electronic invoicing with SAR\nCash control\nTicket and letter invoice formats\nSales reports\nProducts and inventory\nAccounts receivable\n1 point of sale\n1 administrator user\n2 additional users\nTechnical support',
    'Más control','More control','',0,20,1
),
(
    'izzy','Regular','Regular',
    'Más capacidad para crecer, con compras, control financiero y más usuarios.',
    'More capacity to grow with purchases, financial control and additional users.',
    'L. 1,610 / mes','L. 1,610 / month',
    'Facturación electrónica con el SAR\nControl de caja\nFormato de factura ticket y carta\nReportes de ventas\nProductos, inventario y compras\nCuentas por cobrar a clientes\nCuentas por pagar a proveedores\n2 puntos de venta\n1 usuario administrador\n3 usuarios adicionales\nFacturas recurrentes automáticas\nSoporte técnico',
    'Electronic invoicing with SAR\nCash control\nTicket and letter invoice formats\nSales reports\nProducts, inventory and purchases\nAccounts receivable\nAccounts payable\n2 points of sale\n1 administrator user\n3 additional users\nAutomatic recurring invoices\nTechnical support',
    'Más capacidad para crecer','More room to grow','',0,30,1
),
(
    'izzy','Estándar','Standard',
    'Operación integral con más puntos de venta, compras, cotizaciones y control financiero.',
    'An integrated operation with more points of sale, purchases, quotations and financial control.',
    'L. 2,499 / mes','L. 2,499 / month',
    'Facturación electrónica con el SAR\nControl de caja\nFormato de factura ticket y carta\nReportes de ventas\nRegistro de productos e inventario\nRegistro de compras y cotizaciones\nCuentas por cobrar a clientes\nCuentas por pagar a proveedores\n3 puntos de venta\n1 usuario administrador\n4 usuarios adicionales\nFacturas recurrentes automáticas\nSoporte técnico',
    'Electronic invoicing with SAR\nCash control\nTicket and letter invoice formats\nSales reports\nProducts and inventory\nPurchases and quotations\nAccounts receivable\nAccounts payable\n3 points of sale\n1 administrator user\n4 additional users\nAutomatic recurring invoices\nTechnical support',
    'Operación integral','Integrated operation','',0,40,1
),
(
    'izzy','Premium','Premium',
    'Gestión avanzada para operaciones en crecimiento que necesitan mayor capacidad, control y herramientas administrativas.',
    'Advanced management for growing operations that need more capacity, control and administrative tools.',
    'L. 3,499 / mes','L. 3,499 / month',
    'Facturación electrónica con el SAR\nControl de caja\nFormato de factura ticket y carta\nProductos e inventario en múltiples bodegas\nTransferencias entre bodegas\nRegistro de compras\nReportes de ventas, compras y cotizaciones\nNómina y contratos de empleados\nCuentas por cobrar a clientes\nCuentas por pagar a proveedores\n4 puntos de venta\n1 usuario administrador\n10 usuarios adicionales\nControl de asistencia de empleados\nFacturas recurrentes automáticas\nSoporte técnico',
    'Electronic invoicing with SAR\nCash control\nTicket and letter invoice formats\nProducts and inventory across multiple warehouses\nWarehouse transfers\nPurchase registry\nSales, purchases and quotation reports\nPayroll and employee contracts\nAccounts receivable\nAccounts payable\n4 points of sale\n1 administrator user\n10 additional users\nEmployee attendance control\nAutomatic recurring invoices\nTechnical support',
    'Recomendado para mayor control','Recommended for maximum control','',1,50,1
),
(
    'izzy','Restaurantes','Restaurants & Visual Sales',
    'Una experiencia visual ágil para restaurantes y también para otros negocios que prefieren vender mediante productos con imágenes. Puede configurarse con mesas o sin mesas.',
    'A fast visual experience for restaurants and other businesses that prefer selling through image-based products. It can be configured with or without tables.',
    'L. 2,499 / mes','L. 2,499 / month',
    'Venta visual por productos con imágenes\nConfigurable con mesas o sin mesas\nMesas, reservas y pedidos para llevar\nComandas y pantalla de cocina\nCobro y facturación electrónica con el SAR\nControl de caja\nFormato de factura ticket y carta\nCuentas abiertas\nProductos e inventario\nCreación y gestión de promociones y combos\n1 punto de venta\n1 usuario administrador\n4 usuarios adicionales\nFacturas recurrentes automáticas\nSoporte técnico',
    'Visual sales with image-based products\nConfigurable with or without tables\nTables, reservations and takeout orders\nKitchen tickets and kitchen screen\nPayment and electronic invoicing with SAR\nCash control\nTicket and letter invoice formats\nOpen accounts\nProducts and inventory\nPromotion and combo management\n1 point of sale\n1 administrator user\n4 additional users\nAutomatic recurring invoices\nTechnical support',
    'Experiencia visual configurable','Configurable visual experience','',0,60,1
);


-- =========================================================
-- ESTIMATE REQUESTS — PREMIUM ACTION CENTER / COMMUNICATION
-- V5: spam/archive metadata + outbound reply history
-- Idempotent and shared-host friendly: no ALTER TABLE required.
-- =========================================================

CREATE TABLE IF NOT EXISTS estimate_request_flags (
  estimate_id BIGINT UNSIGNED NOT NULL,
  is_spam TINYINT(1) NOT NULL DEFAULT 0,
  archived_at DATETIME NULL,
  updated_by INT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (estimate_id),
  KEY idx_estimate_request_flags_bucket (is_spam, archived_at),
  KEY idx_estimate_request_flags_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_replies (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  admin_id INT UNSIGNED NULL,
  recipient_email VARCHAR(380) NOT NULL,
  subject VARCHAR(240) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_replies_request (estimate_id, created_at),
  KEY idx_estimate_replies_admin (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- ESTIMATE REPLIES — OUTBOUND ATTACHMENTS
-- Stores files attached by administrators to sent responses.
-- =========================================================

CREATE TABLE IF NOT EXISTS estimate_reply_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reply_id BIGINT UNSIGNED NOT NULL,
  estimate_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_reply_files_reply (reply_id, created_at),
  KEY idx_estimate_reply_files_estimate (estimate_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Administrable social networks (no schema change; values live in generic settings).
INSERT INTO settings(setting_key,setting_value) VALUES
('social_networks_json','[{"enabled":false,"platform":"instagram","url":"","sort_order":1},{"enabled":true,"platform":"facebook","url":"https://web.facebook.com/esmultiserv","sort_order":2},{"enabled":false,"platform":"tiktok","url":"","sort_order":3},{"enabled":false,"platform":"youtube","url":"","sort_order":4},{"enabled":false,"platform":"linkedin","url":"","sort_order":5}]'),
('social_size','medium'),
('social_style','icon'),
('social_location','footer_floating_right'),
('social_show_desktop','1'),
('social_show_mobile','1')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- ES MULTISERVICIOS Facebook page.
-- Upgrade only an untouched/empty social configuration so existing custom networks are preserved.
UPDATE settings
SET setting_value='[{"enabled":false,"platform":"instagram","url":"","sort_order":1},{"enabled":true,"platform":"facebook","url":"https://web.facebook.com/esmultiserv","sort_order":2},{"enabled":false,"platform":"tiktok","url":"","sort_order":3},{"enabled":false,"platform":"youtube","url":"","sort_order":4},{"enabled":false,"platform":"linkedin","url":"","sort_order":5}]'
WHERE setting_key='social_networks_json'
  AND (setting_value IS NULL OR TRIM(setting_value)='' OR setting_value='[{"enabled":false,"platform":"instagram","url":"","sort_order":1},{"enabled":false,"platform":"facebook","url":"","sort_order":2},{"enabled":false,"platform":"tiktok","url":"","sort_order":3},{"enabled":false,"platform":"youtube","url":"","sort_order":4},{"enabled":false,"platform":"linkedin","url":"","sort_order":5}]');

-- =========================================================
-- PRIVACY-FRIENDLY PUBLIC ANALYTICS
-- =========================================================
CREATE TABLE IF NOT EXISTS site_visits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_key CHAR(64) NOT NULL,
  path VARCHAR(500) NOT NULL DEFAULT '/',
  visited_at DATETIME NOT NULL,
  visit_date DATE NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_visits_date (visit_date, id),
  KEY idx_site_visits_visitor (visitor_key, visit_date),
  KEY idx_site_visits_visited_at (visited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Sabrosísimo Mix portfolio entry. Image can be uploaded later from Marketing site ES/EN.
INSERT INTO marketing_projects(title,category_es,category_en,description_es,description_en,image_path,project_url,sort_order,active)
SELECT 'Sabrosísimo Mix','Sitio comercial + CMS administrable','Business website + custom CMS',
'Sitio web responsive para servicios gastronómicos y eventos, con contenido administrable, servicios, galería, cotizaciones, redes sociales y contacto directo por WhatsApp.',
'Responsive website for food services and events, with manageable content, services, gallery, quote requests, social networks and direct WhatsApp contact.',
'assets/projects/sabrosisimo-mix-logo.jpg','https://sabrosisimomix.esmultiservicios.com/',20,1
WHERE NOT EXISTS (SELECT 1 FROM marketing_projects WHERE title='Sabrosísimo Mix');

-- SABROSISIMO_DEFAULTS_SAFE_20260930
UPDATE marketing_projects
SET category_es=CASE WHEN TRIM(COALESCE(category_es,''))='' THEN 'Sitio comercial + CMS administrable' ELSE category_es END,
    category_en=CASE WHEN TRIM(COALESCE(category_en,''))='' THEN 'Business website + custom CMS' ELSE category_en END,
    description_es=CASE WHEN TRIM(COALESCE(description_es,''))='' THEN 'Sitio web responsive para servicios gastronómicos y eventos, con contenido administrable, servicios, galería, cotizaciones, redes sociales y contacto directo por WhatsApp.' ELSE description_es END,
    description_en=CASE WHEN TRIM(COALESCE(description_en,''))='' THEN 'Responsive website for food services and events, with manageable content, services, gallery, quote requests, social networks and direct WhatsApp contact.' ELSE description_en END,
    image_path=CASE WHEN TRIM(COALESCE(image_path,''))='' THEN 'assets/projects/sabrosisimo-mix-logo.jpg' ELSE image_path END,
    project_url=CASE WHEN TRIM(COALESCE(project_url,''))='' THEN 'https://sabrosisimomix.esmultiservicios.com/' ELSE project_url END
WHERE title='Sabrosísimo Mix';


-- Floating widget settings
INSERT INTO settings(setting_key,setting_value) VALUES
('site_timezone','America/Tegucigalpa'),
('floating_external_enabled','0'),
('floating_external_name','External widget'),
('floating_external_snippet',''),
('floating_external_position','right'),
('floating_external_order','20'),
('whatsapp_order','10'),
('whatsapp_show_desktop','1'),
('whatsapp_show_mobile','1'),
('floating_external_show_desktop','1'),
('floating_external_show_mobile','1'),
('floating_widget_gap','12')
ON DUPLICATE KEY UPDATE setting_value=setting_value;


-- Permissions for analytics, social networks and floating widgets
INSERT INTO admin_permissions(permission_key,permission_name,permission_group) VALUES
('analytics.view','View website analytics','Analytics'),
('social.manage','Manage social networks','Content'),
('widgets.manage','Manage floating widgets','Settings')
ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name),permission_group=VALUES(permission_group);

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p
WHERE r.role_key IN ('owner','administrator') AND p.permission_key IN ('analytics.view','widgets.manage');

INSERT IGNORE INTO admin_role_permissions(role_id,permission_id)
SELECT r.id,p.id FROM admin_roles r CROSS JOIN admin_permissions p
WHERE r.role_key IN ('owner','administrator','editor') AND p.permission_key='social.manage';


-- ES MULTISERVICIOS social placement UX update
UPDATE settings
SET setting_value='footer_floating_right'
WHERE setting_key='social_location'
  AND setting_value IN ('footer','floating_right');


-- =========================================================
-- ES MULTISERVICIOS CORPORATE SOLUTIONS DIRECTORY
-- IZZY + CAMI + ZYNKO, no pricing cards / no product screenshots
-- =========================================================
INSERT INTO marketing_products(product_key,name,logo_path,accent_color,description_es,description_en,features_es,features_en,cta_url,sort_order,active)
SELECT 'zynko','ZYNKO','assets/brand/zynko-solution.png', '#16B89A',
       'Plataforma omnicanal multiempresa para centralizar conversaciones, clientes, equipos y canales en una sola operación. Integra NIVO Web Chat y NIVO IA para automatizar atención, trabajar con conocimiento aprobado y transferir conversaciones a personas cuando sea necesario.',
       'Multi-company omnichannel platform that centralizes conversations, customers, teams and channels in one operation. It includes NIVO Web Chat and NIVO AI for assisted service, approved knowledge and human handoff when needed.',
       'Conversaciones centralizadas\nNIVO Web Chat\nNIVO IA y automatización\nUsuarios y permisos\nAPI y webhooks\nNotificaciones\nGestión multiempresa',
       'Centralized conversations\nNIVO Web Chat\nNIVO AI and automation\nUsers and permissions\nAPI and webhooks\nNotifications\nMulti-company management',
       '',30,1
WHERE NOT EXISTS (SELECT 1 FROM marketing_products WHERE product_key='zynko');

UPDATE marketing_products SET
  description_es='Sistema web de facturación y gestión para empresas y comercios, diseñado para centralizar operaciones y adaptarse a distintos modelos de negocio.',
  description_en='Web-based billing and management solution for companies and shops, designed to centralize operations and adapt to different business models.',
  features_es='Facturación\nInventario\nGestión empresarial\nExperiencias configurables',
  features_en='Billing\nInventory\nBusiness management\nConfigurable experiences',
  sort_order=10,active=1
WHERE product_key='izzy';

UPDATE marketing_products SET
  description_es='Sistema web para clínicas y consultorios que organiza pacientes, expedientes, atenciones, seguimiento y procesos clínicos en una sola plataforma.',
  description_en='Web solution for clinics and medical practices that organizes patients, records, visits, follow-up and clinical workflows in one platform.',
  features_es='Pacientes\nExpedientes\nAtenciones\nSeguimiento clínico',
  features_en='Patients\nRecords\nVisits\nClinical follow-up',
  sort_order=20,active=1
WHERE product_key='cami';

UPDATE marketing_products SET cta_url='' WHERE product_key IN ('izzy','cami') AND cta_url IN ('#izzy','#cami');
DELETE FROM marketing_plans;

INSERT INTO landing_content(content_key,lang,content_value) VALUES
('solutions_title','es','Soluciones digitales creadas para resolver necesidades reales'),
('solutions_title','en','Digital solutions built to solve real business needs'),
('solutions_text','es','IZZY, CAMI y ZYNKO son soluciones desarrolladas por ES MULTISERVICIOS. Conoce qué hace cada una y visita su sitio dedicado para obtener más información.'),
('solutions_text','en','IZZY, CAMI and ZYNKO are solutions developed by ES MULTISERVICIOS. Discover what each one does and visit its dedicated website for more information.'),
('contact_text','es','Cuéntanos qué quieres resolver. Podemos orientarte sobre IZZY, CAMI, ZYNKO, desarrollo web o una solución a la medida.'),
('contact_text','en','Tell us what you need to solve. We can guide you on IZZY, CAMI, ZYNKO, web development or a custom solution.'),
('affiliate_text','es','Nuestro programa de afiliados permite recomendar las soluciones y servicios de ES MULTISERVICIOS y obtener beneficios según las condiciones vigentes.'),
('affiliate_text','en','Our affiliate program lets you recommend ES MULTISERVICIOS solutions and services and earn benefits under the current program terms.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO settings(setting_key,setting_value) VALUES
('whatsapp_position','left'),
('whatsapp_enabled','1'),
('floating_external_name','NIVO Web Chat'),
('floating_external_position','right'),
('floating_external_enabled','1'),
('floating_external_url','')
ON DUPLICATE KEY UPDATE setting_value=CASE
  WHEN setting_key='whatsapp_position' THEN 'left'
  WHEN setting_key='floating_external_name' THEN 'NIVO Web Chat'
  WHEN setting_key='floating_external_position' THEN 'right'
  ELSE setting_value
END;


-- ES MULTISERVICIOS SOLUTION LOGOS 2026-09-30

UPDATE marketing_products
SET logo_path='assets/brand/izzy-solution.png'
WHERE product_key='izzy' AND (logo_path IS NULL OR logo_path='' OR logo_path='assets/brand/izzy.png');

UPDATE marketing_products
SET logo_path='assets/brand/cami-solution.png'
WHERE product_key='cami' AND (logo_path IS NULL OR logo_path='' OR logo_path='assets/brand/cami-display.png' OR logo_path='assets/brand/cami.png');

UPDATE marketing_products
SET logo_path='assets/brand/zynko-solution.png', accent_color='#16B89A'
WHERE product_key='zynko' AND (logo_path IS NULL OR logo_path='');

UPDATE marketing_products
SET description_es='Plataforma omnicanal multiempresa para centralizar conversaciones, clientes, equipos y canales en una sola operación. Integra NIVO Web Chat y NIVO IA para automatizar atención, trabajar con conocimiento aprobado y transferir conversaciones a personas cuando sea necesario.',
    description_en='Multi-company omnichannel platform that centralizes conversations, customers, teams and channels in one operation. It includes NIVO Web Chat and NIVO AI for assisted service, approved knowledge and human handoff when needed.',
    features_es='Conversaciones centralizadas\nNIVO Web Chat\nNIVO IA y automatización\nUsuarios y permisos\nAPI y webhooks\nNotificaciones\nGestión multiempresa',
    features_en='Centralized conversations\nNIVO Web Chat\nNIVO AI and automation\nUsers and permissions\nAPI and webhooks\nNotifications\nMulti-company management',
    sort_order=30,
    active=1
WHERE product_key='zynko';

INSERT INTO landing_content(content_key,lang,content_value) VALUES
('contact_text','es','Cuéntanos qué quieres resolver. Podemos orientarte sobre IZZY, CAMI, ZYNKO, desarrollo web o una solución a la medida.'),
('contact_text','en','Tell us what you need to solve. We can guide you on IZZY, CAMI, ZYNKO, web development or a custom solution.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);


-- =========================================================
-- CORPORATE SOLUTIONS ADMIN EXTENSION 2026-09-30
-- Logos, taglines and configurable CTA labels for every solution
-- =========================================================
SET @db_name = DATABASE();
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='marketing_products' AND COLUMN_NAME='tagline_es');
SET @sql = IF(@col_exists=0,'ALTER TABLE marketing_products ADD COLUMN tagline_es VARCHAR(180) NULL AFTER accent_color','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='marketing_products' AND COLUMN_NAME='tagline_en');
SET @sql = IF(@col_exists=0,'ALTER TABLE marketing_products ADD COLUMN tagline_en VARCHAR(180) NULL AFTER tagline_es','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='marketing_products' AND COLUMN_NAME='cta_label_es');
SET @sql = IF(@col_exists=0,'ALTER TABLE marketing_products ADD COLUMN cta_label_es VARCHAR(100) NULL AFTER features_en','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='marketing_products' AND COLUMN_NAME='cta_label_en');
SET @sql = IF(@col_exists=0,'ALTER TABLE marketing_products ADD COLUMN cta_label_en VARCHAR(100) NULL AFTER cta_label_es','SELECT 1'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO settings(setting_key,setting_value) VALUES ('site_logo_path','assets/brand/es-multiservicios-official.png')
ON DUPLICATE KEY UPDATE setting_value=CASE WHEN setting_value IS NULL OR TRIM(setting_value)='' THEN VALUES(setting_value) ELSE setting_value END;

-- Use the official logos supplied for the corporate directory.
UPDATE marketing_products SET logo_path='assets/brand/izzy-solution.png', tagline_es='Facturación y gestión empresarial', tagline_en='Billing and business management', cta_label_es='Conocer IZZY', cta_label_en='Explore IZZY' WHERE product_key='izzy';
UPDATE marketing_products SET logo_path='assets/brand/cami-solution.png', tagline_es='Gestión clínica y seguimiento del paciente', tagline_en='Clinical management and patient follow-up', cta_label_es='Conocer CAMI', cta_label_en='Explore CAMI' WHERE product_key='cami';

INSERT INTO marketing_products(product_key,name,logo_path,accent_color,tagline_es,tagline_en,description_es,description_en,features_es,features_en,cta_label_es,cta_label_en,cta_url,sort_order,active)
SELECT 'zynko','ZYNKO','assets/brand/zynko-solution.png','#16B89A',
       'Omnicanal, multiempresa y atención inteligente','Omnichannel, multi-company and intelligent customer service',
       'Plataforma omnicanal multiempresa que centraliza conversaciones, clientes, equipos y canales. Integra NIVO Web Chat y NIVO IA para automatizar atención, trabajar con conocimiento aprobado y transferir conversaciones a personas cuando sea necesario.',
       'Multi-company omnichannel platform that centralizes conversations, customers, teams and channels. It includes NIVO Web Chat and NIVO AI for automation, approved knowledge and human handoff when needed.',
       'Conversaciones centralizadas\nNIVO Web Chat\nNIVO IA y automatización\nUsuarios y permisos\nAPI y webhooks\nNotificaciones\nGestión multiempresa',
       'Centralized conversations\nNIVO Web Chat\nNIVO AI and automation\nUsers and permissions\nAPI and webhooks\nNotifications\nMulti-company management',
       'Conocer ZYNKO','Explore ZYNKO','https://zynko.esmultiservicios.com/',30,1
WHERE NOT EXISTS (SELECT 1 FROM marketing_products WHERE product_key='zynko');

UPDATE marketing_products SET
  logo_path='assets/brand/zynko-solution.png', accent_color='#16B89A',
  tagline_es='Omnicanal, multiempresa y atención inteligente',
  tagline_en='Omnichannel, multi-company and intelligent customer service',
  description_es='Plataforma omnicanal multiempresa que centraliza conversaciones, clientes, equipos y canales. Integra NIVO Web Chat y NIVO IA para automatizar atención, trabajar con conocimiento aprobado y transferir conversaciones a personas cuando sea necesario.',
  description_en='Multi-company omnichannel platform that centralizes conversations, customers, teams and channels. It includes NIVO Web Chat and NIVO AI for automation, approved knowledge and human handoff when needed.',
  features_es='Conversaciones centralizadas\nNIVO Web Chat\nNIVO IA y automatización\nUsuarios y permisos\nAPI y webhooks\nNotificaciones\nGestión multiempresa',
  features_en='Centralized conversations\nNIVO Web Chat\nNIVO AI and automation\nUsers and permissions\nAPI and webhooks\nNotifications\nMulti-company management',
  cta_label_es='Conocer ZYNKO', cta_label_en='Explore ZYNKO', sort_order=30, active=1
WHERE product_key='zynko';


-- =========================================================
-- CUSTOMER OPINIONS / TESTIMONIALS
-- Only real, approved opinions should be published.
-- =========================================================
CREATE TABLE IF NOT EXISTS marketing_testimonials (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_name VARCHAR(180) NOT NULL,
  client_role_es VARCHAR(180) NULL,
  client_role_en VARCHAR(180) NULL,
  solution_name VARCHAR(120) NULL,
  quote_es TEXT NULL,
  quote_en TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_marketing_testimonials_public (active,sort_order,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Improve the corporate hero CTA without overriding later custom copy.
UPDATE landing_content SET content_value='Solicitar asesoría' WHERE content_key='hero_primary' AND lang='es' AND content_value='Solicitar demo';
UPDATE landing_content SET content_value='Request consultation' WHERE content_key='hero_primary' AND lang='en' AND content_value='Request a demo';

UPDATE marketing_products SET cta_url='https://zynko.esmultiservicios.com/' WHERE product_key='zynko' AND (cta_url IS NULL OR TRIM(cta_url)='');


-- Convert the five previous editable placeholders into clean fictional demo profiles.
UPDATE marketing_testimonials SET
 client_name='Carlos Méndez',
 client_role_es='Gerente administrativo · Perfil demostrativo',
 client_role_en='Administrative manager · Demo profile',
 solution_name='IZZY',
 quote_es='Centralizar ventas, inventario y facturación en una sola solución permite trabajar con mayor orden y tener información más clara para la operación diaria.',
 quote_en='Centralizing sales, inventory and billing in one solution helps teams work with better organization and clearer day-to-day information.',
 sort_order=10
WHERE client_name='Ejemplo de opinión 01';
UPDATE marketing_testimonials SET
 client_name='Andrea Castillo', client_role_es='Coordinadora clínica · Perfil demostrativo', client_role_en='Clinical coordinator · Demo profile', solution_name='CAMI',
 quote_es='Tener pacientes, expedientes, visitas y seguimiento clínico organizados en un mismo entorno facilita la continuidad de la atención y reduce tareas dispersas.',
 quote_en='Keeping patients, records, visits and clinical follow-up organized in one environment supports continuity of care and reduces scattered tasks.', sort_order=20
WHERE client_name='Ejemplo de opinión 02';
UPDATE marketing_testimonials SET
 client_name='José Rivera', client_role_es='Coordinador de servicio · Perfil demostrativo', client_role_en='Customer service coordinator · Demo profile', solution_name='ZYNKO',
 quote_es='Una bandeja centralizada con NIVO Web Chat y NIVO IA ayuda a ordenar conversaciones, automatizar respuestas y transferir a una persona cuando la atención lo requiere.',
 quote_en='A centralized inbox with NIVO Web Chat and NIVO AI helps organize conversations, automate responses and hand off to a person when needed.', sort_order=30
WHERE client_name='Ejemplo de opinión 03';
UPDATE marketing_testimonials SET
 client_name='Melissa Hernández', client_role_es='Administración y operaciones · Perfil demostrativo', client_role_en='Administration and operations · Demo profile', solution_name='IZZY',
 quote_es='Contar con una plataforma web adaptable permite consultar la operación desde distintos dispositivos y mantener procesos comerciales en un solo lugar.',
 quote_en='An adaptable web platform makes it possible to review operations from different devices and keep commercial processes in one place.', sort_order=40
WHERE client_name='Ejemplo de opinión 04';
UPDATE marketing_testimonials SET
 client_name='Daniel Flores', client_role_es='Proyecto a la medida · Perfil demostrativo', client_role_en='Custom project · Demo profile', solution_name='Soluciones a la medida',
 quote_es='Cuando el proceso no encaja en un sistema estándar, una solución desarrollada a la medida permite digitalizar el flujo real de trabajo sin obligar al equipo a cambiar su operación.',
 quote_en='When a process does not fit a standard system, a custom solution can digitize the real workflow without forcing the team to reshape its operation.', sort_order=50
WHERE client_name='Ejemplo de opinión 05';

-- =========================================================
-- OPINIONES / CASOS ILUSTRATIVOS INICIALES
-- Los nombres son ficticios y se identifican como tales para
-- no presentar testimonios inventados como clientes reales.
-- Se pueden editar o sustituir completamente desde Admin.
-- =========================================================
INSERT INTO marketing_testimonials
(client_name,client_role_es,client_role_en,solution_name,quote_es,quote_en,sort_order,active)
SELECT seed.client_name,seed.client_role_es,seed.client_role_en,seed.solution_name,seed.quote_es,seed.quote_en,seed.sort_order,1
FROM (
    SELECT 'Carlos Méndez' AS client_name,
           'Gerente administrativo · Perfil demostrativo' AS client_role_es,
           'Administrative manager · Demo profile' AS client_role_en,
           'IZZY' AS solution_name,
           'Centralizar ventas, inventario y facturación en una sola solución permite trabajar con mayor orden y tener información más clara para la operación diaria.' AS quote_es,
           'Centralizing sales, inventory and billing in one solution helps teams work with better organization and clearer day-to-day information.' AS quote_en,
           10 AS sort_order
    UNION ALL
    SELECT 'Andrea Castillo','Coordinadora clínica · Perfil demostrativo','Clinical coordinator · Demo profile','CAMI',
           'Tener pacientes, expedientes, visitas y seguimiento clínico organizados en un mismo entorno facilita la continuidad de la atención y reduce tareas dispersas.',
           'Keeping patients, records, visits and clinical follow-up organized in one environment supports continuity of care and reduces scattered tasks.',20
    UNION ALL
    SELECT 'José Rivera','Coordinador de servicio · Perfil demostrativo','Customer service coordinator · Demo profile','ZYNKO',
           'Una bandeja centralizada con NIVO Web Chat y NIVO IA ayuda a ordenar conversaciones, automatizar respuestas y transferir a una persona cuando la atención lo requiere.',
           'A centralized inbox with NIVO Web Chat and NIVO AI helps organize conversations, automate responses and hand off to a person when needed.',30
    UNION ALL
    SELECT 'Melissa Hernández','Administración y operaciones · Perfil demostrativo','Administration and operations · Demo profile','IZZY',
           'Contar con una plataforma web adaptable permite consultar la operación desde distintos dispositivos y mantener procesos comerciales en un solo lugar.',
           'An adaptable web platform makes it possible to review operations from different devices and keep commercial processes in one place.',40
    UNION ALL
    SELECT 'Daniel Flores','Proyecto a la medida · Perfil demostrativo','Custom project · Demo profile','Soluciones a la medida',
           'Cuando el proceso no encaja en un sistema estándar, una solución desarrollada a la medida permite digitalizar el flujo real de trabajo sin obligar al equipo a cambiar su operación.',
           'When a process does not fit a standard system, a custom solution can digitize the real workflow without forcing the team to reshape its operation.',50
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM marketing_testimonials LIMIT 1);



-- 2026-09-30: migrate previous demo testimonial names to person-style demo profiles.
UPDATE marketing_testimonials SET client_name='Carlos Méndez', client_role_es='Gerente administrativo · Perfil demostrativo', client_role_en='Administrative manager · Demo profile' WHERE client_name='Grupo Nova Comercial';
UPDATE marketing_testimonials SET client_name='Andrea Castillo', client_role_es='Coordinadora clínica · Perfil demostrativo', client_role_en='Clinical coordinator · Demo profile' WHERE client_name='Clínica Vida Integral';
UPDATE marketing_testimonials SET client_name='José Rivera', client_role_es='Coordinador de servicio · Perfil demostrativo', client_role_en='Customer service coordinator · Demo profile' WHERE client_name='Conecta Centroamérica';
UPDATE marketing_testimonials SET client_name='Melissa Hernández', client_role_es='Administración y operaciones · Perfil demostrativo', client_role_en='Administration and operations · Demo profile' WHERE client_name='Distribuidora Horizonte';
UPDATE marketing_testimonials SET client_name='Daniel Flores', client_role_es='Proyecto a la medida · Perfil demostrativo', client_role_en='Custom project · Demo profile' WHERE client_name='Innova Servicios HN';

-- ============================================================
-- MULTI FLOATING WIDGET MANAGER
-- WhatsApp + NIVO Web Chat + future external widgets
-- ============================================================
INSERT INTO settings(setting_key,setting_value) VALUES
('floating_widgets_json',''),
('whatsapp_order','10'),
('whatsapp_show_desktop','1'),
('whatsapp_show_mobile','1'),
('floating_widget_gap','12')
ON DUPLICATE KEY UPDATE setting_value=setting_value;

-- ============================================================
-- 2026-10-03: CONTACT EMAIL VALIDATION + CROSS-SESSION RATE LIMIT
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_rate_limits (
  ip_hash CHAR(64) NOT NULL,
  window_started_at DATETIME NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_attempt_at DATETIME NOT NULL,
  PRIMARY KEY (ip_hash),
  KEY idx_contact_rate_last (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings(setting_key,setting_value) VALUES
('contact_email_dns_validation','1'),
('contact_email_block_disposable','1'),
('contact_email_api_enabled','0'),
('contact_email_api_url',''),
('contact_email_api_key','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
