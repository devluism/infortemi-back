<?php

namespace App\Http\Controllers;

use App\Models\Liquidaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Tymon\JWTAuth\Facades\JWTAuth;

class WithdrawalController extends Controller
{
    public function getWithdrawals(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if ($user->admin == 1) {
            if (isset($request->id)) {
                $withdrawals = Liquidaction::where('user_id', $request->id)->with('user', 'package')->get();
                foreach ($withdrawals as $withdrawal) {
                    $withdrawal->wallet_used = Crypt::decrypt($withdrawal->wallet_used) ?? $withdrawal->wallet_used;
                    $withdrawal->hash = $withdrawal->hash ?? "";
                }
            }
            else {
                $withdrawals = Liquidaction::where('user_id', '>', 1)->with('user', 'package')->get();
                foreach ($withdrawals as $withdrawal) {
                    $withdrawal->wallet_used = Crypt::decrypt($withdrawal->wallet_used) ?? $withdrawal->wallet_used;
                    $withdrawal->hash = $withdrawal->hash ?? "";
                }
            }
        }
        else {
            $withdrawals = Liquidaction::where('user_id', $user->id)->with('user', 'package')->get();
            foreach ($withdrawals as $withdrawal) {
                $withdrawal->wallet_used = Crypt::decrypt($withdrawal->wallet_used) ?? $withdrawal->wallet_used;
                $withdrawal->hash = $withdrawal->hash ?? "";
            }
        }

        return response()->json($withdrawals, 200);
    }
}
