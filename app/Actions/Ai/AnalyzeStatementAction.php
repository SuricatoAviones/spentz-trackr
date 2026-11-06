<?php

namespace App\Actions\Ai;

use App\Actions\Ai\Concerns\BuildsPromptContext;
use App\Models\User;
use App\Services\AccountStatementService;
use App\Services\Ai\AiManager;
use App\Services\Ai\AiMessage;
use App\Services\Ai\AiRequest;
use Carbon\CarbonInterface;

/**
 * Análisis en lenguaje natural de un estado de cuenta.
 *
 * Las cifras las calcula `AccountStatementService` y se le pasan al modelo ya
 * hechas; el modelo solo interpreta. Por eso no necesita herramientas: es una
 * sola llamada, más barata y más rápida que una conversación.
 */
class AnalyzeStatementAction
{
    use BuildsPromptContext;

    /** Movimientos que se le pasan al modelo: los más recientes del periodo. */
    private const MOVEMENTS_FOR_CONTEXT = 120;

    public function __construct(
        private readonly AiManager $ai,
        private readonly AccountStatementService $statements,
    ) {}

    /**
     * @return array{analysis: string, period: array{from: string, to: string}}
     */
    public function handle(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $driver = $this->ai->forUser($user);

        $statement = $this->statements->build($user, $from, $to, self::MOVEMENTS_FOR_CONTEXT);

        foreach (['expense_categories', 'income_categories', 'sources'] as $key) {
            $statement[$key] = array_map(
                fn (array $row): array => array_diff_key($row, array_flip(['color', 'icon', 'id'])),
                $statement[$key],
            );
        }

        $response = $driver->send(new AiRequest(
            system: $this->systemPrompt(),
            messages: [AiMessage::user(
                "Account statement data (JSON):\n".json_encode($statement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            )],
            maxTokens: 6000,
        ));

        return [
            'analysis' => trim($response->text) !== '' ? $response->text : __('messages.ai_assistant_incomplete'),
            'period' => ['from' => $statement['period']['from'], 'to' => $statement['period']['to']],
        ];
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
        You are a personal-finance analyst. You receive one user's account statement for a period as JSON, produced by the Spentz Trackr app. All amounts in *_usd fields are USD equivalents frozen at each record's exchange rate; "ves" means bolívares (Bs) and "usdt" is treated 1:1 with USD. There is no opening balance: running_usd is the cumulative net inside the period. movements may be truncated (movements_truncated) but the totals always cover the whole period.

        Write a short report in {$this->responseLanguage()} using Markdown with these sections:
        1. A two or three sentence summary of the period (incomes, expenses, net, savings rate when available).
        2. Highlights: where the money went (top categories and payment sources) and the comparison with the previous period.
        3. Alerts: unusual or large expenses, categories over budget, budget status, high bolívar commissions, or periods with no income. Skip the section if there is nothing relevant.
        4. Three to five concrete, numbers-based recommendations.

        Use only the figures in the data; never invent transactions. If you compute a number, keep it simple and show the operation. Format amounts as "$1,234.56" for USD and "Bs 1.234,56" for bolívares. End with one line noting this is not professional financial advice.
        {$this->todayLine()}
        PROMPT;
    }
}
