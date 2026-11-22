<?php

namespace App\Http\Controllers\V1\Admin\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\NotesRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('view notes');

        $limit = $request->limit ?? 10;

        $notes = Note::latest()
            ->whereCompany()
            ->applyFilters($request->all())
            ->paginate($limit);

        return NoteResource::collection($notes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NotesRequest $request): NoteResource
    {
        $this->authorize('manage notes');

        $note = Note::create($request->getNotesPayload());

        return new NoteResource($note);
    }

    /**
     * Display the specified resource.
     */
    public function show(Note $note): NoteResource
    {
        $this->authorize('view notes', $note);

        return new NoteResource($note);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(NotesRequest $request, Note $note): NoteResource
    {
        $this->authorize('manage notes', $note);

        $note->update($request->getNotesPayload());

        return new NoteResource($note);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Note $note): JsonResponse
    {
        $this->authorize('manage notes', $note);

        $note->delete();

        return response()->json([
            'success' => true,
        ]);
    }
}
