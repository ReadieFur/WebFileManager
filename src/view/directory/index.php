<?php
    //This is to be the login page.
    require_once __DIR__ . '/../../assets/php/main.php';
    require_once __DIR__ . '/../../_assets/configuration/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../../assets/php/head.php'); ?>
    <link rel="stylesheet" type="text/css" href="<?php echo Config::Config()['site']['path']; ?>/view/directory/directory.css"/>
    <script src="<?php echo Config::Config()['site']['path']; ?>/view/directory/directory.js" type="module" defer></script>
</head>
<header id="header">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../../assets/php/header.php'); ?>
</header>
<body>
    <section>
        <!-- Modified from: https://github.com/kOFReadie/Cloud/blob/main/src/files/index.php -->
        <table class="directoryListingContainer">
            <tbody>
                <tr>
                    <th class="nameColumn">Name</th>
                    <th class="typeColumn">Type</th>
                    <th class="dateColumn">Date Modified</th>
                    <th class="sizeColumn">Size</th>
                    <th class="optionsColumn">Options</th>
                </tr>
            </tbody>
            <tbody id="directoryListing">
                <!-- <tr class="listItem">
                    <td class="nameColumn">
                        <table>
                            <tbody>
                                <tr>
                                    <td>
                                        <img src="./../../assets/images/folder.png" alt="Folder" class="icon">
                                    </td>
                                    <td>
                                        <p>Name</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                    <td class="typeColumn">
                        <p>Type</p>
                    </td>
                    <td class="dateColumn">
                        <p>Date Modified</p>
                    </td>
                    <td class="sizeColumn">
                        <p>Size</p>
                    </td>
                    <td class="optionsColumn">
                        <button>Sharing</button>
                    </td>
                </tr> -->
            </tbody>
        </table>
    </section>
</body>
<footer id="footer">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../../assets/php/footer.php'); ?>
</footer>