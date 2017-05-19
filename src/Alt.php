<?php defined('ALT_PATH') OR die('No direct access allowed.');

// php5.2 support
function array_union()
{
    $args = func_get_args();

    $array1 = is_array($args[0]) ? $args[0] : array();
    $union = $array1;
    if (count($args) > 1) for ($i = 1; $i < count($args); $i++) {
        $array2 = $args[$i];
        foreach ($array2 as $key => $value) {
            if (false === array_key_exists($key, $union)) {
                $union[$key] = $value;
            }
        }
    }

    return $union;
}

if (!function_exists('http_response_code')) {
    function http_response_code($newcode = NULL)
    {
        static $code = 200;
        if ($newcode !== NULL) {
            header('X-PHP-Response-Code: ' . $newcode, true, $newcode);
            if (!headers_sent())
                $code = $newcode;
        }
        return $code;
    }
}

if (!function_exists('getallheaders')) {
    /**
     * Get all HTTP header key/values as an associative array for the current request.
     *
     * @return string[string] The HTTP header key/value pairs.
     */
    function getallheaders()
    {
        $headers = array();
        $copy_server = array(
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5' => 'Content-Md5',
        );
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $key = substr($key, 5);
                if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copy_server[$key])) {
                $headers[$copy_server[$key]] = $value;
            }
        }
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }
        return $headers;
    }
}

class Alt
{
    // environment
    const ENV_DEVELOPMENT = 1;
    const ENV_PRODUCTION = 2;
    public static $environment = self::ENV_PRODUCTION;

    // output type
    const OUTPUT_HTML = 'html';
    const OUTPUT_JSON = 'json';
    const OUTPUT_XML = 'xml';
    public static $outputs = array(
        self::OUTPUT_JSON => 'application/',
        self::OUTPUT_XML => 'application/',
        self::OUTPUT_HTML => 'text/',
    );
    public static $output = self::OUTPUT_JSON;

    // response status
    const STATUS_OK = '200';
    const STATUS_UNAUTHORIZED = '401';
    const STATUS_FORBIDDEN = '403';
    const STATUS_NOTFOUND = '404';
    const STATUS_ERROR = '500';
    public static $status = array(
        self::STATUS_OK => 'OK',
        self::STATUS_UNAUTHORIZED => 'UNAUTHORIZED',
        self::STATUS_FORBIDDEN => 'FORBIDDEN',
        self::STATUS_NOTFOUND => 'NOT FOUND',
        self::STATUS_ERROR => 'ERROR',
    );

    // profiler
    public static $timestart = 0;
    public static $timestop = 0;
    public static $config = array();

    public static $route = "route";
    public static $routes = array();
    public static function route($regex, $location)
    {
        self::$routes[$regex] = $location;
    }

    /**
     * Start Alt application
     */
    public static function start()
    {
        session_cache_limiter('private_no_expire');
        session_start();

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: *');
        header('Access-Control-Allow-Headers: accept, authorization, content-type, x-requested-with');

        // set timestart
        self::$timestart = $_SERVER['REQUEST_TIME_FLOAT'];

        // read config from file
        self::$config = include_once ALT_PATH . 'config.php';

        // set environment
        self::$environment = self::$config['app']['environment'] ? self::$config['app']['environment'] : self::$environment;

        // set log level
        Alt_Log::$level = self::$config['log']['level'] ? self::$config['log']['level'] : (self::$environment != self::ENV_DEVELOPMENT ? Alt_Log::LEVEL_ERROR : Alt_Log::LEVEL_LOG);

        // set default output
        self::$output = self::$config['app']['output'] ? self::$config['app']['output'] : self::$output;

        // can be used as a web app or command line
        switch (PHP_SAPI) {
            case 'cli':
                $baseurl = '';
                $total = (int)$_SERVER['argc'];
                if ($total > 1) for ($i = 1; $i < $total; $i++) {
                    list($key, $value) = explode('=', trim($_SERVER['argv'][$i]));

                    switch ($key) {
                        case '--uri':
                            $_SERVER['REQUEST_URI'] = strtolower($value);
                            break;
                        default:
                            $_REQUEST[$key] = $value;
                            break;
                    }
                }
                $_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : "";
                break;
            default:
                list($baseurl) = explode('index.php', $_SERVER['PHP_SELF']);

                $headers = getallheaders();
                if (isset($headers['Authorization']))
                    list($bearer, $_REQUEST['token']) = explode(' ', $headers['Authorization']);

                $input = file_get_contents('php://input');
                if ($input != "") {
                    if (self::$environment != self::ENV_DEVELOPMENT && self::$config['security']) {
                        $input = Alt_Security::decrypt($input, self::$config['security']);
                    }

                    // try to decode using json
                    $json = json_decode($input, true);
                    if ($json != null) {
                        $_REQUEST = array_union($_REQUEST, $json);
                    } else {
                        $request = array();
                        parse_str($input, $request);
                        if (count($request) > 0) {
                            $_REQUEST = array_union($request, $_REQUEST);
                            $_POST = $_REQUEST;
                        }
                    }
                }

                break;
        }

        // get authorization token
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $matches = array();
                preg_match('/Bearer (.*)/', $headers['Authorization'], $matches);
                if (isset($matches[1])) $_REQUEST['token'] = $matches[1];
            }
        }

        // get routing and output type
        $uri = substr($_SERVER['REQUEST_URI'], strlen($baseurl)) ? substr($_SERVER['REQUEST_URI'], strlen($baseurl)) : "";
        list($route) = explode('?', $uri);
        list($routing, $ext) = explode(".", $route);
        $routing = $routing ? $routing : 'index';
        $routing = str_replace('/', DIRECTORY_SEPARATOR, $routing);

        if (isset(self::$outputs[$ext])) self::$output = $ext;

        try {
            // options request from cors, skip, return empty string
            if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
                self::response(array(
                    's' => self::STATUS_OK,
                    'm' => '',
                ));
                return;
            }

            // check pre and post routing
            $routes = explode(DIRECTORY_SEPARATOR, $routing);
            if ($routes[count($routes) - 1] == 'pre' || $routes[count($routes) - 1] == 'post')
                throw new Alt_Exception("Request not found", self::STATUS_NOTFOUND);

            // pre function in config
            $fn = self::$config["route"]["pre"];
            if (is_callable($fn))
                $fn($routes);

            // pre file before controller
            $pre = ALT_PATH . self::$route . DIRECTORY_SEPARATOR . $routes[0] . DIRECTORY_SEPARATOR . 'pre.php';
            if (is_file($pre))
                include_once $pre;

            // try get file in route folder
            $isfound = false;
            $controller = ALT_PATH . self::$route . DIRECTORY_SEPARATOR;
            foreach(self::$routes as $regex => $location) {
                // Turn "(/)" into "/?"
                $pattern = preg_replace('#\(/\)#', '/?', $regex);

                // Create capture group for ":parameter"
                $allowedParamChars = '[a-zA-Z0-9\_\-]+';
                $pattern = preg_replace(
                    '/:(' . $allowedParamChars . ')/',   # Replace ":parameter"
                    '(?<$1>' . $allowedParamChars . ')', # with "(?<parameter>[a-zA-Z0-9\_\-]+)"
                    $pattern
                );

                // Create capture group for '{parameter}'
                $pattern = preg_replace(
                    '/{('. $allowedParamChars .')}/',    # Replace "{parameter}"
                    '(?<$1>' . $allowedParamChars . ')', # with "(?<parameter>[a-zA-Z0-9\_\-]+)"
                    $pattern
                );

                // Add start and end matching
                $pattern = "@^" . $pattern . "$@D";

                preg_match($pattern, str_replace(DIRECTORY_SEPARATOR, "/", $routing), $matches);
                if(count($matches) > 0){
                    // Get elements with string keys from matches
                    $params = array_intersect_key(
                        $matches,
                        array_flip(array_filter(array_keys($matches), 'is_string'))
                    );

                    foreach ($params as $key => $value) {
                        $_GET[$key] = $value;
                        $_REQUEST[$key] = $value;
                    }
                    $isfound = true;
                    $controller .= is_callable($location) ? call_user_func($location, $params) : $location;
                    break;
                }
            }

            if(!$isfound) {
                $controller .= $routing;
            }

            if (!is_file($controller . ".php"))
                throw new Exception("Request not found", self::STATUS_NOTFOUND);

            ob_start();
            $res = (include_once $controller . ".php");

            $res = ob_get_contents() ? ob_get_contents() : $res;
            ob_end_clean();

            $GLOBALS['response'] = $res;

            // post function in config
            $fn = self::$config["route"]["post"];
            if (is_callable($fn))
                $fn($routes);

            // post route
            $post = ALT_PATH . self::$route . DIRECTORY_SEPARATOR . $routes[0] . DIRECTORY_SEPARATOR . 'post.php';
            if (is_file($post))
                include_once $post;

            $res = $GLOBALS['response'] = $res;
            self::response(array(
                's' => self::STATUS_OK,
                'd' => $res,
            ));
        } catch (Alt_Exception $e) {
            self::response(array(
                's' => $e->getCode(),
                'm' => $e->getMessage(),
            ));
        } catch (Exception $e) {
            self::response(array(
                's' => self::STATUS_ERROR,
                'm' => self::$environment == self::ENV_DEVELOPMENT ? $e->getCode() . " : " . $e->getMessage() : self::$status[self::STATUS_ERROR],
            ));
        }
    }

    /**
     * Test Alt application
     */
    public static function test()
    {
        // set timestart
        self::$timestart = $_SERVER['REQUEST_TIME_FLOAT'];

        // read config
        self::$config = include_once ALT_PATH . 'config.php';

        // set environment
        self::$environment = self::$config['app']['environment'] ? self::$config['app']['environment'] : self::$environment;

        // set log level
        Alt_Log::$level = self::$config['log']['level'] ? self::$config['log']['level'] : (self::$environment != self::ENV_DEVELOPMENT ? Alt_Log::LEVEL_ERROR : Alt_Log::LEVEL_LOG);

        // set default output
        self::$output = self::$config['app']['output'] ? self::$config['app']['output'] : self::$output;
    }

    public static function autoload($class)
    {
        // Transform the class name according to PSR-0
        $class = ltrim($class, '\\');
        $file = DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';

        if (is_file(ALT_PATH . 'engine' . $file)) {
            require ALT_PATH . 'engine' . $file;
            return TRUE;
        }

        if (is_file(ALT_PATH . 'vendor/niwasmala/alt-php/src' . $file)) {
            require ALT_PATH . 'vendor/niwasmala/alt-php/src' . $file;
            return TRUE;
        }

        return FALSE;
    }

    public static function response($output = array(), $options = array())
    {
        // adding benchmark time and memory
        self::$timestop = microtime(true);
        if (self::$environment == self::ENV_DEVELOPMENT) $output['t'] = round(self::$timestop - self::$timestart, 6);
        if (self::$environment == self::ENV_DEVELOPMENT) $output['u'] = memory_get_peak_usage(true) / 1000;

        // log
        System_Log::record($output);

        // switch by output type
        switch (self::$output) {
            case self::OUTPUT_JSON:
            default:
//                $output = json_encode(unserialize(str_replace(array('NAN;', 'INF;'), '0;', serialize($output))));
                $output = json_encode($output);

                if (self::$environment != self::ENV_DEVELOPMENT && self::$config['security'])
                    $output = Alt_Security::encrypt($output, self::$config['security']);
                break;
            case self::OUTPUT_XML:
                $text = '<?xml version="1.0" encoding="UTF-8"?>';
                $text .= '<xml>';
                $text .= self::xml_encode($output['s'] == self::STATUS_OK && $output['d'] ? $output['d'] : $output['m']);
                $text .= '</xml>';
                $output = $text;

                if (self::$environment != self::ENV_DEVELOPMENT && self::$config['security'])
                    $output = Alt_Security::encrypt($output, self::$config['security']);
                break;
            case self::OUTPUT_HTML:
                $output = $output['s'] == self::STATUS_OK && $output['d'] ? $output['d'] : $output['m'];
                break;
        }

        header('Content-length: ' . strlen($output));
        echo $output;
        die;
    }

    public static function xml_encode($data)
    {
        $str = '';
        switch (gettype($data)) {
            case 'string':
            case 'number':
            case 'integer':
            case 'double':
            default:
                $str .= $data;
                break;
            case 'array':
            case 'object':
                foreach ($data as $key => $value) {
                    $str .= '<' . $key . '>';
                    $str .= self::xml_encode($value);
                    $str .= '</' . $key . '>';
                }
                break;
        }
        return $str;
    }

    public static function url(){
        $currentPath = $_SERVER['PHP_SELF'];

        // output: Array ( [dirname] => /myproject [basename] => index.php [extension] => php [filename] => index )
        $pathInfo = pathinfo($currentPath);

        // output: localhost
        $hostName = $_SERVER['HTTP_HOST'];

        // output: http://
        $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';

        // return: http://localhost/myproject/
        return $protocol.'://'.$hostName.str_replace("//", "/", $pathInfo['dirname']."/");
    }
}