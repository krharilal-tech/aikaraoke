<?php
/**
 * Home → current page, plus the matching BreadcrumbList JSON-LD.
 *
 * @var array{name: string, path: string} $page
 */
$trail = [
    ['name' => 'Home', 'path' => '/'],
    ['name' => $page['name'], 'path' => $page['path']],
];
?>
<nav aria-label="Breadcrumb" class="seo-breadcrumb mb-3">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="<?= e(base_url('/')) ?>">Home</a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e($page['name']) ?></li>
  </ol>
</nav>
<?= \App\Services\SeoSchema::script(\App\Services\SeoSchema::breadcrumbs($trail)) ?>
