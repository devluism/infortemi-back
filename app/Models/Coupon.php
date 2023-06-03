<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = ['percentage','stock', 'code', 'buyer_id', 'user_id'];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function buyer(){
        return $this->belongsTo(User::class);
    }
    public function order()
    {
        return $this->hasOne(Order::class, 'coupon_id');
    }
}
