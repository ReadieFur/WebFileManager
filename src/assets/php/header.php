<?php
    require_once __DIR__ . '/../../_assets/configuration/config.php';
?>
<link rel="stylesheet" type="text/css" href="<?php echo Config::Config()['site']['path']; ?>/assets/css/header.css"/>
<section>
    <span class="bottomStripThin"></span>
    <div class="titleContainer">
        <a href="<?php echo Config::Config()['site']['path']; ?>/">
            <img class="small titleIcon" src="<?php echo Config::Config()['site']['path']; ?>/assets/images/icon.png">
            <h3 class="title"><?php echo Config::Config()['site']['name']; ?></h3>
        </a>
    </div>
    <div class="navigationContainer">
        <a href="<?php echo Config::Config()['site']['path']; ?>/view/directory/">Files</a>
        <a href="<?php echo Config::Config()['site']['path']; ?>/admin/">Admin</a>
        <a href="<?php echo Config::Config()['site']['path']; ?>/">Account</a>
        <div class="naviDropdown">
            <a>Options +</a>
            <div class="dropdownContent">
                <div></div>
                <div class="bottomStrip">
                    <a id="darkMode">Dark&nbsp;Mode</a>
                </div>
            </div>
        </div>
    </div>
</section>
<div id="alertBoxContainer">
    <div class="background"></div>
    <div id="alertBox">
        <input id="alertBoxTextBox" type="text">
        <p id="alerBoxText"></p>
        <p class="dismissText"><small>Click to dismiss this messaege.</small></p>
    </div>
</div>