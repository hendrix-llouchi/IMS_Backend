<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\OwnerSeeder;
use App\Models\User;
use App\Models\WorkerFlag;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class OwnerTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // Seeded owner credentials — mirrors OwnerSeeder exactly
    // ──────────────────────────────────────────────────────────────
    private const OWNER_USERNAME = 'owner';
    private const TOKEN_COOKIE   = 'token';

    /**
     * Seed the owner account and disable throttle middleware before every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->seed(OwnerSeeder::class);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: mint a JWT for the seeded owner and return a headers
    // array that carries it as a Bearer token.
    //
    // Why Bearer and not withCookie()?
    // The OwnerMiddleware calls JWTAuth::parseToken()->authenticate()
    // which resolves the token from the Authorization header by default.
    // withCookie() passes a raw (un-encrypted) JWT string; Laravel's
    // EncryptCookies middleware rejects it before the custom middleware
    // ever runs, producing a spurious 401. The Bearer header path is
    // not affected by cookie encryption and mirrors exactly what
    // JWTAuth::parseToken() looks for.
    // ──────────────────────────────────────────────────────────────
    private function actingAsOwner(): string
    {
        $owner = User::where('username', self::OWNER_USERNAME)->firstOrFail();
        return JWTAuth::fromUser($owner);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: mint a JWT for any given User model instance.
    // ──────────────────────────────────────────────────────────────
    private function tokenFor(User $user): string
    {
        return JWTAuth::fromUser($user);
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: create a minimal manager user inline.
    // ──────────────────────────────────────────────────────────────
    private function createManager(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'                  => 'Test Manager',
            'age'                   => 28,
            'phone_number'          => '1111111111',
            'location'              => 'Branch A',
            'emergency_contact'     => 'Emergency A',
            'email'                 => 'manager_' . uniqid() . '@ims.com',
            'username'              => 'manager_' . uniqid(),
            'password'              => Hash::make('password'),
            'role'                  => 'manager',
            'is_active'             => true,
            'is_temporary_password' => false,
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────────
    // Helper: create a minimal worker user inline.
    // ──────────────────────────────────────────────────────────────
    private function createWorker(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'                  => 'Test Worker',
            'age'                   => 22,
            'phone_number'          => '2222222222',
            'location'              => 'Warehouse B',
            'emergency_contact'     => 'Emergency B',
            'email'                 => 'worker_' . uniqid() . '@ims.com',
            'username'              => 'worker_' . uniqid(),
            'password'              => Hash::make('password'),
            'role'                  => 'worker',
            'is_active'             => true,
            'is_temporary_password' => false,
        ], $overrides));
    }

    // ══════════════════════════════════════════════════════════════
    //  USER MANAGEMENT
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 1. test_owner_can_create_manager
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_create_manager(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/owner/users/create', [
                'name'              => 'Alice Manager',
                'age'               => 32,
                'phone_number'      => '0501234567',
                'location'          => 'Main Office',
                'emergency_contact' => '0599999999',
                'email'             => 'alice.manager@ims.com',
                'username'          => 'alice_manager',
                'role'              => 'manager',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'username' => 'alice_manager',
            'role'     => 'manager',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. test_owner_can_create_worker
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_create_worker(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/owner/users/create', [
                'name'              => 'Bob Worker',
                'age'               => 24,
                'phone_number'      => '0507654321',
                'location'          => 'Warehouse C',
                'emergency_contact' => '0588888888',
                'email'             => 'bob.worker@ims.com',
                'username'          => 'bob_worker',
                'role'              => 'worker',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'username' => 'bob_worker',
            'role'     => 'worker',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. test_create_user_generates_temporary_password
    // ──────────────────────────────────────────────────────────────
    public function test_create_user_generates_temporary_password(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/owner/users/create', [
                'name'              => 'Temp Pass User',
                'age'               => 27,
                'phone_number'      => '0500000001',
                'location'          => 'Branch X',
                'emergency_contact' => '0577777777',
                'email'             => 'temp.user@ims.com',
                'username'          => 'temp_user',
                'role'              => 'worker',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'username'              => 'temp_user',
            'is_temporary_password' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. test_create_user_with_duplicate_email_fails
    // ──────────────────────────────────────────────────────────────
    public function test_create_user_with_duplicate_email_fails(): void
    {
        $token = $this->actingAsOwner();

        // Create a user with a known email first
        $this->createManager(['email' => 'duplicate@ims.com']);

        // Attempt to create another user with the same email
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/owner/users/create', [
                'name'              => 'Duplicate Email',
                'age'               => 25,
                'phone_number'      => '0500000002',
                'location'          => 'Branch Y',
                'emergency_contact' => '0566666666',
                'email'             => 'duplicate@ims.com',
                'username'          => 'unique_username_1',
                'role'              => 'manager',
            ]);

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. test_create_user_with_missing_fields_fails
    // ──────────────────────────────────────────────────────────────
    public function test_create_user_with_missing_fields_fails(): void
    {
        $token = $this->actingAsOwner();

        // Omit required fields: name, email, username, role
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/owner/users/create', [
                'age'      => 30,
                'location' => 'Somewhere',
            ]);

        $response->assertStatus(422);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. test_owner_can_get_all_users
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_get_all_users(): void
    {
        $token = $this->actingAsOwner();

        $this->createManager();
        $this->createWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/users');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'last_page',
            'per_page',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. test_owner_cannot_see_own_account_in_user_list
    // ──────────────────────────────────────────────────────────────
    public function test_owner_cannot_see_own_account_in_user_list(): void
    {
        $token = $this->actingAsOwner();
        $owner = User::where('username', self::OWNER_USERNAME)->firstOrFail();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/users');

        $response->assertStatus(200);

        // Extract all IDs from the paginated data array
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($owner->id, $returnedIds, 'Owner\'s own account must not appear in the user list.');
    }

    // ──────────────────────────────────────────────────────────────
    // 8. test_owner_can_get_single_user
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_get_single_user(): void
    {
        $token   = $this->actingAsOwner();
        $manager = $this->createManager(['name' => 'Specific Manager']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/owner/users/{$manager->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $manager->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 9. test_get_nonexistent_user_returns_404
    // ──────────────────────────────────────────────────────────────
    public function test_get_nonexistent_user_returns_404(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/users/99999');

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    // 10. test_owner_can_update_user
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_update_user(): void
    {
        $token  = $this->actingAsOwner();
        $worker = $this->createWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/owner/users/{$worker->id}", [
                'name'     => 'Updated Name',
                'location' => 'Updated Location',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id'       => $worker->id,
            'name'     => 'Updated Name',
            'location' => 'Updated Location',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 11. test_owner_can_deactivate_user
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_deactivate_user(): void
    {
        $token  = $this->actingAsOwner();
        $worker = $this->createWorker(['is_active' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/owner/users/{$worker->id}/deactivate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id'        => $worker->id,
            'is_active' => false,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 12. test_deactivated_user_cannot_login
    // ──────────────────────────────────────────────────────────────
    public function test_deactivated_user_cannot_login(): void
    {
        $plainPassword = 'password123';
        $worker = $this->createWorker([
            'username'  => 'inactive_worker',
            'password'  => Hash::make($plainPassword),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => $worker->username,
            'password' => $plainPassword,
        ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    // 13. test_owner_can_reactivate_user
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_reactivate_user(): void
    {
        $token  = $this->actingAsOwner();
        $worker = $this->createWorker(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/owner/users/{$worker->id}/reactivate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id'        => $worker->id,
            'is_active' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 14. test_owner_can_reset_user_password
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_reset_user_password(): void
    {
        $token  = $this->actingAsOwner();
        $worker = $this->createWorker(['is_temporary_password' => false]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/owner/users/{$worker->id}/reset-password");

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id'                    => $worker->id,
            'is_temporary_password' => true,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 15. test_owner_can_delete_user
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_delete_user(): void
    {
        $token   = $this->actingAsOwner();
        $manager = $this->createManager();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/owner/users/{$manager->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $manager->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 16. test_deleted_user_no_longer_exists
    // ──────────────────────────────────────────────────────────────
    public function test_deleted_user_no_longer_exists(): void
    {
        $token   = $this->actingAsOwner();
        $manager = $this->createManager();
        $id      = $manager->id;

        // Delete the user
        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/owner/users/{$id}")
            ->assertStatus(200);

        // Subsequent GET must return 404
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/owner/users/{$id}");

        $response->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════
    //  WORKER FLAGS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 17. test_owner_can_get_pending_flags
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_get_pending_flags(): void
    {
        $token   = $this->actingAsOwner();
        $manager = $this->createManager();
        $worker  = $this->createWorker();

        // Create one pending flag and one dismissed flag
        WorkerFlag::create([
            'manager_id' => $manager->id,
            'worker_id'  => $worker->id,
            'reason'     => 'Pending reason',
            'status'     => 'pending',
        ]);

        WorkerFlag::create([
            'manager_id'  => $manager->id,
            'worker_id'   => $worker->id,
            'reason'      => 'Dismissed reason',
            'status'      => 'dismissed',
            'reviewed_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/flags');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'last_page',
            'per_page',
        ]);

        // All returned flags must be pending
        foreach ($response->json('data') as $flag) {
            $this->assertEquals('pending', $flag['status'], 'Only pending flags should be returned.');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 18. test_owner_can_dismiss_flag
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_dismiss_flag(): void
    {
        $token   = $this->actingAsOwner();
        $manager = $this->createManager();
        $worker  = $this->createWorker();

        $flag = WorkerFlag::create([
            'manager_id' => $manager->id,
            'worker_id'  => $worker->id,
            'reason'     => 'Reason for dismissal',
            'status'     => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/owner/flags/{$flag->id}/dismiss");

        $response->assertStatus(200);

        $flag->refresh();
        $this->assertEquals('dismissed', $flag->status);
        $this->assertNotNull($flag->reviewed_at, 'reviewed_at must be set after dismissal.');
    }

    // ──────────────────────────────────────────────────────────────
    // 19. test_owner_can_warn_worker
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_warn_worker(): void
    {
        $token   = $this->actingAsOwner();
        $manager = $this->createManager();
        $worker  = $this->createWorker();

        $flag = WorkerFlag::create([
            'manager_id' => $manager->id,
            'worker_id'  => $worker->id,
            'reason'     => 'Reason for warning',
            'status'     => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/owner/flags/{$flag->id}/warn");

        $response->assertStatus(200);

        $flag->refresh();
        $this->assertEquals('warning_issued', $flag->status);
        $this->assertNotNull($flag->reviewed_at, 'reviewed_at must be set after warning.');
    }

    // ──────────────────────────────────────────────────────────────
    // 20. test_reviewed_flag_not_in_pending_list
    // ──────────────────────────────────────────────────────────────
    public function test_reviewed_flag_not_in_pending_list(): void
    {
        $token   = $this->actingAsOwner();
        $manager = $this->createManager();
        $worker  = $this->createWorker();

        $flag = WorkerFlag::create([
            'manager_id' => $manager->id,
            'worker_id'  => $worker->id,
            'reason'     => 'Will be dismissed',
            'status'     => 'pending',
        ]);

        // Dismiss the flag
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/owner/flags/{$flag->id}/dismiss")
            ->assertStatus(200);

        // Fetch pending flags — dismissed flag must not appear
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/flags');

        $response->assertStatus(200);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($flag->id, $returnedIds, 'Dismissed flag must not appear in the pending list.');
    }

    // ══════════════════════════════════════════════════════════════
    //  STOCK, ORDERS AND REPORTS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 21. test_owner_can_view_all_stock
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_view_all_stock(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/stock');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'last_page',
            'per_page',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 22. test_owner_can_view_all_orders
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_view_all_orders(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/orders');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'last_page',
            'per_page',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 23. test_owner_can_get_financial_report
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_get_financial_report(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/reports/financial');

        $response->assertStatus(200);
        // The financial report must include order count breakdown keys
        $response->assertJsonStructure([
            'total_orders',
            'delivered_orders',
            'flagged_orders',
            'pending_orders',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 24. test_owner_can_get_audit_report
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_get_audit_report(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/reports/audit');

        $response->assertStatus(200);
        // The audit key must be present; each product entry must carry
        // low_stock and stock_percentage computed fields.
        $response->assertJsonStructure(['audit']);

        foreach ($response->json('audit') as $product) {
            $this->assertArrayHasKey('low_stock', $product, 'Each audit entry must have a low_stock flag.');
            $this->assertArrayHasKey('stock_percentage', $product, 'Each audit entry must have a stock_percentage value.');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 25. test_owner_can_get_settings
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_get_settings(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/owner/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure(['settings']);
    }

    // ──────────────────────────────────────────────────────────────
    // 26. test_owner_can_update_settings
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_update_settings(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/owner/settings', [
                'low_stock_threshold' => 25,
                'pagination_per_page' => 15,
                'lockout_attempts'    => 5,
                'lockout_duration'    => 30,
                'reset_token_expiry'  => 120,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Settings updated successfully.']);
    }

    // ══════════════════════════════════════════════════════════════
    //  RBAC — AUTHENTICATION AND AUTHORISATION GUARDS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 27. test_unauthenticated_user_cannot_access_owner_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_unauthenticated_user_cannot_access_owner_endpoints(): void
    {
        // No Authorization header — the request is completely anonymous.
        $response = $this->getJson('/api/owner/users');

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────
    // 28. test_manager_cannot_access_owner_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_manager_cannot_access_owner_endpoints(): void
    {
        $manager      = $this->createManager();
        $managerToken = $this->tokenFor($manager);

        $response = $this->withHeader('Authorization', "Bearer {$managerToken}")
            ->getJson('/api/owner/users');

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    // 29. test_worker_cannot_access_owner_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_access_owner_endpoints(): void
    {
        $worker      = $this->createWorker();
        $workerToken = $this->tokenFor($worker);

        $response = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->getJson('/api/owner/users');

        $response->assertStatus(403);
    }
}
