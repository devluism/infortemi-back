<?php

namespace App\Http\Controllers;

use App\Mail\CodeSecurity;
use App\Models\Liquidaction;
use App\Models\User;
use App\Models\Prefix;
use App\Models\ProfileLog;
use App\Models\WalletComission;
use App\Rules\ChangePassword;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{
    public function getUsersWalletsList()
    {
        $users = User::where('id', '!=', 1)->get();
        $data = [];
        foreach ($users as $user) {
            $amount = $user->wallets->where('status', '0')->sum('amount_available');
            $data[] = [
                'id' => $user->id,
                'userName' => $user->user_name,
                'email' => $user->email,
                'status' => $user->status,
                'affiliate' => $user->getAffiliateStatus(),
                'balance' => number_format($amount, 2, ',', '.'),
            ];
        }
        return response()->json($data, 200);
    }

    public function getFilterUsersWalletsList(Request $request)
    {
        $query = User::where('admin', '0');
        $params = false;

        if ($request->has('email') && $request->email !== null) {
            $query->where('email', $request->email);
            $params = true;
        }

        if ($request->has('id') && $request->id !== null) {
            $query->where('id', $request->id);
            $params = true;
        }

        $users = $query->get();

        $data = [];

        foreach ($users as $user) {
            $amount = $user->wallets->where('status', 'Available')->sum('amount_available');
            $data[] = [
                'id' => $user->id,
                'userName' => $user->user_name,
                'email' => $user->email,
                'status' => $user->status,
                'affiliate' => $user->getAffiliateStatus(),
                'balance' => number_format($amount, 2, ',', '.'),
            ];
        }
        return response()->json($data, 200);
    }

    public function filterUsersWalletsList(Request $request)
    {
        $query = Liquidaction::where('user_id', '>', 1)->with('user', 'package');
        $params = false;

        if ($request->has('email') && $request->email !== null) {
            $email = $request->email;
            $query->whereHas('user', function($q) use($email){
                $q->where('email', $email);
            });
            $params = true;
        }

        if ($request->has('id') && $request->id !== null) {
            $id = $request->id;
            $query->whereHas('user', function($q) use($id){
                $q->where('id', $id);
            });
            $params = true;
        }

        $withdrawals = $query->get();

        $data = [];

        if($withdrawals->count() == 0 || !$params) {
            return response()->json($data, 200);
        }

        foreach ($withdrawals as $withdrawal) {
            $withdrawal->wallet_used = Crypt::decrypt($withdrawal->wallet_used) ?? $withdrawal->wallet_used;
            $withdrawal->hash = $withdrawal->hash ?? str_pad($withdrawal->id, 4, '0', STR_PAD_LEFT);
        }

        return response()->json($withdrawals, 200);
    }
    public function filterUsersList(Request $request)
    {
        $user = User::where('admin', '0')
            ->get()
            ->values('id', 'user_name', 'email', 'affiliate', 'created_at');

        $query = User::where('admin', '0');
        $params = false;

        if ($request->has('email') && $request->email !== null) {
            $query->where('email', $request->email);
            $params = true;
        }

        if ($request->has('id') && $request->id !== null) {
            $query->where('id', $request->id);
            $params = true;
        }

        $user = $query->get()->values('id', 'user_name', 'email', 'affiliate', 'created_at');


        if(!$user || !$params) {
            return response()->json($user, 200);
        }
        return response()->json($user, 200);
    }

    public function GetCountry(){
        $paises = Prefix::all();
        return response()->json($paises, 200);
    }

    public function getUser(Request $request)
    {
        $user = User::with('prefix')->findOrFail($request->auth_user_id);
        return response()->json($user, 200);

    }

	public function ChangeData(Request $request) {
		$user = User::find($request->auth_user_id);
        $log = new ProfileLog;

		$request->validate([
            'name'        => ['nullable', 
            		   		  'string', 
            		   		  'max:255'],
            'last_name'   => ['nullable', 
            		   		  'string', 
            		   		  'max:255'],
            'email'       => ['nullable',
                              'email',
            				  'max:255',],
            'phone'       => 'nullable',
            'prefix_id'   => 'nullable',
            'profile_picture' => 'required'
        ]);

        $data = [
            'id' => $request->auth_user_id, 
            'name' => $request->name,
            'last_name' => $request->last_name,
            'email' => $request->email];

        $url = config('services.backend_auth.base_uri');

        $response = Http::withHeaders([
            'apikey' => config('services.backend_auth.key'),
        ])->post("{$url}change-data", $data);

        $responseObject = $response->object();

        if($responseObject->status) {
            if ($user->status_change == 1) {
                $request->email == null || $request->email == ''
                    ? $user->email = $user->email
                    : $user->email = $request->email; 

                $user->status_change = null;
                $user->code_security = null;  
            }

            if ($request->hasFile('profile_picture')){
                
                $picture = $request->file('profile_picture');
                $name_picture = $request->auth_user_id.'.'.$picture->getClientOriginalName();
                $picture->move(public_path('storage') . '/profile/picture/'.$request->auth_user_id.'/'.'.', $name_picture);

                $user->profile_picture = $name_picture;
            }

            $request->name == null || $request->name == ''
                ? $user->name = $user->name
                : $user->name = $request->name;

            $request->last_name == null || $request->last_name == ''
                ? $user->last_name = $user->last_name
                : $user->last_name = $request->last_name;

            $request->user_name == null || $request->user_name == ''
                ? $user->user_name = $user->user_name
                : $user->user_name = $request->user_name;

            $request->phone == null || $request->phone == ''
                ? $user->phone = $user->phone
                : $user->phone = $request->phone;

            $request->prefix_id == null || $request->prefix_id == ''
                ? $user->prefix_id = $user->prefix_id
                : $user->prefix_id = $request->prefix_id;

            $user->update();

            $log->create([
                'user' => $user->id,
                'subject' => 'Profile Data updated',
            ]);

            return response()->json('Profile Data updated', 200);
        }
	}

    public function ChangePassword(Request $request) {
    	$request->validate([
            'current_password' => ['required', new ChangePassword($request->auth_user_id)],
            'new_password' => [
                'required', 'string',
                Password::min(8)
                        ->mixedCase()
                        ->letters()
                        ->numbers()
                        ->symbols(),
            ],
            'confirm_password' => ['same:new_password'],
        ]);

        $log = new ProfileLog;

        $data = ['id' => $request->auth_user_id, 'password' => $request->new_password];

        $url = config('services.backend_auth.base_uri');

        $response = Http::withHeaders([
            'apikey' => config('services.backend_auth.key'),
        ])->post("{$url}change-password", $data);

        $responseObject = $response->object();

        if($responseObject->status) {
            $log->create([
                'user' => $responseObject->user_id,
                'subject' => 'Password Updated',
            ]);

            return response()->json('Password Updated', 200);
        } else {
            return response()->json('error', 401);
        }
    }

    public function CheckCodeToChangeEmail(Request $request) {
        $request->validate([
            'code_security' => 'required|string',
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::find($request->auth_user_id);

        if (Carbon::parse($user->code_verified_at)->addHour()->isPast()) {
            $user->update([
                'code_security' => null,
            ]);
            $response = ['message' => 'Expired code'];
            return response()->json($response, 422);
        }

        if (Hash::check($request->code_security, $user->code_security)) {
            $data = [
                'id' => $request->auth_user_id, 
                'email' => $request->email,
                'password' => $request->password,];

            $url = config('services.backend_auth.base_uri');

            $response = Http::withHeaders([
                'apikey' => config('services.backend_auth.key'),
            ])->post("{$url}check-credentials-email", $data);

            $responseObject = $response->object();

            if($responseObject->status) {
                $user->update(['status_change' => 1]);
                return response()->json('Authorized credentials', 200);
            }
        } else {
            $user->update(['status_change' => 0]);
            $response = ['message' => 'Code is not valid'];
            return response()->json($response, 422);
        }
    }

    public function SendSecurityCode(Request $request) {
    	$user = User::find($request->auth_user_id);
        $log = new ProfileLog;
        $code = Str::random(12);
        $code_encrypted = Hash::make($code);

        $user->update([
        	'code_security' => $code_encrypted,
            'code_verified_at' => Carbon::now(),
        ]);
        
        $log->create([
            'user' => $user->id,
            'subject' => 'Request code security',
        ]);

        Mail::to($user->email)->send(new CodeSecurity($code));

        return response()->json('Code send succesfully', 200);
    }

    public function getUsers()
    {
        $users = User::where('admin', '0')->get()->values('id', 'user_name', 'email', 'affiliate', 'created_at');

        return response()->json($users, 200);
    }

    public function updateUserAffiliate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->email)->update(['affiliate' => $request->affiliate]);

        return response()->json(['status' => 'success', 'message' => 'User updated!'], 200);
    }

    public function auditUserWallets(Request $request)
    {
        $wallets = WalletComission::where('user_id', $request->user_id)->get();

        if (count($wallets) > 0) {
            $data = new Collection();

            foreach ($wallets as $wallet) {
                $buyer = User::find($wallet->buyer_id);
                
                switch ($wallet->status) {                    
                    case 'Requested':
                        $tag = 'warning';
                        break;
                    
                    case 'Paid':
                        $tag = 'primary';
                        break;
                    
                    case 'Voided':
                        $tag = 'danger';
                        break;
                        
                    case 'Subtracted':
                        $tag = 'secondary';
                        break;
                        
                    default:
                        $tag = 'success';
                        break;
                }
                
                $object = new \stdClass();
                $object->id = $wallet->id;
                $object->buyer = ucwords(strtolower($buyer->name." ".$buyer->last_name));
                $object->amount = $wallet->amount;
                $object->status = ['title' => $wallet->status, 'tag' => $tag];
                $object->date = $wallet->created_at->format('m/d/Y');
                $data->push($object);
            }
            return response()->json($data, 200);
        }
        return response()->json(['status' => 'warning', 'message' => "This user don't have any wallet"], 200);
    }
    public function auditUserProfile(Request $request)
    {
        $user = User::with('prefix')->findOrFail($request->user_id);
        return response()->json($user, 200);
    }
    public function auditUserDashboard(Request $request)
    {
        $user = User::with('prefix')->findOrFail($request->user_id);
        // Falta presentar las metricas del usuario
        // Esto devuelve datos generales
        return response()->json($user, 200);
    }

    public function toggleUserCanBuyFast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users',
            'can_buy_fast' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 400);
        }

        User::where('email', $request->email)->first()->update(['can_buy_fast' => $request->can_buy_fast]);

        return response()->json(['status' => 'success', 'message' => 'User updated!'], 200);
    }

    
}
