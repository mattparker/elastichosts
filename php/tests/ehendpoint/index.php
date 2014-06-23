<?php
/**
 * User: matt
 * Date: 23/06/14
 * Time: 11:10
 */

/**
 * Mock ElasticHosts endpoint for testing purposes
 *
 * Run it as the built-in server for php and point build process
 * at it; it should return reasonable values...
 *
 */



// Get the path:
$request = $_SERVER["REQUEST_URI"];

$routes = array(
    array(
        're' => '\/drives\/create',
        'controller' => 'Drives',
        'action' => 'createAction'
    ),
    array(
        're' => '\/drives\/[a-z0-9\-]*\/image\/[a-z0-9\-]*',
        'controller' => 'Drives',
        'action' => 'imageAction'
    ),
    array(
        're' => '\/drives\/[a-z0-9\-]*\/info',
        'controller' => 'Drives',
        'action' => 'infoAction'
    )
);


foreach ($routes as $route) {
    if (preg_match('/' . $route['re'] . '/', $request)) {
        $controller = $route['controller'];
        $method = $route['action'];
        continue;
    }
}


error_log("$controller::$method");
$args = [];

// Run the request
$controllerInstance = new $controller();
$response = $controllerInstance->{$method}($args);
echo $response;
exit;


class EH {

    protected function guid () {
        return strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)));
    }
}



class Drives extends EH {

    public function createAction ($args) {
        $guid = $this->guid();

        return PHP_EOL
. 'drive ' . $guid  . PHP_EOL
. 'encryption:cipher aes-xts-plain' . PHP_EOL
. 'name madeupname' . PHP_EOL
. 'read:bytes 4096' . PHP_EOL
. 'read:requests 1' . PHP_EOL
. 'size 12582912' . PHP_EOL
. 'status active' . PHP_EOL
. 'tier disk' . PHP_EOL
. 'user eeeeeee-1111-1111-ffff-6f6f6f6f6f6' . PHP_EOL
. 'write:bytes 4096' . PHP_EOL
. 'write:requests 1' . PHP_EOL;

    }

    public function listAction ($args) {
        return <<<DRIVELIST
019238098-192380928
945vkjfd=-dfsdfsdfk
DRIVELIST;
    }


    public function imageAction ($args) {
        return '';
    }


    public function infoAction () {
        // Randomly decide imaging status
        $r = rand(1, 10);
        $imaging = ($r < 5 ? 'false' : ($r < 7 ? 'queued' : 'true'));
        $guid = $this->guid();

        return PHP_EOL
        . 'drive ' . $guid  . PHP_EOL
        . 'encryption:cipher aes-xts-plain' . PHP_EOL

        . 'imaging ' . $imaging . PHP_EOL
        . 'name madeupname' . PHP_EOL
        . 'read:bytes 4096' . PHP_EOL
        . 'read:requests 1' . PHP_EOL
        . 'size 12582912' . PHP_EOL
        . 'status active' . PHP_EOL
        . 'tier disk' . PHP_EOL
        . 'user eeeeeee-1111-1111-ffff-6f6f6f6f6f6' . PHP_EOL
        . 'write:bytes 4096' . PHP_EOL
        . 'write:requests 1' . PHP_EOL;

    }
}