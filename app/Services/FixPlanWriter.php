<?php

namespace App\Services;

/**
 * Writes a Learner-mode fix plan in stages — an outline, then ONE milestone per model call, then a glossary — and
 * checks the structure of every part in code.
 *
 * Why: asked for the whole plan in one reply, a free model dropped the step blocks (Goal bullets with their whys, a
 * Where line), which are the point of the format, and kept only specs and checks. A small part is much easier to get
 * right, and a part that has no real steps is rejected and asked again; if it never gets there nothing is saved.
 */
class FixPlanWriter
{
    private const MAX_MILESTONES = 5;

    private const TRIES = 3;

    /**
     * @param  \Closure(string, string): string  $request  builds the user message for one part: (part label, task text)
     * @param  \Closure(string): string  $chat  sends a user message and returns the finished reply
     * @param  string  $standards  the brief and the check list: every finding must quote real words from here
     */
    public function __construct(
        private \Closure $request,
        private \Closure $chat,
        private string $example,
        private string $title,
        private int $nextAttempt,
        private string $standards,
    ) {}

    public function write(): string
    {
        $outline = $this->outline();

        $milestones = [];
        $steps = 0;
        foreach ($outline['milestones'] as $i => $milestone) {
            $n = $i + 1;
            $md = $this->milestone($n, count($outline['milestones']), $milestone, $outline['text'], $steps + 1);
            $milestones[] = self::renumberSteps($md, $steps);
        }

        $milestones[] = $this->reauditMilestone(count($milestones) + 1, $steps + 1);
        $body = implode("\n\n", $milestones);

        $glossary = $this->glossary($body);

        return "# Fix Plan: {$this->title}\n\n**Learner mode: ON**\n\n"
            .$outline['table']."\n\n"
            .$body."\n\n"
            .($glossary !== '' ? $glossary."\n\n" : '')
            .PhaseService::END_MARKER;
    }

    // ------------------------------------------------------------------ parts

    /** @return array{table: string, milestones: list<array{name: string, level: string, why: string, rows: string}>, text: string} */
    private function outline(): array
    {
        $task = <<<'TASK'
            Write ONLY this and nothing else:

            FINDINGS
            1 | <what is wrong, in a short sentence, then its path:line> | <high, medium or low> | <3 to 10 words copied EXACTLY from the project brief or the checks list: the requirement this finding breaks>

            MILESTONES
            1 | <short milestone name> | <FULL, GUIDED, LIGHT or SPEC ONLY> | <one line: why this level> | <the finding numbers it fixes, for example 1, 2>

            Rules: one FINDINGS line per finding. The first column says WHAT is wrong in words (for example "crashes when a prompt is a list, rules.py:11-16"), not only where. The last column must be real words from the brief or the checks list — the app checks it; if nothing in the brief or the checks asks for it, write NONE there and the finding is left out of the plan because it is not graded. Then 2 to 5 MILESTONES lines, ordered by impact — failed gates and broken hard constraints first, then crashes and robustness the brief asks for, then tests, then code quality and README. Do NOT list a "Re-audit and resubmit" milestone; it is added for you. Finish with the line END OF DOCUMENT.
            TASK;

        return $this->ask('OUTLINE', $task, function (string $reply, bool $lastTry): array {
            $parts = preg_split('/^\W*MILESTONES\W*$/mi', self::clean($reply), 2);
            if (count($parts) !== 2) {
                return [false, ['no MILESTONES list — after the FINDINGS lines write a line that says MILESTONES, then 2 to 5 lines like: 1 | name | FULL | why | 1, 2']];
            }

            // findings: keep those whose quote really is in the brief / the checks list. A quote that is not there is sent
            // back (the model may have paraphrased a requirement that does exist) — only NONE, or a third failed try, drops it.
            $haystack = self::fold($this->standards);
            preg_match_all('/^\s*(\d+)\s*[|.)]\s*(.+?)\s*\|\s*(high|medium|low)\b[^|\n]*\|\s*(.+?)\s*$/mi', $parts[0], $found, PREG_SET_ORDER);
            $kept = [];
            $dropped = [];
            $again = [];
            foreach ($found as $f) {
                $quote = trim($f[4], " \t\"'“”`*");
                $problem = trim($f[2]);
                if (preg_match('/^(?:none|n\/a|-+)$/i', $quote)) {
                    $dropped[] = $problem;
                } elseif (mb_strlen($quote) >= 8 && str_contains($haystack, self::fold($quote))) {
                    $kept[(int) $f[1]] = ['problem' => $problem, 'bad' => strtolower($f[3]), 'quote' => $quote];
                    $words = preg_match_all('/[A-Za-z]{2,}/', preg_replace('/`[^`]*`|[\w.\/-]+:\d+(?:[-,]\d+)*/u', ' ', $problem));
                    if ($words < 2) {
                        $again[] = "finding {$f[1]} (`{$problem}`) says only where, not what is wrong — start it with a short sentence about what is wrong, then the path:line";
                    }
                } elseif ($lastTry) {
                    $dropped[] = $problem;
                } else {
                    $again[] = "finding {$f[1]}: «{$quote}» is not in the brief or the checks list — copy the words of the requirement it breaks EXACTLY from there (a crash on bad input is usually covered by a line about crashes or incorrect types), or write NONE in the last column if nothing in the brief asks for it";
                }
            }
            if ($again !== [] && ! $lastTry) {
                return [false, $again];
            }
            if ($kept === []) {
                return [false, ['no FINDINGS line has a quote that really appears in the brief or the checks list — write lines like: 1 | what is wrong, path:line | high | words copied exactly from the brief']];
            }

            // milestones, re-pointed at the kept findings (renumbered 1, 2, 3 ...)
            $number = array_flip(array_keys($kept));   // old number => position
            preg_match_all('/^\s*\d+\s*[|.)]\s*(.+?)\s*\|\s*(FULL|GUIDED|LIGHT|SPEC ONLY)\b[^|\n]*\|\s*(.*?)\s*(?:\|\s*(.*))?$/mi', $parts[1], $lines, PREG_SET_ORDER);
            $milestones = [];
            $assigned = [];
            foreach (array_slice($lines, 0, self::MAX_MILESTONES) as $l) {
                preg_match_all('/\d+/', $l[4] ?? '', $nums);
                $rows = [];
                foreach ($nums[0] as $old) {
                    if (isset($number[(int) $old])) {
                        $rows[$number[(int) $old] + 1] = true;
                    }
                }
                if ($rows === []) {
                    continue;   // a milestone with no graded finding left has nothing to fix
                }
                $milestones[] = [
                    'name' => rtrim(trim(str_replace('**', '', $l[1])), '.'),
                    'level' => strtoupper($l[2]),
                    'why' => trim($l[3]) !== '' ? trim($l[3]) : 'start here.',
                    'rows' => array_keys($rows),
                ];
                $assigned += $rows;
            }
            if ($milestones === []) {
                return [false, ['no MILESTONES list that fixes the findings — write 2 to 5 lines like: 1 | name | FULL | why | 1, 2 (the numbers are the FINDINGS numbers)']];
            }
            foreach (array_keys($kept) as $i => $old) {   // a graded finding no milestone took goes to the first one
                if (! isset($assigned[$i + 1])) {
                    $milestones[0]['rows'][] = $i + 1;
                }
            }

            $fixedIn = [];
            foreach ($milestones as $k => $m) {
                foreach ($m['rows'] as $row) {
                    $fixedIn[$row] ??= 'F'.($k + 1);
                }
            }
            $cell = fn (string $t): string => str_replace('|', '/', $t);
            $table = "## What we are fixing\n\n| # | Problem (with path:line) | How bad | Where the brief asks for it | Fixed in |\n|---|---|---|---|---|";
            foreach (array_values($kept) as $i => $row) {
                $table .= "\n| ".($i + 1).' | '.$cell($row['problem']).' | '.$row['bad'].' | "'.$cell($row['quote']).'" | '.($fixedIn[$i + 1] ?? 'F1').' |';
            }
            $forMilestones = $table;   // the milestone writers are never shown what was dropped, so they cannot fix it
            if ($dropped !== []) {
                $table .= "\n\n> **Not planned** — the brief and the checks do not ask for these, so they are not graded (they came from the audit report only):\n"
                    .implode("\n", array_map(fn ($d) => '> - '.$cell($d), $dropped));
            }

            $milestones = array_map(fn ($m) => ['rows' => implode(', ', array_unique($m['rows']))] + $m, $milestones);
            $listing = implode("\n", array_map(fn ($m, $i) => ($i + 1).' | '.$m['name'].' | '.$m['level'].' | '.$m['why'].' | '.$m['rows'], $milestones, array_keys($milestones)));

            return [true, ['table' => $table, 'milestones' => $milestones, 'text' => $forMilestones."\n\nMILESTONES\n".$listing]];
        });
    }

    /** @param array{name: string, level: string, why: string, rows: string} $m */
    private function milestone(int $n, int $total, array $m, string $outlineText, int $firstStep): string
    {
        $count = preg_match_all('/\d+/', $m['rows']);
        $task = <<<TASK
            The outline of the whole plan (already decided — do not change it):

            {$outlineText}

            Write ONLY Fix Milestone {$n} of {$total}: "{$m['name']}". It fixes these findings of the table (by number): {$m['rows']}. Use the exact files, functions and line numbers from the AUDIT FINDINGS; you cannot see the code, so never write the fixed code.

            Hard rules:
            - Use ONLY the names listed under "NAMES THAT EXIST IN THE PROJECT". Never call a method or use a class that is not listed (do not invent one); a NEW name is created by a step that says "define ...".
            - ONE way to do each thing. Never offer alternatives inside a Goal bullet ("or", "such as", "for example", "(or ...)"): choose one and say it.
            - Use no new library or tool: only what the project already uses.
            - Fix ONLY the findings listed for this milestone. No extra features, no "while you are there" changes, no rewriting code that works: anything that is not one of those findings can break what already passes.
            - Never think aloud. Every Goal bullet is one decided action — no questions, no "the brief says", no "we will", no hedging.
            - A check uses a command from "COMMANDS THAT WORK IN THE PROJECT" or one built from the listed names, followed by what the learner should see. Never write `...` anywhere; if you do not know the exact output, describe the visible result in words (for example "a clean error message and no traceback").

            Write these parts, in this order:
            1. `## Fix Milestone {$n}: {$m['name']}`
            2. `**Recommended support level: {$m['level']}** — {$m['why']}`
            3. EXACTLY {$count} section(s), one per finding listed above, in that order: `### F{$n}.1 <name>` for the first listed finding, `### F{$n}.2 <name>` for the second ... Each section has:
               - `**Spec:**` and a short bullet list (what must exist when the section is done, and how it behaves);
               - 2 to 4 steps. EVERY step is `#### Step <number> — <name>`, then `**Goal:**` with ONE action per bullet — each bullet ends with its why written exactly as ` *→ <why>*` — then `**Where:** <exact file and position>`. Number the steps from {$firstStep} upwards. A step is one new idea; never write a section without steps;
               - `**✅ Checks for F{$n}.k:**` — bullets, each a command in backticks → what the learner should see after the fix (at least one that fails before the fix and passes after) — and `**If a check fails:**` bullets.
            4. A last line `*Commit message:* <one line>`.
            Finish with the line END OF DOCUMENT.

            LAYOUT EXAMPLE — copy this layout exactly, but use the learner's real names and the findings, never this content:

            {$this->example}
            TASK;

        return $this->ask("MILESTONE {$n} of {$total}", $task, function (string $reply) use ($n, $m, $count): array {
            $md = self::normalizeMilestone($reply, $n, $m);
            $problems = self::milestoneProblems($md, $n, $count);

            return $problems === [] ? [true, $md] : [false, $problems];
        });
    }

    /** The last milestone is always the same, so it is written here, not by the model. */
    private function reauditMilestone(int $n, int $firstStep): string
    {
        $a = $firstStep;
        $b = $firstStep + 1;
        $next = $this->nextAttempt;

        return <<<MD
            ## Fix Milestone {$n}: Re-audit and resubmit

            **Recommended support level: SPEC ONLY** — you know every kind of step by now.

            ### F{$n}.1 Prove the fixes and submit again

            **Spec:**
            - the whole test suite passes
            - a new audit report for the fixed project is pasted into the Submit box of this page

            #### Step {$a} — Run the whole test suite

            **Goal:**
            - run the project's test command from the repo root *→ proves the fixes did not break anything that worked before*
            - read the last lines of the output *→ the summary must say that every test passed*

            **Where:** terminal, repo root

            #### Step {$b} — Audit again and resubmit

            **Goal:**
            - on this page, click **Generate Audit Prompt** in the Submit section *→ it builds a fresh prompt that also lists the problems found last time*
            - paste that prompt into Claude Code, opened in your project folder *→ Claude Code reads the code, runs the tests and prints a report; it changes nothing*
            - paste the WHOLE new report into the Submit box and submit *→ the app knows this is a resubmission and checks each earlier problem one by one*

            **Where:** this page, Submit section (bottom)

            **✅ Checks for F{$n}.1:**
            - the page says `Attempt {$next} evaluated` *→ the new report was graded as a resubmission*
            - the Attempts line at the top shows a new badge `#{$next}`

            *Commit message:* `Fix the problems found in the last evaluation`
            MD;
    }

    /** New terms used in the steps, as glossary lines; the glossary is a bonus, so a failure here never fails the plan. */
    private function glossary(string $steps): string
    {
        $task = "Here are the milestones of the plan:\n\n{$steps}\n\nWrite ONLY a glossary of the programming terms in them that a beginner has not met yet: one bullet per term, exactly like `- **term** — what it means in one line. Urdu mein: <one line in Roman Urdu>`. At most 12 bullets. Finish with the line END OF DOCUMENT.";

        try {
            return $this->ask('GLOSSARY', $task, function (string $reply): array {
                preg_match_all('/^- \*\*.+$/m', self::clean($reply), $lines);

                return $lines[0] === [] ? [false, ['no bullets like `- **term** — meaning. Urdu mein: ...`']] : [true, "## GLOSSARY\n".implode("\n", $lines[0])];
            });
        } catch (\RuntimeException) {
            return '';
        }
    }

    // ------------------------------------------------------------------ asking, with the structure checked in code

    /**
     * Asks for one part; a reply that fails the check is rejected, the problems are told to the model, and it tries again.
     *
     * @param  \Closure(string, bool): array{0: bool, 1: mixed}  $check  gets the reply and whether this is the last try; returns [true, parsed] or [false, list of problems]
     */
    private function ask(string $part, string $task, \Closure $check): mixed
    {
        $problems = [];
        $rejection = '';

        for ($try = 1; $try <= self::TRIES; $try++) {
            $reply = ($this->chat)(($this->request)($part, $task.$rejection));
            [$ok, $result] = $check($reply, $try === self::TRIES);

            if ($ok) {
                return $result;
            }

            $problems = $result;
            $rejection = "\n\n## YOUR PREVIOUS REPLY WAS REJECTED\nIt had these problems:\n"
                .implode("\n", array_map(fn ($p) => "- {$p}", $problems))
                ."\nWrite this part again from the start, complete, with every problem fixed.";
        }

        throw new \RuntimeException("The AI could not write the {$part} of the plan in the required step-by-step format after ".self::TRIES.' tries ('.implode('; ', $problems).'). Nothing was saved — click the button again.');
    }

    // ------------------------------------------------------------------ structure (static so it is easy to test)

    /** Text folded for comparing a quote with the brief: lower case, no markdown or quote marks, single spaces. */
    private static function fold(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace(['*', '`', '_', '#', '|', '"', "'", '“', '”', '’'], '', mb_strtolower($text))));
    }

    /** A model reply without its end marker and without an outer code fence. */
    private static function clean(string $reply): string
    {
        $text = trim($reply);
        $text = preg_replace('/^```[a-z]*\s*\n/i', '', $text);
        $text = preg_replace('/\n```\s*$/', '', $text);

        return trim(preg_replace('/\n*'.preg_quote(PhaseService::END_MARKER, '/').'\s*$/', '', trim($text)));
    }

    /**
     * Puts the parts the page depends on into their exact form, whatever the model wrote: the heading, the
     * `Recommended support level` line (the page's level buttons read it), and no reference solution (the app
     * cannot see the learner's code, so any "solution" would be invented).
     *
     * @param  array{name: string, level: string, why: string}  $m
     */
    public static function normalizeMilestone(string $reply, int $n, array $m): string
    {
        $md = self::clean($reply);

        if (preg_match('/^## Fix Milestone/m', $md, $x, PREG_OFFSET_CAPTURE)) {
            $md = substr($md, $x[0][1]);
        }
        $md = preg_replace('/^## Fix Milestone[^\n]*\n?/m', '', $md, 1);
        $md = preg_replace('/^\*\*Recommended support level[^\n]*\n?/mi', '', $md);
        $md = preg_replace('/^#### Reference solution.*?(?=^#{2,4} |^\*\*✅|\z)/ms', '', $md);
        $md = preg_replace('/^## GLOSSARY.*?(?=^## |\z)/ms', '', $md);   // the app writes the one glossary, at the end
        if (preg_match('/^### F\d+\./m', $md, $first, PREG_OFFSET_CAPTURE)) {
            $md = substr($md, $first[0][1]);   // an intro paragraph before the first section is not part of the layout
        }
        // the commit line always has one balanced pair of backticks, whatever the model did to them
        $md = preg_replace_callback('/^\*{1,2}Commit message:?\*{0,2}[ \t]*(.+)$/mi', fn ($c) => '*Commit message:* `'.trim($c[1], " `*\t").'`', $md);
        $md = preg_replace('/\n---\s*(?=\n|\z)/', '', $md);   // separators between sections are not part of the layout
        if (preg_match_all('/^\*Commit message:\*[^\n]*$/m', $md, $commits) > 1) {
            // a milestone is one commit: keep the last commit line, at the end
            $md = rtrim(preg_replace('/^\*Commit message:\*[^\n]*\n?/m', '', $md))."\n\n".end($commits[0]);
        }

        return "## Fix Milestone {$n}: {$m['name']}\n\n**Recommended support level: {$m['level']}** — {$m['why']}\n\n".ltrim($md);
    }

    /**
     * @param  int|null  $expectedSections  how many findings this milestone fixes: one section each, nothing extra
     * @return list<string> what is wrong with a milestone; empty when it has real steps, whys and checks
     */
    public static function milestoneProblems(string $md, int $n, ?int $expectedSections = null): array
    {
        $problems = [];

        $sections = preg_match_all('/^### F'.$n.'\.\d+/m', $md);
        if ($sections < 1) {
            $problems[] = "no section headings like `### F{$n}.1 <name>`";
        } elseif ($expectedSections !== null && $sections !== $expectedSections) {
            $problems[] = "it has {$sections} section(s) but this milestone fixes exactly {$expectedSections} finding(s) — write one `### F{$n}.k` section per listed finding and nothing for anything else (no extra features)";
        }
        if (substr_count($md, '**Spec:**') < max(1, $sections)) {
            $problems[] = 'every section must start with `**Spec:**` and a bullet list';
        }

        $blocks = preg_split('/^(?=#### Step \d+)/m', $md);
        array_shift($blocks);
        if (count($blocks) < max(1, $sections)) {
            $problems[] = 'the step blocks are missing — every section needs 2 to 4 steps written as `#### Step N — name`, each with `**Goal:**` bullets and a `**Where:**` line (specs and checks alone are not a plan)';
        }

        $noGoal = $noWhere = $bullets = $whys = $alternatives = $statements = $aloud = 0;
        $statementSpans = [];
        foreach ($blocks as $block) {
            if (! preg_match('/\*\*Goal:\*\*[ \t]*\n((?:[ \t]*\n)*(?:- [^\n]*\n?)+)/', $block, $goal)) {
                $noGoal++;

                continue;
            }
            foreach (preg_split('/\n/', $goal[1]) as $line) {
                if (str_starts_with($line, '- ')) {
                    $bullets++;
                    $whys += str_contains($line, '*→') ? 1 : 0;
                    $action = explode('*→', $line)[0];
                    $alternatives += preg_match('/\beither\b/i', $action);
                    // a bullet that asks itself a question or argues with the brief is the writer thinking aloud, not a step
                    $aloud += preg_match('/\?|\b(?:the brief says|we will|we\'ll|for now)\b/i', preg_replace('/`[^`\n]*`/', '', $action));
                    // a whole statement (`if x: return None`) is code, not a step; a condition that ends in a colon is fine
                    // (code spans are paired left to right first, so text BETWEEN two spans is never mistaken for code)
                    preg_match_all('/`([^`\n]+)`/', $action, $spans);
                    foreach ($spans[1] as $span) {
                        if (preg_match('/\b(?:if|for|while|try|except|with|def|class)\b[^\n]*:\s*\S/', $span)) {
                            $statements++;
                            $statementSpans[] = "`{$span}`";
                        }
                    }
                }
            }
            if (! preg_match('/\*\*Where:\*\*[ \t]*\S/', $block)) {
                $noWhere++;
            }
        }
        if ($noGoal > 0) {
            $problems[] = "{$noGoal} step(s) have no `**Goal:**` line followed by `- ` bullets";
        }
        if ($noWhere > 0) {
            $problems[] = "{$noWhere} step(s) have no `**Where:** <file and position>` line";
        }
        if ($statements > 0) {
            $problems[] = "{$statements} Goal bullet(s) hold a whole code statement, for example ".implode(' and ', array_slice($statementSpans, 0, 2))." — say in words what to write; a single condition that ends in a colon is the most code a bullet may show";
        }
        if ($aloud > 0) {
            $problems[] = "{$aloud} Goal bullet(s) think aloud (a question mark, \"the brief says\", \"we will\", \"for now\") — decide first, then write only the one action to do";
        }
        if ($alternatives > 0) {
            $problems[] = "{$alternatives} Goal bullet(s) offer alternatives (\"either ... or ...\") — choose ONE way and say it";
        }
        if ($bullets > 0 && $whys / $bullets < 0.7) {
            $problems[] = 'every Goal bullet must end with its why, written exactly as ` *→ why*`';
        }

        if (! preg_match('/^\*\*✅ Checks for[^\n]*\n(?:[ \t]*\n)*(?:- [^\n]*`[^\n]*\n?)+/mu', $md)) {
            $problems[] = 'no `**✅ Checks for F'.$n.'.k:**` list — bullets that each hold a command in backticks and what to see';
        }
        if (preg_match('/\b(pip3? install|composer require|npm (?:i|install)|pycodestyle|flake8|pylint|phpstan|eslint)\b/i', $md, $tool)) {
            $problems[] = "it uses a new library or tool (`{$tool[1]}`) — this project may only use what it already has; remove it";
        }
        // Allowed: `... ok` / `... FAIL` / `... ERROR` (what `python -m unittest -v` really prints after each test name) and
        // dots that follow a word, as in "try...except" or the printed message "Invalid json...". A `...` that stands alone
        // (`foo(...)`, `[a, ...]`, ` ... `) stands in for text the writer did not write.
        // The bullets under "If a check fails:" (`ImportError: cannot import name ...` → what to do) may use `...` for the part
        // of the error message that changes from run to run — the learner is only matching the start of what Python printed.
        $withoutUnittestDots = preg_replace(['/^\*\*If a check fails:?\*\*[^\n]*\n(?:[ \t]*[-*][^\n]*(?:\n|\z))*/mi','/\.\.\. (?:ok|FAIL|ERROR|skipped)\b/', '/(?<=\w)\.\.\./'], '', $md);
        if (($dots = strpos($withoutUnittestDots, '...')) !== false) {
            $near = trim(preg_replace('/\s+/', ' ', substr($withoutUnittestDots, max(0, $dots - 60), 120)));
            $problems[] = "it contains `...` near «{$near}» — never use dots as a placeholder; write the exact text, or describe the visible result in words";
        }

        return $problems;
    }

    /** Numbers the steps of one milestone continuing from $counter, so the model's own numbering never matters. */
    private static function renumberSteps(string $md, int &$counter): string
    {
        return preg_replace_callback('/^#### Step \d+/m', function () use (&$counter) {
            return '#### Step '.++$counter;
        }, $md);
    }
}
