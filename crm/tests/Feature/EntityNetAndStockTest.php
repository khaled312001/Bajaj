<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityNetAndStockTest extends TestCase
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

    private function customer(string $name): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '0100' . random_int(1000000, 9999999), 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
    }

    private function dealData(array $o = []): array
    {
        return $o + ['vehicle' => 'بوكسر', 'model' => '2027', 'pay_method' => 'تقسيط', 'status' => 'جاري التقسيط', 'total_price' => 58500,
            'down_payment' => 30500, 'months' => 18, 'interest_type' => 'table', 'sale_date' => '2026-10-20'];
    }

    private function deal(Customer $c, array $o = [])
    {
        $r = $this->post("/customers/{$c->id}/deals", $this->dealData($o));

        return [$c->deals()->latest('id')->first(), $r];
    }

    public function test_the_entity_debt_is_what_it_actually_transfers(): void
    {
        $c = $this->customer('عميل أمان');
        [$d] = $this->deal($c, ['finance_entity' => 'أمان']);
        $this->assertEquals(28000, $d->financed_amount);
        $this->assertEquals(27010, $d->balance);
        $this->get("/customers/{$c->id}")->assertSee('27,010.00')->assertSee('الممول 28,000 − 3% (840) − نثريات (150) = 27,010');
        $this->get("/deals/{$d->id}/edit")->assertSee('name="entity_net"', false)->assertSee('صافي المستحق من الجهة');

        // ريفي keeps 4% and the first installment
        [$r] = $this->deal($this->customer('عميل ريفي'), ['finance_entity' => 'ريفي']);
        $this->assertEquals(round(28000 - 1120 - $r->monthly_installment, 2), $r->balance);

        // typed by hand on the deal, then back to automatic
        $this->put("/deals/{$d->id}", $this->dealData(['finance_entity' => 'أمان', 'entity_net' => 26800]))->assertSessionHasNoErrors();
        $this->assertEquals(26800, $d->fresh()->balance);
        $this->get("/customers/{$c->id}")->assertSee('مكتوب يدوياً: 26,800');
        $this->put("/deals/{$d->id}", $this->dealData(['finance_entity' => 'أمان', 'entity_net' => '']))->assertSessionHasNoErrors();
        $this->assertNull($d->fresh()->entity_net);
        $this->assertEquals(27010, $d->fresh()->balance);

        // the transfer of the net clears the debt completely, and it stays editable
        $this->post("/deals/{$d->id}/entity", ['entity_ref' => 'R1', 'entity_collected_at' => today()->toDateString(), 'entity_channel' => 'تحويل بنكي'])->assertSessionHasNoErrors();
        $this->assertEquals(0, $d->fresh()->balance);
        $this->get("/customers/{$c->id}")->assertSee('تعديل تحويل أمان')->assertSee('تقدر ترجع تعدل أي بيانات هنا');
        $this->post("/deals/{$d->id}/entity", ['entity_ref' => 'R2', 'entity_collected_at' => ''])->assertSessionHasNoErrors();
        $this->assertSame('R2', $d->fresh()->entity_ref);
        $this->assertEquals(27010, $d->fresh()->balance);

        // new deductions in settings re-price every open entity debt
        $this->get('/settings')->assertOk()->assertSee('جهات التقسيط وشروطها');
        $this->put('/settings/deductions', ['rules' => [['entity' => 'أمان', 'percent' => 0, 'fixed' => 0, 'first' => 0, 'fees' => 0], ['entity' => 'ريفي', 'percent' => 4, 'fixed' => 0, 'first' => 1, 'fees' => 0]]])
            ->assertSessionHasNoErrors();
        $this->assertEquals(28000, $d->fresh()->balance);
        $this->assertEquals(round(28000 - 1120 - $r->monthly_installment, 2), $r->fresh()->balance);
        $agent = User::factory()->create(['username' => 'ag21', 'role' => 'agent', 'must_change_password' => false]);
        $this->actingAs($agent)->put('/settings/deductions', ['rules' => [['entity' => 'أمان', 'percent' => 50]]])->assertForbidden();
    }

    public function test_a_new_finance_entity_is_added_with_its_own_terms(): void
    {
        $this->get('/settings')->assertOk()->assertSee('إضافة جهة تقسيط')->assertSee('مدة التجميد (يوم)')->assertSee('id="ded-new"', false);
        $rules = collect(\App\Models\Lookup::list('finance_entity'))->values()->map(fn ($e) => ['entity' => $e] + \App\Models\Deal::deductionRule($e))->all();
        $rules[] = ['entity' => 'فاليو', 'percent' => 5, 'fixed' => 200, 'first' => 0, 'fees' => 1, 'days' => 10];
        $rules[] = ['entity' => '', 'percent' => 9];   // an untouched new row
        $this->put('/settings/deductions', ['rules' => $rules])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'تمت إضافة فاليو لجهات التقسيط'));

        $this->assertContains('فاليو', \App\Models\Lookup::list('finance_entity'));
        $this->assertEquals(['percent' => 5, 'fixed' => 200, 'first' => false, 'fees' => true, 'days' => 10], \App\Models\Deal::deductionRule('فاليو'));
        $this->assertEquals(15, \App\Models\Deal::deductionRule('أمان')['days'], 'the others keep their terms');
        $this->get('/settings')->assertSee('<b>فاليو</b>', false);

        // it is offered on deals and its debt follows its terms: 28,000 − 5% (1,400) − 200 − admin fees
        $c = $this->customer('عميل فاليو');
        $this->get("/customers/{$c->id}/deals/create")->assertSee('<option >فاليو</option>', false);
        [$d] = $this->deal($c, ['finance_entity' => 'فاليو', 'admin_fees' => 1000]);
        $this->assertTrue($d->isEntityFinanced());
        $this->assertEquals(28000 - 1400 - 200 - $d->admin_fees, $d->balance);
        $this->post("/deals/{$d->id}/entity", ['entity_deposited_at' => '2026-10-01'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-11', $d->fresh()->entity_due_date->toDateString(), 'frozen 10 days');
        $this->get("/customers/{$c->id}")->assertSee('data-fill-entity-days="10"', false);

        // the company's own installments can't be turned into an outside entity
        $this->put('/settings/deductions', ['rules' => [['entity' => 'نظام الشركة', 'percent' => 3]]])->assertSessionHasErrors('rules');
    }

    public function test_a_deal_chassis_is_reserved_in_stock_then_sold_with_the_buyer(): void
    {
        $unit = Vehicle::create(['chassis' => 'CAAA21BZ3VC256943', 'motor' => 'TE71101', 'type' => 'بوكسر', 'model_year' => '2027', 'color' => 'أسود', 'status' => 'in_stock', 'cost_price' => 50000, 'transport_cost' => 0, 'other_cost' => 0]);
        $spare = Vehicle::create(['chassis' => 'CAAA21BZ3VC999001', 'motor' => 'TE99001', 'status' => 'in_stock', 'cost_price' => 0, 'transport_cost' => 0, 'other_cost' => 0]);

        // saved while still open: held in stock, the motor comes from stock
        $c = $this->customer('حسين محمود');
        [$d, $r] = $this->deal($c, ['status' => 'مفتوحة', 'pay_method' => 'كاش', 'chassis' => 'caaa21bz3vc 256943', 'color' => '']);
        $r->assertSessionHas('success', fn ($m) => str_contains($m, 'اتحجز في المخزن'));
        $unit->refresh();
        $this->assertSame(['reserved', $d->id], [$unit->status, $unit->deal_id]);
        $this->assertSame('TE71101', $d->motor);
        $this->assertSame('أسود', $d->color);
        $this->get('/vehicles')->assertSee('محجوزة')->assertSee('حسين محمود');
        $this->get("/customers/{$c->id}")->assertSee('المخزن:')->assertSee('محجوزة');

        // another customer can't take it
        $other = $this->customer('عميل تاني');
        $this->post("/customers/{$other->id}/deals", $this->dealData(['chassis' => 'CAAA21BZ3VC256943']))->assertSessionHasErrors(['chassis' => 'رقم الشاسيه CAAA21BZ3VC256943 محجوز في المخزن للعميل حسين محمود (' . $c->code . ')']);
        $this->assertSame(0, $other->deals()->count());

        // executed: leaves stock as sold, with the buyer, how it was sold, the price and the date
        $this->put("/deals/{$d->id}", $this->dealData(['status' => 'منفذة', 'pay_method' => 'كاش', 'chassis' => 'CAAA21BZ3VC256943', 'motor' => 'TE71101']))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'اتسجل مباع في المخزن'));
        $unit->refresh();
        $this->assertSame('sold', $unit->status);
        $this->assertSame('2026-10-20', $unit->sold_at->toDateString());
        $this->assertEquals(58500, $unit->sale_price);
        $this->get('/vehicles')->assertSee('مباعة')->assertSee($c->code)->assertSee('كاش · 58,500 ج.م · 2026/10/20');
        $this->get('/vehicles/export')->assertOk();

        // a changed chassis gives the first one back to stock, deleting the deal releases the second
        $this->put("/deals/{$d->id}", $this->dealData(['status' => 'منفذة', 'pay_method' => 'كاش', 'chassis' => 'CAAA21BZ3VC999001', 'motor' => '']))->assertSessionHasNoErrors();
        $this->assertSame(['in_stock', null, null], [$unit->fresh()->status, $unit->fresh()->deal_id, $unit->fresh()->sold_at]);
        $this->assertSame('sold', $spare->fresh()->status);
        $this->delete("/deals/{$d->id}")->assertRedirect();
        $this->assertSame(['in_stock', null], [$spare->fresh()->status, $spare->fresh()->deal_id]);

        // a chassis that isn't in stock is still saved, with a note
        [, $r] = $this->deal($this->customer('بره المخزن'), ['chassis' => 'NOTINSTOCK1']);
        $r->assertSessionHas('success', fn ($m) => str_contains($m, 'مش موجود في المخزن'));
    }

    public function test_entity_deals_stay_reserved_until_delivered_and_a_lost_deal_frees_the_vehicle(): void
    {
        $unit = Vehicle::create(['chassis' => 'CH-ENTITY-1', 'status' => 'in_stock', 'cost_price' => 0, 'transport_cost' => 0, 'other_cost' => 0]);
        $c = $this->customer('عميل أمان');
        [$d] = $this->deal($c, ['finance_entity' => 'أمان', 'stage' => 'قيد المراجعة', 'chassis' => 'CH-ENTITY-1']);
        $this->assertSame('reserved', $unit->fresh()->status, 'still under review at the entity');

        // the entity's transfer executes the deal and sells the vehicle
        $this->post("/deals/{$d->id}/entity", ['entity_collected_at' => today()->toDateString(), 'entity_channel' => 'تحويل بنكي'])->assertSessionHasNoErrors();
        $this->assertSame('منفذة', $d->fresh()->status);
        $this->assertSame('sold', $unit->fresh()->status);

        // delivered stage alone also counts as sold; lost frees it
        $unit2 = Vehicle::create(['chassis' => 'CH-ENTITY-2', 'status' => 'in_stock', 'cost_price' => 0, 'transport_cost' => 0, 'other_cost' => 0]);
        [$d2] = $this->deal($this->customer('عميل ريفي'), ['finance_entity' => 'ريفي', 'stage' => 'تم التسليم', 'chassis' => 'CH-ENTITY-2']);
        $this->assertSame('sold', $unit2->fresh()->status);
        $this->put("/deals/{$d2->id}", $this->dealData(['finance_entity' => 'ريفي', 'status' => 'مفقودة', 'chassis' => 'CH-ENTITY-2']))->assertSessionHasNoErrors();
        $this->assertSame(['in_stock', null], [$unit2->fresh()->status, $unit2->fresh()->deal_id]);

        // a direct company installment running = delivered
        $unit3 = Vehicle::create(['chassis' => 'CH-DIRECT-3', 'status' => 'in_stock', 'cost_price' => 0, 'transport_cost' => 0, 'other_cost' => 0]);
        $this->deal($this->customer('مباشر'), ['chassis' => 'CH-DIRECT-3']);
        $this->assertSame('sold', $unit3->fresh()->status);
    }
}
