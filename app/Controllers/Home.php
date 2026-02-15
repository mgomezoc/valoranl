<?php

namespace App\Controllers;

use App\Models\ListingModel;
use App\Services\ValuationService;

class Home extends BaseController
{
    public function __construct(
        private readonly ValuationService $valuationService = new ValuationService(),
        private readonly ListingModel $listingModel = new ListingModel(),
    ) {
    }

    public function index(): string
    {
        return view('home', [
            'pageTitle' => 'ValoraNL | Calcula el valor estimado de tu casa en Nuevo León',
            'metaDescription' => 'Calcula una valuación estimada por comparables para casas y departamentos en Nuevo León. Recibe rango, confianza y propiedades similares.',
            'propertyTypes' => $this->listingModel->getDistinctPropertyTypes(),
            'municipalities' => $this->listingModel->getDistinctMunicipalities(),
        ]);
    }

    public function estimate()
    {
        $rules = [
            'property_type' => 'required|string|max_length[80]',
            'municipality' => 'required|string|max_length[120]',
            'colony' => 'required|string|max_length[160]',
            'area_construction_m2' => 'required|decimal|greater_than[0]',
            'area_land_m2' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'bedrooms' => 'permit_empty|integer|greater_than_equal_to[0]',
            'bathrooms' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'half_bathrooms' => 'permit_empty|integer|greater_than_equal_to[0]',
            'parking' => 'permit_empty|integer|greater_than_equal_to[0]',
            'lat' => 'permit_empty|decimal',
            'lng' => 'permit_empty|decimal',
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok' => false,
                'message' => 'Revisa los campos del formulario.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        try {
            $result = $this->valuationService->estimate($this->request->getPost());

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
