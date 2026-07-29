<?php

namespace App\Http\Controllers;

use App\Http\Requests\Life\MemoryStoreRequest;
use App\Http\Requests\Life\MemoryUpdateRequest;
use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemoryController extends Controller
{
    public function store(MemoryStoreRequest $request): RedirectResponse
    {
        $path = $request->hasFile('photo')
            ? $request->file('photo')->store('memories', 'public')
            : null;

        $request->user()->memories()->create([
            ...$request->safe()->except('photo'),
            'photo_path' => $path,
        ]);

        return back();
    }

    /**
     * A new photo replaces the old one (and deletes it from disk); leaving
     * the file input empty on edit keeps whatever photo was already there.
     */
    public function update(MemoryUpdateRequest $request, Memory $memory): RedirectResponse
    {
        $path = $memory->photo_path;

        if ($request->hasFile('photo')) {
            if ($memory->photo_path) {
                Storage::disk('public')->delete($memory->photo_path);
            }

            $path = $request->file('photo')->store('memories', 'public');
        }

        $memory->update([
            ...$request->safe()->except('photo'),
            'photo_path' => $path,
        ]);

        return back();
    }

    public function destroy(Request $request, Memory $memory): RedirectResponse
    {
        abort_unless($memory->user_id === $request->user()->id, 403);

        if ($memory->photo_path) {
            Storage::disk('public')->delete($memory->photo_path);
        }

        $memory->delete();

        return back();
    }
}
