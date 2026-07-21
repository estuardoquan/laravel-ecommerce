<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AddressStoreRequest;
use App\Models\Address;
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
