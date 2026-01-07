# Docker BuildKit Setup on Arch Linux

**Last Updated**: 2026-01-07
**Status**: Production Ready

## Overview

Docker BuildKit is required for building the Doctrine Firebird Driver test environment. The Dockerfile uses BuildKit-specific features like cache mounts for faster builds.

## Prerequisites

- Arch Linux (Manjaro, EndeavourOS, etc.)
- Docker installed and running
- Sudo access for package installation

## Installation

### Method 1: Via Pacman (Recommended)

```bash
# Install docker-buildx plugin
sudo pacman -S docker-buildx

# Verify installation
docker buildx version
# Expected output: github.com/docker/buildx 0.30.1 ...
```

### Method 2: Manual Installation (Alternative)

If the package is not available:

```bash
# Download latest buildx binary
BUILDX_VERSION=v0.30.1
ARCH=linux-amd64
mkdir -p ~/.docker/cli-plugins
curl -L "https://github.com/docker/buildx/releases/download/${BUILDX_VERSION}/buildx-${BUILDX_VERSION}.${ARCH}" \
  -o ~/.docker/cli-plugins/docker-buildx
chmod +x ~/.docker/cli-plugins/docker-buildx

# Verify
docker buildx version
```

## BuildKit Configuration

### Enable BuildKit by Default

Add to `~/.bashrc` or `~/.zshrc`:

```bash
# Enable Docker BuildKit
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1
```

Apply changes:

```bash
source ~/.bashrc  # or ~/.zshrc
```

### Per-Command Usage

Alternatively, use BuildKit per command:

```bash
# Build with BuildKit
DOCKER_BUILDKIT=1 docker build -t myimage .

# Docker Compose with BuildKit
DOCKER_BUILDKIT=1 docker compose build
```

## Buildx Builder Setup

### Create and Use Default Builder

```bash
# Create a new builder instance
docker buildx create --name mybuilder --driver docker-container --bootstrap

# Set as default
docker buildx use mybuilder

# Verify
docker buildx ls
# Should show mybuilder as current builder (*)
```

### Using Default Docker Driver

For local builds, the default driver works well:

```bash
# Use default builder
docker buildx use default

# Verify
docker buildx ls
```

## Building the Doctrine Firebird Driver Test Environment

### Method 1: Docker Compose (Recommended)

```bash
cd /path/to/doctrine-firebird-driver

# Build with PHP 8.3
DOCKER_BUILDKIT=1 docker compose -f tests/docker-compose.yml build \
  --build-arg PHP_VERSION=8.3 app

# Or use the test script
cd tests
./docker-cqc.sh
```

### Method 2: Direct Docker Build

```bash
cd /path/to/doctrine-firebird-driver/tests

# Build test image
DOCKER_BUILDKIT=1 docker build \
  --build-arg PHP_VERSION=8.3 \
  -t doctrine-firebird-driver-test:php8.3 \
  -f app/Dockerfile \
  app/
```

### Build Arguments

| Argument | Default | Options |
|----------|---------|---------|
| `PHP_VERSION` | `8.1` | `8.1`, `8.2`, `8.3`, `8.4`, `8.5` |

## Troubleshooting

### Error: "BuildKit is enabled but the buildx component is missing"

**Cause**: docker-buildx plugin not installed.

**Solution**:
```bash
sudo pacman -S docker-buildx
```

### Error: "the --mount option requires BuildKit"

**Cause**: BuildKit not enabled for the build command.

**Solution**:
```bash
# Prefix command with DOCKER_BUILDKIT=1
DOCKER_BUILDKIT=1 docker build ...
```

### Error: "failed to solve: failed to fetch"

**Cause**: Network issues or cache corruption.

**Solution**:
```bash
# Clear builder cache
docker buildx prune -a -f

# Retry build
DOCKER_BUILDKIT=1 docker compose build --no-cache
```

### Error: "Docker Compose requires buildx plugin to be installed"

**Cause**: Warning only, but buildx needed for full feature support.

**Solution**:
```bash
# Install buildx as shown above
sudo pacman -S docker-buildx
```

## Verification Checklist

After setup, verify everything works:

```bash
# 1. Check buildx is installed
docker buildx version
# ✅ Expected: version number displayed

# 2. Check builder instance
docker buildx ls
# ✅ Expected: At least one builder listed

# 3. Test build with cache mounts
cd /path/to/doctrine-firebird-driver/tests
DOCKER_BUILDKIT=1 docker build \
  --build-arg PHP_VERSION=8.3 \
  -f app/Dockerfile \
  app/
# ✅ Expected: Build succeeds without "--mount option requires BuildKit" error

# 4. Verify cache mounts work
docker buildx du
# ✅ Expected: Cache statistics displayed
```

## Performance Benefits

With BuildKit enabled, you get:

1. **Faster Builds**: Parallel layer building
2. **Better Caching**: Build cache persistence across builds
3. **Cache Mounts**: Shared package manager caches (apt, composer)
4. **Smaller Images**: Automatic layer squashing
5. **Security**: Rootless build support

## Additional Resources

- [Docker BuildKit Documentation](https://docs.docker.com/build/buildkit/)
- [Buildx Documentation](https://docs.docker.com/build/buildx/)
- [Arch Linux Docker Package](https://archlinux.org/packages/extra/x86_64/docker/)
- [Arch Linux Docker Buildx Package](https://archlinux.org/packages/extra/x86_64/docker-buildx/)

## Project-Specific Notes

### php-firebird Extension Versions

The test Dockerfile uses:
- **Current**: php-firebird v7.0.0-rc.48 (as of 2026-01-07)
- **Source**: https://github.com/satwareAG/php-firebird

### Firebird Database Versions Supported

- Firebird 5.0 (Primary)
- Firebird 4.0 (Active)
- Firebird 3.0 (Legacy)
- Firebird 2.5 (SuperClassic - Deprecated)

## See Also

- [Docker Infrastructure Standards](../../Rules/infrastructure-standards.md)
- [Testing Documentation](../TESTING.md)
- [CI/CD Pipeline Configuration](../../.github/workflows/)
