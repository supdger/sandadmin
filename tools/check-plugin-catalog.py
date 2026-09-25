#!/usr/bin/env python3
"""Reject catalog releases that the installed SandAdmin host cannot install."""

import argparse
import configparser
import hashlib
import io
import json
from pathlib import Path
import re
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
VERSION = re.compile(r"^\d+\.\d+\.\d+$")


def host_version():
    app = ROOT / 'server/vendor/supdger/sand-core/server/plugin/sandadmin/config/app.php'
    text = app.read_text()
    match = re.search(r"['\"]version['\"]\s*=>\s*['\"](\d+\.\d+\.\d+)['\"]", text)
    if not match:
        raise ValueError(f'Cannot read host version from {app}')
    return match.group(1)


def package_version():
    lock = json.loads((ROOT / 'server/composer.lock').read_text())
    package = next(item for item in lock['packages'] if item['name'] == 'supdger/sand-package')
    return tuple_version(package['version'])


def tuple_version(value):
    if not isinstance(value, str) or not VERSION.fullmatch(value):
        raise ValueError(f'Invalid version: {value}')
    return tuple(map(int, value.split('.')))


def asset_bytes(repository, tag, name):
    request = urllib.request.Request(
        f'https://api.github.com/repos/{repository}/releases/tags/{tag}',
        headers={'Accept': 'application/vnd.github+json', 'User-Agent': 'sandadmin-catalog-check'},
    )
    with urllib.request.urlopen(request, timeout=20) as response:
        release = json.load(response)
    asset = next((item for item in release['assets'] if item['name'] == name), None)
    if asset is None:
        raise ValueError(f'Release asset not found: {repository} {tag} {name}')
    request = urllib.request.Request(asset['browser_download_url'], headers={'User-Agent': 'sandadmin-catalog-check'})
    with urllib.request.urlopen(request, timeout=60) as response:
        return response.read(5 * 1024 * 1024 + 1)


def metadata(content):
    with zipfile.ZipFile(io.BytesIO(content)) as archive:
        names = archive.namelist()
        if 'info.ini' not in names:
            raise ValueError('ZIP root info.ini is missing')
        if 'host-payload.json' in names:
            raise ValueError('Current SandPackage cannot install host-payload.json')
        info = archive.read('info.ini').decode('utf-8-sig')
    parser = configparser.ConfigParser(interpolation=None, inline_comment_prefixes=('#', ';'))
    parser.read_string('[plugin]\n' + info)
    values = {}
    for key in ('app', 'version', 'support'):
        value = parser['plugin'].get(key, '').strip()
        if len(value) >= 2 and value[0] == value[-1] and value[0] in ("'", '"'):
            value = value[1:-1]
        values[key] = value
    return values


def supported(support, host):
    tokens = support.split('|')
    major = host.split('.')[0]
    if not tokens or any(not re.fullmatch(r'(?:\d+\.x|>=\d+\.\d+\.\d+)', token) for token in tokens):
        return False
    return any(
        token == f'{major}.x' or
        (token.startswith('>=') and tuple_version(host) >= tuple_version(token[2:]))
        for token in tokens
    )


def check(catalog, host, verify_assets):
    seen = set()
    for plugin in catalog['plugins']:
        app = plugin['app']
        if app in seen:
            raise ValueError(f'Duplicate plugin: {app}')
        seen.add(app)
        repository = plugin['repository']
        for release in plugin['versions']:
            label = f"{app} {release['version']}"
            minimum = tuple_version(release['host_min'])
            maximum = tuple_version(release['host_max']) if 'host_max' in release else None
            if maximum == minimum:
                raise ValueError(f'{label}: host_max must not pin one patch version')
            if host < minimum or (maximum and host > maximum):
                raise ValueError(f'{label}: catalog excludes host {".".join(map(str, host))}')
            if not verify_assets:
                continue
            content = asset_bytes(repository, release['tag'], release['asset'])
            if len(content) > 5 * 1024 * 1024:
                raise ValueError(f'{label}: ZIP exceeds 5 MiB')
            if hashlib.sha256(content).hexdigest() != release['sha256']:
                raise ValueError(f'{label}: SHA-256 mismatch')
            info = metadata(content)
            if info.get('app') != app or info.get('version') != release['version']:
                raise ValueError(f'{label}: ZIP identity does not match catalog')
            if info.get('support', '').startswith('>='):
                if package_version() < (0, 1, 5):
                    raise ValueError(f'{label}: installed SandPackage does not support minimum-version declarations')
                if tuple_version(info['support'][2:]) != minimum:
                    raise ValueError(f'{label}: ZIP support minimum differs from catalog host_min')
            if not supported(info.get('support', ''), '.'.join(map(str, host))):
                raise ValueError(f'{label}: ZIP support does not include host')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--verify-assets', action='store_true')
    args = parser.parse_args()
    catalog = json.loads((ROOT / 'catalog.json').read_text())
    check(catalog, tuple_version(host_version()), args.verify_assets)
    print('Plugin catalog compatibility passed')
