<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    class WPDBExtraManagerStub
    {
        public string $prefix = 'wp_';

        /** @var array<int, array{0: string, 1: array<int, mixed>}> */
        public array $prepare_calls = [];

        /** @var array<int, mixed> */
        public array $get_results_calls = [];

        /** @var array<string, array<int, object>> */
        public array $result_map = [];

        public bool $triggeredDoingItWrong = false;

        public function prepare(string $query, ...$args): array
        {
            $this->prepare_calls[] = [$query, $args];

            $placeholderCount = preg_match_all('/(?<!%)%[dsf]/', $query, $matches);

            if ($placeholderCount === 0 || $placeholderCount !== count($args)) {
                $this->triggeredDoingItWrong = true;
            }

            return [$query, $args];
        }

        /**
         * @param array{0: string, 1: array<int, mixed>}|string $query
         * @return array<int, object>
         */
        public function get_results($query): array
        {
            $this->get_results_calls[] = $query;

            if (is_array($query)) {
                [$sql, $args] = $query;
                $key = $this->buildKey($sql, $args);
            } else {
                $key = $this->buildKey($query, []);
            }

            return $this->result_map[$key] ?? [];
        }

        /**
         * @param array<int, mixed> $args
         */
        private function buildKey(string $sql, array $args): string
        {
            if ($args === []) {
                return $sql;
            }

            $normalizedArgs = array_map(static function ($value): string {
                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }

                if (is_float($value)) {
                    return rtrim(rtrim(sprintf('%.8F', $value), '0'), '.');
                }

                return (string) $value;
            }, $args);

            return $sql . '|' . implode('|', $normalizedArgs);
        }
    }

    $wpdb = new WPDBExtraManagerStub();
    $GLOBALS['wpdb'] = $wpdb;

    require_once __DIR__ . '/../includes/Data/ExtraManager.php';

    $table = $wpdb->prefix . 'fp_extras';
    $allExtrasSql = "SELECT * FROM `{$table}` ORDER BY name ASC";
    $activeExtrasSql = "SELECT * FROM `{$table}` WHERE is_active = %d ORDER BY name ASC";

    $wpdb->result_map[$allExtrasSql] = [
        (object) [
            'id' => 1,
            'name' => 'Photography',
            'is_active' => 1,
        ],
        (object) [
            'id' => 2,
            'name' => 'Private Transfer',
            'is_active' => 0,
        ],
    ];

    $wpdb->result_map[$activeExtrasSql . '|1'] = [
        (object) [
            'id' => 1,
            'name' => 'Photography',
            'is_active' => 1,
        ],
    ];

    $allExtras = \FP\Esperienze\Data\ExtraManager::getAllExtras();

    if (!is_array($allExtras)) {
        echo "Expected getAllExtras() to return an array\n";
        exit(1);
    }

    if (count($allExtras) !== 2) {
        echo "Expected getAllExtras() to return two extras\n";
        exit(1);
    }

    foreach ($allExtras as $extra) {
        if (!is_object($extra)) {
            echo "Extras should be returned as objects\n";
            exit(1);
        }
    }

    if ($wpdb->prepare_calls !== []) {
        echo "getAllExtras() should not call prepare() without filters\n";
        exit(1);
    }

    $activeExtras = \FP\Esperienze\Data\ExtraManager::getAllExtras(true);

    if (count($activeExtras) !== 1) {
        echo "Expected only active extras when filter is enabled\n";
        exit(1);
    }

    $firstActive = $activeExtras[0];
    if (!is_object($firstActive) || $firstActive->id !== 1) {
        echo "Active extras should return the Photography record\n";
        exit(1);
    }

    if (count($wpdb->prepare_calls) !== 1) {
        echo "getAllExtras(true) should use prepare() exactly once\n";
        exit(1);
    }

    if (!is_array($wpdb->get_results_calls[1])) {
        echo "Active extras query should be executed with prepared arguments\n";
        exit(1);
    }

    if ($wpdb->triggeredDoingItWrong) {
        echo "wpdb->_doing_it_wrong should not be triggered\n";
        exit(1);
    }

    echo "ExtraManager::getAllExtras tests passed\n";
}
