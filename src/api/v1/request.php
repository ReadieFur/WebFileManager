<?php
require_once __DIR__ . '/../../_assets/logger.php';

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
    public const DEFAULT_RESPONSE_CODE = 200;

    private static bool $initialized = false;

    private static ?array $SERVER = null;
    private static ?string $REQUEST_METHOD = null;
    private static ?array $GET = null;
    private static ?array $POST = null;
    private static ?array $FILES = null;
    private static ?array $COOKIE = null;
    private static ?array $ENV = null;
    private static ?array $HEADERS = null;
    private static ?array $REQUEST = null;
    private static ?array $SESSION = null;

    //This should be automatically called by PHP when the class is loaded (run at the bottom of this file).
    public static function Init(): void
    {
        if (self::$initialized === true) { return; }
        self::$initialized = true;

        self::$SERVER = $_SERVER;
        self::$REQUEST_METHOD = RequestMethod::GetMethod(self::$SERVER['REQUEST_METHOD']);
        self::$GET = $_GET;
        self::$POST = $_POST;
        self::$FILES = $_FILES;
        self::$COOKIE = $_COOKIE;
        self::$ENV = $_ENV;
        self::$HEADERS = getallheaders();
        self::$REQUEST = $_REQUEST;
        self::$SESSION = $_SESSION;
        header('Content-Type: application/json'); //All data should/will be returned in JSON format.
        self::SendResponse(self::DEFAULT_RESPONSE_CODE); //Set as the default response.
    }

    public static function RunScriptForRequestMethod(string $baseDirectory): never
    {
        $filePath = $baseDirectory . '/' . strtolower(self::$REQUEST_METHOD) . '.php';
        if (file_exists($filePath))
        {
            require_once $filePath;
            self::SendResponse(self::DEFAULT_RESPONSE_CODE); //If this point is reached (which it shouldn't) the program will return error.
        }
        else
        {
            Logger::Log('Requested method not found', LogLevel::DEBUG);
            self::SendResponse(405);
        }
    }

    public static function DenyIfNotRequestMethod(string $requestMethod)
    {
        if (self::$REQUEST_METHOD !== $requestMethod)
        {
            Logger::Log('Requested method not allowed', LogLevel::DEBUG);
            self::SendResponse(405);
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

    public static function RequestMethod(): string { return self::$REQUEST_METHOD; }
    public static function Get(): array { return self::$GET; }
    public static function Post(): array { return self::$POST; }
    public static function Files(): array { return self::$FILES; }
    public static function Cookie(): array { return self::$COOKIE; }
    public static function Server(): array { return self::$SERVER; }
    public static function Env(): array { return self::$ENV; }
    public static function Headers(): array { return self::$HEADERS; }
    public static function Request(): array { return self::$REQUEST; }
    public static function Session(): array { return self::$SESSION; }
}
Request::Init();