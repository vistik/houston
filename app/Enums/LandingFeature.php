<?php

namespace App\Enums;

/**
 * The features the landing page has a section for. The Features menu lists them, and each section's
 * heading wears the same icon and colour, so a feature looks the same wherever it shows. The value is
 * the section's id on the landing page.
 */
enum LandingFeature: string
{
    case Metrics = 'features';
    case Repository = 'repo';
    case Events = 'events';
    case Teams = 'teams';
    case Mcp = 'mcp';
    case Wall = 'wall';
    case Benchmarks = 'benchmarks';
    case Method = 'how-it-measures';

    public function label(): string
    {
        return match ($this) {
            self::Metrics => 'Metrics at a glance',
            self::Repository => 'Per repository',
            self::Events => 'Before & after',
            self::Teams => 'Per team',
            self::Mcp => 'MCP',
            self::Wall => 'Wall display',
            self::Benchmarks => 'Go / no-go',
            self::Method => 'How it measures',
        };
    }

    public function blurb(): string
    {
        return match ($this) {
            self::Metrics => '46 readouts from PRs, reviews and CI runs',
            self::Repository => 'Where every pull request spends its time',
            self::Events => 'Log a change, then see if it helped',
            self::Teams => 'Report on a team, not a repo',
            self::Mcp => 'Ask your AI assistant about your metrics',
            self::Wall => 'Put it on the big board',
            self::Benchmarks => 'Set your own benchmarks',
            self::Method => 'Every metric, in plain words',
        };
    }

    /**
     * One hue each, all about as light so none shouts. They steer clear of the green, amber and red
     * the benchmark tiers use, so a feature never reads as a verdict.
     */
    public function color(): string
    {
        return match ($this) {
            self::Metrics => '#5cc8ff',
            self::Repository => '#a78bfa',
            self::Events => '#ff9f6b',
            self::Teams => '#f47ab8',
            self::Mcp => '#2dd4bf',
            self::Wall => '#8b9cff',
            self::Benchmarks => '#b5e655',
            self::Method => '#f0d264',
        };
    }

    /** The inner SVG of a 24×24 stroke icon (Lucide shapes). */
    public function icon(): string
    {
        return match ($this) {
            self::Metrics => '<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>',
            self::Repository => '<circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><path d="M6 9v12"/>',
            self::Events => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
            self::Teams => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            self::Mcp => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>',
            self::Wall => '<rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
            self::Benchmarks => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
            self::Method => '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/>',
        };
    }
}
