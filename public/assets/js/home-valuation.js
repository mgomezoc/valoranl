(function ($) {
    'use strict';

    const config = window.ValoraNLEstimateConfig || {};
    const chartisBaseUrl = config.chartisBaseUrl || 'https://chartismx.com/api';
    const nlStateId = config.nlStateId || '19';
    const conservationInference = config.conservationInference || {};

    const $form = $('#valuation-form-element');
    const $submit = $('#valuation-submit');
    const $errors = $('#valuation-form-errors');

    const $municipality = $('#municipality');
    const $municipalityOptions = $('#municipality-options');
    const $colony = $('#colony');
    const $colonyOptions = $('#colony-options');

    const $ageYears = $('#age_years');
    const $conservationLevel = $('#conservation_level');
    const $advancedToggle = $('#advanced-fields-toggle');
    const $advancedFields = $('#advanced-fields');

    const municipalityMap = new Map();

    const $resultsSection = $('#valuation-results-section');
    const $message = $('#valuation-result-message');
    const $estimatedValue = $('#result-estimated-value');
    const $estimatedLow = $('#result-estimated-low');
    const $estimatedHigh = $('#result-estimated-high');
    const $confidence = $('#result-confidence');
    const $confidenceReasons = $('#result-confidence-reasons');
    const $comparablesBody = $('#comparables-table tbody');
    const $aiPoweredBanner = $('#ai-powered-banner');
    const $aiPoweredMessage = $('#ai-powered-message');
    const $dualResultsComparison = $('#dual-results-comparison');
    const $resultOriginalValue = $('#result-original-value');
    const $resultOriginalRange = $('#result-original-range');
    const $resultAiValue = $('#result-ai-value');
    const $resultAiRange = $('#result-ai-range');

    const $calcMethod = $('#calc-method');
    const $calcScope = $('#calc-scope');
    const $calcCounts = $('#calc-counts');
    const $calcDbUsage = $('#calc-db-usage');
    const $calcDataOrigin = $('#calc-data-origin');
    const $calcAiInputs = $('#calc-ai-inputs');
    const $calcAiStatus = $('#calc-ai-status');
    const $calcPpuWeighted = $('#calc-ppu-weighted');
    const $calcPpuAdjusted = $('#calc-ppu-adjusted');
    const $calcFormulas = $('#calc-formulas');
    const $calcAdvisorDetails = $('#calc-advisor-details');

    const $residualSection = $('#residual-breakdown-section');
    const $residualConstruction = $('#residual-construction');
    const $residualEquipment = $('#residual-equipment');
    const $residualLand = $('#residual-land');
    const $residualLandUnit = $('#residual-land-unit');

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

    const inferConservation = (age) => {
        const ageNum = parseInt(age, 10);
        if (Number.isNaN(ageNum) || ageNum < 0) {
            return '';
        }

        const thresholds = Object.keys(conservationInference)
            .map(Number)
            .sort((a, b) => a - b);

        for (const threshold of thresholds) {
            if (ageNum <= threshold) {
                return String(conservationInference[String(threshold)]);
            }
        }

        return '4';
    };

    const renderComparables = (comparables) => {
        if (!Array.isArray(comparables) || comparables.length === 0) {
            $comparablesBody.html('<tr><td colspan="8" class="text-center">No hay comparables disponibles.</td></tr>');
            return;
        }

        const rows = comparables.map((item) => {
            const sourceLink = item.url
                ? `<a href="${item.url}" target="_blank" rel="noopener">Ver anuncio</a>`
                : 'N/D';

            const fre = item.homologation_factors ? formatNumber(item.homologation_factors.fre, 4) : 'N/D';
            const ppuHomol = item.ppu_homologado ? formatCurrency(item.ppu_homologado) : 'N/D';

            return `
                <tr>
                    <td>${item.title || 'Comparable'}</td>
                    <td>${formatCurrency(item.price_amount)}</td>
                    <td>${formatNumber(item.area_construction_m2, 2)}</td>
                    <td>${formatCurrency(item.ppu_m2)}</td>
                    <td>${ppuHomol}</td>
                    <td>${fre}</td>
                    <td>${item.colony || '—'}, ${item.municipality || '—'}</td>
                    <td>${sourceLink}</td>
                </tr>
            `;
        }).join('');

        $comparablesBody.html(rows);
    };

    const renderResidualBreakdown = (response) => {
        const residual = response.residual_breakdown;
        if (!residual) {
            $residualSection.hide();
            return;
        }

        $residualConstruction.text(formatCurrency(residual.construction_value));
        $residualEquipment.text(formatCurrency(residual.equipment_value));
        $residualLand.text(formatCurrency(residual.land_value));
        $residualLandUnit.text(formatCurrency(residual.land_unit_value));
        $residualSection.show();
    };

    const renderBreakdown = (response) => {
        const breakdown = response.calc_breakdown || {};
        const ppuStats = breakdown.ppu_stats || {};
        const humanSteps = Array.isArray(breakdown.human_steps) ? breakdown.human_steps : [];
        const advisorSteps = Array.isArray(breakdown.advisor_detail_steps) ? breakdown.advisor_detail_steps : [];

        const methodCode = (breakdown.method || '').toLowerCase();
        const isOpenAiMethod = methodCode.indexOf('openai') !== -1 || methodCode.indexOf('ai_augmented') !== -1 || breakdown.ai_powered === true;
        const methodLabel = methodCode.indexOf('ai_augmented') !== -1
            ? 'Algoritmo local con PPU de OpenAI (sin comparables locales)'
            : (isOpenAiMethod
                ? 'Estimación de apoyo potenciada por IA'
                : (methodCode.indexOf('synthetic') !== -1
                    ? 'Estimación de apoyo (sin suficientes comparables)'
                    : 'Comparación con propiedades similares (Excel v2)'));

        const scopeLabelMap = {
            colonia: 'Misma colonia',
            municipio: 'Mismo municipio',
            municipio_ampliado: 'Municipio ampliado',
            estado: 'Referencia estatal (Nuevo León)',
            sintetico: 'Referencia general de mercado',
        };

        const dataOrigin = breakdown.data_origin || {};
        const usedDatabase = breakdown.used_properties_database === true || dataOrigin.used_for_calculation === true;
        const aiMetadata = breakdown.ai_metadata || {};
        const aiAttempted = aiMetadata.attempted === true;
        const aiStatus = aiMetadata.status || 'no_intentado';

        if (isOpenAiMethod || aiStatus === 'success') {
            const aiDisclaimer = breakdown.valuation_factors && breakdown.valuation_factors.ai_disclaimer
                ? breakdown.valuation_factors.ai_disclaimer
                : 'Estimación orientativa generada por IA por falta de comparables locales.';
            $aiPoweredMessage.text(aiDisclaimer);
            $aiPoweredBanner.show();
        } else if (aiAttempted) {
            $aiPoweredMessage.text(`Intentamos consultar IA para mejorar este cálculo, pero no estuvo disponible (estado: ${aiStatus}). Mostramos una estimación local orientativa.`);
            $aiPoweredBanner.show();
        } else {
            $aiPoweredBanner.hide();
        }

        $calcMethod.text(methodLabel);
        $calcScope.text(scopeLabelMap[breakdown.scope_used] || breakdown.scope_used || response.location_scope || 'N/D');
        $calcCounts.text(`${breakdown.comparables_raw ?? 'N/D'} / ${breakdown.comparables_useful ?? 'N/D'}`);
        const aiStatusLabelMap = {
            success: 'Consulta IA exitosa',
            disabled: 'IA deshabilitada en .env',
            missing_api_key: 'Falta OPENAI_API_KEY',
            request_exception: 'Error de conexión/ejecución al consultar OpenAI',
            non_2xx_status: 'OpenAI respondió con error HTTP',
            invalid_json_response: 'Respuesta IA inválida (JSON)',
            empty_message_content: 'Respuesta IA vacía',
            invalid_model_payload: 'Payload IA sin formato esperado',
            invalid_amounts: 'IA no devolvió montos válidos',
            request_started: 'Consulta IA iniciada',
        };

        const aiInputSummary = aiMetadata.input_summary || 'Se enviaron características del inmueble objetivo (tipo, ubicación, superficie, edad y estado de conservación), junto con el resultado de búsqueda local sin comparables útiles.';

        $calcDbUsage.text(usedDatabase ? 'Sí. Se utilizaron comparables de la base de propiedades.' : 'No. Se usó referencia de mercado sin comparables utilizables.');
        $calcDataOrigin.text(dataOrigin.source_label || 'N/D');
        $calcAiInputs.text(aiInputSummary);
        $calcAiStatus.text(aiStatusLabelMap[aiStatus] || aiStatus || 'N/D');
        $calcPpuWeighted.text(formatCurrency(ppuStats.ppu_promedio));
        $calcPpuAdjusted.text(formatCurrency(ppuStats.ppu_aplicado));

        const comparison = breakdown.result_comparison || {};
        const originalResult = comparison.algorithm_existing || null;
        const aiAugmentedResult = comparison.ai_augmented || null;

        if (originalResult && aiAugmentedResult) {
            $resultOriginalValue.text(formatCurrency(originalResult.estimated_value));
            $resultOriginalRange.text(`Rango: ${formatCurrency(originalResult.estimated_low)} a ${formatCurrency(originalResult.estimated_high)}`);
            $resultAiValue.text(formatCurrency(aiAugmentedResult.estimated_value));
            $resultAiRange.text(`Rango: ${formatCurrency(aiAugmentedResult.estimated_low)} a ${formatCurrency(aiAugmentedResult.estimated_high)}`);
            $dualResultsComparison.show();
        } else {
            $dualResultsComparison.hide();
        }

        const formulaItems = humanSteps.length > 0
            ? humanSteps.map((item) => `<li>${item}</li>`).join('')
            : '<li>Sin explicación disponible.</li>';
        $calcFormulas.html(formulaItems);

        const advisorItems = advisorSteps.length > 0
            ? advisorSteps.map((item) => `<li>${item}</li>`).join('')
            : '<li>Sin detalle técnico disponible.</li>';
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
        renderResidualBreakdown(response);

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
                age_years: { required: true, digits: true, min: 0, max: 100 },
                conservation_level: { digits: true, min: 1, max: 10 },
                construction_unit_value: { number: true, min: 0, max: 50000 },
                equipment_value: { number: true, min: 0, max: 5000000 },
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
                age_years: {
                    required: 'Ingresa la edad del inmueble.',
                    max: 'La edad máxima es 100 años.',
                },
            },
        });
    };

    setupValidation();
    loadMunicipalities();

    $municipality.on('change blur', function () {
        loadColonies($(this).val().trim());
    });


    $advancedToggle.on('click', function (event) {
        event.preventDefault();

        const isExpanded = $(this).attr('aria-expanded') === 'true';
        const nextExpanded = !isExpanded;

        $(this).attr('aria-expanded', String(nextExpanded));
        $advancedFields.toggleClass('show', nextExpanded);
    });

    $ageYears.on('change blur', function () {
        const age = $(this).val();
        if ($conservationLevel.val() === '') {
            const inferred = inferConservation(age);
            if (inferred) {
                $conservationLevel.val(inferred);
            }
        }
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
