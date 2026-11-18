<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\ReportService;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(Request $request): Response
    {
        $range = $this->range($request);

        return Inertia::render('Reportes/Index', [
            'summary' => $this->reports->summary($range),
            'filters' => ['range' => $range->preset, 'from' => $range->fromDate(), 'to' => $range->toDate()],
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $range = $this->range($request);

        return response()->download($this->reports->excel($range), $this->reports->baseName($range).'.xlsx')
            ->deleteFileAfterSend();
    }

    public function zip(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $range = $this->range($request);
        $count = Sale::query()->whereBetween('issue_date', [$range->fromDate(), $range->toDate()])->count();
        abort_if($count === 0, 422, 'No hay ventas en ese rango.');
        set_time_limit(600);

        return response()->stream(fn () => $this->reports->streamZip($range), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$this->reports->baseName($range).'.zip"',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function range(Request $request): DateRange
    {
        return DateRange::make($request->query('range', 'mes'), $request->query('from'), $request->query('to'));
    }
}
