<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'percent' => $this->percent,
            'type' => $this->type,
            'compound_tax' => $this->compound_tax,
            'collective_tax' => $this->collective_tax,
            'description' => $this->description,
            'company_id' => $this->company_id,
            'company' => $this->when($this->relationLoaded('company'), function () {
                return new CompanyResource($this->company);
            }),
        ];
    }
}
