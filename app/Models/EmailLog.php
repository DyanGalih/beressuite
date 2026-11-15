<?php

namespace Crater\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'revoked_at' => 'datetime',
    ];

    public function mailable()
    {
        return $this->morphTo();
    }

    public function hasMailableType(string $type): bool
    {
        return $this->mailable_type === $type;
    }

    public function isExpired(): bool
    {
        if ($this->revoked_at !== null) {
            return true;
        }

        $mailable = $this->mailable;
        if (! $mailable) {
            return true;
        }

        $companyId = $mailable->company_id;
        $linkexpiryDays = CompanySetting::getSetting('link_expiry_days', $companyId);
        $checkExpiryLinks = CompanySetting::getSetting('automatically_expire_public_links', $companyId);

        if ($checkExpiryLinks === 'YES') {
            $days = (int) ($linkexpiryDays ?: 7);
            $expiryDate = $this->created_at->copy()->addDays($days);
            if (Carbon::now()->isAfter($expiryDate)) {
                return true;
            }
        }

        return false;
    }

    public function validMailable(string $expectedClass)
    {
        if ($this->revoked_at !== null) {
            return null;
        }

        if (! $this->hasMailableType($expectedClass)) {
            return null;
        }

        $mailable = $this->mailable;
        if (! $mailable) {
            return null;
        }

        if ($this->isExpired()) {
            return null;
        }

        $headerCompany = request()->header('company');
        if ($headerCompany && (int) $mailable->company_id !== (int) $headerCompany) {
            return null;
        }

        return $mailable;
    }

    public function revoke()
    {
        $this->update(['revoked_at' => now()]);

        return $this;
    }
}
