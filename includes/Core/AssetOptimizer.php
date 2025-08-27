<?php
/**
 * Asset Optimizer for CSS/JS Minification
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Asset optimizer class for simple minification and concatenation
 */
class AssetOptimizer {
    
    /**
     * Assets directory
     */
    private static $assets_dir = '';
    
    /**
     * Initialize optimizer
     */
    public static function init(): void {
        if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) {
            return; // Constants not yet available, skip initialization
        }
        
        self::$assets_dir = FP_ESPERIENZE_PLUGIN_DIR . 'assets/';
        
        // Generate minified files if they don't exist or source files are newer
        add_action('init', [__CLASS__, 'maybeGenerateMinified'], 5);
        
        // Clear minified files when plugin is updated
        add_action('upgrader_process_complete', [__CLASS__, 'clearMinified'], 10, 2);
    }
    
    /**
     * Check if minified files need to be generated
     */
    public static function maybeGenerateMinified(): void {
        // Only generate in admin or if WP_DEBUG is true
        if (!is_admin() && !WP_DEBUG) {
            return;
        }
        
        $css_files = self::getCSSFiles();
        $js_files = self::getJSFiles();
        
        // Check CSS files
        foreach ($css_files as $group => $files) {
            $minified_path = self::$assets_dir . "css/{$group}.min.css";
            if (self::shouldRegenerateMinified($files, $minified_path)) {
                self::minifyCSS($files, $minified_path);
            }
        }
        
        // Check JS files
        foreach ($js_files as $group => $files) {
            $minified_path = self::$assets_dir . "js/{$group}.min.js";
            if (self::shouldRegenerateMinified($files, $minified_path)) {
                self::minifyJS($files, $minified_path);
            }
        }
    }
    
    /**
     * Get CSS files to minify
     *
     * @return array
     */
    private static function getCSSFiles(): array {
        return [
            'frontend' => [
                self::$assets_dir . 'css/frontend.css'
            ],
            'admin' => [
                self::$assets_dir . 'css/admin.css'
            ]
        ];
    }
    
    /**
     * Get JS files to minify
     *
     * @return array
     */
    private static function getJSFiles(): array {
        return [
            'frontend' => [
                self::$assets_dir . 'js/frontend.js',
                self::$assets_dir . 'js/tracking.js'
            ],
            'admin' => [
                self::$assets_dir . 'js/admin.js'
            ],
            'booking-widget' => [
                self::$assets_dir . 'js/booking-widget.js'
            ],
            'archive-block' => [
                self::$assets_dir . 'js/archive-block.js'
            ]
        ];
    }
    
    /**
     * Check if minified file should be regenerated
     *
     * @param array $source_files Source files
     * @param string $minified_path Minified file path
     * @return bool
     */
    private static function shouldRegenerateMinified(array $source_files, string $minified_path): bool {
        if (!file_exists($minified_path)) {
            return true;
        }
        
        $minified_time = filemtime($minified_path);
        
        foreach ($source_files as $source_file) {
            if (!file_exists($source_file)) {
                continue;
            }
            
            if (filemtime($source_file) > $minified_time) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Minify CSS files
     *
     * @param array $files Source files
     * @param string $output_path Output path
     * @return bool
     */
    private static function minifyCSS(array $files, string $output_path): bool {
        $combined_css = '';
        
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $css = file_get_contents($file);
            $combined_css .= "/* " . basename($file) . " */\n";
            $combined_css .= $css . "\n\n";
        }
        
        // Simple CSS minification
        $minified_css = self::minifyCSSContent($combined_css);
        
        // Add header comment
        $header = "/* FP Esperienze - Minified CSS - Generated: " . date('Y-m-d H:i:s') . " */\n";
        $minified_css = $header . $minified_css;
        
        $result = file_put_contents($output_path, $minified_css);
        
        if ($result !== false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Assets: Generated minified CSS: " . basename($output_path));
        }
        
        return $result !== false;
    }
    
    /**
     * Minify JS files
     *
     * @param array $files Source files
     * @param string $output_path Output path
     * @return bool
     */
    private static function minifyJS(array $files, string $output_path): bool {
        $combined_js = '';
        
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $js = file_get_contents($file);
            $combined_js .= "/* " . basename($file) . " */\n";
            $combined_js .= $js . "\n\n";
        }
        
        // Simple JS minification
        $minified_js = self::minifyJSContent($combined_js);
        
        // Add header comment
        $header = "/* FP Esperienze - Minified JS - Generated: " . date('Y-m-d H:i:s') . " */\n";
        $minified_js = $header . $minified_js;
        
        $result = file_put_contents($output_path, $minified_js);
        
        if ($result !== false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Assets: Generated minified JS: " . basename($output_path));
        }
        
        return $result !== false;
    }
    
    /**
     * Simple CSS minification
     *
     * @param string $css CSS content
     * @return string
     */
    private static function minifyCSSContent(string $css): string {
        // Remove comments (but preserve browser hacks)
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        
        // Remove extra spaces
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove spaces around specific characters
        $css = str_replace([' {', '{ ', ' }', '} ', '; ', ' ;', ': ', ' :', ', ', ' ,'], 
                          ['{', '{', '}', '}', ';', ';', ':', ':', ',', ','], $css);
        
        // Remove empty rules
        $css = preg_replace('/[^{}]+{\s*}/', '', $css);
        
        return trim($css);
    }
    
    /**
     * Simple JS minification
     *
     * @param string $js JS content
     * @return string
     */
    private static function minifyJSContent(string $js): string {
        // Remove single-line comments (but preserve URLs)
        $js = preg_replace('/(?<!http:)(?<!https:)\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remove extra whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove spaces around operators and punctuation
        $js = str_replace([' = ', ' == ', ' === ', ' != ', ' !== ', ' + ', ' - ', ' * ', ' / ', ' % ',
                          ' && ', ' || ', ' ( ', ' ) ', ' { ', ' } ', ' [ ', ' ] ', ' ; ', ' , '],
                         ['=', '==', '===', '!=', '!==', '+', '-', '*', '/', '%',
                          '&&', '||', '(', ')', '{', '}', '[', ']', ';', ','], $js);
        
        return trim($js);
    }
    
    /**
     * Get minified asset URL
     *
     * @param string $asset_type 'css' or 'js'
     * @param string $group Asset group name
     * @return string|false URL or false if not available
     */
    public static function getMinifiedAssetUrl(string $asset_type, string $group) {
        $minified_path = self::$assets_dir . "{$asset_type}/{$group}.min.{$asset_type}";
        
        if (!file_exists($minified_path)) {
            return false;
        }
        
        return FP_ESPERIENZE_PLUGIN_URL . "assets/{$asset_type}/{$group}.min.{$asset_type}";
    }
    
    /**
     * Check if minified assets are available
     *
     * @return bool
     */
    public static function hasMinifiedAssets(): bool {
        // Check if at least the main frontend files exist
        $frontend_css = self::$assets_dir . 'css/frontend.min.css';
        $frontend_js = self::$assets_dir . 'js/frontend.min.js';
        
        return file_exists($frontend_css) && file_exists($frontend_js);
    }
    
    /**
     * Clear all minified files
     */
    public static function clearMinified(): void {
        $patterns = [
            self::$assets_dir . 'css/*.min.css',
            self::$assets_dir . 'js/*.min.js'
        ];
        
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Assets: Cleared all minified files");
        }
    }
    
    /**
     * Get asset optimization stats
     *
     * @return array
     */
    public static function getOptimizationStats(): array {
        $css_files = self::getCSSFiles();
        $js_files = self::getJSFiles();
        
        $stats = [
            'css' => [],
            'js' => [],
            'total_original_size' => 0,
            'total_minified_size' => 0,
            'compression_ratio' => 0
        ];
        
        // CSS stats
        foreach ($css_files as $group => $files) {
            $original_size = 0;
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $original_size += filesize($file);
                }
            }
            
            $minified_path = self::$assets_dir . "css/{$group}.min.css";
            $minified_size = file_exists($minified_path) ? filesize($minified_path) : 0;
            
            $stats['css'][$group] = [
                'original_size' => $original_size,
                'minified_size' => $minified_size,
                'savings' => $original_size - $minified_size,
                'compression_ratio' => $original_size > 0 ? round((1 - $minified_size / $original_size) * 100, 1) : 0
            ];
            
            $stats['total_original_size'] += $original_size;
            $stats['total_minified_size'] += $minified_size;
        }
        
        // JS stats
        foreach ($js_files as $group => $files) {
            $original_size = 0;
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $original_size += filesize($file);
                }
            }
            
            $minified_path = self::$assets_dir . "js/{$group}.min.js";
            $minified_size = file_exists($minified_path) ? filesize($minified_path) : 0;
            
            $stats['js'][$group] = [
                'original_size' => $original_size,
                'minified_size' => $minified_size,
                'savings' => $original_size - $minified_size,
                'compression_ratio' => $original_size > 0 ? round((1 - $minified_size / $original_size) * 100, 1) : 0
            ];
            
            $stats['total_original_size'] += $original_size;
            $stats['total_minified_size'] += $minified_size;
        }
        
        // Overall compression ratio
        if ($stats['total_original_size'] > 0) {
            $stats['compression_ratio'] = round((1 - $stats['total_minified_size'] / $stats['total_original_size']) * 100, 1);
        }
        
        return $stats;
    }
}