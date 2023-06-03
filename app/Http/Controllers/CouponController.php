<?php

namespace App\Http\Controllers;

use App\Http\Requests\CouponStoreRequest;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    public function create(CouponStoreRequest $request) {
        $user = JWTAuth::parseToken()->authenticate();
        try {
            DB::beginTransaction();
            if ($user->affiliate != '2') {
                if ($request->percentage  == 5 || $request->percentage == 10) {
                    $coupon = Coupon::create([
                        'buyer_id' => $user->id,
                        'percentage' => $request->percentage,
                        'stock' => 1,
                        'code' => Str::random(8)
                    ]);
                    $dataMail = [
                        'code' => $coupon->code,
                    ];
                    Mail::send('mails.CouponCreate', $dataMail,  function ($msj) use ($user) {
                        $msj->subject('Coupon Create!');
                        $msj->to($user->email);
                    });
                } else {
                    return response()->json(['status' => 'error', 'message' => 'The percentage of the ticket is not valid!'], 400);
                }
            } else {
                if ($request->percentage == 5 || $request->percentage == 10 || $request->percentage == 20 ) {
                    $coupon = Coupon::create([
                        'buyer_id' => $user->id,
                        'percentage' => $request->percentage,
                        'stock' => 1,
                        'code' => Str::random(8)
                    ]);
                    $dataMail = [
                        'code' => $coupon->code,
                    ];
                    Mail::send('mails.CouponCreate', $dataMail,  function ($msj) use ($user) {
                        $msj->subject('Coupon Create!');
                        $msj->to($user->email);
                    });
                } else {
                    return response()->json(['status' => 'error', 'message' => 'The percentage of the ticket is not valid!'], 400);
                }
            }
            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Coupon Created!'], 200);
        } catch (\Throwable $th) {
            Log::error($th);
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $th], 400);
        }
    }
    public function validateCoupon(Request $request) {
        $user = JWTAuth::parseToken()->authenticate();
        $validator = Validator::make($request->all(), [
            'codeCoupon' => 'required',
        ]);
        if ($validator->fails()) return response()->json($validator->errors()->toJson(), 400);
        $userCoupon = Coupon::where('user_id', $user->id)->where('stock', '>', 0)->first();
        if ($userCoupon != null) return response()->json(['status' => 'warning', 'message' => 'You already have an active coupon'], 400);     
        $coupon = Coupon::where('code', $request->codeCoupon)
            ->where('stock', '>', 0)
            ->where('user_id', null)
            ->where('buyer_id', '!=', $user->id)
            ->first();
        if ($coupon != null){
            if ($coupon->buyer_id == $user->padre->id) {
                $coupon->user_id = $user->id;
                $coupon->save();
                return response()->json(['status' => 'success', 'data' => ['percentage' => $coupon->percentage, 'message' => 'Coupon Used!']], 200);
            } else {
                return response()->json(['status' => 'error', 'message' => 'You are not referred to the creator of the coupon'], 400);
            }
        } else {
            $coupon = Coupon::where('code', $request->codeCoupon)->where('stock', '>', 0)->first();
            if ($coupon != null) {
                if ($coupon->buyer_id == $user->id) {
                    return response()->json(['status' => 'warning', 'message' => 'You cannot use a coupon that you have created'], 400);
                } 
            }
            return response()->json(['status' => 'error', 'message' => 'This Coupon is not available'], 400);
        }
    }
    public function checkUserCouponActive(Request $request) {
        $user = JWTAuth::parseToken()->authenticate();
        $userCoupon = Coupon::where('user_id', $user->id)->where('stock', '>', 0)->first();
        if ($userCoupon != null) {
            return response()->json(['status' => 'warning', 'data' => ['percentage' => $userCoupon->percentage, 'message' => 'You already have an active coupon']], 400);
        } else {
            return response()->json(['status' => 'success', 'message' => 'Not Coupon Active'], 200);
        }
    }
}
