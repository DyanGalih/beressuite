<?php

namespace Crater\Models;

use Carbon\Carbon;
use Crater\Http\Requests\UserRequest;
use Crater\Notifications\MailResetPasswordNotification;
use Crater\Traits\HasCustomFieldsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\HasRolesAndAbilities;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use Notifiable;
    use InteractsWithMedia;
    use HasCustomFieldsTrait;
    use HasFactory;
    use HasRolesAndAbilities;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = [
        'currency',
    ];

    protected $appends = [
        'formattedCreatedAt',
        'avatar',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    /**
     * Find the user instance for the given username.
     *
     * @param  string  $username
     * @return \App\User
     */
    public function findForPassport($username)
    {
        return $this->where('email', $username)->first();
    }

    public function setPasswordAttribute($value)
    {
        if ($value != null) {
            $this->attributes['password'] = bcrypt($value);
        }
    }

    public function isSuperAdminOrAdmin(): bool
    {
        return ($this->role == 'super admin') || ($this->role == 'admin');
    }

    public static function login($request)
    {
        $remember = $request->remember;
        $email = $request->email;
        $password = $request->password;

        return (\Auth::attempt(['email' => $email, 'password' => $password], $remember));
    }

    public function getFormattedCreatedAtAttribute($value)
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', request()->header('company'));

        return Carbon::parse($this->created_at)->format($dateFormat);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class, 'creator_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'creator_id');
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class, 'creator_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'creator_id');
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company', 'user_id', 'company_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'creator_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'creator_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'creator_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'creator_id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class, 'user_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::BILLING_TYPE);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', Address::SHIPPING_TYPE);
    }

    /**
     * Override the mail body for reset password notification mail.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new MailResetPasswordNotification($token));
    }

    public function scopeWhereOrder($query, $orderByField, $orderBy)
    {
        $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereSearch($query, $search)
    {
        foreach (explode(' ', $search) as $term) {
            $query->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', '%'.$term.'%')
                    ->orWhere('email', 'LIKE', '%'.$term.'%')
                    ->orWhere('phone', 'LIKE', '%'.$term.'%');
            });
        }
    }

    public function scopeWhereContactName($query, $contactName)
    {
        return $query->where('contact_name', 'LIKE', '%'.$contactName.'%');
    }

    public function scopeWhereDisplayName($query, $displayName)
    {
        return $query->where('name', 'LIKE', '%'.$displayName.'%');
    }

    public function scopeWherePhone($query, $phone)
    {
        return $query->where('phone', 'LIKE', '%'.$phone.'%');
    }

    public function scopeWhereEmail($query, $email)
    {
        return $query->where('email', 'LIKE', '%'.$email.'%');
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('display_name')) {
            $query->whereDisplayName($filters->get('display_name'));
        }

        if ($filters->get('email')) {
            $query->whereEmail($filters->get('email'));
        }

        if ($filters->get('phone')) {
            $query->wherePhone($filters->get('phone'));
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'name';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function scopeWhereSuperAdmin($query)
    {
        $query->orWhere('role', 'super admin');
    }

    public function scopeApplyInvoiceFilters($query, array $filters)
    {
        $filters = collect($filters);

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->invoicesBetween($start, $end);
        }
    }

    public function scopeInvoicesBetween($query, $start, $end)
    {
        $query->whereHas('invoices', function ($query) use ($start, $end) {
            $query->whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            );
        });
    }

    public function getAvatarAttribute()
    {
        $avatar = $this->getMedia('admin_avatar')->first();

        if ($avatar) {
            return  asset($avatar->getUrl());
        }

        return 0;
    }

    public function setSettings($settings)
    {
        foreach ($settings as $key => $value) {
            $this->settings()->updateOrCreate(
                [
                    'key' => $key,
                ],
                [
                    'key' => $key,
                    'value' => $value,
                ]
            );
        }
    }

    public function hasCompany($company_id): bool
    {
        if (! is_numeric($company_id)) {
            return false;
        }

        $companies = array_map('strval', $this->companies()->pluck('company_id')->toArray());

        return in_array((string) $company_id, $companies, true);
    }

    public function getAllSettings()
    {
        return $this->settings()->get()->mapWithKeys(function ($item) {
            return [$item['key'] => $item['value']];
        });
    }

    public function getSettings($settings)
    {
        return $this->settings()->whereIn('key', $settings)->get()->mapWithKeys(function ($item) {
            return [$item['key'] => $item['value']];
        });
    }

    public function isOwner(): bool
    {
        if (Schema::hasColumn('companies', 'owner_id')) {
            $company = Company::find(request()->header('company'));

            if ($company && $this->id == $company->owner_id) {
                return true;
            }
        } else {
            return $this->role == 'super admin' || $this->role == 'admin';
        }

        return false;
    }

    public static function createFromRequest(UserRequest $request): self
    {
        $user = self::create($request->getUserPayload());

        $user->setSettings([
            'language' => CompanySetting::getSetting('language', $request->header('company')),
        ]);

        $companies = collect($request->companies);
        $user->companies()->sync($companies->pluck('id'));

        foreach ($companies as $company) {
            BouncerFacade::scope()->to($company['id']);

            BouncerFacade::sync($user)->roles([$company['role']]);
        }

        return $user;
    }

    public function updateFromRequest(UserRequest $request): self
    {
        $this->update($request->getUserPayload());

        $companies = collect($request->companies);
        $this->companies()->sync($companies->pluck('id'));

        foreach ($companies as $company) {
            BouncerFacade::scope()->to($company['id']);

            BouncerFacade::sync($this)->roles([$company['role']]);
        }

        return $this;
    }

    public function checkAccess(array|object $data): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $attributes = is_array($data) ? $data : (array) ($data->data ?? []);
        $ownerOnly = (bool) ($attributes['owner_only'] ?? false);
        $ability = $attributes['ability'] ?? '';
        $model = $attributes['model'] ?? '';

        if ((! $ownerOnly) && empty($ability)) {
            return true;
        }

        if ((! $ownerOnly) && (! empty($ability)) && (! empty($model)) && $this->can($ability, $model)) {
            return true;
        }

        if ((! $ownerOnly) && (! empty($ability)) && $this->can($ability)) {
            return true;
        }

        return false;
    }

    public static function deleteUsers(array $ids): bool
    {
        foreach ($ids as $id) {
            $user = self::find($id);

            if ($user->invoices()->exists()) {
                $user->invoices()->update(['creator_id' => null]);
            }

            if ($user->estimates()->exists()) {
                $user->estimates()->update(['creator_id' => null]);
            }

            if ($user->customers()->exists()) {
                $user->customers()->update(['creator_id' => null]);
            }

            if ($user->recurringInvoices()->exists()) {
                $user->recurringInvoices()->update(['creator_id' => null]);
            }

            if ($user->expenses()->exists()) {
                $user->expenses()->update(['creator_id' => null]);
            }

            if ($user->payments()->exists()) {
                $user->payments()->update(['creator_id' => null]);
            }

            if ($user->items()->exists()) {
                $user->items()->update(['creator_id' => null]);
            }

            if ($user->settings()->exists()) {
                $user->settings()->delete();
            }

            $user->delete();
        }

        return true;
    }
}
