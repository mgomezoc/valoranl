<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingModel extends Model
{
    protected $table            = 'listings';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'source_id',
        'source_listing_id',
        'url',
        'url_normalized',
        'url_hash',
        'fingerprint_hash',
        'dedupe_hash',
        'status',
        'price_type',
        'price_amount',
        'currency',
        'maintenance_fee',
        'property_type',
        'area_construction_m2',
        'area_land_m2',
        'bedrooms',
        'bathrooms',
        'half_bathrooms',
        'parking',
        'floors',
        'age_years',
        'street',
        'colony',
        'municipality',
        'state',
        'country',
        'postal_code',
        'lat',
        'lng',
        'geo_precision',
        'title',
        'description',
        'images_json',
        'contact_json',
        'amenities_json',
        'details_json',
        'raw_json',
        'source_first_seen_at',
        'source_last_seen_at',
        'seen_first_at',
        'seen_last_at',
    ];

    protected $validationRules = [
        'source_id' => 'permit_empty|integer',
        'title' => 'permit_empty|string|max_length[255]',
        'status' => 'permit_empty|in_list[active,inactive,sold,unknown]',
        'price_type' => 'permit_empty|in_list[sale,rent,unknown]',
        'currency' => 'permit_empty|string|max_length[10]',
    ];

    /**
     * @return array<int, string>
     */
    public function getDistinctPropertyTypes(): array
    {
        $rows = $this->select('property_type')
            ->where('property_type IS NOT NULL', null, false)
            ->where('property_type !=', '')
            ->groupBy('property_type')
            ->orderBy('property_type', 'ASC')
            ->findAll();

        return array_values(array_map(static fn(array $row): string => (string) $row['property_type'], $rows));
    }

    /**
     * @return array<int, string>
     */
    public function getDistinctMunicipalities(): array
    {
        $rows = $this->select('municipality')
            ->where('municipality IS NOT NULL', null, false)
            ->where('municipality !=', '')
            ->groupBy('municipality')
            ->orderBy('municipality', 'ASC')
            ->findAll();

        return array_values(array_map(static fn(array $row): string => (string) $row['municipality'], $rows));
    }
}
