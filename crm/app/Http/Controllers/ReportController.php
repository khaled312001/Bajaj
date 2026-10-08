<?php

namespace App\Http\Controllers;

use App\Exports\SheetWriter;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Activity;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function index()
    {
        return view('reports.index', ['reports' => ReportService::REPORTS]);
    }

    public function show(Request $request, string $key)
    {
        [$from, $to, $emp] = $this->params($request);
        $report = $this->reports->build($key, $from, $to, $emp);

        return view('reports.show', [
            'report' => $report, 'key' => $key, 'meta' => ReportService::REPORTS[$key],
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'from' => $from, 'to' => $to, 'emp' => $emp,
        ]);
    }

    public function export(Request $request, string $key)
    {
        [$from, $to, $emp] = $this->params($request);
        $r = $this->reports->build($key, $from, $to, $emp);
        Activity::log('export', null, 'تصدير تقرير: ' . $r['title'], ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        $summary = ['الفترة' => $from->format('Y/m/d') . ' → ' . $to->format('Y/m/d')];
        foreach ($r['cards'] as $label => $value) {
            $summary[$label] = $value;
        }

        return SheetWriter::download("تقرير-{$key}-" . now()->format('Y-m-d') . '.xlsx', [[
            'title' => mb_substr($r['title'], 0, 28), 'summary' => $summary, 'headers' => $r['columns'],
            'rows' => $r['rows'], 'money' => $r['money'],
        ]]);
    }

    public function pdf(Request $request, string $key)
    {
        [$from, $to, $emp] = $this->params($request);
        $r = $this->reports->build($key, $from, $to, $emp);
        Activity::log('export', null, 'PDF تقرير: ' . $r['title'], ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
        $pdf = \App\Services\PdfService::render('pdf.report', ['r' => $r, 'from' => $from, 'to' => $to, 'employee' => $emp ? User::find($emp)?->name : null],
            $r['title'], count($r['columns']) > 6, 'تقرير: ' . $r['title']);

        return \App\Services\PdfService::response($pdf, 'تقرير-' . $key . '-' . now()->format('Y-m-d') . '.pdf');
    }

    private function params(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'employee' => ['nullable', 'integer'],
        ]);
        $from = ! empty($data['from']) ? Carbon::parse($data['from']) : now()->startOfMonth();
        $to = ! empty($data['to']) ? Carbon::parse($data['to']) : now();

        $emp = ! empty($data['employee']) ? (int) $data['employee'] : null;
        if (! $request->user()->isAdmin()) {
            $emp = $request->user()->id;   // staff only ever see their own figures
        }

        return [$from, $to, $emp];
    }
}
