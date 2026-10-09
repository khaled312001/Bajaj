<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DocumentLog;
use App\Models\Lead;
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
        $this->get('/documents/new/invoice')->assertNotFound();  // invoices stay admin-only even with legal_documents granted
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

    public function test_whatsapp_slot_checked_against_any_existing_number(): void
    {
        // existing customer's NON-primary number (alt_phone) must still be caught by a NEW customer's 3rd slot (whatsapp)
        Customer::create(['name' => 'موجود', 'phone' => '01055556666', 'alt_phone' => '01022223333', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->actingAs($this->admin);
        $this->post('/customers', ['name' => 'جديد', 'phone' => '01077778888', 'whatsapp' => '01022223333', 'status' => 'مفتوحة'])
            ->assertSessionHasErrors('whatsapp');
        $this->assertDatabaseMissing('customers', ['phone' => '01077778888']);
        $this->getJson('/customers/check-phone?phone=01022223333')->assertJson(['exists' => true]);
        $this->post('/customers', ['name' => 'جديد', 'phone' => '01077778888', 'whatsapp' => '01099998888', 'status' => 'مفتوحة'])->assertRedirect();
    }

    public function test_customer_form_has_cascading_governorate_center_selects(): void
    {
        $this->actingAs($this->admin);
        $this->get('/customers/create')->assertOk()
            ->assertSee('geo-gov', false)->assertSee('geo-center', false)
            ->assertSee('window.EGYPT_GEO', false)->assertSee('نجع حمادي');
    }

    public function test_customer_can_have_multiple_vehicles_newest_first(): void
    {
        $this->actingAs($this->admin);
        $this->post('/customers', ['name' => 'عميل مركبات', 'phone' => '01066667777', 'status' => 'مفتوحة', 'interest' => 'Qute'])->assertRedirect();
        $c = Customer::where('phone', '01066667777')->first();
        $this->assertDatabaseHas('customer_vehicles', ['customer_id' => $c->id, 'vehicle' => 'Qute']);

        $this->post("/customers/{$c->id}/vehicles", ['vehicle' => 'Boxer 5G', 'notes' => 'مهتم جدًا'])->assertRedirect();
        $c->refresh();
        $this->assertSame('Boxer 5G', $c->interest); // primary interest mirrors the newest vehicle
        $vehicles = $c->vehicles()->pluck('vehicle')->all();
        $this->assertSame(['Boxer 5G', 'Qute'], $vehicles); // newest first

        $resp = $this->get("/customers/{$c->id}");
        $resp->assertOk()->assertSeeInOrder(['Boxer 5G', 'Qute']);
    }

    public function test_per_employee_custom_permission_override(): void
    {
        // the agent's role profile normally has no 'reports' permission
        $this->actingAs($this->agent)->get('/reports')->assertForbidden();

        $this->actingAs($this->admin)->put("/users/{$this->agent->id}", [
            'name' => $this->agent->name, 'username' => $this->agent->username, 'role' => 'agent',
            'is_active' => '1', 'custom_perms' => '1',
            'perms' => ['customers' => ['view' => 1], 'reports' => ['view' => 1]],
        ])->assertRedirect();
        Permissions::flush();
        $this->agent->refresh();
        $this->assertSame(['customers' => ['view'], 'reports' => ['view']], $this->agent->permissions_override);

        $this->actingAs($this->agent->fresh())->get('/reports')->assertOk();
        $this->actingAs($this->agent->fresh())->get('/followups')->assertForbidden(); // not in the override, even though the role profile grants it

        // turning custom_perms off reverts to the role profile
        $this->actingAs($this->admin)->put("/users/{$this->agent->id}", [
            'name' => $this->agent->name, 'username' => $this->agent->username, 'role' => 'agent', 'is_active' => '1',
        ])->assertRedirect();
        Permissions::flush();
        $this->assertNull($this->agent->fresh()->permissions_override);
        $this->actingAs($this->agent->fresh())->get('/followups')->assertOk();
    }

    public function test_public_lead_form_capture_assign_and_convert_flow(): void
    {
        // anonymous visitor submits the public form
        $this->get('/lead')->assertOk()->assertSee('geo-gov', false);
        $this->post('/lead', ['name' => 'ليد تجريبي', 'phone' => '01033334444', 'vehicle' => 'Qute', 'governorate' => 'قنا', 'district' => 'قنا'])
            ->assertRedirect(route('lead.public'));
        $lead = Lead::where('phone', '01033334444')->first();
        $this->assertNotNull($lead);
        $this->assertSame('new', $lead->status);

        // agent cannot see the admin-only inbox or convert an unassigned lead
        $this->actingAs($this->agent)->get('/leads')->assertForbidden();
        $this->actingAs($this->agent)->get("/leads/{$lead->id}/convert")->assertForbidden();

        // admin bulk-assigns it to the agent
        $this->actingAs($this->admin)->post('/leads/assign', ['ids' => [$lead->id], 'assigned_to' => $this->agent->id])->assertRedirect();
        $lead->refresh();
        $this->assertSame('assigned', $lead->status);
        $this->assertSame($this->agent->id, $lead->assigned_to);

        // the assigned agent converts it into a real customer
        $this->actingAs($this->agent)->get("/leads/{$lead->id}/convert")
            ->assertRedirect(route('customers.create', ['phone' => $lead->phone, 'name' => $lead->name, 'interest' => $lead->vehicle, 'governorate' => $lead->governorate, 'district' => $lead->district, 'address' => null, 'lead_id' => $lead->id]));
        $this->actingAs($this->agent)->post('/customers', ['name' => $lead->name, 'phone' => $lead->phone, 'interest' => $lead->vehicle, 'status' => 'مفتوحة', 'lead_id' => $lead->id])->assertRedirect();
        $lead->refresh();
        $this->assertSame('converted', $lead->status);
        $this->assertNotNull($lead->customer_id);
    }

    public function test_stale_followup_allows_takeover_request_subject_to_admin_approval(): void
    {
        $other = User::factory()->create(['username' => 'ag3', 'role' => 'agent', 'password' => 'Agent12345', 'must_change_password' => false]);
        $c = Customer::create(['name' => 'عميل متأخر', 'phone' => '01022221111', 'status' => 'مفتوحة', 'created_by' => $this->agent->id, 'assigned_to' => $this->agent->id]);
        \App\Models\Followup::create(['customer_id' => $c->id, 'reason' => 'متابعة', 'due_date' => today()->subDays(3), 'assigned_to' => $this->agent->id, 'created_by' => $this->agent->id]);

        \App\Support\Settings::set('agent_scope', 'all'); // colleague needs to be able to see the customer at all
        $this->actingAs($other)->post("/customers/{$c->id}/reassignment-requests", ['reason' => 'العميل ينتظر رد'])->assertRedirect();
        $this->assertDatabaseHas('reassignment_requests', ['customer_id' => $c->id, 'requested_by' => $other->id, 'status' => 'pending']);

        // a second request from the same employee while one is pending is rejected
        $this->actingAs($other)->post("/customers/{$c->id}/reassignment-requests", [])->assertStatus(422);

        $this->actingAs($this->agent)->get('/reassignment-requests')->assertForbidden();
        $req = \App\Models\ReassignmentRequest::first();
        $this->actingAs($this->admin)->post("/reassignment-requests/{$req->id}/approve")->assertRedirect();

        $c->refresh();
        $this->assertSame($other->id, $c->assigned_to);
        $this->assertSame('approved', $req->fresh()->status);
    }

    public function test_dashboard_shows_once_per_day_followups_notice(): void
    {
        $c = Customer::create(['name' => 'عميل اليوم', 'phone' => '01099990000', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        \App\Models\Followup::create(['customer_id' => $c->id, 'reason' => 'اليوم', 'due_date' => today(), 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id]);
        $this->actingAs($this->admin);
        $this->get('/dashboard')->assertOk()->assertSee('متابعة مستحقة اليوم');
        $this->get('/dashboard')->assertOk()->assertDontSee('متابعة مستحقة اليوم'); // only once per calendar day
    }

    public function test_followups_shown_newest_first_on_customer_page(): void
    {
        $this->actingAs($this->admin);
        $c = Customer::create(['name' => 'عميل متابعات', 'phone' => '01055554444', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $old = \App\Models\Followup::create(['customer_id' => $c->id, 'reason' => 'قديمة', 'due_date' => today()->addDays(5), 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id]);
        $old->forceFill(['created_at' => now()->subDays(3)])->save();
        $new = \App\Models\Followup::create(['customer_id' => $c->id, 'reason' => 'جديدة', 'due_date' => today()->addDay(), 'assigned_to' => $this->admin->id, 'created_by' => $this->admin->id]);
        $this->get("/customers/{$c->id}")->assertOk()->assertSeeInOrder(['جديدة', 'قديمة']);
    }

    public function test_chat_start_send_text_and_customer_link_messages(): void
    {
        $conversation = \App\Models\Conversation::between($this->admin, $this->agent);

        $this->actingAs($this->admin)->get("/chat/{$conversation->id}")->assertOk();

        $resp1 = $this->actingAs($this->admin)
            ->postJson("/chat/{$conversation->id}/messages", ['body' => 'مرحباً، كيف الحال؟'])
            ->assertOk();
        $this->assertGreaterThan(0, $resp1->json('message.id'));
        $this->assertDatabaseHas('chat_messages', ['conversation_id' => $conversation->id, 'sender_id' => $this->admin->id, 'type' => 'text', 'body' => 'مرحباً، كيف الحال؟']);

        $c = Customer::create(['name' => 'عميل للمشاركة', 'phone' => '01066665555', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $resp = $this->actingAs($this->admin)->postJson("/chat/{$conversation->id}/messages", ['customer_id' => $c->id]);
        $resp->assertOk();
        $this->assertDatabaseHas('chat_messages', ['conversation_id' => $conversation->id, 'type' => 'customer_link', 'customer_id' => $c->id]);
        $this->assertStringContainsString($c->name, $resp->json('message.html'));

        // an empty message (no body, no customer, no audio) is rejected
        $this->actingAs($this->admin)->postJson("/chat/{$conversation->id}/messages", [])->assertStatus(422);
    }

    public function test_chat_unread_count_and_polling_marks_messages_read(): void
    {
        $conversation = \App\Models\Conversation::between($this->admin, $this->agent);
        $this->actingAs($this->admin)->postJson("/chat/{$conversation->id}/messages", ['body' => 'هل يمكنك المتابعة؟'])->assertOk();

        $this->actingAs($this->agent)->getJson('/chat/unread-count')->assertOk()->assertJson(['count' => 1]);

        // opening the thread (show) marks it read
        $this->actingAs($this->agent)->get("/chat/{$conversation->id}")->assertOk();
        $this->actingAs($this->agent)->getJson('/chat/unread-count')->assertOk()->assertJson(['count' => 0]);

        // a second message is only marked read once the recipient polls/opens it
        $this->actingAs($this->admin)->postJson("/chat/{$conversation->id}/messages", ['body' => 'رسالة ثانية'])->assertOk();
        $this->actingAs($this->agent)->getJson('/chat/unread-count')->assertOk()->assertJson(['count' => 1]);
        $this->actingAs($this->agent)->getJson("/chat/{$conversation->id}/messages?after=0")->assertOk();
        $this->actingAs($this->agent)->getJson('/chat/unread-count')->assertOk()->assertJson(['count' => 0]);
    }

    public function test_chat_non_participant_cannot_view_send_or_stream_voice(): void
    {
        $outsider = User::factory()->create(['username' => 'ag3', 'role' => 'agent', 'password' => 'Agent12345', 'must_change_password' => false]);
        $conversation = \App\Models\Conversation::between($this->admin, $this->agent);

        $this->actingAs($outsider)->get("/chat/{$conversation->id}")->assertForbidden();
        $this->actingAs($outsider)->getJson("/chat/{$conversation->id}/messages")->assertForbidden();
        $this->actingAs($outsider)->postJson("/chat/{$conversation->id}/messages", ['body' => 'تطفل'])->assertForbidden();

        $voiceMessage = $conversation->messages()->create(['sender_id' => $this->admin->id, 'type' => 'voice', 'voice_path' => 'chat-voice/does-not-matter.webm']);
        $this->actingAs($outsider)->get(route('chat.voice', $voiceMessage))->assertForbidden();

        // starting a conversation with yourself is rejected
        $this->actingAs($this->admin)->post('/chat/start', ['user_id' => $this->admin->id])->assertStatus(422);
    }

    public function test_backup_restore_reverts_data_and_takes_a_pre_restore_safety_backup(): void
    {
        $c = Customer::create(['name' => 'عميل قبل الاسترجاع', 'phone' => '01033332222', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);
        $this->actingAs($this->admin)->post('/backups')->assertRedirect();
        $b = \App\Models\Backup::first();
        $this->assertSame('ok', $b->status);

        // mutate data after the backup was taken
        $c->update(['name' => 'اسم تم تغييره بعد النسخة']);
        $newCustomer = Customer::create(['name' => 'عميل لم يكن موجوداً وقت النسخة', 'phone' => '01044443333', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);

        // wrong confirmation phrase is rejected, no data change
        $this->actingAs($this->admin)->post("/backups/{$b->id}/restore", ['confirm' => 'wrong'])->assertSessionHasErrors('confirm');
        $this->assertSame('اسم تم تغييره بعد النسخة', $c->fresh()->name);

        // agent cannot restore at all
        $this->actingAs($this->agent)->post("/backups/{$b->id}/restore", ['confirm' => 'استرجاع'])->assertForbidden();

        // correct restore reverts to the backed-up state
        $this->actingAs($this->admin)->post("/backups/{$b->id}/restore", ['confirm' => 'استرجاع'])->assertRedirect();
        $this->assertSame('عميل قبل الاسترجاع', $c->fresh()->name);
        $this->assertNull(Customer::find($newCustomer->id));

        // a pre-restore safety backup of the state right before the restore was taken automatically
        $this->assertSame(1, \App\Models\Backup::where('kind', 'pre_restore')->count());
        $this->assertDatabaseHas('backups', ['kind' => 'pre_restore', 'status' => 'ok']);

        @unlink($b->path());
        foreach (\App\Models\Backup::where('kind', 'pre_restore')->get() as $pb) {
            @unlink($pb->path());
        }
    }

    public function test_chat_customer_search_respects_visibility_scope(): void
    {
        \App\Support\Settings::set('agent_scope', 'own');
        $own = Customer::create(['name' => 'عميل الموظف الخاص', 'phone' => '01077778888', 'status' => 'مفتوحة', 'created_by' => $this->agent->id, 'assigned_to' => $this->agent->id]);
        $other = Customer::create(['name' => 'عميل غير مرئي', 'phone' => '01077779999', 'status' => 'مفتوحة', 'created_by' => $this->admin->id, 'assigned_to' => $this->admin->id]);

        $resp = $this->actingAs($this->agent)->getJson('/chat/search-customers?q=' . urlencode('عميل'))->assertOk();
        $names = collect($resp->json('customers'))->pluck('name');
        $this->assertTrue($names->contains('عميل الموظف الخاص'));
        $this->assertFalse($names->contains('عميل غير مرئي'));
    }
}
