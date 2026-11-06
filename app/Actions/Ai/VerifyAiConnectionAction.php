<?php

namespace App\Actions\Ai;

use App\Models\AiSetting;
use App\Models\User;
use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\Date;

/**
 * Hace una petición mínima al proveedor para comprobar clave, modelo y
 * endpoint. Si falla lanza `AiException` con el motivo ya traducido.
 */
class VerifyAiConnectionAction
{
    public function __construct(private readonly AiManager $ai) {}

    public function handle(User $user): AiSetting
    {
        $this->ai->forUser($user)->verify();

        /** @var AiSetting $setting */
        $setting = $user->aiSetting;
        $setting->forceFill(['last_verified_at' => Date::now()])->save();

        return $setting;
    }
}
