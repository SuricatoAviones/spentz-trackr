<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\User;
use App\Support\Features as InstanceFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends BaseApiController
{
    /**
     * Hash de un valor que nadie usa, con el mismo coste que los reales, para
     * que el login tarde lo mismo exista o no el email. Ver `store()`.
     */
    private const DECOY_HASH = '$2y$12$NZKv3FyQKF/t6Z5sVuY6QOc7OydUpFn2L80zi/Um9acSWJDYT7vjq';

    /**
     * Register a new user and return a personal access token.
     */
    public function register(Request $request, CreateNewUser $createNewUser): JsonResponse
    {
        // Mismo interruptor que el formulario web: con el registro cerrado la
        // API no puede ser la puerta de atrás de una instancia privada.
        abort_unless(InstanceFeatures::registrationEnabled(), 404);

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

        // Si el email no existe se compara igualmente contra un hash señuelo:
        // sin esto la respuesta volvía de inmediato (no se calculaba bcrypt) y
        // el tiempo delataba qué correos están registrados.
        $knownHash = $user === null ? self::DECOY_HASH : $user->password;

        $passwordMatches = Hash::check($request->string('password'), $knownHash);

        if ($user === null || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->isSuspended()) {
            return $this->apiError(__('messages.account_suspended'), 403);
        }

        $token = $user->createToken($request->string('device_name', 'spent-trackr-api'), ['*'], now()->addDays(90));

        return $this->apiResponse([
            'token' => $token->plainTextToken,
            'token_id' => $token->accessToken->id,
            'user' => $this->userShape($user),
        ], __('messages.api_login_success'));
    }

    /**
     * Revoke the current personal access token.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->apiResponse(message: __('messages.api_logout_success'));
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
