<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RadioCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class RadioCodeController extends Controller
{
    public function index(): View
    {
        return view('welcome');
    }

    public function search(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:4|max:40',
        ]);

        $serial = strtoupper(trim($validated['serial']));
        $results = RadioCode::query()->where('serial', $serial)->get();

        if ($results->isEmpty()) {
            return back()->with('error', 'No code found for this serial number.');
        }

        if ($results->count() === 1) {
            $result = $results->first();
            return view('result', [
                'serial'       => $serial,
                'brand'        => $result->brand,
                'car_make'     => $result->car_make,
                'radio_code_id' => $result->id,
            ]);
        }

        return view('select-model', [
            'serial'  => $serial,
            'options' => $results,
        ]);
    }

    public function selectModel(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'serial'        => 'required|string|min:4|max:40',
            'radio_code_id' => 'required|integer',
        ]);

        $serial = strtoupper(trim($validated['serial']));
        $result = RadioCode::query()->find($validated['radio_code_id']);

        if (!$result || $result->serial !== $serial) {
            return back()->with('error', 'Invalid selection.');
        }

        return view('result', [
            'serial'        => $serial,
            'brand'         => $result->brand,
            'car_make'      => $result->car_make,
            'radio_code_id' => $result->id,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'serial'        => 'required|string|min:4|max:40',
            'email'         => 'required|email|max:100',
            'radio_code_id' => 'nullable|integer',
        ]);

        $serial = strtoupper(trim($validated['serial']));

        $result = isset($validated['radio_code_id'])
            ? RadioCode::query()->find($validated['radio_code_id'])
            : RadioCode::query()->where('serial', $serial)->first();

        if (!$result) {
            return back()->with('error', 'Serial not found.');
        }

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return back()->with('error', 'Stripe is not configured on server.');
        }

        Stripe::setApiKey($secret);

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Radio Unlock Code - '.$result->car_make,
                            'description' => 'Serial: '.$serial,
                        ],
                        'unit_amount' => 299,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $validated['email'],
                'success_url' => route('payment.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('home'),
                'metadata' => [
                    'serial'        => $serial,
                    'email'         => $validated['email'],
                    'radio_code_id' => (string) $result->id,
                    'channel'       => 'web',
                ],
            ]);
        } catch (ApiErrorException $e) {
            return back()->with('error', 'Unable to start checkout. Please try again.');
        }

        Order::query()->create([
            'serial' => $serial,
            'email' => $validated['email'],
            'stripe_session_id' => $session->id,
            'amount' => 2.99,
            'currency' => 'USD',
            'status' => 'pending',
            'brand' => $result->brand,
            'car_make' => $result->car_make,
        ]);

        return redirect($session->url);
    }

    public function success(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:255',
        ]);

        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            return redirect('/')->with('error', 'Stripe is not configured on server.');
        }

        Stripe::setApiKey($secret);

        try {
            $session = Session::retrieve($validated['session_id']);
        } catch (ApiErrorException $e) {
            return redirect('/')->with('error', 'Payment session not found.');
        }

        if ($session->payment_status !== 'paid') {
            return redirect('/')->with('error', 'Payment not completed.');
        }

        $order = Order::query()->where('stripe_session_id', $session->id)->first();

        if (!$order) {
            return redirect('/')->with('error', 'Order not found.');
        }

        if ($order->status !== 'paid') {
            $result = RadioCode::query()
                ->where('serial', $order->serial)
                ->where('brand', $order->brand)
                ->where('car_make', $order->car_make)
                ->first();

            if (!$result) {
                return redirect('/')->with('error', 'Code not found for this order.');
            }

            $order->update([
                'status' => 'paid',
                'code_revealed' => $result->code,
                'stripe_payment_id' => is_string($session->payment_intent) ? $session->payment_intent : null,
            ]);
        }

        return view('success', [
            'serial' => $order->serial,
            'code' => $order->code_revealed,
            'car_make' => $order->car_make,
            'brand' => $order->brand,
        ]);
    }
}

