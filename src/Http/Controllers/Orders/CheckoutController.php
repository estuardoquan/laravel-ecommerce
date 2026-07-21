<?php

namespace EQ\LaravelEcommerce\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AddressStoreRequest;
use App\JsonApi\Proxies\ShopOrder;
use App\Models\Address;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CheckoutController extends Controller
{
    private function get_payment_token()
    {
        try {
            $response = Http::asJson()->post(env('N1CO_API_URL') . '/token', [
                'clientId' => env('N1CO_CLIENT_ID'),
                'clientSecret' => env('N1CO_CLIENT_SECRET'),
            ]);
        } catch (\Throwable $th) {
            return null;
        }

        if (!$response->successful()) abort($response->status());

        return $response->json();
    }

    public function store_address(AddressStoreRequest $request, Order $order)
    {
        $address = new Address($request->validated());

        $address->addressable()->associate($request->user());

        $request->session()->put('selected_address', $address);

        return to_route('checkout.render', $order);
    }

    public function update_order_address(Request $request, Order $order)
    {
        $request->validate([
            'address_id' => [
                'required',
            ],
        ]);

        $address = Address::find($request->get('address_id'));

        $request->session()->put('selected_address', $address);

        return to_route('checkout.render', $order);
    }

    public function update_order_payment(Request $request, Order $order)
    {
        $request->validate([
            'number' => [
                'required'
            ],
            'expirationMonth' => [
                'required'
            ],
            'expirationYear' => [
                'required'
            ],
            'cvv' => [
                'required'
            ],
            'cardHolder' => [
                'required'
            ],
        ]);

        $token_response = $this->get_payment_token();
        $t = $token_response['tokenType'] . ' ' . $token_response['accessToken'];

        $user = $request->user()->only('id', 'email', 'name');

        $data = [
            'customer' => [
                ...$user,
                'phoneNumber' => '000',
            ],
            'card' => [
                ...$request->all(),
                'singleUse' => true,
            ],
        ];

        try {
            $response = Http::asJson()
                ->withHeader('Authorization', $t)
                ->post(env('N1CO_API_URL') . '/paymentmethods', $data);
        } catch (\Throwable $th) {
            throw ValidationException::withMessages([
                'api' => 'An unexpected error occurred while contacting the payment service.',
            ]);
        }

        if (!$response->successful()) {
            $errorData = $response->json();
            $errors = [];

            if (isset($errorData['errors'])) {
                $errors = $errorData['errors'];
            } elseif (isset($errorData['error'])) {
                $errors['api'] = $errorData['error'];
            } else {
                $errors['api'] = 'An error occurred while processing the payment method.';
            }

            throw ValidationException::withMessages($errors);
        }


        $payment_method = [
            'id' => $response->json('id'),
            'bin' => $response->json('bin'),
            'cardHolder' => $request->get('cardHolder'),
            'valid' => $request->get('expirationMonth') . '/' . $request->get('expirationYear'),
            'safe_number' => substr_replace($request->get('number'), '********', 4, 8),
        ];

        $request->session()->put('payment_method', $payment_method);

        return to_route('checkout.render', $order);
    }


    public function checkout(Request $request, Order $order): InertiaResponse
    {
        $authorize = Gate::inspect('view', ShopOrder::wrap($order));

        if (!$authorize->allowed()) abort(403);

        $user = $request->user()->load(['addresses']);
        $order = $order->load([
            'orderDetails',
            'orderDetails.product',
            'orderDetails.plus',
            'addresses',
        ]);

        $selected_address = $request->session()->get('selected_address');

        $addresses = $user->addresses->map(fn($v) => [
            'selected' => $v->id === ($selected_address ? $selected_address->id : null),
            ...$v->toArray()
        ]);

        if ($selected_address && $selected_address->id === null) {
            $addresses->prepend([
                'selected' => true,
                ...$selected_address->toArray()
            ]);
        }

        $token_response = $this->get_payment_token();

        $t = $token_response['tokenType'] . ' ' . $token_response['accessToken'];

        return Inertia::render('checkout/CheckoutView', [
            'order_data' => $order,
            'order_total' => $order->total(),
            'addresses' => $addresses,
            'payment_method' => $request->session()->get('payment_method'),
            'selected_address' => $selected_address,
            'auth3ds' => $request->session()->get('auth3ds'),
            'token' => $t,
        ]);
    }

    public function approve(Request $request, Order $order)
    {
        $request->validate([
            'authentication_id' => [
                'sometimes',
            ]
        ]);

        $token_response = $this->get_payment_token();
        $t = $token_response['tokenType'] . ' ' . $token_response['accessToken'];

        $payment = $request->session()->pull('payment_method');
        $address = $request->session()->pull('selected_address');

        $data = [
            'order' => [
                'id' => $order->id,
                'amount' => $order->total(),
                'description' => null,
                'name' => $request->user()->name . '|' . $order->id,
            ],
            'billingInfo' => [
                "countryCode" => $payment['bin']['countryCode'],
                "stateCode" => $address->state,
                "zipCode" => $address->zip_code ?? '00000',
            ],
            'cardId' => $payment['id'],
            'authenticationId' => $request->get('authentication_id'),
        ];

        try {
            $response = Http::asJson()
                ->withHeader('Authorization', $t)
                ->post(env('N1CO_API_URL') . '/charges', $data);
        } catch (\Throwable $th) {
            dump($th);
        }

        $status = $response->json('status');

        if ($status === 'SUCCEEDED') {
            $order->payment_method = $payment;

            if ($address->id === null) {
                $address->save();
            }

            $order->addresses()->attach($address);
            $order->save();

            return Inertia::location('/orders');
        }

        if ($status === 'AUTHENTICATION_REQUIRED') {

            $request->session()->put('auth3ds', [...$response->json('authentication')]);
            $request->session()->put('selected_address', $address);
            $request->session()->put('payment_method', $payment);

            return to_route('checkout.render', $order);
        }
    }
}
