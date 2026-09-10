<?php

namespace App\Http\Controllers;

use App\Services\Installer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class InstallController extends Controller
{
    public function __construct(private readonly Installer $installer)
    {
        // The wizard must work on a fresh deploy with no .env (APP_ENV then
        // defaults to "production"), so the only guards are: not already
        // installed, and not running in headless/Docker mode.
        $installMode = $_SERVER['APP_INSTALL_MODE'] ?? $_ENV['APP_INSTALL_MODE'] ?? getenv('APP_INSTALL_MODE');

        if ($installMode === 'headless') {
            abort(404);
        }

        if ($installer->isInstalled()) {
            abort(403, 'La aplicación ya está instalada.');
        }
    }

    public function welcome(): Response
    {
        return Inertia::render('install/Requirements', [
            'requirements' => $this->installer->checkRequirements(),
        ]);
    }

    public function database(): Response
    {
        $database = session('install.database', $this->installer->defaultDatabase());

        return Inertia::render('install/Database', [
            'defaults' => $database,
        ]);
    }

    public function storeDatabase(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'connection' => ['required', 'in:sqlite,mysql,pgsql'],
            'host' => ['required_if:connection,mysql,pgsql', 'nullable', 'string'],
            'port' => ['required_if:connection,mysql,pgsql', 'nullable', 'string'],
            'database' => ['required', 'string'],
            'username' => ['required_if:connection,mysql,pgsql', 'nullable', 'string'],
            'password' => ['nullable', 'string'],
        ])->validated();

        session(['install.database' => $data]);

        return response()->json(['success' => true]);
    }

    public function appSetup(): Response
    {
        return Inertia::render('install/AppSetup', [
            'defaults' => [
                'app_name' => config('app.name', 'Spentz Trackr'),
                'app_url' => config('app.url', request()->getSchemeAndHttpHost()),
                'app_locale' => config('app.locale', 'es'),
                'timezone' => config('app.timezone', 'UTC'),
            ],
            'database' => session('install.database', $this->installer->defaultDatabase()),
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'db_connection' => ['required', 'in:sqlite,mysql,pgsql'],
            'db_host' => ['required_if:db_connection,mysql,pgsql', 'nullable', 'string'],
            'db_port' => ['required_if:db_connection,mysql,pgsql', 'nullable', 'string'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required_if:db_connection,mysql,pgsql', 'nullable', 'string'],
            'db_password' => ['nullable', 'string'],
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:255'],
            'app_locale' => ['required', 'in:es,en'],
            'timezone' => ['required', 'string'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->installer->install(array_map(
            static fn ($value): ?string => $value === null ? null : (string) $value,
            $validator->validated(),
        ));

        return response()->json([
            'success' => true,
            'message' => 'Instalación completada correctamente.',
        ]);
    }

    public function finish(): Response
    {
        return Inertia::render('install/Finished');
    }
}
