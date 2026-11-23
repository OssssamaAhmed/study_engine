# Study Engine — Workspace Agent Rules

## 🔴 BLAST RADIUS PROTOCOL (MANDATORY — fires on every session)

> This rule is non-negotiable. Every agent working on this codebase must follow
> the Blast Radius Protocol before making ANY change to any file.

### What it is
Before touching code, the agent must declare the full **blast radius** of the
intended change: every file that will be modified AND every file that could
break as a side-effect. The learner (Osama) must be informed BEFORE the first
edit keystroke.

### When it applies
**Always.** Bug fix, new feature, layout tweak, refactor, config change — no
exceptions. Even "trivial" one-liner changes require a brief blast-radius
statement.

### The mandatory pre-edit declaration

Before writing or editing ANY file, output this block verbatim, filled in:

```
## ⚡ Blast Radius Declaration

**Files I will directly edit:**
- `path/to/file1` — reason
- `path/to/file2` — reason

**Files that depend on the above and could break:**
- `path/to/dependent1` — why it's affected
- (none) — if truly isolated

**Risky side-effects to watch:**
- e.g. "Renaming this method also affects the 3 callers listed above"
- e.g. "Changing this DB column requires a migration AND model cast update"

**Confidence the rest of the app stays green:** High / Medium / Low

**Recommended safe approach:**
- Step 1 ...
- Step 2 ...
```

### Rules after the declaration

1. **No silent multi-file changes.** If an edit forces a second file to change,
   declare it before touching that second file too.

2. **Stop and ask if confidence < High.** If side-effects are uncertain, stop
   and ask the user before proceeding.

3. **Run verification after every logical batch.** After finishing a related
   group of edits run `php artisan test` (if relevant) or `npm run build` (if
   CSS/JS changed) and report the result.

4. **Never migrate:fresh without explicit consent.** The dev database holds real
   study data (see CLAUDE.md). This is covered by the accidental-data-loss-prevention skill.

5. **Use the blast-radius analyzer script.** For any non-trivial change run:
   ```
   php scripts/blast_radius.php <path/to/file/being/changed>
   ```
   and include its output in the declaration.

---

## Standing project rules (always active)

- This is a **Windows 11 native** environment — no Docker, no WSL.
- **PHP 8.4** portable at `C:\Users\HP\tools\php`. Laravel 13, SQLite.
- Run app: `php artisan serve`. Build assets: `npm run build`.
- **Never rewrite** `resources/prompts/exercise-engine.md` or
  `RUNTIME_SYSTEM_PROMPT.md`.
- **Never `migrate:fresh`** — ask first.
- **Always run** `php artisan update:package "<summary>"` at the end of any
  session that changed app files (distributes updates to sister copies).
- Design tokens and component classes live in `resources/css/app.css` — use
  them, never hand-roll utility padding on ad-hoc elements.
- Embeddings are 1536-dim `text-embedding-3-small` — never change the model
  while the corpus exists.
