# Changelog

## 1.0.3 - 2026-10-02

- Fixed HTTP 500 errors when saving gallery avatars on strict MySQL/MariaDB installations by using the schema-safe `gallery` avatar type.
- Kept repair compatibility with legacy `default_avatar` and truncated `default_av` records.
- Expanded repair detection to cover users with no saved avatar and missing local avatar files.
- Split Admin CP maintenance into separate broken-avatar, no-avatar, and exact-match actions.
- Added hybrid processing: operations affecting 500 users or fewer run immediately, while larger operations queue through MyBB’s task system in 500-user batches.
- Added background-job progress reporting and duplicate-job protection.

## 1.0.2 - 2026-09-20

- Added an Admin CP maintenance tool to preview and repair broken gallery avatar
  assignments after gallery files or folders are renamed.

## 1.0.1 - 2026-09-17

- Added an optional setting that assigns a random gallery avatar to new registrations using the default avatar.
- Standardized the plugin website and author links used in the MyBB plugin list.

## 1.0.0 - 2026-09-08

- Added initial Avatar Gallery plugin for MyBB 1.8.
- Added recursive avatar discovery with subdirectory collections.
- Added a compact User CP picker with a category dropdown and scrollable avatar grid.
- Added configurable default collection selection with first-folder fallback.
- Added server-side validation for selected gallery avatars.
- Matched the default allowed extension list to MyBB avatar uploads while keeping WebP available as an opt-in extension.
- Added configurable gallery directory, public URL path, allowed image extensions, and default collection.
- Added separate Admin CP language file for plugin listing and settings screens.
- Added activation-time setting synchronization that preserves existing custom setting values.
- Added release packaging layout, tests, and GitHub/Gitea release workflows.
