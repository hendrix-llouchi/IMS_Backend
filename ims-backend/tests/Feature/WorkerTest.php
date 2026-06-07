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
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class WorkerTest extends TestCase
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

        // Silence broadcast() calls so no real Pusher connection is attempted.
        config(['broadcasting.default' => 'log']);

        $this->seed(OwnerSeeder::class);
    }

    // ══════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════

    /**
     * Create a worker user, mint a JWT for them, and return the token string.
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
     * Create a worker User model and return both the model and its JWT token.
     * Returns ['user' => User, 'token' => string].
     */
    private function actingAsWorkerWithUser(array $overrides = []): array
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

        return ['user' => $worker, 'token' => JWTAuth::fromUser($worker)];
    }

    /**
     * Create a manager user, mint a JWT for them, and return the token string.
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
     * Create and return a Product with known stock levels.
     *
     * @param int $warehouseId
     * @param int $stock       The initial current_stock value (default 50).
     */
    private function createProduct(int $warehouseId, int $stock = 50): Product
    {
        return Product::create([
            'warehouse_id'    => $warehouseId,
            'name'            => 'Test Product ' . uniqid(),
            'type'            => 'General',
            'description'     => 'A test product',
            'unit'            => 'pcs',
            'current_stock'   => $stock,
            'max_stock_level' => 100,
        ]);
    }

    /**
     * Create an Order with status=assigned, worker_id set, and one OrderItem
     * with quantity=5.
     */
    private function createOrderAssignedTo(int $managerId, int $workerId, int $productId): Order
    {
        $order = Order::create([
            'manager_id'        => $managerId,
            'worker_id'         => $workerId,
            'recipient_name'    => 'Assigned Recipient',
            'recipient_contact' => '0501234567',
            'delivery_deadline' => now()->addDays(7)->toDateString(),
            'status'            => 'assigned',
            'flag_reason'       => null,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $productId,
            'quantity'   => 5,
        ]);

        return $order;
    }

    /**
     * Create an Order with status=unassigned and no worker_id.
     */
    private function createUnassignedOrder(int $managerId, int $productId): Order
    {
        $order = Order::create([
            'manager_id'        => $managerId,
            'worker_id'         => null,
            'recipient_name'    => 'Unassigned Recipient',
            'recipient_contact' => '0509999999',
            'delivery_deadline' => now()->addDays(5)->toDateString(),
            'status'            => 'unassigned',
            'flag_reason'       => null,
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $productId,
            'quantity'   => 5,
        ]);

        return $order;
    }

    // ══════════════════════════════════════════════════════════════
    //  ORDERS — READ
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 1. test_worker_can_view_all_orders
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_view_all_orders(): void
    {
        $token     = $this->actingAsWorker();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Seed a couple of orders of different types so the list is non-empty
        $this->createUnassignedOrder($manager->id, $product->id);
        $this->createUnassignedOrder($manager->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/worker/orders');

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
    // 2. test_worker_can_view_assigned_orders
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_view_assigned_orders(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $order = $this->createOrderAssignedTo($manager->id, $worker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/worker/orders/assigned');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'total',
            'last_page',
            'per_page',
        ]);

        // The authenticated worker's order must appear in the list
        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains(
            $order->id,
            $returnedIds,
            'The assigned order belonging to the authenticated worker must appear in the list.'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // 3. test_worker_cannot_see_other_workers_orders_in_assigned
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_see_other_workers_orders_in_assigned(): void
    {
        // Authenticated worker
        ['user' => $authWorker, 'token' => $token] = $this->actingAsWorkerWithUser();

        // A different worker
        $otherWorker = User::create([
            'name'                  => 'Other Worker',
            'age'                   => 25,
            'phone_number'          => '3333333333',
            'location'              => 'Branch C',
            'emergency_contact'     => 'Emergency C',
            'email'                 => 'otherworker_' . uniqid() . '@ims.com',
            'username'              => 'otherworker_' . uniqid(),
            'password'              => Hash::make('password'),
            'role'                  => 'worker',
            'is_active'             => true,
            'is_temporary_password' => false,
        ]);

        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Order assigned to the OTHER worker
        $otherOrder = $this->createOrderAssignedTo($manager->id, $otherWorker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/worker/orders/assigned');

        $response->assertStatus(200);

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains(
            $otherOrder->id,
            $returnedIds,
            'Orders assigned to another worker must NOT appear in the authenticated worker\'s assigned list.'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // 4. test_worker_can_get_single_assigned_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_get_single_assigned_order(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $order = $this->createOrderAssignedTo($manager->id, $worker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/worker/orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $order->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // 5. test_worker_cannot_get_another_workers_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_get_another_workers_order(): void
    {
        // Authenticated worker
        ['token' => $token] = $this->actingAsWorkerWithUser();

        // A different worker who owns the order
        $otherWorker = User::create([
            'name'                  => 'Other Worker',
            'age'                   => 25,
            'phone_number'          => '3333333333',
            'location'              => 'Branch C',
            'emergency_contact'     => 'Emergency C',
            'email'                 => 'otherworker_' . uniqid() . '@ims.com',
            'username'              => 'otherworker_' . uniqid(),
            'password'              => Hash::make('password'),
            'role'                  => 'worker',
            'is_active'             => true,
            'is_temporary_password' => false,
        ]);

        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);
        $otherOrder = $this->createOrderAssignedTo($manager->id, $otherWorker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/worker/orders/{$otherOrder->id}");

        $response->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════
    //  ORDERS — DELIVER
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 6. test_worker_can_mark_order_as_delivered
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_mark_order_as_delivered(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id, 50);

        $order = $this->createOrderAssignedTo($manager->id, $worker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/worker/orders/{$order->id}/deliver");

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'delivered',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 7. test_delivery_deducts_stock
    // ──────────────────────────────────────────────────────────────
    public function test_delivery_deducts_stock(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();

        // current_stock = 50, order item quantity = 5 → expect 45 after delivery
        $product = $this->createProduct($warehouse->id, 50);
        $order   = $this->createOrderAssignedTo($manager->id, $worker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/worker/orders/{$order->id}/deliver");

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id'            => $product->id,
            'current_stock' => 45, // 50 - 5
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 8. test_worker_cannot_deliver_unassigned_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_deliver_unassigned_order(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Order is unassigned (worker_id = null).
        // The controller first filters: WHERE worker_id = auth()->id()
        // An unassigned order has worker_id = null, so it is NEVER found
        // by that query — the controller returns 404 before it can reach
        // the status !== 'assigned' check that would return 400.
        $order = $this->createUnassignedOrder($manager->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/worker/orders/{$order->id}/deliver");

        $response->assertStatus(404);
    }

    // ──────────────────────────────────────────────────────────────
    // 9. test_worker_cannot_deliver_another_workers_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_deliver_another_workers_order(): void
    {
        // Authenticated worker
        ['token' => $token] = $this->actingAsWorkerWithUser();

        // A different worker who owns the order
        $otherWorker = User::create([
            'name'                  => 'Other Worker',
            'age'                   => 25,
            'phone_number'          => '4444444444',
            'location'              => 'Branch D',
            'emergency_contact'     => 'Emergency D',
            'email'                 => 'otherworker2_' . uniqid() . '@ims.com',
            'username'              => 'otherworker2_' . uniqid(),
            'password'              => Hash::make('password'),
            'role'                  => 'worker',
            'is_active'             => true,
            'is_temporary_password' => false,
        ]);

        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);
        $order     = $this->createOrderAssignedTo($manager->id, $otherWorker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/worker/orders/{$order->id}/deliver");

        $response->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════
    //  ORDERS — FLAG
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 10. test_worker_can_flag_assigned_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_flag_assigned_order(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $order = $this->createOrderAssignedTo($manager->id, $worker->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/worker/orders/{$order->id}/flag", [
                'flag_reason' => 'Address not found.',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'flagged',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 11. test_flagged_order_has_correct_status_and_reason
    // ──────────────────────────────────────────────────────────────
    public function test_flagged_order_has_correct_status_and_reason(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        $order = $this->createOrderAssignedTo($manager->id, $worker->id, $product->id);

        $flagReason = 'Customer refused delivery.';

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/worker/orders/{$order->id}/flag", [
                'flag_reason' => $flagReason,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('orders', [
            'id'          => $order->id,
            'status'      => 'flagged',
            'flag_reason' => $flagReason,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // 12. test_worker_cannot_flag_unassigned_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_flag_unassigned_order(): void
    {
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();
        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // Order is unassigned (worker_id = null).
        // The controller first filters: WHERE worker_id = auth()->id()
        // An unassigned order has worker_id = null, so it is NEVER found
        // by that query — the controller returns 404 before it can reach
        // the status !== 'assigned' check that would return 400.
        $order = $this->createUnassignedOrder($manager->id, $product->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/worker/orders/{$order->id}/flag", [
                'flag_reason' => 'Some reason.',
            ]);

        $response->assertStatus(404);
    }

    // ══════════════════════════════════════════════════════════════
    //  STOCK
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 13. test_worker_can_view_stock
    // ──────────────────────────────────────────────────────────────
    public function test_worker_can_view_stock(): void
    {
        $token     = $this->actingAsWorker();
        $warehouse = $this->createWarehouse();
        $this->createProduct($warehouse->id);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/worker/stock');

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
    //  RBAC — FORBIDDEN ACTIONS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 14. test_worker_cannot_create_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_create_order(): void
    {
        $token     = $this->actingAsWorker();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);

        // POST to the manager order-creation endpoint — worker must be denied
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/manager/orders', [
                'recipient_name'    => 'Should Fail',
                'recipient_contact' => '0500000000',
                'delivery_deadline' => now()->addDays(3)->toDateString(),
                'items'             => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(403);
    }

    // ──────────────────────────────────────────────────────────────
    // 15. test_worker_cannot_assign_order
    // ──────────────────────────────────────────────────────────────
    public function test_worker_cannot_assign_order(): void
    {
        // Authenticated as worker
        ['user' => $worker, 'token' => $token] = $this->actingAsWorkerWithUser();

        $manager   = $this->createManager();
        $warehouse = $this->createWarehouse();
        $product   = $this->createProduct($warehouse->id);
        $order     = $this->createUnassignedOrder($manager->id, $product->id);

        // PATCH to the manager assign endpoint — worker must be denied
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/manager/orders/{$order->id}/assign", [
                'worker_id' => $worker->id,
            ]);

        $response->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════
    //  RBAC — AUTHENTICATION AND AUTHORISATION GUARDS
    // ══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────────────────────
    // 16. test_unauthenticated_user_cannot_access_worker_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_unauthenticated_user_cannot_access_worker_endpoints(): void
    {
        // No Authorization header — the request is completely anonymous.
        $response = $this->getJson('/api/worker/orders');

        $response->assertStatus(401);
    }

    // ──────────────────────────────────────────────────────────────
    // 17. test_manager_cannot_access_worker_endpoints
    // ──────────────────────────────────────────────────────────────
    public function test_manager_cannot_access_worker_endpoints(): void
    {
        $managerToken = $this->actingAsManager();

        $response = $this->withHeader('Authorization', "Bearer {$managerToken}")
            ->getJson('/api/worker/orders');

        $response->assertStatus(403);
    }
}
