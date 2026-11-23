<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiTokenStoreRequest;
use App\Http\Resources\ApiTokenResource;
use App\Models\PersonalAccessToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiTokensController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isOwner(), 403, 'Only company owners can manage API tokens.');

        $companyId = (int) $request->header('company');

        $tokens = PersonalAccessToken::query()
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ApiTokenResource::collection($tokens),
            'available_abilities' => config('api_tokens.abilities', []),
        ]);
    }

    public function store(ApiTokenStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $request->header('company');
        $expiresAt = $request->filled('expires_at') ? Carbon::parse($request->input('expires_at')) : null;
        $abilities = array_values($request->validated('abilities'));

        $newToken = DB::transaction(function () use ($user, $request, $abilities, $expiresAt, $companyId) {
            $token = $user->createToken($request->validated('name'), $abilities, $expiresAt);
            $token->accessToken->forceFill(['company_id' => $companyId])->save();

            return $token;
        });

        Log::info('api_token.created', [
            'actor_user_id' => $user->id,
            'company_id' => $companyId,
            'token_id' => $newToken->accessToken->id,
            'token_name' => $newToken->accessToken->name,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);

        return response()->json([
            'data' => new ApiTokenResource($newToken->accessToken),
            'plain_text_token' => $newToken->plainTextToken,
        ], 201);
    }

    public function rotate(Request $request, int $token): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isOwner(), 403, 'Only company owners can manage API tokens.');

        $companyId = (int) $request->header('company');

        [$revokedId, $name, $abilities, $replacement] = DB::transaction(function () use ($user, $token, $companyId) {
            $existing = PersonalAccessToken::query()
                ->where('id', $token)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            $expiresAt = ($existing->expires_at !== null && $existing->expires_at->isFuture())
                ? $existing->expires_at
                : null;
            $abilities = array_values($existing->abilities ?? []);
            $name = $existing->name;
            $revokedId = $existing->id;

            $created = $user->createToken($name, $abilities, $expiresAt);
            $created->accessToken->forceFill(['company_id' => $companyId])->save();
            $existing->delete();

            return [$revokedId, $name, $abilities, $created];
        });

        Log::info('api_token.rotated', [
            'actor_user_id' => $user->id,
            'company_id' => $companyId,
            'revoked_token_id' => $revokedId,
            'new_token_id' => $replacement->accessToken->id,
            'token_name' => $name,
            'abilities' => $abilities,
        ]);

        return response()->json([
            'data' => new ApiTokenResource($replacement->accessToken),
            'plain_text_token' => $replacement->plainTextToken,
        ]);
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isOwner(), 403, 'Only company owners can manage API tokens.');

        $companyId = (int) $request->header('company');

        $existing = PersonalAccessToken::query()
            ->where('id', $token)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $existing->delete();

        Log::info('api_token.revoked', [
            'actor_user_id' => $user->id,
            'company_id' => $companyId,
            'token_id' => $existing->id,
            'token_name' => $existing->name,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}
