# FIX PLAN TASK — {{TRACK_ROLE}} portfolio project

You are a senior mentor. I am an absolute beginner on the {{TRACK_ROLE}} track. I built the portfolio project "{{PROJECT_TITLE}}" and submitted it for grading (attempt {{ATTEMPT_NO}}: **{{SCORE}}/100 — {{STATUS}}**). The grader and an audit of my repository found weak points. Write me an IMPLEMENTATION PLAN that fixes exactly those weak points — and nothing else. I will build the fixes myself, step by step, then submit the project again.

## What to do first

- If you can read my repository (you are running inside my project folder), read the files named in the findings before you write anything, so every **Where:** line names the real file, class and function. Do not change any file — only print the plan.
- If you cannot read my repository, work from the findings below. Use the paths and line numbers they give, and write "check this against your own code" where you are unsure.

## Scope rules

1. Fix ONLY what the findings below list as wrong, failed, partial or missing. No new features, no refactoring of code that works, no style changes the findings did not ask for.
2. Every finding that cost me points must appear in the plan. If a finding is a deliberate choice that the brief allows, say so in the table and do not plan a fix for it. A finding is only worth a fix if it traces to a line of the brief (below) or to a gate: skip anything marked "Beyond the plan", "OUT OF SCOPE" or "Optional improvements" — those were never graded. A finding is only worth a fix if it traces to a line of the brief (below) or to a gate: skip anything marked "Beyond the plan", "OUT OF SCOPE" or "Optional improvements" — those were never graded.
3. Keep every name I already use (see "The architecture I must keep"). Do not rename or restructure anything.
4. Order the work by impact: (a) failed gates and broken hard constraints, (b) crashes, security and robustness, (c) tests, (d) code quality and README. Group the fixes into 2–5 **Fix Milestones**, each ending with a commit message, so my history stays clean.
5. The last Fix Milestone is always "Re-audit and resubmit": generate a fresh audit prompt in my study app (Phases → Submit → Generate Audit Prompt), run it in Claude Code, and paste the new report into the SAME Submit box. The app knows it is a resubmission and checks my earlier problems one by one.

## The plan format — the same one my other implementation plans use

{{PLAN_FORMAT}}

Differences for a fix plan:
- Title: `# Fix Plan: {{PROJECT_TITLE}}`, then the `**Learner mode: ON**` line.
- Skip `## Before We Start`, `## Architecture Lock`, `## Milestone Map` and `## Brief Coverage` (I already have the first two; the "What we are fixing" table below replaces the other two). Right after the title add `## What we are fixing` instead: a table with the columns `#`, `Problem (with path:line)`, `How bad`, `Fixed in`. Every row points to one Fix Milestone.
- Name the milestones `## Fix Milestone N: <name>` and the sections `### Fn.k <name>` (F1.1, F1.2, ...). Write every one of them at FULL detail and keep the `**Recommended support level: ...**` line under each milestone heading.
- Each Fix Milestone ends with: its `✅ Checks` list — each point a command and the exact output I should see *after* the fix (add a check that fails before the fix and passes after, for every defect) — a one-line commit message, and a `#### Reference solution` with the corrected code of only the functions that change.
- The `Re-audit and resubmit` milestone has no reference solution.
- Finish with `## GLOSSARY` for new terms, then the line `END OF DOCUMENT`.

## The architecture I must keep

{{ARCHITECTURE}}

## What the evaluation found

### Grader feedback (attempt {{ATTEMPT_NO}})

{{EVALUATION}}

### Audit findings from Claude Code's report

{{FINDINGS}}

{{EARLIER}}

## The project brief (for context — the requirements and constraints the fixes must respect)

{{BRIEF}}
