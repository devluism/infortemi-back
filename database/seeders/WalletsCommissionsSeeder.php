<?php

namespace Database\Seeders;

use App\Models\WalletComission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class WalletsCommissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        WalletComission::create([
            'type'=> 3,
            'user_id'=> 2,
            'order_id'=> 1,
            'type'=> 3,
        ]);
    }
}
