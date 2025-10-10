#!/bin/bash
# Script to apply MultiFlexi validation standards to all projects
# Usage: ./apply-multiflexi-standards.sh

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 MultiFlexi Standards Application Script${NC}"
echo "================================================"

# Get list of all MultiFlexi projects
echo -e "${YELLOW}📋 Fetching list of MultiFlexi projects...${NC}"
projects=$(ssh multiflexi.eu 'multiflexi-cli application list --format json' | jq -r '.[].homepage' | sort | uniq)

# Create temporary directory for cloning
temp_dir=$(mktemp -d)
echo -e "${BLUE}📁 Using temporary directory: $temp_dir${NC}"

# Function to apply standards to a project
apply_standards() {
    local repo_url=$1
    local repo_name=$(basename "$repo_url" .git)
    local repo_path="$temp_dir/$repo_name"
    
    echo -e "${YELLOW}🔄 Processing: $repo_name${NC}"
    
    # Clone repository
    if ! git clone "$repo_url" "$repo_path" 2>/dev/null; then
        echo -e "${RED}❌ Failed to clone $repo_url${NC}"
        return 1
    fi
    
    cd "$repo_path"
    
    # Check if multiflexi directory exists
    if [ ! -d "multiflexi" ]; then
        echo -e "${YELLOW}⚠️  No multiflexi directory in $repo_name, skipping${NC}"
        return 0
    fi
    
    echo -e "${GREEN}✅ Found multiflexi directory in $repo_name${NC}"
    
    # Create .github directory if it doesn't exist
    mkdir -p .github/workflows
    
    # Copy GitHub Actions workflow
    cp "$HOME/Projects/SpojeNet/abraflexi-ipex/.github/workflows/multiflexi-validation.yml" .github/workflows/
    
    # Copy standards documentation
    cp "$HOME/Projects/SpojeNet/abraflexi-ipex/.github/multiflexi-standards.md" .github/
    
    # Copy Makefile template
    cp "$HOME/Projects/SpojeNet/abraflexi-ipex/.github/Makefile.multiflexi" .github/
    
    # Copy pre-commit hook
    mkdir -p .git/hooks
    cp "$HOME/Projects/SpojeNet/abraflexi-ipex/.git/hooks/pre-commit" .git/hooks/
    chmod +x .git/hooks/pre-commit
    
    # Update copilot instructions if they exist
    if [ -f ".github/copilot-instructions.md" ]; then
        echo -e "${BLUE}📝 Updating existing copilot instructions${NC}"
        # Add MultiFlexi validation rules if not already present
        if ! grep -q "multiflexi.*app.json.*schema" .github/copilot-instructions.md; then
            echo "" >> .github/copilot-instructions.md
            echo "# MultiFlexi JSON Validation" >> .github/copilot-instructions.md
            cat .github/multiflexi-standards.md >> .github/copilot-instructions.md
        fi
    else
        echo -e "${BLUE}📝 Creating copilot instructions${NC}"
        cp .github/multiflexi-standards.md .github/copilot-instructions.md
    fi
    
    # Validate current JSON files
    echo -e "${BLUE}🔍 Validating current JSON files${NC}"
    if command -v python3 >/dev/null && python3 -c "import jsonschema, requests" 2>/dev/null; then
        find multiflexi/ -name "*.json" -exec python3 -m json.tool {} \; -print >/dev/null
        echo -e "${GREEN}✅ JSON syntax validation passed${NC}"
    else
        echo -e "${YELLOW}⚠️  Cannot validate schema (missing dependencies)${NC}"
    fi
    
    # Commit changes if there are any
    if ! git diff --quiet || [ -n "$(git ls-files --others --exclude-standard)" ]; then
        git add .
        git commit -m "Add MultiFlexi JSON validation standards and automation

- Add GitHub Actions workflow for automatic JSON validation
- Add pre-commit hook for local validation
- Add MultiFlexi development standards documentation
- Add Makefile targets for validation
- Update copilot instructions for automatic validation"
        
        echo -e "${GREEN}📤 Changes committed locally${NC}"
        echo -e "${YELLOW}💡 To push changes, run: cd $repo_path && git push${NC}"
    else
        echo -e "${BLUE}ℹ️  No changes needed in $repo_name${NC}"
    fi
    
    echo -e "${GREEN}✅ Completed processing $repo_name${NC}"
    echo "---"
}

# Process each project
echo -e "${BLUE}🔄 Processing projects...${NC}"
echo ""

while IFS= read -r repo_url; do
    if [ -n "$repo_url" ] && [ "$repo_url" != "null" ]; then
        repo_url=$(echo "$repo_url" | tr -d '"')
        apply_standards "$repo_url"
    fi
done <<< "$projects"

echo ""
echo -e "${GREEN}🎉 All projects processed!${NC}"
echo -e "${BLUE}📁 Cloned repositories are in: $temp_dir${NC}"
echo -e "${YELLOW}💡 Remember to review and push changes in each repository${NC}"

# Offer to clean up
read -p "Do you want to clean up temporary directory? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    rm -rf "$temp_dir"
    echo -e "${GREEN}🗑️  Temporary directory cleaned up${NC}"
else
    echo -e "${BLUE}📁 Temporary directory preserved at: $temp_dir${NC}"
fi