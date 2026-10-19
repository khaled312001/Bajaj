<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatAndLeadFormTest extends TestCase
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

    public function test_chat_still_opens_after_the_other_employee_is_deleted(): void
    {
        $gone = User::factory()->create(['name' => 'موظف اتمسح', 'username' => 'gone1', 'role' => 'agent', 'must_change_password' => false]);
        $conversation = Conversation::between($this->admin, $gone);
        $conversation->messages()->create(['sender_id' => $gone->id, 'type' => 'text', 'body' => 'رسالة قديمة', 'created_at' => now()]);
        $conversation->update(['last_message_at' => now()]);
        $gone->delete();

        $this->actingAs($this->admin);
        $this->get('/chat')->assertOk()->assertSee('موظف اتمسح');
        $this->get("/chat/{$conversation->id}")->assertOk()->assertSee('رسالة قديمة');

    }

    public function test_admin_picks_which_vehicles_the_public_lead_form_offers(): void
    {
        $all = Lookup::list('vehicle');
        $this->assertGreaterThan(2, count($all));
        [$a, $b, $hidden] = [$all[0], $all[1], $all[2]];

        // nothing picked yet: every vehicle is offered
        $this->get('/lead')->assertOk()->assertSee($hidden);

        $this->actingAs($this->admin)->get('/leads')->assertOk()->assertSee(route('leads.form'));
        $this->get('/settings')->assertOk()->assertDontSee('name="lead_form_vehicles[]"', false)->assertSee(route('leads.form'));
        $this->get('/leads/form')->assertOk()->assertSee('name="lead_form_vehicles[]"', false)->assertSee('معاينة');
        $payload = ['lead_form_enabled' => '1', 'lead_form_title' => 'اطلب موتوسيكلك', 'lead_form_intro' => 'مقدمة جديدة', 'lead_form_vehicles' => [$a, $b], 'lead_form_fields' => json_encode(\App\Support\LeadForm::defaults())];
        $this->put(route('leads.form.update'), $payload)->assertSessionHasNoErrors();

        Settings::all(); // cache was cleared on save
        $this->assertSame([$a, $b], \App\Http\Controllers\LeadController::publicVehicles());
        auth()->logout();
        $this->flushSession(); // the settings flash would show the thank-you screen instead of the form
        $this->get('/lead')->assertOk()->assertSee('اطلب موتوسيكلك')->assertSee('مقدمة جديدة')->assertSee($a)->assertSee($b)->assertDontSee('>' . $hidden . '<', false);

        // un-ticking everything goes back to the full list
        unset($payload['lead_form_vehicles']);
        $this->actingAs($this->admin)->put(route('leads.form.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame($all, \App\Http\Controllers\LeadController::publicVehicles());

        // the general settings still save without the lead form fields; employees can't open the page
        $this->put(route('settings.general'), ['app_name' => 'بجاج قنا', 'agent_scope' => 'own', 'agent_idle_minutes' => 45, 'view_alert_threshold' => 40,
            'view_block_threshold' => 90, 'plans' => [['name' => 'جدول', 'table' => '12=1.2']]])->assertSessionHasNoErrors();
        $this->assertSame('اطلب موتوسيكلك', Settings::get('lead_form_title'));
        $agent = User::factory()->create(['username' => 'ag9', 'role' => 'agent', 'must_change_password' => false]);
        $this->actingAs($agent)->get('/leads/form')->assertForbidden();
    }
}
