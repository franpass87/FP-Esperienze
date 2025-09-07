<?php
namespace FP\Esperienze\Core {
    class CapabilityManager {
        public static bool $canManage = true;
        public static function canManageFPEsperienze(): bool {
            return self::$canManage;
        }
    }
}

namespace {
    define('ABSPATH', __DIR__);
    function register_rest_route(...$args) {}
    function __($text, $domain = null) { return $text; }

    class WP_REST_Request {
        private array $params;
        public function __construct(array $params = []) { $this->params = $params; }
        public function get_param(string $key) { return $this->params[$key] ?? null; }
    }
    class WP_REST_Response {
        public $data;
        public array $headers = [];
        public function __construct($data) { $this->data = $data; }
        public function set_headers(array $headers) { $this->headers = $headers; }
    }
    class WP_Error {
        public $code;
        public $message;
        public $data;
        public function __construct($code, $message = '', $data = []) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
    }

    require __DIR__ . '/includes/REST/ICSAPI.php';

    $baseDir = sys_get_temp_dir() . '/ics-api-test';
    $outsideDir = sys_get_temp_dir() . '/ics-api-test-outside';
    @mkdir($baseDir, 0777, true);
    @mkdir($outsideDir, 0777, true);
    file_put_contents("$baseDir/valid.ics", "VALID");
    file_put_contents("$outsideDir/outside.ics", "OUTSIDE");
    @unlink("$baseDir/malicious.ics");
    symlink("$outsideDir/outside.ics", "$baseDir/malicious.ics");

    define('FP_ESPERIENZE_ICS_DIR', $baseDir);

    $api = new \FP\Esperienze\REST\ICSAPI();

    $reqValid = new WP_REST_Request(['filename' => 'valid.ics']);
    $resValid = $api->serveICSFile($reqValid);
    echo ($resValid instanceof WP_REST_Response ? 'PASS' : 'FAIL') . " - valid file\n";

    $reqBad = new WP_REST_Request(['filename' => 'malicious.ics']);
    $resBad = $api->serveICSFile($reqBad);
    $isForbidden = ($resBad instanceof WP_Error && $resBad->code === 'forbidden');
    echo ($isForbidden ? 'PASS' : 'FAIL') . " - symlink traversal\n";
}
