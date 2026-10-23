<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DocumentLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DocumentService;
use App\Services\PdfService;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealerBatchTest extends TestCase
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
        foreach (range(1, 6) as $i) {
            Vehicle::create(['chassis' => "CAAA21BZ{$i}VC25708{$i}", 'motor' => "TE7132{$i}", 'type' => 'بجاج بوكسر 150 سي سي', 'model_year' => '2027',
                'color' => $i % 2 ? 'ابيض' : 'اسود', 'status' => 'in_stock', 'arrived_at' => '2026-10-01']);
        }
    }

    private function dealer(): Customer
    {
        $this->post('/customers', ['name' => 'علي محمد', 'phone' => '01025226383', 'status' => 'منفذة', 'is_dealer' => 1, 'shop_name' => 'معرض ابوعبده - القصير'])
            ->assertSessionHasNoErrors();

        return Customer::where('phone', '01025226383')->first();
    }

    private function batch(Customer $c, array $o = [])
    {
        return $this->post("/customers/{$c->id}/deals", $o + ['vehicle' => '', 'pay_method' => 'كاش', 'status' => 'منفذة', 'chassis' => 'CAAA21BZ1VC257081',
            'units' => "CAAA21BZ2VC257082\ncaaa21bz3vc257083 | TE71323\n\nCAAA21BZ4VC257084, TE71324\nTE71325", 'total_price' => 250000, 'down_payment' => 100000, 'sale_date' => '2026-10-23']);
    }

    public function test_a_dealer_takes_a_batch_and_every_chassis_leaves_stock_sold_in_his_name(): void
    {
        $c = $this->dealer();
        $this->assertTrue($c->is_dealer);
        $this->assertSame('علي محمد (معرض ابوعبده - القصير)', $c->display_name);
        $this->get('/customers?kind=dealer')->assertSee('علي محمد')->assertSee('تاجر');
        $this->get('/customers?kind=customer')->assertDontSee('01025226383');

        $this->batch($c)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '5 مكن اتسجلوا مباع في المخزن باسم علي محمد (معرض ابوعبده - القصير)'));
        $deal = $c->deals()->first();
        $this->assertSame(5, $deal->quantity());
        $this->assertSame('بجاج بوكسر 150 سي سي', $deal->vehicle, 'the type comes from stock');
        $this->assertEquals(150000, $deal->balance);
        $sold = Vehicle::where('deal_id', $deal->id)->get();
        $this->assertCount(5, $sold);
        $this->assertTrue($sold->every(fn ($v) => $v->status === 'sold' && (float) $v->sale_price === 50000.0 && $v->sold_at->toDateString() === '2026-10-23'));
        $this->assertSame('in_stock', Vehicle::where('chassis', 'CAAA21BZ6VC257086')->value('status'));
        $this->get('/vehicles?status=sold')->assertSee('علي محمد (معرض ابوعبده - القصير)');

        // his file: a dealer badge, the batch with every chassis, and his wholesale account
        $this->get("/customers/{$c->id}")->assertOk()->assertSee('تاجر — معرض ابوعبده - القصير')->assertSee('5 مكن')
            ->assertSee('CAAA21BZ5VC257085')->assertSee('صفقات التاجر')->assertSee('إقرار استلام كمية')->assertSee('150,000.00');

        // he pays against the deal like any customer
        $this->post("/deals/{$deal->id}/payments", ['amount' => 50000, 'paid_on' => today()->toDateString(), 'method' => 'نقدي'])->assertSessionHasNoErrors();
        $this->assertEquals(100000, $deal->fresh()->balance);

        // a chassis taken off the batch goes back to stock
        $this->put("/deals/{$deal->id}", ['pay_method' => 'كاش', 'status' => 'منفذة', 'chassis' => 'CAAA21BZ1VC257081', 'units' => "CAAA21BZ2VC257082\nCAAA21BZ3VC257083",
            'total_price' => 150000, 'down_payment' => 100000])->assertSessionHasNoErrors();
        $this->assertSame(3, Vehicle::where('deal_id', $deal->id)->where('status', 'sold')->count());
        $this->assertSame('in_stock', Vehicle::where('chassis', 'CAAA21BZ4VC257084')->value('status'));
    }

    public function test_a_chassis_in_his_batch_cannot_be_sold_again(): void
    {
        $this->batch($this->dealer());
        $other = Customer::create(['name' => 'عميل تاني', 'phone' => '01011112222', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->post("/customers/{$other->id}/deals", ['pay_method' => 'كاش', 'status' => 'مفتوحة', 'chassis' => 'CAAA21BZ3VC257083', 'total_price' => 50000])
            ->assertSessionHasErrors(['chassis' => 'رقم الشاسيه CAAA21BZ3VC257083 مباع في المخزن للعميل علي محمد (' . Customer::where('phone', '01025226383')->value('code') . ')']);
        $this->post("/customers/{$other->id}/deals", ['pay_method' => 'كاش', 'status' => 'مفتوحة', 'units' => "CAAA21BZ6VC257086\nTE71322", 'total_price' => 50000])
            ->assertSessionHasErrors('chassis');
    }

    public function test_the_dealer_receipt_is_in_his_own_name_with_paid_remaining_and_debt(): void
    {
        $c = $this->dealer();
        $this->batch($c);
        $deal = $c->deals()->first();

        $form = $this->get("/documents/new/bulk_receipt?deal={$deal->id}")->assertOk();
        $form->assertSee('علي محمد (معرض ابوعبده - القصير)')->assertSee('CAAA21BZ4VC257084 | TE71324')->assertSee('name="remaining"', false)->assertSee('name="debt"', false);
        $values = $form->viewData('values');
        $this->assertCount(5, explode("\n", $values['items']));
        $this->assertSame('بجاج بوكسر 150 سي سي | 2027 | اسود | CAAA21BZ2VC257082 | TE71322', explode("\n", $values['items'])[1]);
        $this->assertEquals(150000, $values['remaining']);
        $this->assertEquals(150000, $values['debt']);

        $this->post('/documents/new/bulk_receipt', ['deal_id' => $deal->id, 'customer_name' => $values['customer_name'], 'address' => 'القصير - البحر الاحمر', 'items' => $values['items'],
            'to_name' => '', 'price' => 250000, 'paid' => 100000, 'remaining' => 150000, 'debt' => 150000, 'delivered_by' => 'انور ربيع سعيد', 'phone' => '01025226383'])->assertRedirect();
        $log = DocumentLog::where('type', 'bulk_receipt')->latest('id')->first();
        $html = view('pdf.document', ['type' => 'bulk_receipt', 'def' => DocumentService::type('bulk_receipt'), 'd' => $log->data(), 'deal' => null,
            'docNo' => 'BK-1', 'docDate' => '2026/10/23', 'zarad' => true, 'company' => PdfService::company(), 'title' => 'x', 'logo' => ''])->render();

        $this->assertStringContainsString('وأني استلمت جميع الملحقات الخاصة بها.</p>', $html);
        foreach (['لتوصيلها', 'على سبيل الأمانة', 'خائناً للأمانة'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
        foreach (['250,000 جنيه', 'المدفوع', '100,000 جنيه', 'الباقي', '150,000 جنيه', 'إجمالي المديونية المستحقة عليه حتى تاريخه', 'وهذا إقرار مني بذلك.', 'المستلم : علي محمد (معرض ابوعبده - القصير)', 'البصمة'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        // the thumbprint sits beside the date, in the same row
        $this->assertMatchesRegularExpression('/التاريخ :<br>2026\/10\/23<\/td>\s*<td[^>]*>البصمة :/u', $html);
        $this->get("/documents/{$log->id}/pdf")->assertOk();

        // a payment receipt shows what is left on the deal and his whole debt
        $this->post("/deals/{$deal->id}/payments", ['amount' => 50000, 'paid_on' => today()->toDateString(), 'method' => 'نقدي']);
        $p = $deal->payments()->first();
        $r = $this->get("/documents/new/receipt?payment={$p->id}")->assertOk()->viewData('values');
        $this->assertEquals(100000, $r['remaining']);
        $this->assertEquals(100000, $r['debt']);
    }
}
