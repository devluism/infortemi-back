<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletComission;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Crypt;

class WalletController extends Controller
{
    public function addBalanceToUser(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:users',
            'email' => 'required|email|exists:users',
            'amount' => 'required'
        ]);
        $user = User::find($request->id);

        $wallet = WalletComission::create([
            'user_id' => $user->id,
            'buyer_id' => $user->padre->id,
            'membership_id' => null,
            'order_id' => null,
            'description' => 'Bono asignado',
            'type' => '1',
            'level' => 1,
            'status' => 0,
            'avaliable_withdraw' => 0,
            'amount_available' => $request->amount,
            'amount' => $request->amount,
        ]);
        return $wallet;
    }

    public function refundsList(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if ($user->admin == 1) {
            $refunds = WalletComission::where('type', 3)
                ->with('order', 'user')
                ->get();
        } else {
            $refunds = WalletComission::where('type', 3)
                ->where('user_id', $request->auth_user_id)
                ->with('order', 'user')
                ->get();
        }
        $data = array();
        foreach ($refunds as $refund) {
            $item = [
                'id' => $refund->id,
                'program' => 'FYT ' . ucfirst(strtolower($refund->order->packageMembership->getTypeName())) . $refund->order->packageMembership->account,
                'status' => $this->getStatus($refund),
                'status_name' => $this->getStatusName($this->getStatus($refund)),
                'amount' => $refund->amount,
                'amount_available' => $refund->amount_available,
                'created_at' => $refund->created_at->format('d-m-Y')
            ];
            if ($user->admin == 1) {
                $item['name'] = $refund->user->name;
                $item['last_name'] = $refund->user->last_name;
                $item['user_name'] = $refund->user->user_name;
                $item['email'] = $refund->user->email;
            };
            array_push($data, $item);
        }
        return response()->json(['status' => 'success', 'data' => $data, 201]);
    }

    private function getStatus($refund)
    {
        $package = $refund->order->packageMembership->type;
        $project = $refund->order->project;

        if ($package == "3" || $package == "2") {
            return !empty($project->formularies) ? intval($project->formularies->status) : 0;
        }
        if ($package == "1") {
            return !empty($project->formularies[1]) ? intval($project->formularies[1]->status) : 0;
        }
    }

    public function getStatusName($status)
    {
        $array = ['Pending', 'Passed', 'Not Approved', 'Expired'];
        return $array[$status];
    }

    public function getRefunds()
    {
        $user = JWTAuth::parseToken()->authenticate();
        if ($user->admin == 1) {
            $refunds = WalletComission::where('type', 3)->with('package')->with('order')->get();
        }
        else {
            $refunds = WalletComission::where([['user_id', $user->id], ['type', 3]])->with('package')->with('order')->get();
        }

        $data = array();
        foreach ($refunds as $refund) {
            if (isset($refund->order->project)) {
                $phase = ($refund->order->project->phase2 == null && $refund->order->project->phase1 == null)
                ? ""
                : (($refund->order->project->phase2 != null)
                ? "Phase 2"
                : "Phase 1");
            }

            $object = [
                'id' => $refund->id,
                'user' => $refund->user,
                'program' => $refund->order->packageMembership->getTypeName(),
                'phase' => $phase ?? "",
                'account' => $refund->order->packageMembership->account,
                'amount' => $refund->amount,
                'date' => $refund->created_at,
            ];
            array_push($data, $object);
        }

        return response()->json($data, 201);
    }

    public function devolutionsAdmin()
    {
        $devolutions = WalletComission::where('type', 3)->with('package', 'order')->get(['id', 'amount', 'membership_id', 'created_at']);

        return response()->json($devolutions, 200);
    }

    public function getWallets(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (isset($request->user_id))
        {
            $user = User::findOrFail($request->user_id);
        }

        $wallets = WalletComission::where('user_id', $user->id)->get();

        $data = new Collection();
        foreach ($wallets as $wallet) {
            $buyer = User::find($wallet->buyer_id);

            switch ($wallet->type) {
                case '0':
                    $type = 'Referral I';
                    break;

                case '1':
                    $type = 'Assigned';
                    break;

                case '2':
                    $type = 'Referral II';
                    break;

                default:
                    $type = 'Refund';
                    break;
            }

            $object = new \stdClass();
            $object->id = $wallet->id;
            $object->buyer = ($wallet->type != 3) ? ucwords(strtolower($buyer->name . " " . $buyer->last_name)) : 'FYT '.$wallet->order->packageMembership->getTypeName();
            $object->type = $type;
            $object->amount = number_format($wallet->amount, 2);
            $object->status = $wallet->status;
            $object->date = $wallet->created_at;
            $data->push($object);
        }
        return response()->json($data, 200);
    }

    public function getTotalAvailable(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (isset($request->user_id))
        {
            $user = User::findOrFail($request->user_id);
        }

        $amount = WalletComission::where('user_id', $user->id)->where('status', '0')->sum('amount_available');

        $data = [
            'text' => number_format($amount,2,'.',','),
            'number' => $amount
        ];

        return response()->json($data,200);
    }

    public function getTotalDirects(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (isset($request->user_id))
        {
            $user = User::findOrFail($request->user_id);
        }

        $amount = WalletComission::where('user_id', $user->id)
                                    ->where('status', '0')
                                    ->where('type', '0')
                                    ->where('level', 1)
                                    ->sum('amount_available');

        return response()->json(number_format($amount,2,'.',','),200);
    }

    public function checkWalletUser(Request $request) 
    {
        $user = JWTAuth::parseToken()->authenticate();
        if (isset($request->user_id))
        {
            $user = User::findOrFail($request->user_id);
        }

        $data = [];

        if ($user->wallet) {

            $data['bool'] = true;
            $data['wallet'] = Crypt::decrypt($user->wallet);

            return response()->json($data,200);
        } else {

            $data['bool'] = false;
            $data['wallet'] = '';

            return response()->json($data,200);
        }
    }
}
