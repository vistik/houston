# Changelog

All notable changes to Houston are documented in this file. It is updated from the [GitHub releases](https://github.com/vistik/houston/releases) when one is published, so edit the release notes rather than this file.

## [Unreleased](https://github.com/vistik/houston/compare/v1.0.1...HEAD)

## [v1.0.1](https://github.com/vistik/houston/compare/v1.0.0...v1.0.1) - 2026-10-25

## What's Changed

- Show real, synced data in the demo by @vistik in https://github.com/vistik/houston/pull/6
- Require PHP 8.5.12, add README screenshots and fix the website by @vistik in https://github.com/vistik/houston/pull/8

**Full Changelog**: https://github.com/vistik/houston/compare/v1.0.0...v1.0.1

## [v1.0.0](https://github.com/vistik/houston/releases/tag/v1.0.0) - 2026-10-25

- 📊 **43 readouts, 5 groups.** Speed, review quality, CI health, DORA delivery and work in progress. Every metric explains itself in plain words.
- 🛰️ **Flight recorder for every repo.** See where each PR spends its time (coding, pickup, review, merge delay) and spot the one stuck on the pad.
- 👩‍🚀 **Team reports.** Report across every repo by team. History follows who was on the team, so a reshuffle won't rewrite last quarter.
- 📍 **Mission log.** Rolled out AI reviews or a new tool? Log it as an event and see if things actually got better.
- 🎯 **Go / no-go criteria.** Set your own targets, or start from 31 built-in standards (DORA, LinearB and more), and get rated elite to low.
- 🤖 **CAPCOM for your AI assistant.** A read-only MCP server lets Claude Code and friends answer "why was CI so bad last week?"
- 🖥️ **The big board.** An auto-rotating, always-dark wall display, ready for the screen at the front of the room.
- 💥 **Change failure rate.** Inferred from reverts, hotfixes and rollbacks; the pattern is configurable.
- 🚢 **Deploys tracked.** GitHub Deployments to production, with Releases as the backup.
- 🔒 **Authorized personnel only.** A single login, on by default. Set it up with `php artisan houston:install`, or `--generate` for zero questions.
- ⛽ **Easy on fuel.** Syncs every 15 minutes and pauses before GitHub's rate limit runs dry.
- 🏠 **Launches from your laptop.** MIT licensed, SQLite by default, and nothing leaves your server.
