<?php

namespace App\Models;

use CodeIgniter\Model;

class SourceModel extends Model
{
    protected $table          = 'sources';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'source_code',
        'source_name',
        'base_url',
        'is_active',
    ];

    protected $validationRules = [
        'source_code' => 'required|string|max_length[50]',
        'source_name' => 'required|string|max_length[100]',
    ];
}
