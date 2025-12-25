# PHP Extension Build and Distribution Guide

**Date:** 2025-12-25
**Author:** Michael Wegener
**Status:** Research Document
**Related:** [Windows CI Options Research](./2025-12-25-windows-ci-options-research.md)

## Overview

This document describes how precompiled PHP extensions can be built and distributed, with specific focus on the php-firebird extension for Windows and Linux platforms.

---

## 1. Windows DLL Building

### 1.1 php/php-windows-builder (Recommended)

The official PHP project provides GitHub Actions for building Windows extensions:

**Repository:** https://github.com/php/php-windows-builder

#### Three Action Components

| Action | Purpose |
|--------|---------|
| `php/php-windows-builder/extension-matrix@v1` | Generates build matrix from `composer.json` |
| `php/php-windows-builder/extension@v1` | Compiles the extension DLL |
| `php/php-windows-builder/release@v1` | Uploads DLLs to GitHub releases |

#### Build Matrix Output

The extension-matrix action reads `composer.json` and generates:

- **PHP versions:** 8.2, 8.3, 8.4 (based on `require.php` constraint)
- **Architectures:** x64 (primary), x86 (optional)
- **Thread safety:** NTS (non-thread-safe), TS (thread-safe)

**Typical output:** 6-8 DLLs per release covering all PHP version/arch/TS combinations.

#### Requirements

The `composer.json` must include:

```json
{
  "type": "php-ext",
  "require": {
    "php": ">=8.2"
  }
}
```

#### Basic Workflow Example

```yaml
name: Build Windows DLLs

on:
  push:
  pull_request:
  release:
    types: [created]

permissions:
  contents: write

jobs:
  get-extension-matrix:
    runs-on: ubuntu-latest
    outputs:
      matrix: ${{ steps.extension-matrix.outputs.matrix }}
    steps:
      - uses: actions/checkout@v4
      - id: extension-matrix
        uses: php/php-windows-builder/extension-matrix@v1

  build:
    needs: get-extension-matrix
    runs-on: ${{ matrix.os }}
    strategy:
      matrix: ${{fromJson(needs.get-extension-matrix.outputs.matrix)}}
    steps:
      - uses: actions/checkout@v4
      - uses: php/php-windows-builder/extension@v1
        with:
          php-version: ${{ matrix.php-version }}
          arch: ${{ matrix.arch }}
          ts: ${{ matrix.ts }}

  release:
    runs-on: ubuntu-latest
    needs: build
    if: ${{ github.event_name == 'release' }}
    steps:
      - uses: php/php-windows-builder/release@v1
        with:
          release: ${{ github.event.release.tag_name }}
          token: ${{ secrets.GITHUB_TOKEN }}
```

### 1.2 Special Considerations for php-firebird

**Problem:** The PHP Windows SDK dependencies do NOT include Firebird client libraries.

The php-windows-builder downloads pre-built dependencies from https://windows.php.net/downloads/php-sdk/deps/ but Firebird is not among them.

#### Solution Options

**Option 1: Chocolatey Pre-Build Installation**

```yaml
- name: Install Firebird Client
  run: choco install firebird --version=4.0.6 -y
```

Available Chocolatey versions:
- firebird 3.0.4
- firebird 4.0.6
- firebird 5.0.0-5.0.3

**Option 2: Direct Download from Firebird Project**

```yaml
- name: Download Firebird SDK
  run: |
    curl -L -o firebird-sdk.zip https://github.com/FirebirdSQL/firebird/releases/download/v4.0.6/Firebird-4.0.6.3602-0-x64.zip
    unzip firebird-sdk.zip -d C:\firebird
```

**Option 3: Custom libs Parameter**

The extension action supports a `libs` parameter for custom library paths:

```yaml
- uses: php/php-windows-builder/extension@v1
  with:
    php-version: ${{ matrix.php-version }}
    arch: ${{ matrix.arch }}
    ts: ${{ matrix.ts }}
    libs: C:\firebird\lib
```

### 1.3 DLL Naming Convention

Following established patterns (e.g., FirebirdSQL/php-firebird v3.0.1):

```
php_{PHP_VERSION}-{EXTENSION_NAME}-{EXT_VERSION}-win-{ARCH}-{TS}.dll

Examples:
php_8.2.0-interbase-7.0.0-win-x64-nts.dll
php_8.2.0-interbase-7.0.0-win-x64-ts.dll
php_8.3.0-interbase-7.0.0-win-x64-nts.dll
php_8.3.0-interbase-7.0.0-win-x64-ts.dll
php_8.4.0-interbase-7.0.0-win-x64-nts.dll
php_8.4.0-interbase-7.0.0-win-x64-ts.dll
```

---

## 2. Linux Binary Building

### 2.1 Source Compilation

The standard method for Linux PHP extensions:

```bash
# Clone repository
git clone https://github.com/satwareAG/php-firebird.git
cd php-firebird

# Prepare build environment
phpize

# Configure (auto-detect Firebird location)
./configure --with-firebird

# Or specify Firebird path explicitly
./configure --with-firebird=/opt/firebird

# Compile
make

# Install to PHP extensions directory
sudo make install

# Enable in php.ini
echo "extension=interbase.so" | sudo tee /etc/php.d/interbase.ini
```

### 2.2 Build Dependencies

Required packages for compilation:

**Debian/Ubuntu:**
```bash
sudo apt-get install php-dev firebird-dev libfbclient2
```

**Fedora/RHEL:**
```bash
sudo dnf install php-devel firebird-devel libfbclient2
```

**Alpine:**
```bash
apk add php-dev firebird-dev
```

### 2.3 PECL Distribution

PECL (PHP Extension Community Library) provides standardized extension distribution.

**Installation via PECL:**
```bash
pecl install interbase
```

**Creating a PECL Package:**

1. Create `package.xml` with metadata
2. Register account on pecl.php.net
3. Submit package for approval
4. Upload source tarballs for each release

**Example package.xml structure:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<package packagerversion="1.10.13" version="2.0"
         xmlns="http://pear.php.net/dtd/package-2.0">
  <name>interbase</name>
  <channel>pecl.php.net</channel>
  <summary>Firebird/InterBase database driver</summary>
  <description>
    Allows PHP to interact with Firebird and InterBase databases.
  </description>
  <lead>
    <name>Michael Wegener</name>
    <user>mwegener</user>
    <email>mw@satware.com</email>
    <active>yes</active>
  </lead>
  <date>2025-12-25</date>
  <time>12:00:00</time>
  <version>
    <release>7.0.0</release>
    <api>7.0.0</api>
  </version>
  <stability>
    <release>stable</release>
    <api>stable</api>
  </stability>
  <license uri="https://www.php.net/license/">PHP License</license>
  <notes>
    - PHP 8.2+ support
    - Object-oriented API
    - Firebird 3.x/4.x/5.x support
  </notes>
  <contents>
    <dir name="/">
      <file role="src" name="config.m4"/>
      <file role="src" name="config.w32"/>
      <file role="src" name="php_interbase.h"/>
      <file role="src" name="interbase.c"/>
      <!-- ... additional source files ... -->
    </dir>
  </contents>
  <dependencies>
    <required>
      <php>
        <min>8.2.0</min>
      </php>
      <pearinstaller>
        <min>1.10.0</min>
      </pearinstaller>
    </required>
  </dependencies>
  <providesextension>interbase</providesextension>
  <extsrcrelease/>
</package>
```

### 2.4 Docker-Based Compilation

For reproducible builds:

```dockerfile
FROM php:8.4-cli

# Install Firebird client
RUN apt-get update && apt-get install -y \
    firebird-dev \
    libfbclient2 \
    && rm -rf /var/lib/apt/lists/*

# Clone and build extension
WORKDIR /src
RUN git clone https://github.com/satwareAG/php-firebird.git . \
    && phpize \
    && ./configure --with-firebird \
    && make \
    && make install

# Enable extension
RUN echo "extension=interbase.so" > /usr/local/etc/php/conf.d/interbase.ini
```

---

## 3. Distribution Channels

### 3.1 Comparison Matrix

| Channel | Windows | Linux | Auto-install | Versioning | Maintenance |
|---------|---------|-------|--------------|------------|-------------|
| **GitHub Releases** | ✅ DLLs | ✅ Tarballs | ❌ Manual | ✅ Tags | Low |
| **PECL** | ❌ | ✅ Source | ✅ `pecl install` | ✅ | Medium |
| **Composer** | ❌ | ❌ | ✅ PHP-only | ✅ | Low |
| **OS Packages** | ❌ | ✅ | ✅ apt/dnf | ⚠️ Varies | High |
| **Docker Hub** | ✅ | ✅ | ✅ `docker pull` | ✅ | Medium |

### 3.2 GitHub Releases (Primary Recommendation)

**Advantages:**
- Automatic via GitHub Actions
- Version-tagged downloads
- Direct links for installation scripts
- No external dependencies for publishing

**Structure:**

```
Release: v7.0.0
Assets:
├── php_8.2.0-interbase-7.0.0-win-x64-nts.dll
├── php_8.2.0-interbase-7.0.0-win-x64-ts.dll
├── php_8.3.0-interbase-7.0.0-win-x64-nts.dll
├── php_8.3.0-interbase-7.0.0-win-x64-ts.dll
├── php_8.4.0-interbase-7.0.0-win-x64-nts.dll
├── php_8.4.0-interbase-7.0.0-win-x64-ts.dll
├── interbase-7.0.0.tgz (source tarball)
└── checksums.sha256
```

**User Installation (Windows):**

```powershell
# Download DLL for PHP 8.4 NTS x64
$phpVersion = "8.4.0"
$extVersion = "7.0.0"
$url = "https://github.com/satwareAG/php-firebird/releases/download/v$extVersion/php_$phpVersion-interbase-$extVersion-win-x64-nts.dll"

# Download to PHP ext directory
Invoke-WebRequest -Uri $url -OutFile "C:\php\ext\php_interbase.dll"

# Add to php.ini
Add-Content -Path "C:\php\php.ini" -Value "extension=php_interbase.dll"
```

### 3.3 PECL Registration

**When to use PECL:**
- Wide Linux distribution
- Standard `pecl install` workflow desired
- Official PHP ecosystem presence

**Process:**
1. Create `package.xml` (see section 2.3)
2. Register at https://pecl.php.net/account-request.php
3. Submit package for approval
4. After approval, manage releases via PECL web interface

### 3.4 Docker Images

Pre-built Docker images with php-firebird:

```yaml
# docker-compose.yml example
services:
  php:
    image: satwareag/php-firebird:8.4
    # Extension pre-installed and configured
```

**Publishing to Docker Hub:**

```bash
docker build -t satwareag/php-firebird:8.4 .
docker push satwareag/php-firebird:8.4
```

---

## 4. Recommended Implementation for satwareAG/php-firebird

### 4.1 Phase 1: Windows DLL Builds (Priority)

**GitHub Issue:** https://github.com/satwareAG/php-firebird/issues/24

Create `.github/workflows/windows-dll.yml`:

```yaml
name: Build Windows DLLs

on:
  push:
    branches: [master, main]
  pull_request:
  release:
    types: [created]
  workflow_dispatch:

permissions:
  contents: write

jobs:
  get-extension-matrix:
    runs-on: ubuntu-latest
    outputs:
      matrix: ${{ steps.extension-matrix.outputs.matrix }}
    steps:
      - uses: actions/checkout@v4
      - id: extension-matrix
        uses: php/php-windows-builder/extension-matrix@v1

  build:
    needs: get-extension-matrix
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: false
      matrix: ${{ fromJson(needs.get-extension-matrix.outputs.matrix) }}
    steps:
      - uses: actions/checkout@v4
      
      # Install Firebird client libraries
      - name: Install Firebird Client
        shell: cmd
        run: |
          choco install firebird --version=4.0.6 -y
          
      - name: Set Firebird Environment
        shell: pwsh
        run: |
          $firebirdPath = "C:\Program Files\Firebird\Firebird_4_0"
          echo "FIREBIRD_HOME=$firebirdPath" >> $env:GITHUB_ENV
          echo "$firebirdPath\bin" >> $env:GITHUB_PATH
          
      - uses: php/php-windows-builder/extension@v1
        with:
          php-version: ${{ matrix.php-version }}
          arch: ${{ matrix.arch }}
          ts: ${{ matrix.ts }}

  release:
    runs-on: ubuntu-latest
    needs: build
    if: ${{ github.event_name == 'release' }}
    steps:
      - uses: php/php-windows-builder/release@v1
        with:
          release: ${{ github.event.release.tag_name }}
          token: ${{ secrets.GITHUB_TOKEN }}
```

### 4.2 Phase 2: Documentation

Update README.md with:

1. **Windows Installation:**
   - Link to releases page
   - Instructions for downloading correct DLL variant
   - php.ini configuration

2. **Linux Installation:**
   - Source compilation instructions
   - PECL installation (when available)
   - Docker image usage

3. **Firebird Client Requirements:**
   - Which Firebird client versions are compatible
   - Where to download Firebird client
   - Environment variable configuration

### 4.3 Phase 3: PECL Registration (Future)

1. Prepare `package.xml`
2. Register satwareAG package owner on PECL
3. Submit for approval
4. Automate releases via GitHub Actions

---

## 5. Existing php-firebird Release Examples

### 5.1 FirebirdSQL/php-firebird v3.0.1

Has Windows DLLs for PHP 8.0-8.2:

```
php_8.0.0-interbase-3.0.1-win-x64-nts.dll
php_8.0.0-interbase-3.0.1-win-x64-ts.dll
php_8.1.0-interbase-3.0.1-win-x64-nts.dll
php_8.1.0-interbase-3.0.1-win-x64-ts.dll
php_8.2.0-interbase-3.0.1-win-x64-nts.dll
php_8.2.0-interbase-3.0.1-win-x64-ts.dll
```

### 5.2 satwareAG/php-firebird (Current)

Releases without Windows DLLs:
- v7.0.0-rc.7
- v7.0.0-rc.6
- v7.0.0-rc.2
- v7.0.0-rc.1
- v6.2.0

**Gap to address:** Add Windows DLL builds using the workflow above.

---

## 6. References

- **php-windows-builder:** https://github.com/php/php-windows-builder
- **php-windows-builder examples:** https://github.com/php/php-windows-builder/tree/main/examples
- **Imagick workflow example:** https://github.com/php/php-windows-builder/blob/main/examples/non-immutable-releases/build.yml
- **PECL documentation:** https://pecl.php.net/
- **Firebird downloads:** https://firebirdsql.org/en/downloads/
- **GitHub Issue #24:** https://github.com/satwareAG/php-firebird/issues/24

---

## Appendix: Troubleshooting

### Windows Build Issues

**Issue:** Missing Firebird headers
**Solution:** Ensure Firebird SDK is installed via Chocolatey or direct download before build step

**Issue:** Link errors for fbclient.dll
**Solution:** Add Firebird lib path to `LIB` environment variable or use `libs` parameter

**Issue:** Architecture mismatch (x86 vs x64)
**Solution:** Ensure Firebird client architecture matches PHP architecture

### Linux Build Issues

**Issue:** `phpize: command not found`
**Solution:** Install `php-dev` or `php-devel` package

**Issue:** `configure: error: Cannot find Firebird client library`
**Solution:** Install `firebird-dev` and specify path with `--with-firebird=/path`

**Issue:** Extension loads but `fbird_connect()` fails
**Solution:** Ensure Firebird client library is in LD_LIBRARY_PATH
