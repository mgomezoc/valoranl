/**
 * home-new.js — UI de la nueva home ValoraNL
 * Patrón ChartisMX tomado de dashboard-map.js (getMunicipios / getColonias con ID numérico)
 */
(function ($) {
    'use strict';

    /* ─────────────────────────────────────────────
       CONFIG
    ───────────────────────────────────────────── */
    const config       = window.ValoraNLEstimateConfig || {};
    const CHARTIS_BASE = config.chartisBaseUrl || 'https://chartismx.com/api';
    const NL_STATE_ID  = config.nlStateId     || '19';

    /* Fallback ZMM si ChartisMX falla */
    const ZMM_FALLBACK = [
        'Apodaca','Cadereyta Jiménez','Ciénega de Flores','García','General Escobedo',
        'General Zuazua','Guadalupe','Juárez','Monterrey','Pesquería','Salinas Victoria',
        'San Nicolás de los Garza','San Pedro Garza García','Santa Catarina','Santiago','Zuazua',
    ];

    /* Mapa nombre → ID numérico (igual que municipalityMap en dashboard-map.js) */
    const municipioMap = new Map(); // key: nombre normalizado → value: id (string)

    /* ─────────────────────────────────────────────
       HELPERS
    ───────────────────────────────────────────── */
    function normalize(s) {
        return (s || '').toString().trim().toLowerCase();
    }

    function normalizeName(item) {
        if (typeof item === 'string') return item;
        return (
            item.nombre || item.Nombre ||
            item.name   || item.municipio || item.Municipio ||
            item.NOM_MUN || item.label || ''
        ).toString();
    }

    function getMunicipioId(item) {
        /* mismo orden que municipalityIdFromItem en dashboard-map.js */
        return (
            item.id      || item.municipio || item.Municipio ||
            item.cve_mun || item.CVE_MUN   || item.value || null
        );
    }

    function resolveParamMunicipio(nameInput) {
        const key = normalize(nameInput);
        if (!key) return '';
        /* Si tenemos ID numérico lo usamos, si no mandamos el nombre */
        return municipioMap.get(key) || nameInput.trim();
    }

    /* ─────────────────────────────────────────────
       MAPA LEAFLET
    ───────────────────────────────────────────── */
    const NL_CENTER = [25.6866, -100.3161];

    const map = L.map('vn-leaflet-map', {
        center: NL_CENTER,
        zoom: 11,
        zoomControl: true,
        scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    let mapMarker = null;

    const goldIcon = L.divIcon({
        className: '',
        html: [
            '<div style="',
            '  width:22px;height:22px;border-radius:50%;',
            '  background:#e6d7b8;border:3px solid #002b36;',
            '  box-shadow:0 3px 10px rgba(0,0,0,0.55);',
            '  transition:transform .15s ease;',
            '"></div>',
        ].join(''),
        iconSize: [22, 22],
        iconAnchor: [11, 11],
    });

    function setMapMarker(lat, lng) {
        if (mapMarker) map.removeLayer(mapMarker);
        mapMarker = L.marker([lat, lng], { icon: goldIcon, draggable: true }).addTo(map);
        map.setView([lat, lng], 15);
        writeCoords(lat, lng);

        mapMarker.on('dragend', function (ev) {
            const pos = ev.target.getLatLng();
            writeCoords(pos.lat, pos.lng);
        });
    }

    function writeCoords(lat, lng) {
        $('#lat').val(lat.toFixed(6));
        $('#lng').val(lng.toFixed(6));
    }

    /* Click en mapa → pone pin */
    map.on('click', function (ev) {
        setMapMarker(ev.latlng.lat, ev.latlng.lng);
    });

    /* Invalidar tamaño cuando ya es visible */
    setTimeout(function () { map.invalidateSize(); }, 350);

    /* ─────────────────────────────────────────────
       SELECT2 — inicialización
       Todos con dropdownParent correcto para evitar
       que el dropdown quede cortado por overflow:hidden
    ───────────────────────────────────────────── */
    const $mapWrapper  = $('.vn-map-wrapper');
    const $formCard    = $('.vn-form-card');

    /* helpers */
    function initSelect2Light($el, opts) {
        $el.select2($.extend({
            width: '100%',
            dropdownParent: $mapWrapper,
            theme: 'default',
        }, opts));
    }

    function initSelect2Dark($el, opts) {
        $el.select2($.extend({
            width: '100%',
            dropdownParent: $formCard,
            theme: 'default',
        }, opts));
        /* marcar el contenedor para CSS dark */
        $el.next('.select2-container').addClass('vn2-dark');
    }

    /* Selectores del área del mapa */
    initSelect2Light($('#area_metro'), {
        placeholder: 'Zona Metropolitana de Monterrey',
        allowClear: false,
        minimumResultsForSearch: Infinity,
    });

    initSelect2Light($('#municipality_select'), {
        placeholder: 'Selecciona municipio...',
        allowClear: true,
    });

    initSelect2Light($('#colony_select'), {
        placeholder: 'Selecciona colonia...',
        allowClear: true,
        tags: true,          /* permite escribir si no aparece en lista */
        language: {
            noResults: function () { return 'Elige un municipio primero'; },
        },
    });

    /* Selectores del form card (dark) */
    initSelect2Dark($('#property_type_select'), {
        placeholder: 'Tipo de propiedad',
        allowClear: false,
        minimumResultsForSearch: Infinity,
    });

    initSelect2Dark($('#age_years'), {
        placeholder: 'Ej. 10',
        allowClear: true,
    });

    initSelect2Dark($('#conservation_level'), {
        placeholder: 'Se infiere de la edad',
        allowClear: true,
    });

    /* ─────────────────────────────────────────────
       CHARTIS MX — getMunicipios
       Endpoint correcto: /getMunicipios?entidad=19
       (mismo patrón que dashboard-map.js → loadMunicipios)
    ───────────────────────────────────────────── */
    function loadMunicipios() {
        const url = CHARTIS_BASE + '/getMunicipios?entidad=' + NL_STATE_ID;

        $.getJSON(url)
            .done(function (response) {
                const items = Array.isArray(response) ? response : (response.data || []);

                municipioMap.clear();
                items.forEach(function (m) {
                    const nombre = normalizeName(m).trim();
                    const id     = getMunicipioId(m);
                    if (nombre && id !== null && id !== undefined && id !== '') {
                        municipioMap.set(normalize(nombre), String(id));
                    }
                });

                fillMunicipioSelect(items.map(normalizeName));
            })
            .fail(function () {
                console.warn('[ValoraNL] ChartisMX getMunicipios falló → usando fallback ZMM');
                fillMunicipioSelect(ZMM_FALLBACK);
            });
    }

    function fillMunicipioSelect(nombres) {
        const $sel = $('#municipality_select');
        const unique = [...new Set(nombres.filter(Boolean))].sort(function (a, b) {
            return a.localeCompare(b, 'es');
        });

        $sel.empty().append('<option value=""></option>');
        unique.forEach(function (n) {
            $sel.append($('<option>').val(n).text(n));
        });
        $sel.trigger('change.select2'); /* refresca UI sin disparar lógica */
    }

    /* ─────────────────────────────────────────────
       CHARTIS MX — getColonias
       Endpoint correcto: /getColonias?entidad=19&municipio=ID
       (mismo patrón que dashboard-map.js → loadColonias)
    ───────────────────────────────────────────── */
    function loadColonias(municipioNombre) {
        const $sel = $('#colony_select');

        /* Limpiar y deshabilitar mientras carga */
        $sel.empty().append('<option value=""></option>');
        $sel.prop('disabled', true).trigger('change.select2');

        if (!municipioNombre) return;

        const municipioParam = resolveParamMunicipio(municipioNombre);
        const url = CHARTIS_BASE + '/getColonias?entidad=' + NL_STATE_ID +
                    '&municipio=' + encodeURIComponent(municipioParam);

        $.getJSON(url)
            .done(function (response) {
                const items = Array.isArray(response) ? response : (response.data || []);

                if (!items.length) {
                    console.warn('[ValoraNL] getColonias devolvió lista vacía para:', municipioParam);
                    $sel.prop('disabled', false).trigger('change.select2');
                    return;
                }

                items.forEach(function (c) {
                    /* Usar nombre de la colonia como valor (mismo que en valuation) */
                    const nombre = (
                        c.nombre || c.Nombre || c.name ||
                        c.colonia || c.Colonia || ''
                    ).toString().trim();

                    if (nombre) {
                        $sel.append($('<option>').val(nombre).text(nombre));
                    }
                });

                $sel.prop('disabled', false).trigger('change.select2');
            })
            .fail(function () {
                console.warn('[ValoraNL] getColonias falló para:', municipioParam);
                $sel.prop('disabled', false).trigger('change.select2');
            });
    }

    /* ─────────────────────────────────────────────
       EVENTOS SELECT2 — municipio / colonia
    ───────────────────────────────────────────── */
    $('#municipality_select').on('change', function () {
        const mun = $(this).val() || '';
        loadColonias(mun);

        /* Geocodear al municipio automáticamente si no hay pin */
        if (mun && !$('#lat').val()) {
            geocodeStr(mun + ', Nuevo León, México');
        }
    });

    /* ─────────────────────────────────────────────
       GEOCODING — Nominatim
    ───────────────────────────────────────────── */
    function showGeoStatus(msg, type) {
        $('#geocode-status')
            .removeClass('geocode-searching geocode-success geocode-error')
            .addClass('geocode-' + type)
            .html(msg)
            .show();
    }

    function geocodeStr(query) {
        showGeoStatus('<i class="fa-solid fa-spinner fa-spin"></i> Buscando...', 'searching');

        $.ajax({
            url: 'https://nominatim.openstreetmap.org/search',
            data: { q: query, format: 'json', limit: 1, countrycodes: 'mx' },
            dataType: 'json',
        }).done(function (results) {
            if (results && results.length) {
                const r = results[0];
                setMapMarker(parseFloat(r.lat), parseFloat(r.lon));
                showGeoStatus(
                    '<i class="fa-solid fa-circle-check"></i> ' +
                    (r.display_name || '').substring(0, 55) + '…',
                    'success'
                );
            } else {
                showGeoStatus(
                    '<i class="fa-solid fa-triangle-exclamation"></i> No encontrado. Haz clic en el mapa.',
                    'error'
                );
            }
        }).fail(function () {
            showGeoStatus(
                '<i class="fa-solid fa-triangle-exclamation"></i> Error de red.',
                'error'
            );
        });
    }

    function buildSearchQuery() {
        const addr   = $('#address').val().trim();
        const colony = $('#colony_select').val() || '';
        const mun    = $('#municipality_select').val() || '';
        return [addr, colony, mun, 'Nuevo León', 'México'].filter(Boolean).join(', ');
    }

    $('#geocode-btn').on('click', function () {
        const q = buildSearchQuery();
        if (!q.replace(/,\s*/g, '').trim()) {
            showGeoStatus('Ingresa una dirección o elige un municipio.', 'error');
            return;
        }
        geocodeStr(q);
    });

    $('#address').on('keydown', function (ev) {
        if (ev.key === 'Enter') { ev.preventDefault(); $('#geocode-btn').trigger('click'); }
    });

    /* ─────────────────────────────────────────────
       COUNTER CARDS — +/- con animación
    ───────────────────────────────────────────── */
    const COUNTER_CFG = {
        bedrooms:       { min: 0, max: 10 },
        bathrooms:      { min: 0, max: 10 },
        half_bathrooms: { min: 0, max: 5  },
        parking:        { min: 0, max: 10 },
    };

    function getCounterVal(field) {
        return parseInt($('#' + field).val(), 10) || 0;
    }

    function setCounterVal(field, v) {
        const cfg = COUNTER_CFG[field];
        v = Math.max(cfg.min, Math.min(cfg.max, v));
        $('#' + field).val(v);
        const $disp = $('#' + field + '-display');
        $disp.text(v).addClass('vn-counter-pop');
        setTimeout(function () { $disp.removeClass('vn-counter-pop'); }, 200);
    }

    $(document).on('click', '.vn-counter-btn--plus', function () {
        setCounterVal($(this).data('target'), getCounterVal($(this).data('target')) + 1);
    });

    $(document).on('click', '.vn-counter-btn--minus', function () {
        setCounterVal($(this).data('target'), getCounterVal($(this).data('target')) - 1);
    });

    /* ─────────────────────────────────────────────
       TOGGLE AVANZADO
    ───────────────────────────────────────────── */
    $('#toggle-advanced').on('click', function () {
        $('#advanced-fields').slideToggle(220);
        $(this).find('.vn-chevron').toggleClass('open');
    });

    /* ─────────────────────────────────────────────
       SUBMIT → AJAX al backend CI4
    ───────────────────────────────────────────── */
    $('#valuation-submit').on('click', function () {
        /* Sincronizar hidden inputs que el backend espera */
        syncHidden('property_type', $('#property_type_select').val() || 'casa');
        syncHidden('municipality', $('#municipality_select').val() || '');
        syncHidden('colony',       $('#colony_select').val()       || '');

        /* Validación client-side */
        const errs = [];
        if (!$('#municipality').val())             errs.push('Selecciona un municipio.');
        if (!$('#lat').val() || !$('#lng').val())  errs.push('Ubica tu inmueble en el mapa (clic o buscar).');
        if (!$('#area_construction_m2').val())     errs.push('Ingresa los m² de construcción.');
        if ($('#age_years').val() === '')          errs.push('Selecciona la edad del inmueble.');

        const $errBox = $('#valuation-form-errors');
        if (errs.length) {
            $errBox
                .html(errs.map(function (e) { return '<div>• ' + e + '</div>'; }).join(''))
                .removeClass('d-none');
            $errBox[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }
        $errBox.addClass('d-none');

        /* FormData */
        const payload = {
            property_type:           $('#property_type').val(),
            municipality:            $('#municipality').val(),
            colony:                  $('#colony').val(),
            address:                 $('#address').val(),
            lat:                     $('#lat').val(),
            lng:                     $('#lng').val(),
            area_construction_m2:    $('#area_construction_m2').val(),
            area_land_m2:            $('#area_land_m2').val(),
            age_years:               $('#age_years').val(),
            conservation_level:      $('#conservation_level').val(),
            bedrooms:                $('#bedrooms').val(),
            bathrooms:               $('#bathrooms').val(),
            half_bathrooms:          $('#half_bathrooms').val(),
            parking:                 $('#parking').val(),
            construction_unit_value: $('#construction_unit_value').val(),
            equipment_value:         $('#equipment_value').val(),
        };

        /* CSRF CI4 */
        const $csrf = $('input[name^="csrf"]').first();
        if ($csrf.length) payload[$csrf.attr('name')] = $csrf.val();

        const $btn = $('#valuation-submit');
        $btn.prop('disabled', true).html(
            '<i class="fa-solid fa-spinner fa-spin me-2"></i> Calculando...'
        );

        $.ajax({
            url:    config.estimateUrl,
            method: 'POST',
            data:   payload,
        }).done(function (response) {
            if (typeof window.VNHandleEstimateResponse === 'function') {
                window.VNHandleEstimateResponse(response);
            }
        }).fail(function (xhr) {
            let msg = 'Error al calcular. Intenta nuevamente.';
            try { const r = JSON.parse(xhr.responseText); if (r.message) msg = r.message; }
            catch (_) {}
            $errBox.html('<div>• ' + msg + '</div>').removeClass('d-none');
        }).always(function () {
            $btn.prop('disabled', false).html(
                '<i class="fa-solid fa-calculator me-2"></i> Continuar'
            );
        });
    });

    /* ─────────────────────────────────────────────
       UTILIDADES
    ───────────────────────────────────────────── */
    function syncHidden(id, val) {
        let $el = $('#' + id);
        if (!$el.length) {
            $el = $('<input type="hidden">').attr({ id: id, name: id }).appendTo('body');
        }
        $el.val(val);
    }

    /* ─────────────────────────────────────────────
       BOOT
    ───────────────────────────────────────────── */
    loadMunicipios();

})(jQuery);
