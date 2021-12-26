<?php
class Main
{
    public static function ExecuteAndRead(string $phpFile): string
    {
        ob_start();
        require_once $phpFile;
        $output = ob_get_contents();
        ob_end_clean();
        if ($output === false) { throw new Exception("Failed to read output from $phpFile"); }
        return $output;
    }
}