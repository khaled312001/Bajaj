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
    private const VEHICLE = [
        ['product', 'النوع / الماركة', 'text'], ['model', 'الموديل', 'text'], ['color', 'اللون', 'text'],
        ['chassis', 'رقم الشاسيه', 'text'], ['motor', 'رقم الموتور', 'text'],
    ];

    /** @return array<string,array{title:string,prefix:string,agent:bool,icon:string,group:string,desc:string,fields:array}> */
    public static function types(): array
    {
        $person = self::PERSON;
        $vehicle = self::VEHICLE;

        return [
            'quote' => ['title' => 'عرض سعر', 'prefix' => 'Q', 'agent' => true, 'icon' => 'fa-file-invoice-dollar', 'group' => 'مبيعات', 'desc' => 'عرض سعر نقدي للعميل بالمنتج والسعر وبيانات السداد.',
                'fields' => [['customer_name', 'اسم العميل', 'text'], ['product', 'المنتج', 'text'], ['price', 'السعر (ج.م)', 'money'], ['validity', 'مدة سريان العرض', 'text', 'ثلاثة أيام من تاريخه'], ['notes', 'ملاحظات', 'textarea']]],
            'statement' => ['title' => 'كشف أقساط العميل', 'prefix' => 'S', 'agent' => true, 'icon' => 'fa-table-list', 'group' => 'مبيعات', 'desc' => 'جدول الأقساط والمسدد والمتبقي (يتطلب اختيار صفقة).',
                'fields' => [['customer_name', 'اسم العميل', 'text'], ['notes', 'ملاحظات', 'textarea']]],
            'invoice' => ['title' => 'فاتورة بيع', 'prefix' => 'I', 'agent' => false, 'icon' => 'fa-receipt', 'group' => 'مبيعات', 'desc' => 'فاتورة بيع مركبة بالمدفوع والمتبقي.',
                'fields' => array_merge($person, $vehicle, [['price', 'الإجمالي (ج.م)', 'money'], ['paid', 'المدفوع (ج.م)', 'money'], ['pay_method', 'طريقة الدفع', 'text'], ['notes', 'ملاحظات', 'textarea']])],
            'receipt' => ['title' => 'إيصال سداد', 'prefix' => 'R', 'agent' => false, 'icon' => 'fa-money-check-dollar', 'group' => 'مبيعات', 'desc' => 'إيصال استلام مبلغ (قسط / مقدم / دفعة).',
                'fields' => [['customer_name', 'استلمنا من السيد', 'text'], ['amount', 'المبلغ (ج.م)', 'money'], ['reason', 'وذلك عن', 'text', 'قسط'], ['pay_method', 'طريقة الدفع', 'text', 'نقدي'], ['received_by', 'المستلم', 'text']]],
            'finance_offer' => ['title' => 'عرض سعر لجهة التمويل', 'prefix' => 'F', 'agent' => false, 'icon' => 'fa-building-columns', 'group' => 'جهات التمويل', 'desc' => 'عرض السعر المقدم لشركة التمويل (ريفي وغيرها).',
                'fields' => array_merge([['entity', 'جهة التمويل', 'text', 'ريفي']], [['customer_name', 'اسم العميل', 'text']], $vehicle, [['price', 'السعر (ج.م)', 'money'], ['paid', 'تم دفع (ج.م)', 'money']])],
            'finance_receipt' => ['title' => 'إقرار استلام منتج (جهة تمويل)', 'prefix' => 'FR', 'agent' => false, 'icon' => 'fa-file-signature', 'group' => 'جهات التمويل', 'desc' => 'إقرار التاجر بتسليم المنتج للعميل بموجب التمويل.',
                'fields' => [['entity', 'جهة التمويل', 'text', 'ريفي لتمويل المشروعات متناهي الصغر'], ['customer_name', 'اسم العميل', 'text'], ['nat_id', 'الرقم القومي', 'text'], ['product', 'المنتج / السلعة', 'text'], ['quantity', 'الكمية', 'text', '1'], ['price', 'القيمة (ج.م)', 'money'], ['activity', 'نشاط التاجر', 'text', 'بيع وسائل نقل خفيف']]],
            'mobaya' => ['title' => 'مبايعة', 'prefix' => 'M', 'agent' => false, 'icon' => 'fa-stamp', 'group' => 'المرور', 'desc' => 'مبايعة لإدارة المرور لترخيص المركبة (مع خيار حظر البيع).',
                'fields' => array_merge([['traffic_dept', 'إدارة مرور', 'text', 'قنا']], [['product', 'الماركة', 'text'], ['color', 'اللون', 'text'], ['chassis', 'شاسيه رقم', 'text'], ['motor', 'ماتور رقم', 'text'], ['model', 'الموديل', 'text'], ['cylinders', 'عدد السلندرات', 'text', '1']], [['customer_name', 'إلى السيد', 'text'], ['address', 'العنوان', 'text'], ['nat_id', 'الرقم القومي', 'text'], ['retain_title', 'مع الاحتفاظ بحق الملكية (حظر بيع)', 'check']])],
            'renewal' => ['title' => 'خطاب تجديد', 'prefix' => 'RN', 'agent' => false, 'icon' => 'fa-rotate', 'group' => 'المرور', 'desc' => 'خطاب لإدارة المرور لتجديد ترخيص المركبة لمدة سنة.',
                'fields' => array_merge([['traffic_dept', 'إدارة مرور', 'text', 'قنا']], [['product', 'الماركة', 'text'], ['color', 'اللون', 'text'], ['chassis', 'شاسيه رقم', 'text'], ['motor', 'ماتور رقم', 'text'], ['model', 'الموديل', 'text'], ['cylinders', 'عدد السلندرات', 'text', '1']], $person)],
            'clearance' => ['title' => 'مخالصة (رفع حظر البيع)', 'prefix' => 'C', 'agent' => false, 'icon' => 'fa-circle-check', 'group' => 'المرور', 'desc' => 'مخالصة بسداد كامل الأقساط ورفع حظر البيع.',
                'fields' => array_merge([['traffic_dept', 'إدارة مرور', 'text', 'قنا']], [['product', 'الماركة', 'text'], ['color', 'اللون', 'text'], ['chassis', 'شاسيه رقم', 'text'], ['motor', 'ماتور رقم', 'text'], ['model', 'الموديل', 'text'], ['cylinders', 'عدد السلندرات', 'text', '1']], $person)],
            'docs_receipt' => ['title' => 'إقرار استلام أوراق المركبة', 'prefix' => 'D', 'agent' => false, 'icon' => 'fa-folder-open', 'group' => 'إقرارات', 'desc' => 'إقرار العميل باستلام جميع أوراق المركبة (مخالصة نهائية).',
                'fields' => array_merge($person, [['code', 'كود العميل', 'text']], $vehicle, [['mobaya_no', 'مبايعة رقم', 'text'], ['purchase_date', 'تاريخ الشراء', 'date'], ['delivered_by', 'تم التسليم بمعرفة', 'text'], ['phone', 'الموبايل', 'text']])],
            'vehicle_receipt' => ['title' => 'إقرار استلام مركبة', 'prefix' => 'V', 'agent' => false, 'icon' => 'fa-motorcycle', 'group' => 'إقرارات', 'desc' => 'إقرار العميل باستلام المركبة وملحقاتها.',
                'fields' => array_merge($person, $vehicle, [['purchase_date', 'تاريخ الشراء', 'date'], ['delivered_by', 'تم التسليم بمعرفة', 'text']])],
            'broker_receipt' => ['title' => 'إقرار استلام سمسار (أمانة)', 'prefix' => 'B', 'agent' => false, 'icon' => 'fa-user-shield', 'group' => 'إقرارات', 'desc' => 'إقرار شخص باستلام مركبة لتوصيلها للعميل على سبيل الأمانة.',
                'fields' => array_merge([['broker_name', 'اسم المستلم (السمسار)', 'text'], ['broker_nat', 'رقمه القومي', 'text'], ['broker_address', 'عنوانه', 'text']], $vehicle, [['purchase_date', 'تاريخ الشراء', 'date']], [['customer_name', 'لتوصيلها للسيد', 'text'], ['nat_id', 'الرقم القومي للعميل', 'text'], ['address', 'عنوان العميل', 'text']])],
            'bulk_receipt' => ['title' => 'إقرار استلام كمية', 'prefix' => 'BK', 'agent' => false, 'icon' => 'fa-boxes-stacked', 'group' => 'إقرارات', 'desc' => 'إقرار استلام أكثر من مركبة (سطر لكل مركبة).',
                'fields' => array_merge($person, [['items', 'المركبات (سطر لكل مركبة: النوع | الموديل | اللون | الشاسيه | الموتور)', 'textarea']])],
            'cash_received' => ['title' => 'إقرار استلام مبلغ نقدي', 'prefix' => 'CR', 'agent' => false, 'icon' => 'fa-hand-holding-dollar', 'group' => 'إقرارات', 'desc' => 'إقرار العميل باستلام مبلغ (مثل الكاش باك) وعدم الرجوع على الشركة.',
                'fields' => array_merge($person, [['amount', 'المبلغ (ج.م)', 'money'], ['reason', 'وهو عبارة عن', 'text', 'قيمة كامل المبلغ سعر الكاش باك نظير ترخيص السيارة'], ['witness', 'اسم الشاهد (أمام الموظف)', 'text']])],
            'waiver_amount' => ['title' => 'إقرار تنازل عن مبلغ', 'prefix' => 'W', 'agent' => false, 'icon' => 'fa-file-circle-xmark', 'group' => 'إقرارات', 'desc' => 'إقرار بأن المبلغ المحول للشركة خاص بشراء مركبة باسم شخص آخر.',
                'fields' => array_merge($person, [['amount', 'المبلغ (ج.م)', 'money'], ['bank', 'محوّل إلى', 'text', 'بنك الاسكندرية'], ['product', 'خاص بشراء', 'text'], ['chassis', 'شاسيه', 'text'], ['motor', 'موتور', 'text'], ['purchase_date', 'تاريخ الشراء', 'date'], ['to_name', 'باسم', 'text'], ['to_nat', 'رقمه القومي', 'text'], ['to_address', 'عنوانه', 'text']])],
            'transfer_rights' => ['title' => 'إقرار تنازل وتحويل حق ملكية', 'prefix' => 'T', 'agent' => false, 'icon' => 'fa-people-arrows', 'group' => 'إقرارات', 'desc' => 'تنازل عميل عن حقوقه في مركبة لصالح عميل آخر (نقل ملكية).',
                'fields' => [['customer_name', 'المتنازل', 'text'], ['nat_id', 'رقمه القومي', 'text'], ['address', 'عنوانه', 'text'], ['to_name', 'المتنازل إليه', 'text'], ['to_nat', 'رقمه القومي', 'text'], ['to_address', 'عنوانه', 'text'], ['product', 'نوع المركبة', 'text'], ['price', 'إجمالي القيمة (ج.م)', 'money'], ['chassis', 'رقم الشاسيه', 'text'], ['motor', 'رقم الموتور', 'text'], ['payment_note', 'كيفية السداد', 'text', 'جزء محول عن طريق إنستاباي والجزء الآخر عن طريق شركة التقسيط'], ['phone', 'رقم موبايل المتنازل', 'text']]],
            'factory_auth' => ['title' => 'تفويض استلام من المصنع', 'prefix' => 'A', 'agent' => false, 'icon' => 'fa-industry', 'group' => 'إقرارات', 'desc' => 'تفويض شخص لاستلام البضاعة (تروسيكلات) من الشركة الموردة.',
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
                'customer_name' => $customer->name, 'nat_id' => $customer->nat_id, 'address' => trim(implode(' - ', array_filter([$customer->address, $customer->district, $customer->governorate]))),
                'phone' => $customer->phone, 'code' => $customer->code,
            ];
        }
        if ($deal) {
            $p += [
                'product' => $deal->vehicle, 'model' => $deal->model, 'color' => $deal->color, 'chassis' => $deal->chassis, 'motor' => $deal->motor,
                'price' => $deal->total_price ?: null, 'paid' => (float) $deal->down_payment + (float) $deal->paid_total ?: null,
                'purchase_date' => $deal->sale_date?->toDateString(), 'mobaya_no' => $deal->mobaya_no, 'entity' => $deal->finance_entity,
                'pay_method' => $deal->pay_method,
            ];
        }
        if ($payment) {
            $p += ['amount' => $payment->amount, 'reason' => 'سداد ' . ($payment->note ?: 'قسط'), 'pay_method' => $payment->method ?: 'نقدي'];
        }

        return array_filter($p, fn ($v) => $v !== null && $v !== '');
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
