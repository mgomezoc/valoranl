<?php

namespace App\Libraries;

use App\Models\ListingModel;

class ListingViewService
{
    public function __construct(private readonly ListingModel $listingModel = new ListingModel())
    {
    }

    /**
     * @return array{cards: array<int, array<string, mixed>>, stats: array<string, mixed>}
     */
    public function getHomeData(int $latestLimit = 12, int $statsSample = 400): array
    {
        $latestListings = $this->listingModel->getLatestListings($latestLimit);
        $statsSampleRows = $this->listingModel->getListingsForMarketStats($statsSample);

        $marketStats = $this->buildMarketStats($statsSampleRows);
        $cards = $this->buildListingCards($latestListings, $marketStats['medianPpu']);

        return [
            'cards' => $cards,
            'stats' => $marketStats,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getListingDetailData(int $id): ?array
    {
        $listing = $this->listingModel->findWithSource($id);

        if ($listing === null) {
            return null;
        }

        $area = (float) ($listing['area_construction_m2'] ?? 0);
        $price = (float) ($listing['price_amount'] ?? 0);
        $ppu = $area > 0 ? $price / $area : null;

        $listing['price_per_m2'] = $ppu;
        $listing['estimated_low'] = $ppu !== null ? $ppu * $area * 0.92 : null;
        $listing['estimated_high'] = $ppu !== null ? $ppu * $area * 1.08 : null;

        return $listing;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildMarketStats(array $rows): array
    {
        $prices = [];
        $ppus = [];
        $activeCount = 0;
        $municipalityCounts = [];

        foreach ($rows as $row) {
            $price = (float) ($row['price_amount'] ?? 0);
            $area = (float) ($row['area_construction_m2'] ?? 0);
            $status = strtolower((string) ($row['status'] ?? 'unknown'));
            $municipality = trim((string) ($row['municipality'] ?? ''));

            if ($price > 0) {
                $prices[] = $price;
            }

            if ($price > 0 && $area > 0) {
                $ppus[] = $price / $area;
            }

            if ($status === 'active') {
                $activeCount++;
            }

            if ($municipality !== '') {
                $municipalityCounts[$municipality] = ($municipalityCounts[$municipality] ?? 0) + 1;
            }
        }

        arsort($municipalityCounts);

        return [
            'totalListings' => count($rows),
            'activeListings' => $activeCount,
            'activeRate' => count($rows) > 0 ? ($activeCount / count($rows)) * 100 : 0,
            'medianPrice' => $this->median($prices),
            'medianPpu' => $this->median($ppus),
            'topMunicipality' => (string) (array_key_first($municipalityCounts) ?? 'N/D'),
            'topMunicipalityCount' => (int) (array_values($municipalityCounts)[0] ?? 0),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function buildListingCards(array $rows, float $marketMedianPpu): array
    {
        $cards = [];

        foreach ($rows as $row) {
            $images = json_decode((string) ($row['images_json'] ?? '[]'), true) ?: [];
            $coverImage = $images[0] ?? base_url('assets/img/property_img_1.jpg');
            $price = (float) ($row['price_amount'] ?? 0);
            $area = (float) ($row['area_construction_m2'] ?? 0);
            $ppu = ($price > 0 && $area > 0) ? $price / $area : null;
            $location = trim((string) (($row['colony'] ?? '') . ', ' . ($row['municipality'] ?? '')), ', ');

            $cards[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? 'Propiedad sin título'),
                'coverImage' => (string) $coverImage,
                'price' => $price,
                'currency' => (string) ($row['currency'] ?? 'MXN'),
                'location' => $location !== '' ? $location : 'Ubicación no disponible',
                'propertyType' => (string) ($row['property_type'] ?? 'N/D'),
                'sourceName' => (string) ($row['source_name'] ?? 'No definida'),
                'bedrooms' => $row['bedrooms'] ?? null,
                'bathrooms' => $row['bathrooms'] ?? null,
                'parking' => $row['parking'] ?? null,
                'areaConstructionM2' => $area > 0 ? $area : null,
                'pricePerM2' => $ppu,
                'marketTag' => $this->getMarketTag($ppu, $marketMedianPpu),
            ];
        }

        return $cards;
    }

    private function getMarketTag(?float $ppu, float $marketMedianPpu): string
    {
        if ($ppu === null || $marketMedianPpu <= 0) {
            return 'Sin referencia';
        }

        $ratio = $ppu / $marketMedianPpu;

        return match (true) {
            $ratio < 0.9 => 'Oportunidad',
            $ratio > 1.1 => 'Arriba del mercado',
            default => 'En mercado',
        };
    }

    /**
     * @param array<int, float> $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 !== 0) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}
