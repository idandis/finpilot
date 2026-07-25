<?php

namespace App\Http\Controllers;

use App\Http\Requests\Passwords\PasswordEntryStoreRequest;
use App\Http\Requests\Passwords\PasswordEntryUpdateRequest;
use App\Models\PasswordEntry;
use App\Models\PasswordGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordEntryController extends Controller
{
    public function store(PasswordEntryStoreRequest $request, PasswordGroup $passwordGroup): RedirectResponse
    {
        $passwordGroup->entries()->create($request->validated());

        return back();
    }

    public function update(PasswordEntryUpdateRequest $request, PasswordEntry $passwordEntry): RedirectResponse
    {
        $passwordEntry->update($request->validated());

        return back();
    }

    public function destroy(Request $request, PasswordEntry $passwordEntry): RedirectResponse
    {
        abort_unless($passwordEntry->group->user_id === $request->user()->id, 403);

        $passwordEntry->delete();

        return back();
    }

    /**
     * The only place the decrypted password ever leaves the server: fetched
     * on demand when the user clicks "show", never embedded in the page's
     * initial Inertia payload (see PasswordGroupController::index()).
     */
    public function reveal(Request $request, PasswordEntry $passwordEntry): JsonResponse
    {
        abort_unless($passwordEntry->group->user_id === $request->user()->id, 403);

        return response()->json(['password' => $passwordEntry->password]);
    }
}
