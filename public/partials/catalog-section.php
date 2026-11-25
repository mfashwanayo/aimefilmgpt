<?php
/** Expects $platform ('aimefilms'|'filmsnyarwanda'|'musicspart'). Renders trending + filterable latest grid. */
declare(strict_types=1);
require_once PROJECT_ROOT . '/app/Services/CatalogService.php';
require_once PROJECT_ROOT . '/app/Helpers/catalog.php';

$catalog = new CatalogService(db());
$tab = (string)($_GET['type'] ?? '');
$tabs = CatalogService::TABS[$platform];
if (!in_array($tab, $tabs, true)) $tab = '';
$search = trim((string)($_GET['q'] ?? ''));
$result = $catalog->listing($platform, ['type' => $tab, 'q' => $search], (int)($_GET['page'] ?? 1));
$filtered = $tab !== '' || $search !== '';
$trending = $filtered || $result['page'] > 1 ? [] : $catalog->trending($platform, 6);
$query = array_filter(['type' => $tab, 'q' => $search], fn($v) => $v !== '');
?>
<section class="section" id="catalog">
  <?php if ($trending): ?>
    <div class="section-heading"><div><span class="eyebrow">POPULAR NOW</span><h2>Most <span>watched.</span></h2></div></div>
    <div class="catalog-grid"><?php foreach ($trending as $i) echo render_card($i); ?></div>
    <div style="height:46px"></div>
  <?php endif; ?>

  <div class="section-heading"><div><span class="eyebrow">JUST ADDED</span><h2>Latest <span>releases.</span></h2></div></div>
  <nav class="status-tabs" aria-label="Filter by type">
    <a class="<?= $tab === '' ? 'active' : '' ?>" href="?<?= e(http_build_query(array_filter(['q' => $search]))) ?>#catalog">All</a>
    <?php foreach ($tabs as $t): ?>
      <a class="<?= $tab === $t ? 'active' : '' ?>" href="?<?= e(http_build_query(array_filter(['type' => $t, 'q' => $search]))) ?>#catalog"><?= e(type_label($t)) ?>s</a>
    <?php endforeach; ?>
  </nav>
  <form method="get" action="#catalog" class="catalog-search">
    <?php if ($tab): ?><input type="hidden" name="type" value="<?= e($tab) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search titles, genres or artists…" aria-label="Search">
    <button class="btn btn-small btn-primary" type="submit">Search</button>
  </form>

  <?php if ($result['items']): ?>
    <div class="catalog-grid"><?php foreach ($result['items'] as $i) echo render_card($i); ?></div>
    <?= admin_pagination($query, $result['page'], $result['pages']) ?>
  <?php else: ?>
    <div class="catalog-empty">
      <strong><?= $filtered ? 'Nothing matches your search.' : 'No releases yet.' ?></strong>
      <p><?= $filtered ? 'Try a different word or clear the filters.' : 'Approved content from our artists will appear here as soon as it is published.' ?></p>
      <?php if ($filtered): ?><a class="btn btn-small btn-glass" href="?#catalog">Clear filters</a><?php endif; ?>
    </div>
  <?php endif; ?>
</section>
