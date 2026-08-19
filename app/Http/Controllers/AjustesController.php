<?php

namespace App\Http\Controllers;

use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AjustesController extends Controller
{
    public function __invoke(Request $request, ExchangeRateService $rateService): Response
    {
        $user = $request->user();

        $rateService->ensureFreshRate($user);

        return Inertia::render('ajustes', [
            'rate' => $rateService->rateForUser($user),
            'monthlyExpenseCount' => $user->expenses()
                ->forPeriod(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())
                ->count(),
        ]);
    }
}
