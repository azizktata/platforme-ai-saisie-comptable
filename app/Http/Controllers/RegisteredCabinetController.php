<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterCabinetRequest;
use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredCabinetController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterCabinetRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $cabinetName = $data['cabinet_name'];
        $slugBase = Str::slug($cabinetName) ?: 'cabinet';
        unset($data['cabinet_name']);

        $admin = DB::transaction(function () use ($data, $cabinetName, $slugBase): User {
            $slug = $slugBase;
            $suffix = 2;

            while (Cabinet::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase.'-'.$suffix++;
            }

            $cabinet = Cabinet::query()->create([
                'name' => $cabinetName,
                'slug' => $slug,
            ]);

            return $cabinet->users()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'cabinet_role' => User::CABINET_ROLE_ADMIN,
            ]);
        });

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
