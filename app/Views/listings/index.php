<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($pageTitle ?? 'ValoraNL | Propiedades') ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="<?= base_url('assets/css/listings-explorer.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<section class="cs_gray_bg cs_p96_66 listings-page">
    <div class="container">
        <div class="listing-hero">
            <div class="listing-hero__badge">Catalogo inmobiliario</div>
            <h1 class="listing-hero__title">Explorador profesional de propiedades</h1>
            <p class="listing-hero__subtitle">
                Consulta todas las propiedades de la base con filtros avanzados, busqueda inteligente y mapa interactivo.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-xl-3 col-lg-4">
                <aside class="listing-filters">
                    <div class="listing-filters__header">
                        <h2>Filtros</h2>
                        <button type="button" class="vn-btn vn-btn--secondary" id="clear-filters-btn">Limpiar</button>
                    </div>

                    <div class="listing-filters__body">
                        <div class="filter-group">
                            <label for="filter-search">Busqueda</label>
                            <input id="filter-search" type="text" class="form-control" placeholder="Titulo, colonia, calle, id...">
                        </div>

                        <div class="filter-group">
                            <label for="filter-municipality">Municipio</label>
                            <select id="filter-municipality" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach (($filterOptions['municipalities'] ?? []) as $municipality): ?>
                                    <option value="<?= esc($municipality) ?>"><?= esc($municipality) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter-colony">Colonia</label>
                            <select id="filter-colony" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach (($filterOptions['colonies'] ?? []) as $colony): ?>
                                    <option value="<?= esc($colony) ?>"><?= esc($colony) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filter-property-type">Tipo de propiedad</label>
                            <select id="filter-property-type" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach (($filterOptions['property_types'] ?? []) as $propertyType): ?>
                                    <option value="<?= esc($propertyType) ?>"><?= esc($propertyType) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter-status">Estatus</label>
                                <select id="filter-status" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach (($filterOptions['statuses'] ?? []) as $status): ?>
                                        <option value="<?= esc($status) ?>"><?= esc($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="filter-price-type">Operacion</label>
                                <select id="filter-price-type" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach (($filterOptions['price_types'] ?? []) as $priceType): ?>
                                        <option value="<?= esc($priceType) ?>"><?= esc($priceType) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter-price-min">Precio min</label>
                                <input id="filter-price-min" type="number" class="form-control" min="0" step="1000">
                            </div>
                            <div class="filter-group">
                                <label for="filter-price-max">Precio max</label>
                                <input id="filter-price-max" type="number" class="form-control" min="0" step="1000">
                            </div>
                        </div>

                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter-const-min">Const min m2</label>
                                <input id="filter-const-min" type="number" class="form-control" min="0" step="1">
                            </div>
                            <div class="filter-group">
                                <label for="filter-const-max">Const max m2</label>
                                <input id="filter-const-max" type="number" class="form-control" min="0" step="1">
                            </div>
                        </div>

                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter-land-min">Terreno min m2</label>
                                <input id="filter-land-min" type="number" class="form-control" min="0" step="1">
                            </div>
                            <div class="filter-group">
                                <label for="filter-land-max">Terreno max m2</label>
                                <input id="filter-land-max" type="number" class="form-control" min="0" step="1">
                            </div>
                        </div>

                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter-bedrooms-min">Rec min</label>
                                <input id="filter-bedrooms-min" type="number" class="form-control" min="0" step="1">
                            </div>
                            <div class="filter-group">
                                <label for="filter-bathrooms-min">Banos min</label>
                                <input id="filter-bathrooms-min" type="number" class="form-control" min="0" step="0.5">
                            </div>
                        </div>

                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filter-parking-min">Estac min</label>
                                <input id="filter-parking-min" type="number" class="form-control" min="0" step="1">
                            </div>
                            <div class="filter-group">
                                <label for="filter-source">Fuente</label>
                                <select id="filter-source" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach (($filterOptions['sources'] ?? []) as $source): ?>
                                        <option value="<?= esc($source) ?>"><?= esc($source) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="filter-switches">
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" id="filter-has-geo">
                                <span class="form-check-label">Solo con coordenadas</span>
                            </label>
                        </div>

                        <div class="filter-group">
                            <label for="filter-sort">Orden</label>
                            <select id="filter-sort" class="form-select">
                                <option value="updated_desc">Mas recientes</option>
                                <option value="price_desc">Precio mayor a menor</option>
                                <option value="price_asc">Precio menor a mayor</option>
                                <option value="ppu_desc">PPU mayor a menor</option>
                                <option value="ppu_asc">PPU menor a mayor</option>
                                <option value="const_desc">Construccion mayor a menor</option>
                                <option value="const_asc">Construccion menor a mayor</option>
                            </select>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="col-xl-9 col-lg-8">
                <section class="listing-stage">
                    <div class="listing-stage__toolbar">
                        <div>
                            <h3 class="listing-stage__title">Resultados</h3>
                            <p class="listing-stage__meta" id="results-meta">Cargando propiedades...</p>
                        </div>
                        <button type="button" class="vn-btn vn-btn--primary" id="toggle-map-btn">Ocultar mapa</button>
                    </div>

                    <div class="listing-map-wrap" id="listing-map-wrap">
                        <div id="listing-map"></div>
                    </div>

                    <div id="listings-grid" class="listings-grid"></div>

                    <div class="text-center mt-4">
                        <button type="button" class="vn-btn vn-btn--secondary" id="load-more-btn">Cargar mas</button>
                    </div>

                    <noscript>
                        <div class="mt-4">
                            <h4>Listado indexable de propiedades</h4>
                            <ul>
                                <?php foreach (($listings ?? []) as $listing): ?>
                                    <li>
                                        <a href="<?= url_to('listings.show', (int) ($listing['id'] ?? 0)) ?>">
                                            <?= esc(($listing['title'] ?? '') !== '' ? $listing['title'] : ('Propiedad #' . ($listing['id'] ?? ''))) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </noscript>
                </section>
            </div>
        </div>
    </div>
</section>

<div class="modal fade listing-detail-modal" id="listingDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detail-title">Detalle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="detail-body"></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
    window.ListingsExplorerConfig = {
        listings: <?= json_encode($listings ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        ranges: <?= json_encode($ranges ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        defaultImage: <?= json_encode(base_url('assets/img/property_img_1.jpg')) ?>,
    };
</script>
<script src="<?= base_url('assets/js/listings-explorer.js') ?>"></script>
<?= $this->endSection() ?>
