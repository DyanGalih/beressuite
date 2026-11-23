<?php

namespace App\Http\Controllers\V1\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    use SendsPasswordResetEmails;

    protected function sendResetLinkResponse(Request $request, $response): JsonResponse
    {
        return response()->json([
            'message' => 'Password reset email sent.',
            'data' => $response,
        ]);
    }

    protected function sendResetLinkFailedResponse(Request $request, $response): JsonResponse
    {
        return response()->json([
            'error' => 'Email could not be sent to this email address.'
        ], 403);
    }
}
