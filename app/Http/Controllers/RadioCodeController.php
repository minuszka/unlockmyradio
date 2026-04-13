<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\RadioCode;
use App\Support\RadioCodeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class RadioCodeController extends Controller
{
    public function __construct(private readonly RadioCodeResolver $resolver)
    {
    }

    public function index(): View
    {
        return view('welcome');
    }

    public function search(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
        ]);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);
        $results = $this->resolver->findCandidates($inputSerial);

        if ($results->isEmpty()) {
            return back()->with('error', 'No code found for this serial number.');
        }

        if ($results->count() === 1) {
            $result = $results->first();

            return view('result', [
                'serial' => $inputSerial,
                'brand' => $result->brand,
                'car_make' => $result->car_make,
                'radio_code_id' => $result->id,
            ]);
        }

        return view('select-model', [
            'serial' => $inputSerial,
            'options' => $results,
        ]);
    }

    public function selectModel(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
            'radio_code_id' => 'required|integer',
        ]);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);
        $result = RadioCode::query()->find($validated['radio_code_id']);

        if (!$result || !$this->resolver->serialMatchesResult($inputSerial, $result)) {
            return back()->with('error', 'Invalid selection.');
        }

        return view('result', [
            'serial' => $inputSerial,
            'brand' => $result->brand,
            'car_make' => $result->car_make,
            'radio_code_id' => $result->id,
        ]);
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'serial' => 'required|string|min:6|max:64',
            'email' => 'required|email|max:100',
            'radio_code_id' => 'nullable|integer',
        ]);

        $inputSerial = $this->resolver->normalizeSerial($validated['serial']);

        if (isset($validated['radio_code_id'])) {
            $result = RadioCode::query()->find($validated['radio_code_id']);

            if (!$result || !$this->resolver->serialMatchesResult($inputSerial, $result)) {
                return back()->with('error', 'Invalid radio model selection for this serial.');
            }
        } else {
            $results = $this->resolver->findCandidates($inputSerial);

            if ($results->isEmpty()) {
                return back()->with('error', 'Serial not found.');
            }

            if ($results->count() > 1) {
                return view('select-model', [
                    'serial' => $inputSerial,
                    'options' => $results,
                ]);
            }

            $result = $results->first();
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
                            'description' => 'Serial: '.$inputSerial,
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
                    'serial_input' => $inputSerial,
                    'serial_lookup' => $result->serial,
                    'email' => $validated['email'],
                    'radio_code_id' => (string) $result->id,
                    'channel' => 'web',
                ],
            ]);
        } catch (ApiErrorException $e) {
            return back()->with('error', 'Unable to start checkout. Please try again.');
        }

        Order::query()->create([
            'serial' => $result->serial,
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

        $displaySerial = isset($session->metadata->serial_input)
            ? (string) $session->metadata->serial_input
            : $order->serial;

        return view('success', [
            'serial' => $displaySerial,
            'code' => $order->code_revealed,
            'car_make' => $order->car_make,
            'brand' => $order->brand,
        ]);
    }
}
