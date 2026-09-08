# Avatar Gallery for MyBB

Adds a selectable, categorized avatar gallery to MyBB's User Control Panel. Images are discovered recursively from `images/avatars`; subdirectories become collections.

## Features

- Discovers GIF, JPEG, JPE, BMP, PNG, and optional WebP images.
- Validates selections server-side and rejects traversal and escaping symlinks.
- Works independently of upload and remote-avatar permissions.
- Configurable filesystem directory, public path, extensions, and default collection.

## Installation

1. Copy the contents of `Upload/` into the root of a MyBB 1.8 installation.
2. Add images to `images/avatars` (subdirectories become collections).
3. Install and activate **Avatar Gallery** in **ACP → Configuration → Plugins**.
4. Optionally edit **ACP → Configuration → Settings → Avatar Gallery**.

Users select an image at **User CP → Change Avatar**.

The gallery directory is the filesystem path MyBB scans for image files. The gallery URL path is the public browser path used to display those same images; both commonly use `images/avatars`, but they can differ on custom deployments.

## Development

```bash
php -l Upload/inc/plugins/default_avatars.php
php -l Upload/inc/languages/english/default_avatars.lang.php
php -l Upload/inc/languages/english/admin/default_avatars.lang.php
php tests/default_avatars_test.php
```

Tagged releases package `Upload`, `README.md`, `LICENSE`, and `CHANGELOG.md` into a `mybb-avatar-gallery-{version}.zip` archive.

Created for [Space Cadet issue #36](https://gitea.rcs1.top/sickprodigy/mybb_space-cadet_theme/issues/36).

## License

Copyright (C) 2026 SickProdigy. Licensed under [GPL-3.0-only](LICENSE).
