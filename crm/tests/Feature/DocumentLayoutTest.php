<?php

namespace Tests\Feature;

use App\Services\DocumentService;
use App\Services\PdfService;
use App\Support\TrafficLayout;
use Tests\TestCase;

class DocumentLayoutTest extends TestCase
{
    private array $d = [
        'customer_name' => 'توفيق فاروق توفيق حامد', 'nat_id' => '29001223100134', 'address' => 'قفط - قنا',
        'product' => 'تروسيكل محلي ليفان 200 سي سي', 'model' => '2027', 'color' => 'ازرق', 'chassis' => 'CAAHCMC07VC022270',
        'motor' => '163FML*V1006294', 'traffic_dept' => 'قنا', 'traffic_code' => '214', 'mobaya_no' => '55',
    ];

    private function find(array $items, string $text): ?array
    {
        foreach ($items as $it) {
            if ($it[5] === $text) {
                return $it;
            }
        }

        return null;
    }

    public function test_traffic_letters_keep_the_approved_positions(): void
    {
        $items = TrafficLayout::items('mobaya', $this->d, '2026/09/14');

        $this->assertSame('0000055', $this->find($items, '0000055')[5] ?? null, 'serial padded to 7 digits');
        $date = $this->find($items, '14-Sep-2026');
        $this->assertNotNull($date, 'date printed like the original');
        $this->assertEqualsWithDelta(70.2, $date[0], 0.01);
        $this->assertNotNull($this->find($items, '*163FML*V1006294*'));
        $chassis = $this->find($items, 'CAAHCMC07VC022270');
        $this->assertEqualsWithDelta(126.9, $chassis[0], 0.01);
        $this->assertEqualsWithDelta(157.0, $chassis[1] + $chassis[2], 0.01, 'chassis ends at the approved column');
        $this->assertNull($this->find($items, '( مـع الاحـتـفاظ بـحـق الـمـلـكـيـة )'));

        $retain = TrafficLayout::items('mobaya', $this->d + ['retain_title' => 1], '2026/09/14');
        $this->assertNotNull($this->find($retain, '( مـع الاحـتـفاظ بـحـق الـمـلـكـيـة )'));
        $this->assertEqualsWithDelta(71.8, $this->find($retain, '14-Sep-2026')[0], 0.01);

        $clearance = TrafficLayout::items('clearance', $this->d, '2026/09/14');
        $this->assertEqualsWithDelta(67.8, $this->find($clearance, '14-Sep-2026')[0], 0.01);
        $this->assertNotNull($this->find($clearance, 'وتمكين المالك من نقل ملكيتها او اتخاذ كافة الإجراءات المرورية اللازمة باسمه'));

        $renewal = TrafficLayout::items('renewal', $this->d, '2026/09/14');
        $this->assertNotNull($this->find($renewal, 'لمدة سنه'));
    }

    public function test_every_document_prints_on_a_single_page(): void
    {
        foreach (DocumentService::types() as $type => $def) {
            if ($type === 'statement') {
                continue;
            }
            $traffic = in_array($type, ['mobaya', 'renewal', 'clearance'], true);
            $pdf = PdfService::render($traffic ? 'pdf.traffic' : 'pdf.document', [
                'type' => $type, 'def' => $def, 'd' => $this->d, 'deal' => null, 'docNo' => DocumentService::docNo($type, 7),
                'docDate' => '2026/09/14', 'zarad' => true,
            ], $def['title'], false, $def['title'], $traffic ? ['paper' => true] : ['company' => true]);

            $this->assertStringStartsWith('%PDF', $pdf);
            $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), "$type must fit on one page");
        }
    }
}
