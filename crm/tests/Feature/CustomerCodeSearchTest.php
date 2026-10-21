<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCodeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_searching_a_code_finds_that_customer_and_not_phones_sharing_its_digits(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->first();
        $admin->forceFill(['must_change_password' => false])->save();
        $agent = User::factory()->create(['username' => 'ag11', 'role' => 'agent', 'must_change_password' => false]);
        Permissions::flush();

        $target = Customer::create(['code' => '8NZ789', 'name' => 'حسين محمود', 'phone' => '01150811711', 'status' => 'مفتوحة', 'created_by' => $admin->id, 'assigned_to' => $admin->id]);
        // the digits of the code (8789) inside other customers' numbers
        $noise = Customer::create(['code' => 'REG782', 'name' => 'مؤمن محمد', 'phone' => '01151780030', 'whatsapp' => '01146878968', 'status' => 'مفتوحة', 'created_by' => $admin->id, 'assigned_to' => $admin->id]);
        foreach (range(1, 25) as $i) {
            Customer::create(['name' => "رقم فيه 8789 رقم $i", 'phone' => '0100' . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '8789', 'status' => 'مفتوحة', 'created_by' => $admin->id, 'assigned_to' => $admin->id]);
        }

        $this->assertSame([$target->id], Customer::query()->search('8NZ789')->pluck('id')->all());
        $this->assertSame([$target->id], Customer::query()->search('8nz789')->pluck('id')->all(), 'lower case too');
        $this->assertNotContains($noise->id, Customer::query()->search('8NZ7')->pluck('id')->all());

        // the list (and the top quick search) opens the file directly, for the admin and for an employee
        $this->actingAs($admin)->get('/customers?q=8NZ789')->assertRedirect(route('customers.show', $target));
        $this->actingAs($agent)->get('/customers?q=8nz789')->assertRedirect(route('customers.show', $target));
        $this->getJson('/customers/lookup?q=8NZ789')->assertJsonCount(1)->assertJsonFragment(['code' => '8NZ789']);

        // numbers still search phones, names still search names
        $this->actingAs($admin)->get('/customers?q=8789')->assertOk()->assertSee('رقم فيه 8789 رقم 1')->assertSee('مؤمن محمد');
        $this->get('/customers?q=01150811711')->assertRedirect(route('customers.show', $target));
        $this->get('/customers?q=' . urlencode('حسين'))->assertOk()->assertSee('8NZ789');
    }
}
