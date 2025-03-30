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
            'user_id' => 'required|exists:users,id',
        ]);

        Shift::updateOrCreate(
            ['user_id' => $request->user_id, 'date' => $request->date],
            ['shift' => $request->shift]
        );

        return redirect()->back();
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'shifts' => 'required|array',
            'shifts.*.date' => 'required|date',
            'shifts.*.shift' => 'required|string',
            'shifts.*.action' => 'required|in:add,remove',
            'shifts.*.user_id' => 'required|exists:users,id',
        ]);

        foreach ($request->shifts as $shiftData) {
            if ($shiftData['action'] === 'add') {
                Shift::updateOrCreate(
                    [
                        'user_id' => $shiftData['user_id'],
                        'date' => $shiftData['date'],
                        'shift' => $shiftData['shift']
                    ],
                );
            } else {
                // Remove the shift
                Shift::where('user_id', $shiftData['user_id'])
                    ->where('date', $shiftData['date'])
                    ->where('shift', $shiftData['shift'])
                    ->delete();
            }
        }

        return redirect()->back();
    }

    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $shifts = Shift::with('user')
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        return response()->json(['shifts' => $shifts]);
    }
}
