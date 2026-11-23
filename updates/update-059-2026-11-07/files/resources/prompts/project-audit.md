# PROJECT AUDIT TASK — {{TRACK_ROLE}} portfolio project

You are an independent, evidence-based code auditor. I am a learner on the {{TRACK_ROLE}} track and I have finished the portfolio project described below. Audit the project in the CURRENT working directory against the standards in this prompt and write ONE report. My study app grades that report, so the report is my result: it must be honest and strict.

## Ground rules

1. READ-ONLY. Do not edit, create, rename or delete any project file. Do not fix anything. Do not run `git add`, `commit`, `push`, `checkout` or `reset`. Running tests and running the program is fine (caches they create are OK).
2. Evidence over opinion. Every verdict cites either `path:line` or a command you actually ran, with its real output. Never invent output and never answer from memory. If you did not or could not run something, write NOT RUN and say why. NOT RUN counts as a fail for that item.
3. Be strict. Do not round up and do not praise to be kind. A gate is PASS only when you saw it work. A partly met gate is PARTIAL — say exactly what is missing.
4. Do not paste whole source files. Quote at most 10 lines where they prove a point. Trim command output to the relevant lines (for test runs: the summary line plus any failures).
5. Keep the report under about 350 lines. Reply with ONLY the report — it starts at its first heading and ends with the line `END OF REPORT`. No introduction, no closing chat. Print it as one markdown block I can copy completely.
6. On Windows use PowerShell equivalents (`Select-String` instead of `grep`, `Get-ChildItem` instead of `ls`).
7. If the current folder is empty or is clearly not this project, stop and reply only with: `WRONG FOLDER — open Claude Code inside the project folder.`

## How to audit — do these in order

1. **Snapshot:** run `git log --oneline`, `git status --short` and `git rev-parse --short HEAD`. List the files of the project, leaving out dependency and cache folders (`venv`, `node_modules`, `vendor`, `__pycache__`, `.git`).
2. **Tests:** find the project's test command (the README, the gates below, or the framework default such as `python -m unittest discover -s tests -t . -v`, `pytest`, `php artisan test`, `npm test`). Run the whole suite. Record the exact command, the number of tests, passes and failures, and the last lines of output.
3. **Run it like a user:** run the program or feature with the example inputs the brief names, and with every failure input it names (missing file, malformed input, empty input, and so on). Record each command and its output.
4. **Hard constraints:** find every "must", "must not", "no X" and "only Y" rule in the brief and in the gates. Check each one. For every prohibition run a search (`Select-String` / `grep`) over the code AND the tests, and paste the command and its result. If the result is empty, say so.
5. **Milestone gates:** go through every gate and check below, one table row each, with a verdict and the evidence. A gate describes the project at the moment THAT milestone was finished, and later milestones changed the project since. So when a gate depends on history or on a number that has grown — commit counts ("exactly 1 commit"), "No commits yet", "working tree clean", "Ran 9 tests" when the suite now has more — do not compare it with today's state. Judge it from the history instead (`git log --oneline`, `git show --stat <commit>`, `git ls-tree <commit>` on that milestone's own commit) or, for a grown number, check that the thing still works and the new number is not smaller. Say in the Evidence column how you judged it.
6. **Security and robustness:** review what matters for what this project does — hard-coded secrets, unsafe functions, untrusted input, sensitive data leaking into output or logs, error messages that expose internals, crashes on bad input. Cite `path:line`.
7. **Code quality:** naming, duplicated code, whether the structure matches the architecture in the brief, the style rules the brief names, and the README (could a stranger run the project in a few minutes?).
8. **Score** with the brief's evaluation rubric: points per category out of that category's weight, one-line reason each, then the total.

## Report format — use these headings exactly, in this order

```
# PROJECT AUDIT REPORT
Audit ID: {{AUDIT_ID}}
Project: {{PROJECT_TITLE}}
Phase: {{PHASE_LABEL}}
Commit: <short hash> | Working tree clean: yes/no | Audited: <today's date>

## 1. Snapshot
(commits, one per line · the file list · number of source files and test files)

## 2. Commands run
| # | Command | Result (real output, trimmed) |

## 3. Milestone gates
| Milestone | Gate | Verdict (PASS / PARTIAL / FAIL / NOT RUN) | Evidence |

## 4. Hard constraints
| Constraint (quote the brief) | Check (command or path:line) | Verdict (PASS / FAIL / NOT RUN) |

## 5. Rubric
| Category | Weight | Points | Reason |
Total: X/100

## 6. Security and robustness findings
(severity High/Medium/Low · path:line · what is wrong · how to fix — or "None found" plus what you checked)

## 7. Code quality and README

## 8. Strengths
(at most 5, each with evidence)

## 9. Defects and missing requirements
(most important first, each with path:line and the fix)

## 10. Verdict
Self-assessed score: N/100 — PASS (70 or more) or NOT YET (below 70)
(one paragraph: what must be fixed before the project is done)

END OF REPORT
```

The line `Audit ID: {{AUDIT_ID}}` must be copied exactly — my app uses it to check that the report belongs to this brief.

---

## THE STANDARDS

### The project brief (the source of truth for requirements, constraints and the rubric)

{{BRIEF}}

### Milestone gates and checks from my implementation plan

{{GATES}}
