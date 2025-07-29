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

class WalletController extends Controller {

  function __construct() {
    $this->middleware(['auth', 'verified']);
    $this->model = 'App\Models\Backend\__Main\Wallet';
    $this->path = 'pages.backend.__main.wallet.';
    $this->url = '/dashboard/wallets';
    $this->data = $this->model::get();
  }

  /**
  **************************************************
  * @return INDEX
  **************************************************
  **/

  public function index() {
    $model = $this->model;
    if (request()->ajax()) {
      return DataTables::of(WalletTransaction::where('id_user', Auth::User()->id)->where('status', 'Paid')->orderby('created_at', 'desc')->get())
      ->editColumn('date_start', function ($order) { return empty($order->date_start) ? NULL : \Carbon\Carbon::parse($order->date_start)->format('d F Y, H:i'); })
      ->editColumn('date_end', function ($order) { return empty($order->date_end) ? NULL : \Carbon\Carbon::parse($order->date_end)->format('d F Y, H:i'); })
      ->editColumn('date', function ($order) { return empty($order->created_at) ? NULL : \Carbon\Carbon::parse($order->created_at)->format('d F Y, H:i'); })
      ->editColumn('description', function ($order) { return nl2br(e($order->description)); })
      ->editColumn('status', function ($order) {
        if($order->status == 'Unpaid') { return '<span class="label label-danger label-inline mr-2"> Gagal </span>'; }
        if($order->status == 'Paid') { return '<span class="label label-success label-inline mr-2"> Selesai </span>'; }
      })
      ->editColumn('balance', function ($order) {
        if($order->status == 'Unpaid') { return '<span class="text-danger"> Rp ' . number_format($order->balance, 2, ",", ".") . '</span>'; }
        if($order->status == 'Paid') { return '<span class="text-success"> + Rp ' . number_format($order->balance, 2, ",", ".") . '</span>'; }
      })
      ->editColumn('rate', function ($order) { return "Rp " . number_format($order->price * 1000, 2, ",", "."); })
      ->rawColumns(['description', 'balance', 'status'])
      ->addIndexColumn()->make(true);
    }
    return view($this->path . 'index', compact('model'));
  }

  public function checkout(Request $request) {
    $request->validate(
      ['balance' => 'required|numeric|min:10|max:50000'],
      [
        'balance.min'    => 'Minimal Top Up Rp 1.000',
        'balance.max'    => 'Maksimal Top Up Rp 50.000',
      ],
    );
    $userId = Auth::id();
    $url = $this->url;
    $number = time();
    $request->request->add([
      'id_order'      => $number,
      'id_user' => Auth::User()->id,
      'status' => 'Unpaid',
    ]);
    $order = WalletTransaction::create($request->all());
    $database = WalletTransaction::orderby('created_at', 'desc')->first();

    \Midtrans\Config::$serverKey = config('midtrans.server_key');
    \Midtrans\Config::$isProduction = config('midtrans.is_production');
    \Midtrans\Config::$isSanitized = true;
    \Midtrans\Config::$is3ds = true;

    $params = array(
      'transaction_details' => array(
        'order_id' => $number,
        'gross_amount' => $request->balance,
      ),
      'customer_details' => array(
        'id' => Auth::user()->id,
        'firts_name' => Auth::user()->name,
      ),
      "custom_field1" => Auth::id(),
    );



    $snapToken = \Midtrans\Snap::getSnapToken($params);
    return view($this->path . 'checkout', compact('snapToken', 'order', 'url', 'userId', 'database'));
  }

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
          CURLOPT_HTTPHEADER => array('Authorization: ' . env('APP_API', '')),
        ));
        curl_exec($curl);
      }
    }
  }

}
