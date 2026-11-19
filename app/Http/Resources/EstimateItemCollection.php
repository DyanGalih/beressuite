<?php

namespace Crater\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class EstimateItemCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
