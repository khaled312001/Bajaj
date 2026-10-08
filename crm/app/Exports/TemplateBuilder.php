<?php

namespace App\Exports;

use App\Models\Customer;
use App\Models\Lookup;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Builds the fixed-layout workbooks (template + data export) with dropdown lists. */
class TemplateBuilder
{
    public const PAYMENT_METHODS = ['نقدي', 'تحويل بنكي', 'شيك', 'فودافون كاش', 'فيزا / ماكينة'];
    public const VALIDATION_ROWS = 3000;

    public static function lists(): array
    {
        return [
            'governorate' => Lookup::list('governorate'),
            'channel' => Lookup::list('channel'),
            'vehicle' => Lookup::list('vehicle'),
            'finance_entity' => Lookup::list('finance_entity'),
            'followup_reason' => Lookup::list('followup_reason'),
            'statuses' => Customer::STATUSES,
            'pay_methods' => ['كاش', 'تقسيط'],
            'priorities' => ['عادية', 'عالية'],
            'payment_methods' => self::PAYMENT_METHODS,
            'users' => User::where('is_active', true)->orderBy('username')->pluck('username')->all(),
        ];
    }

    /** Empty, ready-to-fill template. */
    public static function template(string $type): Spreadsheet
    {
        return self::workbook($type, [], true);
    }

    /** Same layout filled with data (round-trip export). */
    public static function withData(string $type, iterable $rows): Spreadsheet
    {
        return self::workbook($type, $rows, false);
    }

    private static function workbook(string $type, iterable $rows, bool $withSample): Spreadsheet
    {
        $meta = Schemas::TYPES[$type];
        $cols = Schemas::columns($type);
        $keys = array_keys($cols);

        $book = new Spreadsheet();
        $data = $book->getActiveSheet();
        $data->setTitle($meta['sheet']);
        $data->setRightToLeft(true);

        // header row
        foreach ($keys as $i => $k) {
            $data->setCellValueExplicit([$i + 1, 1], $cols[$k][0], DataType::TYPE_STRING);
            $data->getColumnDimensionByColumn($i + 1)->setWidth($cols[$k][2]);
            $required = $cols[$k][4];
            $data->getStyle([$i + 1, 1])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($required ? 'B91C1C' : SheetWriter::NAVY);
        }
        $last = count($keys);
        $head = $data->getStyle([1, 1, $last, 1]);
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $data->getRowDimension(1)->setRowHeight(34);
        $data->freezePane('A2');

        // text format for phone / code / id columns so Excel does not drop leading zeros
        foreach ($keys as $i => $k) {
            if (in_array($k, ['phone', 'alt_phone', 'whatsapp', 'nat_id', 'code', 'customer', 'chassis', 'motor'], true)) {
                $data->getStyle([$i + 1, 2, $i + 1, self::VALIDATION_ROWS])->getNumberFormat()->setFormatCode('@');
            }
            if (in_array($k, ['first_due_date', 'delivery_date', 'due_date', 'paid_on'], true)) {
                $data->getStyle([$i + 1, 2, $i + 1, self::VALIDATION_ROWS])->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }
        }

        // lists sheet (hidden) + dropdown validations
        $listsSheet = new Worksheet($book, 'القوائم');
        $book->addSheet($listsSheet);
        $all = self::lists();
        $col = 1;
        foreach ($keys as $i => $k) {
            $list = $cols[$k][3];
            if (! $list || empty($all[$list])) {
                continue;
            }
            $items = $all[$list];
            $listsSheet->setCellValueExplicit([$col, 1], $cols[$k][0], DataType::TYPE_STRING);
            foreach ($items as $r => $item) {
                $listsSheet->setCellValueExplicit([$col, $r + 2], (string) $item, DataType::TYPE_STRING);
            }
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $range = "'القوائم'!\${$colLetter}\$2:\${$colLetter}$" . (count($items) + 1);

            $dv = new DataValidation();
            $dv->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_WARNING)
                ->setAllowBlank(true)->setShowDropDown(false)->setShowErrorMessage(true)
                ->setErrorTitle('قيمة غير موجودة')->setError('اختر قيمة من القائمة المنسدلة.')
                ->setFormula1($range);
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $data->setDataValidation("{$cell}2:{$cell}" . self::VALIDATION_ROWS, $dv);
            $col++;
        }
        $listsSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        // rows
        $r = 2;
        foreach ($rows as $row) {
            foreach ($keys as $i => $k) {
                $v = $row[$k] ?? null;
                if ($v === null || $v === '') {
                    continue;
                }
                if (is_int($v) || is_float($v)) {
                    $data->setCellValue([$i + 1, $r], $v);
                } else {
                    $data->setCellValueExplicit([$i + 1, $r], (string) $v, DataType::TYPE_STRING);
                }
            }
            $r++;
        }

        if ($withSample) {
            self::instructions($book, $type, $cols);
        }

        $book->setActiveSheetIndex(0);

        return $book;
    }

    private static function instructions(Spreadsheet $book, string $type, array $cols): void
    {
        $ws = new Worksheet($book, 'تعليمات');
        $book->addSheet($ws, 1);
        $ws->setRightToLeft(true);
        $lines = [
            ['تعليمات تعبئة الملف', ''],
            ['', ''],
            ['1', 'لا تغيّر أسماء الأعمدة ولا ترتيبها ولا تحذف أي عمود.'],
            ['2', 'الأعمدة ذات العنوان الأحمر (*) إلزامية.'],
            ['3', 'الأعمدة التي بها قائمة منسدلة اختر منها فقط، والقيم تُدار من صفحة الإعدادات.'],
            ['4', 'الهاتف والرقم القومي والكود تُكتب كنص (الأعمدة مضبوطة مسبقاً حتى لا يضيع الصفر الأول).'],
            ['5', 'التواريخ بصيغة 2026-12-31 أو 31/12/2026.'],
            ['6', 'ابدأ من الصف الثاني مباشرة ولا تترك صفوفاً فارغة بين البيانات.'],
        ];
        if ($type === 'customers') {
            $lines[] = ['7', 'إذا كان الهاتف أو الكود موجوداً بالفعل يتم تحديث العميل أو تخطيه حسب اختيارك عند الرفع.'];
            $lines[] = ['8', 'أعمدة الصفقة (من "مركبة الصفقة" إلى "الموديل") اختيارية: عند تعبئتها تُنشأ صفقة للعميل وجدول أقساط تلقائياً.'];
            $lines[] = ['9', 'لإضافة أكثر من صفقة لنفس العميل كرّر بيانات العميل (نفس الهاتف) في صف جديد مع بيانات الصفقة الأخرى.'];
        }
        if ($type === 'payments') {
            $lines[] = ['7', 'تُسجَّل الدفعة على آخر صفقة تقسيط للعميل لها رصيد متبقٍ، أو على الصفقة المطابقة للشاسيه إن كتبته.'];
        }
        foreach ($lines as $i => [$a, $b]) {
            $ws->setCellValueExplicit([1, $i + 1], (string) $a, DataType::TYPE_STRING);
            $ws->setCellValueExplicit([2, $i + 1], (string) $b, DataType::TYPE_STRING);
        }
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $ws->getColumnDimension('A')->setWidth(8);
        $ws->getColumnDimension('B')->setWidth(110);
    }
}
