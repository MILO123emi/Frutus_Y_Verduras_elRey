<?php

use App\Models\Customer;
use App\Models\Freight;
use App\Models\FreightSchedule;
use App\Services\AccountsService;
use App\Services\FreightService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedFoundations();
    $this->svc = app(FreightService::class);
    $this->wed = FreightSchedule::create(['weekday' => 3, 'destination' => 'San Pedro Sula', 'active' => true]);
    $this->fri = FreightSchedule::create(['weekday' => 5, 'destination' => 'San Pedro Sula', 'active' => true]);
    FreightSchedule::query()->update(['created_at' => '2026-01-01 00:00:00']);
});

it('crea los viajes fijos de miércoles y viernes de la semana', function () {
    $created = $this->svc->materialize(CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-22'));

    expect($created)->toBe(2)
        ->and(Freight::query()->orderBy('trip_date')->pluck('trip_date')->map->toDateString()->all())
        ->toBe(['2026-11-18', '2026-11-20']);
});

it('es idempotente: abrir la semana dos veces no duplica viajes', function () {
    $range = [CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')];
    $this->svc->materialize(...$range);
    $again = $this->svc->materialize(...$range);

    expect($again)->toBe(0)->and(Freight::count())->toBe(8); // 4 miércoles + 4 viernes
});

it('un viaje cancelado no se vuelve a crear', function () {
    $this->svc->materialize(CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-22'));
    Freight::query()->where('trip_date', '2026-11-18')->update(['status' => 'cancelled', 'customized' => true]);
    $this->svc->materialize(CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-22'));

    expect(Freight::query()->where('trip_date', '2026-11-18')->count())->toBe(1)
        ->and(Freight::query()->where('trip_date', '2026-11-18')->value('status'))->toBe('cancelled');
});

it('al cambiar la plantilla regenera solo los viajes futuros sin tocar', function () {
    $today = CarbonImmutable::parse('2026-11-17');
    $this->svc->materialize(CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-29'));
    Freight::query()->where('trip_date', '2026-11-25')->update(['customized' => true, 'notes' => 'Llevar cajas']);

    $this->wed->update(['destination' => 'Tegucigalpa']);
    $this->svc->resetFuture($this->wed, $today);
    $this->svc->materialize(CarbonImmutable::parse('2026-11-09'), CarbonImmutable::parse('2026-11-29'));

    $dest = Freight::query()->where('freight_schedule_id', $this->wed->id)->orderBy('trip_date')->pluck('destination', 'trip_date');
    expect($dest->values()->all())->toBe(['San Pedro Sula', 'Tegucigalpa', 'San Pedro Sula']); // 11 pasado, 18 futuro, 25 editado
});

it('no crea viajes de plantillas inactivas ni antes de que existiera la plantilla', function () {
    $this->fri->update(['active' => false]);
    FreightSchedule::query()->whereKey($this->wed->id)->update(['created_at' => '2026-11-19 00:00:00']);

    expect($this->svc->materialize(CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-29')))->toBe(1);
});

it('reproduces the edit form payload and isolates the submit event in status', function () {
    $this->wed->update(['departure_time' => '07:30']);
    $this->svc->materialize(CarbonImmutable::parse('2026-11-16'), CarbonImmutable::parse('2026-11-22'));
    $freight = Freight::where('freight_schedule_id', $this->wed->id)->firstOrFail();
    $customer = Customer::firstOrFail();
    $freight->update(['customer_id' => $customer->id, 'purpose' => 'customer', 'charge_amount' => '1500.00', 'charge_to_account' => true]);
    $this->actingAs(adminUser());
    $response = $this->get(route('fletes.index', ['fecha' => '2026-11-18']));
    $row = collect($response->original->getData()['page']['props']['freights'])->firstWhere('id', $freight->id);
    expect($row['trip_date'])->toBe('2026-11-18')->and($row['departure_time'])->toBe('07:30');
    // Old openEdit spreads the entire row; useForm serializes its initial form fields.
    $payload = array_replace($freight->toArray(), $row, ['notes' => 'Llevar cajas']);
    // @submit.prevent="saveFreight" passes SubmitEvent as status, serialized to {}.
    $broken = array_replace($payload, ['status' => new stdClass]);
    $this->putJson(route('fletes.update', $freight), $broken)
        ->assertUnprocessable()->assertJsonValidationErrors('status')
        ->assertJsonCount(1, 'errors');
    expect($freight->fresh()->notes)->toBeNull()->and($freight->fresh()->customized)->toBeFalse();
    // Same extras/date/time are accepted once the state remains a string.
    $this->put(route('fletes.update', $freight), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect($freight->fresh()->notes)->toBe('Llevar cajas')->and($freight->fresh()->customized)->toBeTrue();
});

it('cancelling an account freight restores the customer balance', function () {
    $customer = Customer::firstOrFail();
    $freight = Freight::create(['trip_date' => '2026-11-18', 'destination' => 'SPS', 'purpose' => 'customer',
        'customer_id' => $customer->id, 'charge_amount' => '1500.00', 'charge_to_account' => true, 'status' => 'scheduled']);
    $accounts = app(AccountsService::class);
    expect($accounts->balance($customer))->toBe('1500.00');
    $this->actingAs(adminUser())->put(route('fletes.update', $freight), array_replace($freight->toArray(), [
        'trip_date' => '2026-11-18', 'status' => 'cancelled',
    ]))->assertSessionHasNoErrors()->assertRedirect();
    expect($accounts->balance($customer))->toBe('0.00');
});
