#!/usr/bin/env bash
# create-new-feature.sh — Scaffold a new feature spec directory
# Usage: ./create-new-feature.sh <feature-number> <feature-slug>
# Example: ./create-new-feature.sh 001 dbal4-compatibility
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TEMPLATES_DIR="$SCRIPT_DIR/../templates"
SPECS_DIR="$SCRIPT_DIR/../specs"

if [[ $# -lt 2 ]]; then
    echo "Usage: $0 <number> <slug>"
    echo "Example: $0 001 dbal4-compatibility"
    exit 1
fi

NUM="$1"
SLUG="$2"
FEATURE_DIR="$SPECS_DIR/${NUM}-${SLUG}"
DATE="$(date +%Y-%m-%d)"

if [[ -d "$FEATURE_DIR" ]]; then
    echo "❌ Feature directory already exists: $FEATURE_DIR"
    exit 1
fi

echo "Creating feature: ${NUM}-${SLUG}"
mkdir -p "$FEATURE_DIR/contracts" "$FEATURE_DIR/checklists"

# Copy and customize templates
sed "s/\[FEATURE NAME\]/${SLUG}/g; s/###-feature-name/${NUM}-${SLUG}/g; s/\[DATE\]/${DATE}/g" \
    "$TEMPLATES_DIR/spec-template.md" > "$FEATURE_DIR/spec.md"

sed "s/\[FEATURE NAME\]/${SLUG}/g; s/###-feature-name/${NUM}-${SLUG}/g; s/\[DATE\]/${DATE}/g" \
    "$TEMPLATES_DIR/plan-template.md" > "$FEATURE_DIR/plan.md"

sed "s/\[FEATURE NAME\]/${SLUG}/g; s/###-feature-name/${NUM}-${SLUG}/g; s/\[DATE\]/${DATE}/g" \
    "$TEMPLATES_DIR/tasks-template.md" > "$FEATURE_DIR/tasks.md"

# Create empty research and data-model files
cat > "$FEATURE_DIR/research.md" << EOF
# Research: ${SLUG}

**Feature**: \`${NUM}-${SLUG}\`
**Created**: ${DATE}

## Technical Unknowns

| Unknown | Research Task | Finding |
|---------|--------------|---------|
| [Unknown] | [How to research] | [TBD] |

## Findings

### [Topic 1]

[Research findings]

**Source**: [Firebird docs / DBAL docs / GitHub issue]
EOF

cat > "$FEATURE_DIR/data-model.md" << EOF
# Data Model: ${SLUG}

**Feature**: \`${NUM}-${SLUG}\`
**Created**: ${DATE}

## Entities

### [Entity Name]

| Attribute | Type | Description | Constraints |
|-----------|------|-------------|-------------|
| [attr] | [type] | [description] | [constraints] |

## Relationships

- **[Entity A]** → **[Entity B]**: [relationship description]
EOF

cat > "$FEATURE_DIR/quickstart.md" << EOF
# Quickstart / Validation Scenarios: ${SLUG}

**Feature**: \`${NUM}-${SLUG}\`
**Created**: ${DATE}

## Validation Scenario 1: [Title]

\`\`\`php
// Minimal code to validate the feature works
\`\`\`

**Expected**: [What should happen]

## Running Tests

\`\`\`bash
# Unit tests only
php vendor/bin/phpunit tests/Test/Unit/ --filter [TestClass]

# Functional tests (requires Docker)
cd tests && docker compose up -d
php vendor/bin/phpunit tests/Test/Functional/ --filter [TestClass]
\`\`\`
EOF

echo ""
echo "✅ Feature scaffolded: $FEATURE_DIR"
echo ""
echo "Files created:"
echo "  - spec.md      (edit: define WHAT and WHY)"
echo "  - plan.md      (edit after spec is approved)"
echo "  - tasks.md     (edit after plan is approved)"
echo "  - research.md  (fill during planning)"
echo "  - data-model.md"
echo "  - quickstart.md"
echo "  - contracts/   (add interface specs here)"
echo "  - checklists/  (add quality checklists here)"
echo ""
echo "Next: Edit spec.md, then run /speckit.clarify"
