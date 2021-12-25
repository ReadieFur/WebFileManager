<?php
require_once __DIR__ . '/../../../_assets/configuration/config.php';
require_once __DIR__ . '/../../../_assets/database/tables/webfilemanager_users/webfilemanager_users.php';

Request::DenyIfDirectRequest(__FILE__);

//Tweaked from: https://github.com/kOFReadie/api-readie/blob/f30dd5c8279befb106ad35be4b1f7c740050b6f0/account/accountHelper.php
//Because I am using a heavily simplified version of my main API (and database helper), I will not be able to return as specific data as I normally would. Perhaps in V2 I will upgrade to the more advanced version of the API. The reason I havent just done that now is because I can't be bothered.
class AccountHelper
{
    //Based on the 13 month calendar, value is in seconds.
    //..., Months, days, hours, minutes, seconds.
    private const SESSION_TIMEOUT = 7257600; //Three months: 3 * 28 * 24 * 60 * 60.
    //https://stackoverflow.com/questions/12018245/regular-expression-to-validate-username/12019115.
    private const USERNAME_REGEX = '/(?=.{4,20}$)(?![_.])(?!.*[_.]{2})[a-zA-Z0-9._]+(?<![_.])/m';
    //https://stackoverflow.com/questions/19605150/regex-for-password-must-contain-at-least-eight-characters-at-least-one-number-a.
    private const PASSWORD_REGEX = '/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d\W]{8,32}$/m';

    private webfilemanager_users $usersTable;

    function __construct()
    {
        $this->usersTable = new webfilemanager_users(
            true,
            Config::Config()['database']['host'],
            Config::Config()['database']['database'],
            Config::Config()['database']['username'],
            Config::Config()['database']['password']
        );
    }
    
    public function CreateAccount($id, $token, $username, $password, $admin): false | string
    {
        if (
            !AccountHelper::CheckID($id) ||
            !AccountHelper::CheckToken($token) ||
            !AccountHelper::CheckUsername($username) ||
            !AccountHelper::CheckPassword($password) ||
            !AccountHelper::CheckAdmin($admin)
        )
        { return false; }

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if ($requestAccount === false || $requestAccount->admin != 1) { return false; }

        $existingUsers = $this->usersTable->Select(array('username'=>$username));
        if ($existingUsers === false || count($existingUsers) > 0) { return false; }
        
        $uid = '';
        do
        {
            $uid = str_replace('.', '', uniqid('', true));
            $existingIDs = $this->usersTable->Select(array('id'=>$uid));
            if ($existingIDs === false) { return false; }
        }
        while (count($existingIDs) > 0);

        $encryptedPassword = password_hash($password, PASSWORD_BCRYPT);
        if ($encryptedPassword === false || $encryptedPassword === null) { return false; }

        $insertResult = $this->usersTable->Insert(array(
            'id' => $uid,
            'username' => $username,
            'password' => $encryptedPassword,
            'admin' => $admin ? 1 : 0
        ));
        if ($insertResult === false) { return false; }
        return $uid;
    }

    public function UpdateAccount($id, $token, $uid, $password, $admin): bool
    {
        if (
            !AccountHelper::CheckID($id) ||
            !AccountHelper::CheckToken($token) ||
            !AccountHelper::CheckID($uid) ||
            !AccountHelper::CheckAdmin($admin)
        )
        { return false; }

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if (
            $requestAccount === false ||
            ($admin == 1 && $requestAccount->admin != 1)
        ) { return false; }

        $existingUsers = $this->usersTable->Select(array('id'=>$uid));
        if ($existingUsers === false || empty($existingUsers)) { return false; }

        $encryptedPassword = null;
        if ($password != null && !AccountHelper::CheckPassword($password)) { return false; }
        else if ($password != null)
        {
            $encryptedPassword = password_hash($password, PASSWORD_BCRYPT);
            if ($encryptedPassword === false || $encryptedPassword === null) { return false; }
        }

        $updateResult = $this->usersTable->Update(array(
            'password' => $encryptedPassword??$existingUsers[0]->password,
            'admin' => $admin == 1 ? '1' : '0',
            'sessionToken' => $encryptedPassword != null ? null : $existingUsers[0]->sessionToken
        ), array('id'=>$uid));
        if ($updateResult === false) { return false; }
        return true;
    }

    public function DeleteAccount($id, $token, $uid): bool
    {
        if (
            !AccountHelper::CheckID($id) ||
            !AccountHelper::CheckToken($token) ||
            !AccountHelper::CheckID($uid)
        )
        { return false; }

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if ($requestAccount === false || ($id !== $uid && $requestAccount->admin != 1)) { return false; }

        $deleteResult = $this->usersTable->Delete(array('id'=>$uid), true);
        if ($deleteResult === false) { return false; }
        return true;
    }

    public function GetAccountDetails($id, $token, $uid): false | object
    {
        if (!AccountHelper::CheckID($id) || !AccountHelper::CheckToken($token) || !AccountHelper::CheckID($uid)) { return false; }

        if ($this->VerifyToken($id, $token) === false)
        { return false; }

        $user = $this->usersTable->Select(array('id'=>$uid));
        if ($user === false) { return false; }
        return $user[0];
    }

    public function GetAllAccounts($id, $token): false | array
    {
        if (!AccountHelper::CheckID($id) || !AccountHelper::CheckToken($token)) { return false; }

        if ($this->VerifyToken($id, $token) === false)
        { return false; }

        $users = $this->usersTable->Select(array());
        if ($users === false) { return false; }
        return $users;
    }

    public function LogIn($username, $password): false | string
    {
        if (!AccountHelper::CheckUsername($username) || !AccountHelper::CheckPassword($password)) { return false; }

        $existingUsers = $this->usersTable->Select(array('username'=>$username));
        if ($existingUsers === false || empty($existingUsers)) { return false; }
        $user = $existingUsers[0];

        if (password_verify($password, $user->password) === false) { return false; }

        if ($user->sessionToken !== null)
        {
            $decryptResult = AccountHelper::Crypt(false, $user->id, $user->sessionToken);
            if ($decryptResult === false) { return false; }
            $tokenData = json_decode($decryptResult, true);
            if ($tokenData === null) { return false; }
            if (time() - $tokenData['timestamp'] < AccountHelper::SESSION_TIMEOUT) { return $user->sessionToken; }
        }

        $sessionToken = AccountHelper::Crypt(true, $user->id, json_encode(array(
            'timestamp'=>time() + AccountHelper::SESSION_TIMEOUT
        )));
        if ($sessionToken === false) { return false; }

        $updateResult = $this->usersTable->Update(
            array(
                'sessionToken'=>$sessionToken
            ),
            array(
                'id'=>$user->id
            )
        );
        if ($updateResult === false) { return false; }
        return $sessionToken;
    }

    public function LogOut($id, $token): bool
    {
        if (!AccountHelper::CheckID($id) || !AccountHelper::CheckToken($token)) { return false; }

        if ($this->VerifyToken($id, $token) === false) { return false; }

        $updateResult = $this->usersTable->Update(
            array(
                'sessionToken'=>null
            ),
            array(
                'id'=>$id
            )
        );
        if ($updateResult === false) { return false; }
        return true;
    }
    
    public function VerifyToken($id, $token): bool
    {
        if (!AccountHelper::CheckID($id) || !AccountHelper::CheckToken($token)) { return false; }

        $selectResult = $this->usersTable->Select(array('id'=>$id));
        if ($selectResult === false || empty($selectResult)) { return false; }
        $user = $selectResult[0];

        if ($token != $user->sessionToken) { return false; }

        $decryptResult = AccountHelper::Crypt(false, $id, $token);
        if ($decryptResult === false) { return false; }
        $tokenData = json_decode($decryptResult, true);
        if ($tokenData === null) { return false; }
        if (time() - $tokenData['timestamp'] > AccountHelper::SESSION_TIMEOUT) { return false; }
        return true;
    }

    private static function CheckID($uid): bool
    {
        if (gettype($uid) !== 'string') { return false; }
        return strlen($uid) === 22;
    }

    private static function CheckToken($uid): bool
    {
        return gettype($uid) === 'string';
    }

    private static function CheckUsername($username): bool
    {
        if (gettype($username) !== 'string') { return false; }
        preg_match(AccountHelper::USERNAME_REGEX, $username, $usernameMatch);
        return strlen($usernameMatch[0] ?? null) === strlen($username);
    }

    private static function CheckPassword($password): bool
    {
        if (gettype($password) !== 'string') { return false; }
        preg_match(AccountHelper::PASSWORD_REGEX, $password, $passwordMatch);
        return strlen($passwordMatch[0] ?? null) === strlen($password);
    }

    private static function CheckAdmin($admin): bool
    {
        return gettype($admin) === 'boolean' || ($admin == 0 || $admin == 1);
    }

    public static function Crypt(bool $encrypt, string $key, string $data): false | string
    {
        //https://www.geeksforgeeks.org/how-to-encrypt-and-decrypt-a-php-string/
        $method = 'aes-256-cbc-hmac-sha256'; //Cipher method.
        $options = 0;
        $iv = substr($key, 0, 16); //#CHANGE FOR NEW ENCRYPTION OUTPUT (MUST BE EXACTLY 16 BYTES).
        $result = null;
        if ($encrypt) { $result = openssl_encrypt($data, $method, $key, $options, $iv); } //Use openssl_encrypt() function to encrypt the data.
        else { $result = openssl_decrypt($data, $method, $key, $options, $iv); } //Use openssl_decrypt() function to decrypt the data.
        if ($result === false) { return false; }
        return $result;
    }
}