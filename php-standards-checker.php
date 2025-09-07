<?php
/**
 * PHP Standards Compliance Checker for FP Esperienze
 * 
 * This script validates PHP code compliance with:
 * - PSR-4 namespace standards
 * - WordPress coding standards
 * - Security best practices
 * - Type hints and documentation
 */

class PhpStandardsChecker {
    
    private const REQUIRED_NAMESPACE = 'FP\\Esperienze\\';
    private const INCLUDES_DIR = 'includes/';
    
    private array $errors = [];
    private array $warnings = [];
    private array $suggestions = [];
    
    public function __construct() {
        echo "=== PHP Standards Compliance Checker ===\n\n";
    }
    
    /**
     * Run all compliance checks
     */
    public function runAllChecks(): bool {
        $this->checkPSR4Compliance();
        $this->checkWordPressStandards();
        $this->checkSecurityPractices();
        $this->checkTypeHints();
        $this->checkDocumentation();
        $this->checkConfigurationFiles();
        
        return $this->printResults();
    }
    
    /**
     * Check PSR-4 namespace compliance
     */
    private function checkPSR4Compliance(): void {
        echo "🔍 Checking PSR-4 namespace compliance...\n";
        
        $files = $this->getPhpFiles();
        foreach ($files as $file) {
            $this->validateNamespace($file);
        }
        
        echo "✅ PSR-4 namespace check completed\n\n";
    }
    
    /**
     * Check WordPress coding standards compliance
     */
    private function checkWordPressStandards(): void {
        echo "🔍 Checking WordPress coding standards...\n";
        
        $files = $this->getPhpFiles();
        foreach ($files as $file) {
            $this->validateWordPressStandards($file);
        }
        
        echo "✅ WordPress standards check completed\n\n";
    }
    
    /**
     * Check security practices
     */
    private function checkSecurityPractices(): void {
        echo "🔍 Checking security practices...\n";
        
        $files = $this->getPhpFiles();
        foreach ($files as $file) {
            $this->validateSecurityPractices($file);
        }
        
        echo "✅ Security practices check completed\n\n";
    }
    
    /**
     * Check type hints usage
     */
    private function checkTypeHints(): void {
        echo "🔍 Checking type hints...\n";
        
        $files = $this->getPhpFiles();
        foreach ($files as $file) {
            $this->validateTypeHints($file);
        }
        
        echo "✅ Type hints check completed\n\n";
    }
    
    /**
     * Check documentation compliance
     */
    private function checkDocumentation(): void {
        echo "🔍 Checking documentation...\n";
        
        $files = $this->getPhpFiles();
        foreach ($files as $file) {
            $this->validateDocumentation($file);
        }
        
        echo "✅ Documentation check completed\n\n";
    }
    
    /**
     * Check configuration files
     */
    private function checkConfigurationFiles(): void {
        echo "🔍 Checking configuration files...\n";
        
        $this->validatePhpStanConfig();
        $this->validatePhpcsConfig();
        $this->validateComposerConfig();
        
        echo "✅ Configuration files check completed\n\n";
    }
    
    /**
     * Get all PHP files in the includes directory
     */
    private function getPhpFiles(): array {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::INCLUDES_DIR)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Validate namespace in a PHP file
     */
    private function validateNamespace(string $file): void {
        $content = file_get_contents($file);
        $relativePath = str_replace(self::INCLUDES_DIR, '', $file);
        $expectedNamespace = $this->getExpectedNamespace($relativePath);
        
        // Special case: WooCommerce product classes need to be in global namespace
        if (basename($file) === 'WC_Product_Experience.php') {
            if (preg_match('/namespace\s+/', $content)) {
                $this->warnings[] = "WooCommerce product class should be in global namespace: {$file}";
            }
            return;
        }
        
        // Check if namespace is declared
        if (!preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $this->errors[] = "Missing namespace in {$file}";
            return;
        }
        
        $actualNamespace = trim($matches[1]);
        
        // Validate namespace matches PSR-4 structure
        if ($actualNamespace !== $expectedNamespace) {
            $this->errors[] = "Incorrect namespace in {$file}. Expected: {$expectedNamespace}, Found: {$actualNamespace}";
        }
    }
    
    /**
     * Get expected namespace based on file path
     */
    private function getExpectedNamespace(string $relativePath): string {
        $dir = dirname($relativePath);
        if ($dir === '.') {
            return rtrim(self::REQUIRED_NAMESPACE, '\\');
        }
        
        $namespace = str_replace('/', '\\', $dir);
        return self::REQUIRED_NAMESPACE . $namespace;
    }
    
    /**
     * Validate WordPress standards in a file
     */
    private function validateWordPressStandards(string $file): void {
        $content = file_get_contents($file);
        
        // Check for proper defined() usage
        if (!preg_match('/defined\s*\(\s*[\'"]ABSPATH[\'"]\s*\)\s*\|\|\s*exit/', $content)) {
            $this->warnings[] = "Missing ABSPATH check in {$file}";
        }
        
        // Check for proper text domain usage
        if (preg_match_all('/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $matches)) {
            foreach ($matches[2] as $domain) {
                if ($domain !== 'fp-esperienze') {
                    $this->warnings[] = "Incorrect text domain '{$domain}' in {$file}. Should be 'fp-esperienze'";
                }
            }
        }
        
        // Check for hardcoded URLs (but allow external service URLs like Google Fonts, APIs)
        if (preg_match_all('/https?:\/\/([^\s\'"]+)/', $content, $matches)) {
            $allowedDomains = [
                'fonts.googleapis.com',
                'fonts.gstatic.com', 
                'api.google.com',
                'places.googleapis.com',
                'mybusinessbusinessinformation.googleapis.com',
                'www.googleapis.com',
                'api.sendgrid.com',
                'api.mailchimp.com',
                'graph.facebook.com',
                'connect.facebook.net',
                'www.facebook.com',
                'www.googletagmanager.com',
                'analytics.google.com',
                'business.google.com',
                'support.google.com',
                'www.google.com',
                'sendinblue.com',
                'api.brevo.com',
                'schema.org',
                'httpbin.org'  // Used for testing HTTP requests
            ];
            
            foreach ($matches[1] as $url) {
                $domain = parse_url('https://' . $url, PHP_URL_HOST);
                if (!in_array($domain, $allowedDomains) && !preg_match('/localhost|127\.0\.0\.1|example\.com/', $domain)) {
                    $this->suggestions[] = "Consider using WordPress URL functions for {$domain} in {$file}";
                }
            }
        }
    }
    
    /**
     * Validate security practices in a file
     */
    private function validateSecurityPractices(string $file): void {
        $content = file_get_contents($file);
        
        // Check for user input handling
        if (preg_match('/\$_(?:GET|POST|REQUEST|COOKIE)/', $content)) {
            // Check if it's being used in proper security checks (nonce verification, sanitization)
            $hasProperHandling = (
                preg_match('/wp_verify_nonce.*\$_POST/', $content) ||  // Nonce verification
                preg_match('/sanitize_|wp_unslash|wp_kses/', $content) ||  // Sanitization
                preg_match('/isset\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE).*wp_verify_nonce/', $content)  // Combined check
            );
            
            if (!$hasProperHandling) {
                $this->warnings[] = "User input detected without proper sanitization in {$file}";
            }
        }
        
        // Check for output without escaping
        if (preg_match('/echo\s+\$/', $content)) {
            if (!preg_match('/esc_html|esc_attr|esc_url|wp_kses/', $content)) {
                $this->suggestions[] = "Consider using esc_html/esc_attr for output in {$file}";
            }
        }
        
        // Check for nonce verification in form handlers
        if (preg_match('/\$_POST/', $content) && !preg_match('/wp_verify_nonce|check_admin_referer|check_ajax_referer/', $content)) {
            // Skip if it's a class that handles nonces differently or includes proper sanitization
            $hasProperSanitization = preg_match('/sanitize_text_field.*\$_POST|sanitize_email.*\$_POST|absint.*\$_POST/', $content);
            $isWooCommerceHook = preg_match('/add_action.*woocommerce_|add_filter.*woocommerce_/', $content);
            $hasSecurityContext = preg_match('/verifyAdminAction|checkAdminCapability|current_user_can/', $content);
            
            if (!$hasProperSanitization && !$isWooCommerceHook && !$hasSecurityContext) {
                $this->warnings[] = "POST handling should include nonce verification in {$file}";
            }
        }
    }
    
    /**
     * Validate type hints in a file
     */
    private function validateTypeHints(string $file): void {
        $content = file_get_contents($file);
        
        // Look for function declarations
        preg_match_all('/(?:public|private|protected)?\s*function\s+(\w+)\s*\(([^)]*)\)(?:\s*:\s*(\w+))?/', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $functionName = $match[1];
            $parameters = $match[2];
            $returnType = $match[3] ?? null;
            
            // Skip magic methods and constructors for return type requirement
            if (!in_array($functionName, ['__construct', '__destruct', '__toString', '__invoke']) && !$returnType) {
                $this->suggestions[] = "Consider adding return type hint for {$functionName}() in {$file}";
            }
            
            // Check parameter type hints
            if ($parameters && !preg_match('/\w+\s+\$/', $parameters)) {
                $this->suggestions[] = "Consider adding parameter type hints for {$functionName}() in {$file}";
            }
        }
    }
    
    /**
     * Validate documentation in a file
     */
    private function validateDocumentation(string $file): void {
        $content = file_get_contents($file);
        
        // Check for class documentation
        if (preg_match('/class\s+\w+/', $content)) {
            if (!preg_match('/\/\*\*\s*\n\s*\*.*\*\/\s*class/', $content)) {
                $this->suggestions[] = "Missing class documentation in {$file}";
            }
        }
        
        // Check for function documentation
        preg_match_all('/(?:public|private|protected)?\s*function\s+(\w+)/', $content, $matches);
        foreach ($matches[1] as $functionName) {
            if (!preg_match("/\/\*\*.*\*\/\s*(?:public|private|protected)?\s*function\s+{$functionName}/s", $content)) {
                $this->suggestions[] = "Missing documentation for {$functionName}() in {$file}";
            }
        }
    }
    
    /**
     * Validate PHPStan configuration
     */
    private function validatePhpStanConfig(): void {
        if (!file_exists('phpstan.neon')) {
            $this->errors[] = "Missing phpstan.neon configuration file";
            return;
        }
        
        $config = file_get_contents('phpstan.neon');
        
        // Check level
        if (!preg_match('/level:\s*8/', $config)) {
            $this->warnings[] = "PHPStan level should be 8 for strict analysis";
        }
        
        // Check paths
        if (!preg_match('/paths:\s*\n\s*-\s*includes\//', $config)) {
            $this->errors[] = "PHPStan should analyze includes/ directory";
        }
        
        // Check bootstrap
        if (!preg_match('/bootstrap:\s*phpstan-bootstrap\.php/', $config)) {
            $this->warnings[] = "PHPStan should use phpstan-bootstrap.php for WordPress compatibility";
        }
    }
    
    /**
     * Validate PHPCS configuration
     */
    private function validatePhpcsConfig(): void {
        if (!file_exists('phpcs.xml')) {
            $this->errors[] = "Missing phpcs.xml configuration file";
            return;
        }
        
        $config = file_get_contents('phpcs.xml');
        
        // Check WordPress standards
        if (!preg_match('/rule\s+ref="WordPress"/', $config)) {
            $this->errors[] = "PHPCS should use WordPress coding standards";
        }
        
        // Check PHP compatibility
        if (!preg_match('/testVersion.*8\.1/', $config)) {
            $this->warnings[] = "PHPCS should check PHP 8.1+ compatibility";
        }
    }
    
    /**
     * Validate Composer configuration
     */
    private function validateComposerConfig(): void {
        if (!file_exists('composer.json')) {
            $this->errors[] = "Missing composer.json file";
            return;
        }
        
        $composer = json_decode(file_get_contents('composer.json'), true);
        
        // Check PHP version requirement
        if (!isset($composer['require']['php']) || !preg_match('/8\.1/', $composer['require']['php'])) {
            $this->warnings[] = "Composer should require PHP 8.1+";
        }
        
        // Check PSR-4 autoloading
        if (!isset($composer['autoload']['psr-4']['FP\\Esperienze\\'])) {
            $this->errors[] = "Missing PSR-4 autoload configuration for FP\\Esperienze\\";
        }
        
        // Check dev dependencies
        $devDeps = $composer['require-dev'] ?? [];
        if (!isset($devDeps['phpstan/phpstan'])) {
            $this->warnings[] = "Missing PHPStan in dev dependencies";
        }
        if (!isset($devDeps['squizlabs/php_codesniffer'])) {
            $this->warnings[] = "Missing PHPCS in dev dependencies";
        }
    }
    
    /**
     * Print results and return overall status
     */
    private function printResults(): bool {
        echo "=== RESULTS ===\n\n";
        
        if (empty($this->errors) && empty($this->warnings)) {
            echo "🎉 All checks passed! Your code follows PHP standards.\n\n";
        }
        
        if (!empty($this->errors)) {
            echo "❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "  • {$error}\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                echo "  • {$warning}\n";
            }
            echo "\n";
        }
        
        if (!empty($this->suggestions)) {
            echo "💡 SUGGESTIONS (" . count($this->suggestions) . "):\n";
            foreach ($this->suggestions as $suggestion) {
                echo "  • {$suggestion}\n";
            }
            echo "\n";
        }
        
        echo "Summary:\n";
        echo "  Errors: " . count($this->errors) . "\n";
        echo "  Warnings: " . count($this->warnings) . "\n";
        echo "  Suggestions: " . count($this->suggestions) . "\n\n";
        
        return empty($this->errors);
    }
}

// Run the checker
if (php_sapi_name() === 'cli') {
    $checker = new PhpStandardsChecker();
    $success = $checker->runAllChecks();
    exit($success ? 0 : 1);
}