<?php

namespace App\Http\Controllers\V1\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiskEnvironmentRequest;
use App\Http\Resources\FileDiskResource;
use App\Models\FileDisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DiskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage file disk');

        $limit = $request->has('limit') ? $request->limit : 5;
        $disks = FileDisk::whereCompany($request->header('company'))->applyFilters($request->all())
            ->latest()
            ->paginateData($limit);

        return FileDiskResource::collection($disks);
    }

    public function store(DiskEnvironmentRequest $request): FileDiskResource|JsonResponse
    {
        $this->authorize('manage file disk');

        if (! FileDisk::validateCredentials($request->credentials, $request->driver)) {
            return respondJson('invalid_credentials', 'Invalid Credentials.');
        }

        $disk = FileDisk::createDisk($request);

        return new FileDiskResource($disk);
    }

    public function update($disk, Request $request): FileDiskResource|JsonResponse
    {
        $this->authorize('manage file disk');

        $disk = FileDisk::whereCompany($request->header('company'))->findOrFail($disk);
        $credentials = $request->credentials;
        $driver = $request->driver;

        if (is_array($credentials) && is_array($disk->credentials)) {
            foreach (['secret', 'password', 'token', 'key'] as $secretKey) {
                if (isset($credentials[$secretKey]) && $credentials[$secretKey] === '********' && ! empty($disk->credentials[$secretKey])) {
                    $credentials[$secretKey] = $disk->credentials[$secretKey];
                }
            }
            $request->merge(['credentials' => $credentials]);
        }

        if ($credentials && $driver && $disk->type !== 'SYSTEM') {
            if (! FileDisk::validateCredentials($credentials, $driver)) {
                return respondJson('invalid_credentials', 'Invalid Credentials.');
            }

            $disk->updateDisk($request);
        } elseif ($request->set_as_default) {
            $disk->setAsDefaultDisk();
        }

        return new FileDiskResource($disk);
    }

    public function show(string $disk): JsonResponse
    {
        $this->authorize('manage file disk');

        $diskData = [];
        switch ($disk) {
            case 'local':
                $diskData = [
                    'root' => config('filesystems.disks.local.root'),
                ];

                break;


            case 's3':
                $diskData = [
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'root' => '',
                ];

                break;

            case 'doSpaces':
                $diskData = [
                    'key' => '',
                    'secret' => '',
                    'region' => '',
                    'bucket' => '',
                    'endpoint' => '',
                    'root' => '',
                ];

                break;

            case 'dropbox':
                $diskData = [
                    'token' => '',
                    'key' => '',
                    'secret' => '',
                    'app' => '',
                    'root' => '',
                ];

                break;
        }

        $data = array_merge($diskData);

        return response()->json($data);
    }

    public function destroy($disk, Request $request): JsonResponse
    {
        $this->authorize('manage file disk');

        $disk = FileDisk::whereCompany($request->header('company'))->findOrFail($disk);
        if ($disk->setAsDefault() && $disk->type === 'SYSTEM') {
            return respondJson('not_allowed', 'Not Allowed');
        }

        $disk->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function getDiskDrivers(): JsonResponse
    {
        $this->authorize('manage file disk');

        $drivers = [
            [
                'name' => 'Local',
                'value' => 'local',
            ],
            [
                'name' => 'Amazon S3',
                'value' => 's3',
            ],
            [
                'name' => 'Digital Ocean Spaces',
                'value' => 'doSpaces',
            ],
            [
                'name' => 'Dropbox',
                'value' => 'dropbox',
            ],
        ];

        $default = config('filesystems.default');

        return response()->json([
            'drivers' => $drivers,
            'default' => $default,
        ]);
    }
}
