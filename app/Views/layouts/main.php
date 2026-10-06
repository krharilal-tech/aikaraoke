<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-LJ1GP9EZBJ"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-LJ1GP9EZBJ');
</script>
<?php $documentTitle = $metaTitle ?? ((isset($pageTitle) ? $pageTitle . ' — ' : '') . config('app.name')); ?>
<title><?= e($documentTitle) ?></title>
<?php if (isset($metaDescription)): ?>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<?php if (isset($canonicalPath)): ?>
<?php $canonicalUrl = base_url($canonicalPath); ?>
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(config('seo_pages.brand')) ?>">
<meta property="og:title" content="<?= e($documentTitle) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php if (isset($metaDescription)): ?>
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<?php if (isset($ogImage)): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($documentTitle) ?>">
<?php if (isset($metaDescription)): ?>
<meta name="twitter:description" content="<?= e($metaDescription) ?>">
<?php endif; ?>
<?php endif; ?>
<?php foreach ($structuredData ?? [] as $schema): ?>
<?= \App\Services\SeoSchema::script($schema) ?>

<?php endforeach; ?>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(base_url()) ?>">
<meta name="auth-status" content="<?= \App\Core\Auth::check() ? '1' : '0' ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<?= partial('partials/navbar') ?>

<?php if (!app_is_installed()): ?>
<div class="alert alert-warning rounded-0 text-center mb-0 py-2" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-1"></i>
  AI Karaoke Maker is not installed yet.
  <a href="<?= e(base_url('install')) ?>" class="alert-link">Run the install wizard</a> to create the database and configuration.
</div>
<?php endif; ?>

<main class="app-main">
<?= $content ?>
</main>

<?= partial('partials/footer') ?>

<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (isset($pageScript)): ?>
<script src="<?= e(asset($pageScript)) ?>"></script>
<?php endif; ?>
</body>
</html>
