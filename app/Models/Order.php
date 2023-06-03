<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'hash',
        'image',
        'type',
        'status',
        'membership_packages_id',
        'coupon_id'
    ];

    public function user()
    {
        return $this->BelongsTo(User::class, 'user_id', 'id');
    }

    public function user_referral()
    {
        return $this->user()->referrals()->first();
    }

    public function transaction()
    {
        return $this->hasOne(FutswapTransaction::class);
    }

    public function pagueloFacilTransaction()
    {
        return $this->hasOne(PagueloFacilTransaction::class, 'order_id', 'id');
    }

    public function project()
    {
        return $this->hasOne(Project::class, 'order_id', 'id');
    }

    public function packageMembership()
    {
        return $this->hasOne(PackageMembership::class, 'id', 'membership_packages_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'id', 'order_id');
    }
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }
}
