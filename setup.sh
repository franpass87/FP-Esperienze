#!/bin/bash

# FP Esperienze Plugin Setup Script
# This script helps install the required dependencies for FP Esperienze plugin

echo "FP Esperienze Plugin Setup"
echo "=========================="
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed."
    echo ""
    echo "Please install Composer first:"
    echo "  • Visit: https://getcomposer.org/download/"
    echo "  • Or install globally via your package manager"
    echo ""
    exit 1
fi

echo "✅ Composer found: $(composer --version | head -1)"
echo ""

# Check if we're in the right directory
if [ ! -f "composer.json" ] || [ ! -f "fp-esperienze.php" ]; then
    echo "❌ This script must be run from the FP Esperienze plugin directory."
    echo ""
    echo "Make sure you're in the directory containing:"
    echo "  • composer.json"
    echo "  • fp-esperienze.php"
    echo ""
    exit 1
fi

echo "✅ Found FP Esperienze plugin files"
echo ""

# Install dependencies
echo "Installing dependencies..."
echo "Running: composer install --no-dev --optimize-autoloader"
echo ""

if composer install --no-dev --optimize-autoloader; then
    echo ""
    echo "✅ Dependencies installed successfully!"
    echo ""
    echo "You can now:"
    echo "  1. Upload this plugin directory to your WordPress /wp-content/plugins/ folder"
    echo "  2. Activate the plugin in WordPress Admin > Plugins"
    echo ""
    echo "Happy booking! 🎉"
else
    echo ""
    echo "❌ Failed to install dependencies."
    echo ""
    echo "Try running manually:"
    echo "  composer install --no-dev --optimize-autoloader"
    echo ""
    exit 1
fi