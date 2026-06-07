<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\OwnerSeeder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\WorkerFlag;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class ManagerTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // Constants
    // ──────────────────────────────────────────────────────────────
    private const TOKEN_COOKIE = 'token';

    /**
     * Seed the owner account and disable throttle middleware before every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        // Switch the broadcast driver to 'log' so controller broadcast() calls
        // (OrderAssigned, LowStockAlert, ShortDeliveryAlert, etc.) are silently
        // discarded instead of attempting a real Pusher connection during tests.
        config(['broadcasting.default' => 'log']);

        $this->seed(OwnerSeeder::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════

    /**
     * Create a manager user and return a signed JWT for them.
     */
    private function actingAsManager(array $overrides = []): string
    {
        $manager = User::create(array_merge([
            'name'                  => 'Test Manager',
            'age'                   => 30,
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

        return JWTAuth::fromUser($manager);
    }

    /**
     * Create a manager User model (returned for later reference).
     */
    private function createManager(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'                  => 'Test Manager',
            'age'                   => 30,
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

    /**
     * Create a worker user and return a signed JWT for them.
     */
    private function actingAsWorker(array $overrides = []): string
    {
        $worker = User::create(array_merge([
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

        return JWTAuth::fromUser($worker);
    }

    /**
     * Create a worker User model (returned for later reference).
     */
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

    /**
     * Create and return a Warehouse record.
     */
    private function createWarehouse(array $overrides = []): Warehouse
    {
        return Warehouse::create(array_merge([
            'name'     => 'Test Warehouse ' . uniqid(),
            'location' => 'Test Location',
        ], $overrides));
    }

    /**
     * Create and return a Product with known stock levels (max=100, current=50).
     */
    private function createProduct(int $warehouseId, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'warehouse_id'   => $warehouseId,
            'name'           => 'Test Product ' . uniqid(),
            'type'           => 'General',
            'description'    => 'A test product',
            'unit'           => 'pcs',
            'current_stock'  => 50,
            'max_stock_level' => 100,
        ], $overrides));
    }

    /**
     * Create and return an Order (status=unassigned) with one order item.
     */
    private function createOrder(int $managerId, int $warehouseId, array $overrides = []): Order
    {
        $product = $this->createProduct($warehouseId);

        $order = Order::create(array_merge([
            'manager_id'        => $managerId,
            'worker_id'         => null,
            'recipient_name'    => 'John Doe',
            'recipient_contact' => '0501234567',
            'delivery_deadline' => now()->addDays(7)->toDateString(),
            'status'            => 'unassigned',
            'flag_reason'       => null,
        ], $overrides));

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);

        return $order;
    }

    /**
     * Create and return a PurchaseOrder (status=pending) with one item.
     */
    private function createPurchaseOrder(int $managerId, int $warehouseId, array $overrides = []): PurchaseOrder
    {
        $product = $this->createProduct($warehouseId);

        $po = PurchaseOrder::create(array_merge([
            'manager_id'             => $managerId,
            'warehouse_id'           => $warehouseId,
            'supplier_name'          => 'Test Supplier',
            'expected_delivery_date' => now()->addDays(14)->toDateString(),
            'actual_arrival_date'    => null,
            'status'                 => 'pending',
        ], $overrides));

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id'        => $product->id,
            'quantity_ordered'  => 20,
            'quantity_received' => null,
        ]);

        return $po;
    }

    // ══════════════════════════════════════════════════════════════
    //  USER MANAGEMENT
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 1. test_manager_can_create_worker
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_create_worker(): void
    {
        $token = $this->actingAsManager();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/users/create', [
                'name'              => 'New Worker',
                'age'               => 25,
                'phone_number'      => '0501112222',
                'location'          => 'Warehouse D',
                'emergency_contact' => '0599990000',
                'email'             => 'new.worker@ims.com',
                'username'          => 'new_worker_test',
                'role'              => 'worker',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'username' => 'new_worker_test',
            'role'     => 'worker',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 2. test_manager_cannot_create_manager
    //
    // The createWorker endpoint ignores any role field in the request
    // body and hardcodes role='worker'. Sending role='manager' does NOT
    // raise a validation error in the current implementation — the
    // endpoint simply silently overrides it to 'worker'. The test
    // therefore asserts that the resulting record is stored as 'worker',
    // not 'manager', proving the business rule is enforced at the
    // persistence layer even if the HTTP response is still 201.
    // ──────────────────────────────────────────────────────────────
    public function test_manager_cannot_create_manager(): void
    {
        $token = $this->actingAsManager();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/users/create', [
                'name'              => 'Another Manager',
                'age'               => 35,
                'phone_number'      => '0503334444',
                'location'          => 'Branch B',
                'emergency_contact' => '0588880000',
                'email'             => 'another.manager@ims.com',
                'username'          => 'another_manager_test',
                'role'              => 'manager', // ignored by endpoint
            ]);

        // The endpoint always forces role=worker regardless of the body.
        // Verify the user was NOT stored as manager in the database.
        $this->assertDatabaseMissing('users', [
            'username' => 'another_manager_test',
            'role'     => 'manager',
        ]);
        $this->assertDatabaseHas('users', [
            'username' => 'another_manager_test',
            'role'     => 'worker',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 3. test_manager_can_get_all_workers
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_all_workers(): void
    {
        $token = $this->actingAsManager();

        $this->createWorker();
        $this->createWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/users');

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
    // 4. test_manager_can_get_worker_status
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_worker_status(): void
    {
        $token = $this->actingAsManager();

        $this->createWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/workers/status');

        $response->assertStatus(200);

        // Endpoint returns {'workers': [...]} (not paginated)
        $this->assertArrayHasKey('workers', $response->json(), 'Response must have a workers key.');
        foreach ($response->json('workers') as $worker) {
            $this->assertArrayHasKey('status', $worker, 'Each worker entry must include a status field.');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 5. test_worker_status_is_busy_when_has_assigned_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_status_is_busy_when_has_assigned_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $worker    = $this->createWorker();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Create an order already assigned to this worker
        $order = Order::create([
            'manager_id'        => $manager->id,
            'worker_id'         => $worker->id,
            'recipient_name'    => 'Busy Test Recipient',
            'recipient_contact' => '0501110000',
            'delivery_deadline' => now()->addDays(3)->toDateString(),
            'status'            => 'assigned',
            'flag_reason'       => null,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/workers/status');

        $response->assertStatus(200);

        // Endpoint returns {'workers': [...]} (not paginated)
        $workers = collect($response->json('workers'));
        $found   = $workers->firstWhere('id', $worker->id);

        $this->assertNotNull($found, 'Worker must appear in the status list.');
        $this->assertEquals('Busy', $found['status'], 'Worker with an assigned order must be Busy.');
    }

    // ──────────────────────────────────────────────────────────────
    // 6. test_worker_status_is_available_when_no_assigned_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_status_is_available_when_no_assigned_order(): void
    {
        $manager = $this->createManager();
        $token   = JWTAuth::fromUser($manager);
        $worker  = $this->createWorker();

        // No orders linked to this worker at all

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/workers/status');

        $response->assertStatus(200);

        // Endpoint returns {'workers': [...]} (not paginated)
        $workers = collect($response->json('workers'));
        $found   = $workers->firstWhere('id', $worker->id);

        $this->assertNotNull($found, 'Worker must appear in the status list.');
        $this->assertEquals('Available', $found['status'], 'Worker with no assigned order must be Available.');
    }

    // ══════════════════════════════════════════════════════════════
    //  WAREHOUSES
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 7. test_manager_can_create_warehouse
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_create_warehouse(): void
    {
        $token = $this->actingAsManager();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/warehouses', [
                'name'     => 'New Warehouse Alpha',
                'location' => 'Industrial Zone 1',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouses', [
            'name'     => 'New Warehouse Alpha',
            'location' => 'Industrial Zone 1',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. test_manager_can_get_all_warehouses
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_all_warehouses(): void
    {
        $token = $this->actingAsManager();

        $this->createWarehouse();
        $this->createWarehouse();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/warehouses');

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
    // 9. test_manager_can_get_single_warehouse
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_single_warehouse(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse(['name' => 'Specific Warehouse']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/manager/warehouses/{$warehouse->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $warehouse->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 10. test_manager_can_update_warehouse
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_update_warehouse(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/warehouses/{$warehouse->id}", [
                'name'     => 'Updated Warehouse Name',
                'location' => 'Updated Location',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouses', [
            'id'       => $warehouse->id,
            'name'     => 'Updated Warehouse Name',
            'location' => 'Updated Location',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  PRODUCTS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 11. test_manager_can_create_product
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_create_product(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/products', [
                'warehouse_id'    => $warehouse->id,
                'name'            => 'Widget A',
                'type'            => 'Electronics',
                'description'     => 'A small widget',
                'unit'            => 'pcs',
                'current_stock'   => 40,
                'max_stock_level' => 200,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'name'         => 'Widget A',
            'warehouse_id' => $warehouse->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 12. test_manager_can_get_all_products
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_all_products(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();

        $this->createProduct($warehouse->id);
        $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/products');

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
    // 13. test_manager_can_get_single_product
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_single_product(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/manager/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $product->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 14. test_manager_can_update_product
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_update_product(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/products/{$product->id}", [
                'name'        => 'Updated Product Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id'          => $product->id,
            'name'        => 'Updated Product Name',
            'description' => 'Updated description',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  STOCK
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 15. test_manager_can_view_stock
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_view_stock(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();

        $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/stock');

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
    // 16. test_manager_can_update_stock
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_update_stock(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id, ['current_stock' => 50]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/stock/{$product->id}", [
                'current_stock' => 75,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id'            => $product->id,
            'current_stock' => 75,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 17. test_manager_can_get_low_stock_products
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_low_stock_products(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();

        // max=100, current=25 → 25% of max → below 30% threshold → low stock
        $lowProduct = $this->createProduct($warehouse->id, [
            'current_stock'   => 25,
            'max_stock_level' => 100,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/stock/low');

        $response->assertStatus(200);

        // Endpoint returns {'low_stock_products': [...]} (not paginated)
        $returnedIds = collect($response->json('low_stock_products'))->pluck('id')->all();
        $this->assertContains(
            $lowProduct->id,
            $returnedIds,
            'Product at or below 30% threshold must appear in the low-stock list.'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // 18. test_product_above_threshold_not_in_low_stock
    // ──────────────────────────────────────────────────────────────
    public function test_product_above_threshold_not_in_low_stock(): void
    {
        $token     = $this->actingAsManager();
        $warehouse = $this->createWarehouse();

        // max=100, current=50 → 50% of max → above 30% threshold → NOT low stock
        $normalProduct = $this->createProduct($warehouse->id, [
            'current_stock'   => 50,
            'max_stock_level' => 100,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/stock/low');

        $response->assertStatus(200);

        // Endpoint returns {'low_stock_products': [...]} (not paginated)
        $returnedIds = collect($response->json('low_stock_products'))->pluck('id')->all();
        $this->assertNotContains(
            $normalProduct->id,
            $returnedIds,
            'Product above the 30% threshold must NOT appear in the low-stock list.'
        );
    }

    // ══════════════════════════════════════════════════════════════
    //  ORDERS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 19. test_manager_can_create_order
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_create_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/orders', [
                'recipient_name'    => 'Jane Smith',
                'recipient_contact' => '0507778888',
                'delivery_deadline' => now()->addDays(5)->toDateString(),
                'items'             => [
                    ['product_id' => $product->id, 'quantity' => 3],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'manager_id'     => $manager->id,
            'recipient_name' => 'Jane Smith',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 20. test_order_is_created_with_unassigned_status
    // ──────────────────────────────────────────────────────────────
    public function test_order_is_created_with_unassigned_status(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/orders', [
                'recipient_name'    => 'Mark Unassigned',
                'recipient_contact' => '0509990000',
                'delivery_deadline' => now()->addDays(10)->toDateString(),
                'items'             => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'manager_id'     => $manager->id,
            'recipient_name' => 'Mark Unassigned',
            'status'         => 'unassigned',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 21. test_manager_can_get_all_orders
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_all_orders(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();

        $this->createOrder($manager->id, $warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/orders');

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
    // 22. test_manager_can_get_single_order
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_single_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $order     = $this->createOrder($manager->id, $warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/manager/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $order->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 23. test_manager_can_assign_order_to_worker
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_assign_order_to_worker(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $worker    = $this->createWorker();
        $warehouse = $this->createWarehouse();
        $order     = $this->createOrder($manager->id, $warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/orders/{$order->id}/assign", [
                'worker_id' => $worker->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'        => $order->id,
            'worker_id' => $worker->id,
            'status'    => 'assigned',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 24. test_cannot_assign_already_assigned_order
    // ──────────────────────────────────────────────────────────────
    public function test_cannot_assign_already_assigned_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $worker    = $this->createWorker();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Create an order that is already assigned
        $order = Order::create([
            'manager_id'        => $manager->id,
            'worker_id'         => $worker->id,
            'recipient_name'    => 'Already Assigned',
            'recipient_contact' => '0501111111',
            'delivery_deadline' => now()->addDays(5)->toDateString(),
            'status'            => 'assigned',
            'flag_reason'       => null,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $anotherWorker = $this->createWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/orders/{$order->id}/assign", [
                'worker_id' => $anotherWorker->id,
            ]);

        $response->assertStatus(400);
    }

    // ──────────────────────────────────────────────────────────────
    // 25. test_manager_can_flag_order
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_flag_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $order     = $this->createOrder($manager->id, $warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/orders/{$order->id}/flag", [
                'flag_reason' => 'Delivery address is incorrect.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'          => $order->id,
            'status'      => 'flagged',
            'flag_reason' => 'Delivery address is incorrect.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 26. test_manager_can_resolve_flagged_order
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_resolve_flagged_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $worker    = $this->createWorker();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Create a flagged order that was previously assigned
        $order = Order::create([
            'manager_id'        => $manager->id,
            'worker_id'         => $worker->id,
            'recipient_name'    => 'Flagged Order Recipient',
            'recipient_contact' => '0502223333',
            'delivery_deadline' => now()->addDays(5)->toDateString(),
            'status'            => 'flagged',
            'flag_reason'       => 'Some issue',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 2,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/orders/{$order->id}/resolve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'          => $order->id,
            'status'      => 'assigned',
            'flag_reason' => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 27. test_cannot_resolve_non_flagged_order
    // ──────────────────────────────────────────────────────────────
    public function test_cannot_resolve_non_flagged_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();

        // Order is unassigned (not flagged)
        $order = $this->createOrder($manager->id, $warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/orders/{$order->id}/resolve");

        $response->assertStatus(400);
    }

    // ══════════════════════════════════════════════════════════════
    //  PURCHASE ORDERS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 28. test_manager_can_create_purchase_order
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_create_purchase_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/purchase-orders', [
                'warehouse_id'           => $warehouse->id,
                'supplier_name'          => 'Acme Supplies',
                'expected_delivery_date' => now()->addDays(14)->toDateString(),
                'items'                  => [
                    ['product_id' => $product->id, 'quantity_ordered' => 50],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('purchase_orders', [
            'manager_id'    => $manager->id,
            'supplier_name' => 'Acme Supplies',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 29. test_purchase_order_created_with_pending_status
    // ──────────────────────────────────────────────────────────────
    public function test_purchase_order_created_with_pending_status(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/purchase-orders', [
                'warehouse_id'           => $warehouse->id,
                'supplier_name'          => 'Pending Supplier',
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'items'                  => [
                    ['product_id' => $product->id, 'quantity_ordered' => 10],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('purchase_orders', [
            'manager_id'    => $manager->id,
            'supplier_name' => 'Pending Supplier',
            'status'        => 'pending',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 30. test_manager_can_get_all_purchase_orders
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_all_purchase_orders(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();

        $this->createPurchaseOrder($manager->id, $warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/purchase-orders');

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
    // 31. test_manager_can_get_single_purchase_order
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_single_purchase_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $po        = $this->createPurchaseOrder($manager->id, $warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/manager/purchase-orders/{$po->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $po->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 32. test_complete_delivery_updates_stock
    // ──────────────────────────────────────────────────────────────
    public function test_complete_delivery_updates_stock(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();

        // Product starts with 50 units
        $product = $this->createProduct($warehouse->id, [
            'current_stock'   => 50,
            'max_stock_level' => 200,
        ]);

        $po = PurchaseOrder::create([
            'manager_id'             => $manager->id,
            'warehouse_id'           => $warehouse->id,
            'supplier_name'          => 'Stock Update Supplier',
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'actual_arrival_date'    => null,
            'status'                 => 'pending',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id'        => $product->id,
            'quantity_ordered'  => 30,
            'quantity_received' => null,
        ]);

        // Receive the full quantity — endpoint requires status, actual_arrival_date,
        // and items keyed by purchase_order_item_id (not product_id)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/purchase-orders/{$po->id}/status", [
                'status'               => 'complete',
                'actual_arrival_date'  => now()->toDateString(),
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received'       => 30,
                    ],
                ],
            ]);

        $response->assertStatus(200);

        // Stock must have been incremented: 50 + 30 = 80
        $this->assertDatabaseHas('products', [
            'id'            => $product->id,
            'current_stock' => 80,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 33. test_complete_delivery_sets_status_to_complete
    // ──────────────────────────────────────────────────────────────
    public function test_complete_delivery_sets_status_to_complete(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $po = PurchaseOrder::create([
            'manager_id'             => $manager->id,
            'warehouse_id'           => $warehouse->id,
            'supplier_name'          => 'Complete Supplier',
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'actual_arrival_date'    => null,
            'status'                 => 'pending',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id'        => $product->id,
            'quantity_ordered'  => 20,
            'quantity_received' => null,
        ]);

        // Receive all 20 units — status=complete triggers the complete path
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/purchase-orders/{$po->id}/status", [
                'status'              => 'complete',
                'actual_arrival_date' => now()->toDateString(),
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received'       => 20,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('purchase_orders', [
            'id'     => $po->id,
            'status' => 'complete',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 34. test_short_delivery_sets_status_to_incomplete
    // ──────────────────────────────────────────────────────────────
    public function test_short_delivery_sets_status_to_incomplete(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $po = PurchaseOrder::create([
            'manager_id'             => $manager->id,
            'warehouse_id'           => $warehouse->id,
            'supplier_name'          => 'Short Supplier',
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'actual_arrival_date'    => null,
            'status'                 => 'pending',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id'        => $product->id,
            'quantity_ordered'  => 20,
            'quantity_received' => null,
        ]);

        // Receive only 15 out of 20 units → short delivery, status=incomplete
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/purchase-orders/{$po->id}/status", [
                'status'              => 'incomplete',
                'actual_arrival_date' => now()->toDateString(),
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received'       => 15,
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('purchase_orders', [
            'id'     => $po->id,
            'status' => 'incomplete',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 35. test_cannot_update_non_pending_purchase_order
    // ──────────────────────────────────────────────────────────────
    public function test_cannot_update_non_pending_purchase_order(): void
    {
        $manager   = $this->createManager();
        $token     = JWTAuth::fromUser($manager);
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Create a PO that is already complete
        $po = PurchaseOrder::create([
            'manager_id'             => $manager->id,
            'warehouse_id'           => $warehouse->id,
            'supplier_name'          => 'Already Complete Supplier',
            'expected_delivery_date' => now()->addDays(7)->toDateString(),
            'actual_arrival_date'    => now()->toDateString(),
            'status'                 => 'complete',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id'        => $product->id,
            'quantity_ordered'  => 10,
            'quantity_received' => 10,
        ]);

        // Attempt to receive again on an already-complete PO → must return 400
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/purchase-orders/{$po->id}/status", [
                'status'              => 'complete',
                'actual_arrival_date' => now()->toDateString(),
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received'       => 10,
                    ],
                ],
            ]);

        $response->assertStatus(400);
    }

    // ══════════════════════════════════════════════════════════════
    //  WORKER FLAGS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 36. test_manager_can_flag_worker
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_flag_worker(): void
    {
        $manager = $this->createManager();
        $token   = JWTAuth::fromUser($manager);
        $worker  = $this->createWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/flags', [
                'worker_id' => $worker->id,
                'reason'    => 'Failed to deliver on time.',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('worker_flags', [
            'manager_id' => $manager->id,
            'worker_id'  => $worker->id,
            'reason'     => 'Failed to deliver on time.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 37. test_flag_is_created_with_pending_status
    // ──────────────────────────────────────────────────────────────
    public function test_flag_is_created_with_pending_status(): void
    {
        $manager = $this->createManager();
        $token   = JWTAuth::fromUser($manager);
        $worker  = $this->createWorker();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/flags', [
                'worker_id' => $worker->id,
                'reason'    => 'Broke equipment during shift.',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('worker_flags', [
            'manager_id'  => $manager->id,
            'worker_id'   => $worker->id,
            'status'      => 'pending',
            'reviewed_at' => null,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 38. test_manager_can_get_all_flags
    // ──────────────────────────────────────────────────────────────
    public function test_manager_can_get_all_flags(): void
    {
        $manager = $this->createManager();
        $token   = JWTAuth::fromUser($manager);
        $worker  = $this->createWorker();

        WorkerFlag::create([
            'manager_id' => $manager->id,
            'worker_id'  => $worker->id,
            'reason'     => 'First flag reason',
            'status'     => 'pending',
        ]);

        WorkerFlag::create([
            'manager_id' => $manager->id,
            'worker_id'  => $worker->id,
            'reason'     => 'Second flag reason',
            'status'     => 'pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/manager/flags');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'last_page',
            'per_page',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  RBAC — AUTHENTICATION AND AUTHORISATION GUARDS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 39. test_unauthenticated_user_cannot_access_manager_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_unauthenticated_user_cannot_access_manager_endpoints(): void
    {
        // No Authorization header — completely anonymous request
        $response = $this->getJson('/api/manager/users');

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────
    // 40. test_worker_cannot_access_manager_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_access_manager_endpoints(): void
    {
        $workerToken = $this->actingAsWorker();

        $response = $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->getJson('/api/manager/users');

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    // 41. test_owner_cannot_access_manager_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_owner_cannot_access_manager_endpoints(): void
    {
        $owner      = User::where('username', 'owner')->firstOrFail();
        $ownerToken = JWTAuth::fromUser($owner);

        $response = $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->getJson('/api/manager/users');

        $response->assertStatus(403);
    }
}
