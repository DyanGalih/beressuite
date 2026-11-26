<?php

namespace App\Http\Resources;

use App\Models\CompanySetting;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'level' => $this->level,
            'formatted_created_at' => $this->getFormattedAt(),
            'abilities' => $this->getAbilities(),
        ];
    }

    public function getFormattedAt(): ?string
    {
        $dateFormat = CompanySetting::getSetting('carbon_date_format', $this->scope);
        if (empty($dateFormat)) {
            $dateFormat = config('app.date_format', 'Y-m-d');
        }

        return $this->created_at ? Carbon::parse($this->created_at)->format($dateFormat) : null;
    }
}
