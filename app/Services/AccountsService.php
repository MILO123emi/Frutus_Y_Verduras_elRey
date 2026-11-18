<?php

namespace App\Services;

use App\Models\AccountMovement;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Freight;
use App\Models\Sale;
use App\Models\User;
use App\Support\DateRange;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cuentas por cobrar como libro derivado (ver docs/DECISIONES.md #9):
 *   saldo = ventas a crédito activas + saldos anteriores + fletes cargados a cuenta − abonos.
 * Nada se guarda duplicado; el estado de cada documento se calcula aplicando los abonos FIFO.
 */
class AccountsService
{
    /**
     * Subconsulta con una fila por cargo/abono: customer_id, entry_date, debit, credit.
     * Solo cuenta lo vigente (ventas emitidas, movimientos activos, fletes no cancelados).
     */
    public function entriesQuery(): Builder
    {
        $sales = DB::table('sales')
            ->selectRaw("customer_id, issue_date as entry_date, total as debit, 0::numeric as credit")
            ->where('status', Sale::STATUS_ISSUED)->where('terms', Sale::TERMS_CREDIT)
            ->whereNotNull('customer_id');

        $movements = DB::table('account_movements')
            ->selectRaw("customer_id, movement_date as entry_date,
                CASE WHEN type = 'opening_balance' THEN amount ELSE 0 END as debit,
                CASE WHEN type = 'payment' THEN amount ELSE 0 END as credit")
            ->where('status', 'active');

        $freights = DB::table('freights')
            ->selectRaw('customer_id, trip_date as entry_date, charge_amount as debit, 0::numeric as credit')
            ->where('charge_to_account', true)->where('status', '!=', 'cancelled')
            ->whereNotNull('customer_id')->where('charge_amount', '>', 0);

        return $sales->unionAll($movements)->unionAll($freights);
    }

    /**
     * Clientes con su saldo, ordenados de mayor a menor deuda. Una sola consulta (sin N+1).
     *
     * @return Collection<int, object>
     */
    public function balancesQuery(bool $onlyWithDebt = true, ?string $search = null): Builder
    {
        $totals = DB::query()->fromSub($this->entriesQuery(), 'e')
            ->selectRaw('customer_id, SUM(debit) - SUM(credit) as balance, MIN(entry_date) FILTER (WHERE debit > 0) as oldest')
            ->groupBy('customer_id');

        $q = DB::table('customers as c')
            ->leftJoinSub($totals, 't', 't.customer_id', '=', 'c.id')
            ->select('c.id', 'c.name', 'c.phone', 'c.credit_limit', 'c.default_terms')
            ->selectRaw('COALESCE(t.balance, 0) as balance')
            ->where('c.active', true);

        if ($onlyWithDebt) {
            $q->whereRaw('COALESCE(t.balance, 0) > 0');
        }
        if ($search) {
            $q->whereRaw('unaccent(c.name) ILIKE unaccent(?)', ['%'.$search.'%']);
        }

        return $q->orderByDesc('balance')->orderBy('c.name')->orderBy('c.id');
    }

    public function balances(bool $onlyWithDebt = true, ?string $search = null)
    {
        return $this->balancesQuery($onlyWithDebt, $search)->get()
            ->map(function ($r) {
                $r->balance = Money::sum((string) $r->balance);

                return $r;
            });
    }

    public function balance(Customer $customer): string
    {
        $row = DB::query()->fromSub($this->entriesQuery(), 'e')
            ->where('customer_id', $customer->id)
            ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as balance')
            ->first();

        return Money::sum((string) ($row->balance ?? '0'));
    }

    /** Total por cobrar de todo el negocio (solo saldos positivos). */
    public function totalReceivable(): string
    {
        return Money::sum('0', ...$this->balances()->pluck('balance')->map(fn ($b) => (string) $b)->all());
    }

    /**
     * Movimientos del cliente en orden, con saldo acumulado (estado de cuenta).
     *
     * @return array<int, array<string, mixed>>
     */
    public function ledger(Customer $customer, ?string $from = null, ?string $to = null, bool $includeVoided = false): array
    {
        $rows = [];

        Sale::query()->where('customer_id', $customer->id)->where('terms', Sale::TERMS_CREDIT)
            ->where('status', Sale::STATUS_ISSUED)
            ->get(['id', 'full_number', 'issue_date', 'total', 'created_at', 'fiscal_snapshot'])
            ->each(function (Sale $s) use (&$rows) {
                $rows[] = [
                    'key' => 's'.$s->id, 'date' => $s->issue_date->toDateString(), 'sort' => $s->created_at,
                    'kind' => $s->isInvoice() ? 'Factura' : 'Pedido', 'description' => $s->full_number,
                    'debit' => (string) $s->total, 'credit' => '0.00', 'sale_id' => $s->id, 'movement_id' => null,
                ];
            });

        AccountMovement::query()->where('customer_id', $customer->id)
            ->when(! $includeVoided, fn ($q) => $q->active())
            ->leftJoin('users as voider', 'voider.id', '=', 'account_movements.voided_by')
            ->select('account_movements.*', 'voider.name as voided_by_name')->get()
            ->each(function (AccountMovement $m) use (&$rows) {
                $voided = $m->status === 'voided';
                $voidedBy = $m->voided_by_name;
                $isPayment = $m->type === AccountMovement::TYPE_PAYMENT;
                $rows[] = [
                    'key' => 'm'.$m->id, 'date' => $m->movement_date->toDateString(), 'sort' => $m->created_at,
                    'kind' => $isPayment ? 'Abono' : 'Saldo anterior',
                    'description' => $voided
                        ? 'Anulado · L '.$m->amount.' · '.$m->void_reason.' · Por: '.($voidedBy ?? 'Usuario no disponible')
                        : ($m->notes ?: ($isPayment ? self::methodLabel($m->method) : 'Deuda de antes del sistema')),
                    'status' => $m->status, 'amount' => (string) $m->amount,
                    'void_reason' => $m->void_reason, 'voided_by' => $voidedBy, 'voided_at' => $m->voided_at?->toIso8601String(),
                    'debit' => $voided || $isPayment ? '0.00' : (string) $m->amount,
                    'credit' => ! $voided && $isPayment ? (string) $m->amount : '0.00',
                    'sale_id' => null, 'movement_id' => $m->id,
                ];
            });

        Freight::query()->where('customer_id', $customer->id)->where('charge_to_account', true)
            ->where('status', '!=', 'cancelled')->where('charge_amount', '>', 0)->get()
            ->each(function (Freight $f) use (&$rows) {
                $rows[] = [
                    'key' => 'f'.$f->id, 'date' => $f->trip_date->toDateString(), 'sort' => $f->created_at,
                    'kind' => 'Flete', 'description' => 'Flete a '.$f->destination,
                    'debit' => (string) $f->charge_amount, 'credit' => '0.00', 'sale_id' => null, 'movement_id' => null,
                ];
            });

        usort($rows, fn ($a, $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);

        $running = '0.00';
        $opening = '0.00';
        $out = [];
        foreach ($rows as $r) {
            $running = Money::sum($running, $r['debit'], '-'.$r['credit']);
            if ($from && $r['date'] < $from) {
                $opening = $running;

                continue;
            }
            if ($to && $r['date'] > $to) {
                continue;
            }
            unset($r['sort']);
            $out[] = $r + ['balance' => $running];
        }

        if ($from && $opening !== '0.00') {
            array_unshift($out, [
                'key' => 'opening', 'date' => $from, 'kind' => 'Saldo al inicio', 'description' => '',
                'debit' => '0.00', 'credit' => '0.00', 'balance' => $opening, 'sale_id' => null, 'movement_id' => null,
            ]);
        }

        return $out;
    }

    /**
     * Cargos pendientes aplicando abonos FIFO (lo más viejo se paga primero),
     * con días de antigüedad y antigüedad por tramos.
     *
     * @return array{documents: array<int, array<string, mixed>>, aging: array<string, string>, credit_in_favor: string}
     */
    public function openDocuments(Customer $customer, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now(DateRange::TZ)->startOfDay();
        $ledger = $this->ledger($customer);

        $credits = Money::sum('0', ...array_map(fn ($r) => $r['credit'], $ledger));
        $docs = [];
        foreach ($ledger as $r) {
            if (bccomp($r['debit'], '0', 2) <= 0) {
                continue;
            }
            $applied = bccomp($credits, $r['debit'], 2) >= 0 ? $r['debit'] : $credits;
            $credits = Money::sum($credits, '-'.$applied);
            $pending = Money::sum($r['debit'], '-'.$applied);
            if (bccomp($pending, '0', 2) > 0) {
                $days = (int) CarbonImmutable::parse($r['date'], DateRange::TZ)->diffInDays($today);
                $docs[] = [
                    'date' => $r['date'], 'kind' => $r['kind'], 'description' => $r['description'],
                    'sale_id' => $r['sale_id'], 'amount' => $r['debit'], 'pending' => $pending, 'days' => $days,
                ];
            }
        }

        $aging = ['0_30' => '0.00', '31_60' => '0.00', '61_90' => '0.00', '90_plus' => '0.00'];
        foreach ($docs as $d) {
            $bucket = match (true) {
                $d['days'] <= 30 => '0_30',
                $d['days'] <= 60 => '31_60',
                $d['days'] <= 90 => '61_90',
                default => '90_plus',
            };
            $aging[$bucket] = Money::sum($aging[$bucket], $d['pending']);
        }

        return ['documents' => $docs, 'aging' => $aging, 'credit_in_favor' => $credits];
    }

    /** Registra un abono (o un saldo anterior). */
    public function record(Customer $customer, string $type, array $data, User $user): AccountMovement
    {
        if (! in_array($type, [AccountMovement::TYPE_PAYMENT, AccountMovement::TYPE_OPENING], true)) {
            throw new InvalidArgumentException('Tipo de movimiento inválido.');
        }
        $amount = Money::round((string) $data['amount']);
        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('El monto debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($customer, $type, $data, $user, $amount) {
            $m = AccountMovement::create([
                'customer_id' => $customer->id,
                'type' => $type,
                'movement_date' => $data['date'] ?? CarbonImmutable::now(DateRange::TZ)->toDateString(),
                'amount' => $amount,
                'method' => $type === AccountMovement::TYPE_PAYMENT ? ($data['method'] ?? 'cash') : null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
                'user_id' => $user->id,
            ]);
            AuditLog::create([
                'user_id' => $user->id, 'action' => "account.{$type}",
                'auditable_type' => AccountMovement::class, 'auditable_id' => $m->id,
                'data' => ['customer' => $customer->name, 'amount' => $amount],
            ]);

            return $m;
        });
    }

    /** Anula un abono o saldo anterior (nunca se borra). */
    public function void(AccountMovement $movement, string $reason, User $user): AccountMovement
    {
        if ($movement->status !== 'active') {
            throw new InvalidArgumentException('Este movimiento ya está anulado.');
        }
        $reason = trim($reason) ?: 'Deshecho por el usuario';

        return DB::transaction(function () use ($movement, $reason, $user) {
            $movement->update([
                'status' => 'voided', 'void_reason' => $reason,
                'voided_at' => Carbon::now(), 'voided_by' => $user->id,
            ]);
            AuditLog::create([
                'user_id' => $user->id, 'action' => 'account.voided',
                'auditable_type' => AccountMovement::class, 'auditable_id' => $movement->id,
                'data' => ['reason' => $reason, 'amount' => (string) $movement->amount],
            ]);

            return $movement;
        });
    }

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'transfer' => 'Abono por transferencia',
            'check' => 'Abono con cheque',
            'other' => 'Abono',
            default => 'Abono en efectivo',
        };
    }
}
