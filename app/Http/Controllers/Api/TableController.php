<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    public function index()
    {
        $user = request()->user();
        $tables = Table::when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->when(request('search'), fn($q, $s) => $q->where('table_number', 'like', "%$s%"))
            ->when(request('status'), fn($q, $s) => $q->where('status', $s))
            ->orderByRaw('CAST(REGEXP_REPLACE(table_number, "[^0-9]", "") AS UNSIGNED), table_number')
            ->paginate(request('per_page', 50));
        return response()->json($tables);
    }

    public function all()
    {
        $user = request()->user();
        $tables = Table::when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->where('status', '!=', 'maintenance')
            ->orderByRaw('CAST(REGEXP_REPLACE(table_number, "[^0-9]", "") AS UNSIGNED), table_number')
            ->get();
        return response()->json($tables);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'table_number' => 'required|string|max:50|unique:tables',
            'capacity'     => 'required|integer|min:1|max:100',
            'status'       => 'nullable|in:available,occupied,reserved,maintenance',
            'notes'        => 'nullable|string',
        ]);

        $table = Table::create([
            'branch_id'    => $request->user()->branch_id,
            'table_number' => $data['table_number'],
            'capacity'     => $data['capacity'],
            'status'       => $data['status'] ?? 'available',
            'notes'        => $data['notes'] ?? null,
        ]);

        return response()->json($table, 201);
    }

    public function show(Table $table)
    {
        $this->checkBranchAccess($table);
        return response()->json($table);
    }

    public function update(Request $request, Table $table)
    {
        $this->checkBranchAccess($table);

        $data = $request->validate([
            'table_number' => 'required|string|max:50|unique:tables,table_number,' . $table->id,
            'capacity'     => 'required|integer|min:1|max:100',
            'status'       => 'nullable|in:available,occupied,reserved,maintenance',
            'notes'        => 'nullable|string',
            'pos_x'        => 'nullable|integer',
            'pos_y'        => 'nullable|integer',
            'width'        => 'nullable|integer|min:1|max:10',
            'height'       => 'nullable|integer|min:1|max:10',
            'shape'        => 'nullable|in:rectangle,circle,bar',
            'section'      => 'nullable|string|max:100',
            'rotation'     => 'nullable|integer|in:0,45,90,135,180,225,270,315',
        ]);

        $table->update([
            'table_number' => $data['table_number'],
            'capacity'     => $data['capacity'],
            'status'       => $data['status'] ?? $table->status,
            'notes'        => $data['notes'] ?? $table->notes,
            'pos_x'        => $data['pos_x'] ?? $table->pos_x,
            'pos_y'        => $data['pos_y'] ?? $table->pos_y,
            'width'        => $data['width'] ?? $table->width,
            'height'       => $data['height'] ?? $table->height,
            'shape'        => $data['shape'] ?? $table->shape,
            'section'      => array_key_exists('section', $data) ? $data['section'] : $table->section,
            'rotation'     => $data['rotation'] ?? $table->rotation,
        ]);

        return response()->json($table, 200);
    }

    public function updatePositions(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'positions'          => 'required|array|min:1',
            'positions.*.id'     => 'required|integer|exists:tables,id',
            'positions.*.pos_x'  => 'required|integer',
            'positions.*.pos_y'  => 'required|integer',
            'positions.*.width'  => 'nullable|integer|min:1|max:10',
            'positions.*.height' => 'nullable|integer|min:1|max:10',
            'positions.*.shape'  => 'nullable|in:rectangle,circle,bar',
            'positions.*.section'=> 'nullable|string|max:100',
            'positions.*.rotation'=> 'nullable|integer',
        ]);

        DB::transaction(function () use ($data, $user) {
            foreach ($data['positions'] as $pos) {
                $table = Table::find($pos['id']);
                if (!$table) continue;
                if (!$user->isAdmin() && $table->branch_id !== $user->branch_id) continue;

                $update = ['pos_x' => $pos['pos_x'], 'pos_y' => $pos['pos_y']];
                if (isset($pos['width']))    $update['width']    = $pos['width'];
                if (isset($pos['height']))   $update['height']   = $pos['height'];
                if (isset($pos['shape']))    $update['shape']    = $pos['shape'];
                if (array_key_exists('section', $pos)) $update['section'] = $pos['section'];
                if (isset($pos['rotation'])) $update['rotation'] = $pos['rotation'];

                $table->update($update);
            }
        });

        return response()->json(['message' => 'Positions saved']);
    }

    public function floorPlan(Request $request)
    {
        $user = $request->user();

        $tables = Table::when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->where('status', '!=', 'maintenance')
            ->orderByRaw('CAST(REGEXP_REPLACE(table_number, "[^0-9]", "") AS UNSIGNED), table_number')
            ->get();

        // Attach active draft sale summary to each occupied table
        $activeSalesByTable = Sale::where('status', 'draft')
            ->when(!$user->isAdmin(), fn($q) => $q->where('branch_id', $user->branch_id))
            ->with(['waiter:id,name', 'items'])
            ->get()
            ->keyBy('table_number');

        $result = $tables->map(function (Table $table) use ($activeSalesByTable) {
            $activeSale = $activeSalesByTable->get($table->table_number);
            return [
                'id'           => $table->id,
                'table_number' => $table->table_number,
                'capacity'     => $table->capacity,
                'status'       => $table->status,
                'section'      => $table->section,
                'pos_x'        => $table->pos_x,
                'pos_y'        => $table->pos_y,
                'width'        => $table->width,
                'height'       => $table->height,
                'shape'        => $table->shape,
                'rotation'     => $table->rotation,
                'active_sale'  => $activeSale ? [
                    'id'           => $activeSale->id,
                    'invoice_number'=> $activeSale->invoice_number,
                    'order_type'   => $activeSale->order_type,
                    'guest_count'  => $activeSale->guest_count,
                    'waiter'       => $activeSale->waiter?->only(['id', 'name']),
                    'item_count'   => $activeSale->items->count(),
                    'total'        => (float) $activeSale->total,
                    'created_at'   => $activeSale->created_at,
                ] : null,
            ];
        });

        // Keyed by section name (tables without a section fall under "")
        return response()->json(
            $result->groupBy(fn($t) => $t['section'] ?? '')->map(fn($tables) => $tables->values())
        );
    }

    public function destroy(Table $table)
    {
        $this->checkBranchAccess($table);
        
        $table->delete();
        return response()->json(['message' => 'Table deleted successfully'], 200);
    }

    private function checkBranchAccess(Table $table)
    {
        $user = request()->user();
        if (!$user->isAdmin() && $user->branch_id !== $table->branch_id) {
            abort(403, 'Forbidden for this branch.');
        }
    }
}
