# Avatar Gallery for MyBB

Adds a selectable, categorized avatar gallery to MyBB's User Control Panel. Images are discovered recursively from `images/avatars`; subdirectories become collections.

## Features

- Discovers PNG, JPEG, GIF, and WebP images.
- Validates selections server-side and rejects traversal and escaping symlinks.
- Works independently of upload and remote-avatar permissions.
- Configurable filesystem directory, public path, and extensions.

## Installation

1. Copy `inc/` into the matching directory of a MyBB 1.8 installation.
2. Add images to `images/avatars` (subdirectories become collections).
3. Install and activate **Avatar Gallery** in **ACP → Configuration → Plugins**.
4. Optionally edit **ACP → Configuration → Settings → Avatar Gallery**.

Users select an image at **User CP → Change Avatar**.

## Development

```bash
php -l inc/plugins/default_avatars.php
php -l inc/languages/english/default_avatars.lang.php
```

Created for [Space Cadet issue #36](https://gitea.rcs1.top/sickprodigy/mybb_space-cadet_theme/issues/36).

## License

Copyright (C) 2026 SickProdigy. Licensed under [GPL-3.0-only](LICENSE).
