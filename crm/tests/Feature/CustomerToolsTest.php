<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RoleProfile;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->first();
        $this->admin->forceFill(['must_change_password' => false])->save();
        $this->agent = User::factory()->create(['username' => 'ag5', 'role' => 'agent', 'password' => 'Agent12345', 'must_change_password' => false]);
        Permissions::flush();
    }

    public function test_finance_copy_text_deducts_the_first_installment_from_the_months(): void
    {
        $r = $this->actingAs($this->admin)->postJson('/calculator/calculate', ['price' => 83000, 'down' => 23000, 'months' => 18, 'type' => 'table', 'product' => 'بوكسر 150'])
            ->assertOk()->json();

        $text = $r['finance_text'];
        $this->assertStringStartsWith('*تفاصيل التقسيط*', $text);
        $this->assertStringContainsString('*المنتج:* بوكسر 150', $text);
        $this->assertStringContainsString('*سعر الكاش:* 83,000 جنيه', $text);
        $this->assertStringContainsString('*نظام التقسيط (18 شهر)*', $text);
        $this->assertStringContainsString('(17 شهر)', $text);
        $first = $r['down'] + $r['monthly'] + $r['fees'];
        $this->assertStringContainsString(number_format($first) . ' جنيه', $text);
        $this->assertStringContainsString('الإجمالي الكلي: ' . number_format($first + $r['monthly'] * 17) . ' جنيه', $text);

        // the bank-style systems have no financing-entity text
        $flat = $this->postJson('/calculator/calculate', ['price' => 83000, 'down' => 23000, 'months' => 18, 'type' => 'flat', 'rate' => 10])->json();
        $this->assertArrayNotHasKey('finance_text', $flat);
        $this->get('/calculator')->assertOk()->assertSee('نسخ لجهة التقسيط');
    }

    public function test_employee_lists_own_customers_but_can_open_any_customer_by_search(): void
    {
        $others = Customer::create(['name' => 'عميل مسجل باسم زميل', 'phone' => '01055556666', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->actingAs($this->agent);

        // the list and the dashboard count only the employee's own customers
        $this->assertFalse(Permissions::allows($this->agent, 'customers_all'));
        $this->get('/customers')->assertDontSee('عميل مسجل باسم زميل');
        $this->get('/dashboard')->assertOk();

        // searching the number goes straight to the file, which opens read-only
        $this->get('/customers?q=01055556666')->assertRedirect(route('customers.show', $others));
        $this->get('/customers?q=' . urlencode('مسجل باسم'))->assertSee('عميل مسجل باسم زميل');
        $this->getJson('/customers/lookup?q=01055556666')->assertJsonFragment(['name' => 'عميل مسجل باسم زميل']);
        $this->get("/customers/{$others->id}")->assertOk()->assertSee('طلب نقل')->assertDontSee(route('customers.edit', $others))->assertDontSee(route('deals.create', $others));
        $this->put("/customers/{$others->id}", ['name' => 'تغيير', 'phone' => '01055556666', 'status' => 'مفتوحة'])->assertForbidden();
        $this->post("/customers/{$others->id}/vehicles", ['vehicle' => 'x'])->assertForbidden();
        $this->get("/customers/{$others->id}/deals/create")->assertForbidden();

        // ... but a follow-up and a transfer request are allowed
        $this->post('/followups', ['mode' => 'now', 'customer_id' => $others->id, 'reason' => 'تذكير', 'outcome' => 'اتصل يسأل عن السعر'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('followups', ['customer_id' => $others->id, 'created_by' => $this->agent->id]);
        $this->post("/customers/{$others->id}/reassignment-requests", ['reason' => 'العميل اتصل بيا'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reassignment_requests', ['customer_id' => $others->id, 'requested_by' => $this->agent->id, 'status' => 'pending']);

        // the admin can let an employee list or edit colleagues' customers
        $perms = Permissions::effectiveFor($this->agent);
        $perms['customers_all'] = ['view', 'edit'];
        $this->agent->forceFill(['permissions_override' => $perms])->save();
        Permissions::flush();
        $this->actingAs($this->agent->fresh())->get('/customers')->assertSee('عميل مسجل باسم زميل');
        $this->get("/customers/{$others->id}/edit")->assertOk();

        $this->actingAs($this->admin)->get("/users/{$this->agent->id}/edit")->assertOk()->assertSee('عملاء الزملاء');
    }

    public function test_migration_takes_back_the_see_all_grant_and_fills_the_vehicle_list(): void
    {
        $role = RoleProfile::create(['name' => 'دور قديم', 'permissions' => ['customers' => ['view'], 'customers_all' => ['view']]]);
        $this->agent->forceFill(['permissions_override' => ['customers' => ['view'], 'customers_all' => ['view']]])->save();
        $product = Product::withoutEvents(fn () => Product::create(['name' => 'منتج مستورد بدون أحداث', 'price' => 50000, 'is_active' => true]));
        $this->assertNotContains($product->name, \App\Models\Lookup::list('vehicle'));

        (require database_path('migrations/2026_10_14_100000_list_own_customers_and_sync_vehicle_products.php'))->up();

        $this->assertArrayNotHasKey('customers_all', $role->fresh()->permissions);
        $this->assertSame(['customers' => ['view']], $this->agent->fresh()->permissions_override);
        $this->assertContains($product->name, \App\Models\Lookup::list('vehicle'));
    }

    public function test_customer_file_shows_vehicle_prices_and_the_quick_calculator(): void
    {
        $product = Product::where('is_active', true)->first() ?? Product::create(['name' => 'بوكسر تجربة', 'price' => 70000, 'is_active' => true]);
        $c = Customer::create(['name' => 'عميل السعر', 'phone' => '01066667777', 'status' => 'مفتوحة', 'interest' => $product->name, 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $c->vehicles()->create(['vehicle' => $product->name]);

        $this->actingAs($this->admin);
        $this->get('/customers/create')->assertOk()->assertSee('m-quick-calc', false)->assertSee('data-price-hint', false)->assertSee('احسب القسط')
            ->assertSee('المنتجات بالأسعار')->assertSee($product->name . ' — ' . number_format($product->price) . ' ج.م');
        $this->get("/customers/{$c->id}")->assertOk()->assertSee('m-quick-calc', false)->assertSee(number_format($product->price))->assertSee('نسخ لجهة التقسيط');
    }
}
