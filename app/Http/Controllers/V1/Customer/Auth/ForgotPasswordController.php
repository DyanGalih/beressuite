<?php

namespace Crater\Http\Controllers\V1\Customer\Auth;

use Crater\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    public function broker(): PasswordBroker
    {
        return Password::broker('customers');
    }

    protected function sendResetLinkResponse(Request $request, $response): JsonResponse
    {
        return response()->json([
            'message' => 'Password reset email sent.',
            'data' => $response,
        ]);
    }

    protected function sendResetLinkFailedResponse(Request $request, $response): Response
    {
        return response('Email could not be sent to this email address.', 403);
    }
}
