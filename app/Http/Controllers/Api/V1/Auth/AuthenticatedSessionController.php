<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends BaseApiController
{
    /**
     * Register a new user and return a personal access token.
     */
    public function register(Request $request, CreateNewUser $createNewUser): JsonResponse
    {
        $user = $createNewUser->create($request->all());

        $token = $user->createToken('spent-trackr-api', ['*'], now()->addDays(90));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token->plainTextToken,
                'token_id' => $token->accessToken->id,
                'user' => $this->userShape($user),
            ],
            'message' => 'Usuario registrado correctamente',
        ], 201);
    }

    /**
     * Issue a personal access token for valid credentials.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if ($user->isSuspended()) {
            return $this->apiError('Tu cuenta está suspendida.', 403);
        }

        $token = $user->createToken($request->string('device_name', 'spent-trackr-api'), ['*'], now()->addDays(90));

        return $this->apiResponse([
            'token' => $token->plainTextToken,
            'token_id' => $token->accessToken->id,
            'user' => $this->userShape($user),
        ], 'Inicio de sesión exitoso');
    }

    /**
     * Revoke the current personal access token.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->apiResponse(message: 'Sesión cerrada correctamente');
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->apiResponse($this->userShape($request->user()));
    }

    /**
     * @return array<string, mixed>
     */
    private function userShape(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->isAdmin(),
            'locale' => $user->locale,
            'tracking_type' => $user->tracking_type,
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }
}
