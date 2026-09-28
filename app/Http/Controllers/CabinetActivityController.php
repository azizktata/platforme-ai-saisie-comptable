<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCabinetActivityRequest;
use Illuminate\Http\RedirectResponse;

class CabinetActivityController extends Controller
{
    public function store(StoreCabinetActivityRequest $request): RedirectResponse
    {
        $request->user()->cabinet->activities()->create($request->validated());

        return to_route('companies.index');
    }
}
