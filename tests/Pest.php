<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\DocumentSeries;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\BusinessSettingsSeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\DocumentSeriesSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UnitSeeder;

/** Siembra los cimientos (emisor, series, catálogo, admin) para los tests. */
function seedFoundations(): void
{
    (new BusinessSettingsSeeder)->run();
    (new UnitSeeder)->run();
    (new DocumentSeriesSeeder)->run();
    (new ProductSeeder)->run();
    (new CustomerSeeder)->run();
    (new AdminUserSeeder)->run();
}

function adminUser(): User
{
    return User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
}

function opSeries(): DocumentSeries
{
    return DocumentSeries::query()->where('code', 'OP')->firstOrFail();
}

/** Serie de facturas SAR (un CAI) para tests. Completa la razón social del emisor. */
function invoiceSeries(array $overrides = []): DocumentSeries
{
    \App\Models\BusinessSettings::current()?->update(['legal_name' => 'Juan Pérez (prueba)']);

    static $n = 0;
    $n++;

    return DocumentSeries::create(array_merge([
        'code' => 'FAC'.$n,
        'title' => 'FACTURA',
        'kind' => DocumentSeries::KIND_INVOICE,
        'origin' => DocumentSeries::ORIGIN_SYSTEM,
        'number_from' => 1,
        'next_number' => 1,
        'number_to' => 100,
        'pad_length' => 8,
        'cai' => 'A1B2C3-D4E5F6-A7B8C9-D0E1F2-A3B4C5-D6',
        'establishment' => '000',
        'emission_point' => '001',
        'doc_type_code' => '01',
        'issue_deadline' => now('America/Tegucigalpa')->addMonths(6)->toDateString(),
        'status' => 'active',
    ], $overrides));
}

function sellerUser(): User
{
    return User::query()->firstOrCreate(['email' => 'vendedor@test'], [
        'name' => 'Vendedor', 'username' => 'vendedor', 'password' => 'secret', 'role' => User::ROLE_SELLER, 'active' => true,
    ]);
}

function line(string $desc = 'Repollo', string $qty = '1', string $price = '100.00', string $tax = 'exempt'): array
{
    return ['description' => $desc, 'unit' => 'Saco', 'quantity' => $qty, 'unit_price' => $price, 'tax_type' => $tax];
}
