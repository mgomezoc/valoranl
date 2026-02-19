<!DOCTYPE html>
<html class="no-js" lang="es">
<head>
    <?php
        $resolvedTitle = trim((string) ($this->renderSection('title') ?: ($pageTitle ?? 'ValoraNL')));
        $resolvedDescription = trim((string) ($metaDescription ?? 'ValoraNL: inteligencia de datos inmobiliarios en Nuevo Leon.'));
        $resolvedCanonical = trim((string) ($canonicalUrl ?? current_url()));
        $resolvedOgType = trim((string) ($ogType ?? 'website'));
        $resolvedOgImage = trim((string) ($ogImage ?? base_url('assets/img/valoranl/logo-valoranl.png')));
        $resolvedRobots = trim((string) ($metaRobots ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'));
    ?>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= esc($resolvedDescription) ?>">
    <meta name="robots" content="<?= esc($resolvedRobots) ?>">
    <link rel="canonical" href="<?= esc($resolvedCanonical) ?>">
    <link rel="alternate" hreflang="es-MX" href="<?= esc($resolvedCanonical) ?>">
    <link rel="alternate" hreflang="x-default" href="<?= esc($resolvedCanonical) ?>">
    <meta property="og:locale" content="es_MX">
    <meta property="og:type" content="<?= esc($resolvedOgType) ?>">
    <meta property="og:title" content="<?= esc($resolvedTitle) ?>">
    <meta property="og:description" content="<?= esc($resolvedDescription) ?>">
    <meta property="og:url" content="<?= esc($resolvedCanonical) ?>">
    <meta property="og:image" content="<?= esc($resolvedOgImage) ?>">
    <meta property="og:site_name" content="ValoraNL">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= esc($resolvedTitle) ?>">
    <meta name="twitter:description" content="<?= esc($resolvedDescription) ?>">
    <meta name="twitter:image" content="<?= esc($resolvedOgImage) ?>">
    <link rel="icon" href="<?= base_url('assets/img/icons/favicon.svg') ?>">
    <title><?= esc($resolvedTitle) ?></title>
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/fontawesome.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/slick.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/light-gallery.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/jquery-ui.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/odometer.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/animate.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/valoranl-theme.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/home-new.css') ?>">
    <?php if (isset($schemaJsonLd) && is_array($schemaJsonLd)): ?>
    <script type="application/ld+json"><?= json_encode($schemaJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <?php endif; ?>
    <?= $this->renderSection('head') ?>
    <?= $this->renderSection('styles') ?>
</head>
<body>
    <div class="cs_preloader cs_center">
        <div class="cs_preloader_in cs_center cs_radius_50">
            <span class="cs_center cs_white_bg cs_accent_color">
                <svg width="50" height="50" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M24 7.96075V21.317C23.9993 22.0283 23.7181 22.7104 23.2182 23.2134C22.7182 23.7164 22.0404 23.9993 21.3333 24H16.8889C16.4174 24 15.9652 23.8115 15.6318 23.4761C15.2984 23.1407 15.1111 22.6857 15.1111 22.2113V17.2924C15.1111 17.0552 15.0175 16.8277 14.8508 16.66C14.6841 16.4923 14.458 16.398 14.2222 16.398H9.77778C9.54203 16.398 9.31594 16.4923 9.14924 16.66C8.98254 16.8277 8.88889 17.0552 8.88889 17.2924V22.2113C8.88889 22.6857 8.70159 23.1407 8.36819 23.4761C8.03479 23.8115 7.58261 24 7.11111 24H2.66667C1.95964 23.9993 1.28177 23.7164 0.781828 23.2134C0.281884 22.7104 0.000705969 22.0283 0 21.317V7.96075C0.000665148 7.65188 0.0804379 7.34839 0.231621 7.07957C0.382805 6.81075 0.600296 6.58567 0.863111 6.42605L11.0853 0.255041C11.3617 0.0881572 11.6779 0 12.0002 0C12.3225 0 12.6388 0.0881572 12.9151 0.255041L23.1373 6.42605C23.4001 6.58573 23.6175 6.81083 23.7686 7.07965C23.9197 7.34846 23.9994 7.65192 24 7.96075Z" fill="currentColor" />
                </svg>
            </span>
        </div>
    </div>

    <?= $this->include('partials/navbar') ?>

    <main>
        <?= $this->renderSection('content') ?>
    </main>

    <?= $this->include('partials/footer') ?>

    <button type="button" class="cs_scrolltop_btn cs_center cs_radius_50 cs_white_bg cs_accent_color">
        <svg xmlns="http://www.w3.org/2000/svg" width="2em" height="2em" viewBox="0 0 15 15">
            <path fill="currentColor" fill-rule="evenodd" d="M7.146 2.146a.5.5 0 0 1 .708 0l4 4a.5.5 0 0 1-.708.708L8 3.707V12.5a.5.5 0 0 1-1 0V3.707L3.854 6.854a.5.5 0 1 1-.708-.708z" clip-rule="evenodd" />
        </svg>
    </button>

    <script src="<?= base_url('assets/js/jquery.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/jquery.slick.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/light-gallery.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/jquery-ui.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/odometer.js') ?>"></script>
    <script src="<?= base_url('assets/js/wow.min.js') ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= base_url('assets/js/main.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
