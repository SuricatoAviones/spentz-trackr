<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    /**
     * Persist the user's language preference and apply it to the current request.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(config('app.available_locales'))],
        ]);

        if ($request->user() !== null) {
            $request->user()->update(['locale' => $validated['locale']]);
        }

        $request->session()->put('locale', $validated['locale']);
        App::setLocale($validated['locale']);

        return back();
    }
}
