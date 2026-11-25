<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\RecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    // Every food item with its ingredient list, estimated cost and servings available
    public function index(Request $request)
    {
        $this->authorizeAccess($request);
        $user = $request->user();

        $dishes = Product::where('product_type', 'Food')
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%' . $request->input('search') . '%'))
            ->with(['category:id,name', 'recipeItems.ingredient:id,name,unit_type,stock_quantity,purchase_price'])
            ->orderBy('name')
            ->get()
            ->map(function (Product $p) {
                $lines = $p->recipeItems->filter(fn($l) => $l->ingredient)->map(fn($l) => [
                    'ingredient_id' => $l->ingredient_id,
                    'name'          => $l->ingredient->name,
                    'unit'          => $l->ingredient->unit_type,
                    'quantity'      => (float) $l->quantity,
                    'stock'         => (float) $l->ingredient->stock_quantity,
                ])->values();

                $cost = $p->recipeItems->filter(fn($l) => $l->ingredient)
                    ->sum(fn($l) => $l->quantity * (float) $l->ingredient->purchase_price);

                return [
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'category'      => $p->category?->name,
                    'selling_price' => (float) $p->selling_price,
                    'lines'         => $lines,
                    'cost'          => round($cost, 2),
                    'can_make'      => $p->servingsAvailable(),
                ];
            });

        return response()->json($dishes);
    }

    // Replace the recipe of one dish
    public function update(Request $request, Product $product)
    {
        $this->authorizeAccess($request);
        $user = $request->user();

        if (strtolower((string) $product->product_type) !== 'food') {
            return response()->json(['message' => 'Recipes can only be set on food items.'], 422);
        }
        if (!$user->isAdmin() && $user->branch_id !== $product->branch_id) abort(403, 'Forbidden for this branch.');

        $data = $request->validate([
            'lines'                 => 'present|array',
            'lines.*.ingredient_id' => 'required|integer|distinct|exists:products,id',
            'lines.*.quantity'      => 'required|numeric|gt:0',
        ]);

        $ids = collect($data['lines'])->pluck('ingredient_id');
        $valid = Product::whereIn('id', $ids)->where('product_type', Product::INGREDIENT_TYPE)->count();
        if ($valid !== $ids->count()) {
            return response()->json(['message' => 'Recipe lines must be ingredients.'], 422);
        }

        DB::transaction(function () use ($product, $data) {
            RecipeItem::where('product_id', $product->id)->delete();
            foreach ($data['lines'] as $line) {
                RecipeItem::create([
                    'product_id'    => $product->id,
                    'ingredient_id' => $line['ingredient_id'],
                    'quantity'      => $line['quantity'],
                ]);
            }
            AuditLog::record('recipe_updated', "Recipe updated: {$product->name} (" . count($data['lines']) . ' ingredients)', $product);
        });

        return response()->json(['message' => 'Recipe saved']);
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if (!$user->isAdmin() && !in_array($user->role, ['manager', 'store_keeper'], true)) {
            abort(403, 'Not allowed to manage recipes.');
        }
    }
}
