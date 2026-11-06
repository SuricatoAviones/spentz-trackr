<?php

namespace App\Actions\Ai\Concerns;

use Illuminate\Support\Facades\Date;

/**
 * Contexto común de los prompts: idioma de respuesta y fecha de hoy.
 *
 * Los prompts se escriben en inglés (es lo que los modelos siguen con más
 * fidelidad) pero se pide responder en el idioma activo del usuario.
 */
trait BuildsPromptContext
{
    protected function responseLanguage(): string
    {
        return app()->getLocale() === 'en' ? 'English' : 'Spanish (Venezuelan usage)';
    }

    protected function todayLine(): string
    {
        $today = Date::today();

        return 'Today is '.$today->toDateString().' ('.$today->englishDayOfWeek.').';
    }
}
