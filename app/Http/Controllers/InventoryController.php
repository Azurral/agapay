<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStock;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Role;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'allItems' => InventoryItem::orderBy('name')->get(['id', 'name', 'unit_label']),
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
}
