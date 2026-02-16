<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'ValoraNL') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="cs_gray_bg cs_p96_66 valuation-page">
    <div class="container">
        <div class="row cs_gap_y_24">
            <!-- Help sidebar (moves to top on mobile via CSS order) -->
            <div class="col-lg-4 vn-help-sidebar d-none d-lg-block">
                <div class="cs_card cs_style_1 p-4 valuation-card vn-help-card">
                    <h2 class="cs_fs_29 mb-3">¿Qué recibirás?</h2>
                    <div class="vn-help-item">
                        <i class="fa-solid fa-coins"></i>
                        <span>Valor estimado en MXN basado en comparables reales.</span>
                    </div>
                    <div class="vn-help-item">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Rango bajo/alto (±10%) según metodología avalúo.</span>
                    </div>
                    <div class="vn-help-item">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Nivel de confianza (0–100) con explicación.</span>
                    </div>
                    <div class="vn-help-item">
                        <i class="fa-solid fa-table-list"></i>
                        <span>Top comparables con factores de homologación.</span>
                    </div>
                    <div class="vn-help-item">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>Desglose residual (si proporcionas valor unitario de construcción).</span>
                    </div>
                    <p class="mb-0 mt-3" style="font-size:0.85rem; opacity:0.7;"><strong>Disclaimer:</strong> Esta estimación es referencial y no sustituye un avalúo profesional certificado.</p>
                </div>
            </div>

            <!-- Help banner for mobile (collapsible) -->
            <div class="col-12 d-lg-none">
                <div class="cs_card cs_style_1 p-3 valuation-card">
                    <a class="vn-coords-toggle w-100 d-flex justify-content-between" data-bs-toggle="collapse" href="#mobile-help-content" role="button" aria-expanded="false">
                        <span><i class="fa-solid fa-circle-info me-2"></i>¿Qué recibirás?</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </a>
                    <div class="collapse mt-3" id="mobile-help-content">
                        <div class="vn-help-item"><i class="fa-solid fa-coins"></i><span>Valor estimado en MXN basado en comparables reales.</span></div>
                        <div class="vn-help-item"><i class="fa-solid fa-chart-line"></i><span>Rango bajo/alto (±10%).</span></div>
                        <div class="vn-help-item"><i class="fa-solid fa-shield-halved"></i><span>Nivel de confianza (0–100).</span></div>
                        <div class="vn-help-item"><i class="fa-solid fa-table-list"></i><span>Top comparables con homologación.</span></div>
                        <p class="mb-0 mt-2" style="font-size:0.82rem; opacity:0.65;"><strong>Disclaimer:</strong> Estimación referencial, no sustituye avalúo profesional.</p>
                    </div>
                </div>
            </div>

            <!-- Form with stepper -->
            <div class="col-lg-8">
                <div class="cs_card cs_style_1 p-4 valuation-card" id="valuation-form">
                    <h1 class="cs_fs_42 mb-2">Calculadora de valuación inmobiliaria</h1>
                    <p class="mb-4">Completa los datos en 3 sencillos pasos y recibe un valor estimado por comparables reales en Nuevo León.</p>

                    <!-- Stepper indicator -->
                    <div class="vn-stepper">
                        <div class="vn-stepper__step active" data-step="1">
                            <div class="vn-stepper__circle">1</div>
                            <div class="vn-stepper__label">Ubicación</div>
                        </div>
                        <div class="vn-stepper__line"></div>
                        <div class="vn-stepper__step" data-step="2">
                            <div class="vn-stepper__circle">2</div>
                            <div class="vn-stepper__label">Características</div>
                        </div>
                        <div class="vn-stepper__line"></div>
                        <div class="vn-stepper__step" data-step="3">
                            <div class="vn-stepper__circle">3</div>
                            <div class="vn-stepper__label">Calcular</div>
                        </div>
                    </div>

                    <form id="valuation-form-element" method="post" action="<?= url_to('valuation.estimate') ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" id="property_type" name="property_type" value="casa">

                        <!-- ═══ STEP 1: Ubicación ═══ -->
                        <div class="vn-step-panel active" id="step-1">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tipo de propiedad</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-house"></i>
                                        <input type="text" class="form-control" value="Casa" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="municipality" class="form-label">Municipio *</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-map-location-dot"></i>
                                        <input id="municipality" name="municipality" type="text" class="form-control" list="municipality-options" placeholder="Ej. Monterrey" required>
                                        <datalist id="municipality-options"></datalist>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="colony" class="form-label">Colonia *</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-location-dot"></i>
                                        <input id="colony" name="colony" type="text" class="form-control" list="colony-options" placeholder="Ej. Cumbres 2do Sector" required>
                                        <datalist id="colony-options"></datalist>
                                    </div>
                                    <small class="text-muted">Puedes escribir cualquier colonia; también se sugieren colonias del municipio elegido.</small>
                                </div>
                                <div class="col-12">
                                    <label for="address" class="form-label">Dirección completa *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid fa-road"></i></span>
                                        <input id="address" name="address" type="text" class="form-control"
                                               placeholder="Ej. Av. Revolución 1200, Cumbres, Monterrey" required>
                                        <button type="button" id="geocode-btn" class="btn vn-btn vn-btn--primary">
                                            <i class="fa-solid fa-magnifying-glass-location"></i> Buscar
                                        </button>
                                    </div>
                                    <small class="text-muted">La dirección se usa para obtener coordenadas precisas automáticamente.</small>
                                    <div id="geocode-status" class="mt-1" style="display:none;"></div>
                                </div>
                                <div class="col-6">
                                    <label for="lat" class="form-label">Latitud *</label>
                                    <input id="lat" name="lat" type="number" step="0.000001" class="form-control"
                                           readonly required placeholder="Auto-detectada">
                                </div>
                                <div class="col-6">
                                    <label for="lng" class="form-label">Longitud *</label>
                                    <input id="lng" name="lng" type="number" step="0.000001" class="form-control"
                                           readonly required placeholder="Auto-detectada">
                                </div>
                                <div class="col-12">
                                    <a href="#" id="manual-coords-toggle" class="vn-coords-toggle">
                                        <i class="fa-solid fa-pen"></i> Ingresar coordenadas manualmente
                                    </a>
                                </div>
                            </div>
                            <div class="vn-step-nav">
                                <div></div>
                                <button type="button" class="vn-btn vn-btn--primary" data-step-next="2">
                                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ═══ STEP 2: Características ═══ -->
                        <div class="vn-step-panel" id="step-2">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="area_construction_m2" class="form-label">m² construcción *</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-ruler-combined"></i>
                                        <input id="area_construction_m2" name="area_construction_m2" type="number" min="1" step="0.01" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="area_land_m2" class="form-label">m² terreno</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-vector-square"></i>
                                        <input id="area_land_m2" name="area_land_m2" type="number" min="0" step="0.01" class="form-control" placeholder="Mejora precisión">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="age_years" class="form-label">Edad del inmueble (años) *</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-calendar-days"></i>
                                        <input id="age_years" name="age_years" type="number" min="0" max="100" step="1" class="form-control" required placeholder="Ej. 15">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="conservation_level" class="form-label">Estado de conservación *</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-star-half-stroke"></i>
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
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="bedrooms" class="form-label"><i class="fa-solid fa-bed me-1"></i> Recámaras</label>
                                    <input id="bedrooms" name="bedrooms" type="number" min="0" step="1" class="form-control">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="bathrooms" class="form-label"><i class="fa-solid fa-bath me-1"></i> Baños</label>
                                    <input id="bathrooms" name="bathrooms" type="number" min="0" step="0.5" class="form-control">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="half_bathrooms" class="form-label"><i class="fa-solid fa-toilet me-1"></i> Medios baños</label>
                                    <input id="half_bathrooms" name="half_bathrooms" type="number" min="0" step="1" class="form-control">
                                </div>
                                <div class="col-6 col-md-3">
                                    <label for="parking" class="form-label"><i class="fa-solid fa-car me-1"></i> Estac.</label>
                                    <input id="parking" name="parking" type="number" min="0" step="1" class="form-control">
                                </div>
                            </div>
                            <div class="vn-step-nav">
                                <button type="button" class="vn-btn vn-btn--secondary" data-step-prev="1">
                                    <i class="fa-solid fa-arrow-left"></i> Anterior
                                </button>
                                <button type="button" class="vn-btn vn-btn--primary" data-step-next="3">
                                    Siguiente <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>

                        <!-- ═══ STEP 3: Avanzado + Calcular ═══ -->
                        <div class="vn-step-panel" id="step-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="construction_unit_value" class="form-label">Valor unitario construcción ($/m²)</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-hammer"></i>
                                        <input id="construction_unit_value" name="construction_unit_value" type="number" min="0" max="50000" step="0.01" class="form-control" placeholder="Ej. 7500">
                                    </div>
                                    <small class="text-muted">Permite calcular el desglose residual (terreno vs. construcción).</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="equipment_value" class="form-label">Equipamiento (MXN)</label>
                                    <div class="vn-input-icon">
                                        <i class="fa-solid fa-couch"></i>
                                        <input id="equipment_value" name="equipment_value" type="number" min="0" max="5000000" step="0.01" class="form-control" placeholder="Ej. 20000">
                                    </div>
                                    <small class="text-muted">Valor del equipamiento adicional del inmueble.</small>
                                </div>
                            </div>

                            <!-- Summary mini card -->
                            <div class="vn-summary-mini mt-4" id="step-summary">
                                <strong style="font-size:0.82rem; text-transform:uppercase; letter-spacing:0.04em; opacity:0.6;">Resumen de tu propiedad</strong>
                                <div class="vn-summary-mini__row mt-2">
                                    <span class="vn-summary-mini__label">Municipio</span>
                                    <span class="vn-summary-mini__value" id="summary-municipality">—</span>
                                </div>
                                <div class="vn-summary-mini__row">
                                    <span class="vn-summary-mini__label">Colonia</span>
                                    <span class="vn-summary-mini__value" id="summary-colony">—</span>
                                </div>
                                <div class="vn-summary-mini__row">
                                    <span class="vn-summary-mini__label">Construcción</span>
                                    <span class="vn-summary-mini__value" id="summary-area">— m²</span>
                                </div>
                                <div class="vn-summary-mini__row">
                                    <span class="vn-summary-mini__label">Edad / Conservación</span>
                                    <span class="vn-summary-mini__value" id="summary-age">—</span>
                                </div>
                            </div>

                            <div id="valuation-form-errors" class="alert alert-danger d-none mt-3 mb-0"></div>

                            <div class="vn-step-nav">
                                <button type="button" class="vn-btn vn-btn--secondary" data-step-prev="2">
                                    <i class="fa-solid fa-arrow-left"></i> Anterior
                                </button>
                                <button id="valuation-submit" type="submit" class="vn-btn vn-btn--primary vn-btn--lg">
                                    <i class="fa-solid fa-calculator"></i> Calcular valuación
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">Método</span>
                                <span class="vn-breakdown-value" id="calc-method">—</span>
                            </div>
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">Zona considerada</span>
                                <span class="vn-breakdown-value" id="calc-scope">—</span>
                            </div>
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">Propiedades encontradas / usadas</span>
                                <span class="vn-breakdown-value" id="calc-counts">—</span>
                            </div>
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">¿Se usaron propiedades de la base?</span>
                                <span class="vn-breakdown-value" id="calc-db-usage">—</span>
                            </div>
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">Origen de los datos</span>
                                <span class="vn-breakdown-value" id="calc-data-origin">—</span>
                            </div>
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
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">PPU promedio homologado</span>
                                <span class="vn-breakdown-value" id="calc-ppu-weighted">—</span>
                            </div>
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">PPU aplicado (redondeado)</span>
                                <span class="vn-breakdown-value" id="calc-ppu-adjusted">—</span>
                            </div>
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
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">Datos enviados a IA</span>
                                <span class="vn-breakdown-value" id="calc-ai-inputs">—</span>
                            </div>
                            <div class="vn-breakdown-item">
                                <span class="vn-breakdown-label">Estado de consulta IA</span>
                                <span class="vn-breakdown-value" id="calc-ai-status">—</span>
                            </div>
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

            <!-- Desktop table -->
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

            <!-- Mobile cards -->
            <div class="vn-comparable-cards d-md-none" id="comparables-cards"></div>
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
