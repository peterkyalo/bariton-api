<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $users = $query->latest()->paginate(15);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'date_of_birth' => $request->input('date_of_birth'),
            'status' => $request->input('status', 'active'),
            'email_verified_at' => now(),
            'privacy_consent_at' => now(),
            'privacy_policy_version' => '1.0',
            'password' => Hash::make($request->input('password')),
        ]);

        $user->syncRoles($request->input('roles'));

        activity()
            ->performedOn($user)
            ->causedBy($request->user())
            ->log("Created user {$user->name} ({$user->email}) with roles: ".implode(', ', $request->input('roles')));

        return response()->json([
            'message' => 'User created successfully.',
            'data' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->update($request->only('name', 'email', 'phone', 'date_of_birth', 'status'));

        activity()
            ->performedOn($user)
            ->causedBy($request->user())
            ->log("Updated user details for {$user->name}");

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function updateRoles(Request $request, User $user): JsonResponse
    {
        $this->authorize('assignRoles', $user);

        $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($request->input('roles'));

        activity()
            ->performedOn($user)
            ->causedBy($request->user())
            ->log("Assigned roles to {$user->name}: ".implode(', ', $request->input('roles')));

        return response()->json([
            'message' => 'User roles updated successfully.',
            'data' => new UserResource($user),
        ]);
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->authorize('updateStatus', $user);

        $request->validate([
            'status' => ['required', 'in:active,pending_guardian,suspended'],
        ]);

        $user->update(['status' => $request->input('status')]);

        activity()
            ->performedOn($user)
            ->causedBy($request->user())
            ->log("Changed status of {$user->name} to {$request->input('status')}");

        return response()->json([
            'message' => 'User status updated successfully.',
            'data' => new UserResource($user),
        ]);
    }
}
