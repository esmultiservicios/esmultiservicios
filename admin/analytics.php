<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_permission('analytics.view');

$tzName = setting('site_timezone', 'America/Tegucigalpa');
try { $tz = new DateTimeZone($tzName); } catch (Throwable $e) { $tz = new DateTimeZone('America/Tegucigalpa'); $tzName='America/Tegucigalpa'; }
$today = (new DateTimeImmutable('now',$tz))->format('Y-m-d');
$yesterday = (new DateTimeImmutable('yesterday',$tz))->format('Y-m-d');

$metrics=['today'=>0,'today_unique'=>0,'yesterday'=>0,'week'=>0,'total'=>0,'unique_total'=>0];
$history=[]; $recent=[]; $tableReady=true;
try {
    ensure_site_visits_table();
    $q=db()->prepare('SELECT COUNT(*) visits, COUNT(DISTINCT visitor_key) unique_visitors FROM site_visits WHERE visit_date=?');
    $q->execute([$today]); $r=$q->fetch()?:[]; $metrics['today']=(int)($r['visits']??0); $metrics['today_unique']=(int)($r['unique_visitors']??0);
    $q->execute([$yesterday]); $r=$q->fetch()?:[]; $metrics['yesterday']=(int)($r['visits']??0);
    $weekStart=(new DateTimeImmutable('today',$tz))->modify('-6 days')->format('Y-m-d');
    $q=db()->prepare('SELECT COUNT(*) FROM site_visits WHERE visit_date BETWEEN ? AND ?'); $q->execute([$weekStart,$today]); $metrics['week']=(int)$q->fetchColumn();
    $r=db()->query('SELECT COUNT(*) visits, COUNT(DISTINCT visitor_key) unique_visitors FROM site_visits')->fetch()?:[]; $metrics['total']=(int)($r['visits']??0); $metrics['unique_total']=(int)($r['unique_visitors']??0);
    $history=db()->query("SELECT visit_date,COUNT(*) visits,COUNT(DISTINCT visitor_key) unique_visitors,MIN(visited_at) first_visit,MAX(visited_at) last_visit FROM site_visits GROUP BY visit_date ORDER BY visit_date DESC LIMIT 31")->fetchAll();
    $recent=db()->query("SELECT path,visited_at,visit_date FROM site_visits ORDER BY id DESC LIMIT 30")->fetchAll();
} catch(Throwable $e) { $tableReady=false; }
function local_analytics_time(?string $utc, DateTimeZone $tz): string {
    if(!$utc) return '—';
    try { return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d h:i:s A'); } catch(Throwable $e) { return '—'; }
}
$pageTitle='Analytics'; $active='analytics'; require __DIR__.'/_header.php';
?>
<div class="page-heading animate-in"><div><p class="eyebrow">ANALYTICS</p><h1>Website visits</h1><p class="muted">Privacy-friendly public traffic. No visitor IP addresses are stored.</p></div></div>
<?php if(!$tableReady): ?><div class="notice warning"><strong>Analytics table is not ready.</strong><p>Run the current database-update.sql once. The public site will continue working normally until then.</p></div><?php else: ?>
<div class="stat-grid analytics-stat-grid">
<?php foreach ([['Visits today',$metrics['today']],['Unique today',$metrics['today_unique']],['Visits yesterday',$metrics['yesterday']],['Last 7 days',$metrics['week']],['Total visits',$metrics['total']],['Unique visitors',$metrics['unique_total']]] as $m): ?>
<div class="stat"><span><?=h($m[0])?></span><strong><?=number_format((int)$m[1])?></strong></div>
<?php endforeach; ?></div>
<section class="panel"><div class="section-heading"><div><p class="eyebrow">HISTORY</p><h2>Daily traffic</h2><p>Hours shown in Honduras time (<?=h($tzName)?>).</p></div></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Visits</th><th>Unique</th><th>First visit</th><th>Last visit</th></tr></thead><tbody>
<?php foreach($history as $row): ?><tr><td><?=h($row['visit_date'])?></td><td><?=number_format((int)$row['visits'])?></td><td><?=number_format((int)$row['unique_visitors'])?></td><td><?=h(local_analytics_time($row['first_visit'],$tz))?></td><td><?=h(local_analytics_time($row['last_visit'],$tz))?></td></tr><?php endforeach; ?>
<?php if(!$history): ?><tr><td colspan="5">No visits recorded yet.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel"><div class="section-heading"><div><p class="eyebrow">RECENT</p><h2>Recent public visits</h2></div></div><div class="analytics-recent-list">
<?php foreach($recent as $row): ?><div class="analytics-recent-item"><strong><?=h($row['path'])?></strong><span><?=h(local_analytics_time($row['visited_at'],$tz))?></span></div><?php endforeach; ?>
<?php if(!$recent): ?><div class="empty-state">No public visits recorded yet.</div><?php endif; ?></div></section>
<?php endif; ?>
<?php require __DIR__.'/_footer.php'; ?>
