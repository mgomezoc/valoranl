<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'ValoraNL') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="cs_hero cs_style_1">
    <div class="container">
        <div class="cs_hero_content_wrapper cs_center_column cs_bg_filed cs_radius_25" data-src="<?= base_url('assets/img/hero_img_2.jpg') ?>">
            <div class="cs_hero_text text-center">
                <h1 class="cs_hero_title cs_fs_64 cs_mb_34">Inteligencia inmobiliaria para <span class="cs_accent_color">Nuevo León</span></h1>
                <p class="cs_fs_24 mb-0">Concentramos listings, limpiamos datos y estimamos valor de mercado por comparables.</p>
            </div>
        </div>

        <div class="row cs_gap_y_24 mt-4">
            <div class="col-lg-3 col-sm-6">
                <div class="cs_iconbox cs_style_1 cs_white_bg cs_radius_15 p-3 h-100">
                    <h3 class="cs_fs_38 mb-1"><?= esc(number_format((int) ($marketStats['totalListings'] ?? 0))) ?></h3>
                    <p class="mb-0">Listings analizados</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="cs_iconbox cs_style_1 cs_white_bg cs_radius_15 p-3 h-100">
                    <h3 class="cs_fs_38 mb-1">$<?= esc(number_format((float) ($marketStats['medianPrice'] ?? 0), 0)) ?></h3>
                    <p class="mb-0">Mediana de precio</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="cs_iconbox cs_style_1 cs_white_bg cs_radius_15 p-3 h-100">
                    <h3 class="cs_fs_38 mb-1">$<?= esc(number_format((float) ($marketStats['medianPpu'] ?? 0), 0)) ?></h3>
                    <p class="mb-0">Mediana por m²</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="cs_iconbox cs_style_1 cs_white_bg cs_radius_15 p-3 h-100">
                    <h3 class="cs_fs_38 mb-1"><?= esc(number_format((float) ($marketStats['activeRate'] ?? 0), 1)) ?>%</h3>
                    <p class="mb-0">Inventario activo</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cs_gray_bg cs_p96_66">
    <div class="container">
        <div class="cs_section_heading cs_style_1 cs_mb_47">
            <div class="cs_section_heading_left">
                <h2 class="cs_section_title cs_fs_50 mb-0">Propiedades destacadas</h2>
                <p class="mb-0">Zona con mayor actividad: <strong><?= esc($marketStats['topMunicipality'] ?? 'N/D') ?></strong> (<?= esc((string) ($marketStats['topMunicipalityCount'] ?? 0)) ?> publicaciones)</p>
            </div>
        </div>

        <div class="row cs_gap_y_24">
            <?php if (empty($cards)): ?>
                <div class="col-12">
                    <div class="alert alert-info mb-0">No hay propiedades para mostrar todavía.</div>
                </div>
            <?php endif; ?>

            <?php foreach ($cards as $card): ?>
                <div class="col-lg-4 col-md-6">
                    <article class="cs_card cs_style_1 h-100">
                        <a href="<?= url_to('listing.show', (int) $card['id']) ?>" class="cs_card_thumb d-block">
                            <img src="<?= esc($card['coverImage']) ?>" alt="<?= esc($card['title']) ?>">
                        </a>
                        <div class="cs_card_info">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-dark"><?= esc($card['marketTag']) ?></span>
                                <span class="fw-semibold"><?= esc($card['propertyType']) ?></span>
                            </div>
                            <h2 class="cs_card_title cs_fs_24 mb-2">
                                <a href="<?= url_to('listing.show', (int) $card['id']) ?>"><?= esc($card['title']) ?></a>
                            </h2>
                            <p class="mb-2"><strong>Ubicación:</strong> <?= esc($card['location']) ?></p>
                            <p class="mb-2"><strong>Precio:</strong> <?= esc($card['currency']) ?> <?= esc(number_format((float) $card['price'], 0)) ?></p>
                            <p class="mb-2"><strong>Precio/m²:</strong> <?= $card['pricePerM2'] ? esc(number_format((float) $card['pricePerM2'], 0)) : 'N/D' ?></p>
                            <p class="mb-2"><strong>Características:</strong>
                                <?= esc((string) ($card['bedrooms'] ?? 'N/D')) ?> rec ·
                                <?= esc((string) ($card['bathrooms'] ?? 'N/D')) ?> baños ·
                                <?= esc((string) ($card['parking'] ?? 'N/D')) ?> est.
                            </p>
                            <p class="mb-0"><strong>Fuente:</strong> <?= esc($card['sourceName']) ?></p>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
