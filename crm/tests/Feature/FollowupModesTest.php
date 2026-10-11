<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Followup;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowupModesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('role', 'admin')->first();
        $this->admin->forceFill(['must_change_password' => false])->save();
        Permissions::flush();
        $this->customer = Customer::create(['name' => 'عميل متابعة', 'phone' => '01033334444', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->actingAs($this->admin);
    }

    public function test_current_follow_up_is_logged_without_scheduling(): void
    {
        $this->get('/followups')->assertOk()->assertSee('متابعة حالية')->assertSee('متابعة مجدولة');

        $this->post('/followups', ['mode' => 'now', 'customer_id' => $this->customer->id, 'reason' => 'تذكير'])
            ->assertSessionHasErrors('outcome');

        $this->post('/followups', ['mode' => 'now', 'customer_id' => $this->customer->id, 'reason' => 'تذكير', 'outcome' => 'وعد بالسداد يوم الخميس', 'due_date' => ''])
            ->assertSessionHasNoErrors();
        $done = Followup::sole();
        $this->assertSame('done', $done->status);
        $this->assertSame('وعد بالسداد يوم الخميس', $done->outcome);
        $this->assertTrue($done->due_date->isToday());
        $this->assertSame($this->admin->id, $done->completed_by);
        $this->assertSame(0, Followup::pending()->count());
    }

    public function test_current_follow_up_can_also_schedule_the_next_one(): void
    {
        $date = today()->addDays(3)->format('Y-m-d');
        $this->post('/followups', ['mode' => 'now', 'customer_id' => $this->customer->id, 'reason' => 'تذكير', 'priority' => 'high',
            'outcome' => 'طلب مهلة', 'schedule_next' => '1', 'due_date' => $date, 'due_time' => '11:30', 'notes' => 'كلمه تاني'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Followup::where('status', 'done')->count());
        $next = Followup::pending()->sole();
        $this->assertSame($date, $next->due_date->format('Y-m-d'));
        $this->assertSame('كلمه تاني', $next->notes);
        $this->assertSame('high', $next->priority);

        // asking to schedule without a date is rejected
        $this->post('/followups', ['mode' => 'now', 'customer_id' => $this->customer->id, 'reason' => 'تذكير', 'outcome' => 'x', 'schedule_next' => '1', 'due_date' => ''])
            ->assertSessionHasErrors('due_date');
    }

    public function test_scheduled_follow_up_still_works(): void
    {
        $this->post('/followups', ['mode' => 'schedule', 'customer_id' => $this->customer->id, 'reason' => 'تذكير', 'due_date' => today()->addDay()->format('Y-m-d')])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Followup::pending()->count());
        $this->assertSame(0, Followup::where('status', 'done')->count());

        $this->post('/followups', ['mode' => 'schedule', 'customer_id' => $this->customer->id, 'reason' => 'تذكير'])->assertSessionHasErrors('due_date');
    }

    public function test_clear_overdue_all_or_oldest_count(): void
    {
        $make = fn (int $daysAgo, array $o = []) => Followup::create($o + ['customer_id' => $this->customer->id, 'reason' => 'تذكير',
            'due_date' => today()->subDays($daysAgo), 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id]);
        $oldest = [$make(30), $make(20)];
        $newer = [$make(10), $make(5, ['reason' => 'سداد']), $make(2)];
        $todayOne = $make(0);

        $this->get('/followups?tab=overdue')->assertOk()->assertSee('مسح المتأخرات');

        $this->post('/followups/clear-overdue', ['scope' => 'count'])->assertSessionHasErrors('count');
        $this->post('/followups/clear-overdue', ['scope' => 'count', 'count' => 2])->assertRedirect();
        foreach ($oldest as $f) {
            $this->assertSame('cancelled', $f->fresh()->status);
        }
        foreach ($newer as $f) {
            $this->assertSame('pending', $f->fresh()->status);
        }

        // filters are respected
        $this->post('/followups/clear-overdue', ['scope' => 'all', 'reason' => 'سداد']);
        $this->assertSame('cancelled', $newer[1]->fresh()->status);
        $this->assertSame('pending', $newer[0]->fresh()->status);

        $this->post('/followups/clear-overdue', ['scope' => 'all']);
        $this->assertSame(0, Followup::pending()->whereDate('due_date', '<', today())->count());
        $this->assertSame('pending', $todayOne->fresh()->status, 'today\'s follow-ups are not overdue');
    }
}
