<?php

namespace EQ\LaravelEcommerce\Http\Controllers\Settings;

use EQ\LaravelEcommerce\Http\Controllers\Controller;
use EQ\LaravelEcommerce\Http\Requests\Settings\AddressStoreRequest;
use EQ\LaravelEcommerce\Models\Address;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;


class AddressController extends Controller
{
    /**
     * Show the user's addresses settings page.
     */
    public function render(): Response
    {
        return Inertia::render('settings/Addresses');
    }

    public function store(AddressStoreRequest $request)
    {
        $address = new Address($request->validated());

        $address->addressable()->associate($request->user());

        $address->save();


        // return response('lol');
    }
}
