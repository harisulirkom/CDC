<?php

namespace Tests\Feature;

use App\Models\ExportJob;
use App\Models\Questionnaire;
use App\Models\Role;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'security-rate-limit@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_logout_invalidates_current_access_token(): void
    {
        $role = Role::firstOrCreate(['nama_role' => 'admin_universitas']);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('secret-123'),
        ]);

        $token = $user->createToken('security-test-token')->plainTextToken;
        $tokenId = $user->tokens()->latest('id')->value('id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }

    public function test_export_request_is_forbidden_for_non_admin_role(): void
    {
        Queue::fake();

        $role = Role::firstOrCreate(['nama_role' => 'viewer']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $questionnaire = Questionnaire::create([
            'judul' => 'Q Security',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->postJson('/api/exports/responses', [
                'questionnaire_id' => $questionnaire->id,
                'format' => 'csv',
            ])->assertStatus(403);
    }

    public function test_export_detail_blocks_idor_for_different_non_privileged_user(): void
    {
        $role = Role::firstOrCreate(['nama_role' => 'admin_prodi']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $otherUser = User::factory()->create(['role_id' => $role->id]);
        $questionnaire = Questionnaire::create([
            'judul' => 'Q Security IDOR',
            'status' => 'published',
        ]);

        $job = ExportJob::create([
            'questionnaire_id' => $questionnaire->id,
            'status' => 'queued',
            'format' => 'csv',
            'filters' => [],
            'requested_by' => $owner->id,
        ]);

        $this->actingAs($otherUser)
            ->getJson('/api/exports/'.$job->id)
            ->assertStatus(403);
    }

    public function test_export_endpoint_is_rate_limited_after_twenty_requests_per_minute(): void
    {
        Queue::fake();

        $role = Role::firstOrCreate(['nama_role' => 'admin_universitas']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $questionnaire = Questionnaire::create([
            'judul' => 'Q Security Export Limit',
            'status' => 'published',
        ]);

        for ($i = 1; $i <= 20; $i++) {
            $this->actingAs($user)
                ->postJson('/api/exports/responses', [
                    'questionnaire_id' => $questionnaire->id,
                    'format' => 'csv',
                ])->assertStatus(202);
        }

        $this->actingAs($user)
            ->postJson('/api/exports/responses', [
                'questionnaire_id' => $questionnaire->id,
                'format' => 'csv',
            ])->assertStatus(429);
    }

    public function test_production_guard_rejects_debug_true(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'enforceProductionSecurityGuards');
        $method->setAccessible(true);

        $originalEnv = $this->app->environment();
        $originalDebug = config('app.debug');
        $originalDefaultDb = config('database.default');
        $originalDbConfig = config('database.connections.mysql_guard');

        $this->app['env'] = 'production';
        config([
            'app.debug' => true,
            'database.default' => 'mysql_guard',
            'database.connections.mysql_guard' => ['username' => 'cdc_app'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_DEBUG must be false in production');

        try {
            $method->invoke($provider);
        } finally {
            $this->app['env'] = $originalEnv;
            config([
                'app.debug' => $originalDebug,
                'database.default' => $originalDefaultDb,
                'database.connections.mysql_guard' => $originalDbConfig,
            ]);
        }
    }

    public function test_production_guard_rejects_superuser_db_account(): void
    {
        $provider = new AppServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'enforceProductionSecurityGuards');
        $method->setAccessible(true);

        $originalEnv = $this->app->environment();
        $originalDebug = config('app.debug');
        $originalDefaultDb = config('database.default');
        $originalDbConfig = config('database.connections.mysql_guard');

        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'database.default' => 'mysql_guard',
            'database.connections.mysql_guard' => ['username' => 'root'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('database username must be least-privilege in production');

        try {
            $method->invoke($provider);
        } finally {
            $this->app['env'] = $originalEnv;
            config([
                'app.debug' => $originalDebug,
                'database.default' => $originalDefaultDb,
                'database.connections.mysql_guard' => $originalDbConfig,
            ]);
        }
    }
}
