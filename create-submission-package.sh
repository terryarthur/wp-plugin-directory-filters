#!/bin/bash

# WordPress.org Plugin Submission Package Creator
# Creates a clean package ready for WordPress.org submission

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Creating WordPress.org submission package...${NC}"

# Create submission directory
SUBMISSION_DIR="wppd-filters-submission"
rm -rf "$SUBMISSION_DIR"
mkdir -p "$SUBMISSION_DIR/wppd-filters"

echo -e "${YELLOW}Copying plugin files...${NC}"

# Copy main plugin files
cp wp-plugin-directory-filters.php "$SUBMISSION_DIR/wppd-filters/"
cp readme.txt "$SUBMISSION_DIR/wppd-filters/"
cp uninstall.php "$SUBMISSION_DIR/wppd-filters/"
cp index.php "$SUBMISSION_DIR/wppd-filters/"

# Copy assets
mkdir -p "$SUBMISSION_DIR/wppd-filters/assets/css"
mkdir -p "$SUBMISSION_DIR/wppd-filters/assets/js"
cp assets/css/admin.css "$SUBMISSION_DIR/wppd-filters/assets/css/"
cp assets/css/index.php "$SUBMISSION_DIR/wppd-filters/assets/css/"
cp assets/js/admin.js "$SUBMISSION_DIR/wppd-filters/assets/js/"
cp assets/js/index.php "$SUBMISSION_DIR/wppd-filters/assets/js/"
cp assets/index.php "$SUBMISSION_DIR/wppd-filters/assets/"

# Copy includes
mkdir -p "$SUBMISSION_DIR/wppd-filters/includes"
cp includes/class-*.php "$SUBMISSION_DIR/wppd-filters/includes/"
cp includes/index.php "$SUBMISSION_DIR/wppd-filters/includes/"

# Copy languages
mkdir -p "$SUBMISSION_DIR/wppd-filters/languages"
cp languages/README.md "$SUBMISSION_DIR/wppd-filters/languages/"
cp languages/index.php "$SUBMISSION_DIR/wppd-filters/languages/"

echo -e "${YELLOW}Creating submission archive...${NC}"

# Create ZIP file
cd "$SUBMISSION_DIR"
zip -r "../wppd-filters-v1.0.0.zip" wppd-filters/
cd ..

# Calculate file size
FILESIZE=$(stat -f%z "wppd-filters-v1.0.0.zip" 2>/dev/null || stat -c%s "wppd-filters-v1.0.0.zip" 2>/dev/null)
FILESIZE_MB=$(echo "scale=2; $FILESIZE / 1024 / 1024" | bc)

echo -e "${GREEN}✅ Submission package created successfully!${NC}"
echo -e "${GREEN}📦 File: wppd-filters-v1.0.0.zip${NC}"
echo -e "${GREEN}📊 Size: ${FILESIZE_MB} MB${NC}"
echo ""
echo -e "${YELLOW}Files included in submission:${NC}"
echo "├── wp-plugin-directory-filters.php"
echo "├── readme.txt" 
echo "├── uninstall.php"
echo "├── index.php"
echo "├── assets/"
echo "│   ├── css/admin.css"
echo "│   ├── js/admin.js"
echo "│   └── index.php files"
echo "├── includes/"
echo "│   ├── class-*.php files"
echo "│   └── index.php"
echo "└── languages/"
echo "    ├── README.md"
echo "    └── index.php"
echo ""
echo -e "${GREEN}🚀 Ready for WordPress.org submission!${NC}"
echo -e "${YELLOW}📋 Next steps:${NC}"
echo "1. Visit https://wordpress.org/plugins/developers/add/"
echo "2. Upload wppd-filters-v1.0.0.zip"
echo "3. Fill out the submission form"
echo "4. Wait for review (typically 7-14 days)"

# Cleanup
rm -rf "$SUBMISSION_DIR"