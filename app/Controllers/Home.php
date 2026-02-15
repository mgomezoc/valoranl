<?php

namespace App\Controllers;

use App\Libraries\ListingViewService;
use CodeIgniter\Exceptions\PageNotFoundException;

class Home extends BaseController
{
    public function __construct(private readonly ListingViewService $listingViewService = new ListingViewService())
    {
    }

    public function index(): string
    {
        $homeData = $this->listingViewService->getHomeData();

        return view('home', [
            'pageTitle'       => 'ValoraNL | Inteligencia de datos inmobiliarios en Nuevo León',
            'metaDescription' => 'Plataforma para consolidar listings, analizar mercado y estimar valor de propiedades en Nuevo León.',
            'cards'           => $homeData['cards'],
            'marketStats'     => $homeData['stats'],
        ]);
    }

    public function show(int $id): string
    {
        $listing = $this->listingViewService->getListingDetailData($id);

        if ($listing === null) {
            throw PageNotFoundException::forPageNotFound('No se encontró la propiedad solicitada.');
        }

        return view('listings/detail', [
            'pageTitle'       => ($listing['title'] ?? 'Detalle de propiedad') . ' | ValoraNL',
            'metaDescription' => 'Detalle de la propiedad con métricas de mercado y valuación estimada.',
            'listing'         => $listing,
        ]);
    }
}
