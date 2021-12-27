<?php
require_once __DIR__ . '/../../_assets/logger/logger.php';

global $overrideDefaults;

class ErrorMessages
{
    const INVALID_PATH = 'INVALID_PATH';
    const NO_RESPONSE = 'NO_RESPONSE';
    const METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    const DIRECT_REQUEST_NOT_ALLOWED = 'DIRECT_REQUEST_NOT_ALLOWED';
    const INVALID_PARAMETERS = 'INVALID_PARAMETERS';
    const INVALID_ACCOUNT_DATA = 'INVALID_ACCOUNT_DATA';
    const PATH_ALREADY_EXISTS = 'PATH_ALREADY_EXISTS';
    const DATABASE_ERROR = 'DATABASE_ERROR';
    const THUMBNAL_ERROR = 'THUMBNAL_ERROR';
    const INVALID_FILE_TYPE = 'INVALID_FILE_TYPE';
    const UNKNOWN_ERROR = 'UNKNOWN_ERROR';
}

class RequestMethod
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';
    public const PATCH = 'PATCH';
    public const HEAD = 'HEAD';
    public const UNKNOWN = 'UNKNOWN';

    public static function GetMethod(string $method): string
    {
        switch ($method)
        {
            case 'GET':
                return self::GET;
            case 'POST':
                return self::POST;
            case 'PUT':
                return self::PUT;
            case 'DELETE':
                return self::DELETE;
            case 'PATCH':
                return self::PATCH;
            case 'HEAD':
                return self::HEAD;
            default:
                return self::UNKNOWN;
        }
    }
}

class Request
{
    public const DEFAULT_RESPONSE_CODE = 500;

    private static bool $initialized = false;

    private static ?array $URL;
    private static ?array $URL_STRIPPED_ROOT;
    private static ?array $SERVER;
    private static ?string $REQUEST_METHOD;
    private static ?array $GET;
    private static ?array $POST;
    private static ?array $FILES;
    private static ?array $COOKIE;
    private static ?array $ENV;
    private static ?array $HEADERS;
    private static ?array $REQUEST;
    // private static ?array $SESSION;

    //This should be automatically called by PHP when the class is loaded (run at the bottom of this file).
    public static function Init(): void
    {
        global $overrideDefaults;

        if (self::$initialized === true) { return; }
        self::$initialized = true;

        $requestURI = $_SERVER['REQUEST_URI'];
        $queryStringStartIndex = strpos($_SERVER['REQUEST_URI'], '?');
        if ($queryStringStartIndex !== false) { $requestURI = substr($_SERVER['REQUEST_URI'], 0, $queryStringStartIndex); }

        self::$URL = array_filter(explode('/', $requestURI), fn($part) => !ctype_space($part) && $part !== '');
        self::$URL_STRIPPED_ROOT = array_filter(explode('/', str_replace(Config::Config()['site']['path'], '', $requestURI)), fn($part) => !ctype_space($part) && $part !== '');
        self::$SERVER = $_SERVER;
        self::$SERVER['REQUEST_URI'] = $requestURI;
        self::$REQUEST_METHOD = RequestMethod::GetMethod(self::$SERVER['REQUEST_METHOD']);
        self::$GET = $_GET;
        self::$POST = array();
        foreach ($_POST as $key => $value)
        {
            if (is_array($value)) { self::$POST[$key] = $value; }
            else { self::$POST[$key] = urldecode($value); }
        }
        self::$FILES = $_FILES;
        self::$COOKIE = $_COOKIE;
        self::$ENV = $_ENV;
        self::$HEADERS = getallheaders();
        self::$REQUEST = $_REQUEST;
        // self::$SESSION = $_SESSION;

        if ($overrideDefaults !== true)
        {
            header('Content-Type: application/json'); //All data should/will be returned in JSON format.
            http_response_code(self::DEFAULT_RESPONSE_CODE); //Set as the default response.
        }
    }

    public static function RunScriptForRequestMethod(string $baseDirectory): never
    {
        $filePath = $baseDirectory . '/' . strtolower(self::$REQUEST_METHOD) . '.php';
        if (file_exists($filePath))
        {
            require_once $filePath; //Should never return.
            Logger::Log(ErrorMessages::NO_RESPONSE, LogLevel::ERROR);
            self::SendError(self::DEFAULT_RESPONSE_CODE, ErrorMessages::NO_RESPONSE); //If this point is reached (which it shouldn't) the program will return error.
            //5** errors cannot return data if I recall correctly.
        }
        else
        {
            Logger::Log(ErrorMessages::METHOD_NOT_ALLOWED, LogLevel::DEBUG);
            self::SendError(405, ErrorMessages::METHOD_NOT_ALLOWED);
        }
    }

    public static function DenyIfNotRequestMethod(string $requestMethod)
    {
        if (self::$REQUEST_METHOD !== $requestMethod)
        {
            Logger::Log(ErrorMessages::METHOD_NOT_ALLOWED, LogLevel::DEBUG);
            self::SendError(405, ErrorMessages::METHOD_NOT_ALLOWED);
        }
    }

    public static function DenyIfDirectRequest(string $fileName): void
    {
        //This could be improved to validate the path too.
        if (basename($fileName) === basename(self::$SERVER['SCRIPT_FILENAME']))
        {
            Logger::Log(ErrorMessages::DIRECT_REQUEST_NOT_ALLOWED, LogLevel::DEBUG);
            self::SendError(405, ErrorMessages::DIRECT_REQUEST_NOT_ALLOWED);
        }
    }

    public static function SendError(int $code, string $message = null): never
    {
        if ($message !== null)
        {
            $body = new stdClass();
            $body->error = $message;
            self::SendResponse($code, $body);
        }
        else
        {
            self::SendResponse($code);
        }
    }

    public static function SendResponse(int $code, stdClass $body = null): never
    {
        $response = json_encode($body??new stdClass());
        if ($response !== false)
        {
            http_response_code($code);
            echo $response;
        }
        else
        {
            Logger::Log('Failed to encode response body.', LogLevel::ERROR);
        }
        exit;
    }

    public static function URL(): ?array { return self::$URL; }
    public static function URLStrippedRoot(): ?array { return self::$URL_STRIPPED_ROOT; }
    public static function RequestMethod(): ?string { return self::$REQUEST_METHOD; }
    public static function Get(): ?array { return self::$GET; }
    public static function Post(): ?array { return self::$POST; }
    public static function Files(): ?array { return self::$FILES; }
    public static function Cookie(): ?array { return self::$COOKIE; }
    public static function Server(): ?array { return self::$SERVER; }
    public static function Env(): ?array { return self::$ENV; }
    public static function Headers(): ?array { return self::$HEADERS; }
    public static function Request(): ?array { return self::$REQUEST; }
    // public static function Session(): ?array { return self::$SESSION; }
}
Request::Init();
Request::DenyIfDirectRequest(__FILE__);