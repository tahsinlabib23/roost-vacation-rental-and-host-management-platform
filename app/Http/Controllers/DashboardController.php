<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();
        $isHost = $user->role === 'host';
        $today = now()->startOfDay();

        if ($isHost) {
            $bookings = Booking::query()
                ->whereHas('property', fn ($query) => $query->where('host_id', $user->id));

            $propertyCount = Property::query()->where('host_id', $user->id)->count();
            $properties = Property::query()
                ->where('host_id', $user->id)
                ->latest()
                ->take(4)
                ->get();

            $stats = [
                [
                    'label' => 'Published places',
                    'value' => number_format(Property::query()->where('host_id', $user->id)->where('status', 'published')->count()),
                    'note' => 'Ready for guests',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Upcoming stays',
                    'value' => number_format((clone $bookings)->where('status', 'confirmed')->whereDate('check_in', '>=', $today)->count()),
                    'note' => 'Confirmed arrivals',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Requests to review',
                    'value' => number_format((clone $bookings)->where('status', 'pending')->count()),
                    'note' => 'Waiting for your reply',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Booked this month',
                    'value' => '৳'.number_format((clone $bookings)
                        ->whereIn('status', ['confirmed', 'completed'])
                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->sum('total_amount')),
                    'note' => 'Confirmed booking value',
                    'tone' => 'plain',
                ],
            ];

            $reservations = (clone $bookings)
                ->with(['property', 'guest'])
                ->latest()
                ->take(6)
                ->get();
        } else {
            $bookings = Booking::query()->where('guest_id', $user->id);

            $propertyCount = 0;
            $properties = collect();
            $stats = [
                [
                    'label' => 'Upcoming stays',
                    'value' => number_format((clone $bookings)->where('status', 'confirmed')->whereDate('check_in', '>=', $today)->count()),
                    'note' => 'Your next adventures',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Requests in progress',
                    'value' => number_format((clone $bookings)->where('status', 'pending')->count()),
                    'note' => 'Awaiting a host reply',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Completed stays',
                    'value' => number_format((clone $bookings)->where('status', 'completed')->count()),
                    'note' => 'Places you have visited',
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Booked this month',
                    'value' => '৳'.number_format((clone $bookings)
                        ->whereIn('status', ['confirmed', 'completed'])
                        ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->sum('total_amount')),
                    'note' => 'Confirmed booking value',
                    'tone' => 'plain',
                ],
            ];

            $reservations = (clone $bookings)
                ->with('property')
                ->orderByRaw("CASE WHEN status = 'confirmed' AND check_in >= ? THEN 0 ELSE 1 END", [$today->toDateString()])
                ->orderBy('check_in')
                ->take(6)
                ->get();
        }

        $nextArrival = (clone $bookings)->with('property')
            ->where('status', 'confirmed')
            ->whereDate('check_in', '>=', $today)
            ->orderBy('check_in')
            ->first();

        return view('dashboard', [
            'user' => $user,
            'isHost' => $isHost,
            'stats' => $stats,
            'properties' => $properties,
            'propertyCount' => $propertyCount,
            'reservations' => $reservations,
            'nextArrival' => $nextArrival,
        ]);
    }
}
