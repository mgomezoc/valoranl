<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'ValoraNL') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="cs_hero cs_style_1">
    <div class="container">
        <div class="cs_hero_content_wrapper cs_center_column cs_bg_filed cs_radius_25" data-src="<?= base_url('assets/img/hero_img_2.jpg') ?>">
            <div class="cs_hero_text text-center">
                <h1 class="cs_hero_title cs_fs_64 cs_mb_34">Calcula el valor estimado de tu propiedad en Nuevo León</h1>
                <p class="cs_fs_24 mb-0">Valuación por comparables reales de mercado: precio estimado, rango y nivel de confianza en segundos.</p>
            </div>
        </div>
    </div>
</section>

<section class="cs_gray_bg cs_p96_66">
    <div class="container">
        <div class="row cs_gap_y_24">
            <div class="col-lg-7">
                <div class="cs_card cs_style_1 p-4">
                    <h2 class="cs_fs_38 mb-3">Calculadora de valuación</h2>
                    <p class="mb-4">Ingresa los datos principales para estimar valor por metodología de comparables y precio por m².</p>

                    <form id="valuation-form" method="post" action="<?= url_to('valuation.estimate') ?>">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="property_type" class="form-label">Tipo de propiedad *</label>
                                <select id="property_type" name="property_type" class="form-select" required>
                                    <option value="">Selecciona...</option>
                                    <?php foreach ($propertyTypes as $propertyType): ?>
                                        <option value="<?= esc($propertyType) ?>"><?= esc(ucfirst($propertyType)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="municipality" class="form-label">Municipio *</label>
                                <select id="municipality" name="municipality" class="form-select" required>
                                    <option value="">Selecciona...</option>
                                    <?php foreach ($municipalities as $municipality): ?>
                                        <option value="<?= esc($municipality) ?>"><?= esc($municipality) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label for="colony" class="form-label">Colonia *</label>
                                <input id="colony" name="colony" type="text" class="form-control" required placeholder="Ej. Cumbres 2do Sector">
                            </div>
                            <div class="col-md-6">
                                <label for="area_construction_m2" class="form-label">m² construcción *</label>
                                <input id="area_construction_m2" name="area_construction_m2" type="number" min="1" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="area_land_m2" class="form-label">m² terreno (opcional)</label>
                                <input id="area_land_m2" name="area_land_m2" type="number" min="0" step="0.01" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="bedrooms" class="form-label">Recámaras</label>
                                <input id="bedrooms" name="bedrooms" type="number" min="0" step="1" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="bathrooms" class="form-label">Baños</label>
                                <input id="bathrooms" name="bathrooms" type="number" min="0" step="0.5" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="half_bathrooms" class="form-label">Medios baños</label>
                                <input id="half_bathrooms" name="half_bathrooms" type="number" min="0" step="1" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label for="parking" class="form-label">Estacionamientos</label>
                                <input id="parking" name="parking" type="number" min="0" step="1" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="lat" class="form-label">Latitud (opcional)</label>
                                <input id="lat" name="lat" type="number" step="0.000001" class="form-control" placeholder="25.6866">
                            </div>
                            <div class="col-md-6">
                                <label for="lng" class="form-label">Longitud (opcional)</label>
                                <input id="lng" name="lng" type="number" step="0.000001" class="form-control" placeholder="-100.3161">
                            </div>
                        </div>

                        <div id="valuation-form-errors" class="alert alert-danger d-none mt-3 mb-0"></div>

                        <button id="valuation-submit" type="submit" class="cs_btn cs_style_1 cs_accent_bg cs_white_color cs_radius_7 mt-4">
                            <span class="cs_btn_icon"><i class="fa-solid fa-calculator"></i></span>
                            <span class="cs_btn_text">Calcular valuación</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="cs_card cs_style_1 p-4 h-100">
                    <h3 class="cs_fs_29 mb-3">¿Qué recibirás?</h3>
                    <ul class="mb-4">
                        <li>Valor estimado en MXN.</li>
                        <li>Rango bajo/alto según dispersión real del mercado.</li>
                        <li>Nivel de confianza (0–100) con explicación.</li>
                        <li>Top comparables para sustentar el cálculo.</li>
                    </ul>
                    <p class="mb-0"><strong>Disclaimer:</strong> Esta estimación es referencial y no sustituye un avalúo profesional certificado.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cs_p96_66" id="valuation-results-section" style="display:none;">
    <div class="container">
        <div class="cs_card cs_style_1 p-4">
            <h2 class="cs_fs_38 mb-3">Resultado de valuación</h2>
            <p id="valuation-result-message" class="mb-4"></p>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Valor estimado</small>
                        <h3 id="result-estimated-value" class="mb-0">—</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Rango bajo</small>
                        <h4 id="result-estimated-low" class="mb-0">—</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Rango alto</small>
                        <h4 id="result-estimated-high" class="mb-0">—</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Confianza</small>
                        <h4 id="result-confidence" class="mb-0">—</h4>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <strong>Explicación:</strong>
                <ul id="result-confidence-reasons" class="mb-0"></ul>
            </div>

            <h3 class="cs_fs_29 mb-3">Top comparables</h3>
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="comparables-table">
                    <thead>
                        <tr>
                            <th>Propiedad</th>
                            <th>Precio</th>
                            <th>m²</th>
                            <th>$/m²</th>
                            <th>Ubicación</th>
                            <th>Score</th>
                            <th>Fuente</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    window.ValoraNLEstimateConfig = {
        estimateUrl: <?= json_encode(url_to('valuation.estimate')) ?>,
    };
</script>
<script src="<?= base_url('assets/js/home-valuation.js') ?>"></script>
<?= $this->endSection() ?>
