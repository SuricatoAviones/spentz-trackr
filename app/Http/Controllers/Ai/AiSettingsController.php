<?php

namespace App\Http\Controllers\Ai;

use App\Actions\Ai\UpdateAiSettingsAction;
use App\Actions\Ai\VerifyAiConnectionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\UpdateAiSettingsRequest;
use App\Support\Presenters\AiSettingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('ai/settings', [
            'setting' => AiSettingPresenter::present($request->user()->aiSetting),
            'providers' => AiSettingPresenter::providers(),
            'customEndpointsAllowed' => (bool) config('ai.allow_custom_endpoints', false),
        ]);
    }

    public function update(UpdateAiSettingsRequest $request, UpdateAiSettingsAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.ai_settings_saved')]);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->aiSetting()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('messages.ai_settings_deleted')]);

        return back();
    }

    /**
     * Prueba la conexión. JSON (no redirect) para que la página muestre el
     * resultado junto al botón; si falla, `AiException` responde con el motivo.
     */
    public function test(Request $request, VerifyAiConnectionAction $action): JsonResponse
    {
        $setting = $action->handle($request->user());

        return response()->json([
            'success' => true,
            'message' => __('messages.ai_connection_ok'),
            'data' => AiSettingPresenter::present($setting),
        ]);
    }
}
