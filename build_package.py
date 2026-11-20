#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
build_package.py — збірка пакета MajorDoMo «TabCalorie».

Створює dist/tabcalorie.tgz зі структурою відносно кореня MajorDoMo:

    modules/tabcalorie/**
    templates/tabcalorie/**
    languages/tabcalorie_*.php
    scripts/cycle_tabcalorie.php
    img/**                     (зокрема img/modules/tabcalorie.png)
    modinfo.json
    README*                    (README.md, README.en.md, README.ru.md)
    LICENSE
    NOTICE.md
    CHANGELOG.md
    docs/**
    build_package.py           (скрипт збірки, для довідки)

Виключення: .git, __pycache__, *.pyc/*.pyo, .env, config.local.json,
а також сам каталог dist (щоб архів не вкладався сам у себе).

Використання:
    python3 build_package.py

Після збірки друкується список файлів із розмірами та підсумковий розмір архіву.
"""

from __future__ import annotations

import os
import sys
import tarfile
from pathlib import Path
from typing import Iterable, List, Tuple

# --------------------------------------------------------------------------- #
# Налаштування
# --------------------------------------------------------------------------- #

SOURCE_ROOT = Path(__file__).resolve().parent
DIST_DIR = SOURCE_ROOT / "dist"
ARCHIVE_PATH = DIST_DIR / "tabcalorie.tgz"

# Каталоги, які додаються цілком (шлях відносно SOURCE_ROOT == arcname у tar).
TREES: Tuple[str, ...] = (
    "modules/tabcalorie",
    "templates/tabcalorie",
    "img",
    "docs",
)

# Файли/шаблони, які додаються окремо (arcname == відносний шлях).
FILES: Tuple[str, ...] = (
    "modinfo.json",
    "README*",
    "LICENSE",
    "NOTICE.md",
    "CHANGELOG.md",
    "languages/tabcalorie_*.php",
    "scripts/cycle_tabcalorie.php",
    "build_package.py",
)

# Обов'язкові файли — без них пакет некоректний.
REQUIRED: Tuple[str, ...] = (
    "modinfo.json",
    "img/modules/tabcalorie.png",
)

EXCLUDED_DIR_NAMES = {".git", "__pycache__", "dist", ".idea", ".vscode"}
EXCLUDED_FILE_NAMES = {".env", "config.local.json"}
EXCLUDED_SUFFIXES = (".pyc", ".pyo", ".swp", ".tmp")


# --------------------------------------------------------------------------- #
# Доміжні функції
# --------------------------------------------------------------------------- #

def is_excluded(path: Path) -> bool:
    """Повертає True, якщо шлях має бути виключений із пакета."""
    parts = set(path.parts)
    if parts & EXCLUDED_DIR_NAMES:
        return True
    if path.name in EXCLUDED_FILE_NAMES:
        return True
    if path.name.endswith(EXCLUDED_SUFFIXES):
        return True
    return False


def arcname_for(path: Path) -> str:
    """Ім'я файлу в архіві — POSIX-шлях відносно кореня репозиторію."""
    return path.relative_to(SOURCE_ROOT).as_posix()


def collect_tree(rel_dir: str, found: dict) -> None:
    """Рекурсивно збирає файли каталогу, обрізаючи виключені підкаталоги."""
    base = SOURCE_ROOT / rel_dir
    if not base.exists():
        print("[WARN] Каталог відсутній, пропущено: {}".format(rel_dir))
        return
    if not base.is_dir():
        print("[WARN] Не каталог, пропущено: {}".format(rel_dir))
        return

    for root, dirs, files in os.walk(str(base)):
        # Обрізаємо виключені каталоги прямо під час обходу.
        dirs[:] = [d for d in dirs if d not in EXCLUDED_DIR_NAMES]
        root_path = Path(root)
        for name in files:
            src = root_path / name
            if is_excluded(src):
                continue
            found[arcname_for(src)] = src


def collect_globs(pattern: str, found: dict) -> None:
    """Додає файли, що відповідають шаблону (наприклад, README*)."""
    matches = sorted(SOURCE_ROOT.glob(pattern))
    matched = False
    for src in matches:
        if not src.is_file() or is_excluded(src):
            continue
        found[arcname_for(src)] = src
        matched = True
    if not matched:
        # Для необов'язкових шаблонів це нормально; для прямих файлів — попередження.
        if "*" not in pattern and "?" not in pattern:
            print("[WARN] Файл відсутній, пропущено: {}".format(pattern))


def human_size(num_bytes: int) -> str:
    """Людсько читальний розмір."""
    value = float(num_bytes)
    for unit in ("B", "KiB", "MiB", "GiB"):
        if value < 1024.0 or unit == "GiB":
            return "{:.0f} {}".format(value, unit) if unit == "B" else "{:.1f} {}".format(value, unit)
        value /= 1024.0
    return "{:.1f} GiB".format(value)


# --------------------------------------------------------------------------- #
# Збірка
# --------------------------------------------------------------------------- #

def build() -> int:
    found: dict = {}

    for tree in TREES:
        collect_tree(tree, found)
    for pattern in FILES:
        collect_globs(pattern, found)

    # Перевірка обов'язкових файлів.
    missing = [name for name in REQUIRED if name not in found]
    if missing:
        print("[ERROR] Відсутні обов'язкові файли:", file=sys.stderr)
        for name in missing:
            print("        - {}".format(name), file=sys.stderr)
        return 1

    # Детермінований порядок записів у архіві.
    ordered = [(name, found[name]) for name in sorted(found)]

    DIST_DIR.mkdir(parents=True, exist_ok=True)
    if ARCHIVE_PATH.exists():
        ARCHIVE_PATH.unlink()

    total_unpacked = 0
    with tarfile.open(str(ARCHIVE_PATH), mode="w:gz", format=tarfile.PAX_FORMAT) as tar:
        for arcname, src in ordered:
            info = tar.gettarinfo(str(src), arcname=arcname)
            # Фіксуємо метадані для відтворюваності збірки.
            info.mtime = 0
            info.uid = 0
            info.gid = 0
            info.uname = ""
            info.gname = ""
            if info.isreg():
                with src.open("rb") as fh:
                    tar.addfile(info, fh)
            else:
                tar.addfile(info)
            total_unpacked += src.stat().st_size

    archive_size = ARCHIVE_PATH.stat().st_size

    print("Пакет: {}".format(ARCHIVE_PATH.relative_to(SOURCE_ROOT).as_posix()))
    print("-" * 72)
    for arcname, src in ordered:
        print("  {:60s} {:>10s}".format(arcname, human_size(src.stat().st_size)))
    print("-" * 72)
    print("Файлів: {}".format(len(ordered)))
    print("Розпакований розмір: {} ({:,} bytes)".format(human_size(total_unpacked), total_unpacked))
    print("Розмір архіву:       {} ({:,} bytes)".format(human_size(archive_size), archive_size))
    print("Готово.")
    return 0


if __name__ == "__main__":
    sys.exit(build())
