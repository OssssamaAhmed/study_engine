<?php

namespace App\Http\Controllers;

use App\Models\Concept;
use App\Models\StruggleNote;
use Illuminate\Http\Request;

class ConceptController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');

        $concepts = Concept::with(['topic', 'struggleNotes' => fn ($q) => $q->where('resolved', false)])
            ->when($filter === 'due', fn ($q) => $q->where('retired', false)->whereDate('next_review_at', '<=', today()))
            ->when($filter === 'struggling', fn ($q) => $q->whereHas('struggleNotes', fn ($n) => $n->where('resolved', false)))
            ->orderBy('next_review_at')
            ->paginate(20)
            ->withQueryString();

        return view('concepts.index', ['concepts' => $concepts, 'filter' => $filter]);
    }

    public function toggleRetire(Concept $concept)
    {
        $concept->update(['retired' => ! $concept->retired]);

        return back()->with('status', $concept->name.($concept->retired ? ' retired.' : ' reactivated.'));
    }

    public function resolveNote(StruggleNote $note)
    {
        $note->update(['resolved' => true]);

        return back()->with('status', 'Struggle note resolved.');
    }
}
