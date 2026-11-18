<?php

namespace Database\Seeders;

use App\Models\AccountMovement;
use App\Models\BusinessSettings;
use App\Models\Customer;
use App\Models\DocumentSeries;
use App\Models\Driver;
use App\Models\Freight;
use App\Models\FreightSchedule;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountsService;
use App\Services\FreightService;
use App\Services\SaleService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Faker\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Sale::query()->exists()) {
            return;
        }

        mt_srand(2026);
        $faker = Factory::create('es_ES');
        $faker->seed(2026);
        $today = CarbonImmutable::today('America/Tegucigalpa');

        DB::transaction(function () use ($faker, $today) {
            $settings = BusinessSettings::current();
            if ($settings->legal_name === null) {
                $settings->update(['legal_name' => 'RAZÓN SOCIAL DE EJEMPLO (cambiar en Ajustes)']);
            }

            $invoiceSeries = DocumentSeries::query()->firstOrCreate(['code' => 'FAC-00000101-1'], [
                'title' => 'FACTURA',
                'kind' => DocumentSeries::KIND_INVOICE,
                'origin' => DocumentSeries::ORIGIN_SYSTEM,
                'cai' => 'EJEMPL-000000-111111-222222-333333-44',
                'establishment' => '000',
                'emission_point' => '001',
                'doc_type_code' => '01',
                'number_from' => 1,
                'number_to' => 500,
                'next_number' => 1,
                'pad_length' => 8,
                'issue_deadline' => $today->addMonthsNoOverflow(6)->toDateString(),
                'status' => 'active',
                'notes' => 'CAI DE EJEMPLO para probar. Apáguelo y registre el real en Ajustes.',
            ]);

            $this->prepareProducts();
            $products = Product::query()->where('active', true)->whereNotNull('reference_price')
                ->with('defaultUnit')->orderBy('form_order')->orderBy('id')->get();
            $customers = Customer::query()->where('active', true)->orderBy('id')->get();
            $admin = User::query()->where('username', env('ADMIN_USERNAME', 'abuelo'))->firstOrFail();
            $seller = User::query()->where('username', 'empleado')->firstOrFail();
            $sales = app(SaleService::class);

            // Seis facturas directas, distribuidas a lo largo de las seis semanas.
            $invoiceDays = [40, 33, 26, 19, 12, 5];
            for ($day = $today->subDays(44); $day->lte($today); $day = $day->addDay()) {
                if ($day->isSunday()) {
                    continue;
                }
                $count = mt_rand(4, 9);
                $minutes = [];
                for ($i = 0; $i < $count; $i++) {
                    $minutes[] = mt_rand(0, 630);
                }
                sort($minutes);

                // Si el día elegido cae domingo, la factura se emite el lunes siguiente.
                $directInvoice = false;
                foreach ($invoiceDays as $daysAgo) {
                    $invoiceDay = $today->subDays($daysAgo);
                    if ($invoiceDay->isSunday()) {
                        $invoiceDay = $invoiceDay->addDay();
                    }
                    $directInvoice = $directInvoice || $day->isSameDay($invoiceDay);
                }

                foreach ($minutes as $i => $minute) {
                    $customer = mt_rand(1, 100) <= 30 ? null : $faker->randomElement($customers->all());
                    $terms = $customer?->default_terms ?? Sale::TERMS_CASH;
                    $lines = [];
                    $lineCount = mt_rand(2, 7);
                    $selection = $faker->randomElements($products->all(), $lineCount);
                    $isInvoice = $directInvoice && $i === 0;
                    foreach ($selection as $product) {
                        $quantity = (string) mt_rand(1, 12);
                        if (mt_rand(1, 10) === 1) {
                            $quantity = mt_rand(1, 11).'.5';
                        }
                        // Precios enteros, ±10%, múltiplos de L 5; sin aritmética float.
                        $reference = (int) $product->reference_price;
                        $minimum = intdiv($reference * 90 + 499, 500);
                        $maximum = intdiv($reference * 110, 500);
                        $price = (string) (mt_rand($minimum, $maximum) * 5);
                        $lines[] = [
                            'product_id' => $product->id,
                            'description' => $product->name,
                            'unit' => $product->defaultUnit->name,
                            'quantity' => $quantity,
                            'unit_price' => $price,
                            'tax_type' => Product::TAX_EXEMPT,
                        ];
                    }

                    // Sin RTN, dos líneas de una unidad mantienen la factura bajo L 10,000.
                    if ($isInvoice && ! $customer?->rtn) {
                        $lines = array_slice($lines, 0, 2);
                        foreach ($lines as &$line) {
                            $line['quantity'] = '1';
                        }
                        unset($line);
                    }

                    $sales->issue([
                        'kind' => $isInvoice ? DocumentSeries::KIND_INVOICE : DocumentSeries::KIND_ORDER,
                        ...($isInvoice ? ['series' => $invoiceSeries] : []),
                        'customer_id' => $customer?->id,
                        'terms' => $terms,
                        'issued_at' => $day->setTime(6, 0)->addMinutes($minute),
                        'due_date' => $terms === Sale::TERMS_CREDIT
                            ? $day->addDays($customer->credit_days ?? 15)->toDateString() : null,
                        'lines' => $lines,
                    ], mt_rand(1, 100) <= 40 ? $seller : $admin);
                }
            }

            $orders = Sale::query()->issued()->whereNull('fiscal_snapshot')->orderBy('id')->limit(2)->get();
            foreach ($orders as $i => $order) {
                $sales->void($order, ['Cliente devolvió el producto', 'Se anotó mal la cantidad'][$i], $admin);
            }

            $convertible = Sale::query()->issued()->whereNull('fiscal_snapshot')
                ->where('terms', Sale::TERMS_CASH)->where('issue_date', '>=', $today->subDays(6)->toDateString())
                ->where(function ($query) {
                    $query->whereNotNull('customer_rtn_snapshot')->orWhere('total', '<', SaleService::INVOICE_ID_THRESHOLD);
                })->orderByDesc('id')->limit(2)->get();
            foreach ($convertible as $order) {
                $sales->convertToInvoice($order, $admin);
            }

            $this->seedPayments($today, $admin);
            $this->seedFreights($today, $admin);
        });
    }

    private function prepareProducts(): void
    {
        // Valores ilustrativos al por mayor en lempiras; se pueden ajustar en Productos.
        $catalog = [
            'Repollo' => ['Saco', 450],
            'Tomate pera' => ['Caja', 380],
            'Tomate manzano' => ['Caja', 420],
            'Papas' => ['Saco', 900],
            'Cebolla roja' => ['Saco', 1100],
            'Cebolla amarilla' => ['Saco', 850],
            'Chiles rellenos' => ['Caja', 350],
            'Ajos' => ['Bolsa', 180],
            'Lechuga chapina' => ['Caja', 300],
            'Zanahoria' => ['Saco', 550],
            'Patastillos' => ['Matate', 220],
            'Apio chapín' => ['Maleta', 160],
            'Habichuelas' => ['Bolsa', 200],
            'Remolacha' => ['Saco', 500],
            'Pepinos' => ['Caja', 280],
            'Jilotes' => ['Bolsa', 150],
            'Ayotes tiernos' => ['Matate', 240],
            'Melón' => ['Unidad', 35],
            'Piñas' => ['Unidad', 30],
            'Bananos maduros' => ['Caja', 250],
            'Yuca' => ['Saco', 450],
            'Perejil' => ['Maleta', 100],
            'Limones' => ['Bolsa', 90],
            'Coliflor' => ['Caja', 450],
            'Camote' => ['Saco', 500],
            'Aguacate' => ['Unidad', 25],
            'Brócoli' => ['Caja', 480],
            'Cilantro' => ['Maleta', 120],
            'Sandías' => ['Unidad', 60],
        ];
        $units = Unit::query()->where('active', true)->pluck('id', 'name');
        foreach ($catalog as $name => [$unit, $price]) {
            Product::query()->where('name', $name)->update([
                'default_unit_id' => $units[$unit],
                'reference_price' => (string) $price,
                'tax_type' => Product::TAX_EXEMPT,
            ]);
        }
    }

    private function seedPayments(CarbonImmutable $today, User $admin): void
    {
        $accounts = app(AccountsService::class);
        $customers = Customer::query()->where('default_terms', Customer::TERMS_CREDIT)->orderBy('id')->get();
        foreach ($customers as $customer) {
            $purchases = Sale::query()->issued()->where('customer_id', $customer->id)
                ->where('terms', Sale::TERMS_CREDIT)->orderBy('issue_date')->orderBy('id')->get();
            if ($purchases->isEmpty()) {
                continue;
            }
            $total = Money::sum(...$purchases->pluck('total')->all());
            $target = Money::round(bcdiv(bcmul($total, (string) mt_rand(50, 85), 2), '100', 4));
            $count = mt_rand(2, 4);
            $paid = '0.00';
            $firstDate = CarbonImmutable::parse($purchases->first()->issue_date->toDateString(), $today->timezone)->addDay();
            $span = (int) $firstDate->diffInDays($today);
            for ($i = 1; $i <= $count; $i++) {
                $date = $firstDate->addDays(intdiv($span * $i, $count));
                $available = Money::sum(...$purchases->filter(fn (Sale $sale) => $sale->issue_date->toDateString() < $date->toDateString())
                    ->pluck('total')->all());
                $desired = $i === $count ? bcsub($target, $paid, 2) : bcdiv($target, (string) $count, 2);
                $remaining = bcsub($available, $paid, 2);
                $amount = bccomp($desired, $remaining, 2) <= 0 ? $desired : $remaining;
                if (bccomp($amount, '0', 2) <= 0) {
                    continue;
                }
                $accounts->record($customer, AccountMovement::TYPE_PAYMENT, [
                    'amount' => $amount,
                    'date' => $date->toDateString(),
                    'method' => $i % 2 === 0 ? 'transfer' : 'cash',
                ], $admin);
                $paid = Money::sum($paid, $amount);
            }
        }

        $accounts->record(Customer::query()->where('name', 'Pulpería Los Ángeles')->firstOrFail(), AccountMovement::TYPE_OPENING, [
            'amount' => '3250.00',
            'date' => $today->subDays(60)->toDateString(),
            'notes' => 'Deuda de la libreta',
        ], $admin);
    }

    private function seedFreights(CarbonImmutable $today, User $admin): void
    {
        $carlos = Driver::query()->updateOrCreate(['name' => 'Carlos Mejía'], [
            'phone' => '9876-5432', 'vehicle' => 'Camión Isuzu blanco', 'active' => true,
        ]);
        $jose = Driver::query()->updateOrCreate(['name' => 'José Ramírez'], [
            'phone' => '9455-1203', 'vehicle' => 'Pick-up Toyota gris', 'active' => true,
        ]);
        foreach ([3, 5] as $weekday) {
            $schedule = FreightSchedule::query()->firstOrCreate([
                'weekday' => $weekday, 'destination' => 'San Pedro Sula', 'driver_id' => $carlos->id,
            ], ['departure_time' => '04:30', 'vehicle' => $carlos->vehicle, 'active' => true]);
            DB::table('freight_schedules')->where('id', $schedule->id)->update(['created_at' => $today->subDays(60)]);
        }
        app(FreightService::class)->materialize($today->subDays(30), $today->addDays(14));
        Freight::query()->where('trip_date', '<', $today->toDateString())->where('status', 'scheduled')->update(['status' => 'done']);

        Freight::create([
            'trip_date' => $today->subDays(5)->toDateString(),
            'driver_id' => $jose->id, 'vehicle' => $jose->vehicle,
            'destination' => 'Tela', 'purpose' => 'family', 'for_whom' => 'Para mi tío Mario',
            'status' => 'done', 'user_id' => $admin->id,
        ]);
        Freight::create([
            'trip_date' => $today->addDays(3)->toDateString(),
            'driver_id' => $carlos->id, 'vehicle' => $carlos->vehicle,
            'destination' => 'San Pedro Sula', 'purpose' => 'customer',
            'customer_id' => Customer::query()->where('name', 'Hotel Costa Atlántida')->firstOrFail()->id,
            'charge_amount' => '1500.00', 'charge_to_account' => true,
            'status' => 'scheduled', 'user_id' => $admin->id,
        ]);
        Freight::create([
            'trip_date' => $today->addDays(6)->toDateString(),
            'driver_id' => $jose->id, 'vehicle' => $jose->vehicle,
            'destination' => 'Olanchito', 'purpose' => 'moving',
            'notes' => 'Llevar muebles y cajas. Recoger en La Ceiba a las 7:00 de la mañana.',
            'status' => 'scheduled', 'user_id' => $admin->id,
        ]);
    }
}
