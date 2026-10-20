<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PalletAddress extends Model
{
    use HasFactory;

    protected $table = 'pallet_address';

    protected $fillable = [
        'row_label',
        'col_label',
        'position_no',
        'code',
        'max_carton',
        'country_mode',
    ];

    public function isMultiCountry(): bool
    {
        return $this->country_mode === 'multi';
    }

    public function assignment()
    {
        return $this->hasOne(PalletAssignment::class, 'pallet_address_id');
    }
}
