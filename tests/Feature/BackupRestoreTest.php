<?php

/*
 * Backup y restauración. Una copia que no se sabe restaurar es una falsa
 * sensación de seguridad, así que el test que más importa aquí es el del viaje
 * de ida y vuelta.
 */

use App\Models\Category;
use App\Models\CreditCard;
use App\Models\CreditCardStatement;
use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentSource;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Support\BackupSchema;
use Illuminate\Support\Facades\DB;

function backupFile(User $admin): string
{
    $json = test()->actingAs($admin)
        ->post(route('admin.system.backup'))
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'spentz').'.json';
    file_put_contents($path, $json);

    return $path;
}

function seedSomeData(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $category = Category::factory()->for($user)->create();
    $source = PaymentSource::factory()->for($user)->create();

    Expense::factory()->count(3)->for($user)->for($category)->for($source, 'paymentSource')->create();
    Income::factory()->count(2)->for($user)->for($category)->create();
    SavingsGoal::factory()->for($user)->create();

    $card = CreditCard::factory()->for($user)->create(['payment_source_id' => $source->id]);
    CreditCardStatement::factory()->for($card)->create();

    return $user;
}

test('the backup carries every table the schema declares', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    seedSomeData();

    $payload = json_decode(file_get_contents(backupFile($admin)), true);

    // Antes eran dos listas separadas y se desincronizaban en silencio: el
    // fichero llegó a omitir ingresos, metas y tarjetas.
    foreach (array_keys(BackupSchema::tables()) as $key) {
        expect($payload)->toHaveKey($key);
    }

    expect($payload['format_version'])->toBe(BackupSchema::VERSION)
        ->and($payload['credit_cards'])->toHaveCount(1)
        ->and($payload['incomes'])->toHaveCount(2);
});

test('a backup restores into an empty database with every row intact', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    seedSomeData();

    $before = collect(BackupSchema::tables())
        ->map(fn (string $table) => DB::table($table)->count());

    $file = backupFile($admin);

    // Se vacía todo y se restaura: el viaje completo de ida y vuelta.
    $this->artisan('backup:restore', ['file' => $file, '--fresh' => true, '--force' => true])
        ->assertSuccessful();

    $after = collect(BackupSchema::tables())
        ->map(fn (string $table) => DB::table($table)->count());

    expect($after->all())->toBe($before->all());
});

test('restoring refuses to run over existing data unless you ask for fresh', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    seedSomeData();

    $file = backupFile($admin);

    // Sin --fresh los ids chocarían; el comando se planta antes de tocar nada.
    $this->artisan('backup:restore', ['file' => $file, '--force' => true])
        ->expectsOutputToContain('Ya hay datos en la base.')
        ->assertFailed();

    expect(User::query()->count())->toBeGreaterThan(0);
});

test('restored users cannot log in with a known password', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    $user = seedSomeData();
    $email = $user->email;

    $file = backupFile($admin);

    $this->artisan('backup:restore', ['file' => $file, '--fresh' => true, '--force' => true])
        ->assertSuccessful();

    $restored = User::query()->where('email', $email)->firstOrFail();

    // La copia no lleva hashes, así que se pone una clave aleatoria que nadie
    // conoce: la cuenta existe pero solo entra tras recuperar la contraseña.
    expect($restored->password)->not->toBeEmpty()
        ->and(Hash::check('password', $restored->password))->toBeFalse();
});

test('a corrupt or foreign file is rejected before touching the database', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    seedSomeData();
    $usersBefore = User::query()->count();

    $broken = tempnam(sys_get_temp_dir(), 'spentz').'.json';
    file_put_contents($broken, '{ esto no es json');

    $this->artisan('backup:restore', ['file' => $broken, '--fresh' => true, '--force' => true])
        ->assertFailed();

    $foreign = tempnam(sys_get_temp_dir(), 'spentz').'.json';
    file_put_contents($foreign, json_encode(['algo' => 'otra cosa']));

    $this->artisan('backup:restore', ['file' => $foreign, '--fresh' => true, '--force' => true])
        ->expectsOutputToContain('no parece una copia')
        ->assertFailed();

    // Ninguno de los dos debe haber vaciado nada.
    expect(User::query()->count())->toBe($usersBefore);
});

test('a newer format version is refused instead of half restored', function () {
    $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

    $future = tempnam(sys_get_temp_dir(), 'spentz').'.json';
    file_put_contents($future, json_encode([
        'format_version' => BackupSchema::VERSION + 1,
        'users' => [],
    ]));

    $this->artisan('backup:restore', ['file' => $future, '--fresh' => true, '--force' => true])
        ->expectsOutputToContain('Actualiza la aplicación')
        ->assertFailed();
});

test('a missing file fails cleanly', function () {
    $this->artisan('backup:restore', ['file' => 'no-existe.json', '--force' => true])
        ->assertFailed();
});
