<?php

namespace App\Http\Controllers;

use App\Exports\SheetWriter;
use App\Support\Activity;
use App\Support\InstallmentCalculator;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    public function index()
    {
        return view('calculator.index', [
            'products' => \App\Models\Product::where('is_active', true)->orderBy('sort')->get(['id', 'name', 'price', 'warranty', 'requirements']),
            'plans' => \App\Support\InstallmentCalculator::plans(),
        ]);
    }

    public function calculate(Request $request)
    {
        return response()->json(InstallmentCalculator::calculate($this->inputs($request)));
    }

    /** Download the quote schedule as Excel (contains no customer data). */
    public function export(Request $request)
    {
        $r = InstallmentCalculator::calculate($this->inputs($request));
        Activity::log('export', null, 'تصدير جدول أقساط (حاسبة)');

        $rows = array_map(fn ($s) => [$s['number'], $s['due_date'], $s['amount'], $s['principal'], $s['interest'], $s['balance']], $r['schedule']);

        return SheetWriter::download('جدول-الأقساط.xlsx', [[
            'title' => 'جدول الأقساط',
            'summary' => [
                'سعر المركبة' => $r['price'], 'المقدم' => $r['down'], 'المبلغ الممول' => $r['financed'],
                'عدد الأشهر' => $r['months'], 'نسبة الفائدة السنوية %' => $r['rate'],
                'القسط الشهري' => $r['monthly'], 'إجمالي الفوائد' => $r['interest_total'], 'إجمالي الأقساط' => $r['total_to_pay'],
            ],
            'headers' => ['القسط', 'تاريخ الاستحقاق', 'قيمة القسط', 'الأصل', 'الفائدة', 'الرصيد المتبقي'],
            'rows' => $rows,
            'money' => [3, 4, 5, 6],
        ]]);
    }

    private function inputs(Request $request): array
    {
        $d = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'down' => ['nullable', 'numeric', 'min:0'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'type' => ['nullable', 'in:flat,reducing,table'],
            'plan' => ['nullable', 'string', 'max:80'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'fees_type' => ['nullable', 'in:fixed,percent'],
            'fees_financed' => ['nullable', 'boolean'],
            'first_due' => ['nullable', 'date'],
        ]);
        $d['fees_financed'] = $request->boolean('fees_financed');

        return $d;
    }
}
