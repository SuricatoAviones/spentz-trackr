<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Serve the PWA manifest from a route instead of a static file so its
 * user-facing strings follow the resolved locale (`SetLocale` runs on the web
 * group). A static `public/manifest.webmanifest` would shadow this route, so it
 * was removed on purpose — don't add it back.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $name = (string) config('app.name', 'Spentz Trackr');

        $icon = fn (string $size, string $purpose): array => [
            'src' => '/images/logo.png',
            'sizes' => $size,
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        return response()
            ->json([
                // The product name is a brand: it is not translated.
                'name' => $name,
                'short_name' => $name,
                'description' => __('messages.pwa_description'),
                'start_url' => '/',
                'display' => 'standalone',
                'background_color' => '#0B1220',
                'theme_color' => '#0B1220',
                'lang' => str_replace('_', '-', app()->getLocale()),
                'dir' => 'ltr',
                'orientation' => 'portrait-primary',
                'scope' => '.',
                'icons' => [
                    $icon('192x192', 'any'),
                    $icon('512x512', 'any'),
                    $icon('192x192', 'maskable'),
                    $icon('512x512', 'maskable'),
                ],
            ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json');
    }
}
