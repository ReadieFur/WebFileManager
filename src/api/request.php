<?php
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
    private static $initialized = false;

    private static RequestMethod $REQUEST_METHOD;
    private static array $GET;
    private static array $POST;
    private static array $FILES;
    private static array $COOKIE;
    private static array $SERVER;
    private static array $ENV;
    private static array $HEADERS;
    private static array $REQUEST;
    private static array $SESSION;

    public static function Init()
    {
        if (self::$initialized) { return; }

        http_response_code(500); //500 Internal Server Error, set this as the default value as the response should return something.
        header('Content-Type: application/json');

        self::$REQUEST_METHOD = RequestMethod::GetMethod(self::$SERVER['REQUEST_METHOD']);
        self::$GET = $_GET;
        self::$POST = $_POST;
        self::$FILES = $_FILES;
        self::$COOKIE = $_COOKIE;
        self::$SERVER = $_SERVER;
        self::$ENV = $_ENV;
        self::$HEADERS = getallheaders();
        self::$REQUEST = $_REQUEST;
        self::$SESSION = $_SESSION;
    }

    public static function SendResponse(int $code, stdClass $body = new stdClass()): never
    {
        self::Init();
        $response = json_encode($body);
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

    public static function RequestMethod(): RequestMethod
    {
        self::Init();
        return self::$REQUEST_METHOD;
    }

    public static function Get(): array
    {
        self::Init();
        return self::$GET;
    }

    public static function Post(): array
    {
        self::Init();
        return self::$POST;
    }

    public static function Files(): array
    {
        self::Init();
        return self::$FILES;
    }

    public static function Cookie(): array
    {
        self::Init();
        return self::$COOKIE;
    }

    public static function Server(): array
    {
        self::Init();
        return self::$SERVER;
    }

    public static function Env(): array
    {
        self::Init();
        return self::$ENV;
    }

    public static function Headers(): array
    {
        self::Init();
        return self::$HEADERS;
    }

    public static function Request(): array
    {
        self::Init();
        return self::$REQUEST;
    }

    public static function Session(): array
    {
        self::Init();
        return self::$SESSION;
    }
}
Request::Init();