<?php

namespace App\Http\Controllers\V1\Admin\CustomField;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomFieldRequest;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomFieldsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomField::class);

        $limit = $request->has('limit') ? $request->limit : 5;

        $customFields = CustomField::applyFilters($request->all())
            ->whereCompany()
            ->latest()
            ->paginateData($limit);

        return CustomFieldResource::collection($customFields);
    }

    public function store(CustomFieldRequest $request): CustomFieldResource
    {
        $this->authorize('create', CustomField::class);

        $customField = CustomField::createCustomField($request);

        return new CustomFieldResource($customField);
    }

    public function show(CustomField $customField): CustomFieldResource
    {
        $this->authorize('view', $customField);

        return new CustomFieldResource($customField);
    }

    public function update(CustomFieldRequest $request, CustomField $customField): CustomFieldResource
    {
        $this->authorize('update', $customField);

        $customField->updateCustomField($request);

        return new CustomFieldResource($customField);
    }

    public function destroy(CustomField $customField): JsonResponse
    {
        $this->authorize('delete', $customField);

        if ($customField->customFieldValues()->exists()) {
            $customField->customFieldValues()->delete();
        }

        $customField->forceDelete();

        return response()->json([
            'success' => true,
        ]);
    }
}
