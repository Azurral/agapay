<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Models\Intervention;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Figma 470:785 (Admin) / 430:1745 (Data Encoder) inventory monitoring, and the 470:986 Record Stock Movement modal. */
class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $q = mb_substr($request->queryText('q'), 0, 100);

        return view('inventory.index', [
            'title' => $request->user()->role?->slug === Role::ADMIN ? 'INVENTORY MANAGEMENT' : 'INVENTORY MONITORING',
            'items' => InventoryItem::withStock()
                ->when($q !== '', fn ($query) => $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [
                    '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($q)).'%',
                ]))
                // Creation order, as in Figma 470:785 (new items appear at the end).
                ->orderBy('id')->get(),
            'allItems' => InventoryItem::with('interventions:id,inventory_item_id')->orderBy('name')->get(),
            'movements' => InventoryMovement::with('item')->latest('movement_date')->latest('id')->limit(10)->get(),
        ]);
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        $itemId = Validator::make($request->only('inventory_item_id'), [
            'inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')],
        ], ['inventory_item_id.*' => 'Choose an item from the list.'])->validateWithBag('inventory')['inventory_item_id'];

        $string = fn (string $key) => is_string($value = $request->input($key)) ? $value : null;

        try {
            $movement = $this->inventory->record(
                InventoryItem::findOrFail($itemId), (string) $string('direction'), $request->input('quantity'),
                $string('movement_date'), $string('notes'), $request->user(),
            );
        } catch (ValidationException $e) {
            // The service names the date "date"; the modal's field is "movement_date".
            $errors = collect($e->errors())->mapWithKeys(fn ($messages, $key) => [$key === 'date' ? 'movement_date' : $key => $messages])->all();

            return back()->withInput()->withErrors($errors, 'inventory');
        } catch (InsufficientStock $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()], 'inventory');
        }

        return back()->with('status', "{$movement->signedLabel()} · {$movement->item->name} recorded.");
    }

    public function storeItem(Request $request): RedirectResponse
    {
        return $this->saveItem($request, new InventoryItem);
    }

    public function updateItem(Request $request, InventoryItem $item): RedirectResponse
    {
        return $this->saveItem($request, $item);
    }

    /**
     * Add or edit an item (no Figma frame: a modal under the mirrored layout, spec §2).
     * `interventions` is the full set of programs that hand this item out; programs left out are unlinked.
     */
    private function saveItem(Request $request, InventoryItem $item): RedirectResponse
    {
        $request->merge(collect(['name', 'unit', 'unit_label'])
            ->filter(fn (string $key) => is_string($request->input($key)))
            ->mapWithKeys(fn (string $key) => [$key => trim(preg_replace('/\s+/', ' ', $request->input($key)))])->all());

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100', function (string $attribute, string $value, \Closure $fail) use ($item) {
                $taken = InventoryItem::whereRaw('LOWER(name) = ?', [mb_strtolower($value)])->whereKeyNot($item->id)->exists();
                if ($taken) {
                    $fail('An item with this name already exists.');
                }
            }],
            'unit' => ['required', 'string', 'max:20'],
            'unit_label' => ['required', 'string', 'max:40'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0', 'max:99999'],
            'interventions' => ['nullable', 'array'],
            'interventions.*' => ['integer', Rule::exists('interventions', 'id')],
        ], ['interventions.*.exists' => 'Choose programs from the list.'])->validateWithBag('item');

        DB::transaction(function () use ($item, $data, $request) {
            $oldLinks = $item->exists ? $item->interventions()->orderBy('id')->get()->map->sourcedName()->all() : [];
            $item->fill(Arr::only($data, ['name', 'unit', 'unit_label', 'low_stock_threshold']))->save();   // Auditable: Added / Updated

            $wanted = array_map('intval', $data['interventions'] ?? []);
            Intervention::where('inventory_item_id', $item->id)->whereNotIn('id', $wanted)->update(['inventory_item_id' => null]);
            Intervention::whereIn('id', $wanted)->update(['inventory_item_id' => $item->id]);

            $newLinks = $item->interventions()->orderBy('id')->get()->map->sourcedName()->all();
            if ($oldLinks !== $newLinks && $item->wasRecentlyCreated === false) {
                AuditLogger::record('Updated Inventory Item', $item, null, ['interventions' => $oldLinks], ['interventions' => $newLinks], $request->user());
            }
        });

        return back()->with('status', "{$item->name} saved.");
    }
}
