{{-- Difficulty + drill-count controls shared by every mission-generating form. --}}
<div class="flex flex-wrap items-center gap-2.5">
    <label class="flex items-center gap-1.5 text-xs font-medium text-muted">
        <span>Diff:</span>
        <select name="difficulty" class="input !w-auto !py-1 !px-2.5 text-xs font-medium bg-surface">
            <option>Easy</option>
            <option>Medium</option>
            <option>Hard</option>
        </select>
    </label>
    <label class="flex items-center gap-1.5 text-xs font-medium text-muted">
        <span>Drills:</span>
        <input type="number" name="drills" value="5" min="0" max="20" class="input !w-16 !py-1 !px-2 text-xs text-center font-mono font-medium">
    </label>
    <label class="flex items-center gap-1.5 text-xs font-medium text-muted">
        <span>Boss:</span>
        <input type="number" name="bosses" value="1" min="0" max="10" class="input !w-16 !py-1 !px-2 text-xs text-center font-mono font-medium">
    </label>
    <label class="flex items-center gap-1.5 text-xs font-medium text-muted" title="Full hand-holding: every Task step tells you the exact line to write and what it does">
        <input type="checkbox" name="learner_mode" value="1" @checked(session('learner_mode'))>
        <span>Learner mode</span>
    </label>
</div>
