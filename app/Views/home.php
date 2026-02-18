<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'ValoraNL') ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<!-- Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- ═══ HERO HEADER ═══ -->
<section class="vn-home-hero">
    <div class="container">
        <div class="vn-hero-inner">

            <!-- Izquierda: logo + copy -->
            <div class="vn-hero-brand">
                <div class="vn-hero-copy">
                    <h1 class="vn-hero-title">Estima el valor de tu inmueble</h1>
                    <p class="vn-hero-subtitle">Obtiene un estimado comercial al instante, basado en comparables reales de Nuevo León.</p>
                </div>
            </div>

            <!-- Derecha: stats decorativas -->
            <div class="vn-hero-stats">
                <div class="vn-hero-stat">
                    <span class="vn-hero-stat__num">+8K</span>
                    <span class="vn-hero-stat__label">Compa&shy;rables</span>
                </div>
                <div class="vn-hero-stat">
                    <span class="vn-hero-stat__num">51</span>
                    <span class="vn-hero-stat__label">Munici&shy;pios</span>
                </div>
                <div class="vn-hero-stat">
                    <span class="vn-hero-stat__num">~2s</span>
                    <span class="vn-hero-stat__label">Tiempo resp.</span>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- ═══ MAIN FORM SECTION ═══ -->
<section class="vn-main-section">
    <div class="container-fluid px-0 px-md-3">
        <div class="vn-layout-grid">

            <!-- ── LEFT: Map ── -->
            <div class="vn-map-col">
                <div class="vn-map-wrapper">
                    <div class="vn-map-label">
                        <i class="fa-solid fa-location-dot"></i> Ubica tu inmueble en el mapa
                    </div>
                    <!-- Location inputs above map -->
                    <div class="vn-location-row">
                        <div class="vn-loc-field">
                            <label class="vn-field-label">Área metropolitana</label>
                            <select id="area_metro" class="vn-select2" style="width:100%">
                                <option value="">Selecciona...</option>
                                <option value="zmm" selected>Zona Metropolitana de Monterrey</option>
                            </select>
                        </div>
                        <div class="vn-loc-field">
                            <label class="vn-field-label">Municipio</label>
                            <select id="municipality_select" class="vn-select2" style="width:100%">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                        <div class="vn-loc-field">
                            <label class="vn-field-label">Código postal</label>
                            <input type="text" id="zip_code" class="vn-text-input" placeholder="Ej. 64000" maxlength="5">
                        </div>
                    </div>
                    <div class="vn-location-row vn-location-row--2col">
                        <div class="vn-loc-field">
                            <label class="vn-field-label">Colonia</label>
                            <select id="colony_select" class="vn-select2" style="width:100%">
                                <option value="">Selecciona...</option>
                            </select>
                        </div>
                        <div class="vn-loc-field">
                            <label class="vn-field-label">Dirección</label>
                            <div class="vn-address-row">
                                <input type="text" id="address" class="vn-text-input" placeholder="Ej. Senda del triunfo 6312">
                                <button type="button" id="geocode-btn" class="vn-icon-btn" title="Buscar en mapa">
                                    <i class="fa-solid fa-magnifying-glass-location"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Leaflet Map -->
                    <div id="vn-leaflet-map"></div>
                    <div id="geocode-status" class="vn-geocode-status" style="display:none;"></div>
                    <input type="hidden" id="lat" name="lat">
                    <input type="hidden" id="lng" name="lng">
                </div>
            </div>

            <!-- ── RIGHT: Property Form ── -->
            <div class="vn-form-col">
                <div class="vn-form-card">

                    <!-- Property type selector -->
                    <div class="vn-section-block">
                        <label class="vn-field-label">Selecciona tu inmueble</label>
                        <select id="property_type_select" class="vn-select2-dark" style="width:100%">
                            <option value="casa" selected>🏠 Casa</option>
                            <option value="departamento">🏢 Departamento</option>
                            <option value="terreno">🌿 Terreno</option>
                        </select>
                    </div>

                    <!-- Describe tu inmueble -->
                    <div class="vn-section-block">
                        <div class="vn-section-title">Describe tu inmueble</div>

                        <!-- Edad -->
                        <div class="vn-field-row">
                            <label class="vn-field-label">Edad en años</label>
                            <select id="age_years" class="vn-select2-dark" style="width:100%">
                                <option value="">Selecciona...</option>
                                <?php for ($i = 0; $i <= 60; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i === 0 ? 'Nuevo (0 años)' : $i . ($i === 1 ? ' año' : ' años') ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- ── Counter Cards ── -->
                        <div class="vn-counters-grid">

                            <!-- Recámaras -->
                            <div class="vn-counter-card">
                                <div class="vn-counter-icon">
                                    <i class="fa-solid fa-bed"></i>
                                </div>
                                <div class="vn-counter-label">Recámaras</div>
                                <div class="vn-counter-controls">
                                    <button type="button" class="vn-counter-btn vn-counter-btn--minus" data-target="bedrooms">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <span class="vn-counter-value" id="bedrooms-display">1</span>
                                    <button type="button" class="vn-counter-btn vn-counter-btn--plus" data-target="bedrooms">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="bedrooms" name="bedrooms" value="1">
                            </div>

                            <!-- Baños completos -->
                            <div class="vn-counter-card">
                                <div class="vn-counter-icon">
                                    <i class="fa-solid fa-shower"></i>
                                </div>
                                <div class="vn-counter-label">Baños completos</div>
                                <div class="vn-counter-controls">
                                    <button type="button" class="vn-counter-btn vn-counter-btn--minus" data-target="bathrooms">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <span class="vn-counter-value" id="bathrooms-display">1</span>
                                    <button type="button" class="vn-counter-btn vn-counter-btn--plus" data-target="bathrooms">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="bathrooms" name="bathrooms" value="1">
                            </div>

                            <!-- Medios baños -->
                            <div class="vn-counter-card">
                                <div class="vn-counter-icon">
                                    <i class="fa-solid fa-toilet"></i>
                                </div>
                                <div class="vn-counter-label">Medios baños</div>
                                <div class="vn-counter-controls">
                                    <button type="button" class="vn-counter-btn vn-counter-btn--minus" data-target="half_bathrooms">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <span class="vn-counter-value" id="half_bathrooms-display">0</span>
                                    <button type="button" class="vn-counter-btn vn-counter-btn--plus" data-target="half_bathrooms">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="half_bathrooms" name="half_bathrooms" value="0">
                            </div>

                            <!-- Estacionamientos -->
                            <div class="vn-counter-card">
                                <div class="vn-counter-icon">
                                    <i class="fa-solid fa-car"></i>
                                </div>
                                <div class="vn-counter-label">Estacionamientos</div>
                                <div class="vn-counter-controls">
                                    <button type="button" class="vn-counter-btn vn-counter-btn--minus" data-target="parking">
                                        <i class="fa-solid fa-minus"></i>
                                    </button>
                                    <span class="vn-counter-value" id="parking-display">0</span>
                                    <button type="button" class="vn-counter-btn vn-counter-btn--plus" data-target="parking">
                                        <i class="fa-solid fa-plus"></i>
                                    </button>
                                </div>
                                <input type="hidden" id="parking" name="parking" value="0">
                            </div>

                        </div><!-- /vn-counters-grid -->

                        <!-- m² row -->
                        <div class="vn-m2-row">
                            <div class="vn-m2-field">
                                <label class="vn-field-label">Construcción m²</label>
                                <input type="number" id="area_construction_m2" name="area_construction_m2" class="vn-text-input vn-text-input--dark" placeholder="Ej. 120" min="1" step="1" required>
                            </div>
                            <div class="vn-m2-field">
                                <label class="vn-field-label">Terreno m²</label>
                                <input type="number" id="area_land_m2" name="area_land_m2" class="vn-text-input vn-text-input--dark" placeholder="Ej. 200" min="0" step="1">
                            </div>
                        </div>

                        <!-- Conservación -->
                        <div class="vn-field-row">
                            <label class="vn-field-label">Estado de conservación</label>
                            <select id="conservation_level" name="conservation_level" class="vn-select2-dark" style="width:100%">
                                <option value="">Se infiere de la edad</option>
                                <option value="10">10 — Nuevo</option>
                                <option value="9">9 — Excelente</option>
                                <option value="8">8 — Muy bueno</option>
                                <option value="7">7 — Bueno</option>
                                <option value="6">6 — Regular bueno</option>
                                <option value="5">5 — Regular</option>
                                <option value="4">4 — Regular malo</option>
                                <option value="3">3 — Malo</option>
                                <option value="2">2 — Muy malo</option>
                                <option value="1">1 — Ruina</option>
                            </select>
                        </div>

                    </div><!-- /vn-section-block -->

                    <!-- Valores avanzados (opcionales) -->
                    <div class="vn-section-block vn-advanced-block">
                        <button type="button" class="vn-advanced-toggle" id="toggle-advanced">
                            <i class="fa-solid fa-sliders me-2"></i> Opciones avanzadas
                            <i class="fa-solid fa-chevron-down vn-chevron ms-auto"></i>
                        </button>
                        <div id="advanced-fields" style="display:none;" class="vn-advanced-fields">
                            <div class="vn-m2-row mt-3">
                                <div class="vn-m2-field">
                                    <label class="vn-field-label">Valor unit. construcción ($/m²)</label>
                                    <input type="number" id="construction_unit_value" name="construction_unit_value" class="vn-text-input vn-text-input--dark" placeholder="Ej. 7500" min="0" max="50000" step="1">
                                </div>
                                <div class="vn-m2-field">
                                    <label class="vn-field-label">Equipamiento (MXN)</label>
                                    <input type="number" id="equipment_value" name="equipment_value" class="vn-text-input vn-text-input--dark" placeholder="Ej. 20000" min="0" max="5000000" step="1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Errors -->
                    <div id="valuation-form-errors" class="vn-form-errors d-none"></div>

                    <!-- Submit -->
                    <button type="button" id="valuation-submit" class="vn-submit-btn">
                        <i class="fa-solid fa-calculator me-2"></i> Continuar
                    </button>

                </div><!-- /vn-form-card -->
            </div><!-- /vn-form-col -->

        </div><!-- /vn-layout-grid -->
    </div>
</section>

<!-- ═══════════════════════════════════════════════
     RESULTS SECTION
     ═══════════════════════════════════════════════ -->
<section class="cs_p96_66 valuation-page" id="valuation-results-section" style="display:none;">
    <div class="container">

        <!-- Result hero -->
        <div class="vn-result-hero wow fadeIn" data-wow-duration="0.6s">
            <div class="vn-result-hero__label">Valor estimado de mercado</div>
            <div class="vn-result-hero__value">
                <span class="vn-result-hero__currency" id="result-currency-prefix">$</span><span class="odometer" id="result-estimated-value">0</span>
            </div>
            <p class="vn-result-hero__message" id="valuation-result-message"></p>

            <!-- Range bar -->
            <div class="vn-range-bar">
                <div class="vn-range-bar__fill" id="range-bar-fill" style="width:100%;"></div>
                <div class="vn-range-bar__marker" id="range-bar-marker" style="left:50%;"></div>
            </div>
            <div class="vn-range-bar__labels">
                <span id="range-label-low">—</span>
                <span id="range-label-high">—</span>
            </div>

            <!-- Confidence badge -->
            <div class="mt-3">
                <span class="vn-confidence-badge" id="result-confidence-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span id="result-confidence">— / 100</span>
                </span>
            </div>
        </div>

        <!-- AI powered banner -->
        <div id="ai-powered-banner" class="alert alert-warning mb-4" style="display:none;">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
                <div>
                    <strong>⚠️ Estimación de apoyo con IA (confianza baja)</strong><br>
                    <span id="ai-powered-message">No se encontraron comparables locales suficientes; este rango es orientativo y debe validarse con avalúo profesional.</span>
                </div>
                <span class="badge text-bg-dark">✨ IA para acelerar tu primer rango de precio</span>
            </div>
        </div>

        <!-- Dual results comparison -->
        <div id="dual-results-comparison" class="mb-4" style="display:none;">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="dual-result-card dual-result-original">
                        <small class="text-muted" style="color:#dbe7ec !important;">Resultado algoritmo original (sin IA)</small>
                        <h4 id="result-original-value" class="mb-1">—</h4>
                        <small id="result-original-range" class="d-block" style="color:#dbe7ec !important;">Rango: —</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="dual-result-card dual-result-ai">
                        <small class="text-muted" style="color:#dbe7ec !important;">Resultado algoritmo + OpenAI (PPU de apoyo)</small>
                        <h4 id="result-ai-value" class="mb-1">—</h4>
                        <small id="result-ai-range" class="d-block" style="color:#dbe7ec !important;">Rango: —</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric cards -->
        <div class="vn-metrics-row wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.1s">
            <div class="vn-metric-card">
                <div class="vn-metric-card__icon"><i class="fa-solid fa-coins"></i></div>
                <div class="vn-metric-card__label">Valor estimado</div>
                <div class="vn-metric-card__value" id="metric-estimated">—</div>
            </div>
            <div class="vn-metric-card">
                <div class="vn-metric-card__icon"><i class="fa-solid fa-arrow-down"></i></div>
                <div class="vn-metric-card__label">Rango bajo (-10%)</div>
                <div class="vn-metric-card__value" id="metric-low">—</div>
            </div>
            <div class="vn-metric-card">
                <div class="vn-metric-card__icon"><i class="fa-solid fa-arrow-up"></i></div>
                <div class="vn-metric-card__label">Rango alto (+10%)</div>
                <div class="vn-metric-card__value" id="metric-high">—</div>
            </div>
            <div class="vn-metric-card">
                <div class="vn-metric-card__icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="vn-metric-card__label">Confianza</div>
                <div class="vn-metric-card__value" id="metric-confidence">—</div>
                <div class="vn-confidence-bar">
                    <div class="vn-confidence-bar__fill" id="confidence-bar-fill" style="width:0%;"></div>
                </div>
            </div>
        </div>

        <!-- Confidence reasons -->
        <div class="mb-4 wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.15s">
            <h3 class="vn-section-heading"><i class="fa-solid fa-circle-info me-2"></i>Explicación de confianza</h3>
            <ul class="vn-confidence-reasons" id="result-confidence-reasons"></ul>
        </div>

        <!-- Residual breakdown -->
        <div id="residual-breakdown-section" class="mb-4 wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.2s" style="display:none;">
            <h3 class="vn-section-heading"><i class="fa-solid fa-layer-group me-2"></i>Desglose residual</h3>
            <div class="vn-residual-row">
                <div class="vn-residual-card">
                    <div class="vn-residual-card__icon"><i class="fa-solid fa-building"></i></div>
                    <div class="vn-residual-card__label">V. Construcciones</div>
                    <div class="vn-residual-card__value" id="residual-construction">—</div>
                </div>
                <div class="vn-residual-card">
                    <div class="vn-residual-card__icon"><i class="fa-solid fa-couch"></i></div>
                    <div class="vn-residual-card__label">V. Equipamiento</div>
                    <div class="vn-residual-card__value" id="residual-equipment">—</div>
                </div>
                <div class="vn-residual-card">
                    <div class="vn-residual-card__icon"><i class="fa-solid fa-mountain-sun"></i></div>
                    <div class="vn-residual-card__label">V. Terreno</div>
                    <div class="vn-residual-card__value" id="residual-land">—</div>
                </div>
                <div class="vn-residual-card">
                    <div class="vn-residual-card__icon"><i class="fa-solid fa-chart-simple"></i></div>
                    <div class="vn-residual-card__label">V.U. Terreno ($/m²)</div>
                    <div class="vn-residual-card__value" id="residual-land-unit">—</div>
                </div>
            </div>
        </div>

        <!-- Technical breakdown accordion -->
        <div class="mb-4 wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.25s">
            <h3 class="vn-section-heading"><i class="fa-solid fa-microscope me-2"></i>¿Cómo obtuvimos este resultado?</h3>
            <div class="accordion vn-accordion" id="breakdown-accordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bd-method">
                            <i class="fa-solid fa-flask me-2"></i> Método y alcance
                        </button>
                    </h2>
                    <div id="bd-method" class="accordion-collapse collapse" data-bs-parent="#breakdown-accordion">
                        <div class="accordion-body">
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">Método</span><span class="vn-breakdown-value" id="calc-method">—</span></div>
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">Zona considerada</span><span class="vn-breakdown-value" id="calc-scope">—</span></div>
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">Propiedades encontradas / usadas</span><span class="vn-breakdown-value" id="calc-counts">—</span></div>
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">¿Se usaron propiedades de la base?</span><span class="vn-breakdown-value" id="calc-db-usage">—</span></div>
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">Origen de los datos</span><span class="vn-breakdown-value" id="calc-data-origin">—</span></div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bd-ppu">
                            <i class="fa-solid fa-calculator me-2"></i> PPU y factores
                        </button>
                    </h2>
                    <div id="bd-ppu" class="accordion-collapse collapse" data-bs-parent="#breakdown-accordion">
                        <div class="accordion-body">
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">PPU promedio homologado</span><span class="vn-breakdown-value" id="calc-ppu-weighted">—</span></div>
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">PPU aplicado (redondeado)</span><span class="vn-breakdown-value" id="calc-ppu-adjusted">—</span></div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bd-ai">
                            <i class="fa-solid fa-robot me-2"></i> Consulta IA
                        </button>
                    </h2>
                    <div id="bd-ai" class="accordion-collapse collapse" data-bs-parent="#breakdown-accordion">
                        <div class="accordion-body">
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">Datos enviados a IA</span><span class="vn-breakdown-value" id="calc-ai-inputs">—</span></div>
                            <div class="vn-breakdown-item"><span class="vn-breakdown-label">Estado de consulta IA</span><span class="vn-breakdown-value" id="calc-ai-status">—</span></div>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bd-explanation">
                            <i class="fa-solid fa-book-open me-2"></i> Explicación sencilla
                        </button>
                    </h2>
                    <div id="bd-explanation" class="accordion-collapse collapse" data-bs-parent="#breakdown-accordion">
                        <div class="accordion-body">
                            <ul id="calc-formulas"></ul>
                        </div>
                    </div>
                </div>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#bd-advisor">
                            <i class="fa-solid fa-user-tie me-2"></i> Detalle para asesores
                        </button>
                    </h2>
                    <div id="bd-advisor" class="accordion-collapse collapse" data-bs-parent="#breakdown-accordion">
                        <div class="accordion-body">
                            <ul id="calc-advisor-details"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparables section -->
        <div class="mb-4 wow fadeInUp" data-wow-duration="0.5s" data-wow-delay="0.3s" id="comparables-section">
            <h3 class="vn-section-heading"><i class="fa-solid fa-table-list me-2"></i>Top comparables</h3>
            <div class="table-responsive d-none d-md-block">
                <table class="table vn-comparables-table" id="comparables-table">
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
            <div class="vn-comparable-cards d-md-none" id="comparables-cards"></div>
        </div>

    </div>
</section>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    window.ValoraNLEstimateConfig = {
        estimateUrl: <?= json_encode(url_to('valuation.estimate')) ?>,
        chartisBaseUrl: 'https://chartismx.com/api',
        nlStateId: '19',
        conservationInference: <?= json_encode((new \Config\Valuation())->conservationInferenceByAge) ?>,
    };
</script>
<script src="<?= base_url('assets/js/home-valuation.js') ?>"></script>
<script src="<?= base_url('assets/js/home-new.js') ?>"></script>
<?= $this->endSection() ?>