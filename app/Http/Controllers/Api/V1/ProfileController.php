<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends BaseApiController
{
    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()?->id)],
            'default_display_currency' => ['sometimes', 'required', Rule::in(['usd', 'usdt'])],
            'tracking_type' => ['sometimes', 'required', Rule::in(['expenses', 'income', 'both'])],
        ]);

        $request->user()->update($validated);

        return $this->apiResponse(null, 'Perfil actualizado correctamente');
    }

    /**
     * Update the authenticated user's locale.
     */
    public function updateLocale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(['es', 'en'])],
        ]);

        $request->user()->update($validated);

        return $this->apiResponse([
            'locale' => $request->user()->locale,
        ], 'Idioma actualizado correctamente');
    }
}
