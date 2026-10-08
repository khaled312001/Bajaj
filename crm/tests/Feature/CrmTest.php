<?php

namespace Tests\Feature;

use App\Exports\Schemas;
use App\Exports\TemplateBuilder;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Followup;
use App\Models\Import;
use App\Models\User;
use App\Support\InstallmentCalculator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class CrmTest extends TestCase
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
        $this->agent = User::factory()->create(['username' => 'ag1', 'role' => 'agent', 'password' => 'Agent12345', 'must_change_password' => false]);
    }

    private function customer(array $o = []): Customer
    {
        return Customer::create($o + ['name' => 'عميل تجريبي', 'phone' => '0100000' . random_int(1000, 9999), 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
    }

    public function test_login_gateways_are_role_separated(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('بوابة الإدارة');
        $this->get('/staff/login')->assertOk()->assertSee('بوابة خدمة العملاء');
        $this->post('/admin/login', ['username' => 'ag1', 'password' => 'Agent12345'])->assertSessionHasErrors('username');
        $this->post('/staff/login', ['username' => 'admin', 'password' => 'x'])->assertSessionHasErrors('username');
        $this->post('/staff/login', ['username' => 'ag1', 'password' => 'Agent12345'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->agent);
    }

    public function test_account_locks_after_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/staff/login', ['username' => 'ag1', 'password' => 'bad']);
        }
        $this->assertTrue($this->agent->fresh()->isLocked());
        $this->post('/staff/login', ['username' => 'ag1', 'password' => 'Agent12345'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_agent_cannot_reach_admin_or_export_areas(): void
    {
        $this->actingAs($this->agent);
        foreach (['/reports', '/reports/summary', '/reports/summary/export', '/data', '/data/export/customers', '/data/export/payments', '/users', '/settings', '/logs', '/data/template/customers'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $c = $this->customer(['assigned_to' => $this->agent->id, 'created_by' => $this->agent->id]);
        $this->delete("/customers/{$c->id}")->assertForbidden();
        $this->post('/data/upload', [])->assertForbidden();
    }

    public function test_agent_only_sees_own_customers_and_masked_national_id(): void
    {
        $mine = $this->customer(['name' => 'عميلي', 'assigned_to' => $this->agent->id, 'created_by' => $this->agent->id, 'nat_id' => '29001011234567']);
        $other = $this->customer(['name' => 'عميل زميلي']);
        $this->actingAs($this->agent);
        $this->get('/customers')->assertSee('عميلي')->assertDontSee('عميل زميلي');
        $this->get("/customers/{$other->id}")->assertNotFound();
        $this->get("/customers/{$mine->id}")->assertOk()->assertDontSee('29001011234567')->assertSee('4567');
        $this->assertNotEquals('29001011234567', \DB::table('customers')->where('id', $mine->id)->value('nat_id'));
    }

    public function test_duplicate_phone_is_blocked_and_reports_owner(): void
    {
        $this->customer(['phone' => '01011112222']);
        $this->actingAs($this->agent)->post('/customers', ['name' => 'مكرر', 'phone' => '01011112222', 'status' => 'مفتوحة'])->assertSessionHasErrors('phone');
        $this->get('/customers/check-phone?phone=01011112222')->assertJson(['exists' => true, 'can_open' => false]);
    }

    public function test_agent_creates_customer_tracking_creator(): void
    {
        $this->actingAs($this->agent)->post('/customers', ['name' => 'أحمد', 'phone' => '+20 101 555 7777', 'status' => 'مفتوحة', 'nat_id' => '29001011234567'])
            ->assertRedirect();
        $c = Customer::where('name', 'أحمد')->first();
        $this->assertSame('01015557777', $c->phone);
        $this->assertSame($this->agent->id, $c->created_by);
        $this->assertDatabaseHas('customer_events', ['customer_id' => $c->id, 'type' => 'created', 'user_id' => $this->agent->id]);
        $this->assertSame(1, Customer::where('nat_id_hash', Customer::hashNatId('29001011234567'))->count());
    }

    public function test_installment_calculator_math(): void
    {
        $flat = InstallmentCalculator::calculate(['price' => 120000, 'down' => 20000, 'months' => 12, 'rate' => 12, 'type' => 'flat']);
        $this->assertEquals(100000, $flat['financed']);
        $this->assertEquals(12000, $flat['interest_total']);
        $this->assertEquals(9333.33, $flat['monthly']);
        $this->assertEquals(112000, $flat['total_to_pay']);
        $this->assertCount(12, $flat['schedule']);
        $this->assertEquals(0, end($flat['schedule'])['balance']);

        $red = InstallmentCalculator::calculate(['price' => 100000, 'down' => 0, 'months' => 12, 'rate' => 12, 'type' => 'reducing']);
        $this->assertEqualsWithDelta(8884.88, $red['monthly'], 0.02);
        $this->assertEquals(0, end($red['schedule'])['balance']);

        $zero = InstallmentCalculator::calculate(['price' => 60000, 'down' => 0, 'months' => 6, 'rate' => 0]);
        $this->assertEquals(10000, $zero['monthly']);
    }

    public function test_deal_generates_schedule_and_payments_allocate(): void
    {
        $c = $this->customer();
        $this->actingAs($this->admin)->post("/customers/{$c->id}/deals", [
            'vehicle' => 'هيونداي توسان', 'pay_method' => 'تقسيط', 'total_price' => 120000, 'down_payment' => 20000, 'months' => 10,
            'interest_rate' => 0, 'interest_type' => 'flat', 'monthly_installment' => 10000, 'first_due_date' => now()->subMonths(3)->toDateString(), 'status' => 'جاري التقسيط',
        ])->assertRedirect();
        $deal = Deal::first();
        $this->assertCount(10, $deal->installments);
        $this->assertEquals(100000, $deal->balance);

        $this->post("/deals/{$deal->id}/payments", ['amount' => 25000, 'paid_on' => today()->toDateString(), 'method' => 'نقدي'])->assertSessionHasNoErrors();
        $deal->refresh();
        $this->assertEquals(75000, $deal->balance);
        $this->assertEquals(10000, $deal->installments[0]->paid_amount);
        $this->assertEquals(5000, $deal->installments[2]->paid_amount);
        $this->assertSame('partial', $deal->installments[2]->state === 'overdue' ? 'partial' : $deal->installments[2]->state);
        $this->assertSame('جاري التقسيط', $c->fresh()->status);

        $this->post("/deals/{$deal->id}/payments", ['amount' => 5, 'paid_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('paid_on');
        $this->get("/customers/{$c->id}")->assertOk()->assertSee('جدول الأقساط');
        $this->get("/deals/{$deal->id}/schedule")->assertOk();
        $this->delete('/payments/' . $deal->payments()->first()->id)->assertRedirect();
        $this->assertEquals(100000, $deal->fresh()->balance);
    }

    public function test_followup_lifecycle_and_visibility(): void
    {
        $c = $this->customer(['assigned_to' => $this->agent->id, 'created_by' => $this->agent->id]);
        $this->actingAs($this->agent)->post('/followups', ['customer_id' => $c->id, 'reason' => 'تذكير', 'due_date' => today()->toDateString(), 'notes' => 'اتصل'])->assertSessionHasNoErrors();
        $f = Followup::first();
        $this->assertSame($this->agent->id, $f->assigned_to);
        $this->get('/followups?tab=today')->assertOk()->assertSee('عميل تجريبي');
        $this->post("/followups/{$f->id}/complete", ['outcome' => 'رد وسيحضر', 'next_date' => now()->addDays(3)->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('done', $f->fresh()->status);
        $this->assertSame(1, Followup::where('status', 'pending')->count());
        $this->post('/followups', ['customer_id' => $c->id, 'reason' => 'تذكير', 'due_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('due_date');

        // another agent cannot touch it
        $other = User::factory()->create(['role' => 'agent', 'must_change_password' => false]);
        $this->actingAs($other)->post('/followups/' . Followup::where('status', 'pending')->first()->id . '/complete', ['outcome' => 'x'])->assertForbidden();
    }

    public function test_admin_can_open_every_page(): void
    {
        $c = $this->customer();
        $this->actingAs($this->admin);
        $deal = app(\App\Services\DealService::class)->save($c, ['vehicle' => 'x', 'pay_method' => 'تقسيط', 'total_price' => 50000, 'down_payment' => 10000, 'months' => 6, 'interest_rate' => 10, 'status' => 'جاري التقسيط'], null, $this->admin);
        Followup::create(['customer_id' => $c->id, 'reason' => 'تذكير', 'due_date' => today(), 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id]);

        foreach (['/dashboard', '/customers', '/customers/create', "/customers/{$c->id}", "/customers/{$c->id}/edit", "/customers/{$c->id}/deals/create", "/deals/{$deal->id}/edit", "/deals/{$deal->id}/schedule",
            '/followups', '/followups?tab=all', '/calculator', '/reports', '/data', '/users', '/users/create', "/users/{$this->agent->id}", "/users/{$this->agent->id}/edit", '/settings', '/logs', '/password', '/customers/lookup?q=عميل'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (array_keys(\App\Services\ReportService::REPORTS) as $key) {
            $this->get("/reports/{$key}")->assertOk();
            $this->get("/reports/{$key}/export")->assertOk();
        }
        $this->get('/data/export/customers')->assertOk();
        $this->get('/data/export/followups')->assertOk();
        $this->get('/data/export/payments')->assertOk();
        foreach (array_keys(Schemas::TYPES) as $t) {
            $this->get("/data/template/{$t}")->assertOk();
        }
    }

    public function test_agent_pages_render(): void
    {
        $c = $this->customer(['assigned_to' => $this->agent->id, 'created_by' => $this->agent->id]);
        $this->actingAs($this->agent);
        foreach (['/dashboard', '/customers', '/customers/create', "/customers/{$c->id}", "/customers/{$c->id}/edit", '/followups', '/calculator', '/my-activity'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post('/calculator/calculate', ['price' => 100000, 'down' => 10000, 'months' => 12, 'rate' => 15])->assertOk()->assertJsonStructure(['monthly', 'schedule']);
        $this->post('/calculator/export', ['price' => 100000, 'down' => 10000, 'months' => 12, 'rate' => 15])->assertOk();
    }

    public function test_excel_template_roundtrip_import(): void
    {
        Storage::fake('local');
        // 1) build a filled template exactly like a user would
        $book = TemplateBuilder::template('customers');
        $ws = $book->getSheet(0);
        $this->assertSame('العملاء', $ws->getTitle());
        $headers = [];
        foreach (Schemas::columns('customers') as $key => $col) {
            $headers[$key] = count($headers) + 1;
        }
        $rows = [
            ['name' => 'سمير علي', 'phone' => '01012345678', 'governorate' => 'القاهرة', 'status' => 'جاري التقسيط', 'vehicle' => 'كيا سبورتاج', 'pay_method' => 'تقسيط',
                'finance_entity' => 'أمان', 'total_price' => 300000, 'down_payment' => 60000, 'months' => 24, 'interest_rate' => 0, 'monthly_installment' => 10000, 'first_due_date' => '2026-12-01', 'nat_id' => '29001011234567'],
            ['name' => 'منى حسن', 'phone' => '01112345678', 'status' => 'مفتوحة'],
            ['name' => '', 'phone' => '123'],               // invalid
            ['name' => 'سمير علي', 'phone' => '01012345678', 'vehicle' => 'إم جي RX5', 'pay_method' => 'كاش', 'total_price' => 500000],  // second deal same customer
        ];
        foreach ($rows as $i => $r) {
            foreach ($r as $k => $v) {
                $cell = [$headers[$k], $i + 2];
                is_numeric($v) && ! in_array($k, ['phone', 'nat_id']) ? $ws->setCellValue($cell, $v) : $ws->setCellValueExplicit($cell, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'x') . '.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($tmp);

        // 2) upload + preview
        $this->actingAs($this->admin);
        $resp = $this->post('/data/upload', ['type' => 'customers', 'mode' => 'skip', 'file' => new UploadedFile($tmp, 'c.xlsx', null, null, true)]);
        $import = Import::first();
        $resp->assertRedirect("/data/import/{$import->id}");
        $this->assertSame(4, $import->total);
        $this->assertSame(3, $import->valid);
        $this->assertSame(1, $import->failed);
        $this->get("/data/import/{$import->id}")->assertOk()->assertSee('الاسم مطلوب');

        // 3) confirm
        $this->post("/data/import/{$import->id}/confirm", ['mode' => 'skip'])->assertRedirect("/data/import/{$import->id}/result");
        $this->assertSame(2, Customer::whereIn('name', ['سمير علي', 'منى حسن'])->count());
        $sameer = Customer::where('phone', '01012345678')->first();
        $this->assertSame(2, $sameer->deals()->count());
        $this->assertSame($this->admin->id, $sameer->created_by);
        $this->assertCount(24, $sameer->deals()->where('pay_method', 'تقسيط')->first()->installments);
        $this->assertSame('29001011234567', $sameer->nat_id);
        $this->get("/data/import/{$import->id}/result")->assertOk();
        $this->get("/data/import/{$import->id}/errors")->assertOk();

        // 4) export → re-import (round trip) with update mode must not duplicate anything
        $path = tempnam(sys_get_temp_dir(), 'e') . '.xlsx';
        file_put_contents($path, $this->get('/data/export/customers')->streamedContent());
        $this->post('/data/upload', ['type' => 'customers', 'mode' => 'update', 'file' => new UploadedFile($path, 'e.xlsx', null, null, true)]);
        $second = Import::latest('id')->first();
        $this->assertSame(0, $second->failed, json_encode($second->errors, JSON_UNESCAPED_UNICODE));
        $this->post("/data/import/{$second->id}/confirm", ['mode' => 'update']);
        $this->assertSame(2, Customer::count());
        $this->assertSame(2, $sameer->deals()->count());
    }

    public function test_view_guard_alerts_and_blocks_scraping(): void
    {
        \App\Support\Settings::set('view_alert_threshold', 5);
        \App\Support\Settings::set('view_block_threshold', 8);
        $cs = collect(range(1, 10))->map(fn () => $this->customer(['assigned_to' => $this->agent->id, 'created_by' => $this->agent->id]));
        $this->actingAs($this->agent);
        $codes = [];
        foreach ($cs as $c) {
            $codes[] = $this->get("/customers/{$c->id}")->getStatusCode();
        }
        $this->assertContains(429, $codes);
        $this->assertDatabaseHas('activity_logs', ['action' => 'security.alert']);
    }

    public function test_api_token_flow_and_agent_restrictions(): void
    {
        $this->postJson('/api/v1/auth/login', ['username' => 'ag1', 'password' => 'Agent12345'])->assertOk()->assertJsonStructure(['token']);
        $token = $this->postJson('/api/v1/auth/login', ['username' => 'ag1', 'password' => 'Agent12345'])->json('token');
        $h = ['Authorization' => "Bearer {$token}"];
        $this->getJson('/api/v1/me', $h)->assertOk()->assertJson(['username' => 'ag1']);
        $this->getJson('/api/v1/customers', $h)->assertOk();
        $this->getJson('/api/v1/users', $h)->assertForbidden();
        $this->getJson('/api/v1/reports/summary', $h)->assertForbidden();
        $this->postJson('/api/v1/customers', ['name' => 'عبر API', 'phone' => '01299998888', 'status' => 'مفتوحة'], $h)->assertCreated();
        $this->postJson('/api/v1/calculator', ['price' => 100000, 'months' => 12, 'rate' => 10], $h)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }
}
