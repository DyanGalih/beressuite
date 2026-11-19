<?php

namespace Crater\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileDisk extends Model
{
    use HasFactory;

    public const DISK_TYPE_SYSTEM = 'SYSTEM';
    public const DISK_TYPE_REMOTE = 'REMOTE';

    protected $guarded = [
        'id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'set_as_default' => 'boolean',
            'credentials' => 'array',
        ];
    }

    public function setCredentialsAttribute($value): void
    {
        $this->attributes['credentials'] = json_encode($value);
    }

    public function scopeWhereOrder(Builder $query, $orderByField, $orderBy): Builder
    {
        return $query->orderBy($orderByField, $orderBy);
    }

    public function scopeWhereCompany(Builder $query, $companyId): Builder
    {
        return $query->whereNotNull('company_id')->where('company_id', $companyId);
    }

    public function scopeFileDisksBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween(
            'file_disks.created_at',
            [$start->format('Y-m-d'), $end->format('Y-m-d')]
        );
    }

    public function scopeWhereSearch(Builder $query, $search): Builder
    {
        foreach (explode(' ', $search) as $term) {
            $query->where(fn (Builder $q) => $q->where('name', 'LIKE', '%'.$term.'%')
                ->orWhere('driver', 'LIKE', '%'.$term.'%'));
        }

        return $query;
    }

    public function scopePaginateData(Builder $query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function scopeApplyFilters(Builder $query, array $filters): void
    {
        $filters = collect($filters);
        if ($filters->get('search')) {
            $query->whereSearch($filters->get('search'));
        }

        if ($filters->get('from_date') && $filters->get('to_date')) {
            $start = Carbon::createFromFormat('Y-m-d', $filters->get('from_date'));
            $end = Carbon::createFromFormat('Y-m-d', $filters->get('to_date'));
            $query->fileDisksBetween($start, $end);
        }

        if ($filters->get('orderByField') || $filters->get('orderBy')) {
            $field = $filters->get('orderByField') ? $filters->get('orderByField') : 'sequence_number';
            $orderBy = $filters->get('orderBy') ? $filters->get('orderBy') : 'asc';
            $query->whereOrder($field, $orderBy);
        }
    }

    public function withStorage(callable $operation)
    {
        $credentials = $this->credentials;
        $options = self::storageOptions($this->driver, is_string($credentials) ? json_decode($credentials, true, flags: JSON_THROW_ON_ERROR) : $credentials);
        $disks = config('filesystems.disks');
        $name = env('DYNAMIC_DISK_PREFIX', 'temp_').$this->driver;
        Storage::forgetDisk($name);
        config(['filesystems.disks.'.$name => $options]);

        try {
            return $operation($name);
        } finally {
            Storage::forgetDisk($name);
            config(['filesystems.disks' => $disks]);
        }
    }

    private static function storageOptions($driver, ?array $credentials): array
    {
        $options = config('filesystems.disks.'.$driver);
        if (! is_array($options)) {
            return [];
        }

        if ($driver === 's3' || $driver === 'do_spaces') {
            $options['key'] = null;
            $options['secret'] = null;
        }

        return array_replace($options, array_intersect_key($credentials ?? [], $options));
    }

    public function setAsDefault(): bool
    {
        return (bool) $this->set_as_default;
    }

    public static function validateCredentials($credentials, $disk): bool
    {
        try {
            $options = self::storageOptions($disk, $credentials);
            if (empty($options)) {
                return false;
            }

            $storage = Storage::build($options);
            $probe = ($disk === 'dropbox' ? ($credentials['root'] ?? '').'/' : '').'crater-'.bin2hex(random_bytes(12));
            if (! $storage->put($probe, 'Check Credentials')) {
                return false;
            }

            try {
                return $storage->exists($probe) && $storage->delete($probe);
            } finally {
                $storage->delete($probe);
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public static function createDisk($request): self
    {
        return DB::transaction(function () use ($request) {
            if ($request->set_as_default) {
                self::updateDefaultDisks($request->header('company'));
            }

            return self::create([
                'credentials' => $request->credentials,
                'name' => $request->name,
                'driver' => $request->driver,
                'set_as_default' => $request->set_as_default,
                'company_id' => $request->header('company'),
            ]);
        });
    }

    private static function updateDefaultDisks($companyId, $excludeId = null): void
    {
        $query = self::whereCompany($companyId)->where('set_as_default', true);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        $query->update(['set_as_default' => false]);
    }

    public function updateDisk($request): self
    {
        return DB::transaction(function () use ($request) {
            $data = [
                'credentials' => $request->credentials,
                'name' => $request->name,
                'driver' => $request->driver,
            ];

            if (! $this->setAsDefault()) {
                if ($request->set_as_default) {
                    self::updateDefaultDisks($this->company_id, $this->id);
                }

                $data['set_as_default'] = $request->set_as_default;
            }

            $this->update($data);

            return $this;
        });
    }

    public function setAsDefaultDisk(): self
    {
        return DB::transaction(function () {
            self::updateDefaultDisks($this->company_id, $this->id);

            $this->set_as_default = true;
            $this->save();

            return $this;
        });
    }

    public function isSystem(): bool
    {
        return $this->type === self::DISK_TYPE_SYSTEM;
    }

    public function isRemote(): bool
    {
        return $this->type === self::DISK_TYPE_REMOTE;
    }
}
