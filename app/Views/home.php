<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'ValoraNL') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="cs_gray_bg cs_p96_66 valuation-page">
    <div class="container">
        <div class="row cs_gap_y_24">
            <div class="col-lg-8">
                <div class="cs_card cs_style_1 p-4 valuation-card" id="valuation-form">
                    <h1 class="cs_fs_42 mb-2">Calculadora de valuación inmobiliaria</h1>
                    <p class="mb-4">Completa los datos de la propiedad y recibe un valor estimado por comparables reales en Nuevo León.</p>

                    <form id="valuation-form-element" method="post" action="<?= url_to('valuation.estimate') ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" id="property_type" name="property_type" value="casa">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tipo de propiedad</label>
                                <input type="text" class="form-control" value="Casa" readonly>
                            </div>
                            <div class="col-md-6">
                                <label for="municipality" class="form-label">Municipio *</label>
                                <input id="municipality" name="municipality" type="text" class="form-control" list="municipality-options" placeholder="Ej. Monterrey" required>
                                <datalist id="municipality-options"></datalist>
                            </div>
                            <div class="col-md-12">
                                <label for="colony" class="form-label">Colonia *</label>
                                <input id="colony" name="colony" type="text" class="form-control" list="colony-options" placeholder="Ej. Cumbres 2do Sector" required>
                                <datalist id="colony-options"></datalist>
                                <small class="text-muted">Puedes escribir cualquier colonia; también se sugieren colonias del municipio elegido.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="area_construction_m2" class="form-label">m² construcción *</label>
                                <input id="area_construction_m2" name="area_construction_m2" type="number" min="1" step="0.01" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="area_land_m2" class="form-label">m² terreno</label>
                                <input id="area_land_m2" name="area_land_m2" type="number" min="0" step="0.01" class="form-control" placeholder="Mejora precisión del cálculo">
                            </div>
                            <div class="col-md-6">
                                <label for="age_years" class="form-label">Edad del inmueble (años) *</label>
                                <input id="age_years" name="age_years" type="number" min="0" max="100" step="1" class="form-control" required placeholder="Ej. 15">
                            </div>
                            <div class="col-md-6">
                                <label for="conservation_level" class="form-label">Estado de conservación *</label>
                                <select id="conservation_level" name="conservation_level" class="form-select" required>
                                    <option value="">Se infiere de la edad</option>
                                    <option value="10">10 - Nuevo</option>
                                    <option value="9">9 - Excelente</option>
                                    <option value="8">8 - Muy bueno</option>
                                    <option value="7">7 - Bueno</option>
                                    <option value="6">6 - Regular bueno</option>
                                    <option value="5">5 - Regular</option>
                                    <option value="4">4 - Regular malo</option>
                                    <option value="3">3 - Malo</option>
                                    <option value="2">2 - Muy malo</option>
                                    <option value="1">1 - Ruina</option>
                                </select>
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

                        <div class="mt-3">
                            <a id="advanced-fields-toggle" class="text-decoration-none" data-bs-toggle="collapse" href="#advanced-fields" role="button" aria-expanded="false" aria-controls="advanced-fields">
                                <i class="fa-solid fa-sliders me-1"></i> Parámetros avanzados (opcional)
                            </a>
                            <div class="collapse mt-2" id="advanced-fields">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="construction_unit_value" class="form-label">Valor unitario construcción ($/m²)</label>
                                        <input id="construction_unit_value" name="construction_unit_value" type="number" min="0" max="50000" step="0.01" class="form-control" placeholder="Ej. 7500">
                                        <small class="text-muted">Permite calcular el desglose residual (terreno vs. construcción).</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="equipment_value" class="form-label">Equipamiento (MXN)</label>
                                        <input id="equipment_value" name="equipment_value" type="number" min="0" max="5000000" step="0.01" class="form-control" placeholder="Ej. 20000">
                                        <small class="text-muted">Valor del equipamiento adicional del inmueble.</small>
                                    </div>
                                </div>
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

            <div class="col-lg-4">
                <div class="cs_card cs_style_1 p-4 h-100 valuation-card valuation-help-card">
                    <h2 class="cs_fs_29 mb-3">¿Qué recibirás?</h2>
                    <ul class="mb-4">
                        <li>Valor estimado en MXN.</li>
                        <li>Rango bajo/alto (±10%) según metodología del Excel de referencia.</li>
                        <li>Nivel de confianza (0–100) con explicación.</li>
                        <li>Top comparables con factores de homologación.</li>
                        <li>Desglose residual (si proporcionas valor unitario de construcción).</li>
                    </ul>
                    <p class="mb-0"><strong>Disclaimer:</strong> Esta estimación es referencial y no sustituye un avalúo profesional certificado.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cs_p96_66 valuation-page" id="valuation-results-section" style="display:none;">
    <div class="container">
        <div class="cs_card cs_style_1 p-4 valuation-card valuation-results-card">
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
                        <small class="text-muted">Rango bajo (-10%)</small>
                        <h4 id="result-estimated-low" class="mb-0">—</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted">Rango alto (+10%)</small>
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


            <div id="ai-powered-banner" class="alert alert-warning mb-4" style="display:none;">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
                    <div>
                        <strong>⚠️ Estimación de apoyo con IA (confianza baja)</strong><br>
                        <span id="ai-powered-message">No se encontraron comparables locales suficientes; este rango es orientativo y debe validarse con avalúo profesional.</span>
                    </div>
                    <span class="badge text-bg-dark">✨ IA para acelerar tu primer rango de precio</span>
                </div>
            </div>

            <div class="mb-4">
                <strong>Explicación:</strong>
                <ul id="result-confidence-reasons" class="mb-0"></ul>
            </div>

            <div class="mb-4" id="residual-breakdown-section" style="display:none;">
                <h3 class="cs_fs_29 mb-3">Desglose residual</h3>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">V. Construcciones</small>
                            <h5 id="residual-construction" class="mb-0">—</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">V. Equipamiento</small>
                            <h5 id="residual-equipment" class="mb-0">—</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">V. Terreno</small>
                            <h5 id="residual-land" class="mb-0">—</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted">V.U. Terreno ($/m²)</small>
                            <h5 id="residual-land-unit" class="mb-0">—</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <h3 class="cs_fs_29 mb-3">¿Cómo obtuvimos este resultado?</h3>
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="calculation-breakdown-table">
                        <tbody>
                        <tr>
                            <th>Método</th>
                            <td id="calc-method">—</td>
                        </tr>
                        <tr>
                            <th>Zona que se tomó en cuenta</th>
                            <td id="calc-scope">—</td>
                        </tr>
                        <tr>
                            <th>Propiedades encontradas / usadas</th>
                            <td id="calc-counts">—</td>
                        </tr>
                        <tr>
                            <th>¿Se usaron propiedades de la base para calcular?</th>
                            <td id="calc-db-usage">—</td>
                        </tr>
                        <tr>
                            <th>Origen de los datos usados</th>
                            <td id="calc-data-origin">—</td>
                        </tr>
                        <tr>
                            <th>Estado de consulta IA</th>
                            <td id="calc-ai-status">—</td>
                        </tr>
                        <tr>
                            <th>PPU promedio homologado</th>
                            <td id="calc-ppu-weighted">—</td>
                        </tr>
                        <tr>
                            <th>PPU aplicado (redondeado a decenas)</th>
                            <td id="calc-ppu-adjusted">—</td>
                        </tr>
                        <tr>
                            <th>Explicación sencilla</th>
                            <td>
                                <ul class="mb-0" id="calc-formulas"></ul>
                            </td>
                        </tr>
                        <tr>
                            <th>Detalle para asesores inmobiliarios</th>
                            <td>
                                <ul class="mb-0" id="calc-advisor-details"></ul>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
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
                        <th>$/m² Homol.</th>
                        <th>FRe</th>
                        <th>Ubicación</th>
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
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
<script>
    window.ValoraNLEstimateConfig = {
        estimateUrl: <?= json_encode(url_to('valuation.estimate')) ?>,
        chartisBaseUrl: 'https://chartismx.com/api',
        nlStateId: '19',
        conservationInference: <?= json_encode((new \Config\Valuation())->conservationInferenceByAge) ?>,
    };
</script>
<script src="<?= base_url('assets/js/home-valuation.js') ?>"></script>
<?= $this->endSection() ?>
