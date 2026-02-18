(function ($) {
    'use strict';

    const config = window.ListingsExplorerConfig || {};
    const allListings = Array.isArray(config.listings) ? config.listings : [];
    const ranges = config.ranges || {};
    const defaultImage = config.defaultImage || '';

    const pageSize = 24;
    let visibleCount = pageSize;
    let filtered = [];
    let map = null;
    let mapLayer = null;
    let mapVisible = true;

    const $meta = $('#results-meta');
    const $grid = $('#listings-grid');
    const $loadMore = $('#load-more-btn');
    const $toggleMap = $('#toggle-map-btn');
    const $mapWrap = $('#listing-map-wrap');

    const $detailTitle = $('#detail-title');
    const $detailBody = $('#detail-body');
    const detailModal = new bootstrap.Modal(document.getElementById('listingDetailModal'));

    const filters = {
        search: $('#filter-search'),
        municipality: $('#filter-municipality'),
        colony: $('#filter-colony'),
        propertyType: $('#filter-property-type'),
        status: $('#filter-status'),
        priceType: $('#filter-price-type'),
        priceMin: $('#filter-price-min'),
        priceMax: $('#filter-price-max'),
        constMin: $('#filter-const-min'),
        constMax: $('#filter-const-max'),
        landMin: $('#filter-land-min'),
        landMax: $('#filter-land-max'),
        bedroomsMin: $('#filter-bedrooms-min'),
        bathroomsMin: $('#filter-bathrooms-min'),
        parkingMin: $('#filter-parking-min'),
        source: $('#filter-source'),
        hasGeo: $('#filter-has-geo'),
        sort: $('#filter-sort'),
    };

    function money(value) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return 'N/D';
        }
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            maximumFractionDigits: 0,
        }).format(Number(value));
    }

    function num(value, maxDigits = 0) {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return 'N/D';
        }
        return new Intl.NumberFormat('es-MX', {
            maximumFractionDigits: maxDigits,
        }).format(Number(value));
    }

    function str(value) {
        return (value || '').toString().trim();
    }

    function imageFromListing(listing) {
        const images = Array.isArray(listing.images) ? listing.images : [];
        return images.length > 0 ? images[0] : defaultImage;
    }

    function parseJsonSafely(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }
        try {
            return JSON.parse(value);
        } catch (e) {
            return null;
        }
    }

    function readFilterNumber($el) {
        const value = ($el.val() || '').toString().trim();
        if (value === '') {
            return null;
        }
        const n = Number(value);
        return Number.isNaN(n) ? null : n;
    }

    function setupDefaults() {
        if (ranges.price_min !== null && ranges.price_min !== undefined) {
            filters.priceMin.attr('placeholder', Math.floor(Number(ranges.price_min)));
        }
        if (ranges.price_max !== null && ranges.price_max !== undefined) {
            filters.priceMax.attr('placeholder', Math.ceil(Number(ranges.price_max)));
        }
        if (ranges.const_min !== null && ranges.const_min !== undefined) {
            filters.constMin.attr('placeholder', Math.floor(Number(ranges.const_min)));
        }
        if (ranges.const_max !== null && ranges.const_max !== undefined) {
            filters.constMax.attr('placeholder', Math.ceil(Number(ranges.const_max)));
        }
        if (ranges.land_min !== null && ranges.land_min !== undefined) {
            filters.landMin.attr('placeholder', Math.floor(Number(ranges.land_min)));
        }
        if (ranges.land_max !== null && ranges.land_max !== undefined) {
            filters.landMax.attr('placeholder', Math.ceil(Number(ranges.land_max)));
        }
    }

    function applyFilters() {
        const q = str(filters.search.val()).toLowerCase();
        const municipality = str(filters.municipality.val());
        const colony = str(filters.colony.val());
        const propertyType = str(filters.propertyType.val());
        const status = str(filters.status.val());
        const priceType = str(filters.priceType.val());
        const source = str(filters.source.val());

        const priceMin = readFilterNumber(filters.priceMin);
        const priceMax = readFilterNumber(filters.priceMax);
        const constMin = readFilterNumber(filters.constMin);
        const constMax = readFilterNumber(filters.constMax);
        const landMin = readFilterNumber(filters.landMin);
        const landMax = readFilterNumber(filters.landMax);
        const bedroomsMin = readFilterNumber(filters.bedroomsMin);
        const bathroomsMin = readFilterNumber(filters.bathroomsMin);
        const parkingMin = readFilterNumber(filters.parkingMin);
        const hasGeo = filters.hasGeo.is(':checked');
        const sort = str(filters.sort.val()) || 'updated_desc';

        filtered = allListings.filter((item) => {
            if (municipality && str(item.municipality) !== municipality) return false;
            if (colony && str(item.colony) !== colony) return false;
            if (propertyType && str(item.property_type) !== propertyType) return false;
            if (status && str(item.status) !== status) return false;
            if (priceType && str(item.price_type) !== priceType) return false;
            if (source && str(item.source_name) !== source) return false;

            const price = Number(item.price_amount || 0);
            const constArea = Number(item.area_construction_m2 || 0);
            const landArea = Number(item.area_land_m2 || 0);
            const bedrooms = Number(item.bedrooms || 0);
            const bathrooms = Number(item.bathrooms || 0);
            const parking = Number(item.parking || 0);

            if (priceMin !== null && price < priceMin) return false;
            if (priceMax !== null && price > priceMax) return false;
            if (constMin !== null && constArea < constMin) return false;
            if (constMax !== null && constArea > constMax) return false;
            if (landMin !== null && landArea < landMin) return false;
            if (landMax !== null && landArea > landMax) return false;
            if (bedroomsMin !== null && bedrooms < bedroomsMin) return false;
            if (bathroomsMin !== null && bathrooms < bathroomsMin) return false;
            if (parkingMin !== null && parking < parkingMin) return false;

            const hasCoordinates = item.lat !== null && item.lat !== undefined && item.lng !== null && item.lng !== undefined;
            if (hasGeo && !hasCoordinates) return false;

            if (q) {
                const blob = [
                    item.id,
                    item.title,
                    item.street,
                    item.colony,
                    item.municipality,
                    item.state,
                    item.postal_code,
                    item.source_name,
                    item.source_listing_id,
                ].join(' ').toLowerCase();
                if (!blob.includes(q)) return false;
            }
            return true;
        });

        filtered.sort((a, b) => {
            const aUpdated = Date.parse(a.updated_at || '') || 0;
            const bUpdated = Date.parse(b.updated_at || '') || 0;
            const aPrice = Number(a.price_amount || 0);
            const bPrice = Number(b.price_amount || 0);
            const aPpu = Number(a.price_per_m2 || 0);
            const bPpu = Number(b.price_per_m2 || 0);
            const aConst = Number(a.area_construction_m2 || 0);
            const bConst = Number(b.area_construction_m2 || 0);

            switch (sort) {
                case 'price_desc': return bPrice - aPrice;
                case 'price_asc': return aPrice - bPrice;
                case 'ppu_desc': return bPpu - aPpu;
                case 'ppu_asc': return aPpu - bPpu;
                case 'const_desc': return bConst - aConst;
                case 'const_asc': return aConst - bConst;
                default: return bUpdated - aUpdated;
            }
        });
    }

    function listingCardHtml(item) {
        const title = str(item.title) || `Propiedad #${item.id}`;
        const location = [str(item.colony), str(item.municipality), str(item.state)].filter(Boolean).join(', ');
        const metrics = [
            `${num(item.area_construction_m2, 2)} m2 const`,
            `${num(item.area_land_m2, 2)} m2 terr`,
            `${money(item.price_per_m2)} /m2`,
        ];

        return `
            <article class="listing-card">
                <div class="listing-card__media" style="background-image:url('${imageFromListing(item)}')">
                    <div class="listing-card__price">${money(item.price_amount)}</div>
                </div>
                <div class="listing-card__body">
                    <h4 class="listing-card__title">${title}</h4>
                    <div class="listing-card__location"><i class="fa-solid fa-location-dot"></i> ${location || 'Ubicacion no disponible'}</div>
                    <div class="listing-card__chips">
                        <span class="listing-chip">${str(item.property_type) || 'N/D'}</span>
                        <span class="listing-chip">${str(item.status) || 'N/D'}</span>
                        <span class="listing-chip">${str(item.price_type) || 'N/D'}</span>
                        <span class="listing-chip">${str(item.source_name) || 'Sin fuente'}</span>
                    </div>
                    <div class="listing-card__metrics">
                        <div><i class="fa-solid fa-bed"></i> ${num(item.bedrooms)}</div>
                        <div><i class="fa-solid fa-bath"></i> ${num(item.bathrooms, 1)}</div>
                        <div><i class="fa-solid fa-car"></i> ${num(item.parking)}</div>
                        <div>${metrics[0]}</div>
                        <div>${metrics[1]}</div>
                        <div>${metrics[2]}</div>
                    </div>
                    <div class="listing-card__actions">
                        <button type="button" class="vn-btn vn-btn--primary js-open-detail" data-id="${item.id}">Ver detalle</button>
                        ${item.url ? `<a href="${item.url}" target="_blank" rel="noopener" class="vn-btn vn-btn--secondary">Fuente</a>` : ''}
                    </div>
                </div>
            </article>
        `;
    }

    function renderCards() {
        const total = filtered.length;
        const shown = Math.min(visibleCount, total);
        const subset = filtered.slice(0, shown);

        if (subset.length === 0) {
            $grid.html('<div class="listing-card__empty">No hay propiedades que coincidan con los filtros actuales.</div>');
        } else {
            $grid.html(subset.map(listingCardHtml).join(''));
        }

        $meta.text(`${num(total)} propiedades encontradas | mostrando ${num(shown)}`);
        $loadMore.toggle(total > shown);
    }

    function buildDetailHtml(item) {
        const location = [str(item.street), str(item.colony), str(item.municipality), str(item.state), str(item.postal_code)].filter(Boolean).join(', ');
        const detailsData = parseJsonSafely(item.details_json);
        const amenitiesData = parseJsonSafely(item.amenities_json);
        const contactData = parseJsonSafely(item.contact_json);

        const detailFields = [
            ['ID', item.id],
            ['Fuente', item.source_name],
            ['ID fuente', item.source_listing_id],
            ['Estatus', item.status],
            ['Operacion', item.price_type],
            ['Tipo', item.property_type],
            ['Precio', money(item.price_amount)],
            ['Mantenimiento', money(item.maintenance_fee)],
            ['PPU', money(item.price_per_m2)],
            ['Const m2', num(item.area_construction_m2, 2)],
            ['Terreno m2', num(item.area_land_m2, 2)],
            ['Recamaras', num(item.bedrooms)],
            ['Banos', num(item.bathrooms, 1)],
            ['Medios banos', num(item.half_bathrooms, 1)],
            ['Estacionamiento', num(item.parking)],
            ['Niveles', num(item.floors)],
            ['Antiguedad', num(item.age_years)],
            ['Precision geo', str(item.geo_precision) || 'unknown'],
            ['Latitud', item.lat !== null ? num(item.lat, 6) : 'N/D'],
            ['Longitud', item.lng !== null ? num(item.lng, 6) : 'N/D'],
            ['Ultima actualizacion', item.updated_at || 'N/D'],
            ['Ultima deteccion', item.seen_last_at || 'N/D'],
        ];

        const detailGrid = detailFields.map(([label, value]) => (
            `<div class="detail-item"><span class="detail-item__label">${label}</span><span class="detail-item__value">${value}</span></div>`
        )).join('');

        return `
            <div class="mb-3">
                <img src="${imageFromListing(item)}" alt="Propiedad" style="width:100%;max-height:360px;object-fit:cover;border-radius:10px;">
            </div>
            <p><strong>Ubicacion:</strong> ${location || 'No disponible'}</p>
            <p><strong>Descripcion:</strong> ${str(item.description) || 'Sin descripcion'}</p>
            <div class="detail-grid mb-3">${detailGrid}</div>
            ${item.url ? `<p><a href="${item.url}" target="_blank" rel="noopener" class="vn-btn vn-btn--primary">Abrir anuncio fuente</a></p>` : ''}
            ${detailsData ? `<h6>details_json</h6><pre class="detail-json">${$('<div>').text(JSON.stringify(detailsData, null, 2)).html()}</pre>` : ''}
            ${amenitiesData ? `<h6 class="mt-3">amenities_json</h6><pre class="detail-json">${$('<div>').text(JSON.stringify(amenitiesData, null, 2)).html()}</pre>` : ''}
            ${contactData ? `<h6 class="mt-3">contact_json</h6><pre class="detail-json">${$('<div>').text(JSON.stringify(contactData, null, 2)).html()}</pre>` : ''}
        `;
    }

    function openDetail(id) {
        const item = allListings.find((x) => Number(x.id) === Number(id));
        if (!item) return;
        $detailTitle.text(str(item.title) || `Propiedad #${item.id}`);
        $detailBody.html(buildDetailHtml(item));
        detailModal.show();
    }

    function renderMap() {
        if (!map) return;
        if (mapLayer) {
            map.removeLayer(mapLayer);
        }

        const points = filtered.filter((item) => item.lat !== null && item.lng !== null);
        mapLayer = L.layerGroup();

        points.forEach((item) => {
            const marker = L.marker([Number(item.lat), Number(item.lng)]);
            const popupHtml = `
                <strong>${str(item.title) || `Propiedad #${item.id}`}</strong><br>
                ${money(item.price_amount)}<br>
                ${str(item.colony)}, ${str(item.municipality)}<br>
                <button type="button" class="vn-btn vn-btn--secondary js-open-detail-map" data-id="${item.id}">Ver detalle</button>
            `;
            marker.bindPopup(popupHtml);
            mapLayer.addLayer(marker);
        });

        mapLayer.addTo(map);

        if (points.length > 0) {
            const bounds = L.latLngBounds(points.map((p) => [Number(p.lat), Number(p.lng)]));
            map.fitBounds(bounds.pad(0.2));
        }
    }

    function initMap() {
        map = L.map('listing-map', {
            zoomControl: true,
        }).setView([25.6866, -100.3161], 10);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        renderMap();
    }

    function refresh() {
        visibleCount = pageSize;
        applyFilters();
        renderCards();
        renderMap();
    }

    function clearFilters() {
        Object.values(filters).forEach(($el) => {
            if ($el.is(':checkbox')) {
                $el.prop('checked', false);
            } else if ($el.is('select')) {
                $el.val('');
            } else {
                $el.val('');
            }
        });
        filters.sort.val('updated_desc');
        refresh();
    }

    function bindEvents() {
        Object.values(filters).forEach(($el) => {
            $el.on('input change', refresh);
        });

        $('#clear-filters-btn').on('click', clearFilters);

        $loadMore.on('click', function () {
            visibleCount += pageSize;
            renderCards();
        });

        $toggleMap.on('click', function () {
            mapVisible = !mapVisible;
            $mapWrap.toggle(mapVisible);
            $toggleMap.text(mapVisible ? 'Ocultar mapa' : 'Mostrar mapa');
            if (mapVisible && map) {
                setTimeout(() => map.invalidateSize(), 150);
            }
        });

        $(document).on('click', '.js-open-detail', function () {
            openDetail($(this).data('id'));
        });

        $(document).on('click', '.js-open-detail-map', function () {
            openDetail($(this).data('id'));
        });
    }

    setupDefaults();
    bindEvents();
    applyFilters();
    renderCards();
    initMap();
})(jQuery);
