<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Support\RecipeStock;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IngredientReportController extends Controller
{
    // Ingredient stock, what was bought, used in sales and wasted over a date range
    public function usage(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin() && !in_array($user->role, ['manager', 'store_keeper'], true)) {
            abort(403, 'Not allowed to view this report.');
        }

        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);
        $from = Carbon::parse($request->input('from', now()->subDays(6)->toDateString()))->startOfDay();
        $to   = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);

        $ingredients = Product::where('product_type', Product::INGREDIENT_TYPE)
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->orderBy('name')
            ->get();
        $ids = $ingredients->pluck('id');

        $movements = StockMovement::whereIn('product_id', $ids)
            ->whereBetween('moved_at', [$from, $to])
            ->get(['product_id', 'movement_type', 'reference_type', 'quantity'])
            ->groupBy('product_id');

        $rows = $ingredients->map(function (Product $p) use ($movements, $days) {
            $m = $movements->get($p->id, collect());
            $sum = fn($type, $ref = null, $notRef = null) => (float) $m
                ->where('movement_type', $type)
                ->when($ref, fn($c) => $c->where('reference_type', $ref))
                ->when($notRef, fn($c) => $c->where('reference_type', '!=', $notRef))
                ->sum('quantity');

            $purchased = $sum('IN', null, RecipeStock::REVERSAL);
            $used      = $sum('OUT', RecipeStock::USED) - $sum('IN', RecipeStock::REVERSAL);
            $wasted    = $sum('DAMAGE');
            $perDay    = $used / $days;

            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'unit'           => $p->unit_type,
                'stock'          => (float) $p->stock_quantity,
                'min_stock'      => (int) $p->min_stock_level,
                'low'            => $p->stock_quantity <= $p->min_stock_level,
                'purchased'      => round($purchased, 3),
                'used'           => round($used, 3),
                'wasted'         => round($wasted, 3),
                'avg_per_day'    => round($perDay, 3),
                'days_left'      => $perDay > 0 ? round($p->stock_quantity / $perDay, 1) : null,
                'cost_used'      => round($used * (float) $p->purchase_price, 2),
            ];
        })->values();

        return response()->json([
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'days'    => $days,
            'rows'    => $rows,
            'by_dish' => $this->byDish($from, $to, $ids, $ingredients),
            'totals'  => [
                'cost_used'  => round($rows->sum('cost_used'), 2),
                'low_count'  => $rows->where('low', true)->count(),
            ],
        ]);
    }

    // Dishes sold in the range and the ingredients they consumed (from the current recipes)
    private function byDish(Carbon $from, Carbon $to, $ingredientIds, $ingredients): array
    {
        $recipes = RecipeItem::whereIn('ingredient_id', $ingredientIds)->get()->groupBy('product_id');
        if ($recipes->isEmpty()) return [];

        $sold = SaleItem::whereIn('product_id', $recipes->keys())
            ->whereHas('sale', fn($q) => $q->where('status', 'completed')->whereBetween('sold_at', [$from, $to]))
            ->selectRaw('product_id, SUM(quantity) as qty')
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');

        $names = Product::whereIn('id', $recipes->keys())->pluck('name', 'id');
        $byId  = $ingredients->keyBy('id');

        return $sold->map(function ($qty, $productId) use ($recipes, $names, $byId) {
            return [
                'dish'        => $names[$productId] ?? "#{$productId}",
                'sold'        => (float) $qty,
                'ingredients' => $recipes[$productId]->map(fn($l) => [
                    'name' => $byId[$l->ingredient_id]->name ?? '—',
                    'unit' => $byId[$l->ingredient_id]->unit_type ?? '',
                    'used' => round($l->quantity * (float) $qty, 3),
                ])->values(),
            ];
        })->sortByDesc('sold')->values()->all();
    }
}
