#!/usr/bin/env python3
"""
FP Esperienze Accessibility Validation Script

This script validates key accessibility features implemented in the plugin.
Run this after making accessibility changes to ensure compliance.
"""

import json
import re
from pathlib import Path

def check_css_contrast():
    """Check that CSS uses accessible color variables"""
    css_file = Path('assets/css/frontend.css')
    if not css_file.exists():
        return False, "CSS file not found"
    
    content = css_file.read_text()
    
    # Check for CSS custom properties
    has_variables = all([
        '--fp-brand-orange-text' in content,
        '--fp-text-gray' in content,
        'var(--fp-brand-orange-text)' in content
    ])
    
    # Check that old problematic colors are replaced
    problematic_colors = ['color: #666;', 'color: #999;']
    has_old_colors = any(color in content for color in problematic_colors)
    
    return has_variables and not has_old_colors, {
        'has_css_variables': has_variables,
        'has_old_colors': has_old_colors
    }

def check_aria_attributes():
    """Check template files for proper ARIA attributes"""
    template_file = Path('templates/single-experience.php')
    if not template_file.exists():
        return False, "Template file not found"
    
    content = template_file.read_text()
    
    aria_checks = {
        'radiogroup': 'role="radiogroup"' in content,
        'aria_labelledby': 'aria-labelledby=' in content,
        'aria_controls': 'aria-controls=' in content,
        'aria_expanded': 'aria-expanded=' in content,
        'aria_labels': 'aria-label=' in content,
        'explicit_ids': 'id="fp-time-slots-label"' in content
    }
    
    passed = sum(aria_checks.values())
    total = len(aria_checks)
    
    return passed >= total * 0.8, aria_checks  # 80% pass rate

def check_javascript_i18n():
    """Check that JavaScript files use localized strings"""
    js_file = Path('assets/js/booking-widget.js')
    if not js_file.exists():
        return False, "JavaScript file not found"
    
    content = js_file.read_text()
    
    i18n_checks = {
        'uses_i18n_object': 'fp_booking_widget_i18n' in content,
        'no_hardcoded_errors': 'Failed to load availability.' not in content,
        'localized_messages': 'error_failed_load_availability' in content,
        'keyboard_navigation': 'keydown' in content and 'ArrowDown' in content
    }
    
    passed = sum(i18n_checks.values())
    total = len(i18n_checks)
    
    return passed == total, i18n_checks

def check_pot_file():
    """Check that .pot file exists and has reasonable content"""
    pot_file = Path('languages/fp-esperienze.pot')
    if not pot_file.exists():
        return False, "POT file not found"
    
    content = pot_file.read_text()
    lines = content.split('\n')
    
    msgid_count = len([line for line in lines if line.startswith('msgid ')])
    has_header = 'fp-esperienze' in content and 'Francesco Passeri' in content
    
    return msgid_count > 500 and has_header, {
        'msgid_count': msgid_count,
        'has_proper_header': has_header
    }

def main():
    """Run all accessibility checks"""
    print("🔍 FP Esperienze Accessibility Validation")
    print("=" * 50)
    print()
    
    checks = [
        ("CSS Color Contrast", check_css_contrast),
        ("ARIA Attributes", check_aria_attributes),
        ("JavaScript i18n", check_javascript_i18n),
        ("Translation Files", check_pot_file)
    ]
    
    results = []
    
    for name, check_func in checks:
        try:
            passed, details = check_func()
            status = "✅ PASS" if passed else "❌ FAIL"
            print(f"{name}: {status}")
            
            if isinstance(details, dict):
                for key, value in details.items():
                    symbol = "✓" if value else "✗"
                    print(f"  {symbol} {key.replace('_', ' ').title()}")
            
            results.append(passed)
            print()
            
        except Exception as e:
            print(f"{name}: ❌ ERROR - {e}")
            results.append(False)
            print()
    
    # Summary
    passed_count = sum(results)
    total_count = len(results)
    percentage = (passed_count / total_count) * 100
    
    print("📊 SUMMARY")
    print("-" * 20)
    print(f"Tests Passed: {passed_count}/{total_count} ({percentage:.1f}%)")
    
    if percentage >= 100:
        print("🎉 All accessibility checks passed!")
    elif percentage >= 75:
        print("✅ Good accessibility compliance!")
    else:
        print("⚠️  Some accessibility issues need attention")
    
    return passed_count == total_count

if __name__ == "__main__":
    success = main()
    exit(0 if success else 1)