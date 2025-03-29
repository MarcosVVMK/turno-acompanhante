<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shift' => 'required|string',
            'date' => 'required|date',
        ]);

        Shift::updateOrCreate(
            ['user_id' => Auth::id(), 'date' => $request->date],
            ['shift' => $request->shift]
        );

        return redirect()->back();
    }
}
