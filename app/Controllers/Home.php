<?php

namespace App\Controllers;

use App\Models\ListingModel;
use App\Services\ValuationService;

class Home extends BaseController
{
    public function __construct(
        private readonly ValuationService $valuationService = new ValuationService(),
        private readonly ListingModel $listingModel = new ListingModel()
    ) {
    }

    public function index(): string
    {
        $baseUrl = rtrim(base_url('/'), '/');
        $schema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $baseUrl . '#organization',
                    'name' => 'ValoraNL',
                    'url' => $baseUrl,
                    'logo' => base_url('assets/img/valoranl/logo-valoranl.png'),
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $baseUrl . '#website',
                    'name' => 'ValoraNL',
                    'url' => $baseUrl,
                    'inLanguage' => 'es-MX',
                ],
            ],
        ];

        return view('home', [
            'pageTitle' => 'ValoraNL | Calculadora de valuacion inmobiliaria en Nuevo Leon',
            'metaDescription' => 'Calcula el valor estimado de una casa en Nuevo Leon con comparables, rango de precio y nivel de confianza.',
            'canonicalUrl' => current_url(),
            'ogType' => 'website',
            'ogImage' => base_url('assets/img/valoranl/logo-valoranl.png'),
            'schemaJsonLd' => $schema,
        ]);
    }

    public function sitemap()
    {
        $urls = [
            [
                'loc' => base_url('/'),
                'lastmod' => gmdate('c'),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => base_url('propiedades'),
                'lastmod' => gmdate('c'),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
        ];

        $rows = $this->listingModel
            ->select('id, updated_at')
            ->orderBy('updated_at', 'DESC')
            ->findAll();

        foreach ($rows as $row) {
            $updatedAt = ! empty($row['updated_at']) ? strtotime((string) $row['updated_at']) : false;
            $urls[] = [
                'loc' => url_to('listings.show', (int) $row['id']),
                'lastmod' => $updatedAt ? gmdate('c', $updatedAt) : gmdate('c'),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', (string) $url['loc']);
            $xml->writeElement('lastmod', (string) $url['lastmod']);
            $xml->writeElement('changefreq', (string) $url['changefreq']);
            $xml->writeElement('priority', (string) $url['priority']);
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $this->response
            ->setHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->setBody($xml->outputMemory());
    }

    public function robots()
    {
        $body = implode("\n", [
            'User-agent: *',
            'Disallow:',
            'Sitemap: ' . base_url('sitemap.xml'),
            '',
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->setBody($body);
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
            'address' => 'permit_empty|max_length[300]',
            'lat' => 'required|decimal',
            'lng' => 'required|decimal',
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
            log_message('error', 'Error en valuacion: {message}', ['message' => $exception->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON([
                'ok' => false,
                'message' => 'Ocurrio un error al calcular la valuacion. Intenta de nuevo.',
            ]);
        }
    }
}
