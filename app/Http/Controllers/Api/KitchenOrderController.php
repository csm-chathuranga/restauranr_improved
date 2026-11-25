<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $kots = KitchenOrder::with(['items.product:id,name', 'sale:id,invoice_number,table_number,order_type,guest_count'])
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when($request->filled('station'), fn($q) => $q->where('station', $request->input('station')))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('sale_id'), fn($q) => $q->where('sale_id', $request->input('sale_id')))
            ->when($request->filled('priority'), fn($q) => $q->where('priority', (bool) $request->input('priority')))
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($kots);
    }

    public function show(Request $request, KitchenOrder $kitchenOrder)
    {
        $this->checkBranchAccess($kitchenOrder);
        return response()->json($kitchenOrder->load(['items.product:id,name', 'sale:id,invoice_number,table_number,order_type']));
    }

    public function updateStatus(Request $request, KitchenOrder $kitchenOrder)
    {
        $this->checkBranchAccess($kitchenOrder);

        $data = $request->validate([
            'status' => 'required|in:new,preparing,ready,served',
        ]);

        $kitchenOrder->update(['status' => $data['status']]);
        AuditLog::record('kot_status_updated', "KOT {$kitchenOrder->kot_number} → {$data['status']}", $kitchenOrder);

        return response()->json($kitchenOrder);
    }

    public function togglePriority(Request $request, KitchenOrder $kitchenOrder)
    {
        $this->checkBranchAccess($kitchenOrder);

        if (!$request->user()->isAdmin() && !in_array($request->user()->role, ['manager', 'cashier'], true)) {
            abort(403, 'Not authorized to set priority.');
        }

        $kitchenOrder->update(['priority' => !$kitchenOrder->priority]);

        return response()->json(['priority' => $kitchenOrder->priority]);
    }

    public function reprint(Request $request, KitchenOrder $kitchenOrder)
    {
        $this->checkBranchAccess($kitchenOrder);

        if (!$request->user()->isAdmin() && !in_array($request->user()->role, ['manager', 'cashier'], true)) {
            abort(403, 'Reprint requires manager or cashier role.');
        }

        $kitchenOrder->update(['printed_at' => now()]);
        AuditLog::record('kot_reprinted', "KOT {$kitchenOrder->kot_number} reprinted", $kitchenOrder);

        return response()->json($kitchenOrder->load(['items.product:id,name', 'sale:id,invoice_number,table_number,order_type']));
    }

    public function voidItem(Request $request, KitchenOrder $kitchenOrder, KitchenOrderItem $item)
    {
        $this->checkBranchAccess($kitchenOrder);

        if ($item->kitchen_order_id !== $kitchenOrder->id) {
            abort(404);
        }

        if (!$request->user()->isAdmin() && $request->user()->role !== 'manager') {
            abort(403, 'Manager approval required to void KOT items.');
        }

        if ($item->voided_at) {
            return response()->json(['message' => 'Item already voided.'], 422);
        }

        $data = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        $item->update([
            'void_reason' => $data['void_reason'],
            'voided_by'   => $request->user()->id,
            'voided_at'   => now(),
        ]);

        AuditLog::record('kot_item_voided', "KOT {$kitchenOrder->kot_number} item voided: {$data['void_reason']}", $kitchenOrder);

        return response()->json($item->load('voidedBy:id,name'));
    }

    // Called internally from SaleController after a sale is confirmed
    public static function createForSale(Sale $sale): void
    {
        $items = SaleItem::where('sale_id', $sale->id)
            ->with('product.category')
            ->get();

        if ($items->isEmpty()) return;

        // Group items by kitchen station via their category
        $byStation = $items->groupBy(function (SaleItem $item) {
            $station = $item->product?->category?->kitchen_station ?? 'kitchen';
            return $station === 'none' ? null : $station;
        })->filter(fn($group, $station) => $station !== null);

        foreach ($byStation as $station => $stationItems) {
            // Generate KOT number: STATION-YYYYMMDD-NNN
            $prefix  = strtoupper(substr($station, 0, 3)) . '-' . now()->format('Ymd') . '-';
            $lastKot = KitchenOrder::where('branch_id', $sale->branch_id)
                ->whereDate('created_at', today())
                ->where('kot_number', 'like', $prefix . '%')
                ->max('kot_number');
            $next    = $lastKot ? ((int) substr($lastKot, -3)) + 1 : 1;
            $kotNumber = $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);

            $kot = KitchenOrder::create([
                'branch_id'  => $sale->branch_id,
                'sale_id'    => $sale->id,
                'kot_number' => $kotNumber,
                'station'    => $station,
                'status'     => 'new',
                'priority'   => false,
                'printed_at' => now(),
            ]);

            foreach ($stationItems as $saleItem) {
                KitchenOrderItem::create([
                    'kitchen_order_id'     => $kot->id,
                    'sale_item_id'         => $saleItem->id,
                    'product_id'           => $saleItem->product_id,
                    'quantity'             => $saleItem->quantity,
                    'special_instructions' => null,
                ]);
            }
        }
    }

    private function checkBranchAccess(KitchenOrder $kot): void
    {
        $user = request()->user();
        if (!$user->isAdmin() && $user->branch_id !== $kot->branch_id) {
            abort(403, 'Forbidden for this branch.');
        }
    }
}
