<?php
declare(strict_types=1);
session_start();
require __DIR__.'/config/bootstrap.php';
if(!config_ready()){ header('Location: install/'); exit; }

$settings=settings();
$requested=(string)($_GET['lang']??'');
if(in_array($requested,['es','en'],true)){
    setcookie('esms_lang',$requested,['expires'=>time()+31536000,'path'=>'/','samesite'=>'Lax']);
    $lang=$requested;
}else{
    $cookie=(string)($_COOKIE['esms_lang']??'');
    $lang=in_array($cookie,['es','en'],true)?$cookie:(str_starts_with(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE']??'')),'en')?'en':'es');
}
$copy=landing_content($lang);
$products=marketing_products();
$plans=marketing_plans();
$projects=marketing_projects();
$t=static fn(string $key,string $fallback=''): string =>$copy[$key]??$fallback;
$pick=static fn(array $row,string $base) => (string)($row[$base.'_'.$lang]??$row[$base.'_es']??'');
$lines=static fn(string $value):array=>array_values(array_filter(array_map('trim',preg_split('/\R+/',$value)?:[])));
$digits=preg_replace('/\D+/','',(string)($settings['phone_digits']??'50489136844'));
$waBase='https://wa.me/'.$digits.'?text=';
$wa=static fn(string $message):string=>$GLOBALS['waBase'].rawurlencode($message);
$name=(string)($settings['company_name']??'ES MULTISERVICIOS');
$maintenance=($settings['maintenance_mode']??'0')==='1';
$adminPreview=!empty($_SESSION['escms_admin_id'])&&($_GET['preview']??'')==='1';
if($maintenance&&!$adminPreview){
    $title=$settings['maintenance_title']??($lang==='es'?'Estamos mejorando nuestro sitio.':'We are improving our website.');
    $text=$settings['maintenance_text']??'';
    ?><!doctype html><html lang="<?=h($lang)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($title)?></title><link rel="stylesheet" href="assets/es-site.css"></head><body class="maintenance-page"><main class="maintenance-card"><img src="assets/brand/es-multiservicios.png" alt="ES MULTISERVICIOS"><h1><?=h($title)?></h1><p><?=h($text)?></p><a class="btn btn-primary" href="<?=h($wa($lang==='es'?'Hola, necesito información de ES MULTISERVICIOS.':'Hello, I need information about ES MULTISERVICIOS.'))?>">WhatsApp</a></main></body></html><?php exit;
}
$nav=[
 'home'=>$lang==='es'?'Inicio':'Home','solutions'=>$lang==='es'?'Soluciones':'Solutions','izzy'=>'IZZY','cami'=>'CAMI',
 'services'=>$lang==='es'?'Servicios':'Services','projects'=>$lang==='es'?'Proyectos':'Projects','affiliate'=>$lang==='es'?'Afiliados':'Affiliates','contact'=>$lang==='es'?'Contacto':'Contact'
];
?><!doctype html>
<html lang="<?=h($lang)?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($settings['seo_title']??'ES MULTISERVICIOS')?></title>
<meta name="description" content="<?=h($settings['seo_description']??'')?>">
<meta name="theme-color" content="#0A2A4A">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/es-site.css">
</head>
<body>
<header class="site-header" data-header>
  <div class="shell nav-shell">
    <a class="master-brand" href="#home" aria-label="ES MULTISERVICIOS"><img src="assets/brand/es-multiservicios.png" alt="ES MULTISERVICIOS"></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-label="Menu" data-nav-toggle><span></span><span></span><span></span></button>
    <nav class="site-nav" data-nav>
      <?php foreach($nav as $id=>$label): ?><a href="#<?=h($id)?>"><?=h($label)?></a><?php endforeach; ?>
    </nav>
    <div class="header-actions">
      <div class="lang-switch" aria-label="Language"><a class="<?=$lang==='es'?'active':''?>" href="?lang=es">ES</a><a class="<?=$lang==='en'?'active':''?>" href="?lang=en">EN</a></div>
      <a class="btn btn-compact btn-primary desktop-cta" href="<?=h($wa($lang==='es'?'Hola, quiero información sobre ES MULTISERVICIOS.':'Hello, I would like information about ES MULTISERVICIOS.'))?>">WhatsApp</a>
    </div>
  </div>
</header>
<main>
<section class="hero" id="home">
  <div class="shell hero-grid">
    <div class="hero-copy reveal">
      <span class="eyebrow"><?=h($t('hero_kicker'))?></span>
      <h1><?=h($t('hero_title'))?></h1>
      <p class="lead"><?=h($t('hero_text'))?></p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?=h($wa($lang==='es'?'Hola, quiero solicitar una demostración de sus soluciones.':'Hello, I would like to request a demo of your solutions.'))?>"><?=h($t('hero_primary'))?></a>
        <a class="btn btn-secondary" href="<?=h($wa($lang==='es'?'Hola, quiero conocer más sobre ES MULTISERVICIOS.':'Hello, I would like to learn more about ES MULTISERVICIOS.'))?>"><?=h($t('hero_secondary'))?></a>
      </div>
      <div class="trust-row">
        <div><strong>100% Web</strong><span><?= $lang==='es'?'Acceso desde navegador':'Browser access' ?></span></div>
        <div><strong>Responsive</strong><span><?= $lang==='es'?'Computadora, tablet y teléfono':'Desktop, tablet and phone' ?></span></div>
        <div><strong><?= $lang==='es'?'A tu medida':'Built around you' ?></strong><span><?= $lang==='es'?'Tecnología adaptable':'Adaptable technology' ?></span></div>
      </div>
    </div>
    <div class="hero-media reveal">
      <div class="product-window main-shot"><img src="assets/products/izzy-dashboard.png" alt="IZZY dashboard"></div>
      <div class="product-window mobile-shot"><img src="assets/products/izzy-mobile.jpeg" alt="IZZY responsive mobile view"></div>
      <div class="product-window login-shot"><img src="assets/products/izzy-login.png" alt="IZZY login"></div>
    </div>
  </div>
</section>

<section class="section section-soft" id="solutions">
  <div class="shell">
    <div class="section-heading centered reveal"><span class="eyebrow"><?= $lang==='es'?'PRODUCTOS PROPIOS':'OUR PRODUCTS' ?></span><h2><?=h($t('solutions_title'))?></h2><p><?=h($t('solutions_text'))?></p></div>
    <div class="product-grid">
      <?php foreach($products as $product): $features=$lines($pick($product,'features')); ?>
      <article class="solution-card reveal" style="--product-accent:<?=h($product['accent_color'])?>">
        <div class="solution-brand"><img src="<?=h($product['logo_path'])?>" alt="<?=h($product['name'])?>"><span><?=h($product['name'])?></span></div>
        <p><?=h($pick($product,'description'))?></p>
        <div class="feature-pills"><?php foreach(array_slice($features,0,4) as $feature): ?><span><?=h($feature)?></span><?php endforeach; ?></div>
        <a class="text-link" href="<?=h($product['cta_url']?:'#'.$product['product_key'])?>"><?= $lang==='es'?'Conocer':'Explore' ?> <?=h($product['name'])?> <b>→</b></a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="izzy">
  <div class="shell">
    <div class="product-section-head reveal"><div><span class="eyebrow product-blue">IZZY</span><h2><?=h($t('izzy_title'))?></h2><p><?=h($t('izzy_text'))?></p></div><img class="product-logo" src="assets/brand/izzy.png" alt="IZZY"></div>
    <div class="mode-grid">
      <article class="mode-card reveal"><div class="screen-frame"><img src="assets/products/izzy-classic-billing.png" alt="IZZY classic billing"></div><div class="mode-copy"><span>01</span><h3><?= $lang==='es'?'Facturación clásica':'Classic billing' ?></h3><p><?= $lang==='es'?'Interfaz empresarial tradicional para trabajar con productos, cantidades, precios, clientes y líneas de factura de forma directa.':'A traditional business interface for products, quantities, pricing, customers and invoice lines.' ?></p></div></article>
      <article class="mode-card reveal"><div class="screen-frame restaurant-crop"><img src="assets/products/izzy-restaurant.png" alt="IZZY visual restaurant interface"></div><div class="mode-copy"><span>02</span><h3><?= $lang==='es'?'Experiencia visual configurable':'Configurable visual experience' ?></h3><p><?= $lang==='es'?'Productos con imágenes y selección visual. Puede configurarse para restaurante y también para otros negocios que trabajan mejor con un catálogo visual.':'Image-based products and visual selection. It can be configured for restaurants and other businesses that work better with a visual catalog.' ?></p></div></article>
      <article class="mode-card reveal"><div class="mobile-mode"><img src="assets/products/izzy-mobile.jpeg" alt="IZZY on mobile"></div><div class="mode-copy"><span>03</span><h3><?= $lang==='es'?'Web y responsive':'Web and responsive' ?></h3><p><?= $lang==='es'?'Se utiliza desde el navegador en computadora, tablet o teléfono. No requiere instalar una aplicación móvil.':'Use it from a browser on desktop, tablet or phone. No mobile app installation is required.' ?></p></div></article>
    </div>
    <div class="izzy-benefits reveal"><span>100% Web</span><span>Responsive</span><span><?= $lang==='es'?'Facturación e inventario':'Billing & inventory' ?></span><span><?= $lang==='es'?'Restaurante según plan':'Restaurant by plan' ?></span><span><?= $lang==='es'?'Reportes y gestión':'Reports & management' ?></span></div>
  </div>
</section>

<section class="section cami-section" id="cami">
  <div class="shell cami-grid">
    <div class="cami-brand-panel reveal"><img src="assets/brand/cami.png" alt="CAMI"><span>CAMI</span><p><?= $lang==='es'?'Simplifica la Salud, Optimiza la Gestión':'Simplify healthcare, optimize management' ?></p></div>
    <div class="cami-copy reveal"><span class="eyebrow product-green">CAMI</span><h2><?=h($t('cami_title'))?></h2><p class="lead-small"><?=h($t('cami_text'))?></p><div class="cami-features"><div><?= $lang==='es'?'Pacientes':'Patients' ?></div><div><?= $lang==='es'?'Atenciones':'Visits' ?></div><div><?= $lang==='es'?'Expedientes':'Records' ?></div><div><?= $lang==='es'?'Seguimiento':'Follow-up' ?></div><div><?= $lang==='es'?'Documentos':'Documents' ?></div><div><?= $lang==='es'?'Gestión':'Management' ?></div></div><p class="subtle"><?= $lang==='es'?'Las capturas reales de CAMI se incorporarán cuando completemos el material visual del producto.':'Real CAMI screenshots will be incorporated once the product visual material is completed.' ?></p><a class="btn btn-cami" href="<?=h($wa($lang==='es'?'Hola, quiero información y una demostración de CAMI.':'Hello, I would like information and a CAMI demo.'))?>"><?= $lang==='es'?'Solicitar demo de CAMI':'Request CAMI demo' ?></a></div>
  </div>
</section>

<section class="section section-dark" id="services">
 <div class="shell"><div class="section-heading reveal"><span class="eyebrow light"><?= $lang==='es'?'SERVICIOS':'SERVICES' ?></span><h2><?=h($t('services_title'))?></h2><p><?=h($t('services_text'))?></p></div>
 <div class="service-grid">
 <?php $servicesPublic=$lang==='es'?[['Sitios web','Sitios corporativos, landing pages y CMS administrables.'],['Software a la medida','Sistemas diseñados alrededor de tus procesos.'],['Automatización','Menos trabajo repetitivo y procesos más ordenados.'],['Integraciones','Conectamos plataformas, APIs y servicios.'],['Implementación','Configuración, migración y acompañamiento.'],['Soporte','Atención para ayudarte a seguir operando.']]:[['Websites','Corporate sites, landing pages and manageable CMS platforms.'],['Custom software','Systems designed around your processes.'],['Automation','Less repetitive work and more organized processes.'],['Integrations','We connect platforms, APIs and services.'],['Implementation','Configuration, migration and onboarding.'],['Support','Assistance to help you keep operating.']]; foreach($servicesPublic as $i=>$svc): ?>
 <article class="service-card reveal"><span><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><h3><?=h($svc[0])?></h3><p><?=h($svc[1])?></p></article><?php endforeach; ?>
 </div></div>
</section>

<?php if($plans): ?><section class="section" id="plans"><div class="shell"><div class="section-heading centered reveal"><span class="eyebrow"><?= $lang==='es'?'PLANES':'PLANS' ?></span><h2><?= $lang==='es'?'Elige una opción que se adapte a tu operación':'Choose an option that fits your operation' ?></h2></div><div class="plans-grid"><?php foreach($plans as $plan): ?><article class="plan-card <?=$plan['featured']?'featured':''?> reveal"><?php if($pick($plan,'badge')):?><span class="plan-badge"><?=h($pick($plan,'badge'))?></span><?php endif; ?><small><?=h(strtoupper($plan['product_key']))?></small><h3><?=h($pick($plan,'name'))?></h3><strong class="plan-price"><?=h($pick($plan,'price_label'))?></strong><p><?=h($pick($plan,'description'))?></p><div class="plan-features"><?php foreach($lines($pick($plan,'features')) as $f):?><span>✓ <?=h($f)?></span><?php endforeach;?></div><a class="btn btn-primary" href="<?=h($plan['cta_url']?:$wa(($lang==='es'?'Hola, quiero información del plan ':'Hello, I need information about the plan ').$pick($plan,'name')))?>"><?= $lang==='es'?'Consultar':'Ask about it' ?></a></article><?php endforeach;?></div></div></section><?php endif; ?>

<section class="section" id="affiliate">
 <div class="shell affiliate-card reveal"><div><span class="eyebrow"><?= $lang==='es'?'PROGRAMA DE AFILIADOS':'AFFILIATE PROGRAM' ?></span><h2><?=h($t('affiliate_title'))?></h2><p><?=h($t('affiliate_text'))?></p><div class="affiliate-steps"><span><b>1</b><?= $lang==='es'?'Recomienda':'Recommend' ?></span><span><b>2</b><?= $lang==='es'?'Conecta':'Connect' ?></span><span><b>3</b><?= $lang==='es'?'Gana':'Earn' ?></span></div></div><a class="btn btn-light" href="<?=h($wa($lang==='es'?'Hola, estoy interesado en el programa de afiliados de ES MULTISERVICIOS.':'Hello, I am interested in the ES MULTISERVICIOS affiliate program.'))?>"><?=h($t('affiliate_cta'))?></a></div>
</section>

<section class="section section-soft" id="projects">
 <div class="shell"><div class="section-heading reveal"><span class="eyebrow"><?= $lang==='es'?'PROYECTOS':'PROJECTS' ?></span><h2><?=h($t('projects_title'))?></h2></div><div class="projects-grid"><?php foreach($projects as $project): ?><article class="project-card reveal"><?php if(!empty($project['image_path'])):?><img src="<?=h($project['image_path'])?>" alt="<?=h($project['title'])?>"><?php else:?><div class="project-placeholder"><span>ES</span></div><?php endif; ?><div><small><?=h($pick($project,'category'))?></small><h3><?=h($project['title'])?></h3><p><?=h($pick($project,'description'))?></p><?php if($project['project_url']):?><a class="text-link" href="<?=h($project['project_url'])?>" target="_blank" rel="noopener"><?= $lang==='es'?'Ver proyecto':'View project' ?> →</a><?php endif;?></div></article><?php endforeach;?></div></div>
</section>

<section class="section why-section"><div class="shell why-grid"><div class="section-heading reveal"><span class="eyebrow"><?= $lang==='es'?'NUESTRO ENFOQUE':'OUR APPROACH' ?></span><h2><?=h($t('why_title'))?></h2><p><?= $lang==='es'?'La tecnología debe simplificar la vida de las personas. Por eso construimos soluciones claras, adaptables y acompañadas por soporte real.':'Technology should simplify people’s lives. That is why we build clear, adaptable solutions backed by real support.' ?></p></div><div class="why-list reveal"><?php $why=$lang==='es'?['Productos propios desarrollados por nuestro equipo','Soluciones adaptadas a cada negocio','Diseño responsive y acceso web','Seguridad y control de acceso','Acompañamiento, implementación y soporte','Capacidad para desarrollar a la medida']:['Our own products built by our team','Solutions adapted to each business','Responsive design and web access','Security and access control','Implementation, onboarding and support','Custom development capabilities']; foreach($why as $item):?><span>✓ <?=h($item)?></span><?php endforeach;?></div></div></section>

<section class="section contact-section" id="contact"><div class="shell contact-card reveal"><div><span class="eyebrow light"><?= $lang==='es'?'HABLEMOS':'LET’S TALK' ?></span><h2><?=h($t('contact_title'))?></h2><p><?=h($t('contact_text'))?></p></div><div class="contact-actions"><a class="btn btn-whatsapp" href="<?=h($wa($lang==='es'?'Hola, quiero información sobre sus soluciones.':'Hello, I would like information about your solutions.'))?>">WhatsApp</a><a class="btn btn-light" href="<?=h($wa($lang==='es'?'Hola, quiero solicitar una demostración.':'Hello, I would like to request a demo.'))?>"><?=h($t('hero_primary'))?></a><a class="support-link" href="<?=h($wa($lang==='es'?'Hola, ya soy cliente y necesito soporte.':'Hello, I am already a customer and I need support.'))?>"><?=h($t('support_cta'))?> →</a></div></div></section>
</main>
<footer class="site-footer"><div class="shell footer-grid"><div class="footer-brand"><img src="assets/brand/es-multiservicios.png" alt="ES MULTISERVICIOS"><p><?= $lang==='es'?'Más que servicios, construimos soluciones.':'More than services, we build solutions.' ?></p></div><div><strong><?= $lang==='es'?'Soluciones':'Solutions' ?></strong><a href="#izzy">IZZY</a><a href="#cami">CAMI</a><a href="#services"><?= $lang==='es'?'Servicios':'Services' ?></a></div><div><strong><?= $lang==='es'?'Empresa':'Company' ?></strong><a href="#projects"><?= $lang==='es'?'Proyectos':'Projects' ?></a><a href="#affiliate"><?= $lang==='es'?'Afiliados':'Affiliates' ?></a><a href="#contact"><?= $lang==='es'?'Contacto':'Contact' ?></a></div><div><strong><?= $lang==='es'?'Soporte':'Support' ?></strong><a href="<?=h($wa($lang==='es'?'Hola, necesito soporte.':'Hello, I need support.'))?>">WhatsApp</a><span><?=h($settings['phone']??'+504 8913-6844')?></span><span>esmultiservicios.com</span></div></div><div class="shell footer-bottom"><span>© <?=date('Y')?> ES MULTISERVICIOS</span><span><?= $lang==='es'?'Todos los derechos reservados.':'All rights reserved.' ?></span></div></footer>
<a class="floating-wa" href="<?=h($wa($lang==='es'?'Hola, quiero información sobre ES MULTISERVICIOS.':'Hello, I would like information about ES MULTISERVICIOS.'))?>" aria-label="WhatsApp">WA</a>
<script src="assets/es-site.js"></script>
</body></html>
