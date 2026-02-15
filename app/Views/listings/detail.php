<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'Detalle de propiedad') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $images = json_decode($listing['images_json'] ?? '[]', true) ?: [];
    $coverImage = $images[0] ?? base_url('assets/img/single_property_1.jpg');
    $price = isset($listing['price_amount']) ? number_format((float) $listing['price_amount'], 0) : 'N/D';
    $marketLow = isset($listing['estimated_low']) && $listing['estimated_low'] !== null ? number_format((float) $listing['estimated_low'], 0) : 'N/D';
    $marketHigh = isset($listing['estimated_high']) && $listing['estimated_high'] !== null ? number_format((float) $listing['estimated_high'], 0) : 'N/D';
    $pricePerM2 = isset($listing['price_per_m2']) && $listing['price_per_m2'] !== null ? number_format((float) $listing['price_per_m2'], 0) : 'N/D';
?>
<section class="cs_page_heading cs_center cs_bg_filed" data-src="<?= esc($coverImage) ?>">
    <div class="container">
        <h1 class="cs_white_color cs_fs_50"><?= esc($listing['title'] ?? 'Propiedad') ?></h1>
    </div>
</section>

<section class="cs_p96_66">
    <div class="container">
        <a href="<?= url_to('home.index') ?>" class="cs_btn cs_style_1 cs_type_1 cs_mb_24">
            <span class="cs_btn_text">← Volver al listado</span>
        </a>

        <div class="row cs_gap_y_24">
            <div class="col-lg-8">
                <div class="cs_card cs_style_1">
                    <div class="cs_card_thumb">
                        <img src="<?= esc($coverImage) ?>" alt="<?= esc($listing['title'] ?? 'Propiedad') ?>">
                    </div>
                    <div class="cs_card_info">
                        <h2 class="cs_fs_32"><?= esc($listing['title'] ?? 'Propiedad') ?></h2>
                        <p><?= esc($listing['description'] ?? 'Sin descripción disponible') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="cs_card cs_style_1">
                    <div class="cs_card_info">
                        <h3 class="cs_fs_24">Resumen de mercado</h3>
                        <ul class="list-unstyled mb-3">
                            <li><strong>Precio listado:</strong> <?= esc($listing['currency'] ?? 'MXN') ?> <?= esc($price) ?></li>
                            <li><strong>Precio por m²:</strong> <?= esc($pricePerM2) ?></li>
                            <li><strong>Rango estimado:</strong> <?= esc($marketLow) ?> - <?= esc($marketHigh) ?></li>
                            <li><strong>Estatus:</strong> <?= esc($listing['status'] ?? 'unknown') ?></li>
                        </ul>

                        <h4 class="cs_fs_20">Ficha técnica</h4>
                        <ul class="list-unstyled mb-0">
                            <li><strong>Tipo:</strong> <?= esc($listing['property_type'] ?? 'N/D') ?></li>
                            <li><strong>Recámaras:</strong> <?= esc((string) ($listing['bedrooms'] ?? 'N/D')) ?></li>
                            <li><strong>Baños:</strong> <?= esc((string) ($listing['bathrooms'] ?? 'N/D')) ?></li>
                            <li><strong>Estacionamientos:</strong> <?= esc((string) ($listing['parking'] ?? 'N/D')) ?></li>
                            <li><strong>Construcción:</strong> <?= esc((string) ($listing['area_construction_m2'] ?? 'N/D')) ?> m²</li>
                            <li><strong>Terreno:</strong> <?= esc((string) ($listing['area_land_m2'] ?? 'N/D')) ?> m²</li>
                            <li><strong>Ubicación:</strong> <?= esc(trim(($listing['colony'] ?? '') . ', ' . ($listing['municipality'] ?? ''), ', ')) ?></li>
                            <li><strong>Fuente:</strong> <?= esc($listing['source_name'] ?? 'N/D') ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
