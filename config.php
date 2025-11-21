<?php
session_start();
date_default_timezone_set('America/Chicago');

require 'vendor/autoload.php';
include('functions.php');

use Illuminate\Database\Capsule\Manager as Capsule;

// Simple template class with basic Blade syntax support
class SimpleTemplate {
    private $viewsPath;
    private $cachePath;

    public function __construct($viewsPath, $cachePath = null) {
        $this->viewsPath = $viewsPath;
        $this->cachePath = $cachePath ?: sys_get_temp_dir();
    }

    public function view() {
        return $this;
    }

    public function make($view, $data = []) {
        return new class($view, $data, $this->viewsPath, $this->cachePath) {
            private $view;
            private $data;
            private $viewsPath;
            private $cachePath;

            public function __construct($view, $data, $viewsPath, $cachePath) {
                $this->view = $view;
                $this->data = $data;
                $this->viewsPath = $viewsPath;
                $this->cachePath = $cachePath;
            }

            public function with($key, $value) {
                $this->data[$key] = $value;
                return $this;
            }

            public function render() {
                $filePath = $this->viewsPath . '/' . $this->view . '.blade.php';
                $content = file_get_contents($filePath);

                // Compile basic Blade syntax
                $content = $this->compileBlade($content);

                // Create cached PHP file
                $cacheFile = $this->cachePath . '/' . md5($this->view) . '.php';
                file_put_contents($cacheFile, $content);

                // Render
                extract($this->data);
                ob_start();
                include $cacheFile;
                return ob_get_clean();
            }

            private function compileBlade($content) {
                // Handle @extends
                if (preg_match("/@extends\('(.+?)'\)/", $content, $matches)) {
                    $layout = $this->viewsPath . '/' . $matches[1] . '.blade.php';
                    $layoutContent = file_get_contents($layout);

                    // Extract section content
                    preg_match("/@section\('content'\)(.*?)@endsection/s", $content, $sectionMatches);
                    $sectionContent = $sectionMatches[1] ?? '';

                    // Replace @yield in layout
                    $content = str_replace("@yield('content')", $sectionContent, $layoutContent);
                }

                // Remove remaining Blade directives that we don't need
                $content = preg_replace("/@extends\('(.+?)'\)/", '', $content);
                $content = preg_replace("/@section\('content'\)/", '', $content);
                $content = preg_replace("/@endsection/", '', $content);

                // Convert Blade echo syntax using str_replace instead of regex with callbacks
                $content = $this->compileEchos($content);

                return $content;
            }

            private function compileEchos($value) {
                $echoPattern = '/\{\{\s*(.+?)\s*\}\}/';
                preg_match_all($echoPattern, $value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $replacement = '<?php echo htmlspecialchars(' . $match[1] . '); ?>';
                    $value = str_replace($match[0], $replacement, $value);
                }

                $rawPattern = '/\{!!\s*(.+?)\s*!!\}/';
                preg_match_all($rawPattern, $value, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $replacement = '<?php echo ' . $match[1] . '; ?>';
                    $value = str_replace($match[0], $replacement, $value);
                }

                return $value;
            }
        };
    }
}

$views = __DIR__ . '/views';
$cache = __DIR__ . '/cache';
$template = new SimpleTemplate($views);

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'fos_dev',
    'username'  => 'fos_dev',
    'password'  => 'fos_dev_password',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Load port configuration
$portsConfigFile = __DIR__ . '/config/ports.php';
if (file_exists($portsConfigFile)) {
    $portsConfig = include $portsConfigFile;
    define('FOS_WEB_PORT', $portsConfig['web_port']);
    define('FOS_STREAM_PORT', $portsConfig['stream_port']);
    define('FOS_RTMP_PORT', $portsConfig['rtmp_port']);
} else {
    // Fallback to default ports if config doesn't exist
    define('FOS_WEB_PORT', 7777);
    define('FOS_STREAM_PORT', 8000);
    define('FOS_RTMP_PORT', 1935);
}