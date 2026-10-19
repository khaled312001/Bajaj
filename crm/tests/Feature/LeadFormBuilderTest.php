<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use App\Support\LeadForm;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadFormBuilderTest extends TestCase
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

    private function save(array $fields)
    {
        return $this->actingAs($this->admin)->put(route('leads.form.update'), [
            'lead_form_enabled' => '1', 'lead_form_title' => 'اطلب مركبتك', 'lead_form_intro' => 'مقدمة',
            'lead_form_fields' => json_encode($fields, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function test_the_untouched_form_still_works_as_before(): void
    {
        $this->get('/lead')->assertOk()->assertSee('name="name"', false)->assertSee('name="phone"', false)->assertSee('name="vehicle"', false)
            ->assertSee('geo-gov', false)->assertSee('geo-center', false)->assertSee('name="address"', false)->assertSee('العنوان بالتفصيل');

        $vehicle = Lookup::list('vehicle')[0];
        $this->post('/lead', ['name' => 'عميل عادي', 'phone' => '01011112222', 'vehicle' => $vehicle, 'governorate' => 'قنا', 'district' => 'قفط', 'address' => 'شارع المحطة'])
            ->assertRedirect(route('lead.public'));
        $this->assertDatabaseHas('leads', ['name' => 'عميل عادي', 'phone' => '01011112222', 'vehicle' => $vehicle, 'district' => 'قفط', 'address' => 'شارع المحطة', 'extra' => null]);
    }

    public function test_admin_edits_adds_removes_and_reorders_fields(): void
    {
        $fields = LeadForm::defaults();
        $byKey = collect($fields)->keyBy('key');
        $new = [
            array_merge($byKey['phone'], ['label' => 'رقم الموبايل', 'placeholder' => 'اكتب رقمك']),            // phone moved to the top and renamed
            array_merge($byKey['name'], ['label' => 'اسمك بالكامل']),
            ['key' => '', 'type' => 'select', 'label' => 'طريقة الشراء', 'required' => true, 'width' => 'half', 'options' => "كاش\nتقسيط\nكاش"],
            ['key' => '', 'type' => 'number', 'label' => 'المقدم المتاح', 'required' => false, 'width' => 'half', 'placeholder' => 'مثلاً 20000'],
            ['key' => '', 'type' => 'textarea', 'label' => 'ملاحظات', 'required' => false],
            array_merge($byKey['vehicle'], ['required' => true]),
            $byKey['governorate'], $byKey['district'],
            // address removed
        ];
        $this->save($new)->assertSessionHasNoErrors();

        $saved = LeadForm::fields();
        $this->assertSame(['phone', 'name'], array_slice(array_column($saved, 'key'), 0, 2));
        $this->assertNotContains('address', array_column($saved, 'key'));
        $buy = collect($saved)->firstWhere('label', 'طريقة الشراء');
        $this->assertSame(['كاش', 'تقسيط'], $buy['options']);
        $this->assertMatchesRegularExpression('/^f_[a-z0-9]{8}$/', $buy['key']);

        // the editor page shows the arrangement; the public form follows it
        $this->get('/leads/form')->assertOk()->assertSee('lf-list', false)->assertSee(trim(json_encode('طريقة الشراء'), '"'), false);
        auth()->logout();
        $page = $this->get('/lead')->assertOk()->assertSee('رقم الموبايل')->assertSee('اكتب رقمك', false)->assertSee('اسمك بالكامل')
            ->assertSee('طريقة الشراء')->assertSee('<option >تقسيط</option>', false)->assertSee('المقدم المتاح')
            ->assertDontSee('name="address"', false)->assertDontSee('العنوان بالتفصيل')->getContent();
        $this->assertLessThan(strpos($page, 'name="name"'), strpos($page, 'name="phone"'), 'phone comes first');
        $this->assertLessThan(strpos($page, 'name="' . $buy['key'] . '"'), strpos($page, 'name="name"'));

        // required custom field and required vehicle are enforced, with the admin's labels
        $vehicle = Lookup::list('vehicle')[0];
        $this->post('/lead', ['name' => 'سيد', 'phone' => '01033334444'])->assertSessionHasErrors([$buy['key'], 'vehicle']);
        $this->post('/lead', ['name' => 'سيد', 'phone' => '01033334444', 'vehicle' => $vehicle, $buy['key'] => 'شيك'])->assertSessionHasErrors($buy['key']);
        $this->assertSame(0, Lead::count());

        $amount = collect($saved)->firstWhere('label', 'المقدم المتاح')['key'];
        $this->post('/lead', ['name' => 'سيد', 'phone' => '01033334444', 'vehicle' => $vehicle, $buy['key'] => 'تقسيط', $amount => '20000', 'address' => 'متشال'])
            ->assertRedirect(route('lead.public'))->assertSessionHasNoErrors();
        $lead = Lead::first();
        $this->assertNull($lead->address, 'a removed field is not saved');
        $this->assertSame([['label' => 'طريقة الشراء', 'value' => 'تقسيط'], ['label' => 'المقدم المتاح', 'value' => '20000']], $lead->extra);

        // the answers show in the inbox and travel to the customer
        $this->actingAs($this->admin)->get('/leads')->assertSee('طريقة الشراء:')->assertSee('تقسيط');
        $this->get("/leads/{$lead->id}/convert")->assertRedirect();
        $this->get('/customers/create?lead_id=' . $lead->id . '&name=x&phone=01033334444')->assertSee("طريقة الشراء: تقسيط\nالمقدم المتاح: 20000", false);
        $agent = User::factory()->create(['username' => 'ag8', 'role' => 'agent', 'must_change_password' => false]);
        $this->post('/leads/assign', ['ids' => [$lead->id], 'assigned_to' => $agent->id])->assertSessionHasNoErrors();
        $this->assertStringContainsString("طريقة الشراء: تقسيط\nالمقدم المتاح: 20000", Customer::where('phone', '01033334444')->value('notes'));
    }

    public function test_name_and_phone_cannot_be_removed_and_bad_input_is_cleaned(): void
    {
        $this->save([
            ['key' => 'address', 'label' => '<b>العنوان</b>', 'required' => true],
            ['key' => 'name', 'label' => 'الاسم', 'required' => false],
            ['key' => 'evil', 'type' => 'script', 'label' => 'حقل غريب'],
        ])->assertSessionHasNoErrors();

        $fields = collect(LeadForm::fields())->keyBy('key');
        $this->assertTrue($fields->has('phone'), 'phone is put back');
        $this->assertTrue($fields['name']['required'], 'name stays required');
        $this->assertSame('العنوان', $fields['address']['label']);
        $odd = $fields->first(fn ($f) => $f['label'] === 'حقل غريب');
        $this->assertSame('text', $odd['type']);
        $this->assertStringStartsWith('f_', $odd['key']);
        $this->assertFalse($fields->has('district'));

        // a list without choices is refused
        $this->save([['key' => '', 'type' => 'select', 'label' => 'قائمة فاضية', 'options' => '']])->assertSessionHasErrors('lead_form_fields');

        // employees can't change the form
        $agent = User::factory()->create(['username' => 'ag10', 'role' => 'agent', 'must_change_password' => false]);
        $this->actingAs($agent)->put(route('leads.form.update'), ['lead_form_enabled' => '1', 'lead_form_fields' => '[]'])->assertForbidden();
    }
}
