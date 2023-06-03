<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TreController;
use App\Http\Requests\UserStoreRequest;
use App\Mail\ForgotPasswordNotification;
use App\Mail\PasswordChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use App\Models\Prefix;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    protected $treController;

    public function __construct(TreController $treController)
    {
        $this->treController = $treController;
    }
    /**
     * The method for registering a new user
     * @param Request
     */
    public function register(UserStoreRequest $request)
    {
        $binary_side = 'R';
        $binary_id = 1;

        if ($request->has('binary_side')) $binary_side = $request->binary_side;


        if ($request->has('buyer_id')) {
            $userFather = User::findOrFail($request->buyer_id);
            $binary_id = $this->treController->getPosition(intval($request->buyer_id), $binary_side);
        }


        try {
            DB::beginTransaction();
            $data = [
                'name' => $request->user_name,
                'last_name' => $request->user_lastname,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'email' => $request->email
            ];
            
            
            $user = User::create([
                'name' => $request->user_name,
                'last_name' => $request->user_lastname,
                'binary_id' => $binary_id,
                'email' => $request->email,
                'email_verified_at' => now(),
                'binary_side' => $binary_side,
                'buyer_id' => $request->buyer_id ?? 1,
                'prefix_id' => $request->prefix_id,
                'status' => '0',
                'code_security' => Str::random(12),
                'phone' => $request->phone,
            ]);
            $user->user_name = strtolower(explode(" ", $request->user_name)[0][0]."".explode(" ", $request->user_lastname)[0])."#".$user->id;

            $url = config('services.backend_auth.base_uri');

            $response = Http::withHeaders([
                'apikey' => config('services.backend_auth.key'),
            ])->post("{$url}register", $data);

            if ($response->successful()) {
                $res = $response->object();
                $user->update(['id' => $res->user->id]);
                $dataEmail = [ 'user' => $user];


                Mail::send('mails.verification',  ['data' => $dataEmail], function ($msj) use ($request){
                    $msj->subject('Email verification.');
                    $msj->to($request->email);
                });
                DB::commit();

                return response()->json([$user], 201);
            }
            DB::rollback();
            $response = ['Error' => 'Error registering user'];

            return response()->json($response, 500);

        } catch (\Throwable $th) {
            Log::error($th);
            DB::rollback();
            $response = ['Error' => 'Error registering user'];
            return response()->json($response, 500);
        }
    }
    /**
     * Get a JWT via given credentials.
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $url = config('services.backend_auth.base_uri');

        $credentials = $request->only('email', 'password');

        $response = Http::withHeaders([
            'apikey' => config('services.backend_auth.key'),
        ])->post("{$url}login", $credentials);
        
        $responseObject = $response->object();

        $user = User::where('email', $request->email)->first();

        if ($responseObject->success && $user) {
            if(!$user->email_verified_at) {    
                $user->update(['code_security' => Str::random(12)]);            
                $dataEmail = [ 'user' => $user];
                
                Mail::send('mails.verification',  ['data' => $dataEmail], function ($msj) use ($request){
                    $msj->subject('E-mail verification.');
                    $msj->to($request->email);
                });

                return response()->json(['message' => 'Unverified email', 'email' => $user->email],200);
            }

            DB::beginTransaction();
            $user->token_jwt = $responseObject->token;
            $user->save();
            $data = [
                'id' => $user->id,
                'user_name' => $user->user_name,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'affiliate' => $user->affiliate,
                'admin' => $user->admin,
                'email_verified_at' => $user->email_verified_at,
                'api_token' => $responseObject->token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'message' => 'Successful login.'
            ];

            DB::commit();
            return response()->json($data, 200);
        }

        DB::rollBack();
        return response()->json(['message' => $responseObject->message],400);
    }

    public function logout(Request $request)
    {
        $token = request()->bearerToken();

        $user = User::findOrFail($request->auth_user_id);

        $url = config('services.backend_auth.base_uri');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'apikey' => config('services.backend_auth.key'),
        ])->post("{$url}logout");

        if($response->successful()) 
        {
            $user->update(['token_jwt' => null]);
            return response()->json(['status' => 'success', 'message' => 'You have successfully logged out'], 200);
        }

    }
    /**
     * Genera un token de seguridad que es enviado al usuario via email para confirmar el restablecimiento de contraseña
     * @param  \Iluminate\Http\Request $request
     * @return Json La respuesta en formato Json
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 200);
        }

        $user = User::where('email', $request->email)->first();

        $token = Str::random(12);
        $user->update(['code_security' => $token]);

        Mail::to($user->email)->send(new ForgotPasswordNotification($token, $user->email));

        $response = ['status' => 'success', 'message' => 'We have sent a security code to your email address.'];

        return response()->json($response, 200);
    }
    /**
     * Confirma el token de seguridad y envia la petición al authJWT para cambiar la contraseña
     * @param  \Iluminate\Http\Request $request
     * @return Json La respuesta en formato Json
     */
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => [
                'required', 'string', 'confirmed',
                Password::min(8) // Debe tener por lo menos 8 caracteres
                    ->mixedCase() // Debe tener mayúsculas + minúsculas
                    ->letters() // Debe incluir letras
                    ->numbers() // Debe incluir números
                    ->symbols(), // Debe incluir símbolos,
            ],
            'code_security' => 'required|exists:users'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()],400);
        }

        $user = User::where('code_security', $request->code_security)->first();

        $data = ['email' => $user->email, 'password' => $request->password];

        $url = config('services.backend_auth.base_uri');
        // Enviamos la data al backend auth para actualizarla
        $response = Http::withHeaders([
            'apikey' => config('services.backend_auth.key'),
        ])->post("{$url}update-password", $data);

        $responseObject = $response->object();

        if($responseObject->status) {    

            Mail::to($user->email)->send(new PasswordChangedNotification());

            $user->update(['code_security' => null]);
    
            $response = ['status' => 'success', 'message' => $responseObject->message];
    
            return response()->json($response, 200);
        }
    }
    // Ruta para probar el funcionamiento de la validación doble con JWT en otro servidor
    public function test(Request $request) 
    {
        $user = User::findOrFail($request->auth_user_id);
        dd($user);
    }
    /**
     * Confirma el correo del usuario al momento de crearse una cuenta.
     * @param  \Iluminate\Http\Request $request
     * @return Json La respuesta en formato Json
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_security' => 'required|exists:users,code_security',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::where('code_security', $request->code_security)->first();

        $data = ['email' => $user->email];

        $url = config('services.backend_auth.base_uri');

        $response = Http::withHeaders([
            'apikey' => config('services.backend_auth.key'),
        ])->post("{$url}verify-email", $data);

        if($response->successful()) 
        {
            $resObj = $response->object();

            $user->update([
                'email_verified_at' => $resObj->time,
                'code_security' => null
            ]);

            $dataEmail = [ 'user' => $user->fullName()];

            Mail::send('mails.welcome',  ['data' => $dataEmail], function ($msj) use ($user){
                $msj->subject('Welcome to FYT.');
                $msj->to($user->email);
            });

            return response()->json(['status' => 'success', 'message' => 'Mail successfully verified'], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Failed mail verification'], 400);
    }
    /**
     * Obtiene la lista de prefijos (paises) para el formulario de registro.
     * @param  \Iluminate\Http\Request $request
     * @return Json La respuesta en formato Json
     */
    public function getPrefixes(Request $request)
    {
        $data = ['prefixes' => Prefix::all()];
        return response()->json($data, 200);
    }
    /**
     * Genera un nuevo codigo de seguridad y lo envia al usuario para confirmar su correo.
     * @param  \Iluminate\Http\Request $request
     * @return Json La respuesta en formato Json
     */
    public function sendEmailVerificationCode(Request $request)
    {
        $request->validate([
            'email' => 'required|exists:users'
        ]);

        $user = User::where('email', $request->email)->first();

        $user->update(['code_security' => Str::random(12)]);

        $dataEmail = [ 'user' => $user];

        Mail::send('mails.verification',  ['data' => $dataEmail], function ($msj) use ($request){
            $msj->subject('Email verification.');
            $msj->to($request->email);
        });


        return response()->json(['message' => 'We have sent a new code to your email'], 200);
    }

    /**
     * Ruta para verificar que el token es valido
     * @param \Iluminate\Http\Request $request
     * @return Json 
     */ 
    public function verifyToken(Request $request)
    {
        $user = User::findOrFail($request->auth_user_id);

        $data = [
            'id' => $user->id,
            'user_name' => $user->user_name,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'admin' => $user->admin,
            'affiliate' => $user->affiliate,
            'email_verified_at' => $user->email_verified_at,
            'api_token' => $user->token_jwt,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'message' => 'Successful login.'
        ];
        return response()->json($data, 200);
    }

    public function getSponsorName($id)
    {
        $user = User::find($id);
        if($user) {
            $name = "$user->name $user->last_name";
            return response()->json($name, 200);
        }
        return response()->json(['message' => 'invalid referral id'], 400);
    }
}
