#!/usr/bin/env bash
# setup-plan.sh — Set up the plan phase for an existing feature spec
# Usage: ./setup-plan.sh <feature-dir>
# Example: ./setup-plan.sh 001-dbal4-compatibility
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SPECS_DIR="$SCRIPT_DIR/../specs"

if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <feature-dir>"
    echo "Example: $0 001-dbal4-compatibility"
    echo ""
    echo "Available features:"
    ls "$SPECS_DIR" 2>/dev/null || echo "  (none yet)"
    exit 1
fi

FEATURE="$1"
FEATURE_DIR="$SPECS_DIR/$FEATURE"

if [[ ! -d "$FEATURE_DIR" ]]; then
    echo "❌ Feature directory not found: $FEATURE_DIR"
    echo ""
    echo "Available features:"
    ls "$SPECS_DIR" 2>/dev/null || echo "  (none yet)"
    exit 1
fi

if [[ ! -f "$FEATURE_DIR/spec.md" ]]; then
    echo "❌ spec.md not found in $FEATURE_DIR"
    echo "Run create-new-feature.sh first."
    exit 1
fi

# Check for unresolved clarifications
if grep -q "\[NEEDS CLARIFICATION\]" "$FEATURE_DIR/spec.md"; then
    echo "⚠️  WARNING: spec.md contains [NEEDS CLARIFICATION] markers."
    echo "   Run /speckit.clarify before planning."
    echo ""
fi

DATE="$(date +%Y-%m-%d)"

# Create contracts directory if missing
mkdir -p "$FEATURE_DIR/contracts" "$FEATURE_DIR/checklists"

# Create research.md if missing
if [[ ! -f "$FEATURE_DIR/research.md" ]]; then
    cat > "$FEATURE_DIR/research.md" << EOF
# Research: ${FEATURE}

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
    echo "  ✅ Created research.md"
fi

# Create data-model.md if missing
if [[ ! -f "$FEATURE_DIR/data-model.md" ]]; then
    cat > "$FEATURE_DIR/data-model.md" << EOF
# Data Model: ${FEATURE}

**Created**: ${DATE}

## Entities

### [Entity Name]

| Attribute | Type | Description | Constraints |
|-----------|------|-------------|-------------|
| [attr] | [type] | [description] | [constraints] |

## Relationships

- **[Entity A]** → **[Entity B]**: [relationship description]
EOF
    echo "  ✅ Created data-model.md"
fi

# Create quickstart.md if missing
if [[ ! -f "$FEATURE_DIR/quickstart.md" ]]; then
    cat > "$FEATURE_DIR/quickstart.md" << EOF
# Quickstart / Validation Scenarios: ${FEATURE}

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
    echo "  ✅ Created quickstart.md"
fi

echo ""
echo "✅ Plan phase ready for: $FEATURE"
echo ""
echo "Files available:"
ls -1 "$FEATURE_DIR/"
echo ""
echo "Next steps:"
echo "  1. Fill in plan.md (architecture decisions)"
echo "  2. Fill in research.md (technical findings)"
echo "  3. Fill in data-model.md (entity definitions)"
echo "  4. Run /speckit.tasks to generate task breakdown"
