<?php

namespace Crater\Http\Controllers\V1\Customer\Auth;

use Crater\Http\Controllers\Controller;
use Crater\Providers\AppServiceProvider;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    use ResetsPasswords;

    protected string $redirectTo = AppServiceProvider::CUSTOMER_HOME;

    public function broker(): PasswordBroker
    {
        return Password::broker('customers');
    }

    protected function sendResetResponse(Request $request, $response): JsonResponse
    {
        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    protected function resetPassword($user, $password): void
    {
        $user->password = $password;

        $user->setRememberToken(Str::random(60));

        $user->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        event(new PasswordReset($user));
    }

    protected function sendResetFailedResponse(Request $request, $response): Response
    {
        return response('Failed, Invalid Token.', 403);
    }
}
