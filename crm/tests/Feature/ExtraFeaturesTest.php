<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DocumentLog;
use App\Models\RoleProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DocumentService;
use App\Support\ArabicNumber;
use App\Support\InstallmentCalculator;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtraFeaturesTest extends TestCase
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
        $this->agent = User::factory()->create(['username' => 'ag2', 'role' => 'agent', 'password' => 'Agent12345', 'must_change_password' => false]);
        Permissions::flush();
    }

    public function test_entity_plans_match_company_sheets(): void
    {
        // Aman sheet: cash 83,000, down 23,000, 12 months -> 6,200 monthly, fees 3% + 650 = 2,450
        $r = InstallmentCalculator::calculate(['price' => 83000, 'down' => 23000, 'months' => 12, 'type' => 'table', 'plan' => 'أمان']);
        $this->assertEquals(6200, $r['monthly']);
        $this->assertEquals(2450, $r['fees']);
        $this->assertEquals(99850, $r['grand_total']);
        // company default plan (Reefy-old sheet) 24 months multiplier 1.44
        $r = InstallmentCalculator::calculate(['price' => 58500, 'down' => 500, 'months' => 24, 'type' => 'table']);
        $this->assertEquals(round(58000 * 1.44 / 24, 2), $r['monthly']);
    }

    public function test_arabic_amount_words(): void
    {
        $this->assertStringContainsString('خمسة وسبعون ألف', ArabicNumber::egp(75000));
        $this->assertStringContainsString('مائة وواحد وعشرون', ArabicNumber::words(121));
    }

    public function test_every_document_type_renders_pdf(): void
    {
        $this->actingAs($this->admin);
        $c = Customer::create(['name' => 'عميل', 'phone' => '01011112222', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $deal = Deal::create(['customer_id' => $c->id, 'vehicle' => 'Qute', 'pay_method' => 'كاش', 'total_price' => 200000, 'created_by' => $this->admin->id]);
        foreach (DocumentService::types() as $key => $def) {
            $res = $this->post("/documents/new/{$key}", ['deal_id' => $deal->id, 'customer_name' => 'عميل اختبار', 'product' => 'Qute', 'price' => 100000, 'amount' => 5000, 'items' => 'Boxer|2026|أسود|CH1|MO1']);
            $res->assertRedirect();
            $log = DocumentLog::latest('id')->first();
            $pdf = $this->get("/documents/{$log->id}/pdf");
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), "type {$key}");
        }
    }

    public function test_agent_permissions_are_enforced_and_editable(): void
    {
        $this->actingAs($this->agent);
        $this->get('/vehicles')->assertOk();
        $this->get('/reports')->assertForbidden();
        $this->get('/documents/new/mobaya')->assertNotFound();   // legal docs not in default profile
        $this->get('/documents/new/quote')->assertOk();
        $this->get('/backups')->assertForbidden();
        $this->get('/roles')->assertForbidden();
        $this->post('/vehicles', ['chassis' => 'ABC1', 'status' => 'in_stock'])->assertForbidden();

        $p = RoleProfile::where('is_default', true)->first();
        $this->actingAs($this->admin)->put("/roles/{$p->id}", ['name' => $p->name, 'perms' => ['reports' => ['view' => 1], 'vehicles' => ['view' => 1, 'create' => 1], 'legal_documents' => ['create' => 1], 'customers' => ['view' => 1]]])->assertRedirect();
        Permissions::flush();
        $this->actingAs($this->agent->fresh());
        $this->get('/reports')->assertOk();
        $this->post('/vehicles', ['chassis' => 'ABC1', 'status' => 'in_stock'])->assertRedirect();
        $this->assertDatabaseHas('vehicles', ['chassis' => 'ABC1']);
        $this->get('/documents/new/mobaya')->assertOk();
        $this->get('/followups')->assertForbidden();            // no longer granted
        $this->get('/reports/summary/export')->assertForbidden(); // exports stay admin-only
    }

    public function test_backup_creates_gzip_dump_and_download_is_admin_only(): void
    {
        $this->actingAs($this->admin)->post('/backups')->assertRedirect();
        $b = Backup::first();
        $this->assertSame('ok', $b->status);
        $this->assertFileExists($b->path());
        $sql = gzdecode(file_get_contents($b->path()));
        $this->assertStringContainsString('CREATE TABLE `customers`', $sql);
        $this->assertStringContainsString("INSERT INTO `users`", $sql);
        $this->get("/backups/{$b->id}/download")->assertOk();
        $this->actingAs($this->agent)->get("/backups/{$b->id}/download")->assertForbidden();
        @unlink($b->path());
    }

    public function test_summary_pages_and_vehicle_stock(): void
    {
        Vehicle::create(['chassis' => 'ZZ1ABCDEFGH', 'type' => 'Boxer', 'status' => 'in_stock', 'cost_price' => 50000]);
        $this->actingAs($this->admin);
        foreach (['day', 'week', 'month', 'year'] as $p) {
            $this->get("/summary?period={$p}")->assertOk()->assertSee('ملخص');
        }
        $this->get('/summary/pdf?period=month')->assertOk();
        $this->get('/vehicles')->assertOk()->assertSee('ZZ1ABCDEFGH');
        $this->actingAs($this->agent)->get('/summary')->assertOk();
        $this->get('/summary/pdf')->assertForbidden();
        $this->get('/vehicles')->assertOk()->assertDontSee('ZZ1ABCDEFGH');   // masked for staff
    }

    public function test_report_pdf_for_admin_only(): void
    {
        $this->actingAs($this->admin)->get('/reports/summary/pdf')->assertOk();
        $this->actingAs($this->agent)->get('/reports/summary/pdf')->assertForbidden();
    }

    public function test_all_reports_render_for_admin(): void
    {
        $this->actingAs($this->admin);
        foreach (array_keys(\App\Services\ReportService::REPORTS) as $k) {
            $this->get("/reports/{$k}")->assertOk();
            $this->get("/reports/{$k}/pdf")->assertOk();
        }
    }

    public function test_phone_search_opens_existing_or_offers_new(): void
    {
        $c = Customer::create(['name' => 'عميل بحث', 'phone' => '01012345678', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->actingAs($this->admin);
        $this->get('/customers?q=01012345678')->assertRedirect("/customers/{$c->id}");
        $this->get('/customers?q=01199999999')->assertOk()->assertSee('إضافة عميل جديد بهذا الرقم')->assertSee('phone=01199999999', false);
        $this->get('/customers/create?phone=01199999999')->assertOk()->assertSee('01199999999');
        $this->getJson('/customers/check-phone?phone=01012345678')->assertJson(['exists' => true, 'name' => 'عميل بحث']);
        $this->getJson('/customers/check-phone?phone=01199999999')->assertJson(['exists' => false])->assertJsonStructure(['new_url']);
    }

    public function test_alt_phone_already_registered_is_rejected(): void
    {
        Customer::create(['name' => 'موجود', 'phone' => '01055556666', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->actingAs($this->admin);
        $this->post('/customers', ['name' => 'جديد', 'phone' => '01077778888', 'alt_phone' => '01055556666', 'status' => 'مفتوحة'])
            ->assertSessionHasErrors('alt_phone');
        $this->assertDatabaseMissing('customers', ['phone' => '01077778888']);
        $this->post('/customers', ['name' => 'جديد', 'phone' => '01077778888', 'alt_phone' => '01088889999', 'status' => 'مفتوحة'])->assertRedirect();
    }
}
