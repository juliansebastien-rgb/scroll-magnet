#!/usr/bin/env python3
import os
import shutil


BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(BASE, "dist", "wordpress-org")
TRUNK = os.path.join(OUT, "trunk")
ASSETS = os.path.join(OUT, "assets")


def reset_dir(path):
    if os.path.isdir(path):
        shutil.rmtree(path)
    os.makedirs(path, exist_ok=True)


def copy_file(src, dst):
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    shutil.copy2(src, dst)


def main():
    reset_dir(TRUNK)
    reset_dir(ASSETS)

    trunk_files = [
        ".gitignore",
        "LICENSE",
        "CHANGELOG.md",
        "README.md",
        "readme.txt",
        "scroll-magnet.php",
    ]

    for rel in trunk_files:
        copy_file(os.path.join(BASE, rel), os.path.join(TRUNK, rel))

    assets_src = os.path.join(BASE, "assets")
    shutil.copytree(assets_src, os.path.join(TRUNK, "assets"), dirs_exist_ok=True)

    wp_org_assets = os.path.join(BASE, "wordpress-org-assets")
    for name in os.listdir(wp_org_assets):
        copy_file(os.path.join(wp_org_assets, name), os.path.join(ASSETS, name))


if __name__ == "__main__":
    main()
