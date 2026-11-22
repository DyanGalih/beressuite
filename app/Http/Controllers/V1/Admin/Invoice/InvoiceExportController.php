<?php

namespace App\Http\Controllers\V1\Admin\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportInvoicesRequest;
use App\Http\Resources\InvoiceExportResource;
use App\Jobs\ExportInvoicesJob;
use App\Models\FileDisk;
use App\Models\InvoiceExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InvoiceExportController extends Controller
{
    /**
     * Dispatch an invoice export job.
     */
    public function store(ExportInvoicesRequest $request): JsonResponse
    {
        $this->authorize('create', InvoiceExport::class);

        $companyId = $request->header('company');

        $export = InvoiceExport::create([
            'company_id' => $companyId,
            'user_id' => $request->user()?->id,
            'status' => InvoiceExport::STATUS_PENDING,
            'filters' => $request->validated(),
        ]);

        ExportInvoicesJob::dispatch($export);

        return (new InvoiceExportResource($export))
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Get the status of an export.
     */
    public function show(int|string $id, Request $request): InvoiceExportResource
    {
        $export = InvoiceExport::whereCompany($request->header('company'))->findOrFail($id);

        $this->authorize('view', $export);

        return new InvoiceExportResource($export);
    }

    /**
     * Download the completed export file.
     */
    public function download(int|string $id, Request $request): Response
    {
        $export = InvoiceExport::whereCompany($request->header('company'))->findOrFail($id);

        $this->authorize('download', $export);

        if (! $export->isCompleted() || ! $export->file_path) {
            abort(404, 'Export file is not ready or has failed.');
        }

        if ($export->expires_at && $export->expires_at->isPast()) {
            abort(410, 'Export file has expired.');
        }

        if ($export->disk === 'local') {
            if (! Storage::disk('local')->exists($export->file_path)) {
                abort(404, 'Export file not found.');
            }

            return Storage::disk('local')->download($export->file_path, $export->file_name);
        }

        $fileDisk = $export->file_disk_id
            ? FileDisk::whereCompany($export->company_id)->find($export->file_disk_id)
            : FileDisk::whereCompany($export->company_id)->where('driver', $export->disk)->first();

        if ($fileDisk) {
            return $fileDisk->withStorage(function ($customDisk) use ($export) {
                if (! Storage::disk($customDisk)->exists($export->file_path)) {
                    abort(404, 'Export file not found.');
                }

                return Storage::disk($customDisk)->download($export->file_path, $export->file_name);
            });
        }

        abort(404, 'Storage disk not available.');
    }
}
