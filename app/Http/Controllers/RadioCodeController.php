<?php

namespace App\Http\Controllers;

use App\Models\RadioCode;
use App\Models\Order;
use Illuminate\Http\Request;

class RadioCodeController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function search(Request $request)
    {
        $request->validate([
            'serial' => 'required|min:4|max:40',
        ]);

        $serial = strtoupper(trim($request->serial));

        $result = RadioCode::where('serial', $serial)->first();

        if (!$result) {
            return back()->with('error', 'No code found for this serial number.');
        }

        return view('result', [
            'serial'   => $serial,
            'brand'    => $result->brand,
            'car_make' => $result->car_make,
            'found'    => true,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'serial' => 'required',
            'email'  => 'required|email',
        ]);

        $serial = strtoupper(trim($request->serial));
        $result = RadioCode::where('serial', $serial)->first();

        if (!$result) {
            return back()->with('error', 'Serial not found.');
        }

        // Stripe checkout session létrehozása
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Radio Unlock Code — ' . $result->car_make,
                        'description' => 'Serial: ' . $serial,
                    ],
                    'unit_amount' => 299,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'customer_email' => $request->email,
            'success_url' => route('payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => route('result', ['serial' => $serial]),
            'metadata' => [
                'serial' => $serial,
                'email'  => $request->email,
            ],
        ]);

        // Order mentése
        Order::create([
            'serial'            => $serial,
            'email'             => $request->email,
            'stripe_session_id' => $session->id,
            'amount'            => 2.99,
            'status'            => 'pending',
            'brand'             => $result->brand,
            'car_make'          => $result->car_make,
        ]);

        return redirect($session->url);
    }

    public function success(Request $request)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $session = \Stripe\Checkout\Session::retrieve($request->session_id);

        if ($session->payment_status !== 'paid') {
            return redirect('/')->with('error', 'Payment not completed.');
        }

        $order = Order::where('stripe_session_id', $session->id)->first();

        if ($order && $order->status !== 'paid') {
            $result = RadioCode::where('serial', $order->serial)->first();
            $order->update([
                'status'        => 'paid',
                'code_revealed' => $result->code,
            ]);
        }

        return view('success', [
            'serial'   => $order->serial,
            'code'     => $order->code_revealed,
            'car_make' => $order->car_make,
            'brand'    => $order->brand,
        ]);
    }
}

