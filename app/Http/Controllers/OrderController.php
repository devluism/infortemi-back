<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OrderController extends Controller
{
    public function getOrdersAdmin() {
        $orders = Order::with('user')->with('project')->with('packageMembership')->get();

        $data = array();
        foreach ($orders as $order) {            
            if (isset($order->project)) {
                $phase = ($order->project->phase2 == null && $order->project->phase1 == null)
                ? ""
                : (($order->project->phase2 != null)
                ? "Phase 2"
                : "Phase 1");
            }

            $object = [
                'id' => $order->id,
                'user_id' => $order->user->id,
                'user_username' => $order->user->user_name,
                'user_email' => $order->user->email,
                'program' => $order->packageMembership->getTypeName(),
                'phase' => $phase ?? "",
                'account' => $order->packageMembership->account,
                'status' => $order->status,
                'hash_id' => $order->hash, // Hash::make($order->id)
                'amount' => $order->amount,
                'sponsor_id' => $order->user->sponsor->id,
                'sponsor_username' => $order->user->sponsor->user_name,
                'sponsor_email' => $order->user->sponsor->email,
                'date' => $order->created_at->format('Y-m-d')
            ];
            array_push($data, $object);
        }

        return response()->json(['status' => 'success', 'data' => $data, 201]);
    }
    public function filterOrders(Request $request)
    {
        $query = Order::with('user');
        $params = false;

        if ($request->has('hash') && $request->hash !== null) {
            $query->where('hash', $request->hash);
            $params = true;
        }

        if ($request->has('email') && $request->email !== null) {
            $email = $request->email;
            $query->whereHas('user', function($q) use($email){
                $q->where('email', $email);
            });
            $params = true;
        }

        $orders = $query->get();

        $data = [];

        if(!$orders || !$params) {
            return response()->json($data, 200);
        }
        foreach ($orders as $order) {
            if (isset($order->project)) {
                $phase = ($order->project->phase2 == null && $order->project->phase1 == null)
                ? ""
                : (($order->project->phase2 != null)
                ? "Phase 2"
                : "Phase 1");
            }

            $object = [
                'id' => $order->id,
                'user_id' => $order->user->id,
                'user_username' => $order->user->user_name,
                'user_email' => $order->user->email,
                'program' => $order->packageMembership->getTypeName(),
                'phase' => $phase ?? "",
                'account' => $order->packageMembership->account,
                'status' => $order->status,
                'hash_id' => $order->hash, // Hash::make($order->id)
                'amount' => $order->amount,
                'sponsor_id' => $order->user->sponsor->id,
                'sponsor_username' => $order->user->sponsor->user_name,
                'sponsor_email' => $order->user->sponsor->email,
                'date' => $order->created_at->format('Y-m-d')
            ];
            
            array_push($data, $object);
        }
        return response()->json($data, 200);
    }
}