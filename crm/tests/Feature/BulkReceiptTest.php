<?php

namespace Tests\Feature;

use App\Models\DocumentLog;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\PdfService;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkReceiptTest extends TestCase
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
    }

    private function html(array $d): string
    {
        return view('pdf.document', ['type' => 'bulk_receipt', 'def' => DocumentService::type('bulk_receipt'), 'd' => $d, 'deal' => null,
            'docNo' => 'BK-1', 'docDate' => '2026/10/20', 'zarad' => true, 'company' => PdfService::company(), 'title' => 'x', 'logo' => ''])->render();
    }

    public function test_quantity_receipt_is_a_trust_receipt_titled_just_receipt(): void
    {
        $this->actingAs($this->admin);
        // listed under its full name, the form asks who it is delivered to and no purchase date
        $this->get('/documents')->assertOk()->assertSee('إقرار استلام كمية');
        $this->get('/documents/new/bulk_receipt')->assertOk()->assertSee('إقرار استلام كمية')
            ->assertSee('name="to_name"', false)->assertSee('name="to_nat"', false)->assertSee('name="to_address"', false)
            ->assertDontSee('name="purchase_date"', false);

        $items = implode("\n", array_map(fn ($i) => "بوكسر 150 | 2026 | أسود | CH{$i} | MO{$i}", range(1, 10)));
        $this->post('/documents/new/bulk_receipt', ['customer_name' => 'محمود حسن', 'nat_id' => '28805122700451', 'address' => 'قفط',
            'items' => $items, 'to_name' => 'أحمد محمد سيد', 'to_nat' => '29901012700333', 'to_address' => 'قوص',
            'delivered_by' => 'أنور', 'phone' => '01012345678'])->assertRedirect();
        $log = DocumentLog::where('type', 'bulk_receipt')->latest('id')->first();

        $html = $this->html($log->data());
        $this->assertStringContainsString('>إقرار استلام</h1>', $html);
        $this->assertStringNotContainsString('استلام كمية', $html);
        foreach (['المقيم / قفط<br>بأنني استلمت من شركة', 'لتوصيلها للسيد / <b>أحمد محمد سيد</b>', 'رقم قومي: 29901012700333 — المقيم / قوص',
            'على سبيل الأمانة', 'أكون خائناً للأمانة', 'بدون أدنى مسؤولية على الشركة', 'CH10', 'البصمة'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
        $this->assertStringNotContainsString('تاريخ الشراء', $html);
        $this->assertStringNotContainsString('ولا يحق لي', $html);

        // ten vehicles still fit on one page
        $pdf = $this->get("/documents/{$log->id}/pdf")->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_a_long_list_repeats_the_letterhead_with_the_document_number_on_the_next_page(): void
    {
        $items = implode("\n", array_map(fn ($i) => "بوكسر 150 | 2026 | أسود | CH{$i} | MO{$i}", range(1, 24)));
        $d = ['customer_name' => 'محمود', 'items' => $items, 'to_name' => 'أحمد', 'to_nat' => '1', 'to_address' => 'قوص'];
        $html = $this->html($d);
        $this->assertStringContainsString('<htmlpageheader name="zaradNext">', $html);
        $this->assertStringContainsString('رقم المستند: <span dir="ltr">BK-1</span>', $html);
        $this->assertStringContainsString('header: html_zaradNext', $html);

        $pdf = PdfService::render('pdf.document', ['type' => 'bulk_receipt', 'def' => DocumentService::type('bulk_receipt'), 'd' => $d, 'deal' => null,
            'docNo' => 'BK-1', 'docDate' => '2026/10/20', 'zarad' => true], 'x', false, 'x', ['company' => true]);
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_receipts_issued_before_the_change_reprint_unchanged(): void
    {
        $html = $this->html(['customer_name' => 'قديم', 'items' => 'بوكسر | 2025 | أحمر | C1 | M1', 'purchase_date' => '2026-09-01']);
        $this->assertStringContainsString('تاريخ الشراء / 2026/09/01', $html);
        $this->assertStringContainsString('ولا يحق لي أو للغير الرجوع على الشركة', $html);
        $this->assertStringNotContainsString('لتوصيلها للسيد', $html);
    }
}
