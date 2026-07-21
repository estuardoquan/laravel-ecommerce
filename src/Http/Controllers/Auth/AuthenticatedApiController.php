<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class AuthenticatedApiController extends Controller
{
    /**
     * Destroy request API Token
     */
    public function destroy(Request $request): Response
    {
        $request->user()->token()->revoke();

        return response()->noContent();
    }
}
