<?php

namespace App\Http\Controllers;

use App\Services\PdfService;
use App\Services\SummaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SummaryController extends Controller
{
    public function index(Request $request)
    {
        return view('summary.index', $this->data($request));
    }

    public function pdf(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'تصدير الملخص متاح للمدير فقط.');
        $s = $this->data($request)['s'];
        $pdf = PdfService::render('pdf.summary', ['s' => $s], 'ملخص ' . SummaryService::PERIODS[$s['period']], false, 'ملخص ' . SummaryService::PERIODS[$s['period']] . ' — ' . $s['label']);

        return PdfService::response($pdf, 'ملخص-' . $s['period'] . '-' . $s['from']->format('Y-m-d') . '.pdf');
    }

    private function data(Request $request): array
    {
        $request->validate(['period' => ['nullable', 'in:day,week,month,year'], 'date' => ['nullable', 'date']]);
        $anchor = $request->filled('date') ? Carbon::parse($request->query('date')) : now();
        $s = SummaryService::build($request->query('period', 'day'), $anchor, $request->user());

        return ['s' => $s, 'anchor' => $anchor, 'periods' => SummaryService::PERIODS];
    }
}
