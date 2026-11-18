<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BusinessSettings;
use App\Models\DocumentSeries;
use App\Services\BackupService;
use App\Support\DefaultCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SettingsController extends Controller
{
    public function index(BackupService $backups): Response
    {
        DocumentSeries::fiscalAlerts(); // refresca estados agotada/vencida

        return Inertia::render('Ajustes/Index', [
            'default_credentials' => DefaultCredentials::inspect(),
            'business' => BusinessSettings::current(),
            'series' => DocumentSeries::query()->withExists('sales')->orderBy('kind')->orderBy('number_from')->get()
                ->map(fn (DocumentSeries $s) => [
                    ...$s->only(['id', 'code', 'title', 'kind', 'origin', 'cai', 'establishment', 'emission_point',
                        'doc_type_code', 'number_from', 'number_to', 'next_number', 'status', 'range_alert_threshold', 'deadline_alert_days', 'notes']),
                    'issue_deadline' => $s->issue_deadline?->toDateString(),
                    'range_label' => $s->rangeLabel(),
                    'next_label' => $s->formatNumber($s->next_number),
                    'remaining' => $s->remaining(),
                    'days_to_deadline' => $s->daysToDeadline(),
                    'usable' => $s->isUsable(),
                    'used' => $s->sales_exists,
                ]),
            'backups' => array_slice($backups->list(), 0, 10),
        ]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'trade_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'activity' => ['nullable', 'string', 'max:255'],
            'rtn' => ['nullable', 'regex:/^\d{14}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:120'],
            'paper_size' => ['nullable', 'in:letter,half-letter'],
        ], ['rtn.regex' => 'El RTN debe tener 14 números, sin guiones.']);

        $settings = BusinessSettings::current() ?? new BusinessSettings;
        $print = $settings->print_settings ?? [];
        $print['paper_size'] = $data['paper_size'] ?? ($print['paper_size'] ?? 'letter');
        unset($data['paper_size']);
        $settings->fill($data + ['print_settings' => $print])->save();

        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'settings.business', 'data' => $data]);

        return back()->with('status', 'Datos del negocio guardados. Se usarán en los documentos nuevos.');
    }

    /** Registra un CAI nuevo (rango autorizado + fecha límite) como serie de facturas. */
    public function storeCai(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cai' => ['required', 'string', 'regex:/^[A-Za-z0-9]{6}(-[A-Za-z0-9]{6}){4}-[A-Za-z0-9]{2}$/', 'unique:document_series,cai'],
            'establishment' => ['required', 'digits:3'],
            'emission_point' => ['required', 'digits:3'],
            'doc_type_code' => ['required', 'digits:2'],
            'number_from' => ['required', 'integer', 'min:1', 'max:99999999'],
            'number_to' => ['required', 'integer', 'gte:number_from', 'max:99999999'],
            'issue_deadline' => ['required', 'date', 'after_or_equal:today'],
            'range_alert_threshold' => ['nullable', 'integer', 'min:0'],
            'deadline_alert_days' => ['nullable', 'integer', 'min:0'],
        ], [
            'cai.regex' => 'El CAI tiene el formato XXXXXX-XXXXXX-XXXXXX-XXXXXX-XXXXXX-XX (36 caracteres con guiones).',
            'cai.unique' => 'Ese CAI ya está registrado.',
            'number_to.gte' => 'El número final debe ser mayor o igual al inicial.',
            'issue_deadline.after_or_equal' => 'La fecha límite ya pasó.',
        ]);

        // No se permiten rangos que se crucen con otro CAI del mismo establecimiento/punto/tipo.
        $overlap = DocumentSeries::query()->where('kind', DocumentSeries::KIND_INVOICE)
            ->where('establishment', $data['establishment'])->where('emission_point', $data['emission_point'])
            ->where('doc_type_code', $data['doc_type_code'])
            ->where('number_from', '<=', $data['number_to'])->where('number_to', '>=', $data['number_from'])
            ->exists();
        if ($overlap) {
            return back()->withErrors(['number_from' => 'Ese rango se cruza con otro CAI ya registrado.']);
        }

        $series = DocumentSeries::create([
            'code' => 'FAC-'.$data['establishment'].$data['emission_point'].$data['doc_type_code'].'-'.$data['number_from'],
            'title' => 'FACTURA',
            'kind' => DocumentSeries::KIND_INVOICE,
            'origin' => DocumentSeries::ORIGIN_SYSTEM,
            'cai' => strtoupper($data['cai']),
            'establishment' => $data['establishment'],
            'emission_point' => $data['emission_point'],
            'doc_type_code' => $data['doc_type_code'],
            'number_from' => $data['number_from'],
            'next_number' => $data['number_from'],
            'number_to' => $data['number_to'],
            'pad_length' => 8,
            'issue_deadline' => $data['issue_deadline'],
            'range_alert_threshold' => $data['range_alert_threshold'] ?? 50,
            'deadline_alert_days' => $data['deadline_alert_days'] ?? 15,
            'status' => 'active',
        ]);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'series.cai_registered',
            'auditable_type' => DocumentSeries::class, 'auditable_id' => $series->id, 'data' => $data]);

        return back()->with('status', 'CAI registrado. Las facturas usarán este rango cuando se acabe el anterior.');
    }

    /** Solo se puede desactivar/activar una serie y cambiar los avisos; el rango no se edita. */
    public function updateSeries(Request $request, DocumentSeries $series): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive'],
            'range_alert_threshold' => ['nullable', 'integer', 'min:0'],
            'deadline_alert_days' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($series->status === 'exhausted' || $series->status === 'expired') {
            unset($data['status']);
        }
        $series->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('status', 'Serie guardada.');
    }

    public function backup(BackupService $backups): RedirectResponse
    {
        try {
            $file = $backups->run();
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Respaldo hecho: '.basename($file).'. Cópielo a una memoria USB.');
    }

    public function downloadBackup(BackupService $backups, string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^elrey_[\d_-]+\.dump$/', $file) === 1, 404);
        $path = $backups->directory().'/'.$file;
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }
}
