<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use Database\Seeders\OwnerSeeder;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;

class SharedTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // Seed the owner account before every test.
    // ──────────────────────────────────────────────────────────────
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OwnerSeeder::class);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers — role token generators
    //
    // Why withHeader('Authorization', ...) and not withCookie()?
    // ─────────────────────────────────────────────────────────────
    // withCookie() passes a raw (un-encrypted) JWT string. Laravel's
    // EncryptCookies middleware rejects it before the custom
    // ExtractTokenFromCookie middleware ever runs, so the
    // Authorization header never gets set and the request lands as
    // anonymous (401). Using the Bearer header directly bypasses that
    // problem and is exactly what JWTAuth::parseToken() looks for —
    // the same approach used in OwnerTest, ManagerTest, and WorkerTest.
    // ──────────────────────────────────────────────────────────────

    /**
     * Returns a JWT for the seeded owner account.
     */
    private function actingAsOwner(): string
    {
        $owner = User::where('role', 'owner')->firstOrFail();
        return JWTAuth::fromUser($owner);
    }

    /**
     * Creates a fresh manager account and returns its JWT.
     */
    private function actingAsManager(): string
    {
        $manager = User::create([
            'name'              => 'Test Manager',
            'age'               => 35,
            'phone_number'      => '1234567890',
            'location'          => 'HQ',
            'emergency_contact' => '0987654321',
            'email'             => 'manager@example.com',
            'username'          => 'manager',
            'password'          => Hash::make('password'),
            'role'              => 'manager',
            'is_active'         => true,
        ]);
        return JWTAuth::fromUser($manager);
    }

    /**
     * Creates a fresh worker account and returns its JWT.
     */
    private function actingAsWorker(): string
    {
        $worker = User::create([
            'name'              => 'Test Worker',
            'age'               => 25,
            'phone_number'      => '1112223333',
            'location'          => 'Warehouse A',
            'emergency_contact' => '4445556666',
            'email'             => 'worker@example.com',
            'username'          => 'worker',
            'password'          => Hash::make('password'),
            'role'              => 'worker',
            'is_active'         => true,
        ]);
        return JWTAuth::fromUser($worker);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers — resource factories
    // ──────────────────────────────────────────────────────────────

    /**
     * Creates and returns a Warehouse record.
     */
    private function createWarehouse(): Warehouse
    {
        return Warehouse::create([
            'name'     => 'Test Warehouse',
            'location' => 'Test Location',
        ]);
    }

    /**
     * Creates and returns a Product linked to the given warehouse.
     */
    private function createProduct(int $warehouseId): Product
    {
        return Product::create([
            'warehouse_id'    => $warehouseId,
            'name'            => 'Test Product',
            'type'            => 'Test Type',
            'description'     => 'Test Description',
            'unit'            => 'kg',
            'current_stock'   => 100,
            'max_stock_level' => 500,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 1. test_owner_can_access_shared_warehouses
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_access_shared_warehouses(): void
    {
        $token = $this->actingAsOwner();
        $this->createWarehouse();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/warehouses');

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. test_manager_can_access_shared_warehouses
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_access_shared_warehouses(): void
    {
        $token = $this->actingAsManager();
        $this->createWarehouse();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/warehouses');

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. test_worker_can_access_shared_warehouses
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_access_shared_warehouses(): void
    {
        $token = $this->actingAsWorker();
        $this->createWarehouse();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/warehouses');

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // 4. test_unauthenticated_cannot_access_shared_warehouses
    // ──────────────────────────────────────────────────────────────
    public function test_unauthenticated_cannot_access_shared_warehouses(): void
    {
        $this->createWarehouse();

        // No Authorization header attached — must be rejected
        $response = $this->getJson('/api/shared/warehouses');

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. test_can_get_single_warehouse
    // ──────────────────────────────────────────────────────────────
    public function test_can_get_single_warehouse(): void
    {
        $token     = $this->actingAsOwner();
        $warehouse = $this->createWarehouse();

        // Verify the record exists in the database before hitting the API
        $this->assertDatabaseHas('warehouses', [
            'id'   => $warehouse->id,
            'name' => 'Test Warehouse',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/warehouses/' . $warehouse->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id'   => $warehouse->id,
                'name' => 'Test Warehouse',
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 6. test_get_nonexistent_warehouse_returns_404
    // ──────────────────────────────────────────────────────────────
    public function test_get_nonexistent_warehouse_returns_404(): void
    {
        $token = $this->actingAsOwner();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/warehouses/99999');

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. test_owner_can_access_shared_products
    // ──────────────────────────────────────────────────────────────
    public function test_owner_can_access_shared_products(): void
    {
        $token     = $this->actingAsOwner();
        $warehouse = $this->createWarehouse();
        $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/products');

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. test_manager_can_access_shared_products
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_access_shared_products(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();
        $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/products');

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // 9. test_worker_can_access_shared_products
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_access_shared_products(): void
    {
        $token     = $this->actingAsWorker();
        $warehouse = $this->createWarehouse();
        $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/products');

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────────────────────
    // 10. test_unauthenticated_cannot_access_shared_products
    // ──────────────────────────────────────────────────────────────
    public function test_unauthenticated_cannot_access_shared_products(): void
    {
        $warehouse = $this->createWarehouse();
        $this->createProduct($warehouse->id);

        // No Authorization header attached — must be rejected
        $response = $this->getJson('/api/shared/products');

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────
    // 11. test_can_get_single_product
    // ──────────────────────────────────────────────────────────────
    public function test_can_get_single_product(): void
    {
        $token     = $this->actingAsWorker();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Verify the record exists in the database before hitting the API
        $this->assertDatabaseHas('products', [
            'id'           => $product->id,
            'name'         => 'Test Product',
            'warehouse_id' => $warehouse->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/products/' . $product->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id'   => $product->id,
                'name' => 'Test Product',
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 12. test_get_nonexistent_product_returns_404
    // ──────────────────────────────────────────────────────────────
    public function test_get_nonexistent_product_returns_404(): void
    {
        $token = $this->actingAsWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/products/99999');

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    // 13. test_shared_warehouses_are_paginated
    // ──────────────────────────────────────────────────────────────
    public function test_shared_warehouses_are_paginated(): void
    {
        $token = $this->actingAsManager();
        $this->createWarehouse();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/warehouses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
                'last_page',
                'per_page',
            ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 14. test_shared_products_are_paginated
    // ──────────────────────────────────────────────────────────────
    public function test_shared_products_are_paginated(): void
    {
        $token     = $this->actingAsOwner();
        $warehouse = $this->createWarehouse();
        $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/shared/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
                'last_page',
                'per_page',
            ]);
    }
}
