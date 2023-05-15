<?php

  /**
   * ====================================================================================================
   * 
   * @Project ExpressPHP
   * @License Apache License
   * @Description
   * 
   * 
   * ====================================================================================================
   */

  namespace ExpressPHP\Standard;

  class ApplicationCore implements ApplicationInterface {
    protected RequestResponseManagerInterface $requestResponseManagerInstance;
    protected ApplicationOptions $applicationOptions;
    protected Array $routes = array();
    protected Array $middlewares = array(); // just middlewares will be executed after layout middlewares;
    protected Array $highOrderMiddlewares = array(); // middlewares that gonna be executed always first;

    public function getApplicationOptions () :ApplicationOptions { return $this->applicationOptions; }
    public function getRoutes () :Array { return $this->routes; }
    public function getMiddlewares () :Array { return $this->middlewares; }
    public function getHighOrderMiddlewares () :Array { return $this->highOrderMiddlewares; }
    
    /**
     * Require type of function
     * Will push the middleware to the list.
     * Each time new route is registed, will add to the begining of controllers these functions;
     */
    public function use ($callback, ? int $highOrder = 0) : void {
      if($highOrder === 0) {
        \array_push($this->middlewares, $callback);
      } else {
        \array_push($this->highOrderMiddlewares, $callback);
      }
    }  

    /** General Route register will create new route with HttpMethods::$GET method */
    public function get (String $path, ...$callbacks) : void {  \array_push($this->routes, new Route($path, HttpMethods::$GET, $callbacks, $this)); }
    /** General Route register will create new route with HttpMethods::$POST method */
    public function post (String $path, ...$callbacks) : void {  \array_push($this->routes, new Route($path, HttpMethods::$POST, $callbacks, $this)); }
    /** General Route register will create new route with HttpMethods::$DELETE method */
    public function delete (String $path, ...$callbacks) : void {  \array_push($this->routes, new Route($path, HttpMethods::$DELETE, $callbacks, $this)); }
    /** General Route register will create new route with HttpMethods::$PUT method */
    public function put (String $path, ...$callbacks) : void {  \array_push($this->routes, new Route($path, HttpMethods::$PUT, $callbacks, $this)); }
    /** General Route register will create new route with HttpMethods::$PATCH method */
    public function patch (String $path, ...$callbacks) : void {  \array_push($this->routes, new Route($path, HttpMethods::$PATCH, $callbacks, $this)); }
    /** General Route register will create new route with HttpMethods::$HEAD method */
    public function head (String $path, ...$callbacks) : void {  \array_push($this->routes, new Route($path, HttpMethods::$HEAD, $callbacks, $this)); }
    /** General Route register will create new route with HttpMethods::$OPTIONS method */
    public function options (String $path, ...$callbacks) : void {  \array_push($this->routes, new Route($path, HttpMethods::$OPTIONS, $callbacks, $this)); }

    /**
     * @doc this method preparing the actual path that mean
     * pre_path will be removed from actual full_path;
     */
    public function preparePath (String $path) :String {
      if($this->applicationOptions->begin() === "") {
        return $path;
      } else {
        $parts = \explode($this->applicationOptions->begin(), $path);
        return isset($parts[1]) ? $parts[1] : $parts[0];
      }
      
    }

    public function htmlLogApis () { 
      for ($i=0; $i < count($this->routes); $i++) { $this->routes[$i]->logHtml(); }
    }

    // debug function
    public function logInstance () {
      echo "<div style='border: 1px solid red; padding: 1rem; width: 100%'>";
        echo "<strong>middlewares:</strong> <br />" . json_encode($this->middlewares) . "<hr />";
        echo "<strong>highorder middlewares:</strong> <br />" . json_encode($this->highOrderMiddlewares) . "<hr />";
        echo "<strong>Application Options:</strong> <br />" . json_encode($this->applicationOptions->getOptions()) . "<hr />";
      echo "</div>";
    }

    public function run () {
      $path_query = \explode("?", $_SERVER["REQUEST_URI"]);
      $current_path = $path_query[0];

      $current_path_parts = splitAndFilterPathParts($current_path);

      $current = matching_route($current_path_parts, $this->routes);

      if($current === null) {
        $current = matching_nonexact_route($current_path_parts, $this->routes);
      }

      if($current === null) {
        echo "Route not found || 404<br /> <br />";
        //logA(array("type" => "in run function", "currentpath" => $current_path_parts, "length" => \count($current_path_parts)));
        return;
      }

      $next = true;
      $request = $this->requestResponseManagerInstance->getRequestInstance();
      $request->setApplicationOptions($this->applicationOptions);
      $request->setCurrent($current);
      $response = $this->requestResponseManagerInstance->getResponseInstance();
      
      
      // Clear the output buffer
      
      ob_start();
      for ($index=0; $index < \count($current->controllers); $index++) { 
        if($next === false) { break; }
        $next = false;
        if($current->controllers[$index]["type"] === "function") {
          // primary express like controller callback or middleware;
          $controller_function = $current->controllers[$index]["value"];
          $controller_function($request, $response, $next);

        } else if($current->controllers[$index]["type"] === "page") {
          // if page type, it still required to use $next = true like if it was controller function;
          include ($this->applicationOptions->getControllerPath() . $current->controllers[$index]["value"] . ".php");
          $next = true;

        } else if($current->controllers[$index]["type"] === "layout") {
          // if layout type, it will auto pass to next controller;
          if($response->getContentType() == "text/html; charset=utf-8") {
            include ($this->applicationOptions->getLayoutPath() . $current->controllers[$index]["value"]);
          }
          $next = true;

        } else {
          include $current->controllers[$index]["value"];
        }
      }
    }

  }

  class Application extends ApplicationCore implements ApplicationInterface {

    public function __construct(?ApplicationOptions $applicationOptions=null) {
      if($applicationOptions !== null) {
        $this->applicationOptions = $applicationOptions;
        $this->requestResponseManagerInstance = $applicationOptions->useRequestResponseManager();
      } else {
        $applicationOpt = new ApplicationOptions();
        $this->applicationOptions = $applicationOpt;
        $this->requestResponseManagerInstance = $applicationOpt->useRequestResponseManager();
      }
    }

    public function useRouter ($r, Bool $parent_layout=false) {
      $router_routes = $r->getRoutes();
      for ($i=0; $i < \count($router_routes); $i++) { 
        \array_push($this->routes, $router_routes[$i]);
      }
    }



  }
  

  class Router extends ApplicationCore implements ApplicationInterface {
    public String $name;

    public function __construct(?ApplicationOptions $applicationOptions=null) {
      if($applicationOptions !== null) {
        $this->applicationOptions = $applicationOptions;
      } else {
        $applicationOpt = new ApplicationOptions();
        $this->applicationOptions = $applicationOpt;
      }
      $this->requestResponseManagerInstance = $this->applicationOptions->useRequestResponseManager();
    }

    /**
     * 
     */
    public function setLayouts (?String $topl, ?String $bottoml) {
      if(isset($topl)) {
        $this->applicationOptions->setLayoutTop($topl);
      }
      if(isset($bottoml)) {
        $this->applicationOptions->setLayoutBottom($bottoml);
      }
    }

  }



  
  // **************************************************
  // @ EXPRESSPHP STATIC TYPES @
  // **************************************************

  class ExpressPHPVariables {
    static $NAME = "ExpressPHP";
    static $VERSION = "1.0.0";
    static $REACT_SCRIPT = "https://unpkg.com/react@17.0.2/umd/react.development.js";
    static $REACTDOM_SCRIPT = "https://unpkg.com/react-dom@17.0.2/umd/react-dom.development.js";
    static $BABEL_SCRIPT = "https://unpkg.com/babel-standalone@6.26.0/babel.min.js";
    static $GITHUB_REPOSITORY = "https://github.com/breathfunwithmind/express-php";
    static $LAYOUT_PATH = "ExpressPHP/views/layouts/";
    static $CONTROLLER_PATH = "ExpressPHP/views/pages/";
    static $REACTJS_PATH = "ExpressPHP/views/reactjs";
    static $BEGIN = "";
    static $AUTHOR = "Mike Karypidis";
    static $COPYRIGHT = "";
    static $ORGANIZATION = "PerfecTEvolutioN";
    static $DOC_LINK = "https://express-php.org";
    static $SUPPORT_LINK = "https://express-php-support.empedus.services/request";
  }

  class HttpMethods {
    static $GET = "GET";
    static $POST = "POST";
    static $PATCH = "PATCH";
    static $PUT = "PUT";
    static $DELETE = 'DELETE';
    static $HEAD = "HEAD";
    static $OPTIONS = "OPTIONS";
  }

  // ~ ExpressPHP Static Types ENDLINE;
  // **************************************************

  // **************************************************
  // @ EXPRESSPHP PRIMARY CLASSES @
  // **************************************************

  class Request implements RequestInterface {
    public ApplicationOptions $applicationOptions;
    public Route $current;
    public $REDIRECT_UNIQUE_ID;
    public $REDIRECT_STATUS;
    public $UNIQUE_ID;
    public $HTTP_HOST;
    public $HTTP_USER_AGENT;
    public $HTTP_ACCEPT;
    public $HTTP_ACCEPT_LANGUAGE;
    public $HTTP_ACCEPT_ENCODING;
    public $HTTP_CONNECTION;
    public $HTTP_UPGRADE_INSECURE_REQUESTS;
    public $HTTP_SEC_FETCH_DEST;
    public $HTTP_SEC_FETCH_MODE;
    public $HTTP_SEC_FETCH_SITE;
    public $PATH;
    public $SERVER_SIGNATURE;
    public $SERVER_SOFTWARE;
    public $SERVER_NAME;
    public $SERVER_ADDR;
    public $REMOTE_ADDR;
    public $DOCUMENT_ROOT;
    public $REQUEST_SCHEME;
    public $CONTEXT_PREFIX;
    public $CONTEXT_DOCUMENT_ROOT;
    public $SERVER_ADMIN;
    public $SCRIPT_FILENAME;
    public $REMOTE_PORT;
    public $REDIRECT_URL;
    public $REDIRECT_QUERY_STRING;
    public $GATEWAY_INTERFACE;
    public $SERVER_PROTOCOL;
    public $REQUEST_METHOD;
    public $QUERY_STRING;
    public $REQUEST_URI;
    public $SCRIPT_NAME;
    public $PHP_SELF;
    public $REQUEST_TIME_FLOAT;
    public $REQUEST_TIME;
    public $USER;
    public $DATA;

    public function __construct() {
      if(isset($_SERVER["REDIRECT_UNIQUE_ID"])) { $this->REDIRECT_UNIQUE_ID = $_SERVER["REDIRECT_UNIQUE_ID"]; }
      if(isset($_SERVER["REDIRECT_STATUS"])) { $this->REDIRECT_STATUS = $_SERVER["REDIRECT_STATUS"]; }
      if(isset($_SERVER["UNIQUE_ID"])) { $this->UNIQUE_ID = $_SERVER["UNIQUE_ID"]; }
      if(isset($_SERVER["HTTP_HOST"])) { $this->HTTP_HOST = $_SERVER["HTTP_HOST"]; }
      if(isset($_SERVER["HTTP_USER_AGENT"])) { $this->HTTP_USER_AGENT = $_SERVER["HTTP_USER_AGENT"]; }
      if(isset($_SERVER["HTTP_ACCEPT"])) { $this->HTTP_ACCEPT = $_SERVER["HTTP_ACCEPT"]; }
      if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) { $this->HTTP_ACCEPT_LANGUAGE = $_SERVER["HTTP_ACCEPT_LANGUAGE"]; }
      if(isset($_SERVER["HTTP_ACCEPT_ENCODING"])) { $this->HTTP_ACCEPT_ENCODING = $_SERVER["HTTP_ACCEPT_ENCODING"]; }
      if(isset($_SERVER["HTTP_CONNECTION"])) { $this->HTTP_CONNECTION = $_SERVER["HTTP_CONNECTION"]; }
      if(isset($_SERVER["HTTP_UPGRADE_INSECURE_REQUESTS"])) { $this->HTTP_UPGRADE_INSECURE_REQUESTS = $_SERVER["HTTP_UPGRADE_INSECURE_REQUESTS"]; }
      if(isset($_SERVER["HTTP_SEC_FETCH_DEST"])) { $this->HTTP_SEC_FETCH_DEST = $_SERVER["HTTP_SEC_FETCH_DEST"]; }
      if(isset($_SERVER["HTTP_SEC_FETCH_MODE"])) { $this->HTTP_SEC_FETCH_MODE = $_SERVER["HTTP_SEC_FETCH_MODE"]; }
      if(isset($_SERVER["HTTP_SEC_FETCH_SITE"])) { $this->HTTP_SEC_FETCH_SITE = $_SERVER["HTTP_SEC_FETCH_SITE"]; }
      if(isset($_SERVER["PATH"])) { $this->PATH = $_SERVER["PATH"]; }
      if(isset($_SERVER["LD_LIBRARY_PATH"])) { $this->LD_LIBRARY_PATH = $_SERVER["LD_LIBRARY_PATH"]; }
      if(isset($_SERVER["SERVER_SIGNATURE"])) { $this->SERVER_SIGNATURE = $_SERVER["SERVER_SIGNATURE"]; }
      if(isset($_SERVER["SERVER_SOFTWARE"])) { $this->SERVER_SOFTWARE = $_SERVER["SERVER_SOFTWARE"]; }
      if(isset($_SERVER["SERVER_NAME"])) { $this->SERVER_NAME = $_SERVER["SERVER_NAME"]; }
      if(isset($_SERVER["SERVER_ADDR"])) { $this->SERVER_ADDR = $_SERVER["SERVER_ADDR"]; }
      if(isset($_SERVER["REMOTE_ADDR"])) { $this->REMOTE_ADDR = $_SERVER["REMOTE_ADDR"]; }
      if(isset($_SERVER["DOCUMENT_ROOT"])) { $this->DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"]; }
      if(isset($_SERVER["REQUEST_SCHEME"])) { $this->REQUEST_SCHEME = $_SERVER["REQUEST_SCHEME"]; }
      if(isset($_SERVER["CONTEXT_PREFIX"])) { $this->CONTEXT_PREFIX = $_SERVER["CONTEXT_PREFIX"]; }
      if(isset($_SERVER["CONTEXT_DOCUMENT_ROOT"])) { $this->CONTEXT_DOCUMENT_ROOT = $_SERVER["CONTEXT_DOCUMENT_ROOT"]; }
      if(isset($_SERVER["SERVER_ADMIN"])) { $this->SERVER_ADMIN = $_SERVER["SERVER_ADMIN"]; }
      if(isset($_SERVER["SCRIPT_FILENAME"])) { $this->SCRIPT_FILENAME = $_SERVER["SCRIPT_FILENAME"]; }
      if(isset($_SERVER["REMOTE_PORT"])) { $this->REMOTE_PORT = $_SERVER["REMOTE_PORT"]; }
      if(isset($_SERVER["REDIRECT_URL"])) { $this->REDIRECT_URL = $_SERVER["REDIRECT_URL"]; }
      if(isset($_SERVER["REDIRECT_QUERY_STRING"])) { $this->REDIRECT_QUERY_STRING = $_SERVER["REDIRECT_QUERY_STRING"]; }
      if(isset($_SERVER["GATEWAY_INTERFACE"])) { $this->GATEWAY_INTERFACE = $_SERVER["GATEWAY_INTERFACE"]; }
      if(isset($_SERVER["SERVER_PROTOCOL"])) { $this->SERVER_PROTOCOL = $_SERVER["SERVER_PROTOCOL"]; }
      if(isset($_SERVER["QUERY_STRING"])) { $this->QUERY_STRING = $_SERVER["QUERY_STRING"]; }
      if(isset($_SERVER["REQUEST_URI"])) { $this->REQUEST_URI = $_SERVER["REQUEST_URI"]; }
      if(isset($_SERVER["SCRIPT_NAME"])) { $this->SCRIPT_NAME = $_SERVER["SCRIPT_NAME"]; }
      if(isset($_SERVER["PHP_SELF"])) { $this->PHP_SELF = $_SERVER["PHP_SELF"]; }
      if(isset($_SERVER["REQUEST_TIME_FLOAT"])) { $this->REQUEST_TIME_FLOAT = $_SERVER["REQUEST_TIME_FLOAT"]; }
      if(isset($_SERVER["REQUEST_TIME"])) { $this->REQUEST_TIME = $_SERVER["REQUEST_TIME"]; }
    }

    public function setApplicationOptions(ApplicationOptions &$options) : void { $this->applicationOptions = $options; }
    public function link() :String { return  $this->domain . "/" . $this->applicationOptions->begin(); }
    public function getApplicationOptions() : ApplicationOptions { return $this->applicationOptions; }

    public function setCurrent (Route $current) : void { $this->current = $current; }
    
    public function getParams () : Array {return $this->current->params; }

    public function getParam (string $key) {
      if(isset($this->current->params[$key])) {
        return $this->current->params[$key];
      }
      return null;
    }
    
    /** returns Array of request headers; */
    public function getHeaders () : ?Array { return getallheaders(); }
    /** returns String of current request header or null; */
    public function getHeader (String $key) : ?String {
      $headers = $this->getHeaders();
      if(!$headers) return null;
      if(isset($headers[$key])) { return $headers[$key]; }
      return null;
    }

    /** simple get params returns $_GET; */
    public function getQueries () : Array {return $_GET; }
    /** simple get params returns $_POST; */
    public function getFormstate () : Array { return $_POST; }
    public function getFiles () { return $_FILES; }
   
    public function getBody ()
    {
      $content_type = $this->getHeader("Content-Type");
      switch ($content_type) {
        case "application/json": return $this->getJsonBody($this->getRawBody()); break;
        case "application/javascript": return $this->getJSBody($this->getRawBody()); break;
        case "application/xml": return $this->getXMLBody($this->getRawBody()); break;
        case "text/html": return $this->getXTMLBody($this->getRawBody()); break;
        case "text/plain": return $this->getTextBody($this->getRawBody()); break;
        case "application/x-www-form-urlencoded": return $this->getTextBody($this->getRawBody()); break;
        default: return $this->getTextBody($this->getRawBody()); break;
      }
    }

    public function getJsonBody (String $raw_string) { return \json_decode($raw_string, true); }
    public function getTextBody (String $raw_string) { return $raw_string; }
    public function getJSBody (String $raw_string) { return $raw_string; }
    public function getXTMLBody (String $raw_string) { return $raw_string; }
    public function getXMLBody (String $raw_string) { return $raw_string; }
    public function getFormURLEncodedBody (String $raw_string) { return $raw_string; }

    /** simple get params returns $_GET; */
    public function getRawBody () : String { return file_get_contents('php://input'); }

    public function setUser ($user) { $this->USER = $user; }
    public function user () { return $this->USER; }
    public function add (String $key, $value) { $this->DATA[$key] = $value; }
    public function get (String $key) {
      if(isset($this->DATA[$key])) return $this->DATA[$key];
      return null;
    }


  }

  class Response implements ResponseInterface {
    private $status;
    private $contentType = 'text/html; charset=utf-8';
    public function send (String $something) : void { echo $something; }
    public function json (Array $something) : void {
      $this->contentType = 'application/json; charset=utf-8';
      header("Content-Type: $this->contentType");
      ob_clean();
      echo \json_encode($something);
    }
    public function status(Int $status) { $this->status = $status; return $this; }
    public function getStatus() { return $this->status; }
    public function getContentType() { return $this->contentType; }
  }

  // todo on version 2 add two more Response class versions to handle mongodb and mysql.

  class MySQLResponse {

  }

  class MongoDBResponse {

  }
 
  // ~ ExpressPHP Primary Classes ENDLINE;
  // **************************************************

  

  // **************************************************
  // @ EXPRESSPHP INTERFACES @
  // **************************************************

  interface RequestInterface {

    public function setApplicationOptions(ApplicationOptions &$options) : void;
    public function link() :String;
    public function getApplicationOptions() : ApplicationOptions;

    public function setCurrent (Route $current) : void;
    public function getParams () : Array;
    public function getParam  (string $key);
    public function getQueries () : Array;
    public function getBody ();
    public function getHeaders () : ?Array;
    public function getHeader (String $key) : ?String;
    public function getRawBody () : String;
    public function getFormstate () : Array;

    public function getJsonBody (String $raw_string);
    public function getTextBody (String $raw_string);
    public function getJSBody (String $raw_string);
    public function getXTMLBody (String $raw_string);
    public function getFormURLEncodedBody (String $raw_string);
    public function getXMLBody (String $raw_string);

    public function setUser($user);
    public function user();
    public function add(String $key, $value);
    public function get(String $key);

  }

  interface ResponseInterface {
    public function status(Int $status);
    public function send(String $something) : void;
    public function json(Array $something) : void;
    public function getStatus();
    public function getContentType();
  }

  interface RequestResponseManagerInterface {
    public function getRequestInstance () : Request;
    public function getResponseInstance () : Response;
  }

  /**
   * abstract class for core application
   * also used for router
   */
  interface ApplicationInterface {
    public function use ($callback, ? int $highOrder = 0) : void;
    public function get (String $path, ...$callbacks) : void;
    public function post (String $path, ...$callbacks) : void;
    public function delete (String $path, ...$callbacks) : void;
    public function put (String $path, ...$callbacks) : void;
    public function patch (String $path, ...$callbacks) : void;
    public function head (String $path, ...$callbacks) : void;
    public function options (String $path, ...$callbacks) : void;
    public function preparePath (String $path) : String;
    public function getApplicationOptions () :ApplicationOptions;
    public function getRoutes () :Array;
    public function getMiddlewares () :Array;
    public function getHighOrderMiddlewares () :Array;
  }
 
  // ~ ExpressPHP Interfaces ENDLINE;
  // **************************************************


  // **************************************************
  // @ EXPRESSPHP TYPINGS @
  // **************************************************

  /**
  * Default Request Response Manager;
  *
  * @author     Mike Karypidis
  * @package    ExpressPHP
  * @since      1.0
  * @version    my-version
  */
  class RequestResponseManager implements RequestResponseManagerInterface {
    public function getRequestInstance () : Request {
      return new Request();
    }
    public function getResponseInstance () : Response {
      return new Response();
    }
  }

  /**
  * Application will have instance of this class and it is required; 
  *
  * @author     Mike Karypidis
  * @package    ExpressPHP
  * @since      1.0
  * @version    my-version
  */
  class Route {
    public String $path;
    public String $method;
    public Array $path_parts;
    public Array $current_path_parts = array();
    public Array $dynamic_path_parts = array();
    public Array $params = array();
    public $usemin; // boolean
    public $size; // int
    public String $router;
    public String $begin;
    public Array $controllers = array();
    public ApplicationOptions $appOptions;
    public ApplicationCore $appCore;

    public function __construct (
      String $path_prop, 
      String $method_prop, 
      Array $controllers, 
      ApplicationCore $applicationCore
      ) 
    {
      $path_props_usable = $applicationCore->getApplicationOptions()->begin() . $path_prop;

      $this->path =  $path_props_usable;
      $this->method = $method_prop;
      $this->appOptions = $applicationCore->getApplicationOptions();
      $this->appCore = $applicationCore;
      $this->begin = $applicationCore->getApplicationOptions()->begin();

      $preparePathParts = 'ExpressPHP\Standard\splitAndFilterPathParts'; // set function to variable;
      $this->path_parts = $preparePathParts($path_props_usable);
      $prepareControllers = 'prepareControllers'; // set private method to variable
      $this->$prepareControllers($controllers);
      $prepareDynamicPathParts = 'prepareDynamicPathParts'; // set private method to variable
      $this->$prepareDynamicPathParts();

      if(\str_ends_with($path_props_usable, "/*")) {
        $this->usemin = true;
        $this->size = count($this->path_parts) - 1;
      } else {
        $this->usemin = false;
        $this->size = count($this->path_parts);
      }

    }

    private function preparePathPart ($path_prop) : void {
      $initialPathParts = \explode("/", $path_prop);
      for ($i=0; $i < count($initialPathParts); $i++) { if($initialPathParts[$i] !== "") { \array_push($this->path_parts, $initialPathParts[$i]); } }
    }

    /**
     * There are 3 type of controllers. The Layout controller, the function controller and the string-page controller;
     * 
     * @author     Mike Karypidis
     * @package    ExpressPHP
     * @since      1.0
     * @version    my-version
     */
    private function prepareControllers (Array $controllers) :void {
      /**
       * First of all we will add high order middleware before anything else;
       * 
       * next we check for layout, if there is one, we set layout middleware;
       * next we set middlewares
       * next we set controllers
       * final we set bottom layout if there is one
       */

      // high order middlewares
      for ($prei=0; $prei < count($this->appCore->getHighOrderMiddlewares()); $prei++) { 
        if(\is_callable($this->appCore->getHighOrderMiddlewares()[$prei])) {
          \array_push($this->controllers, array("type"=> "function", "value" => $this->appCore->getHighOrderMiddlewares()[$prei]));
        } else {
          \array_push($this->controllers, array("type"=> "string", "value" => $this->appCore->getHighOrderMiddlewares()[$prei]));
        }
      }
      // top layout
      if($this->appOptions->getLayoutTop()) {
        \array_push($this->controllers, array("type"=> "layout", "value" => $this->appOptions->getLayoutTop()));
      }
      // middlewares
      for ($prei=0; $prei < count($this->appCore->getMiddlewares()); $prei++) { 
        if(\is_callable($this->appCore->getMiddlewares()[$prei])) {
          \array_push($this->controllers, array("type"=> "function", "value" => $this->appCore->getMiddlewares()[$prei]));
        } else {
          \array_push($this->controllers, array("type"=> "string", "value" => $this->appCore->getMiddlewares()[$prei]));
        }
      }
      // controllers
      for ($i=0; $i < count($controllers); $i++) { 
        if(\is_callable($controllers[$i])) {
          \array_push($this->controllers, array("type"=> "function", "value" => $controllers[$i]));
        } else {
          \array_push($this->controllers, array("type"=> "page", "value" => $controllers[$i]));
        }
      }
      // bottom layout
      if($this->appOptions->getLayoutBottom()) {
        \array_push($this->controllers, array("type"=> "layout", "value" => $this->appOptions->getLayoutBottom()));
      }
    }

    private function prepareDynamicPathParts () {
      for ($i=0; $i < \count($this->path_parts); $i++) { 
        if(\str_starts_with($this->path_parts[$i], ":")) {
          \array_push($this->dynamic_path_parts, array(
            "index" => $i, 
            "property" => substr( $this->path_parts[$i], 1, \strlen($this->path_parts[$i]) - 1)
          ));
        }
      }
    }

    public function setCurrentPathParts ($current_path_parts)
    {
      $this->current_path_parts = $current_path_parts;
      for ($i=0; $i < \count($this->dynamic_path_parts); $i++) { 
        if(isset($current_path_parts[$this->dynamic_path_parts[$i]["index"]])) {
          $this->params[$this->dynamic_path_parts[$i]["property"]] = $current_path_parts[$this->dynamic_path_parts[$i]["index"]];
        }
      }
    }

    public function getPath () :String
    {
      return $this.path;
    }

    public function toJSON () :String {
      return \json_encode(array(
        "path" => $this->path, 
        "method" => $this->method, 
        "path_parts"=> $this->path_parts,
        "controllers" => json_encode($this->controllers),
        "begin" => $this->begin
      ));
    }
    public function logHtml () :void {
      echo "<div style='padding: 1rem; box-shadow: 1px 1px 6.9px 0.14px rgba(0,0,0,0.23)'><ul>";
      $p = $this->path;
      echo "<li><strong>PATH: </strong><em>$p</em></li>";
      $n = $this->appOptions->name;
      echo "<li><strong>NAME: </strong><em>$n</em></li>";
      $b = $this->begin;
      echo "<li><strong>BEGIN: </strong><em>$b</em></li>";
      $m = $this->method;
      echo "<li><strong>METHOD: </strong><em>$m</em></li>";
      $pp = json_encode($this->path_parts);
      echo "<li><strong>PATH_PARTS: </strong><em>$pp</em></li>";
      $dpp = json_encode($this->dynamic_path_parts);
      echo "<li><strong>DYNAMIC_PATH_PARTS: </strong><em>$dpp</em></li>";
      echo "<li><strong>CONTROLLERS: </strong><br />";
      for ($i=0; $i < \count($this->controllers); $i++) { 
        $cv = \json_encode($this->controllers[$i]);
        echo $cv . "<br />";
      }
      echo "</li>";
      $um = $this->usemin;
      if($um == true) {
        echo "<li><strong>USEMMIN: </strong><em>TRUE</em></li>";
      } else {
        echo "<li><strong>USEMMIN: </strong><em>FALSE</em></li>";
      }
      if(\count($this->current_path_parts) !== 0) {
        $cp = json_encode($this->current_path_parts);
        echo "<li><strong>PATH_PARTS: </strong><em>$cp</em></li>";
      }
      $m = json_encode($this->size);
      echo "<li><strong>SIZE: </strong><em>$m</em></li>";

      echo "<ul></div>";
    }

  }

  /**
  * Application will have instance of this class and it is required; 
  * Used for general application configurations as well to transfer the requestresponsemanagerinstance to the main app;
  *
  * @author     Mike Karypidis
  * @package    ExpressPHP
  * @since      1.0
  * @version    my-version
  */
  class ApplicationOptions {
    private $applicationName;
    private $requestResponseManagerInstance;
    private String $begin;
    private String $controllerPath;
    private String $layoutPath;
    
    private String $react_script;
    private String $reactdom_script;
    private String $babel_script;

    private ?String $layout_top = null;
    private ?String $layout_bottom = null;
    
    public String $name;

    public function getOptions () {
      return array(
        "name" => $this->name,
        "applicationName" => $this->applicationName,
        "begin" => $this->begin,
        "controllerPath" => $this->controllerPath,
        "layoutPath" => $this->layoutPath,

        "react_script" => $this->react_script,
        "reactdom_script" => $this->reactdom_script,
        "babel_script" => $this->babel_script,

        "layout_top" => $this->layout_top,
        "layout_bottom" => $this->layout_bottom
      );
    }

    /**
     * matching the substringed version of path; 
     */
    public function __construct($name=null, Array $options = array()) {
      $this->name = $name == null ? ExpressPHPVariables::$NAME : $name;
      
      $this->controllerPath = isset($options["controller_path"]) ? $options["controller_path"] : ExpressPHPVariables::$CONTROLLER_PATH;
      $this->layoutPath = isset($options["layout_path"]) ? $options["layout_path"] : ExpressPHPVariables::$LAYOUT_PATH;

      $this->begin = isset($options["pre_path"]) ? $options["pre_path"] : ExpressPHPVariables::$BEGIN;
      $this->react_script = isset($options["react_script"]) ? $options["react_script"] : ExpressPHPVariables::$REACT_SCRIPT;
      $this->reactdom_script = isset($options["reactdom_script"]) ? $options["reactdom_script"] : ExpressPHPVariables::$REACTDOM_SCRIPT;
      $this->babel_script = isset($options["babel_script"]) ? $options["babel_script"] : ExpressPHPVariables::$BABEL_SCRIPT;
    }
    public function begin () : string { return $this->begin; }
    public function getControllerPath () : string { return $this->controllerPath; }

    public function setBegin(String $beginProp) :void {
      $this->begin = $beginProp;
    }

    public function setRequestResponseManager (RequestResponseManagerInterface $requestResponseManagerInstance) :void { 
      $this->requestResponseManagerInstance = $requestResponseManagerInstance; 
    }
    public function useRequestResponseManager () : RequestResponseManagerInterface {
      if(!$this->requestResponseManagerInstance) {
        return new RequestResponseManager();
      }
      return $this->requestResponseManagerInstance;
    }
    public function getApplicationName () : String { return $this->applicationName; }
    public function setApplicationName (String $name) : void { $this->applicationName = $name; }

    public function setLayoutTop (String $layout_top) :void { $this->layout_top = $layout_top; }
    public function setLayoutBottom (String $layout_bottom) :void { $this->layout_bottom = $layout_bottom; }
    public function getLayoutTop () :?String { return $this->layout_top; }
    public function getLayoutBottom () :?String { return $this->layout_bottom; }

    public function setLayoutPath (String $layout_path) { $this->layoutPath = $layout_path; }
    public function getLayoutPath () :String { return $this->layoutPath; }
    public function setReactScript (String $react_script) { $this->react_script = $react_script; }
    public function getReactScript () :String { return $this->react_script; }
    public function setReactDOMScript (String $react_dom_script) { $this->reactdom_script = $react_dom_script; }
    public function getReactDOMScript () :String { return $this->reactdom_script; }
    public function setBabelScript (String $babel_script) { $this->babel_script = $babel_script; }
    public function getBabelScript () :String { return $this->babel_script; }


  }

  // ~ ExpressPHP Typings ENDLINE;
  // **************************************************

  // **************************************************
  // @ EXPRESSPHP EXCEPTIONS TYPES @
  // **************************************************

  /**
   * @ApplicationOptions
   * @doc - used by core application costructor;
   */
  class FatalException extends \Exception {
    

    public function __construct ()
    {

    }

  }

  // ~ ExpressPHP Exceptions Types;
  // **************************************************

  // **************************************************
  // @ EXPRESSPHP HELPER FUNCTIONS @
  // **************************************************

  function splitAndFilterPathParts ($path_prop) : array {
    $tmpArray = array();
    $initialPathParts = \explode("/", $path_prop);
    for ($i=0; $i < count($initialPathParts); $i++) { if($initialPathParts[$i] !== "") { \array_push($tmpArray, $initialPathParts[$i]); } }
    return $tmpArray;
  }
  
  function matching_route (Array $current_path_parts, Array $routes) {
    $used_routes = array();
    for ($i=0; $i < count($routes); $i++) { 
      if($routes[$i]->size === \count($current_path_parts) and $routes[$i]->usemin !== true and $routes[$i]->method === $_SERVER["REQUEST_METHOD"]) { \array_push($used_routes, $routes[$i]); } 
    }
    $current = null;
    for ($j=0; $j < \count($used_routes); $j++) { 
      $ok = true;
      for ($x=0; $x < \count($used_routes[$j]->path_parts); $x++) { 
        if(\str_starts_with($used_routes[$j]->path_parts[$x], ":")) continue;
        if($used_routes[$j]->path_parts[$x] !== $current_path_parts[$x]) { $ok = false; }
      }
      if($ok === true) { $current = $used_routes[$j]; $current->setCurrentPathParts($current_path_parts); break; }
    }
    return $current;
  }

  function matching_nonexact_route (Array $current_path_parts, Array $routes) {
    $used_routes = array();
    for ($i=0; $i < count($routes); $i++) { 
      if($routes[$i]->size < \count($current_path_parts) and $routes[$i]->usemin === true and $routes[$i]->method === $_SERVER["REQUEST_METHOD"]) { \array_push($used_routes, $routes[$i]); } 
    }
    $current = null;
    for ($j=0; $j < \count($used_routes); $j++) { 
      $ok = true;
      for ($x=0; $x < \count($used_routes[$j]->path_parts); $x++) { 
        if($x === count($used_routes[$j]->path_parts) - 1) continue;
        if(\str_starts_with($used_routes[$j]->path_parts[$x], ":")) continue;
        if($x > \count($used_routes[$j]->path_parts) - 2) continue;
        if($used_routes[$j]->path_parts[$x] !== $current_path_parts[$x]) {$ok = false; }
      }
      if($ok === true) {  $current = $used_routes[$j]; $current->setCurrentPathParts($current_path_parts); break; }
    }
    return $current;
  }

  function log ($somestring) { echo "<div style='padding: 5px; border: 1px solid rgba(0,0,0,0.23)'>" . $somestring . "</div>"; }
  function logA ($something) { 
    echo "<div style='padding: 5px; border: 1px solid rgba(0,0,0,0.23)'>";
    print_r($something);
    echo "</div>"; 
  }
  function logArray (Array $ARR) { 
    echo "<ul>";
    foreach($ARR as $x => $val) {
      echo "<li><strong>" . $x . "</strong>" . $val .  "</li>";
    }
    echo "</ul>";
  }

  function cors ($options=array())
  {
    $expressCors = function (Request &$request, Response &$response, &$next) use ($options) {
      $allow_headers = "";
      $headers = getallheaders();
      foreach ($headers as $key => $value) {
        $allow_headers = $allow_headers . $key . ",";
      }
      $cors_options = array();
      if(isset($options["origin"])) {
        $cors_options["Access-Control-Allow-Origin"] = $options["origin"];
      } else { $cors_options["Access-Control-Allow-Origin"] = "*"; }

      if(isset($options["credentials"])) {
        $cors_options["Access-Control-Allow-Credentials"] = $options["credentials"];
      } else { $cors_options["Access-Control-Allow-Credentials"] = true; }

      if(isset($options["methods"])) {
        $cors_options["Access-Control-Allow-Methods"] = $options["methods"];
      } else { $cors_options["Access-Control-Allow-Methods"] = "GET,HEAD,PUT,PATCH,POST,DELETE,OPTIONS"; }

      if(isset($options["headers"])) {
        $cors_options["Access-Control-Allow-Headers"] = $allow_headers . $options["headers"];
      } else { $cors_options["Access-Control-Allow-Headers"] = $allow_headers; }

      if(isset($options["debug"])) {
        echo "<div style='padding: 1rem; border: 1px solid rgba(0,0,0,0.14)'><h1>Middlewares::Cors Debug</h1>";
        foreach ($cors_options as $key => $value) {
          echo "<strong>" . $key . ":\t\t</strong>" . $value . "<hr />";
        }
        \print_r($cors_options);
        echo "</div>";

      }
      foreach ($cors_options as $key => $value) {
        header($key . ":" . $value);
      }
      
      $next = true;
    }; 
    return $expressCors;
  }


  // ~ ExpressPHP Helper Functions;
  // **************************************************

?>