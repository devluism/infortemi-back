<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Kyc;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class KycController extends Controller
{
    public function admin() {
    	$kyc = Kyc::where('status', '>=', '0')->with('getUser')->get();

    	return response()->json($kyc, 200);
    }
	public function filterKycList(Request $request) {
        $query = Kyc::where('status', '>=', '0')->with('getUser');
        $params = false;

        if ($request->has('email') && $request->email !== null) {
            $email = $request->email;
            $query->whereHas('getUser', function($q) use($email){
                $q->where('email', $email);
            });
            $params = true;
        }

        if ($request->has('id') && $request->id !== null) {
            $query->where('user_id', $request->id);
            $params = true;
        }

        $user = $query->get();


        if(!$user || !$params) {
            return response()->json($user, 200);
        }
        return response()->json($user, 200);
    }

    public function store(Request $request) {
		
    	$us = User::find($request->auth_user_id);

    	if($us->kyc == 1) {
    		return response()->json(['msg' => 'Your KYC request has been approved already'], 200);
    	}

    	if($us->kyc === 0) {
    		return response()->json(['msg' => 'Your KYC request is in process to be checked'], 200);
    	}

    	if($us->kyc == null || $us->kyc == 2) {
	    	if($request->document != null)

		    	if ($request->hasFile('file_front') && $request->hasFile('file_back')) {

		    		$validator = Validator::make($request->all(), [
		                'file_front' => 'mimes:jpg,jpeg,png,svg',
		                'file_back' => 'mimes:jpg,jpeg,png,svg',
	            	]);

	            	if($validator->fails()){
	                    return response()->json(['msg' => 'The file must be a file type of png, jpeg, jpg or svg'], 400);
	            	}

		    		//parte frontal del documento
		            $frontal = $request->file('file_front');
		            $nombre_frontal = $request->auth_user_id.'.'.time().'.front.'.$frontal->getClientOriginalExtension();
		            $frontal->move(public_path('storage') . '/KYC/frontal/'.$request->auth_user_id.'/'.'.', $nombre_frontal);

		            //parte trasera del documento
		            $trasera = $request->file('file_back');
		            $nombre_trasera = $request->auth_user_id.'.'.time().'trasera'.'.'.'.trasera.'.$trasera->getClientOriginalExtension();
		            $trasera->move(public_path('storage') . '/KYC/trasera/'.$request->auth_user_id.'/'.'.', $nombre_trasera);

		            $data =[
		                'user_id'=> $request->auth_user_id,
		                'document'=> $request->document,
		                'file_front'=> $nombre_frontal,
		                'file_back'=> $nombre_trasera,
		                'status' => 0,
		            ];

		            $solicitudKycRechazada = Kyc::where([['user_id', $request->auth_user_id],
		                                    ['status','2']])->get()->last();

		            if(!empty($solicitudKycRechazada)){
		                $solicitudKycRechazada->delete();
		            }

		            Kyc::create($data);

		            $user = User::where('id', $request->auth_user_id)->first();
		            $user->update([
		            	'kyc' => 0
		            ]);

		            return response()->json(['msg' => 'Your KYC request has been send'], 200);
		    	}
		    	else {
		    		return response()->json(['msg' => 'Front and Back part of your document is required'], 401);
		    	}
		    else {
		    	return response()->json(['msg' => 'Choose your type of document to send the KYC request'], 401);
		    }
		}	
    }

    public function updateStatus(Request $request) {
    	$request->validate([
    		'id_user' => 'required|integer',
    		'id_kyc' => 'required|integer',
    		'status' => 'required|integer'
    	]);

    	if($request->status == 1) {
    		$user = User::find($request->id_user);
    		$kyc = Kyc::find($request->id_kyc);

    		$user->kyc = $request->status;
    		$user->save();

    		$kyc->status = $request->status;
    		$kyc->save();

    		return response()->json(['msg' => 'The KYC was confirmed'], 200);
    	} else if ($request->status == 2) {
    		$user = User::find($request->id_user);
    		$kyc = Kyc::find($request->id_kyc);

    		$user->kyc = null;
    		$user->save();
    		
    		$kyc->delete();

    		return response()->json(['msg' => 'The KYC was canceled'], 200);
    	} else {
    		return response()->json(['msg' => 'Status not found'], 400);
    	}
    }
}
