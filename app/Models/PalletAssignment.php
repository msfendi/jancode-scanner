<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PalletAssignment extends Model
{
    use HasFactory;

    protected $table = 'pallet_assignment';

    protected $fillable = [
        'pallet_address_id',
        'style',
        'country',
        'ocf',
        'total_carton',
    ];

    public function address()
    {
        return $this->belongsTo(PalletAddress::class, 'pallet_address_id');
    }

    public function details()
    {
        return $this->hasMany(PalletAssignmentDetail::class, 'assignment_id');
    }
}
