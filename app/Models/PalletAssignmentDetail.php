<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PalletAssignmentDetail extends Model
{
    use HasFactory;

    protected $table = 'pallet_assignment_detail';

    protected $fillable = [
        'assignment_id',
        'jancode_id',
        'packing_list_id',
        'qty_carton',
    ];

    public function assignment()
    {
        return $this->belongsTo(PalletAssignment::class, 'assignment_id');
    }

    public function jancode()
    {
        return $this->belongsTo(Jancode::class, 'jancode_id');
    }
}
