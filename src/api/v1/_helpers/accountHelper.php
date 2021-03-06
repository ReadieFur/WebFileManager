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
    private const SESSION_TIMEOUT = 3 * 28 * 24 * 60 * 60; //3 months.
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
    
    public function CreateAccount($id, $token, $username, $password, $admin): int | string
    {
        if (
            !AccountHelper::CheckID($id) ||
            !AccountHelper::CheckToken($token) ||
            !AccountHelper::CheckUsername($username) ||
            !AccountHelper::CheckPassword($password) ||
            !AccountHelper::CheckAdmin($admin)
        )
        { return 400; }

        $username = strtolower($username);

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if ($requestAccount === false || $requestAccount->admin != 1) { return 403; }

        $existingUsers = $this->usersTable->Select(array('username'=>$username));
        if ($existingUsers === false || count($existingUsers) > 0) { return 409; }
        
        $uid = '';
        do
        {
            $uid = str_replace('.', '', uniqid('', true));
            $existingIDs = $this->usersTable->Select(array('id'=>$uid));
            if ($existingIDs === false) { return false; }
        }
        while (count($existingIDs) > 0);

        $encryptedPassword = password_hash($password, PASSWORD_BCRYPT);
        if ($encryptedPassword === false || $encryptedPassword === null) { return 500; }

        $insertResult = $this->usersTable->Insert(array(
            'id' => $uid,
            'username' => $username,
            'password' => $encryptedPassword,
            'admin' => $admin ? 1 : 0
        ));
        if ($insertResult === false) { return 500; }
        return $uid;
    }

    public function UpdateAccount($id, $token, $uid, $old_password, $new_password, $admin): int
    {
        if (
            !AccountHelper::CheckID($id) ||
            !AccountHelper::CheckToken($token) ||
            !AccountHelper::CheckID($uid) ||
            ($admin !== '' && !AccountHelper::CheckAdmin($admin))
        )
        { return 400; }

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if (
            $requestAccount === false ||
            ($admin == 1 && $requestAccount->admin != 1)
        ) { return 403; }

        $existingUsers = $this->usersTable->Select(array('id'=>$uid));
        if (
            $existingUsers === false ||
            empty($existingUsers) ||
            (
                $admin !== '' &&
                $existingUsers[0]->username == 'admin' //Make sure the admin's account permissions cannot be changed.
            )
        ) { return 404; }
        else if ($admin === '') { $admin = $existingUsers[0]->admin; }

        $encryptedPassword = null;
        if ($new_password != '' && !AccountHelper::CheckPassword($new_password)) { return false; }
        else if ($new_password != '')
        {
            if (
                $requestAccount->admin != 1 &&
                !password_verify($old_password??'', $existingUsers[0]->password)
            ) { return 403; }

            $encryptedPassword = password_hash($new_password, PASSWORD_BCRYPT);
            if ($encryptedPassword === false || $encryptedPassword === null) { return 500; }
        }

        $updateResult = $this->usersTable->Update(array(
            'password' => $encryptedPassword??$existingUsers[0]->password,
            'admin' => $admin == 1 ? '1' : '0',
            'sessionToken' => $encryptedPassword != null ? null : $existingUsers[0]->sessionToken
        ), array('id'=>$uid));
        if ($updateResult === false) { return 500; }
        return 200;
    }

    public function DeleteAccount($id, $token, $uid): int
    {
        if (
            !AccountHelper::CheckID($id) ||
            !AccountHelper::CheckToken($token) ||
            !AccountHelper::CheckID($uid)
        )
        { return 400; }

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if ($requestAccount === false || ($id !== $uid && $requestAccount->admin != 1)) { return 403; }

        $existingUsers = $this->usersTable->Select(array('id'=>$uid));
        if (
            $existingUsers === false ||
            empty($existingUsers) ||
            $existingUsers[0]->username == 'admin' //Make sure the admin account can't be deleted.
        ) { return 404; }

        $deleteResult = $this->usersTable->Delete(array('id'=>$uid), true);
        if ($deleteResult === false) { return 500; }
        return 200;
    }

    public function GetAccountDetails($id, $token, $uid): false | webfilemanager_users
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

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if ($requestAccount === false || $requestAccount->admin != 1) { return false; }

        $users = $this->usersTable->Select(array());
        if ($users === false) { return false; }
        return $users;
    }

    public function LogIn($username, $password): false | object
    {
        if (!AccountHelper::CheckUsername($username) || !AccountHelper::CheckPassword($password)) { return false; }

        $username = strtolower($username);

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
            if (time() - $tokenData['timestamp'] < AccountHelper::SESSION_TIMEOUT)
            {
                $response = new stdClass();
                $response->uid = $user->id;
                $response->token = $user->sessionToken;
                return $response;
            }
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

        $response = new stdClass();
        $response->uid = $user->id;
        $response->token = $sessionToken;
        $response->username = $user->username;
        return $response;
    }

    public function RevokeSession($id, $token, $uid): bool
    {
        if (!AccountHelper::CheckID($id) || !AccountHelper::CheckToken($token)) { return false; }

        $requestAccount = $this->GetAccountDetails($id, $token, $id);
        if ($requestAccount === false || ($id !== $uid && $requestAccount->admin != 1)) { return false; }

        $updateResult = $this->usersTable->Update(
            array(
                'sessionToken'=>null
            ),
            array(
                'id'=>$uid
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