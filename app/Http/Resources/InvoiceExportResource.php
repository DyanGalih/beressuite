<?php

namespace Crater\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'file_name' => $this->file_name,
            'filters' => $this->filters,
            'total_rows' => $this->total_rows,
            'error_message' => $this->error_message,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => $this->status === 'completed'
                ? url("/api/v1/invoices/export/{$this->id}/download")
                : null,
        ];
    }
}
