<?php

use Crater\Models\Company;
use Crater\Models\User;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

function createDocsTestOwnerAndCompany(): array
{
    $company = Company::create(['name' => 'Docs Co', 'slug' => 'docs-co']);

    $owner = User::create([
        'name' => 'Docs Owner',
        'email' => 'docs-owner-'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'role' => 'super admin',
    ]);
    $owner->companies()->attach([$company->id]);
    $company->forceFill(['owner_id' => $owner->id])->save();

    BouncerFacade::scope()->to($company->id);
    BouncerFacade::allow($owner)->everything();

    return [$owner, $company];
}

function issuePinnedToken(User $owner, Company $company, string $name, array $abilities): string
{
    $newToken = $owner->createToken($name, $abilities);
    $newToken->accessToken->forceFill(['company_id' => $company->id])->save();

    return $newToken->plainTextToken;
}

test('bearer token exceeding per-token rate limit receives 429 with rate-limit headers while other tokens and browser sessions remain unaffected', function () {
    config(['api_tokens.rate_limit_per_minute' => 2]);

    [$owner, $company] = createDocsTestOwnerAndCompany();

    $tokenA = issuePinnedToken($owner, $company, 'Token A', ['company:read']);
    $tokenB = issuePinnedToken($owner, $company, 'Token B', ['company:read']);

    // Token A: first 2 requests succeed
    $this->getJson('/api/v1/current-company', [
        'Authorization' => 'Bearer '.$tokenA,
        'company' => (string) $company->id,
    ])->assertOk();

    $this->getJson('/api/v1/current-company', [
        'Authorization' => 'Bearer '.$tokenA,
        'company' => (string) $company->id,
    ])->assertOk();

    // Token A: 3rd request is throttled with 429 + X-RateLimit-* + Retry-After headers
    $throttled = $this->getJson('/api/v1/current-company', [
        'Authorization' => 'Bearer '.$tokenA,
        'company' => (string) $company->id,
    ]);

    $throttled->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', '2')
        ->assertHeader('X-RateLimit-Remaining', '0');

    expect($throttled->headers->has('Retry-After'))->toBeTrue();

    // Token B: isolated bucket, still succeeds
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/current-company', [
        'Authorization' => 'Bearer '.$tokenB,
        'company' => (string) $company->id,
    ])->assertOk();

    // Browser session (TransientToken via Sanctum::actingAs): not throttled by api-token limiter
    app('auth')->forgetGuards();
    Sanctum::actingAs($owner, ['*']);

    for ($i = 0; $i < 4; $i++) {
        $this->getJson('/api/v1/current-company', [
            'company' => (string) $company->id,
        ])->assertOk();
    }
});

test('rejected 403 requests count toward per-token quota and tokens can exceed 180 req/min with per-token isolation on the same IP', function () {
    [$owner, $company] = createDocsTestOwnerAndCompany();

    // 1. 403 ability rejections still consume the token's rate-limit quota
    config(['api_tokens.rate_limit_per_minute' => 2]);
    $restrictedToken = issuePinnedToken($owner, $company, 'Restricted Token', ['invoices:read']);

    $this->get('/api/v1/openapi.yaml', [
        'Authorization' => 'Bearer '.$restrictedToken,
        'Accept' => 'application/json',
    ])->assertStatus(403);

    $this->get('/api/v1/openapi.yaml', [
        'Authorization' => 'Bearer '.$restrictedToken,
        'Accept' => 'application/json',
    ])->assertStatus(403);

    $this->get('/api/v1/openapi.yaml', [
        'Authorization' => 'Bearer '.$restrictedToken,
        'Accept' => 'application/json',
    ])->assertStatus(429);

    // 2. Per-token quota > 180 is not prematurely blocked by inherited 180/min IP throttle, and tokens sharing an IP are isolated
    config(['api_tokens.rate_limit_per_minute' => 185]);
    app('auth')->forgetGuards();
    $highQuotaTokenA = issuePinnedToken($owner, $company, 'High Quota A', ['company:read']);
    $highQuotaTokenB = issuePinnedToken($owner, $company, 'High Quota B', ['company:read']);

    for ($i = 0; $i < 185; $i++) {
        $this->get('/api/v1/openapi.yaml', [
            'Authorization' => 'Bearer '.$highQuotaTokenA,
        ])->assertOk();
    }

    $this->get('/api/v1/openapi.yaml', [
        'Authorization' => 'Bearer '.$highQuotaTokenA,
    ])->assertStatus(429);

    // Token B from the exact same client IP still has its own 185/min bucket
    app('auth')->forgetGuards();
    $this->get('/api/v1/openapi.yaml', [
        'Authorization' => 'Bearer '.$highQuotaTokenB,
    ])->assertOk();
});

test('missing bound models consume per-token quota before SubstituteBindings and invalid bearer requests are rate-limited per IP', function () {
    config(['api_tokens.rate_limit_per_minute' => 2]);

    [$owner, $company] = createDocsTestOwnerAndCompany();
    $tokenA = issuePinnedToken($owner, $company, 'Missing Model Token A', ['invoices:read']);
    $tokenB = issuePinnedToken($owner, $company, 'Missing Model Token B', ['invoices:read']);

    // 1. Authenticated requests for a nonexistent bound Invoice return 404 until quota (2) is exhausted, then 429
    $this->getJson('/api/v1/invoices/999999', [
        'Authorization' => 'Bearer '.$tokenA,
        'company' => (string) $company->id,
    ])->assertStatus(404);

    $this->getJson('/api/v1/invoices/999999', [
        'Authorization' => 'Bearer '.$tokenA,
        'company' => (string) $company->id,
    ])->assertStatus(404);

    $this->getJson('/api/v1/invoices/999999', [
        'Authorization' => 'Bearer '.$tokenA,
        'company' => (string) $company->id,
    ])->assertStatus(429);

    // Separate token quota remains isolated
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/invoices/999999', [
        'Authorization' => 'Bearer '.$tokenB,
        'company' => (string) $company->id,
    ])->assertStatus(404);

    // 2. Invalid Bearer requests are rate-limited by IP before auth:sanctum without blocking valid persistent tokens
    app('auth')->forgetGuards();
    for ($i = 0; $i < 180; $i++) {
        $this->getJson('/api/v1/current-company', [
            'Authorization' => 'Bearer invalid-token-value',
        ])->assertStatus(401);
    }

    $this->getJson('/api/v1/current-company', [
        'Authorization' => 'Bearer invalid-token-value',
    ])->assertStatus(429);

    // Valid persistent token on the same IP still uses its own per-token quota
    app('auth')->forgetGuards();
    $validToken = issuePinnedToken($owner, $company, 'Valid After Unauth Flood', ['company:read']);
    $this->getJson('/api/v1/current-company', [
        'Authorization' => 'Bearer '.$validToken,
        'company' => (string) $company->id,
    ])->assertOk();
});

test('openapi 3.1 endpoint requires authentication and company:read ability for scoped tokens and serves valid yaml', function () {
    [$owner, $company] = createDocsTestOwnerAndCompany();

    // Unauthenticated request -> 401
    $this->getJson('/api/v1/openapi.yaml')->assertStatus(401);

    // Scoped token without company:read -> 403
    $invoicesOnlyToken = issuePinnedToken($owner, $company, 'Invoices Only', ['invoices:read']);
    $this->get('/api/v1/openapi.yaml', [
        'Authorization' => 'Bearer '.$invoicesOnlyToken,
        'Accept' => 'application/json',
    ])->assertStatus(403);

    // Scoped token with company:read -> 200 YAML
    app('auth')->forgetGuards();
    $companyReadToken = issuePinnedToken($owner, $company, 'Company Reader', ['company:read']);
    $tokenResponse = $this->get('/api/v1/openapi.yaml', [
        'Authorization' => 'Bearer '.$companyReadToken,
    ]);

    $tokenResponse->assertOk();
    expect($tokenResponse->headers->get('Content-Type'))->toContain('application/yaml');

    // Authenticated browser session -> 200 YAML
    app('auth')->forgetGuards();
    Sanctum::actingAs($owner, ['*']);
    $sessionResponse = $this->get('/api/v1/openapi.yaml', [
        'company' => (string) $company->id,
    ]);

    $sessionResponse->assertOk();
    expect($sessionResponse->headers->get('Cache-Control'))->toContain('no-store');

    $yaml = $sessionResponse->getContent();

    expect($yaml)->toBeString()
        ->toContain('openapi: 3.1.0')
        ->toContain('scheme: bearer')
        ->toContain('bearerFormat: Sanctum')
        ->toContain('sessionCookieAuth:')
        ->toContain('xsrfTokenHeader:')
        ->toContain('CustomerCreateRequest:')
        ->toContain('DocumentTaxInput:')
        ->toContain('InvoiceCreateRequest:')
        ->toContain('EstimateCreateRequest:')
        ->toContain('PaymentCreateRequest:')
        ->toContain('ItemCreateRequest:')
        ->toContain('ExpenseCreateRequest:')
        ->not->toContain('GenericJson')
        ->toContain('X-RateLimit-Limit:')
        ->toContain('X-RateLimit-Remaining:')
        ->toContain('Retry-After:')
        ->toContain('  /api-tokens:')
        ->toContain('  /api-tokens/{token}:')
        ->toContain('  /api-tokens/{token}/rotate:')
        ->toContain('  /openapi.yaml:')
        ->toContain('  /current-company:')
        ->toContain('  /customers:')
        ->toContain('  /invoices:')
        ->toContain('  /estimates:')
        ->toContain('  /payments:')
        ->toContain('  /items:')
        ->toContain('  /expenses:');
});
