<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SavedDestinationController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('saved_destinations')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $id = DB::table('saved_destinations')->insertGetId($this->validated($request));
        return response()->json(['id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        $destination = DB::table('saved_destinations')->where('id', $id);
        abort_unless($destination->exists(), 404);
        $destination->update($this->validated($request, $id));
        return response()->noContent();
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $unique = Rule::unique('saved_destinations', 'name');
        if ($id !== null) {
            $unique->ignore($id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:120', $unique],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
    }
}
