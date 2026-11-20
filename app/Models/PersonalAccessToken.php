<?php

namespace Crater\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'company_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isScopedIntegrationToken(): bool
    {
        if (is_numeric($this->company_id)) {
            return true;
        }

        return ! $this->can('*');
    }
}
