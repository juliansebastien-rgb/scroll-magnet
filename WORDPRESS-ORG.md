# WordPress.org Assets

The repository now includes ready-to-use publication assets in:

- `wordpress-org-assets/icon-128x128.png`
- `wordpress-org-assets/icon-256x256.png`
- `wordpress-org-assets/banner-772x250.png`
- `wordpress-org-assets/banner-1544x500.png`
- `wordpress-org-assets/screenshot-1.png`

These files are meant for the WordPress.org plugin directory SVN `assets/` folder, not for the runtime plugin ZIP.

## Asset mapping

- `icon-128x128.png` -> plugin icon
- `icon-256x256.png` -> retina plugin icon
- `banner-772x250.png` -> standard banner
- `banner-1544x500.png` -> retina banner
- `screenshot-1.png` -> first screenshot referenced by `readme.txt`

## Regeneration

If you want to regenerate them locally:

```bash
python3 tools/generate_wp_org_assets.py
```

## SVN Preparation

You can also generate a ready-to-import WordPress.org layout:

```bash
python3 tools/prepare_wordpress_org_svn.py
```

This creates:

- `dist/wordpress-org/trunk`
- `dist/wordpress-org/assets`
