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
        ]);
    }

    private static function normalize(string $mode): string
    {
        return array_key_exists($mode, self::LABELS) ? $mode : self::AI_SECURITY;
    }
}
