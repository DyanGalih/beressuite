<?php

use Crater\Models\Company;
use Crater\Models\PersonalAccessToken;
use Crater\Models\User;
use Laravel\Sanctum\Sanctum;
use Silber\Bouncer\BouncerFacade;

function createOwnerWithCompanies(): array
{
    $companyA = Company::create(['name' => 'Token Co A', 'slug' => 'token-co-a']);
    $companyB = Company::create(['name' => 'Token Co B', 'slug' => 'token-co-b']);

    $owner = User::create([
        'name' => 'Owner User',
        'email' => 'owner-'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'role' => 'super admin',
    ]);
    $owner->companies()->attach([$companyA->id, $companyB->id]);
    $companyA->forceFill(['owner_id' => $owner->id])->save();
    $companyB->forceFill(['owner_id' => $owner->id])->save();

    BouncerFacade::scope()->to($companyA->id);
    BouncerFacade::allow($owner)->everything();
    BouncerFacade::scope()->to($companyB->id);
    BouncerFacade::allow($owner)->everything();

    $nonOwner = User::create([
        'name' => 'Staff User',
        'email' => 'staff-'.uniqid().'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);
    $nonOwner->companies()->attach([$companyA->id]);

    return [$owner, $nonOwner, $companyA, $companyB];
}

test('owner can create, list, rotate, and revoke company-pinned API tokens without exposing hashes', function () {
    [$owner, , $companyA, $companyB] = createOwnerWithCompanies();

    Sanctum::actingAs($owner, ['*']);

    // Wildcard ability is rejected on creation
    $this->withHeader('company', (string) $companyA->id)
        ->postJson('/api/v1/api-tokens', [
            'name' => 'Bad Wildcard',
            'abilities' => ['*'],
        ])
        ->assertStatus(422);

    // Create scoped token for Company A
    $createResponse = $this->withHeader('company', (string) $companyA->id)
        ->postJson('/api/v1/api-tokens', [
            'name' => 'ERP Sync',
            'abilities' => ['invoices:read', 'company:read'],
            'expires_at' => now()->addDays(30)->toIso8601String(),
        ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'ERP Sync')
        ->assertJsonPath('data.company_id', $companyA->id)
        ->assertJsonPath('data.abilities', ['invoices:read', 'company:read']);

    $plainToken = $createResponse->json('plain_text_token');
    $tokenId = $createResponse->json('data.id');

    expect($plainToken)->toBeString()->toContain('|');

    // Listing for Company A shows metadata only (no hash or plaintext)
    $listA = $this->withHeader('company', (string) $companyA->id)
        ->getJson('/api/v1/api-tokens');

    $listA->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $tokenId)
        ->assertJsonMissingPath('data.0.token')
        ->assertJsonMissingPath('data.0.plain_text_token');

    // Listing for Company B does NOT include Company A's token
    $this->withHeader('company', (string) $companyB->id)
        ->getJson('/api/v1/api-tokens')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // Company B cannot rotate or revoke Company A's token
    $this->withHeader('company', (string) $companyB->id)
        ->postJson("/api/v1/api-tokens/{$tokenId}/rotate")
        ->assertNotFound();

    $this->withHeader('company', (string) $companyB->id)
        ->deleteJson("/api/v1/api-tokens/{$tokenId}")
        ->assertNotFound();

    // Rotate token in Company A
    $rotateResponse = $this->withHeader('company', (string) $companyA->id)
        ->postJson("/api/v1/api-tokens/{$tokenId}/rotate");

    $rotateResponse->assertOk()
        ->assertJsonPath('data.name', 'ERP Sync')
        ->assertJsonPath('data.company_id', $companyA->id);

    $newTokenId = $rotateResponse->json('data.id');
    expect($newTokenId)->not->toBe($tokenId);
    expect(PersonalAccessToken::find($tokenId))->toBeNull();

    // Rotating the already-consumed original token fails with 404
    $this->withHeader('company', (string) $companyA->id)
        ->postJson("/api/v1/api-tokens/{$tokenId}/rotate")
        ->assertNotFound();

    // Revoke rotated token
    $this->withHeader('company', (string) $companyA->id)
        ->deleteJson("/api/v1/api-tokens/{$newTokenId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(PersonalAccessToken::find($newTokenId))->toBeNull();
});

test('non-owner users are forbidden from managing API tokens', function () {
    [, $nonOwner, $companyA] = createOwnerWithCompanies();

    Sanctum::actingAs($nonOwner, ['*']);

    $this->withHeader('company', (string) $companyA->id)
        ->getJson('/api/v1/api-tokens')
        ->assertForbidden();

    $this->withHeader('company', (string) $companyA->id)
        ->postJson('/api/v1/api-tokens', [
            'name' => 'Unauthorized Token',
            'abilities' => ['invoices:read'],
        ])
        ->assertForbidden();
});

test('company-pinned token auto-pins company, blocks mismatched company header, and enforces abilities', function () {
    [$owner, , $companyA, $companyB] = createOwnerWithCompanies();

    $issued = $owner->createToken('Read Only Sync', ['company:read', 'invoices:read']);
    $issued->accessToken->forceFill(['company_id' => $companyA->id])->save();
    $bearer = $issued->plainTextToken;

    // Auto-pins to Company A even without company header
    $this->withToken($bearer)
        ->getJson('/api/v1/current-company')
        ->assertOk()
        ->assertJsonPath('data.id', $companyA->id);

    // Mismatched company header is rejected with 403 even though owner belongs to Company B
    $this->withToken($bearer)
        ->getJson('/api/v1/current-company', ['company' => (string) $companyB->id])
        ->assertForbidden();

    // Allowed read ability passes
    $this->withToken($bearer)
        ->getJson('/api/v1/invoices')
        ->assertOk();

    // Disallowed write ability is rejected with 403
    $this->withToken($bearer)
        ->postJson('/api/v1/invoices', [])
        ->assertForbidden();

    // Exact-path company:read route (/api/v1/date/formats) succeeds
    $this->withToken($bearer)
        ->getJson('/api/v1/date/formats')
        ->assertOk();

    // Search endpoints (which can return users/customers) are denied to company:read tokens
    $this->withToken($bearer)
        ->getJson('/api/v1/search')
        ->assertForbidden();

    $this->withToken($bearer)
        ->getJson('/api/v1/search/user')
        ->assertForbidden();

    // High-privilege admin/secret routes are denied by default
    $this->withToken($bearer)
        ->getJson('/api/v1/users')
        ->assertForbidden();

    $this->withToken($bearer)
        ->getJson('/api/v1/api-tokens')
        ->assertForbidden();
});

test('expired API token is rejected with 401', function () {
    [$owner, , $companyA] = createOwnerWithCompanies();

    $issued = $owner->createToken('Expired Sync', ['company:read'], now()->subMinute());
    $issued->accessToken->forceFill(['company_id' => $companyA->id])->save();

    $this->withToken($issued->plainTextToken)
        ->getJson('/api/v1/current-company')
        ->assertUnauthorized();
});
