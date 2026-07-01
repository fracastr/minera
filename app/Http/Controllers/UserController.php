<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAbilityService;
use App\Support\PasswordRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->select(['id', 'nombre', 'email', 'role', 'activo', 'created_at'])
            ->orderBy('nombre')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => PasswordRules::requiredConfirmed(),
            'role' => ['required', Rule::in(UserAbilityService::allowedRoles())],
            'activo' => 'sometimes|boolean',
        ]);

        $user = User::create([
            'nombre' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'user' => $user->only(['id', 'nombre', 'email', 'role', 'activo']),
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => PasswordRules::sometimesConfirmed(),
            'role' => ['sometimes', Rule::in(UserAbilityService::allowedRoles())],
            'activo' => 'sometimes|boolean',
        ]);

        if (isset($validated['name'])) {
            $user->nombre = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if (isset($validated['role'])) {
            $user->role = $validated['role'];
        }

        if (isset($validated['activo'])) {
            $user->activo = $validated['activo'];
        }

        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user' => $user->only(['id', 'nombre', 'email', 'role', 'activo']),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }
}
