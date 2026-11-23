<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IngestController;
use App\Models\Topic;
use App\Support\LearnerInstructions;
use App\Support\LearnerName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);
Route::post('/exercise/generate', [DashboardController::class, 'generate']);
Route::get('/exercise/{exercise}', [App\Http\Controllers\ExerciseController::class, 'show']);
Route::post('/exercise/{exercise}/submit', [App\Http\Controllers\ExerciseController::class, 'submit']);
Route::post('/exercise/{exercise}/note', [App\Http\Controllers\ExerciseController::class, 'note']);

Route::get('/chat', [App\Http\Controllers\ChatController::class, 'show']);
Route::post('/chat', [App\Http\Controllers\ChatController::class, 'send']);
Route::post('/chat/clear', [App\Http\Controllers\ChatController::class, 'clear']);
Route::post('/chat/mission', [App\Http\Controllers\ChatController::class, 'saveMission']);

Route::get('/concepts', [App\Http\Controllers\ConceptController::class, 'index']);
Route::post('/concepts/{concept}/retire', [App\Http\Controllers\ConceptController::class, 'toggleRetire']);
Route::post('/notes/{note}/resolve', [App\Http\Controllers\ConceptController::class, 'resolveNote']);

Route::get('/history', function () {
    return view('history', [
        'exercises' => App\Models\Exercise::with('attempts')->orderByDesc('date')->orderByDesc('id')->paginate(20),
    ]);
});

Route::get('/settings', function () {
    return view('settings', [
        'instructions' => LearnerInstructions::get(),
        'name' => LearnerName::get(),
    ]);
});

Route::post('/settings/profile', function (Request $request) {
    $data = $request->validate(['name' => ['nullable', 'string', 'max:60']]);
    LearnerName::set($data['name'] ?? '');

    return redirect('/settings')->with('status', 'Name saved — the dashboard will greet you with it.');
});

Route::post('/settings/reset', function (Request $request) {
    $request->validate(
        ['confirm' => ['required', 'in:RESET']],
        ['confirm.required' => 'Type RESET (all capitals) to confirm.', 'confirm.in' => 'Type RESET (all capitals) to confirm.'],
    );

    // Wipe all progress, child tables first (foreign keys). Roadmap topics stay.
    DB::transaction(function () {
        foreach (['attempts', 'exercise_concept', 'exercises', 'struggle_notes', 'chunks', 'concepts', 'transcripts'] as $table) {
            DB::table($table)->delete();
        }
    });

    LearnerInstructions::set('');
    session()->forget('chat');

    // The roadmap restarts today for a fresh learner.
    $file = File::glob(storage_path('app/roadmap/*.json'))[0];
    $data = json_decode(File::get($file), true);
    $data['meta']['start_date'] = today()->toDateString();
    File::put($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return redirect('/settings')->with('status', 'All progress erased — fresh start. The roadmap begins today.');
});

Route::post('/settings/instructions', function (Request $request) {
    $data = $request->validate(['instructions' => ['nullable', 'string', 'max:4000']]);
    LearnerInstructions::set($data['instructions'] ?? '');

    return redirect('/settings')->with('status', 'Personal instructions saved — every new mission and tutor chat will follow them.');
});

Route::post('/settings/resync', function (App\Services\RoadmapService $roadmap) {
    $count = $roadmap->syncTopics();

    return redirect('/settings')->with('status', "Roadmap resynced — {$count} topics.");
});

Route::get('/ingest', [IngestController::class, 'show']);
Route::post('/ingest/preview', [IngestController::class, 'preview']);
Route::post('/ingest/confirm', [IngestController::class, 'confirm']);

// Milestone 1 verification route: all topics seeded from the roadmap JSON.
Route::get('/debug/topics', fn () => Topic::orderBy('phase_number')->get());
