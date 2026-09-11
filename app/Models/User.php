<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_admin
 * @property Carbon|null $suspended_at
 * @property Carbon|null $email_verified_at
 * @property string $default_display_currency
 * @property string|null $min_commission
 * @property string|null $commission_rate
 * @property string $tracking_type
 * @property string|null $monthly_budget
 * @property string|null $locale
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $expenses_count aggregate helper (withCount)
 * @property-read string|null $expenses_sum_usd_amount aggregate helper (withSum)
 * @property-read string|null $expenses_max_spent_at aggregate helper (withMax)
 */
#[Fillable(['name', 'email', 'password', 'default_display_currency', 'min_commission', 'commission_rate', 'tracking_type', 'monthly_budget', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<PaymentSource, $this>
     */
    public function paymentSources(): HasMany
    {
        return $this->hasMany(PaymentSource::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<Income, $this>
     */
    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    /**
     * @return HasMany<SavingsGoal, $this>
     */
    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    /**
     * @return HasMany<RecurringPayment, $this>
     */
    public function recurringPayments(): HasMany
    {
        return $this->hasMany(RecurringPayment::class);
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_admin' => 'boolean',
            'suspended_at' => 'datetime',
            'min_commission' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'monthly_budget' => 'decimal:2',
        ];
    }
}
