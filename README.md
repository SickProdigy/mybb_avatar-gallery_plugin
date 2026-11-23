# Avatar Gallery for MyBB

Adds a selectable, categorized avatar gallery to MyBB's User Control Panel. Images are discovered recursively from `images/avatars`; subdirectories become collections.

## AdminCP permission setup

Use an account allowed to edit administrator permissions, then open
**AdminCP > Users & Groups > Admin Permissions > Group Permissions**. Edit the
administrator group that should manage this plugin, enable the permission below,
and save. Granting it once to the group avoids configuring every administrator
separately. Custom administrator groups work too; group ID 4 is not required.

| Permission section | Enable |
| --- | --- |
| **Tools & Maintenance** | Can repair and randomize user avatars? |

The management page is **AdminCP > Tools & Maintenance > Avatar Gallery Repair**.
The plugin's ordinary **Configuration > Settings** page separately requires
MyBB's **Can manage settings?** permission; the permission above controls its
dedicated management/maintenance page.

For administrators intended to share a baseline, **Default Permissions** is also
available on the same Admin Permissions screen. Check whether the group uses
that baseline or has its own custom permission set before editing it.

If the owner has access but another administrator does not:

1. Open **Admin Permissions > User Permissions** and inspect that account. A
   custom user permission set takes precedence over group/default permissions;
   changing a group does not override it. Update the user's custom set, or remove
   that custom set through MyBB's permission screen if the account should inherit.
2. Check the actual primary/additional group memberships that grant AdminCP
   access, rather than the displayed group title, and review the relevant group
   permission set and any default it inherits.
3. Save the intended grant and have that administrator reopen the management
   page. Super administrators bypass these checks, so owner access alone does
   not verify another account's permissions.

These steps apply to fresh and existing installations. Plugin upgrades preserve
saved grants and restrictions; they do not automatically enable administrator
permissions. No reinstall, database edit, or new super administrator is needed.
Members and moderators without AdminCP access cannot use these pages. Plugin
permissions are checked by MyBB before page dispatch, including direct requests
and POST actions; normal AdminCP login and CSRF checks still apply.

## Features

- Discovers GIF, JPEG, JPE, BMP, PNG, and optional WebP images.
- Validates selections server-side and rejects traversal and escaping symlinks.
- Works independently of upload and remote-avatar permissions.
- Configurable filesystem directory, public path, extensions, and default collection.
- Optionally assigns each newly registered user a random gallery avatar.
- Repairs broken gallery assignments after gallery files or folders are renamed.

## Installation

1. Copy the contents of `Upload/` into the root of a MyBB 1.8 installation.
2. Add images to `images/avatars` (subdirectories become collections).
3. Install and activate **Avatar Gallery** in **ACP → Configuration → Plugins**.
4. Optionally edit **ACP → Configuration → Settings → Avatar Gallery**.

Users select an image at **User CP → Change Avatar**.

Enable **Random avatar on registration** in the Avatar Gallery settings to replace the normal default avatar with a random validated gallery image for new registrations. Existing users and registrations that already provide a custom avatar are left unchanged.

Use **Admin CP → Tools & Maintenance → Avatar Gallery Repair** for three separate actions: repair saved local or gallery paths whose files went missing after a rename or removal, give users with no avatar a random gallery choice, or replace users sharing an exact saved default/specified avatar value. Operations affecting 500 users or fewer run immediately. Larger operations are queued automatically and processed by MyBB’s task system in batches of 500, with progress shown in Admin CP. External URLs are only changed when entered as an exact match.

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
