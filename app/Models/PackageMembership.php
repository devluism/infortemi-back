<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageMembership extends Model
{
    use HasFactory;

    const FTY_EVALUATION = 1;
    const FTY_FAST = 2;
    const FTY_ACCELERATED = 3;

    protected $fillable = [
        'account',
        'assured_funding',
        'amount',
        'type'
    ];

    public function getTypeName() {
        $array = ['Evaluation', 'Fast', 'Accelerated'];
        return $array[intval($this->type) - 1];
    }
}
