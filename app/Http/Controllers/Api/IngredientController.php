<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Support\StockLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngredientController extends Controller
{
    public const UNITS = ['kg', 'g', 'L', 'ml', 'pcs', 'pack'];

    public function index(Request $request)
    {
        $this->authorizeAccess($request);
        $user = $request->user();

        $items = Product::where('product_type', Product::INGREDIENT_TYPE)
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->input('search') . '%'))
            ->withCount('usedInRecipes')
            ->orderBy('name')
            ->get()
            ->map(fn(Product $p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'unit'            => $p->unit_type,
                'stock_quantity'  => (float) $p->stock_quantity,
                'min_stock_level' => (int) $p->min_stock_level,
                'purchase_price'  => (float) $p->purchase_price,
                'low'             => $p->stock_quantity <= $p->min_stock_level,
                'used_in'         => $p->used_in_recipes_count,
            ]);

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess($request);
        $data = $this->validated($request, true);
        $user = $request->user();

        $ingredient = DB::transaction(function () use ($data, $user) {
            $category = Category::firstOrCreate(
                ['name' => 'Ingredients', 'branch_id' => $user->branch_id],
                ['slug' => 'ingredients', 'is_active' => true, 'kitchen_station' => 'none']
            );

            $product = Product::create([
                'sku'             => 'ING-' . uniqid('', true),
                'branch_id'       => $user->branch_id,
                'name'            => $data['name'],
                'category_id'     => $category->id,
                'product_type'    => Product::INGREDIENT_TYPE,
                'unit_type'       => $data['unit'],
                'base_unit'       => $data['unit'],
                'purchase_price'  => $data['purchase_price'] ?? 0,
                'selling_price'   => 0,
                'stock_quantity'  => $data['stock_quantity'] ?? 0,
                'min_stock_level' => $data['min_stock_level'] ?? 0,
                'is_active'       => true,
            ]);
            $product->update(['sku' => 'ING-' . str_pad($product->id, 5, '0', STR_PAD_LEFT)]);

            if ($product->stock_quantity > 0) {
                StockLedger::record($product, 'IN', (float) $product->stock_quantity, $user->id, $user->branch_id,
                    'MANUAL', null, 'Opening stock');
            }
            AuditLog::record('ingredient_created', "Ingredient {$product->name} added", $product);

            return $product;
        });

        return response()->json($ingredient, 201);
    }

    public function update(Request $request, Product $ingredient)
    {
        $this->authorizeIngredient($request, $ingredient);
        $data = $this->validated($request, false);

        $ingredient->update([
            'name'            => $data['name'],
            'unit_type'       => $data['unit'],
            'base_unit'       => $data['unit'],
            'purchase_price'  => $data['purchase_price'] ?? $ingredient->purchase_price,
            'min_stock_level' => $data['min_stock_level'] ?? $ingredient->min_stock_level,
        ]);

        return response()->json($ingredient);
    }

    // Receive new stock ("add") or correct it after a physical count ("set")
    public function stock(Request $request, Product $ingredient)
    {
        $this->authorizeIngredient($request, $ingredient);
        $data = $request->validate([
            'mode'     => 'required|in:add,set',
            'quantity' => 'required|numeric|min:0',
            'notes'    => 'nullable|string|max:255',
        ]);
        $user = $request->user();

        DB::transaction(function () use ($data, $ingredient, $user) {
            $before = (float) $ingredient->stock_quantity;
            $qty    = (float) $data['quantity'];

            if ($data['mode'] === 'add') {
                $ingredient->increment('stock_quantity', $qty);
                $ingredient->refresh();
                StockLedger::record($ingredient, 'IN', $qty, $user->id, $user->branch_id,
                    'MANUAL', null, $data['notes'] ?? 'Stock received');
            } else {
                $delta = round($qty - $before, 3);
                $ingredient->update(['stock_quantity' => $qty]);
                if ($delta != 0) {
                    StockLedger::record($ingredient, 'ADJUSTMENT', abs($delta), $user->id, $user->branch_id,
                        'MANUAL', null, ($data['notes'] ?? 'Stock count') . ' (' . ($delta > 0 ? '+' : '-') . abs($delta) . ")",
                        ['before' => $before, 'after' => $qty]);
                }
            }
            AuditLog::record('ingredient_stock', "{$ingredient->name} stock {$data['mode']} {$qty}", $ingredient);
        });

        return response()->json($ingredient->fresh());
    }

    public function destroy(Request $request, Product $ingredient)
    {
        $this->authorizeIngredient($request, $ingredient);

        if ($ingredient->usedInRecipes()->exists()) {
            return response()->json(['message' => 'This ingredient is used in a recipe. Remove it from the recipes first.'], 422);
        }

        $ingredient->delete();
        return response()->json(['message' => 'Ingredient deleted']);
    }

    private function validated(Request $request, bool $withStock): array
    {
        return $request->validate([
            'name'            => 'required|string|max:200',
            'unit'            => 'required|in:' . implode(',', self::UNITS),
            'purchase_price'  => 'nullable|numeric|min:0',
            'min_stock_level' => 'nullable|integer|min:0',
            'stock_quantity'  => $withStock ? 'nullable|numeric|min:0' : 'prohibited',
        ]);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user->isAdmin() && !in_array($user->role, ['manager', 'store_keeper'], true)) {
            abort(403, 'Not allowed to manage ingredients.');
        }
    }

    private function authorizeIngredient(Request $request, Product $ingredient): void
    {
        $this->authorizeAccess($request);
        if (!$ingredient->isIngredient()) abort(404);
        $user = $request->user();
        if (!$user->isAdmin() && $user->branch_id !== $ingredient->branch_id) abort(403, 'Forbidden for this branch.');
    }
}
