<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The active study track (AI Security vs Laravel Web). Switched only from the
 * dashboard UI toggle — never .env, never a CLI flag. Session carries it for
 * the browser; the flat file lets terminal commands (no session available)
 * see whatever the browser last set, so `php artisan llm:test` etc. stay in
 * sync with the active track without their own switch.
 */
final class StudyMode
{
    public const AI_SECURITY = 'ai_security';
    public const LARAVEL_WEB = 'laravel_web';

    private const PATH = 'active-mode.txt';

    private const LABELS = [
        self::AI_SECURITY => 'AI Security Engineer',
        self::LARAVEL_WEB => 'Laravel & React Web Developer',
    ];

    /** Compact form for the dashboard toggle and other tight UI spots. */
    private const SHORT_LABELS = [
        self::AI_SECURITY => 'AI Security',
        self::LARAVEL_WEB => 'Laravel Web',
    ];

    /** Role name with its article — for sentences like "training to become {this}". */
    private const ROLE_WITH_ARTICLE = [
        self::AI_SECURITY => 'an AI Security Engineer',
        self::LARAVEL_WEB => 'a Laravel & React Web Developer',
    ];

    private const ROADMAP_DESC = [
        self::AI_SECURITY => 'a fixed 21-phase roadmap',
        self::LARAVEL_WEB => 'your Laravel & React roadmap',
    ];

    /** Mega Portfolio Project rubric category — the one graded, track-specific dimension. */
    private const QUALITY_DIMENSION = [
        self::AI_SECURITY => 'AI Security Controls',
        self::LARAVEL_WEB => 'Code Architecture & Best Practices',
    ];

    /** Boss Fight foreshadowing hint — illustrative examples of "a later roadmap phase". */
    private const FORESHADOW_EXAMPLES = [
        self::AI_SECURITY => 'model routing, input validation gates, log triage, secret handling',
        self::LARAVEL_WEB => 'queues, API design, deployment pipelines',
    ];

    private const EVALUATOR_TITLE = [
        self::AI_SECURITY => 'Senior AI Security Architect',
        self::LARAVEL_WEB => 'Senior Laravel & React Architect',
    ];

    private const PROBLEM_LABEL = [
        self::AI_SECURITY => 'AI Security',
        self::LARAVEL_WEB => 'Web Application',
    ];

    private const PROBLEM_EXAMPLES = [
        self::AI_SECURITY => 'threat detection, prompt injection defense, red teaming, vector store security, agent sandbox security, ML model auditing, guardrails, LLM observability, or security log analysis',
        self::LARAVEL_WEB => 'a booking or reservation system, an inventory/order-management tool, a content platform with roles and permissions, a REST API consumed by a frontend, or an authenticated reporting dashboard',
    ];

    private const PROBLEM_RATIONALE = [
        self::AI_SECURITY => 'why an AI Security Engineer builds this, real-world attack vectors or vulnerabilities addressed',
        self::LARAVEL_WEB => 'why a Laravel & React Web Developer builds this, real-world user needs or business requirements addressed',
    ];

    private const CONSIDERATIONS = [
        self::AI_SECURITY => 'security considerations',
        self::LARAVEL_WEB => 'code quality considerations',
    ];

    private const ARCHITECT_TITLE = [
        self::AI_SECURITY => 'security architect',
        self::LARAVEL_WEB => 'software architect',
    ];

    private const FLAW_LABEL = [
        self::AI_SECURITY => 'security flaws',
        self::LARAVEL_WEB => 'bugs or design flaws',
    ];

    /**
     * Hard rule: every drill, example and variable must be set inside the
     * track's real work — never generic textbook data. Learner requirement:
     * "every word should prepare me for it".
     */
    private const DOMAIN_RULE = [
        self::AI_SECURITY => <<<'RULE'
            HARD RULE — DOMAIN ONLY. Every word must train the learner for real AI Security Engineering work. This applies to every drill and Boss Fight: its one-line description, its Setup data, its variable names, its Task, its Output, and every example and solution. Set each one inside a real AI security task, for example: LLM request/response logs, prompt-injection and jailbreak payloads, API keys and secrets, model routing configs, guardrail allow/deny lists, user roles and permissions for an AI tool, rate limits and token usage, embedding/vector-store records, agent tool calls, audit logs, alert triage, red-team findings, CVE or scan reports.
            NEVER use generic textbook data: no fruits, animals, colours, students and marks/grades, shopping prices or carts, (x, y) points or coordinates, batteries, weather, a/b/c letters, foo/bar, or "sum of numbers" for its own sake.
            Even the simplest Level-1 drill that teaches one plain construct (a `for` loop, `.items()`, unpacking) must use security data — e.g. a list of log levels, a dict of API-key scopes, tuples of (ip, port). If a concept is pure syntax, keep the syntax drill but put it inside such a scenario. This rule overrides every other example in this prompt.
            RULE,
        self::LARAVEL_WEB => <<<'RULE'
            HARD RULE — DOMAIN ONLY. Every word must train the learner for real Laravel & React web development work. This applies to every drill and Boss Fight: its one-line description, its Setup data, its variable names, its Task, its Output, and every example and solution. Set each one inside a real web-app task, for example: routes and controllers, HTTP requests/responses, form validation, users, roles and permissions, orders, bookings, products in an inventory, database rows and Eloquent queries, JSON API payloads, sessions and auth, middleware, queued jobs, config arrays, React props/state and components.
            NEVER use generic textbook data: no fruits, animals, colours, students and marks/grades, (x, y) points or coordinates, batteries, weather, a/b/c letters, foo/bar, or "sum of numbers" for its own sake.
            Even the simplest Level-1 drill that teaches one plain construct (a loop, an array function, destructuring) must use web-app data — e.g. a list of route names, an array of order rows, a user record. If a concept is pure syntax, keep the syntax drill but put it inside such a scenario. This rule overrides every other example in this prompt.
            RULE,
    ];

    /**
     * Hard rule: the learner is a beginner — every drill description says in
     * plain words what the job is and what the code must do, and every new
     * word gets its meaning in brackets the first time it appears.
     */
    private const DESCRIPTION_RULE = [
        self::AI_SECURITY => <<<'RULE'
            HARD RULE — BEGINNER-CLEAR DESCRIPTIONS. The learner is a beginner in both coding and security. The description under every drill and Boss Fight heading is 2–3 short, plain sentences — never one dense technical line:
            1. The scene: what real job this is and why an AI Security Engineer does it.
            2. What the code must do, in everyday words: what data you start with, what you loop over or check, what you build or print. Start this sentence with "You will…".
            3. Explain every technical word the FIRST time it appears, with a short meaning in brackets — security words and Python words alike. Examples: triage (sorting alerts by how urgent they are), scope (what a key is allowed to do), environment (a separate copy of the system — prod for real users, dev for testing), key rotation (replacing old keys with new ones), prompt injection (text that tries to trick the AI into ignoring its rules), payload (the data sent in a request), invert a dictionary (swap keys and values).
            BAD: "Keys are stored per environment; build one map from every service to its key, across all environments."
            GOOD: "Your company keeps its API keys (secret passwords that let a program use an AI service) separately for each environment (a separate copy of the system — prod for real users, dev for testing). You will loop through every environment and build ONE dictionary where each service name points to its key. In an incident, this lets you find any service's key in one place."
            The same bracket rule applies to Task bullets, the Boss Fight scenario and the Solutions: never use a new word without its meaning.
            RULE,
        self::LARAVEL_WEB => <<<'RULE'
            HARD RULE — BEGINNER-CLEAR DESCRIPTIONS. The learner is a beginner in coding and web development. The description under every drill and Boss Fight heading is 2–3 short, plain sentences — never one dense technical line:
            1. The scene: what real job this is and why a web developer does it.
            2. What the code must do, in everyday words: what data you start with, what you loop over or check, what you build or return. Start this sentence with "You will…".
            3. Explain every technical word the FIRST time it appears, with a short meaning in brackets — web words and language words alike. Examples: route (the URL address the app answers), controller (the class that handles a request), middleware (a check that runs before the controller), payload (the data sent in a request), validation (checking the input is allowed before saving it), Eloquent (Laravel's tool for reading and saving database rows).
            BAD: "Group orders by status and return counts per status from the payload."
            GOOD: "Your shop's admin page needs to show how many orders are in each status (pending, paid, shipped). You will loop through the order rows from the request payload (the data sent to the server) and build an array that counts how many orders have each status."
            The same bracket rule applies to Task bullets, the Boss Fight scenario and the Solutions: never use a new word without its meaning.
            RULE,
    ];

    /**
     * One finished milestone of a fix plan, shown to the model as the LAYOUT to copy (never the content): a free model
     * asked for step blocks without an example tends to hand back specs and checks only.
     */
    private const FIX_PLAN_EXAMPLE = [
        self::AI_SECURITY => <<<'EXAMPLE'
            ## Fix Milestone 1: Guard the scanner against odd input

            **Recommended support level: FULL** — these fixes change the main scanning path.

            ### F1.1 Reject input that is not text

            **Spec:**
            - `scan_prompt(text)` returns `[]` when `text` is not a `str`
            - it never raises `AttributeError`

            #### Step 1 — Add the type guard

            **Goal:**
            - open `scanner.py` and find the function `scan_prompt(text)` *→ this is the function that crashed when a log held a list instead of text*
            - as the first line of its body, write an `if` whose condition is `not isinstance(text, str)` *→ `isinstance(value, str)` returns `True` only for text; `not` flips it, so the `if` runs for every other type*
            - inside the `if`, write `return []` *→ no text means nothing to scan, and `return` ends the function right there*

            **Where:** `scanner.py`, inside `scan_prompt`, the first lines of the body

            #### Step 2 — Keep a test for it

            **Goal:**
            - in `tests/test_scanner.py`, define the method `test_non_text_input_is_ignored(self)` inside `TestScanner` *→ a test keeps the fix from breaking silently later*
            - call `self.assertEqual(scan_prompt(["hi"]), [])` *→ `assertEqual(a, b)` fails the test when `a` is not equal to `b`*

            **Where:** `tests/test_scanner.py`, inside `class TestScanner`, below the last test

            **✅ Checks for F1.1:**
            - `python -c "from scanner import scan_prompt; print(scan_prompt(['hi']))"` → `[]`
            - `python -m unittest discover -s tests -t . -v` → the last line is `OK`

            **If a check fails:**
            - `AttributeError: 'list' object has no attribute 'lower'` → the guard sits below the line that uses `text`; move it to the very top

            *Commit message:* `Guard scan_prompt against non-text input`
            EXAMPLE,
        self::LARAVEL_WEB => <<<'EXAMPLE'
            ## Fix Milestone 1: Reject bad order ids

            **Recommended support level: FULL** — these fixes change a public route.

            ### F1.1 Only numbers reach the controller

            **Spec:**
            - `GET /orders/abc` answers `404` and never reaches `OrderController@show`
            - `GET /orders/12` still works

            #### Step 1 — Constrain the route

            **Goal:**
            - open `routes/web.php` and find the route `/orders/{order}` *→ this is the route that crashed on an id that is not a number*
            - chain `->whereNumber('order')` onto it *→ `whereNumber` makes Laravel match the route only when the value is digits, so `/orders/abc` is answered with a 404 before any controller code runs*

            **Where:** `routes/web.php`, on the `/orders/{order}` route

            #### Step 2 — Keep a test for it

            **Goal:**
            - in `tests/Feature/OrderTest.php`, define the method `test_non_numeric_order_id_is_not_found()` *→ a test keeps the fix from breaking silently later*
            - call `$this->get('/orders/abc')->assertNotFound()` *→ `assertNotFound()` fails the test unless the response status is 404*

            **Where:** `tests/Feature/OrderTest.php`, inside `class OrderTest`, below the last test

            **✅ Checks for F1.1:**
            - `php artisan test --filter=OrderTest` → every test passes
            - open `/orders/abc` in the browser → the 404 page, no error trace

            **If a check fails:**
            - the test returns `500` → the route constraint is on a different route than the one the test calls; check the route name

            *Commit message:* `Reject non-numeric order ids`
            EXAMPLE,
    ];

    /** The active track's example milestone for fix plans (layout only). */
    public static function fixPlanExample(?string $mode = null): string
    {
        return self::FIX_PLAN_EXAMPLE[self::normalize($mode ?? self::current())];
    }

    /** The active track's BEGINNER-CLEAR DESCRIPTIONS hard rule — appended to requests whose system prompt can't carry the token. */
    public static function descriptionRule(?string $mode = null): string
    {
        return self::DESCRIPTION_RULE[self::normalize($mode ?? self::current())];
    }

    /** The active track's DOMAIN ONLY hard rule — appended to requests whose system prompt can't carry the token. */
    public static function domainRule(?string $mode = null): string
    {
        return self::DOMAIN_RULE[self::normalize($mode ?? self::current())];
    }

    /** The active track for this request (browser) or the last one set (CLI). */
    public static function current(): string
    {
        return self::normalize(session('study_mode', self::fromFile()));
    }

    /** Durable fallback so `php artisan` commands (no session) know the last mode set in the browser. */
    public static function fromFile(): string
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::PATH) ? self::normalize(trim($disk->get(self::PATH))) : self::AI_SECURITY;
    }

    /** Switch tracks: writes both session (browser) and file (CLI durability). Never touches stored study data. */
    public static function set(string $mode): void
    {
        $mode = self::normalize($mode);

        session(['study_mode' => $mode]);
        Storage::disk('local')->put(self::PATH, $mode);
    }

    public static function label(?string $mode = null): string
    {
        return self::LABELS[self::normalize($mode ?? self::current())];
    }

    public static function shortLabel(?string $mode = null): string
    {
        return self::SHORT_LABELS[self::normalize($mode ?? self::current())];
    }

    public static function roleWithArticle(?string $mode = null): string
    {
        return self::ROLE_WITH_ARTICLE[self::normalize($mode ?? self::current())];
    }

    /** @return array<string, string> mode => label, for rendering the dashboard toggle */
    public static function all(): array
    {
        return self::LABELS;
    }

    /** Substitutes the {{TRACK_*}} placeholders in a system prompt with the active track's wording. */
    public static function renderPrompt(string $raw, ?string $mode = null): string
    {
        $mode = self::normalize($mode ?? self::current());

        return strtr($raw, [
            '{{TRACK_ROLE}}' => self::LABELS[$mode],
            '{{TRACK_ROLE_WITH_ARTICLE}}' => self::ROLE_WITH_ARTICLE[$mode],
            '{{TRACK_ROADMAP_DESC}}' => self::ROADMAP_DESC[$mode],
            '{{TRACK_QUALITY_DIMENSION}}' => self::QUALITY_DIMENSION[$mode],
            '{{TRACK_FORESHADOW_EXAMPLES}}' => self::FORESHADOW_EXAMPLES[$mode],
            '{{TRACK_EVALUATOR_TITLE}}' => self::EVALUATOR_TITLE[$mode],
            '{{TRACK_PROBLEM_LABEL}}' => self::PROBLEM_LABEL[$mode],
            '{{TRACK_PROBLEM_EXAMPLES}}' => self::PROBLEM_EXAMPLES[$mode],
            '{{TRACK_PROBLEM_RATIONALE}}' => self::PROBLEM_RATIONALE[$mode],
            '{{TRACK_CONSIDERATIONS}}' => self::CONSIDERATIONS[$mode],
            '{{TRACK_ARCHITECT_TITLE}}' => self::ARCHITECT_TITLE[$mode],
            '{{TRACK_FLAW_LABEL}}' => self::FLAW_LABEL[$mode],
            '{{TRACK_DOMAIN_RULE}}' => self::DOMAIN_RULE[$mode],
            '{{TRACK_DESCRIPTION_RULE}}' => self::DESCRIPTION_RULE[$mode],
        ]);
    }

    private static function normalize(string $mode): string
    {
        return array_key_exists($mode, self::LABELS) ? $mode : self::AI_SECURITY;
    }
}
