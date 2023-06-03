<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormularyStoreRequest;
use App\Models\Coupon;
use App\Models\PackageMembership;
use App\Models\User;
use App\Models\Order;
use App\Models\Project;
use App\Models\Formulary;
use App\Models\WalletComission;
use App\Services\FutswapService;
use App\Http\Resources\ProjectResource;
use App\Services\PagueloFacilService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class PackageMembershipController extends Controller
{
    private $pagueloFacil;
    private $futswap;

    public function __construct(FutswapService $futswapService = null, PagueloFacilService $pagueloFacil)
    {
        $this->futswap = $futswapService;
        $this->pagueloFacil = $pagueloFacil;
    }
    public function GetPackageMemberships($email)
    {
        $user = User::where('email', $email)->first();
        $orders = Order::where('user_id', $user->id)->with('project', 'packageMembership')->get();
        $packageMemberships = PackageMembership::all();

        foreach ($packageMemberships as $package) {
            foreach ($orders as $order) {
                if ($package->id == $order->membership_packages_id) {
                    $package->status = $order->status;
                    break;
                } else {
                    $package->status = null;
                }
            }
        }

        return response()->json(['status' => 'success', 'data' => ['packages' => $packageMemberships]], 201);
    }

    public function filterAdminReports(Request $request)
    {
        $query = Project::with('order');

        $params = false;

        if ($request->has('email') && $request->email !== null) {

            $email = $request->email;

            $query->whereHas('order', function ($q) use ($email) {
                $q->whereHas('user', function ($a) use ($email) {
                    $a->where('email', $email);
                });
            });

            $params = true;
        }

        if ($request->has('id') && $request->id !== null) {

            $id = $request->id;

            $query->whereHas('order', function ($q) use ($id) {
                $q->whereHas('user', function ($a) use ($id) {
                    $a->where('id', $id);
                });
            });
            $params = true;
        }

        $data = $query->get();

        $projects = ProjectResource::collection($data);
        return response()->json(['status' => 'success', 'data' => $projects, 201]);
    }

    public function formularyUpdate(Request $request)
    {
        $project = Project::find($request->project_id);


        $project->formulary->update([
            'name' => $request->name != "" ? $request->name : $project->formulary->name,
            'login' => $request->login != "" ? $request->login : $project->formulary->login,
            'password' => $request->password != "" ? Crypt::encryptString($request->password) : $project->formulary->password,
            'leverage' => $request->leverage != "" ? $request->leverage : $project->formulary->leverage,
            'balance' => $request->balance && $request->balance != 0 ? $request->balance : $project->formulary->balance,
            'server' => $request->serverr != "" ? $request->serverr : $project->formulary->server,
            'date' => $request->date != "" ? $request->date : $project->formulary->date,
        ]);
        $dataEmail = [
            'user' => $project->order->user->fullName(),
            'name' => $request->name,
            'login' => $request->login,
            'password' => $request->password,
            'leverage' => $request->leverage,
            'balance' => $request->balance,
            'server' => $request->serverr,
            'date' => $request->date
        ];
        Mail::send('mails.sendCredentials',  ['data' => $dataEmail], function ($msj) use ($project) {
            $msj->subject('Update Project Credentials.');
            $msj->to($project->order->user->email);
        });
        return response()->json(['status' => 'success', 'Successful, Formulary Updated!', 201]);
    }

    public function BuyPackage(Request $request)
    {

        $user = JWTAuth::parseToken()->authenticate();

        if(!$user->can_buy_fast) {
            return response()->json(["message" => "You do not have permission to purchase these packages please contact support."],403);
        }

        // $pending = Order::where('user_id', $user->id)->where('status', '0')->first();

        // if (isset($pending)) {
        //     return response()->json(['status' => 'error', 'message' => 'You have a pending package to pay'], 400);
        // }
        
        $package = PackageMembership::where('id', $request->package)->first();
        $amount = $package->amount;
        $percent = $package->amount * 0.10;
        $coupon = Coupon::where('user_id', $user->id)->where('stock', '>', '0')->first();

        if ($coupon) {
            $amount = $amount - (($coupon->percentage * $amount) / 100);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'amount' => $request->status == 3 ? $amount - $percent : $amount,
            'membership_packages_id' => $package->id,
            'type' => $request->status == 3 ? 'renovacion' : 'inicio'
        ]);

        if ($request->platform === 'futswap') {
            $response = $this->futswap->createOrden($user, intval($order->amount), $order->id);
        } elseif ($request->platform === 'paguelofacil') {
            $amount_with_commission = $order->amount * 1.05;
            $response = $this->pagueloFacil->makeTransaction($user->id, $order->id, $amount_with_commission);
        }

        if ($response[0] != 'error') {
            if ($coupon) {
                $coupon->stock -= 1;
                $coupon->save();
                $order->coupon_id = $coupon->id;
                $order->save();
            }
            $dataMail = [
                'email' => $user->email,
                'date' => now()->format('Y-m-d')
            ];

            Mail::send('mails.orderCreate', $dataMail,  function ($msj) {
                $msj->subject('Order Create!');
                $msj->to('admin@fyt.com');
            });

            //redirecciona a la url del pago
            return response()->json(['status' => 'success', 'data' => ['url' => $response, 'message' => "Successful, redirecting to $request->platform"]], 201);
            
        } else {
            return response()->json(['status' => 'error', 'message' => 'There was an error', 'data' => ['url' => $response[1]]], 400);
        }
    }

    public function GetProjectsAdmin()
    {
        $projects = ProjectResource::collection(Project::with('order')->orderBy('id', 'desc')->with('formulary')->get());
        return response()->json(['status' => 'success', 'data' => $projects, 201]);
    }

    public function GetProjectAdmin(Request $request)
    {
        $project = new ProjectResource(Project::findOrFail($request->id));
        return response()->json(['status' => 'success', 'data' => $project, 201]);
    }

    public function updateStatusProject(Request $request)
    {
        $project = Project::find($request->id);
        $user_referred = User::whereId($project->order->user_id)->first();
        $user_coupons = Coupon::whereUser_id($project->order->user_id)->get();
        $coupons = count($user_coupons);
        if ($request->status == '2') {
            if ($project->order->packageMembership->type != '1') {
                $project->status = $request->status;
                $project->save();

                // Programa Fast o Accelerated aprovado
                if ($project->order->packageMembership->type == '3')
                {
                    $dataEmail = [
                        'user' => $project->order->user->fullName(),
                        'program' => 'ACCELERATED CHALLENGE',
                    ];
                    Mail::send('mails.programApproved',  ['data' => $dataEmail], function ($msj) use ($project) {
                        $msj->subject('Accelerated Chanllenge Approved.');
                        $msj->to($project->order->user->email);
                    });
                } else {
                    $dataEmail = [
                        'user' => $project->order->user->fullName(),
                        'program' => 'FAST CHALLENGE',
                    ];
                    Mail::send('mails.programApproved',  ['data' => $dataEmail], function ($msj) use ($project) {
                        $msj->subject('Fast Chanllenge Approved.');
                        $msj->to($project->order->user->email);
                    });
                }

            } else {
                if ($project->status == 1) {
                    $project->status = 2;
                    $project->phase2 = 1;
                    $project->save();

                    // PHASE 2 aprovada del programa Evaluation
                    $dataEmail = [
                        'user' => $project->order->user->fullName(),
                    ];
                    Mail::send('mails.evaluation2Approved',  ['data' => $dataEmail], function ($msj) use ($project) {
                        $msj->subject('Evaluation Chanllenge Phase 2 Approved.');
                        $msj->to($project->order->user->email);
                    });

                } else {
                    $project->status = 1;
                    $project->phase1 = 1;
                    $project->save();
                    // PHASE 1 aprovada del programa Evaluation
                    $dataEmail = [
                        'user' => $project->order->user->fullName(),
                    ];
                    Mail::send('mails.evaluation1Approved',  ['data' => $dataEmail], function ($msj) use ($project) {
                        $msj->subject('Evaluation Chanllenge Phase 1 Approved.');
                        $msj->to($project->order->user->email);
                    });
                }
            }
        } else {
            $project->status = $request->status;
            $project->save();

            // Pograma rechazado
            $phase = isset($project->phase2) ? ' PHASE 2' : (isset($project->phase1) ? ' PHASE 1' : '');
            $dataEmail = [
                'user' => $project->order->user->fullName(),
                'program' => $project->order->packageMembership->getTypeName().$phase,
                'description' => $request->description,
            ];
            Mail::send('mails.unsuccessfulTest',  ['data' => $dataEmail], function ($msj) use ($project) {
                $msj->subject('Unsuccessful Test.');
                $msj->to($project->order->user->email);
            });
        }
        if ($project->status == 1 || $project->status == 2) {
            if ($project->order->packageMembership->type == '1') {
                if ($user_referred->buyer_id == '1' && $coupons == '0') {
                    $this->crateWallet($project);
                }
            }
        }

        return response()->json(['status' => 'success', 'data' => ['project' => $project, 'message' => 'Successful, updated status'], 201]);
    }

    public function formularyCreate(FormularyStoreRequest $request)
    {

        $project = Project::find($request->project_id);

        Formulary::create([
            'project_id' => $project->id,
            'name' => $request->name,
            'login' => $request->login,
            'password' => Crypt::encryptString($request->password),
            'leverage' => $request->leverage,
            'balance' => $request->balance,
            'server' => $request->serverr,
            'date' => $request->date,
        ]);
        $dataEmail = [
            'user' => $project->order->user->fullName(),
            'name' => $request->name,
            'login' => $request->login,
            'password' => $request->password,
            'leverage' => $request->leverage,
            'balance' => $request->balance,
            'server' => $request->serverr,
            'date' => $request->date
        ];

        Mail::send('mails.sendCredentials',  ['data' => $dataEmail], function ($msj) use ($project) {
            $msj->subject('Project Credentials.');
            $msj->to($project->order->user->email);
        });


        return response()->json(['status' => 'success', 'Successful, Formulary Created!', 201]);
    }

    private function crateWallet($project)
    {
        WalletComission::create([
            'user_id' => $project->order->user_id,
            'buyer_id' => 1,
            'membership_id' => $project->id,
            'order_id' => $project->order->id,
            'description' => 'Retorno 100%',
            'type' => 3, //tipo retorno de inversión
            'level' => '1',
            'status' => 0,
            'avaliable_withdraw' => 0,
            'amount_available' => $project->order->amount,
            'amount' => $project->order->amount,
        ]);
    }
}
