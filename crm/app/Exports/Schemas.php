<?php

namespace App\Exports;

/**
 * Fixed Excel layouts. The same headers are used for the downloadable template, the data export
 * and the importer, so a file can be downloaded, filled in and uploaded again without remapping.
 */
class Schemas
{
    public const TYPES = [
        'customers' => ['title' => 'العملاء والصفقات', 'sheet' => 'العملاء', 'icon' => 'fa-users'],
        'followups' => ['title' => 'المتابعات', 'sheet' => 'المتابعات', 'icon' => 'fa-calendar-check'],
        'payments' => ['title' => 'دفعات الأقساط', 'sheet' => 'الدفعات', 'icon' => 'fa-money-bill-wave'],
    ];

    /**
     * key => [header, synonyms[], width, list|null, required]
     * list: lookup type | 'statuses' | 'pay_methods' | 'priorities' | 'users' | null
     */
    public static function columns(string $type): array
    {
        return match ($type) {
            'customers' => [
                'code' => ['كود العميل', ['كود', 'code', 'customer code', 'رقم العميل'], 14, null, false],
                'name' => ['الاسم *', ['الاسم', 'اسم العميل', 'الاسم الكامل', 'اسم', 'name'], 28, null, true],
                'nat_id' => ['الرقم القومي', ['القومي', 'رقم قومي', 'national id', 'nat id', 'natid'], 18, null, false],
                'job' => ['المهنة', ['وظيفة', 'الوظيفة', 'job'], 16, null, false],
                'phone' => ['الهاتف *', ['الهاتف', 'رقم الهاتف', 'الموبايل', 'موبايل', 'رقم الموبايل', 'تليفون', 'phone', 'mobile', 'هاتف'], 16, null, true],
                'alt_phone' => ['هاتف بديل', ['بديل', 'رقم بديل', 'هاتف اخر', 'alt phone', 'altphone'], 16, null, false],
                'whatsapp' => ['واتساب', ['واتس', 'واتس اب', 'whatsapp'], 16, null, false],
                'governorate' => ['المحافظة', ['محافظة', 'gov', 'governorate'], 16, 'governorate', false],
                'district' => ['المركز / الحي', ['المركز', 'الحي', 'المنطقة', 'district'], 18, null, false],
                'address' => ['العنوان', ['عنوان', 'address'], 28, null, false],
                'channel' => ['القناة', ['قناة', 'مصدر العميل', 'المصدر', 'channel'], 16, 'channel', false],
                'interest' => ['المركبة المطلوبة', ['المركبة', 'السيارة', 'الموديل المطلوب', 'اهتمام', 'interest'], 20, 'vehicle', false],
                'status' => ['حالة العميل', ['الحالة', 'حالة البيع', 'status'], 14, 'statuses', false],
                'assigned_username' => ['اسم مستخدم الموظف', ['الموظف', 'الموظف المسؤول', 'اسم المستخدم', 'employee', 'username'], 20, 'users', false],
                'age' => ['العمر', ['عمر العميل', 'age'], 8, null, false],
                'seriousness' => ['جدية العميل', ['الجدية', 'seriousness'], 16, 'seriousness', false],
                'previous_vehicle' => ['المركبة السابقة', ['السابق', 'previous'], 18, null, false],
                'branch' => ['الفرع', ['فرع', 'branch'], 14, 'branch', false],
                'loss_reason' => ['سبب عدم إتمام البيع', ['سبب عدم الشراء', 'سبب الخسارة', 'loss reason'], 24, null, false],
                'notes' => ['ملاحظات', ['ملاحظة', 'notes'], 28, null, false],
                'vehicle' => ['مركبة الصفقة', ['المركبة المباعة', 'vehicle'], 20, 'vehicle', false],
                'pay_method' => ['طريقة الدفع', ['الدفع', 'نوع الدفع', 'pay method'], 14, 'pay_methods', false],
                'finance_entity' => ['جهة التقسيط', ['الجهة', 'جهة التمويل', 'finance'], 18, 'finance_entity', false],
                'total_price' => ['السعر الإجمالي', ['الإجمالي', 'الاجمالي', 'السعر', 'اجمالي السعر', 'total'], 16, null, false],
                'down_payment' => ['المقدم', ['مقدم', 'down'], 14, null, false],
                'months' => ['عدد الأشهر', ['عدد الاشهر', 'المدة', 'مدة التقسيط', 'عدد الاقساط', 'months'], 12, null, false],
                'interest_rate' => ['نسبة الفائدة السنوية %', ['الفائدة', 'نسبة الفائدة', 'interest rate', 'rate'], 16, null, false],
                'monthly_installment' => ['القسط الشهري', ['القسط', 'قسط شهري', 'monthly'], 16, null, false],
                'first_due_date' => ['تاريخ أول قسط', ['اول قسط', 'أول قسط', 'first due'], 16, null, false],
                'delivery_date' => ['تاريخ التسليم', ['التسليم', 'delivery'], 16, null, false],
                'chassis' => ['الشاسيه', ['شاسيه', 'رقم الشاسيه', 'chassis'], 20, null, false],
                'motor' => ['الموتور', ['رقم الموتور', 'motor'], 20, null, false],
                'model' => ['الموديل', ['سنة الصنع', 'model'], 12, null, false],
                'color' => ['لون المركبة', ['اللون', 'color'], 14, null, false],
                'sale_date' => ['تاريخ البيع', ['تاريخ الشراء', 'sale date'], 16, null, false],
                'dealer' => ['التاجر', ['الشاسيه تابع التاجر', 'dealer'], 18, null, false],
                'mobaya_no' => ['رقم المبايعة', ['مبايعة', 'mobaya'], 14, null, false],
            ],
            'followups' => [
                'customer' => ['كود أو هاتف العميل *', ['كود العميل', 'الكود', 'الهاتف', 'هاتف العميل', 'customer'], 22, null, true],
                'reason' => ['سبب المتابعة *', ['السبب', 'reason'], 22, 'followup_reason', true],
                'due_date' => ['تاريخ المتابعة *', ['التاريخ', 'تاريخ', 'date', 'due date'], 16, null, true],
                'due_time' => ['الوقت', ['وقت', 'time'], 10, null, false],
                'priority' => ['الأولوية', ['اولوية', 'priority'], 12, 'priorities', false],
                'assigned_username' => ['اسم مستخدم الموظف', ['الموظف', 'employee', 'username'], 20, 'users', false],
                'notes' => ['ملاحظات', ['ملاحظة', 'notes'], 30, null, false],
            ],
            'payments' => [
                'customer' => ['كود أو هاتف العميل *', ['كود العميل', 'الكود', 'الهاتف', 'customer'], 22, null, true],
                'chassis' => ['الشاسيه (اختياري لتحديد الصفقة)', ['الشاسيه', 'شاسيه', 'chassis'], 22, null, false],
                'amount' => ['المبلغ *', ['مبلغ', 'قيمة الدفعة', 'المدفوع', 'amount'], 14, null, true],
                'paid_on' => ['تاريخ الدفع *', ['التاريخ', 'تاريخ', 'paid on', 'date'], 16, null, true],
                'method' => ['طريقة السداد', ['السداد', 'method'], 16, 'payment_methods', false],
                'note' => ['ملاحظة', ['ملاحظات', 'note'], 26, null, false],
            ],
        };
    }

    public static function normalizeHeader(string $h): string
    {
        $h = str_replace(['*', '(', ')', '/', '_', '-', '.', '،', ':'], ' ', $h);
        $h = strtr($h, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $h)));
    }

    /** Map spreadsheet header text → schema key. */
    public static function matchHeader(string $type, string $header): ?string
    {
        $n = self::normalizeHeader($header);
        if ($n === '') {
            return null;
        }
        $cols = self::columns($type);
        foreach ($cols as $key => [$title, $syn]) {
            if (self::normalizeHeader($title) === $n) {
                return $key;
            }
        }
        foreach ($cols as $key => [$title, $syn]) {
            foreach ($syn as $s) {
                if (self::normalizeHeader($s) === $n) {
                    return $key;
                }
            }
        }

        return null;
    }
}
