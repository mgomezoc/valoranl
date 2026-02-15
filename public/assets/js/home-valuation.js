(function ($) {
    'use strict';

    const config = window.ValoraNLEstimateConfig || {};
    const $form = $('#valuation-form');
    const $submit = $('#valuation-submit');
    const $errors = $('#valuation-form-errors');

    const $resultsSection = $('#valuation-results-section');
    const $message = $('#valuation-result-message');
    const $estimatedValue = $('#result-estimated-value');
    const $estimatedLow = $('#result-estimated-low');
    const $estimatedHigh = $('#result-estimated-high');
    const $confidence = $('#result-confidence');
    const $confidenceReasons = $('#result-confidence-reasons');
    const $comparablesBody = $('#comparables-table tbody');

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

        $('html, body').animate({ scrollTop: $resultsSection.offset().top - 80 }, 400);
    };

    $form.on('submit', function (event) {
        event.preventDefault();
        clearErrors();

        const areaValue = Number($('#area_construction_m2').val());
        if (!areaValue || areaValue <= 0) {
            showErrors({ area_construction_m2: 'El campo m² construcción debe ser mayor a 0.' });
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
