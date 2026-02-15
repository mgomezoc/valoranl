(function ($) {
    'use strict';

    const config = window.ValoraNLEstimateConfig || {};
    const chartisBaseUrl = config.chartisBaseUrl || 'https://chartismx.com/api';
    const nlStateId = config.nlStateId || '19';

    const $form = $('#valuation-form-element');
    const $submit = $('#valuation-submit');
    const $errors = $('#valuation-form-errors');

    const $municipality = $('#municipality');
    const $municipalityOptions = $('#municipality-options');
    const $colony = $('#colony');
    const $colonyOptions = $('#colony-options');

    const municipalityMap = new Map();

    const $resultsSection = $('#valuation-results-section');
    const $message = $('#valuation-result-message');
    const $estimatedValue = $('#result-estimated-value');
    const $estimatedLow = $('#result-estimated-low');
    const $estimatedHigh = $('#result-estimated-high');
    const $confidence = $('#result-confidence');
    const $confidenceReasons = $('#result-confidence-reasons');
    const $comparablesBody = $('#comparables-table tbody');

    const $calcMethod = $('#calc-method');
    const $calcScope = $('#calc-scope');
    const $calcCounts = $('#calc-counts');
    const $calcPpuWeighted = $('#calc-ppu-weighted');
    const $calcPpuAdjusted = $('#calc-ppu-adjusted');
    const $calcPpuRange = $('#calc-ppu-range');
    const $calcFormulas = $('#calc-formulas');
    const $calcAdvisorDetails = $('#calc-advisor-details');

    const formatCurrency = (amount) => {
        if (amount === null || amount === undefined || Number.isNaN(Number(amount))) {
            return 'N/D';
        }

        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            maximumFractionDigits: 0,
        }).format(Number(amount));
    };

    const formatNumber = (value, digits = 0) => {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return 'N/D';
        }

        return new Intl.NumberFormat('es-MX', {
            maximumFractionDigits: digits,
        }).format(Number(value));
    };

    const showErrors = (errors) => {
        const items = Object.values(errors || {}).map((text) => `<li>${text}</li>`).join('');
        $errors.html(`<ul class="mb-0">${items || '<li>Ocurrió un error al procesar la solicitud.</li>'}</ul>`).removeClass('d-none');
    };

    const clearErrors = () => {
        $errors.addClass('d-none').empty();
    };

    const normalizeName = (item) => {
        if (typeof item === 'string') {
            return item;
        }

        return item.nombre || item.name || item.municipio || item.colonia || item.NOM_MUN || item.NOM_COL || item.label || '';
    };

    const renderDatalist = ($datalist, names) => {
        const uniqueNames = [...new Set(names.filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
        const options = uniqueNames.map((name) => `<option value="${$('<div>').text(name).html()}"></option>`).join('');
        $datalist.html(options);
    };

    const municipalityIdFromItem = (item) => item.id || item.municipio || item.cve_mun || item.CVE_MUN || item.value || null;

    const loadMunicipalities = () => {
        return $.getJSON(`${chartisBaseUrl}/getMunicipios`, { entidad: nlStateId })
            .done((response) => {
                const items = Array.isArray(response) ? response : (response.data || []);

                municipalityMap.clear();
                items.forEach((item) => {
                    const name = normalizeName(item).trim();
                    const id = municipalityIdFromItem(item);
                    if (name !== '' && id !== null && id !== undefined && id !== '') {
                        municipalityMap.set(name.toLowerCase(), String(id));
                    }
                });

                renderDatalist($municipalityOptions, items.map(normalizeName));
            })
            .fail(() => {
                console.warn('No fue posible cargar municipios desde ChartisMX.');
            });
    };

    const resolveMunicipalityParam = (municipalityInput) => {
        const normalized = municipalityInput.trim().toLowerCase();
        if (normalized === '') {
            return '';
        }

        return municipalityMap.get(normalized) || municipalityInput.trim();
    };

    const loadColonies = (municipalityInput) => {
        $colonyOptions.empty();

        const municipalityParam = resolveMunicipalityParam(municipalityInput);
        if (!municipalityParam) {
            return;
        }

        $.getJSON(`${chartisBaseUrl}/getColonias`, {
            entidad: nlStateId,
            municipio: municipalityParam,
        }).done((response) => {
            const items = Array.isArray(response) ? response : (response.data || []);
            renderDatalist($colonyOptions, items.map(normalizeName));
        }).fail(() => {
            console.warn('No fue posible cargar colonias desde ChartisMX.');
        });
    };

    const renderComparables = (comparables) => {
        if (!Array.isArray(comparables) || comparables.length === 0) {
            $comparablesBody.html('<tr><td colspan="7" class="text-center">No hay comparables disponibles.</td></tr>');
            return;
        }

        const rows = comparables.map((item) => {
            const sourceLink = item.url
                ? `<a href="${item.url}" target="_blank" rel="noopener">Ver anuncio</a>`
                : 'N/D';

            return `
                <tr>
                    <td>${item.title || 'Comparable'}</td>
                    <td>${formatCurrency(item.price_amount)}</td>
                    <td>${formatNumber(item.area_construction_m2, 2)}</td>
                    <td>${formatCurrency(item.ppu_m2)}</td>
                    <td>${item.colony || '—'}, ${item.municipality || '—'}</td>
                    <td>${formatNumber(item.similarity_score, 3)}</td>
                    <td>${sourceLink}</td>
                </tr>
            `;
        }).join('');

        $comparablesBody.html(rows);
    };


    const renderBreakdown = (response) => {
        const breakdown = response.calc_breakdown || {};
        const ppuStats = breakdown.ppu_stats || {};
        const formulas = breakdown.formula || {};
        const humanSteps = Array.isArray(breakdown.human_steps) ? breakdown.human_steps : [];
        const advisorSteps = Array.isArray(breakdown.advisor_detail_steps) ? breakdown.advisor_detail_steps : [];

        const methodLabel = breakdown.method === 'synthetic_fallback_v1'
            ? 'Estimación de apoyo (sin suficientes comparables)'
            : 'Comparación con propiedades similares';

        const scopeLabelMap = {
            colonia: 'Misma colonia',
            municipio: 'Mismo municipio',
            municipio_ampliado: 'Municipio ampliado',
            estado: 'Referencia estatal (Nuevo León)',
            sintetico: 'Referencia general de mercado',
        };

        $calcMethod.text(methodLabel);
        $calcScope.text(scopeLabelMap[breakdown.scope_used] || breakdown.scope_used || response.location_scope || 'N/D');
        $calcCounts.text(`${breakdown.comparables_raw ?? 'N/D'} / ${breakdown.comparables_useful ?? 'N/D'}`);
        $calcPpuWeighted.text(formatCurrency(ppuStats.weighted_median));
        $calcPpuAdjusted.text(formatCurrency(ppuStats.adjusted_ppu));
        $calcPpuRange.text(`${formatCurrency(ppuStats.p25)} - ${formatCurrency(ppuStats.p75)}`);

        const formulaItems = (humanSteps.length > 0
            ? humanSteps
            : [
                `Valor estimado: ${formulas.estimated_value || 'N/D'}`,
                `Rango bajo: ${formulas.estimated_low || 'N/D'}`,
                `Rango alto: ${formulas.estimated_high || 'N/D'}`,
            ]).map((item) => `<li>${item}</li>`).join('');

        $calcFormulas.html(formulaItems);

        const valuationFactors = breakdown.valuation_factors || {};
        const advisorFallback = [
            `Factor Ross-Heidecke: ${formatNumber(valuationFactors.ross_heidecke, 4)}.`,
            `Factor de negociación: ${formatNumber(valuationFactors.negotiation, 4)}.`,
            `Factor de equipamiento: ${formatNumber(valuationFactors.equipment, 4)}.`,
            `Factor combinado aplicado: ${formatNumber(valuationFactors.combined_adjustment_factor, 4)}.`,
            `Cálculo base: ${formulas.estimated_value || 'N/D'}.`,
        ];

        const advisorItems = (advisorSteps.length > 0 ? advisorSteps : advisorFallback)
            .map((item) => `<li>${item}</li>`)
            .join('');

        $calcAdvisorDetails.html(advisorItems);
    };

    const renderResult = (response) => {
        $resultsSection.show();
        $message.text(response.message || 'Resultado generado.');

        $estimatedValue.text(formatCurrency(response.estimated_value));
        $estimatedLow.text(formatCurrency(response.estimated_low));
        $estimatedHigh.text(formatCurrency(response.estimated_high));
        $confidence.text(`${formatNumber(response.confidence_score)} / 100`);

        const reasons = Array.isArray(response.confidence_reasons)
            ? response.confidence_reasons.map((reason) => `<li>${reason}</li>`).join('')
            : '<li>Sin explicación disponible.</li>';

        $confidenceReasons.html(reasons);
        renderComparables(response.comparables || []);
        renderBreakdown(response);

        $('html, body').animate({ scrollTop: $resultsSection.offset().top - 80 }, 400);
    };

    const setupValidation = () => {
        if (!$.fn.validate) {
            return;
        }

        $form.validate({
            errorClass: 'is-invalid',
            validClass: 'is-valid',
            errorElement: 'div',
            errorPlacement(error, element) {
                error.addClass('invalid-feedback');
                error.insertAfter(element);
            },
            highlight(element) {
                $(element).addClass('is-invalid').removeClass('is-valid');
            },
            unhighlight(element) {
                $(element).removeClass('is-invalid').addClass('is-valid');
            },
            rules: {
                municipality: { required: true, maxlength: 120 },
                colony: { required: true, maxlength: 160 },
                area_construction_m2: { required: true, number: true, min: 1 },
                area_land_m2: { number: true, min: 0 },
                bedrooms: { digits: true, min: 0 },
                bathrooms: { number: true, min: 0 },
                half_bathrooms: { digits: true, min: 0 },
                parking: { digits: true, min: 0 },
                lat: { number: true },
                lng: { number: true },
            },
            messages: {
                municipality: { required: 'Selecciona o escribe un municipio.' },
                colony: { required: 'Selecciona o escribe una colonia.' },
                area_construction_m2: {
                    required: 'Ingresa los m² de construcción.',
                    min: 'Debe ser mayor a 0.',
                },
            },
        });
    };

    setupValidation();
    loadMunicipalities();

    $municipality.on('change blur', function () {
        loadColonies($(this).val().trim());
    });

    $form.on('submit', function (event) {
        event.preventDefault();
        clearErrors();

        if ($.fn.validate && !$form.valid()) {
            return;
        }

        $submit.prop('disabled', true).addClass('disabled');

        $.ajax({
            url: config.estimateUrl,
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
        }).done(function (response) {
            if (!response.ok) {
                showErrors({ general: response.message || 'No fue posible calcular la valuación.' });
            }

            renderResult(response);
        }).fail(function (xhr) {
            const response = xhr.responseJSON || {};
            if (response.errors) {
                showErrors(response.errors);
            } else {
                showErrors({ general: response.message || 'Error inesperado al estimar valuación.' });
            }
        }).always(function () {
            $submit.prop('disabled', false).removeClass('disabled');
        });
    });
})(jQuery);
