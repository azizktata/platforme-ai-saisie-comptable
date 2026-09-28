<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCabinetRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CabinetSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $cabinet = $request->user()->cabinet;
        abort_unless($cabinet !== null, 404);
        $this->authorize('update', $cabinet);

        return Inertia::render('Cabinet/Settings', [
            'cabinet' => [
                'id' => $cabinet->id,
                'name' => $cabinet->name,
                'slug' => $cabinet->slug,
            ],
        ]);
    }

    public function update(UpdateCabinetRequest $request): RedirectResponse
    {
        $cabinet = $request->user()->cabinet;
        abort_unless($cabinet !== null, 404);
        $cabinet->update($request->validated());

        return to_route('cabinet.settings.edit');
    }
}
