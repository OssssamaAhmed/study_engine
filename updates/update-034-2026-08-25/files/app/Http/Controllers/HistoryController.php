<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\Exercise;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $conceptId = $request->query('concept_id');

        $exercises = Exercise::with(['attempts', 'concepts'])
            ->when($conceptId, fn ($q) => $q->whereHas('concepts', fn ($c) => $c->where('concepts.id', $conceptId)))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $concepts = Concept::whereHas('exercises')->orderBy('name')->get(['id', 'name']);

        return view('history', [
            'exercises' => $exercises,
            'concepts' => $concepts,
            'conceptId' => $conceptId,
        ]);
    }

    public function destroy(Exercise $exercise)
    {
        $exercise->delete();

        return redirect()->route('history')->with('status', "Deleted M{$exercise->id}.");
    }
}
