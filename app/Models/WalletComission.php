<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;

class WalletComission extends Model
{
    use HasFactory;
    protected $table = 'wallets_commissions';
    protected $fillable = [
        'user_id',
        'buyer_id',
        'level',
        'description',
        'membership_id',
        'amount',
        'amount_retired',
        'amount_available',
        'amount_last_liquidation',
        'type',
        'liquidation_id',
        'status',
        'avaliable_withdraw',
        'order_id',
        'liquidado'
    ];

    // protected function status(): Attribute {
    //     return new Attribute(
    //         get: fn($value) => ['Available', 'Requested', 'Paid', 'Voided', 'Subtracted'][$value],
    //     );
    // }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
    /**
     * Permite obtener la orden de esta comision
     * @return void
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
    public function project()
    {
        return $this->order->project ?? null;
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
    public function package()
    {
        return $this->belongsTo(Project::class, 'membership_id');
    }

    /**
     * Permite obtener al usuario de una comision
     * @return void
     */
    public function getWalletUser()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Permite obtener al referido de una comision
     * @return void
     */
    public function getWalletReferred()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

}
