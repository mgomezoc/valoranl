<?php

namespace App\Controllers;

use App\Services\ValuationService;

class Home extends BaseController
{
    public function __construct(private readonly ValuationService $valuationService = new ValuationService())
    {
    }

    public function index(): string
    {
        return view('home', [
            'pageTitle' => 'ValoraNL | Calculadora de valuación inmobiliaria en Nuevo León',
            'metaDescription' => 'Calcula el valor estimado de una casa en Nuevo León con comparables, rango de precio y nivel de confianza.',
        ]);
    }

    public function estimate()
    {
        $postData = $this->request->getPost();
        $postData['property_type'] = 'casa';

        $rules = [
            'property_type' => 'required|in_list[casa]',
            'municipality' => 'required|string|max_length[120]',
            'colony' => 'required|string|max_length[160]',
            'area_construction_m2' => 'required|decimal|greater_than[0]',
            'area_land_m2' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'age_years' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
            'conservation_level' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[10]',
            'construction_unit_value' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[50000]',
            'equipment_value' => 'permit_empty|decimal|greater_than_equal_to[0]|less_than_equal_to[5000000]',
            'bedrooms' => 'permit_empty|integer|greater_than_equal_to[0]',
            'bathrooms' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'half_bathrooms' => 'permit_empty|integer|greater_than_equal_to[0]',
            'parking' => 'permit_empty|integer|greater_than_equal_to[0]',
            'lat' => 'permit_empty|decimal',
            'lng' => 'permit_empty|decimal',
        ];

        if (! $this->validateData($postData, $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'message' => 'Revisa los campos del formulario.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        try {
            $result = $this->valuationService->estimate($postData);

            return $this->response->setJSON($result);
        } catch (\Throwable $exception) {
            log_message('error', 'Error en valuación: {message}', ['message' => $exception->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false,
                'message' => 'Ocurrió un error al calcular la valuación. Intenta de nuevo.',
            ]);
        }
    }
}
