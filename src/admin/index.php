<?php
    require_once __DIR__ . '/../assets/php/main.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../assets/php/head.php'); ?>
    <link rel="stylesheet" type="text/css" href="./admin.css"/>
    <script src="./admin.js" type="module" defer></script>
</head>
<header id="header">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../assets/php/header.php'); ?>
</header>
<body>
    <section class="tabButtons">
        <h4>Tabs:</h4>
        <div class="joinButtons">
            <button id="accountsTabButton">Acounts</button>
            <button id="pathsTabButton">Paths</button>
        </div>
    </section>
    <br>
    <section class="tabs">
        <form id="accountsTab" autocomplete="off">
            <table>
                <tbody>
                    <tr>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Confirm Password</th>
                        <th>Admin</th>
                        <th></th>
                </tbody>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" id="username" required/>
                        </td>
                        <td>
                            <input type="password" id="password"/>
                        </td>
                        <td>
                            <input type="password" id="passwordConfirm"/>
                        </td>
                        <td>
                            <label class="checkboxContainer">
                                <input id="admin" type="checkbox">
                                <span class="checkmark"></span>
                            </label>
                        </td>
                        <td>
                            <button id="accountSubmit">Create/Update</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
        <form id="pathsTab" autocomplete="off">
            <table>
                <tbody>
                    <tr>
                        <th>Web Path</th>
                        <th>Local Path</th>
                        <th></th>
                </tbody>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" id="webPath" required/>
                        </td>
                        <td>
                            <input type="text" id="localPath" required/>
                        </td>
                        <td>
                            <button id="pathSubmit">Add/Update</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </section>
    <br>
    <section>
        <table class="listingContainer">
            <tbody id="thBody">
                <!-- <tr>
                    <th>Username</th>
                    <th>Admin</th>
                    <th>Options</th>
                </tr> -->
                <!-- <tr>
                    <th>Web Path</th>
                    <th>Local Path</th>
                    <th>Options</th>
                </tr> -->
            </tbody>
            <tbody id="tdBody">
                <!-- <tr class="listItem selectable">
                    <td>
                        <p>Username</p>
                    </td>
                    <td>
                        <label class="checkboxContainer">
                            <input type="checkbox" checked disabled>
                            <span class="checkmark"></span>
                        </label>
                    </td>
                    <td>
                        <button class="red">Delete</button>
                    </td>
                </tr> -->
                <!-- <tr class="listItem selectable">
                    <td>
                        <p>Web Path</p>
                    </td>
                    <td>
                        <p>Local Path</p>
                    </td>
                    <td>
                        <button class="red">Delete</button>
                    </td>
                </tr> -->
            </tbody>
        </table>
    </section>
</body>
<footer id="footer">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../assets/php/footer.php'); ?>
</footer>