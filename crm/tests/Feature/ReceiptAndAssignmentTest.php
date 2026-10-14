<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentLog;
use App\Models\Lead;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptAndAssignmentTest extends TestCase
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
        $this->agent = User::factory()->create(['username' => 'ag7', 'role' => 'agent', 'password' => 'Agent12345', 'must_change_password' => false]);
        Permissions::flush();
    }

    public function test_receipt_has_code_reference_date_and_details(): void
    {
        $c = Customer::create(['name' => 'عميل الإيصال', 'phone' => '01012121212', 'nat_id' => '29501012700000', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->actingAs($this->admin);

        $this->get("/documents/new/receipt?customer={$c->id}")->assertOk()
            ->assertSee('value="' . $c->code . '"', false)->assertSee('29501012700000')
            ->assertSee('name="pay_method"', false)->assertSee('إنستاباي')->assertSee('رقم المرجع')->assertSee('رقم إيصال التحويل')
            ->assertSee('value="' . today()->toDateString() . '"', false);

        $this->post('/documents/new/receipt', ['customer_id' => $c->id, 'customer_name' => $c->name, 'code' => $c->code, 'nat_id' => '29501012700000',
            'amount' => 5000.01, 'reason' => 'مقدم حجز', 'paid_on' => '2026-10-14', 'pay_method' => 'إنستاباي', 'ref_no' => '459871236',
            'transfer_receipt' => 'TR-1', 'details' => 'الباقي عند الاستلام', 'received_by' => 'أنور'])->assertRedirect();
        $log = DocumentLog::where('type', 'receipt')->latest('id')->first();
        $this->assertSame('459871236', $log->data()['ref_no']);

        $html = view('pdf.document', ['type' => 'receipt', 'def' => \App\Services\DocumentService::type('receipt'), 'd' => $log->data(), 'deal' => null,
            'docNo' => 'R-1', 'docDate' => '2026/10/14', 'zarad' => true, 'company' => \App\Services\PdfService::company(), 'title' => 'x', 'logo' => ''])->render();
        foreach (['كود: <b>' . $c->code, 'رقم قومي: <b>29501012700000', '14-Oct-2026', 'رقم المرجع', '459871236', 'TR-1', 'تفاصيل', 'الباقي عند الاستلام', 'لاغياً بدون ختم'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        $this->get("/documents/{$log->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // cash: no reference line, the details box stays empty for handwriting
        $cash = view('pdf.document', ['type' => 'receipt', 'def' => \App\Services\DocumentService::type('receipt'), 'd' => ['customer_name' => 'x', 'amount' => 100, 'pay_method' => 'كاش'], 'deal' => null,
            'docNo' => 'R-2', 'docDate' => '2026/10/14', 'zarad' => true, 'company' => \App\Services\PdfService::company(), 'title' => 'x', 'logo' => ''])->render();
        $this->assertStringNotContainsString('رقم المرجع', $cash);
        $this->assertStringContainsString('تفاصيل', $cash);
    }

    public function test_customers_assigned_by_someone_else_show_as_new_until_opened(): void
    {
        $c = Customer::create(['name' => 'عميل محوَّل', 'phone' => '01034343434', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $old = Customer::create(['name' => 'عميل قديم للموظف', 'phone' => '01056565656', 'status' => 'مفتوحة', 'created_by' => $this->agent->id, 'assigned_to' => $this->agent->id]);

        $this->actingAs($this->admin)->post("/customers/{$c->id}/assign", ['assigned_to' => $this->agent->id])->assertRedirect();
        $lead = Lead::create(['name' => 'ليد جديد', 'phone' => '01078787878', 'status' => 'new']);
        $this->post('/leads/assign', ['ids' => [$lead->id], 'assigned_to' => $this->agent->id])->assertRedirect();
        $this->assertSame(2, Customer::newlyAssignedTo($this->agent)->count());

        $this->actingAs($this->agent);
        $this->get('/dashboard')->assertOk()->assertSee('تم تعيين 2 عملاء جدد لك')->assertSee('عميل محوَّل')->assertSee('ليد جديد');
        $list = $this->get('/customers')->assertOk()->assertSee('جديد — اتعيّن لك');
        $this->assertTrue(strpos($list->getContent(), 'عميل محوَّل') < strpos($list->getContent(), 'عميل قديم للموظف'), 'new customers come first');
        $this->get('/customers?new=1')->assertSee('عميل محوَّل')->assertDontSee('عميل قديم للموظف');

        // opening the file clears the "new" mark
        $this->get("/customers/{$c->id}")->assertOk();
        $this->assertSame(1, Customer::newlyAssignedTo($this->agent)->count());
        $this->get('/dashboard')->assertSee('تم تعيين 1 عميل جديد لك');

        // a customer the employee adds himself is never "new" for him
        $this->post('/customers', ['name' => 'عميل بنفسه', 'phone' => '01090909090', 'status' => 'مفتوحة'])->assertRedirect();
        $this->assertSame(1, Customer::newlyAssignedTo($this->agent)->count());
    }
}
