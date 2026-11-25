<?php

namespace App\Support;

use App\Models\Product;
use App\Models\RecipeItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;

/**
 * Keeps ingredient stock in step with dishes sold.
 * Ledger rows use movement type OUT/IN with reference types RECIPE / RECIPE_REVERSAL.
 */
class RecipeStock
{
    public const USED     = 'RECIPE';
    public const REVERSAL = 'RECIPE_REVERSAL';

    // Reduce ingredient stock for every dish on a completed sale
    public static function deductForSale(Sale $sale, ?int $userId = null, ?int $branchId = null): void
    {
        $needs = [];
        $items = SaleItem::where('sale_id', $sale->id)->get(['product_id', 'quantity']);

        foreach ($items as $item) {
            $lines = RecipeItem::where('product_id', $item->product_id)->get();
            foreach ($lines as $line) {
                $needs[$line->ingredient_id] = ($needs[$line->ingredient_id] ?? 0) + ($line->quantity * (float) $item->quantity);
            }
        }

        foreach ($needs as $ingredientId => $qty) {
            $qty = round($qty, 3);
            $ingredient = Product::find($ingredientId);
            if (!$ingredient || $qty <= 0) continue;

            $ingredient->decrement('stock_quantity', $qty);
            $ingredient->refresh();

            StockLedger::record(
                $ingredient, 'OUT', $qty, $userId, $branchId,
                self::USED, $sale->id, 'Used in sale ' . $sale->invoice_number,
                ['invoice_number' => $sale->invoice_number]
            );
        }
    }

    // Put back what a deleted sale had consumed (driven by the ledger, so it matches what was taken)
    public static function restoreForSale(Sale $sale, ?int $userId = null, ?int $branchId = null): void
    {
        $alreadyReversed = StockMovement::where('reference_type', self::REVERSAL)
            ->where('reference_id', $sale->id)->exists();
        if ($alreadyReversed) return;

        $used = StockMovement::where('reference_type', self::USED)
            ->where('reference_id', $sale->id)
            ->where('movement_type', 'OUT')
            ->get();

        foreach ($used as $movement) {
            $ingredient = Product::find($movement->product_id);
            if (!$ingredient) continue;

            $ingredient->increment('stock_quantity', (float) $movement->quantity);
            $ingredient->refresh();

            StockLedger::record(
                $ingredient, 'IN', (float) $movement->quantity, $userId, $branchId,
                self::REVERSAL, $sale->id, 'Returned: sale ' . $sale->invoice_number . ' deleted'
            );
        }
    }
}
