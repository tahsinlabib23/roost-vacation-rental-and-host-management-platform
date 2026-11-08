<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function create()
    {
        // Fetch the taxonomy directly from the config file
        $amenityCategories = config('amenities.categories');
        
        return view('properties.create', compact('amenityCategories'));
    }
}