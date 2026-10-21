<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerVehicle;
use App\Models\Deal;
use App\Models\Followup;
use App\Models\Payment;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceEntityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->first();
        $this->admin->forceFill(['must_change_password' => false])->save();
        Permissions::flush();
        $this->actingAs($this->admin);
    }

    private function customer(string $name, array $o = []): Customer
    {
        return Customer::create($o + ['name' => $name, 'phone' => '0100' . random_int(1000000, 9999999), 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
    }

    private function deal(Customer $c, array $o = []): Deal
    {
        $this->post("/customers/{$c->id}/deals", $o + ['vehicle' => 'بوكسر', 'model' => '2027', 'pay_method' => 'تقسيط', 'status' => 'جاري التقسيط', 'total_price' => 58500,
            'down_payment' => 30500, 'months' => 18, 'interest_type' => 'table', 'sale_date' => today()->toDateString()])->assertSessionHasNoErrors();

        return $c->deals()->latest('id')->first();
    }

    public function test_an_entity_deal_owes_the_financed_amount_without_a_schedule_and_a_direct_one_keeps_it(): void
    {
        $c = $this->customer('عميل أمان');
        $d = $this->deal($c, ['finance_entity' => 'أمان', 'entity_branch' => 'قفط', 'stage' => 'تم الموافقة']);
        $this->assertTrue($d->isEntityFinanced());
        $this->assertSame(0, $d->installments()->count());
        $this->assertEquals(28000, $d->balance);
        $this->assertGreaterThan(0, $d->monthly_installment, 'kept for the offer text');
        $this->assertSame('قفط', $d->entity_branch);

        $page = $this->get("/customers/{$c->id}")->assertOk()->assertDontSee('عرض الأقساط')->assertDontSee('سداد الأقساط')
            ->assertSee('المتبقي (المديونية على أمان)')->assertSee('لم يتم الإيداع')->assertSee('أمان — فرع قفط')->assertSee('28,000.00')
            ->assertSee('تسجيل تحويل أمان')->assertDontSee('data-open="m-pay"', false)->assertSee('نسخ للتقسيط')->getContent();
        $this->assertStringNotContainsString('جدول الأقساط</a>', $page);

        $direct = $this->deal($this->customer('عميل مباشر'));
        $this->assertFalse($direct->isEntityFinanced());
        $this->assertSame(18, $direct->installments()->count());
        $this->get("/customers/{$direct->customer_id}")->assertSee('عرض الأقساط')->assertSee('سداد الأقساط');
        $this->get("/deals/{$d->id}/edit")->assertOk()->assertSee('name="entity_branch"', false)->assertSee('entity-hint', false);
    }

    public function test_the_entity_transfer_is_deposited_frozen_then_collected(): void
    {
        $c = $this->customer('عميل ريفي');
        $d = $this->deal($c, ['finance_entity' => 'ريفي']);

        // deposited three days ago: frozen, no payment yet
        $this->post("/deals/{$d->id}/entity", ['entity_ref' => 'TRX-5531', 'entity_deposited_at' => today()->subDays(3)->toDateString(), 'entity_channel' => 'تحويل بنكي', 'entity_account' => 'بنك مصر'])
            ->assertSessionHasNoErrors();
        $d->refresh();
        $this->assertSame('deposited', $d->entity_state);
        $this->assertSame(0, $d->entity_late_days);
        $this->assertSame(0, Payment::count());
        $this->assertEquals(28000, $d->balance);
        $this->get("/customers/{$c->id}")->assertSee('مجمد — يُحصّل ' . today()->subDays(3)->addDays(Deal::ENTITY_DAYS)->format('Y/m/d'))->assertSee('TRX-5531');

        // more than 15 days frozen: late
        $d->forceFill(['entity_deposited_at' => today()->subDays(20)])->save();
        $this->assertSame(5, $d->fresh()->entity_late_days);
        $this->get('/reports/entity_dues?state=late&from=' . today()->subYear()->toDateString())->assertOk()->assertSee($c->code)->assertSee('متأخر 5 يوم');

        // collected: one payment, the debt is cleared and the deal is executed
        $this->post("/deals/{$d->id}/entity", ['entity_ref' => 'TRX-5531', 'entity_deposited_at' => today()->subDays(20)->toDateString(), 'entity_collected_at' => today()->toDateString(),
            'entity_channel' => 'إنستاباي', 'entity_account' => 'حساب الشركة'])->assertSessionHasNoErrors();
        $d->refresh();
        $this->assertSame('collected', $d->entity_state);
        $this->assertEquals(0, $d->balance);
        $this->assertSame('منفذة', $d->status);
        $p = Payment::sole();
        $this->assertEquals(28000, $p->amount);
        $this->assertSame('إنستاباي', $p->method);
        $this->assertStringContainsString('تحصيل من ريفي — مرجع TRX-5531', $p->note);
        $this->get("/customers/{$c->id}")->assertSee('تم التحصيل')->assertSee('إنستاباي حساب الشركة');

        // saving again edits the same payment; the collections report and dues report follow it
        $this->post("/deals/{$d->id}/entity", ['entity_amount' => 27500, 'entity_ref' => 'TRX-5531', 'entity_collected_at' => today()->toDateString(), 'entity_channel' => 'نقدي'])->assertSessionHasNoErrors();
        $this->assertSame(1, Payment::count());
        $this->assertEquals(27500, Payment::sole()->amount);
        $this->assertEquals(500, $d->fresh()->balance);
        $this->get('/reports/entity_dues')->assertOk()->assertSee('تم تحصيله في الفترة')->assertSee('27,500.00');

        // deleting the payment puts the transfer back to "not collected"
        $this->delete('/payments/' . Payment::sole()->id)->assertRedirect();
        $this->assertNull($d->fresh()->entity_collected_at);
        $this->assertEquals(28000, $d->fresh()->balance);

        // a direct deal has no entity transfer
        $direct = $this->deal($this->customer('مباشر'));
        $this->post("/deals/{$direct->id}/entity", ['entity_collected_at' => today()->toDateString()])->assertNotFound();
    }

    public function test_a_requested_vehicle_keeps_its_calculation_entity_branch_and_stage(): void
    {
        $c = $this->customer('عميل مركبات');
        $this->post("/customers/{$c->id}/vehicles", ['vehicle' => 'بوكسر 150', 'finance_entity' => 'ريفي', 'entity_branch' => 'قفط', 'stage' => 'قيد المراجعة'])->assertSessionHasNoErrors();
        $v = CustomerVehicle::where('customer_id', $c->id)->sole();
        $this->assertSame(['ريفي', 'قفط', 'قيد المراجعة'], [$v->finance_entity, $v->entity_branch, $v->stage]);
        $this->assertNotNull($v->entity_sent_at);

        $this->put("/customers/{$c->id}/vehicles/{$v->id}", ['finance_entity' => 'ريفي', 'entity_branch' => 'قوص', 'stage' => 'تم الموافقة', 'notes' => 'مستني الاستعلام'])->assertSessionHasNoErrors();
        $this->assertSame(['قوص', 'تم الموافقة', 'مستني الاستعلام'], [$v->fresh()->entity_branch, $v->fresh()->stage, $v->fresh()->notes]);

        // the calculation is recomputed on the server and saved under the vehicle
        $r = $this->postJson("/customers/{$c->id}/vehicles/{$v->id}/calc", ['price' => 58500, 'down' => 30500, 'months' => 18, 'plan' => 'أمان'])->assertOk()->json('calc');
        $calc = $v->fresh()->calc;
        $this->assertSame(18, $calc['months']);
        $this->assertSame('أمان', $calc['plan']);
        $this->assertEquals($r['monthly'], $calc['monthly']);
        $this->assertGreaterThan(0, $calc['monthly']);
        $this->assertSame('ريفي', $v->fresh()->finance_entity, 'an entity already chosen is kept');

        $this->get("/customers/{$c->id}")->assertOk()->assertSee('الحساب المحفوظ')->assertSee(number_format($calc['monthly'], 2))->assertSee('18 شهر — أمان')
            ->assertSee('ريفي — فرع قوص')->assertSee('تم الموافقة')->assertSee('data-qc-saved=', false)->assertSee('حفظ الحساب للمركبة');

        // a vehicle with no entity takes the calculation's entity; nonsense input is refused
        $this->post("/customers/{$c->id}/vehicles", ['vehicle' => 'كلاسيك'])->assertSessionHasNoErrors();
        $v2 = CustomerVehicle::where('customer_id', $c->id)->where('vehicle', 'كلاسيك')->sole();
        $this->postJson("/customers/{$c->id}/vehicles/{$v2->id}/calc", ['price' => 40000, 'down' => 10000, 'months' => 12, 'plan' => 'أمان'])->assertOk();
        $this->assertSame('أمان', $v2->fresh()->finance_entity);
        $this->postJson("/customers/{$c->id}/vehicles/{$v2->id}/calc", ['price' => 40000, 'down' => 40000, 'months' => 12])->assertStatus(422);
        $other = $this->customer('عميل تاني');
        $this->putJson("/customers/{$other->id}/vehicles/{$v2->id}", ['stage' => 'ملغي'])->assertNotFound();
    }

    public function test_entity_filter_and_report_include_customers_sent_there_who_did_not_execute(): void
    {
        $sentOnly = $this->customer('اتبعت لريفي بس');
        CustomerVehicle::create(['customer_id' => $sentOnly->id, 'vehicle' => 'بوكسر', 'finance_entity' => 'ريفي', 'entity_branch' => 'قفط', 'stage' => 'قيد المراجعة', 'entity_sent_at' => now(), 'created_by' => $this->admin->id]);
        Followup::create(['customer_id' => $sentOnly->id, 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id, 'due_date' => today(), 'reason' => 'متابعة الاستعلام', 'status' => 'done', 'outcome' => 'مستني رد ريفي']);

        $executed = $this->customer('نفذ مع ريفي');
        CustomerVehicle::create(['customer_id' => $executed->id, 'vehicle' => 'بوكسر', 'finance_entity' => 'ريفي', 'entity_branch' => 'قفط', 'entity_sent_at' => now(), 'created_by' => $this->admin->id]);
        $this->deal($executed, ['finance_entity' => 'ريفي', 'entity_branch' => 'قفط']);

        $wentCash = $this->customer('رفض واشترى كاش');
        CustomerVehicle::create(['customer_id' => $wentCash->id, 'vehicle' => 'بوكسر', 'finance_entity' => 'ريفي', 'entity_branch' => 'قوص', 'stage' => 'مرفوض ائتمانين', 'entity_sent_at' => now(), 'created_by' => $this->admin->id]);
        $this->deal($wentCash, ['pay_method' => 'كاش', 'status' => 'منفذة']);

        $aman = $this->customer('اتبعت لأمان');
        CustomerVehicle::create(['customer_id' => $aman->id, 'vehicle' => 'بوكسر', 'finance_entity' => 'أمان', 'entity_sent_at' => now(), 'created_by' => $this->admin->id]);

        // the customers list: sent to ريفي, executed or not
        $this->get('/customers?finance_entity=' . urlencode('ريفي'))->assertOk()->assertSee('اتبعت لريفي بس')->assertSee('نفذ مع ريفي')->assertSee('رفض واشترى كاش')->assertDontSee('اتبعت لأمان');
        $this->get('/customers?finance_entity=' . urlencode('ريفي') . '&entity_branch=' . urlencode('قوص'))->assertSee('رفض واشترى كاش')->assertDontSee('اتبعت لريفي بس');

        $page = $this->get('/reports/entity_sent?entity=' . urlencode('ريفي'))->assertOk()->assertSee('نفذ — جاري التقسيط')->assertSee('لم ينفذ — اشترى كاش')
            ->assertSee('قيد المتابعة')->assertSee('مستني رد ريفي')->assertDontSee('اتبعت لأمان')->getContent();
        $this->assertStringContainsString(json_encode('ريفي — قفط'), $page);   // chart: two sent through قفط, one through قوص
        $this->get('/reports/entity_sent?entity=' . urlencode('ريفي') . '&result=not_done')->assertSee('رفض واشترى كاش')->assertDontSee('اتبعت لريفي بس');
        $this->get('/reports/entity_sent/export?entity=' . urlencode('ريفي') . '&branch=' . urlencode('قفط'))->assertOk();
        $this->get('/reports/entity_dues/pdf?entity=' . urlencode('ريفي'))->assertOk();
    }
}
