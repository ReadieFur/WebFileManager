<?php
    require_once __DIR__ . '/assets/php/main.php';
    $title = 'Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo Main::ExecuteAndRead(__DIR__ . '/assets/php/head.php'); ?>
    <link rel="stylesheet" type="text/css" href="./index.css"/>
    <script src="./index.js" type="module" defer></script>
</head>
<header id="header">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/assets/php/header.php'); ?>
</header>
<body>
    <!-- Modified from: https://github.com/kOFReadie/api-readie/blob/main/account/index.php -->
    <div id="accountContainer" class="center">
        <h3 class="center x">Account</h3>
        <p id="loading" class="center x">Loading...</p>
        <form id="logInTab">
            <table>
                <tbody>
                    <tr>
                        <td>
                            <p>Username:</p>
                        </td>
                        <td>
                            <p>Password:</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input class="username" type="text" name="username" placeholder="Username" required>
                        </td>
                        <td>
                            <input class="password" type="password" name="password" placeholder="Password" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button class="logInButton center x">Login</button>
        </form>
        <form id="accountTab">
            <table>
                <tbody>
                    <tr>
                        <td>
                            <p>Username:</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input class="username" type="text" name="username" placeholder="Username" readonly>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table>
                <tbody>
                    <tr>
                        <td>
                            <p>Current Password:</p>
                        </td>
                        <td>
                            <p>New Password:</p>
                        </td>
                        <td>
                            <p>Confirm Password:</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input class="currentPassword" type="password" name="currentPassword" placeholder="Current Password" required>
                        </td>
                        <td>
                            <input class="newPassword" type="password" name="newPassword" placeholder="New Password" required>
                        </td>
                        <td>
                            <input class="confirmPassword" type="password" name="confirmPassword" placeholder="Confirm Password" required>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="joinButtons center x">
                <button class="updateAccountButton">Update Account</button>
                <button class="logOutButton" type="button">Log Out</button>
            </div>
        </form>
    </div>
</body>
<footer id="footer">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/assets/php/footer.php'); ?>
</footer>