#!/bin/bash
# Security validation script for FP Esperienze

echo "=== FP Esperienze Security Validation ==="
echo

# Check for unsanitized $_POST/$_GET usage
echo "1. Checking for unsanitized \$_POST/\$_GET usage..."
if grep -r "\$_POST\[" includes/ --exclude-dir=vendor | grep -v "sanitize\|absint\|intval\|wp_verify_nonce"; then
    echo "  ❌ Found potentially unsanitized \$_POST usage"
else
    echo "  ✅ No unsanitized \$_POST usage found"
fi

if grep -r "\$_GET\[" includes/ --exclude-dir=vendor | grep -v "sanitize\|absint\|intval\|esc_"; then
    echo "  ❌ Found potentially unsanitized \$_GET usage"
else
    echo "  ✅ No unsanitized \$_GET usage found"
fi

# Check for nonce fields in templates
echo
echo "2. Checking nonce protection in frontend forms..."
if grep -r "wp_nonce_field\|wp_create_nonce" templates/; then
    echo "  ✅ Nonce fields found in templates"
else
    echo "  ❌ No nonce fields found in templates"
fi

# Check for proper escaping in templates
echo
echo "3. Checking output escaping in templates..."
if grep -r "echo.*\$\|<?php.*\$" templates/ | grep -v "esc_html\|esc_attr\|esc_url\|wp_kses"; then
    echo "  ❌ Found potentially unescaped output in templates"
else
    echo "  ✅ All template output appears properly escaped"
fi

# Check for AJAX nonce verification
echo
echo "4. Checking AJAX security..."
ajax_handlers=$(grep -r "wp_ajax" includes/ | grep "add_action" | wc -l)
nonce_checks=$(grep -r "check_ajax_referer\|wp_verify_nonce" includes/ | wc -l)
echo "  📊 Found $ajax_handlers AJAX handlers"
echo "  📊 Found $nonce_checks nonce verifications"

# Check for rate limiting implementation
echo
echo "5. Checking rate limiting..."
if grep -r "RateLimiter::checkRateLimit" includes/; then
    echo "  ✅ Rate limiting implementation found"
else
    echo "  ❌ No rate limiting found"
fi

# Check for secure random generation
echo
echo "6. Checking cryptographic randomness..."
if grep -r "random_bytes" includes/; then
    echo "  ✅ Cryptographically secure random generation found"
else
    echo "  ❌ No secure random generation found"
fi

if grep -r "wp_generate_password" includes/ | grep -v "random_bytes"; then
    echo "  ⚠️  Found wp_generate_password usage - consider replacing with random_bytes"
fi

# Check for HMAC implementation
echo
echo "7. Checking HMAC security..."
if grep -r "hash_hmac\|hash_equals" includes/; then
    echo "  ✅ HMAC implementation found"
else
    echo "  ❌ No HMAC implementation found"
fi

# Check for file security
echo
echo "8. Checking file security..."
if grep -r "\.htaccess" includes/; then
    echo "  ✅ .htaccess security implementation found"
else
    echo "  ❌ No .htaccess security found"
fi

# Check for SQL injection protection
echo
echo "9. Checking SQL injection protection..."
if grep -r "\$wpdb->prepare" includes/ | wc -l; then
    echo "  ✅ Prepared statements found"
else
    echo "  ❌ No prepared statements found"
fi

if grep -r "\$wpdb->query\|\$wpdb->get_" includes/ | grep -v "prepare"; then
    echo "  ⚠️  Found unprepared database queries"
else
    echo "  ✅ All database queries appear to use prepared statements"
fi

echo
echo "=== Security Validation Complete ==="