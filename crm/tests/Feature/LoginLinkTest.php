<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LoginLinkTest extends TestCase
{
    use RefreshDatabase;

    private function issue(string $args = ''): string
    {
        Artisan::call(trim('login:link ' . $args));
        preg_match('#/t/([A-Za-z0-9]{64})#', Artisan::output(), $m);

        return $m[1] ?? '';
    }

    public function test_token_link_logs_in_without_password_and_can_be_rotated_or_revoked(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('role', 'admin')->first();
        $admin->forceFill(['must_change_password' => false])->save();

        $token = $this->issue();
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $admin->fresh()->login_token_hash);

        $this->get('/t/' . $token)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->get(route('dashboard'))->assertOk();

        // wrong token -> 404, nobody logged in
        auth()->logout();
        $this->get('/t/' . str_repeat('a', 64))->assertNotFound();
        $this->assertGuest();

        // issuing a new link kills the old one
        $new = $this->issue($admin->username);
        $this->get('/t/' . $token)->assertNotFound();
        $this->get('/t/' . $new)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);

        // revoke
        auth()->logout();
        $this->issue($admin->username . ' --revoke');
        $this->assertNull($admin->fresh()->login_token_hash);
        $this->get('/t/' . $new)->assertNotFound();
        $this->assertGuest();

        // disabled account cannot use its link
        $token = $this->issue($admin->username);
        $admin->forceFill(['is_active' => false])->save();
        $this->get('/t/' . $token)->assertNotFound();
        $this->assertGuest();
    }
}
