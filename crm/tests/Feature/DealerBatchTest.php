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
        foreach (['المدفوع', '100,000 جنيه', 'الباقي', '150,000 جنيه', 'إجمالي المديونية المستحقة عليه حتى تاريخه', 'وهذا إقرار مني بذلك.', 'المستلم : علي محمد (معرض ابوعبده - القصير)', 'البصمة'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        // the deal total stays in his file, never on the receipt
        $this->assertStringNotContainsString('إجمالي الصفقة', $html);
        $this->assertStringNotContainsString('250,000', $html);
        // the recipient block on the right: name, mobile, signature, date and thumbprint under each other, no dashed box
        $this->assertMatchesRegularExpression('/المستلم : علي محمد.*الموبايل : 01025226383.*التوقيع : \.+.*التاريخ : 2026\/10\/23.*البصمة :.*تم التسليم بمعرفة :/su', $html);
        $this->assertStringNotContainsString('dashed', $html);
        $this->get("/documents/{$log->id}/pdf")->assertOk();

        // a payment receipt shows what is left on the deal and his whole debt
        $this->post("/deals/{$deal->id}/payments", ['amount' => 50000, 'paid_on' => today()->toDateString(), 'method' => 'نقدي']);
        $p = $deal->payments()->first();
        $r = $this->get("/documents/new/receipt?payment={$p->id}")->assertOk()->viewData('values');
        $this->assertEquals(100000, $r['remaining']);
        $this->assertEquals(100000, $r['debt']);
    }

    public function test_vehicle_names_with_arabic_letters_print_whole_on_the_receipt(): void
    {
        // a line split that is not unicode-aware broke "موتوسيكل" (its م ends in the byte 0x85) into an empty row
        $items = "موتوسيكل بجاج بوكسر كلاسيك 2027 | 2027 | ابيض | CH1 | TE1\r\nموتوسيكل بجاج بوكسر كلاسيك 2027 | 2027 | احمر | CH2 | TE2";
        $html = view('pdf.document', ['type' => 'bulk_receipt', 'def' => DocumentService::type('bulk_receipt'), 'd' => ['customer_name' => 'x', 'items' => $items, 'to_name' => ''], 'deal' => null,
            'docNo' => 'BK-1', 'docDate' => '2026/10/23', 'zarad' => true, 'company' => PdfService::company(), 'title' => 'x', 'logo' => ''])->render();
        preg_match('/<tbody>(.*?)<\/tbody>/su', $html, $m);
        $this->assertSame(2, substr_count($m[1], '<tr'));
        $this->assertSame(2, substr_count($m[1], '>موتوسيكل بجاج بوكسر كلاسيك 2027<'));
        $this->assertStringContainsString('>احمر<', $m[1]);
    }

    public function test_editing_a_document_keeps_its_number(): void
    {
        $this->post('/documents/new/bulk_receipt', ['customer_name' => 'تاجر', 'items' => 'بوكسر | 2027 | ابيض | CH1 | TE1', 'paid' => 1000])->assertRedirect();
        $log = DocumentLog::where('type', 'bulk_receipt')->latest('id')->first();
        $count = DocumentLog::count();
        $this->get("/documents/{$log->id}/edit")->assertOk()->assertSee('حفظ التعديل بنفس الرقم')->assertSee(DocumentService::docNo('bulk_receipt', $log->serial));
        $this->put("/documents/{$log->id}", ['customer_name' => 'تاجر معدل', 'items' => 'بوكسر | 2027 | ابيض | CH1 | TE1', 'paid' => 2000])->assertRedirect("/documents/{$log->id}");
        $this->assertSame($count, DocumentLog::count(), 'no new document');
        $fresh = $log->fresh();
        $this->assertSame($log->serial, $fresh->serial);
        $this->assertSame('تاجر معدل', $fresh->customer_name);
        $this->assertEquals(2000, $fresh->data()['paid']);
    }

    public function test_only_the_manager_marks_a_dealer_and_a_dealer_skips_the_sales_fields(): void
    {
        $c = $this->dealer();
        $this->get('/customers/create')->assertSee('name="is_dealer"', false)->assertSee('cust-only');
        // no status / requested vehicle needed for a dealer
        $this->post('/customers', ['name' => 'تاجر 2', 'phone' => '01033334444', 'is_dealer' => 1, 'interest' => 'بوكسر'])->assertSessionHasNoErrors();
        $d2 = Customer::where('phone', '01033334444')->first();
        $this->assertTrue($d2->is_dealer);
        $this->assertSame(0, $d2->vehicles()->count());
        $this->get("/customers/{$c->id}")->assertSee('المعرض / المحل')->assertDontSee('جدية العميل')->assertDontSee('المركبات المطلوبة (0)');
        $this->get('/customers?kind=dealer')->assertSee('التجار');

        $emp = User::where('role', '!=', 'admin')->first() ?? User::factory()->create(['role' => 'employee']);
        $emp->forceFill(['must_change_password' => false, 'is_active' => true])->save();
        Permissions::flush();
        $this->actingAs($emp);
        $this->get('/customers/create')->assertDontSee('name="is_dealer"', false);
        $this->post('/customers', ['name' => 'مش تاجر', 'phone' => '01055556666', 'status' => 'مفتوحة', 'is_dealer' => 1, 'shop_name' => 'معرض'])->assertSessionHasNoErrors();
        $this->assertFalse(Customer::where('phone', '01055556666')->first()->is_dealer, 'an employee cannot mark a dealer');
    }

    public function test_a_customer_can_get_another_deal_from_the_deals_list(): void
    {
        $c = $this->dealer();
        $this->batch($c);
        $this->get("/customers/{$c->id}")->assertSee('إضافة صفقة جديدة')->assertSee("/customers/{$c->id}/deals/create", false);
        $this->batch($c, ['chassis' => 'CAAA21BZ6VC257086', 'units' => '', 'total_price' => 50000, 'down_payment' => 0])->assertSessionHasNoErrors();
        $this->assertSame(2, $c->deals()->count());
        $this->get("/customers/{$c->id}")->assertSee('الصفقات (2)');
    }

    public function test_the_calculation_made_while_adding_a_vehicle_is_saved_with_it(): void
    {
        $c = Customer::create(['name' => 'عميل', 'phone' => '01077778888', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->get("/customers/{$c->id}")->assertSee('data-qc-into="#m-vehicle-add form"', false);
        $this->post("/customers/{$c->id}/vehicles", ['vehicle' => 'بوكسر', 'calc_price' => 58500, 'calc_down' => 30500, 'calc_months' => 18, 'calc_plan' => 'أمان'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'واتحفظ معاها حساب القسط'));
        $v = $c->vehicles()->latest('id')->first();
        $this->assertEquals(58500, $v->calc['price']);
        $this->assertSame(18, (int) $v->calc['months']);

        // and on the new-customer form
        $this->get('/customers/create')->assertSee('data-qc-into="#customer-form"', false);
        $this->post('/customers', ['name' => 'عميل جديد', 'phone' => '01099990000', 'status' => 'مفتوحة', 'interest' => 'بوكسر', 'calc_price' => 40000, 'calc_down' => 10000, 'calc_months' => 12, 'calc_plan' => 'أمان'])
            ->assertSessionHasNoErrors();
        $n = Customer::where('phone', '01099990000')->first();
        $this->assertEquals(40000, $n->vehicles()->first()->calc['price']);
    }

    public function test_the_address_is_not_repeated_on_documents(): void
    {
        $c = new Customer(['address' => 'القصير - البحر الاحمر', 'district' => 'القصير', 'governorate' => 'البحر الاحمر']);
        $this->assertSame('القصير - البحر الاحمر', DocumentService::fullAddress($c));
        $c->address = 'شارع المحطة';
        $this->assertSame('شارع المحطة - القصير - البحر الاحمر', DocumentService::fullAddress($c));
    }
}
