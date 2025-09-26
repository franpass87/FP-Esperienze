<?php
/**
 * Centralised registry for FP Esperienze admin menu entries.
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use function add_query_arg;
use function admin_url;
use function wp_safe_redirect;
use function wp_unslash;

defined('ABSPATH') || exit;

/**
 * Stores menu definitions, separators, and legacy slug aliases so the menu can
 * be rendered in a single pass while keeping backward compatibility intact.
 */
class MenuRegistry {
    private const TOP_LEVEL_SLUG = 'fp-esperienze';

    private static ?self $instance = null;

    /**
     * Top-level menu configuration.
     */
    private array $top_level = [
        'page_title' => '',
        'menu_title' => '',
        'capability' => '',
        'menu_slug' => self::TOP_LEVEL_SLUG,
        'icon_url'   => 'dashicons-admin-generic',
        'position'   => null,
        'callback'   => null,
    ];

    /**
     * Canonical menu items keyed by slug.
     *
     * @var array<string, array>
     */
    private array $items = [];

    /**
     * Registered section separators keyed by identifier.
     *
     * @var array<string, array>
     */
    private array $separators = [];

    /**
     * Mapping of legacy slugs to canonical slugs.
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    private function __construct() {}

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Configure the top-level menu container.
     */
    public function setTopLevel(array $config): void {
        $this->top_level = array_merge($this->top_level, $config);
    }

    public function getTopLevel(): array {
        return $this->top_level;
    }

    /**
     * Register a canonical submenu page definition.
     */
    public function registerPage(array $config): void {
        if (!isset($config['slug'])) {
            return;
        }

        $slug = (string) $config['slug'];

        $defaults = [
            'page_title'  => '',
            'menu_title'  => '',
            'capability'  => 'manage_options',
            'callback'    => null,
            'order'       => 10,
            'load_actions'=> [],
            'hidden'      => false,
            'aliases'     => [],
        ];

        $definition = array_merge($defaults, $config);
        $definition['type'] = 'page';

        $this->items[$slug] = $definition;

        foreach ($definition['aliases'] as $legacy_slug) {
            $this->aliases[(string) $legacy_slug] = $slug;
        }
    }

    /**
     * Register a section separator entry that groups submenu pages.
     */
    public function registerSeparator(string $id, array $config): void {
        $defaults = [
            'label'      => '',
            'order'      => 10,
            'capability' => 'read',
        ];

        $this->separators[$id] = array_merge($defaults, $config, [
            'type' => 'separator',
        ]);
    }

    /**
     * Retrieve menu items and separators sorted by their order value.
     *
     * @return array<int, array>
     */
    public function getOrderedItems(): array {
        $entries = $this->items;

        foreach ($this->separators as $key => $separator) {
            $entries['separator:' . $key] = $separator;
        }

        uasort($entries, static function ($a, $b) {
            $order_a = isset($a['order']) ? (int) $a['order'] : 0;
            $order_b = isset($b['order']) ? (int) $b['order'] : 0;

            if ($order_a === $order_b) {
                return 0;
            }

            return ($order_a < $order_b) ? -1 : 1;
        });

        return array_values($entries);
    }

    /**
     * Retrieve the canonical definition for a slug.
     */
    public function getPage(string $slug): ?array {
        return $this->items[$slug] ?? null;
    }

    /**
     * Get alias mappings.
     *
     * @return array<string, string>
     */
    public function getAliases(): array {
        return $this->aliases;
    }

    /**
     * Perform a safe redirect from a legacy slug to its canonical slug while
     * preserving other query parameters.
     */
    public function redirectAlias(string $legacy_slug): void {
        if (!isset($this->aliases[$legacy_slug])) {
            return;
        }

        $canonical_slug = $this->aliases[$legacy_slug];
        $raw_query = wp_unslash($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $query_args = is_array($raw_query) ? $raw_query : [];
        $query_args['page'] = $canonical_slug;

        $destination = add_query_arg($query_args, admin_url('admin.php'));
        wp_safe_redirect($destination);
        exit;
    }
}
