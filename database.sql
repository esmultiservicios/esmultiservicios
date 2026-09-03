SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  full_name VARCHAR(150) NULL,
  email VARCHAR(180) NULL,
  avatar_path VARCHAR(500) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(64) NULL,
  last_user_agent VARCHAR(500) NULL,
  two_factor_secret_enc TEXT NULL,
  two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_admin_username (username), KEY idx_admin_email (email), KEY idx_admin_role_active(role_id,active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_reset_token (token_hash),
  KEY idx_admin_reset_user (admin_id),
  KEY idx_admin_reset_expiry (expires_at),
  CONSTRAINT fk_admin_reset_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_remember_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  selector CHAR(18) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_remember_selector (selector),
  KEY idx_admin_remember_user (admin_id),
  KEY idx_admin_remember_expiry (expires_at),
  CONSTRAINT fk_admin_remember_user FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_content (
  content_key VARCHAR(100) NOT NULL,
  content_value TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (content_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  details TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  image_path VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_areas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_name VARCHAR(180) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(220) NOT NULL,
  url VARCHAR(500) NOT NULL DEFAULT '#',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(150) NULL,
  phone VARCHAR(80) NULL,
  email VARCHAR(180) NULL,
  address VARCHAR(255) NULL,
  service_needed VARCHAR(150) NULL,
  desired_date DATE NULL,
  message TEXT NULL,
  photo_path VARCHAR(500) NULL,
  status ENUM('new','contacted','in_progress','won','lost','closed') NOT NULL DEFAULT 'new',
  assigned_to INT UNSIGNED NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  follow_up_date DATE NULL,
  internal_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_estimate_status_created(status,created_at), KEY idx_estimate_assigned(assigned_to,status,follow_up_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  estimate_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime_type VARCHAR(100) NULL,
  file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_estimate_attachment_estimate (estimate_id),
  CONSTRAINT fk_estimate_attachment_request FOREIGN KEY (estimate_id) REFERENCES estimate_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correo_tipo (
  correo_tipo_id INT NOT NULL,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS correo (
  correo_id INT NOT NULL AUTO_INCREMENT COMMENT 'Identificador unico de la configuracion de correo',
  correo_tipo_id INT NOT NULL COMMENT 'Tipo de correo',
  metodo_envio ENUM('SMTP','GRAPH') NOT NULL DEFAULT 'SMTP' COMMENT 'SMTP o Microsoft Graph',
  server VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'Servidor SMTP o graph.microsoft.com',
  correo VARCHAR(180) NOT NULL COMMENT 'Correo emisor',
  password TEXT NULL COMMENT 'Contrasena SMTP cifrada',
  port INT NOT NULL DEFAULT 587 COMMENT 'Puerto SMTP; Graph usa 0',
  smtp_secure VARCHAR(10) NOT NULL DEFAULT 'tls' COMMENT 'tls o ssl',
  tenant_id VARCHAR(150) DEFAULT NULL,
  client_id VARCHAR(150) DEFAULT NULL,
  client_secret TEXT NULL COMMENT 'Client secret cifrado',
  graph_user VARCHAR(180) DEFAULT NULL,
  save_to_sent_items TINYINT(1) NOT NULL DEFAULT 1,
  estado TINYINT NOT NULL DEFAULT 1 COMMENT '1 Activo, 2 Inactivo',
  fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (correo_id),
  KEY idx_correo_tipo_estado (correo_tipo_id,estado),
  CONSTRAINT fk_correo_tipo FOREIGN KEY (correo_tipo_id) REFERENCES correo_tipo(correo_tipo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_integrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_name VARCHAR(120) NOT NULL,
  api_type VARCHAR(40) NOT NULL DEFAULT 'custom',
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  environment ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  auth_type VARCHAR(40) NOT NULL DEFAULT 'api_key',
  base_url VARCHAR(500) NULL,
  public_key VARCHAR(500) NULL,
  secret_key TEXT NULL,
  webhook_secret TEXT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO correo_tipo (correo_tipo_id,nombre) VALUES
(1,'Website Alerts'),(2,'Admin Security'),(3,'Estimate Requests'),(4,'Auto Replies')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

INSERT INTO site_content(content_key,content_value) VALUES
('hero_eyebrow','PROFESSIONAL · RELIABLE · READY'),
('hero_title','A clear message for your business starts here.'),
('hero_text','Use the administrator to replace this sample text with your company value proposition, services and customer message.'),
('intro_title','Present your main services clearly.'),
('intro_text','This reusable CMS core is ready for your own company content, images, services and calls to action.'),
('about_title','Tell visitors who you are and why they can trust you.'),
('about_text','Add a short company introduction from Page Content.'),
('about_text_2','Use this area for a second paragraph, differentiator or customer promise.'),
('mission','Write your company mission here.'),
('vision','Write your company vision here.'),
('areas_title','Where do you provide service?'),
('areas_text','Add cities, ZIP codes or coverage areas from the administrator panel.'),
('estimate_title','Tell us what you need.'),
('estimate_text','Complete the form with the information available. Attachments are optional.'),
('contact_title','Ready to talk? Contact our team.')
ON DUPLICATE KEY UPDATE content_value=VALUES(content_value);

INSERT INTO settings(setting_key,setting_value) VALUES
('company_name','Your Company'),
('phone','+1 (000) 000-0000'),
('phone_digits','10000000000'),
('email','administracion@esmultiservicios.com'),
('youtube','#'),('facebook','#'),('tiktok','#'),('website','example.com'),('business_hours',''),
('admin_brand_name','ES MULTISERVICIOS Admin'),('admin_logo_path','assets/brand/es-mark.png'),('favicon_path','assets/brand/favicon.png'),
('maintenance_mode','0'),('maintenance_title','We are improving our website.'),
('maintenance_text','We will be back shortly. For immediate assistance, use the contact information provided by the company.'),
('maintenance_image_path',''),
('whatsapp_enabled','0'),('whatsapp_message','Hello, I would like more information about your services.'),('whatsapp_position','right'),
('banner_enabled','0'),('banner_image_path',''),('banner_alt','Website banner'),
('seo_title','Your Company | Professional Services'),
('seo_description','Describe your company, services and value proposition here.')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

INSERT INTO services(title,details,sort_order,active)
SELECT 'Service One','Describe the main benefits, scope or subservices here.',10,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Service One');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Service Two','Add a second example service or replace this record from the administrator.',20,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Service Two');
INSERT INTO services(title,details,sort_order,active)
SELECT 'Service Three','Use the service manager to add, edit, reorder or hide services.',30,1
WHERE NOT EXISTS (SELECT 1 FROM services WHERE title='Service Three');

INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Project / Portfolio Item 1','',10,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Project / Portfolio Item 1');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Project / Portfolio Item 2','',20,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Project / Portfolio Item 2');
INSERT INTO gallery(title,image_path,sort_order,active)
SELECT 'Project / Portfolio Item 3','',30,1 WHERE NOT EXISTS (SELECT 1 FROM gallery WHERE title='Project / Portfolio Item 3');

INSERT INTO tips(title,url,sort_order,active)
SELECT 'Helpful Tip 1','#',10,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Helpful Tip 1');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Helpful Tip 2','#',20,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Helpful Tip 2');
INSERT INTO tips(title,url,sort_order,active)
SELECT 'Helpful Tip 3','#',30,1 WHERE NOT EXISTS (SELECT 1 FROM tips WHERE title='Helpful Tip 3');


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
('home','Inicio / Hero',10,1),
('solutions','Soluciones',20,1),
('izzy','IZZY',30,1),
('plans','Planes de IZZY',35,1),
('cami','CAMI',40,1),
('services','Servicios',50,1),
('videos','Videos',60,1),
('projects','Proyectos',70,1),
('affiliate','Afiliados',80,1),
('company-artwork','Material corporativo',90,1),
('why','Por qué ES MULTISERVICIOS',100,1),
('contact','Contacto',110,1)
ON DUPLICATE KEY UPDATE label=VALUES(label);
INSERT INTO settings(setting_key,setting_value) VALUES
('seo_title','Your Company | Professional Services'),
('seo_description','Describe your company, services and value proposition here.'),
('seo_social_image',''),('seo_robots','index,follow'),
('google_site_verification',''),
('developer_credit_enabled','0'),('developer_credit_text','Website by ES MULTISERVICIOS')
ON DUPLICATE KEY UPDATE setting_value=setting_value;



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
('videos.manage','Manage website videos','Content'),
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
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage' WHERE r.role_key='administrator';
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
INSERT IGNORE INTO admin_role_permissions(role_id,permission_id) SELECT r.id,p.id FROM admin_roles r JOIN admin_permissions p ON p.permission_key='videos.manage' WHERE r.role_key='editor';
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
('cami','CAMI','assets/brand/cami-display.png','#16CDB7',
'Sistema web para clínicas y consultorios que centraliza la información y el seguimiento del paciente.','Web system for clinics and practices that centralizes patient information and follow-up.',
'Pacientes\nAtenciones\nExpedientes e historial\nSeguimiento clínico\nDocumentos y reportes\nGestión administrativa',
'Patients\nVisits\nRecords and history\nClinical follow-up\nDocuments and reports\nAdministrative management',
'#cami',20,1)
ON DUPLICATE KEY UPDATE name=VALUES(name),logo_path=VALUES(logo_path),accent_color=VALUES(accent_color),description_es=VALUES(description_es),description_en=VALUES(description_en),features_es=VALUES(features_es),features_en=VALUES(features_en),cta_url=VALUES(cta_url),sort_order=VALUES(sort_order),active=VALUES(active);

INSERT INTO marketing_projects(title,category_es,category_en,description_es,description_en,image_path,project_url,sort_order,active)
SELECT 'Castro''s Ready','Sitio corporativo + CMS personalizado','Corporate website + custom CMS',
'Sitio corporativo responsive con administrador propio para contenido, servicios, multimedia, SEO, usuarios, seguridad, respaldos y configuración visual.',
'Responsive corporate website with a custom administrator for content, services, media, SEO, users, security, backups and visual configuration.',
'assets/projects/castros-ready-logo.jpg','https://castroready.esmultiservicios.com/',10,1
WHERE NOT EXISTS (SELECT 1 FROM marketing_projects WHERE title='Castro''s Ready');

UPDATE marketing_projects
SET image_path='assets/projects/castros-ready-logo.jpg'
WHERE title='Castro''s Ready' AND (image_path IS NULL OR image_path='');

INSERT INTO settings(setting_key,setting_value) VALUES
('company_name','ES MULTISERVICIOS'),
('admin_brand_name','ES MULTISERVICIOS Admin'),
('admin_logo_path','assets/brand/es-mark.png'),
('favicon_path','assets/brand/favicon.png'),
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

SET FOREIGN_KEY_CHECKS=1;

-- ES MULTISERVICIOS premium contact defaults
INSERT INTO settings(setting_key,setting_value) VALUES
('contact_map_query','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
