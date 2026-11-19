<?php

namespace Crater\Traits;

use Crater\Models\CustomField;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCustomFieldsTrait
{
    public function fields(): MorphMany
    {
        return $this->morphMany('Crater\Models\CustomFieldValue', 'custom_field_valuable');
    }

    protected static function booted()
    {
        static::deleting(function ($data) {
            if ($data->fields()->exists()) {
                $data->fields()->delete();
            }
        });
    }

    public function addCustomFields(iterable $customFields): void
    {
        foreach ($customFields as $field) {
            if (! is_array($field)) {
                $field = (array)$field;
            }
            $customField = CustomField::find($field['id']);

            $customFieldValue = [
                'type' => $customField->type,
                'custom_field_id' => $customField->id,
                'company_id' => $customField->company_id,
                getCustomFieldValueKey($customField->type) => $field['value'],
            ];

            $this->fields()->create($customFieldValue);
        }
    }

    public function updateCustomFields(iterable $customFields): void
    {
        foreach ($customFields as $field) {
            if (! is_array($field)) {
                $field = (array)$field;
            }

            $customField = CustomField::find($field['id']);
            $customFieldValue = $this->fields()->firstOrCreate([
                'custom_field_id' => $customField->id,
                'type' => $customField->type,
                'company_id' => $this->company_id,
            ]);

            $type = getCustomFieldValueKey($customField->type);
            $customFieldValue->$type = $field['value'];
            $customFieldValue->save();
        }
    }

    public function getCustomFieldBySlug(string $slug): mixed
    {
        return $this->fields()
            ->with('customField')
            ->whereHas('customField', function ($query) use ($slug) {
                $query->where('slug', $slug);
            })->first();
    }

    public function getCustomFieldValueBySlug(string $slug): mixed
    {
        $value = $this->getCustomFieldBySlug($slug);

        if ($value) {
            return $value->defaultAnswer;
        }

        return null;
    }
}
