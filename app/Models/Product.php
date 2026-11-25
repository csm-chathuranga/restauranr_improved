<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OpenBottle;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'sku', 'item_number', 'barcode', 'name', 'description', 'category_id', 'product_type',
        'brand', 'unit_type', 'base_unit', 'selling_variants', 'shot_variants',
        'purchase_price', 'selling_price',
        'stock_quantity', 'min_stock_level', 'image', 'image_public_id', 'is_active', 'supplier_id',
        'bottle_deposit_required', 'bottle_deposit_amount', 'tax_setting_id',
    ];

    protected $casts = [
        'is_active'               => 'boolean',
        'bottle_deposit_required' => 'boolean',
        'bottle_deposit_amount'   => 'float',
        'purchase_price'          => 'float',
        'selling_price'           => 'float',
        'stock_quantity'          => 'float',
        'shot_variants'           => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function taxSetting()
    {
        return $this->belongsTo(TaxSetting::class);
    }

    public function openBottles()
    {
        return $this->hasMany(OpenBottle::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getLowStockAttribute(): bool
    {
        return $this->stock_quantity <= $this->min_stock_level;
    }

    public const INGREDIENT_TYPE = 'Ingredient';

    // Ingredients that go into a dish; stock-tracked but never sold directly
    public function recipeItems()
    {
        return $this->hasMany(RecipeItem::class, 'product_id');
    }

    // Recipe lines where this product is the ingredient
    public function usedInRecipes()
    {
        return $this->hasMany(RecipeItem::class, 'ingredient_id');
    }

    public function isIngredient(): bool
    {
        return strtolower((string) $this->product_type) === strtolower(self::INGREDIENT_TYPE);
    }

    // How many servings the current ingredient stock allows; null when the dish has no recipe
    public function servingsAvailable(): ?int
    {
        $lines = $this->relationLoaded('recipeItems') ? $this->recipeItems : $this->recipeItems()->with('ingredient:id,stock_quantity')->get();
        $lines = $lines->filter(fn($l) => $l->quantity > 0 && $l->ingredient);
        if ($lines->isEmpty()) return null;

        return (int) max(0, floor($lines->map(fn($l) => $l->ingredient->stock_quantity / $l->quantity)->min()));
    }

    public function isStockTracked(): bool
    {
        return strtolower((string) $this->product_type) !== 'food';
    }

    public function getImageAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return asset('storage/' . ltrim($value, '/'));
    }
}
