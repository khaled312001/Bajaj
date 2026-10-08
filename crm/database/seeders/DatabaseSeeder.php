<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            'governorate' => ['قنا', 'الأقصر', 'أسوان', 'سوهاج', 'أسيوط', 'المنيا', 'البحر الأحمر', 'الوادي الجديد', 'القاهرة', 'الجيزة', 'الإسكندرية'],
            'vehicle' => ['Qute', 'Tricycle', 'Boxer 5G', 'Pulsar 180', 'Pulsar 150', 'Discovery 125', 'سكوتر', 'هوجن', 'جلاكسي'],
            'channel' => ['اتصال', 'زيارة', 'Xceed', 'واتساب', 'فيسبوك', 'توصية'],
            'finance_entity' => ['تساهيل', 'بنك اسكندرية', 'ريفي', 'أمان', 'مايلو'],
            'followup_reason' => ['استكمال أوراق', 'الرد على استفسار', 'العميل رفض', 'متردد', 'تذكير', 'تسليم أوراق', 'متابعة بعد البيع', 'تحصيل قسط'],
            'installment_stage' => ['قيد المراجعة', 'تم الموافقة', 'رد علينا', 'ملغي', 'استعلام سيئ', 'تم الصرف', 'تم التسليم'],
            'seriousness' => ['مهتم', 'جاد للشراء', 'خارج نطاق الفرع', 'غير مهتم', 'منتج اخر'],
            'branch' => ['قنا', 'قفط', 'قوص', 'نجع حمادي', 'دشنا', 'الأقصر'],
        ];
        foreach ($lists as $type => $names) {
            foreach ($names as $i => $name) {
                Lookup::firstOrCreate(['type' => $type, 'name' => $name], ['sort' => $i + 1]);
            }
        }

        $defaults = [
            'app_name' => 'بجاج قنا CRM', 'agent_scope' => 'own', 'agent_idle_minutes' => 45,
            'view_alert_threshold' => 40, 'view_block_threshold' => 90, 'default_interest_rate' => 18,
            'offer_title' => 'عرض تقسيط بجاج قنا', 'company_phone' => '01275455573 / 01093057341',
            'working_hours' => 'من السبت إلى الخميس من 10 صباحاً حتى 3 مساءً', 'company_address' => 'شارع المحطة - مركز قفط - قنا',
            'fees_percent' => 3, 'fees_fixed' => 500,
            'company_legal_name' => 'جنوب الصعيد لوسائل النقل الخفيف', 'commercial_register' => '51439', 'tax_card' => '371-154-529',
            'bank_account' => 'بنك الاسكندرية 412030833001', 'manager_name' => 'أنور ربيع سعيد',
        ];
        foreach ($defaults as $k => $v) {
            if (Settings::get($k) === null) {
                Settings::set($k, $v);
            }
        }

        $file = __DIR__ . '/data/products.json';
        if (is_file($file)) {
            foreach (json_decode(file_get_contents($file), true) ?: [] as $p) {
                Product::firstOrCreate(['name' => $p['name']], [
                    'price' => $p['price'], 'warranty' => $p['warranty'], 'requirements' => $p['requirements'],
                    'sort' => $p['sort'], 'is_active' => true,
                ]);
            }
        }

        foreach (\App\Support\Permissions::defaultProfiles() as $name => $def) {
            \App\Models\RoleProfile::firstOrCreate(['name' => $name], ['description' => $def['desc'], 'permissions' => $def['perms'], 'is_default' => $def['is_default']]);
        }

        $this->user('anwar', 'أنور', 'admin');
        $this->user('tarek', 'طارق', 'agent');
    }

    private function user(string $username, string $name, string $role): void
    {
        if (User::where('username', $username)->exists()) {
            return;
        }
        $password = env('SEED_' . strtoupper($username) . '_PASSWORD') ?: Str::password(12, symbols: false);
        User::create([
            'name' => $name, 'username' => $username, 'password' => $password,
            'role' => $role, 'is_active' => true, 'must_change_password' => true,
        ]);
        $this->command?->warn("{$role} created -> username: {$username} | password: {$password}  (change on first login)");
    }
}
