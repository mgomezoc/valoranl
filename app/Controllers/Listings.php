<?php

namespace App\Controllers;

use App\Models\ListingModel;

class Listings extends BaseController
{
    public function __construct(private readonly ListingModel $listingModel = new ListingModel())
    {
    }

    public function index(): string
    {
        $builder = $this->listingModel->builder();
        $rows = $builder
            ->select(
                'listings.id, listings.source_id, listings.source_listing_id, listings.url, listings.status, listings.price_type, listings.price_amount, listings.currency, listings.maintenance_fee,' .
                'listings.property_type, listings.area_construction_m2, listings.area_land_m2, listings.bedrooms, listings.bathrooms, listings.half_bathrooms, listings.parking, listings.floors, listings.age_years,' .
                'listings.street, listings.colony, listings.municipality, listings.state, listings.country, listings.postal_code, listings.lat, listings.lng, listings.geo_precision,' .
                'listings.title, listings.description, listings.images_json, listings.contact_json, listings.amenities_json, listings.details_json, listings.seen_last_at, listings.updated_at,' .
                'sources.source_name'
            )
            ->join('sources', 'sources.id = listings.source_id', 'left')
            ->orderBy('listings.updated_at', 'DESC')
            ->get()
            ->getResultArray();

        $listings = [];
        $municipalities = [];
        $colonies = [];
        $propertyTypes = [];
        $statuses = [];
        $priceTypes = [];
        $sourceNames = [];

        $minPrice = null;
        $maxPrice = null;
        $minConst = null;
        $maxConst = null;
        $minLand = null;
        $maxLand = null;

        foreach ($rows as $row) {
            $price = isset($row['price_amount']) ? (float) $row['price_amount'] : null;
            $const = isset($row['area_construction_m2']) ? (float) $row['area_construction_m2'] : null;
            $land = isset($row['area_land_m2']) ? (float) $row['area_land_m2'] : null;
            $lat = isset($row['lat']) ? (float) $row['lat'] : null;
            $lng = isset($row['lng']) ? (float) $row['lng'] : null;

            if ($price !== null && $price > 0) {
                $minPrice = $minPrice === null ? $price : min($minPrice, $price);
                $maxPrice = $maxPrice === null ? $price : max($maxPrice, $price);
            }
            if ($const !== null && $const > 0) {
                $minConst = $minConst === null ? $const : min($minConst, $const);
                $maxConst = $maxConst === null ? $const : max($maxConst, $const);
            }
            if ($land !== null && $land > 0) {
                $minLand = $minLand === null ? $land : min($minLand, $land);
                $maxLand = $maxLand === null ? $land : max($maxLand, $land);
            }

            $municipality = trim((string) ($row['municipality'] ?? ''));
            $colony = trim((string) ($row['colony'] ?? ''));
            $propertyType = trim((string) ($row['property_type'] ?? ''));
            $status = trim((string) ($row['status'] ?? ''));
            $priceType = trim((string) ($row['price_type'] ?? ''));
            $sourceName = trim((string) ($row['source_name'] ?? ''));

            if ($municipality !== '') {
                $municipalities[$municipality] = true;
            }
            if ($colony !== '') {
                $colonies[$colony] = true;
            }
            if ($propertyType !== '') {
                $propertyTypes[$propertyType] = true;
            }
            if ($status !== '') {
                $statuses[$status] = true;
            }
            if ($priceType !== '') {
                $priceTypes[$priceType] = true;
            }
            if ($sourceName !== '') {
                $sourceNames[$sourceName] = true;
            }

            $pricePerM2 = ($price !== null && $const !== null && $const > 0) ? ($price / $const) : null;
            $images = json_decode((string) ($row['images_json'] ?? '[]'), true);

            $listings[] = [
                'id' => (int) $row['id'],
                'source_id' => isset($row['source_id']) ? (int) $row['source_id'] : null,
                'source_listing_id' => (string) ($row['source_listing_id'] ?? ''),
                'source_name' => $sourceName,
                'url' => (string) ($row['url'] ?? ''),
                'status' => $status,
                'price_type' => $priceType,
                'price_amount' => $price,
                'currency' => (string) ($row['currency'] ?? 'MXN'),
                'maintenance_fee' => isset($row['maintenance_fee']) ? (float) $row['maintenance_fee'] : null,
                'property_type' => $propertyType,
                'area_construction_m2' => $const,
                'area_land_m2' => $land,
                'bedrooms' => isset($row['bedrooms']) ? (int) $row['bedrooms'] : null,
                'bathrooms' => isset($row['bathrooms']) ? (float) $row['bathrooms'] : null,
                'half_bathrooms' => isset($row['half_bathrooms']) ? (float) $row['half_bathrooms'] : null,
                'parking' => isset($row['parking']) ? (int) $row['parking'] : null,
                'floors' => isset($row['floors']) ? (int) $row['floors'] : null,
                'age_years' => isset($row['age_years']) ? (int) $row['age_years'] : null,
                'street' => (string) ($row['street'] ?? ''),
                'colony' => $colony,
                'municipality' => $municipality,
                'state' => (string) ($row['state'] ?? ''),
                'country' => (string) ($row['country'] ?? ''),
                'postal_code' => (string) ($row['postal_code'] ?? ''),
                'lat' => $lat,
                'lng' => $lng,
                'geo_precision' => (string) ($row['geo_precision'] ?? 'unknown'),
                'title' => (string) ($row['title'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'images' => is_array($images) ? array_values(array_filter($images, static fn($v): bool => is_string($v) && $v !== '')) : [],
                'price_per_m2' => $pricePerM2,
                'contact_json' => (string) ($row['contact_json'] ?? ''),
                'amenities_json' => (string) ($row['amenities_json'] ?? ''),
                'details_json' => (string) ($row['details_json'] ?? ''),
                'seen_last_at' => isset($row['seen_last_at']) ? (string) $row['seen_last_at'] : null,
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            ];
        }

        ksort($municipalities);
        ksort($colonies);
        ksort($propertyTypes);
        ksort($statuses);
        ksort($priceTypes);
        ksort($sourceNames);

        return view('listings/index', [
            'pageTitle' => 'ValoraNL | Catalogo de propiedades',
            'metaDescription' => 'Explora todas las propiedades de la base de ValoraNL con filtros, busqueda avanzada y mapa interactivo.',
            'listings' => $listings,
            'filterOptions' => [
                'municipalities' => array_keys($municipalities),
                'colonies' => array_keys($colonies),
                'property_types' => array_keys($propertyTypes),
                'statuses' => array_keys($statuses),
                'price_types' => array_keys($priceTypes),
                'sources' => array_keys($sourceNames),
            ],
            'ranges' => [
                'price_min' => $minPrice,
                'price_max' => $maxPrice,
                'const_min' => $minConst,
                'const_max' => $maxConst,
                'land_min' => $minLand,
                'land_max' => $maxLand,
            ],
        ]);
    }
}
