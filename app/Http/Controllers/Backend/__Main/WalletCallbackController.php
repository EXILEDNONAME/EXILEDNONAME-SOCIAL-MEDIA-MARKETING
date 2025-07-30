<?php

namespace App\Http\Controllers\Backend\__Main;

use Auth;
use App\Http\Controllers\Controller;
use App\Http\Traits\Backend\__System\Controllers\Datatable\DefaultController;
use App\Http\Traits\Backend\__System\Controllers\Datatable\ExtensionController;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use App\Models\User;
use App\Models\Backend\__Main\Product;
use App\Models\Backend\__Main\Wallet;
use App\Models\Backend\__Main\WalletTransaction;

class WalletCallbackController extends Controller {

  public function callback(Request $request) {

    $serverKey = config('midtrans.server_key');
    $hashed = hash("sha512", $request->order_id.$request->status_code.$request->gross_amount.$serverKey);
    if($hashed == $request->signature_key){
      if($request->transaction_status == 'capture' or $request->transaction_status == 'settlement') {
        $order = WalletTransaction::where('id_order', $request->order_id);
        $order->update(['status' => 'Paid']);

        // ADD BALANCE
        $wallet = Wallet::where('id_user', $request->custom_field1)->first();
        $balance = $wallet->balance + $request->gross_amount;
        $wallet->update(['balance' => $balance]);

        $user = User::where('id', $request->custom_field1)->first();

        // AUTO MESSAGE TOP-UP
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.fonnte.com/send', CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => '', CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 0, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => array(
            'target' => env('APP_NUMBER', ''),
            'message' => '## TOP-UP NOTIFICATION
  Name : ' . $user->name . '
  Balance : ' . $request->gross_amount,
  'countryCode' => '62'),
          CURLOPT_HTTPHEADER => array('Authorization: ' . env('APP_NUMBER_SEND', '')),
        ));
        curl_exec($curl);
      }
    }
  }

}
