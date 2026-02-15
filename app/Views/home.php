<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'ValoraNL') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="cs_page_heading cs_center cs_bg_filed" data-src="<?= base_url('assets/img/page_header_1.jpg') ?>">
    <div class="container">
        <h1 class="cs_white_color cs_fs_67">Propiedades disponibles</h1>
        <p class="cs_white_color mb-0">Base consolidada desde múltiples portales inmobiliarios.</p>
    </div>
</section>

<section class="cs_gray_bg cs_p96_66">
    <div class="container">
        <div class="row cs_gap_y_24">
            <?php if (empty($listings)): ?>
                <div class="col-12">
                    <div class="alert alert-info mb-0">No hay propiedades disponibles en este momento.</div>
                </div>
            <?php endif; ?>

            <?php foreach ($listings as $listing): ?>
                <?php
                    $images = json_decode($listing['images_json'] ?? '[]', true) ?: [];
                    $coverImage = $images[0] ?? base_url('assets/img/property_img_1.jpg');
                    $price = isset($listing['price_amount']) ? number_format((float) $listing['price_amount'], 0) : 'N/D';
                    $location = trim(($listing['colony'] ?? '') . ', ' . ($listing['municipality'] ?? ''), ', ');
                ?>
                <div class="col-lg-4 col-md-6">
                    <article class="cs_card cs_style_1 h-100">
                        <a href="<?= url_to('listing.show', (int) $listing['id']) ?>" class="cs_card_thumb d-block">
                            <img src="<?= esc($coverImage) ?>" alt="<?= esc($listing['title'] ?? 'Propiedad') ?>">
                        </a>
                        <div class="cs_card_info">
                            <h2 class="cs_card_title cs_fs_24 mb-2">
                                <a href="<?= url_to('listing.show', (int) $listing['id']) ?>"><?= esc($listing['title'] ?? 'Propiedad sin título') ?></a>
                            </h2>
                            <p class="mb-2"><strong>Ubicación:</strong> <?= esc($location !== '' ? $location : 'No disponible') ?></p>
                            <p class="mb-2"><strong>Precio:</strong> <?= esc($listing['currency'] ?? 'MXN') ?> <?= esc($price) ?></p>
                            <p class="mb-0"><strong>Fuente:</strong> <?= esc($listing['source_name'] ?? 'No definida') ?></p>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
