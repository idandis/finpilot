<?php

namespace App\Http\Controllers;

use App\Http\Requests\Passwords\PasswordGroupStoreRequest;
use App\Http\Requests\Passwords\PasswordGroupUpdateRequest;
use App\Models\PasswordGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordGroupController extends Controller
{
    /**
     * Every group the user owns, each with its accounts - never including
     * the encrypted password column itself, only revealed on demand via
     * PasswordEntryController::reveal().
     */
    public function index(Request $request): Response
    {
        $groups = $request->user()->passwordGroups()
            ->orderBy('name')
            ->with(['entries' => fn ($query) => $query
                ->orderBy('platform_name')
                ->select(['id', 'password_group_id', 'platform_name', 'username', 'created_at'])])
            ->get(['id', 'user_id', 'name', 'icon']);

        return Inertia::render('Passwords/Index', [
            'groups' => $groups,
        ]);
    }

    public function store(PasswordGroupStoreRequest $request): RedirectResponse
    {
        $request->user()->passwordGroups()->create($request->validated());

        return back();
    }

    public function update(PasswordGroupUpdateRequest $request, PasswordGroup $passwordGroup): RedirectResponse
    {
        $passwordGroup->update($request->validated());

        return back();
    }

    public function destroy(Request $request, PasswordGroup $passwordGroup): RedirectResponse
    {
        abort_unless($passwordGroup->user_id === $request->user()->id, 403);

        $passwordGroup->delete();

        return back();
    }
}
