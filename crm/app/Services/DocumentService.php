<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\DocumentLog;
use App\Models\Payment;
use App\Models\User;
use App\Support\ArabicNumber;
use App\Support\Settings;

/** Catalogue of printable documents (from the company's Word/Excel/PDF forms) + prefill + serial numbering. */
class DocumentService
{
    private const PERSON = [
        ['customer_name', 'اسم العميل', 'text'], ['nat_id', 'الرقم القومي', 'text'], ['address', 'العنوان', 'text'],
    ];
    public const RECEIPT_METHODS = ['كاش', 'تحويل بنكي', 'إنستاباي', 'فودافون كاش / محفظة', 'شيك', 'فيزا / ماكينة'];
    private const VEHICLE = [
        ['product', 'النوع / الماركة', 'text'], ['model', 'الموديل', 'text'], ['color', 'اللون', 'text'],
        ['chassis', 'رقم الشاسيه', 'text'], ['motor', 'رقم الموتور', 'text'],
    ];

    /** @return array<string,array{title:string,prefix:string,agent:bool,icon:string,group:string,desc:string,fields:array}> */
    public static function types(): array
    {
        $person = self::PERSON;
        $vehicle = self::VEHICLE;
        // traffic letters: printed on the pre-printed company letterhead paper
        $traffic = [
            ['traffic_dept', 'إدارة مرور', 'text', 'قنا'], ['product', 'ماركة', 'text'], ['color', 'اللون', 'text'], ['chassis', 'شاسيه رقم', 'text'],
            ['motor', 'ماتور رقم', 'text'], ['model', 'موديل', 'text'], ['cylinders', 'عدد السلندرات', 'text', '1'],
            ['customer_name', 'الي السيد', 'text'], ['address', 'العنوان', 'text'], ['nat_id', 'الرقم القومي', 'text'], ['traffic_code', 'رقم اعتماد المرور', 'text', '214'],
        ];

        return [
            'quote' => ['title' => 'عرض سعر', 'prefix' => 'Q', 'agent' => true, 'icon' => 'fa-file-invoice-dollar', 'group' => 'مبيعات', 'desc' => 'عرض سعر نقدي للعميل بالمنتج والسعر وبيانات السداد.',
                'fields' => [['customer_name', 'اسم العميل', 'text'], ['product', 'المنتج', 'text'], ['price', 'السعر (ج.م)', 'money'], ['validity', 'مدة سريان العرض', 'text', 'ثلاثة أيام من تاريخه'], ['notes', 'ملاحظات', 'textarea']]],
            'statement' => ['title' => 'كشف أقساط العميل', 'prefix' => 'S', 'agent' => true, 'icon' => 'fa-table-list', 'group' => 'مبيعات', 'desc' => 'جدول الأقساط والمسدد والمتبقي (يتطلب اختيار صفقة).',
                'fields' => [['customer_name', 'اسم العميل', 'text'], ['notes', 'ملاحظات', 'textarea']]],
            'invoice' => ['title' => 'عقد بيع', 'prefix' => 'I', 'agent' => false, 'icon' => 'fa-receipt', 'group' => 'مبيعات', 'desc' => 'عقد بيع / إقرار استلام المركبة وملحقاتها (بدون مبالغ) بنفس نموذج الشركة.',
                'fields' => array_merge($person, $vehicle, [['purchase_date', 'تاريخ الشراء', 'date'], ['delivered_by', 'تم التسليم بمعرفة', 'text'], ['phone', 'الموبايل', 'text']])],
            'receipt' => ['title' => 'إيصال سداد', 'prefix' => 'R', 'agent' => false, 'icon' => 'fa-money-check-dollar', 'group' => 'مبيعات', 'desc' => 'إيصال استلام مبلغ (قسط / مقدم / دفعة).',
                'fields' => [['customer_name', 'استلمنا من السيد', 'text'], ['code', 'كود العميل', 'text'], ['nat_id', 'الرقم القومي', 'text'],
                    ['amount', 'المبلغ (ج.م)', 'money'], ['reason', 'وذلك قيمة', 'text', 'قسط'], ['paid_on', 'تاريخ السداد', 'date', now()->toDateString()],
                    ['pay_method', 'طريقة الدفع', 'select', 'كاش', self::RECEIPT_METHODS],
                    ['ref_no', 'رقم المرجع (تحويل بنكي / إنستاباي / محفظة)', 'text'], ['transfer_receipt', 'رقم إيصال التحويل', 'text'],
                    ['details', 'تفاصيل (اختياري — لو فاضية يفضل المربع فاضي للكتابة باليد)', 'textarea'], ['received_by', 'المستلم (عن الشركة)', 'text'],
                    ['remaining', 'الباقي على الصفقة بعد الدفعة (ج.م — اختياري)', 'money'], ['debt', 'إجمالي مديونية العميل كل الصفقات (ج.م — اختياري)', 'money']]],
            'finance_offer' => ['title' => 'عرض سعر', 'list_title' => 'عرض سعر لجهة التمويل', 'prefix' => 'F', 'agent' => false, 'icon' => 'fa-building-columns', 'group' => 'جهات التمويل', 'desc' => 'عرض السعر المقدم لشركة التمويل (ريفي وغيرها).',
                'fields' => array_merge([['entity', 'جهة التمويل', 'text', 'ريفي']], [['customer_name', 'اسم العميل', 'text']], $vehicle, [['price', 'السعر (ج.م)', 'money'], ['paid', 'تم دفع (ج.م)', 'money']])],
            'finance_receipt' => ['title' => 'إقرار استلام منتج (جهة تمويل)', 'prefix' => 'FR', 'agent' => false, 'icon' => 'fa-file-signature', 'group' => 'جهات التمويل', 'desc' => 'إقرار التاجر بتسليم المنتج للعميل بموجب التمويل.',
                'fields' => [['entity', 'جهة التمويل', 'text', 'ريفي لتمويل المشروعات متناهي الصغر'], ['customer_name', 'اسم العميل', 'text'], ['nat_id', 'الرقم القومي', 'text'], ['product', 'المنتج / السلعة', 'text'], ['quantity', 'الكمية', 'text', '1'], ['price', 'القيمة (ج.م)', 'money'], ['activity', 'نشاط التاجر', 'text', 'بيع وسائل نقل خفيف']]],
            'mobaya' => ['title' => 'مبايعة', 'prefix' => 'M', 'agent' => false, 'icon' => 'fa-stamp', 'group' => 'المرور', 'desc' => 'مبايعة لإدارة المرور لترخيص المركبة (مع خيار حظر البيع).',
                'fields' => array_merge([['mobaya_no', 'رقم المبايعة (يُطبع أعلى الورقة — اتركه فارغاً للكتابة باليد)', 'text']], $traffic, [['retain_title', 'مع الاحتفاظ بحق الملكية (حظر بيع)', 'check']])],
            'renewal' => ['title' => 'خطاب تجديد', 'prefix' => 'RN', 'agent' => false, 'icon' => 'fa-rotate', 'group' => 'المرور', 'desc' => 'خطاب لإدارة المرور لتجديد ترخيص المركبة لمدة سنة.',
                'fields' => array_merge([['doc_no', 'رقم الخطاب (يُطبع أعلى الورقة — اختياري)', 'text']], $traffic)],
            'clearance' => ['title' => 'مخالصة (رفع حظر البيع)', 'prefix' => 'C', 'agent' => false, 'icon' => 'fa-circle-check', 'group' => 'المرور', 'desc' => 'مخالصة بسداد كامل الأقساط ورفع حظر البيع.',
                'fields' => array_merge([['doc_no', 'رقم المخالصة (يُطبع أعلى الورقة — اختياري)', 'text']], $traffic)],
            'docs_receipt' => ['title' => 'إقرار استلام أوراق المركبة', 'prefix' => 'D', 'agent' => false, 'icon' => 'fa-folder-open', 'group' => 'إقرارات', 'desc' => 'إقرار العميل باستلام جميع أوراق المركبة (مخالصة نهائية).',
                'fields' => array_merge($person, [['code', 'كود العميل', 'text']], $vehicle, [['mobaya_no', 'مبايعة رقم', 'text'], ['purchase_date', 'تاريخ الشراء', 'date'], ['delivered_by', 'تم التسليم بمعرفة', 'text'], ['phone', 'الموبايل', 'text']])],
            'vehicle_receipt' => ['title' => 'إقرار استلام مركبة', 'prefix' => 'V', 'agent' => false, 'icon' => 'fa-motorcycle', 'group' => 'إقرارات', 'desc' => 'إقرار العميل باستلام المركبة وملحقاتها.',
                'fields' => array_merge($person, $vehicle, [['purchase_date', 'تاريخ الشراء', 'date'], ['delivered_by', 'تم التسليم بمعرفة', 'text'], ['phone', 'الموبايل', 'text']])],
            'broker_receipt' => ['title' => 'إقرار استلام سمسار (أمانة)', 'prefix' => 'B', 'agent' => false, 'icon' => 'fa-user-shield', 'group' => 'إقرارات', 'desc' => 'إقرار شخص باستلام مركبة لتوصيلها للعميل على سبيل الأمانة.',
                'fields' => array_merge([['broker_name', 'اسم المستلم (السمسار)', 'text'], ['broker_nat', 'رقمه القومي', 'text'], ['broker_address', 'عنوانه', 'text']], $vehicle, [['purchase_date', 'تاريخ الشراء', 'date']], [['customer_name', 'لتوصيلها للسيد', 'text'], ['nat_id', 'الرقم القومي للعميل', 'text'], ['address', 'عنوان العميل', 'text'], ['delivered_by', 'تم التسليم بمعرفة', 'text']])],
            // listed as "إقرار استلام كمية", printed as just "إقرار استلام"
            'bulk_receipt' => ['title' => 'إقرار استلام', 'list_title' => 'إقرار استلام كمية', 'prefix' => 'BK', 'agent' => false, 'icon' => 'fa-boxes-stacked', 'group' => 'إقرارات', 'desc' => 'إقرار استلام أكثر من مركبة (سطر لكل مركبة): تاجر استلمها بنفسه، أو شخص استلمها لتوصيلها لغيره على سبيل الأمانة — وفيه المدفوع والباقي والمديونية (إجمالي الصفقة بيفضل في ملف العميل بس).',
                'fields' => [['customer_name', 'اسم المستلم', 'text'], ['nat_id', 'الرقم القومي', 'text'], ['address', 'العنوان (المقيم)', 'text'],
                    ['items', 'المركبات (سطر لكل مركبة: النوع | الموديل | اللون | الشاسيه | الموتور)', 'textarea'],
                    ['to_name', 'لتوصيلها للسيد (سيبه فاضي لو المستلم هو المشتري نفسه — تاجر مثلاً)', 'text'], ['to_nat', 'رقمه القومي', 'text'], ['to_address', 'عنوانه (المقيم)', 'text'],
                    ['paid', 'المدفوع (ج.م)', 'money'], ['remaining', 'الباقي على الصفقة (ج.م)', 'money'],
                    ['debt', 'إجمالي مديونية المستلم كل الصفقات (ج.م)', 'money'],
                    ['delivered_by', 'تم التسليم بمعرفة', 'text'], ['phone', 'موبايل المستلم', 'text']]],
            'cash_received' => ['title' => 'إقرار استلام مبلغ نقدي', 'prefix' => 'CR', 'agent' => false, 'icon' => 'fa-hand-holding-dollar', 'group' => 'إقرارات', 'desc' => 'إقرار العميل باستلام مبلغ (مثل الكاش باك) وعدم الرجوع على الشركة.',
                'fields' => array_merge($person, [['amount', 'المبلغ (ج.م)', 'money'], ['reason', 'وهو عبارة عن', 'text', 'قيمة كامل المبلغ سعر الكاش باك نظير ترخيص السيارة'], ['witness', 'اسم الشاهد (أمام الموظف)', 'text']])],
            'waiver_amount' => ['title' => 'إقرار تنازل عن مبلغ', 'prefix' => 'W', 'agent' => false, 'icon' => 'fa-file-circle-xmark', 'group' => 'إقرارات', 'desc' => 'إقرار بأن المبلغ المحول للشركة خاص بشراء مركبة باسم شخص آخر.',
                'fields' => array_merge($person, [['amount', 'المبلغ (ج.م)', 'money'], ['bank', 'محوّل إلى', 'text', 'بنك الاسكندرية'], ['product', 'خاص بشراء', 'text'], ['chassis', 'شاسيه', 'text'], ['motor', 'موتور', 'text'], ['purchase_date', 'تاريخ الشراء', 'date'], ['to_name', 'باسم', 'text'], ['to_nat', 'رقمه القومي', 'text'], ['to_address', 'عنوانه', 'text'], ['phone', 'رقم الموبايل', 'text']])],
            'transfer_rights' => ['title' => 'إقرار تنازل وتحويل حق ملكية', 'prefix' => 'T', 'agent' => false, 'icon' => 'fa-people-arrows', 'group' => 'إقرارات', 'desc' => 'تنازل عميل عن حقوقه في مركبة لصالح عميل آخر (نقل ملكية).',
                'fields' => [['customer_name', 'المتنازل', 'text'], ['nat_id', 'رقمه القومي', 'text'], ['address', 'عنوانه', 'text'], ['to_name', 'المتنازل إليه', 'text'], ['to_nat', 'رقمه القومي', 'text'], ['to_address', 'عنوانه', 'text'], ['product', 'نوع المركبة', 'text'], ['price', 'إجمالي القيمة (ج.م)', 'money'], ['chassis', 'رقم الشاسيه', 'text'], ['motor', 'رقم الموتور', 'text'], ['payment_note', 'كيفية السداد', 'text', 'جزء محول عن طريق إنستاباي والجزء الآخر عن طريق شركة التقسيط'], ['phone', 'رقم موبايل المتنازل', 'text']]],
            'factory_auth' => ['title' => 'تفويض استلام', 'list_title' => 'تفويض استلام من المصنع', 'prefix' => 'A', 'agent' => false, 'icon' => 'fa-industry', 'group' => 'إقرارات', 'desc' => 'تفويض شخص لاستلام البضاعة (تروسيكلات) من الشركة الموردة.',
                'fields' => [['to_company', 'السادة / الشركة', 'text', 'الشركة الدولية للتجارة والتسويق والتوكيلات التجارية (ايتامكو)'], ['delegate_name', 'المفوَّض', 'text'], ['delegate_nat', 'رقمه القومي', 'text'], ['goods', 'البضاعة', 'text', 'تروسيكلات طرفنا لديكم'], ['valid_until', 'سارٍ حتى', 'date']]],
        ];
    }

    public static function type(string $key): ?array
    {
        return self::types()[$key] ?? null;
    }

    public static function allowed(User $user, string $key): bool
    {
        $t = self::type($key);
        if (! $t) {
            return false;
        }
        // Invoices: admin-only, regardless of a staff member's legal_documents permission.
        if ($key === 'invoice') {
            return $user->isAdmin();
        }

        return $user->allows($t['agent'] ? 'documents' : 'legal_documents', 'create');
    }

    /** Values proposed from a deal / customer / payment (the user can edit everything before issuing). */
    public static function prefill(?Deal $deal, ?Customer $customer, ?Payment $payment = null): array
    {
        $deal ??= $payment?->deal;
        $customer ??= $deal?->customer;
        $p = [];
        if ($customer) {
            $p += [
                'customer_name' => $customer->display_name, 'nat_id' => $customer->nat_id, 'address' => self::fullAddress($customer),
                'phone' => $customer->phone, 'code' => $customer->code,
                // what he still owes on all his deals
                'debt' => round((float) $customer->deals()->where('status', '!=', 'مفقودة')->sum('balance'), 2),
            ];
        }
        if ($deal) {
            $p += [
                'product' => $deal->vehicle ?: $deal->vehicleUnit?->type, 'model' => $deal->model ?: $deal->vehicleUnit?->model_year, 'color' => $deal->color ?: $deal->vehicleUnit?->color,
                'chassis' => $deal->chassis ?: $deal->vehicleUnit?->chassis, 'motor' => $deal->motor ?: $deal->vehicleUnit?->motor,
                'price' => $deal->total_price ?: null, 'paid' => (float) $deal->down_payment + (float) $deal->paid_total ?: null,
                'purchase_date' => $deal->sale_date?->toDateString(), 'mobaya_no' => $deal->mobaya_no, 'entity' => $deal->finance_entity,
                'pay_method' => $deal->pay_method, 'remaining' => round((float) $deal->balance, 2),
                'items' => self::bulkItems($deal),
            ];
        }
        if ($payment) {
            $p += ['amount' => $payment->amount, 'reason' => 'سداد ' . ($payment->note ?: 'قسط'), 'pay_method' => $payment->method ?: 'نقدي'];
        }

        return array_filter($p, fn ($v) => $v !== null && $v !== '');
    }

    /** "العنوان - المركز - المحافظة", without repeating what the address already says. */
    public static function fullAddress(Customer $customer): string
    {
        $address = trim((string) $customer->address);
        $parts = array_filter([$customer->district, $customer->governorate], fn ($p) => trim((string) $p) !== '' && ! str_contains($address, trim($p)));

        return trim(implode(' - ', array_filter([$address, ...$parts])));
    }

    /** "النوع | الموديل | اللون | الشاسيه | الموتور" per vehicle of the deal, from stock when it is there. */
    public static function bulkItems(Deal $deal): string
    {
        $units = $deal->vehicleUnits()->get();

        return implode("\n", array_map(function ($s) use ($deal, $units) {
            [$chassis, $motor] = $s;
            $u = Deal::matchUnit($units, $chassis, $motor);

            return implode(' | ', [$u?->type ?: $deal->vehicle, $u?->model_year ?: $deal->model, $u?->color ?: $deal->color, $u?->chassis ?: $chassis, $u?->motor ?: $motor]);
        }, $deal->serials()));
    }

    public static function nextSerial(string $type): int
    {
        return (int) DocumentLog::where('type', $type)->max('serial') + 1;
    }

    public static function docNo(string $type, int $serial, ?string $legacy = null): string
    {
        if ($legacy) {
            return $legacy;
        }
        $t = self::type($type);

        return ($t['prefix'] ?? 'D') . '-' . str_pad((string) $serial, 5, '0', STR_PAD_LEFT);
    }

    public static function words(mixed $amount): string
    {
        return ArabicNumber::egp($amount);
    }

    public static function companyLegal(): string
    {
        return (string) Settings::get('company_legal_name', 'جنوب الصعيد لوسائل النقل الخفيف');
    }
}
