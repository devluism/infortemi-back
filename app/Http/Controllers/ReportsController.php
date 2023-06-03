<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Liquidaction;
use App\Models\Order;
use App\Models\PagueloFacilTransaction;
use App\Models\Project;
use App\Models\WalletComission;
use App\Services\BonusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    protected $bonusService;

    public function __construct(BonusService $bonusService)
    {
        $this->bonusService = $bonusService;
    }

    public function updateOrders(Request $request)
    {
        Log::info($request->all());

        $request = json_decode(json_encode($request->all()));

        $data = [
            'order_id' => $request->customFields->xtrn_or_id,
            'user_id' => $request->customFields->xtrn_us_id,
            'status' => $request->status,
            'total_pay' => $request->totalPay,
            'request_pay_amount' => $request->requestPayAmount,
            'operation_code' => $request->codOper,
            'display_num' => $request->displayNum,
            'date' => $request->date,
            'operation_type' => $request->operationType,
            'return_url' => $request->returnUrl
        ];

        try {
            DB::beginTransaction();

            $order = Order::findOrFail($data['order_id']);

            if ($data['status'] == 1) {
                $order->status = '1';
                $order->hash = $data['operation_code'];
                if($order->user->affiliate == '0')
                {
                    $order->user->affiliate = '1';
                }
                $order->user->status = '1';
                $order->user->save();
            } else {
                $order->status = '3';
                $order->hash = $data['operation_code'];
            }

            $order->save();

            $pagueloFacilTransaction = PagueloFacilTransaction::where('order_id', $order->id)->first();

            $pagueloFacilTransaction->fill($data);

            $pagueloFacilTransaction->save();
            
            $projectStatus = ($order->membership_packages_id < 5 && $order->membership_packages_id > 7)
                ? 2
                : 0;

            Project::create([
                'order_id' => $order->id,
                'amount' => $order->amount,
                'status' => $projectStatus,
            ]);

            if ($order->type == 'inicio') {
                $this->bonusService->directBonus($order->user, $order->amount, $order->user_id, $order);
            }

            DB::commit();
            Log::info("La orden {$order->id} y transacción {$pagueloFacilTransaction} han sido actualizadas correctamente");
        } catch (\Throwable $th) {
            DB::rollback();
            Log::error('Error updating the order from PageloFacilHook');
            Log::error($th);
        }
    }
    public function commision()
    {
        $comisions = WalletComission::with('user')->with('package')->where('type', '!=', 3)->orderBy('id', 'desc')->get();

        return response()->json($comisions, 200);
    }
    public function refund()
    {
        $refund = WalletComission::with('user')->with('package')->where('type', 3)->get();

        return response()->json($refund, 200);
    }
    public function filterComissionList(Request $request)
    {
        $query = WalletComission::with('user')->with('package');
        $params = false;
        if ($request->has('email') && $request->email !== null) {
            $email = $request->email;
            $query->whereHas('user', function ($q) use ($email) {
                $q->where('email', $email);
            });
            $params = true;
        }

        if ($request->has('status') && $request->status !== null && $request->status != 'All') {
            $query->where('status', $request->status);
            $params = true;
        }

        $comission = $query->get();


        if (!$comission || !$params) {
            return response()->json($comission, 200);
        }
        return response()->json($comission, 200);
    }
    public function liquidaction()
    {
        $liquidactions = Liquidaction::with('user')->get();

        return response()->json($liquidactions, 200);
    }

    public function coupons()
    {
        $coupons = Coupon::with('user')->get();

        return response()->json($coupons, 200);
    }
}
