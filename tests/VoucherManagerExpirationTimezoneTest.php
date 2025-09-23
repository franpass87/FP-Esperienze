<?php
declare(strict_types=1);

use FP\Esperienze\Data\VoucherManager;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

class WpdbStub
{
    public string $prefix = 'wp_';

    /**
     * @var array<string, array>
     */
    private array $vouchers = [];

    /**
     * @var array<int, array>
     */
    public array $updates = [];

    public function registerVoucher(array $voucher): void
    {
        $code = $voucher['code'] ?? '';
        if (!is_string($code) || $code === '') {
            return;
        }

        $this->vouchers[$code] = $voucher;
    }

    public function prepare($query, ...$args): array
    {
        return [$query, $args];
    }

    public function get_row($prepared, $output = ARRAY_A)
    {
        [$query, $args] = $prepared;

        if (is_string($query) && stripos($query, 'WHERE code') !== false) {
            $code = $args[0] ?? '';
            if (is_string($code) && $code !== '') {
                return $this->vouchers[$code] ?? null;
            }
        }

        if (is_string($query) && stripos($query, 'WHERE id') !== false) {
            $id = $args[0] ?? null;
            foreach ($this->vouchers as $voucher) {
                if (($voucher['id'] ?? null) === $id) {
                    return $voucher;
                }
            }
        }

        return null;
    }

    public function update($table, $data, $where): int
    {
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
        ];

        foreach ($this->vouchers as &$voucher) {
            $matches = true;

            foreach ((array) $where as $key => $value) {
                if (($voucher[$key] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                foreach ((array) $data as $key => $value) {
                    $voucher[$key] = $value;
                }
            }
        }

        return 1;
    }

    public function getRegisteredVoucher(string $code): ?array
    {
        return $this->vouchers[$code] ?? null;
    }
}

function __(string $text, string $domain = ''): string
{
    return $text;
}

function wc_get_product($product_id)
{
    return null;
}

$GLOBALS['__fp_timezone'] = new DateTimeZone('America/New_York');
$GLOBALS['__fp_current_datetime'] = new DateTimeImmutable('2024-04-22 12:00:00', $GLOBALS['__fp_timezone']);

function wp_timezone(): DateTimeZone
{
    return $GLOBALS['__fp_timezone'];
}

function current_datetime(): DateTimeImmutable
{
    return $GLOBALS['__fp_current_datetime'];
}

function current_time(string $type, int $gmt = 0): int
{
    if ($type === 'timestamp' || $type === 'U') {
        $current = $GLOBALS['__fp_current_datetime'];

        if ($gmt === 1) {
            return $current->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
        }

        return $current->getTimestamp();
    }

    return 0;
}

require_once __DIR__ . '/../includes/Data/VoucherManager.php';

global $wpdb;
$wpdb = new WpdbStub();

$wpdb->registerVoucher([
    'id' => 501,
    'code' => 'NY-EXP-001',
    'product_id' => 0,
    'amount_type' => 'full',
    'amount' => '0',
    'recipient_name' => 'Recipient',
    'recipient_email' => 'recipient@example.com',
    'sender_name' => 'Sender',
    'message' => 'Enjoy your day!',
    'pdf_path' => null,
    'expires_on' => '2024-04-22',
    'status' => 'active',
    'order_id' => 10,
    'order_item_id' => 20,
    'send_date' => null,
    'sent_at' => null,
    'created_at' => '2024-03-01 09:00:00',
]);

$result = VoucherManager::validateVoucherForRedemption('NY-EXP-001');

if (!is_array($result) || ($result['success'] ?? false) !== true) {
    echo "Voucher should be valid throughout the local expiration day\n";
    exit(1);
}

if (!empty($wpdb->updates)) {
    echo "Voucher should not be marked expired before the end of the local day\n";
    exit(1);
}

$GLOBALS['__fp_current_datetime'] = new DateTimeImmutable('2024-04-23 00:30:00', $GLOBALS['__fp_timezone']);

$expiredResult = VoucherManager::validateVoucherForRedemption('NY-EXP-001');

if (!is_array($expiredResult) || ($expiredResult['success'] ?? true) !== false) {
    echo "Voucher should be rejected after the expiration day\n";
    exit(1);
}

if (($expiredResult['message'] ?? '') !== 'This voucher has expired.') {
    echo "Expired voucher should report the expiration message\n";
    exit(1);
}

if (count($wpdb->updates) !== 1) {
    echo "Voucher expiration should trigger a single status update\n";
    exit(1);
}

$storedVoucher = $wpdb->getRegisteredVoucher('NY-EXP-001');

if (!is_array($storedVoucher) || ($storedVoucher['status'] ?? '') !== 'expired') {
    echo "Voucher status should be updated to expired after validation\n";
    exit(1);
}

echo "Voucher expiration timezone regression test passed\n";
