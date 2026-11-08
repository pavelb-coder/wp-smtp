#!/usr/bin/env python3
"""
SemVer x.y.z helper for the WP SMTP-KV plugin.

    python3 tools/version.py check          # verify all version strings match VERSION
    python3 tools/version.py show           # print the current version
    python3 tools/version.py bump patch     # or: minor, major, 2.1.0
    python3 tools/version.py pack           # build ../wp-smtp-<version>.zip

The version is stored in four places (WordPress requires literals in the
plugin header and in readme.txt, so a single dynamic source is impossible):

    VERSION                 -> single source of truth for the tooling
    wp-smtp.php             -> "Version:" header and WP_SMTP_VERSION
    readme.txt              -> "Stable tag:" and "== Changelog ==" section
    wp-smtp-<version>.zip   -> release archive name
"""

import argparse
import fnmatch
import os
import re
import sys
import zipfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
VERSION_FILE = os.path.join(ROOT, "VERSION")
SEMVER = re.compile(r"^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$")

# (relative path, regex with ONE capture group for the version, replacement template)
PLACES = [
    ("wp-smtp.php", re.compile(r"(?m)^ \* Version:\s+(\S+)\s*$"), " * Version:           {v}"),
    ("wp-smtp.php", re.compile(r"define\( 'WP_SMTP_VERSION', '(\d+\.\d+\.\d+)' \);"), "define( 'WP_SMTP_VERSION', '{v}' );"),
    ("readme.txt", re.compile(r"(?m)^Stable tag:\s+(\S+)\s*$"), "Stable tag: {v}"),
]

IGNORE = [
    ".git", ".gitignore", ".distignore", ".arena", "tools", "tests",
    "VERSION", "*.log", "*.zip", "node_modules", "vendor", ".DS_Store",
]


def fail(message):
    print("ERROR: " + message, file=sys.stderr)
    sys.exit(1)


def read_version():
    if not os.path.isfile(VERSION_FILE):
        fail("VERSION file not found")
    value = open(VERSION_FILE, encoding="utf-8").read().strip()
    if not SEMVER.match(value):
        fail("VERSION must be strict x.y.z (e.g. 1.0.0), got: %r" % value)
    return value


def read(path):
    with open(os.path.join(ROOT, path), encoding="utf-8") as f:
        return f.read()


def write(path, text):
    with open(os.path.join(ROOT, path), "w", encoding="utf-8") as f:
        f.write(text)


def check():
    version = read_version()
    ok = True
    for rel, pattern, _ in PLACES:
        matches = pattern.findall(read(rel))
        if not matches:
            print("FAIL  %-14s version string not found" % rel)
            ok = False
        elif any(m != version for m in matches):
            print("FAIL  %-14s has %s, VERSION says %s" % (rel, ", ".join(matches), version))
            ok = False
        else:
            print("OK    %-14s %s" % (rel, version))

    readme = read("readme.txt")
    if "= %s =" % version in readme:
        print("OK    %-14s changelog section = %s =" % ("readme.txt", version))
    else:
        print("FAIL  %-14s no changelog section = %s =" % ("readme.txt", version))
        ok = False

    print("Version: %s (%s)" % (version, "consistent" if ok else "INCONSISTENT"))
    return 0 if ok else 1


def bump(target):
    old = read_version()
    major, minor, patch = (int(x) for x in old.split("."))

    if SEMVER.match(target):
        new = target
    elif target == "major":
        new = "%d.0.0" % (major + 1)
    elif target == "minor":
        new = "%d.%d.0" % (major, minor + 1)
    elif target == "patch":
        new = "%d.%d.%d" % (major, minor, patch + 1)
    else:
        fail("bump target must be x.y.z, major, minor or patch")

    if new == old:
        fail("new version equals the current one")

    for rel, pattern, template in PLACES:
        text, count = pattern.subn(lambda m: template.format(v=new), read(rel))
        if not count:
            fail("could not update %s" % rel)
        write(rel, text)

    readme = read("readme.txt")
    marker = "== Changelog ==\n"
    if marker not in readme:
        fail("readme.txt has no Changelog section")
    if "= %s =" % new not in readme:
        readme = readme.replace(
            marker,
            marker + "\n= %s =\n* TODO: describe the changes.\n" % new,
            1,
        )
        write("readme.txt", readme)

    write("VERSION", new + "\n")
    print("Bumped %s -> %s" % (old, new))
    print("Fill in the changelog in readme.txt, then run: python3 tools/version.py check && python3 tools/version.py pack")
    return 0


def ignored(rel):
    parts = rel.split(os.sep)
    return any(fnmatch.fnmatch(part, pattern) for pattern in IGNORE for part in parts)


def pack():
    if check():
        fail("fix version mismatches before packing")
    version = read_version()
    archive = os.path.join(os.path.dirname(ROOT), "wp-smtp-%s.zip" % version)

    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as z:
        for base, dirs, files in os.walk(ROOT):
            dirs[:] = [d for d in dirs if not ignored(os.path.relpath(os.path.join(base, d), ROOT))]
            for name in sorted(files):
                full = os.path.join(base, name)
                rel = os.path.relpath(full, ROOT)
                if ignored(rel):
                    continue
                z.write(full, os.path.join("wp-smtp", rel))

    print("Built %s" % archive)
    with zipfile.ZipFile(archive) as z:
        for info in z.infolist():
            print("  %-55s %6d B" % (info.filename, info.file_size))
    return 0


def main():
    parser = argparse.ArgumentParser(description="WP SMTP-KV x.y.z version helper")
    parser.add_argument("command", choices=("check", "show", "bump", "pack"))
    parser.add_argument("target", nargs="?", default="patch", help="for bump: x.y.z | major | minor | patch")
    args = parser.parse_args()

    if args.command == "check":
        return check()
    if args.command == "show":
        print(read_version())
        return 0
    if args.command == "bump":
        return bump(args.target)
    return pack()


if __name__ == "__main__":
    sys.exit(main())
