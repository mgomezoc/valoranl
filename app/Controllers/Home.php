<?php

namespace App\Controllers;

use App\Models\ListingModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Home extends BaseController
{
    public function __construct(private readonly ListingModel $listingModel = new ListingModel())
    {
    }

    public function index(): string
    {
        $listings = $this->listingModel->getLatestListings();

        return view('home', [
            'pageTitle'       => 'ValoraNL | Propiedades en Nuevo León',
            'metaDescription' => 'Consulta propiedades consolidadas desde múltiples portales inmobiliarios.',
            'listings'        => $listings,
        ]);
    }

    public function show(int $id): string
    {
        $listing = $this->listingModel->findWithSource($id);

        if ($listing === null) {
            throw PageNotFoundException::forPageNotFound('No se encontró la propiedad solicitada.');
        }

        return view('listings/detail', [
            'pageTitle'       => ($listing['title'] ?? 'Detalle de propiedad') . ' | ValoraNL',
            'metaDescription' => 'Detalle completo de la propiedad seleccionada en ValoraNL.',
            'listing'         => $listing,
        ]);
    }
}
