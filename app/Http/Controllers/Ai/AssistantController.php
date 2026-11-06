<?php

namespace App\Http\Controllers\Ai;

use App\Actions\Ai\AnalyzeStatementAction;
use App\Actions\Ai\AskAssistantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AskAssistantRequest;
use App\Http\Requests\StatementRequest;
use App\Models\AiSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function index(Request $request): Response
    {
        $setting = $request->user()->aiSetting;

        return Inertia::render('ai/assistant', [
            'assistant' => [
                'configured' => $setting instanceof AiSetting && $setting->isReady(),
                'provider' => $setting?->provider->label(),
                'model' => $setting?->resolvedModel(),
            ],
            'trackingType' => $request->user()->tracking_type,
        ]);
    }

    public function ask(AskAssistantRequest $request, AskAssistantAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request->user(), $request->history()),
        ]);
    }

    public function analyzeStatement(StatementRequest $request, AnalyzeStatementAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $action->handle($request->user(), $request->from(), $request->to()),
        ]);
    }
}
