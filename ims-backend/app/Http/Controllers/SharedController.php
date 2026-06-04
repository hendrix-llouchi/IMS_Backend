<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\Product;

class SharedController extends Controller
{
    public function getAllWarehouses()
    {
        $warehouses = Warehouse::paginate(20);
        return response()->json($warehouses);
    }

    public function getWarehouse($id)
    {
        $warehouse = Warehouse::find($id);
        if (!$warehouse) {
            return response()->json(['message' => 'Warehouse not found.'], 404);
        }
        return response()->json(['warehouse' => $warehouse]);
    }

    public function getAllProducts()
    {
        $products = Product::with('warehouse:id,name')->paginate(20);
        return response()->json($products);
    }

    public function getProduct($id)
    {
        $product = Product::with('warehouse:id,name')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }
        return response()->json(['product' => $product]);
    }
}