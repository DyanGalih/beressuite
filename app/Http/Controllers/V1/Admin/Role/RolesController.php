<?php

namespace Crater\Http\Controllers\V1\Admin\Role;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\RoleRequest;
use Crater\Http\Resources\RoleResource;
use Crater\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Silber\Bouncer\BouncerFacade;
use Silber\Bouncer\Database\Role;

class RolesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::when($request->has('orderByField'), function ($query) use ($request) {
            return $query->orderBy($request['orderByField'], $request['orderBy']);
        })
            ->when($request->company_id, function ($query) use ($request) {
                abort_unless($request->user()->hasCompany($request->company_id), 403);

                return $query->withoutGlobalScopes()->where('scope', (int) $request->company_id);
            })
            ->get();

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request): RoleResource
    {
        $this->authorize('create', Role::class);

        $role = Role::create($request->getRolePayload());

        $this->syncAbilities($request, $role);

        return new RoleResource($role);
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return new RoleResource($role);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, Role $role): RoleResource
    {
        $this->authorize('update', $role);

        $role->update($request->getRolePayload());

        $this->syncAbilities($request, $role);

        return new RoleResource($role);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $users = User::whereIs($role->name)->get()->toArray();

        if (! empty($users)) {
            return respondJson('role_attached_to_users', 'Roles Attached to user');
        }

        $role->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    private function syncAbilities(RoleRequest $request, Role $role): bool
    {
        foreach (config('abilities.abilities') as $ability) {
            $check = array_search($ability['ability'], array_column($request->abilities, 'ability'));
            if ($check !== false) {
                BouncerFacade::allow($role)->to($ability['ability'], $ability['model']);
            } else {
                BouncerFacade::disallow($role)->to($ability['ability'], $ability['model']);
            }
        }

        return true;
    }
}
