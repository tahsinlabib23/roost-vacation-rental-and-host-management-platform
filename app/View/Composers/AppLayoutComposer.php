<?php

namespace App\View\Composers;

use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class AppLayoutComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();
        $isHost = $user?->role === 'host';

        $view->with([
            'user' => $user,
            'isHost' => $isHost,
            'propertyCount' => $isHost
                ? Property::query()->where('host_id', $user->id)->count()
                : 0,
        ]);
    }
}
