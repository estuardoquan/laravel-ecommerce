<?php

namespace App\Http\Controllers\OAuth;

use League\OAuth2\Server\AuthorizationServer;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as Base;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ApproveAuthorizationController extends Base
{
    /**
     * Approve the authorization request.
     */
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $response = parent::approve($request, $psrResponse);

        if (!$request->inertia()) {
            return $response;
        }

        $redirect = $response->headers->get('Location');

        return Inertia::location($redirect);
    }
}
