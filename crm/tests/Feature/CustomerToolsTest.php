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

    public function test_seeing_all_customers_is_a_permission_the_admin_controls(): void
    {
        $others = Customer::create(['name' => 'عميل مسجل باسم زميل', 'phone' => '01055556666', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);

        // default customer-service role: sees every customer
        $this->assertTrue(Permissions::allows($this->agent, 'customers_all'));
        $this->actingAs($this->agent)->get('/customers')->assertSee('عميل مسجل باسم زميل');
        $this->get("/customers/{$others->id}")->assertOk();

        // the admin unticks it for this employee -> own customers only
        $perms = Permissions::effectiveFor($this->agent);
        unset($perms['customers_all']);
        $this->agent->forceFill(['permissions_override' => $perms])->save();
        Permissions::flush();
        $this->actingAs($this->agent->fresh())->get('/customers')->assertDontSee('عميل مسجل باسم زميل');
        $this->get("/customers/{$others->id}")->assertNotFound();

        $this->actingAs($this->admin)->get("/users/{$this->agent->id}/edit")->assertOk()->assertSee('رؤية كل العملاء');
    }

    public function test_migration_grants_see_all_to_whoever_could_view_customers(): void
    {
        $role = RoleProfile::create(['name' => 'دور قديم', 'permissions' => ['customers' => ['view', 'create', 'edit']]]);
        $noView = RoleProfile::create(['name' => 'دور بدون عملاء', 'permissions' => ['followups' => ['view']]]);
        $this->agent->forceFill(['permissions_override' => ['customers' => ['view']]])->save();

        (require database_path('migrations/2026_10_13_100000_grant_customers_all_to_customer_viewers.php'))->up();

        $this->assertSame(['view'], $role->fresh()->permissions['customers_all']);
        $this->assertArrayNotHasKey('customers_all', $noView->fresh()->permissions);
        $this->assertSame(['view'], $this->agent->fresh()->permissions_override['customers_all']);
    }

    public function test_customer_file_shows_vehicle_prices_and_the_quick_calculator(): void
    {
        $product = Product::where('is_active', true)->first() ?? Product::create(['name' => 'بوكسر تجربة', 'price' => 70000, 'is_active' => true]);
        $c = Customer::create(['name' => 'عميل السعر', 'phone' => '01066667777', 'status' => 'مفتوحة', 'interest' => $product->name, 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $c->vehicles()->create(['vehicle' => $product->name]);

        $this->actingAs($this->admin);
        $this->get('/customers/create')->assertOk()->assertSee('m-quick-calc', false)->assertSee('data-price-hint', false)->assertSee('احسب القسط');
        $this->get("/customers/{$c->id}")->assertOk()->assertSee('m-quick-calc', false)->assertSee(number_format($product->price))->assertSee('نسخ لجهة التقسيط');
    }
}
