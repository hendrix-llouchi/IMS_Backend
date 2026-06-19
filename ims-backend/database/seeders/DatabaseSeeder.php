<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Owner account via OwnerSeeder
        $this->call([
            OwnerSeeder::class,
        ]);

        // Retrieve seeded owner for reference if needed
        $owner = User::where('role', 'owner')->first();

        // 2. Seed Manager account
        $manager = User::create([
            'name' => 'System Manager',
            'age' => 30,
            'phone_number' => '1111111111',
            'location' => 'Main Warehouse',
            'emergency_contact' => 'N/A',
            'email' => 'manager@ims.com',
            'username' => 'manager',
            'password' => Hash::make('manager1234'),
            'role' => 'manager',
            'is_active' => true,
            'is_temporary_password' => false,
        ]);

        // 3. Seed Worker account
        $worker = User::create([
            'name' => 'System Worker',
            'age' => 25,
            'phone_number' => '2222222222',
            'location' => 'Floor A',
            'emergency_contact' => 'N/A',
            'email' => 'worker@ims.com',
            'username' => 'testworker',
            'password' => Hash::make('Password@123'),
            'role' => 'worker',
            'is_active' => true,
            'is_temporary_password' => false,
        ]);

        // 4. Seed Warehouses
        $wh1 = Warehouse::create([
            'name' => 'Main Warehouse',
            'location' => 'Head Office',
        ]);

        $wh2 = Warehouse::create([
            'name' => 'Secondary Warehouse',
            'location' => 'East Wing',
        ]);

        // 5. Seed Products
        $p1 = Product::create([
            'warehouse_id' => $wh1->id,
            'name' => 'Smartphone',
            'type' => 'Electronics',
            'description' => 'Flagship smartphone model',
            'unit' => 'pcs',
            'current_stock' => 80,
            'max_stock_level' => 200,
        ]);

        $p2 = Product::create([
            'warehouse_id' => $wh1->id,
            'name' => 'Laptop',
            'type' => 'Electronics',
            'description' => 'High performance laptop',
            'unit' => 'pcs',
            'current_stock' => 15,
            'max_stock_level' => 100, // 15% stock -> low stock alert
        ]);

        $p3 = Product::create([
            'warehouse_id' => $wh2->id,
            'name' => 'Office Desk',
            'type' => 'Furniture',
            'description' => 'Ergonomic wooden office desk',
            'unit' => 'pcs',
            'current_stock' => 40,
            'max_stock_level' => 50,
        ]);

        $p4 = Product::create([
            'warehouse_id' => $wh2->id,
            'name' => 'Office Chair',
            'type' => 'Furniture',
            'description' => 'Mesh ergonomic office chair',
            'unit' => 'pcs',
            'current_stock' => 5,
            'max_stock_level' => 50, // 10% stock -> low stock alert
        ]);

        // 6. Seed Orders
        // Order A: Unassigned
        $orderA = Order::create([
            'manager_id' => $manager->id,
            'worker_id' => null,
            'recipient_name' => 'Alice Johnson',
            'recipient_contact' => '123-456-7890',
            'delivery_deadline' => now()->addDays(2)->toDateString(),
            'status' => 'unassigned',
        ]);
        OrderItem::create([
            'order_id' => $orderA->id,
            'product_id' => $p1->id,
            'quantity' => 2,
        ]);

        // Order B: Assigned to testworker
        $orderB = Order::create([
            'manager_id' => $manager->id,
            'worker_id' => $worker->id,
            'recipient_name' => 'Bob Smith',
            'recipient_contact' => '987-654-3210',
            'delivery_deadline' => now()->addDays(1)->toDateString(),
            'status' => 'assigned',
        ]);
        OrderItem::create([
            'order_id' => $orderB->id,
            'product_id' => $p3->id,
            'quantity' => 1,
        ]);

        // Order C: Flagged
        $orderC = Order::create([
            'manager_id' => $manager->id,
            'worker_id' => $worker->id,
            'recipient_name' => 'Charlie Brown',
            'recipient_contact' => '555-555-5555',
            'delivery_deadline' => now()->subDay()->toDateString(),
            'status' => 'flagged',
            'flag_reason' => 'Broken delivery vehicle, replacement delayed.',
        ]);
        OrderItem::create([
            'order_id' => $orderC->id,
            'product_id' => $p2->id,
            'quantity' => 5,
        ]);
    }
}