{{-- Difficulty + drill-count controls shared by every mission-generating form. --}}
<label>
    <span class="sr-only">Difficulty</span>
    <select name="difficulty" class="input w-auto">
        <option>Easy</option>
        <option>Medium</option>
        <option>Hard</option>
    </select>
</label>
<label class="flex items-center gap-2 text-sm text-muted">
    Drills
    <input type="number" name="drills" value="5" min="5" max="10" class="input w-20">
</label>
<label class="flex items-center gap-2 text-sm text-muted">
    Boss fights
    <input type="number" name="bosses" value="1" min="1" max="3" class="input w-20">
</label>
