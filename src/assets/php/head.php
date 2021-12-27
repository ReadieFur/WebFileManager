<?php
    require_once __DIR__ . '/../../_assets/configuration/config.php';

    global $title;
    global $description;
    global $ogType;

    $dirName = ltrim(ucwords(str_replace("_", " ", basename($_SERVER['REQUEST_URI']))), '/');

    $_title = ($title !== null ? $title : $dirName) . ' | ' . Config::Config()['site']['name'];
    $_description = $description !== null ? $description : $dirName;
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark light">
<meta property="og:type" content="<?php echo $ogType !== null ? $ogType : 'website'; ?>">
<meta property="og:title" content="<?php echo $_title; ?>"/>
<meta property="og:description" content="<?php echo $_description; ?>"/>
<meta property="og:url" content="<?php echo $_SERVER['REQUEST_URI']; ?>"/>
<meta property="og:image" content="<?php echo Config::Config()['site']['path']; ?>/assets/images/icon.png"/>
<meta name="theme-color" content="<?php echo Config::Config()['site']['theme']['accent']; ?>">
<title><?php echo $_title; ?></title>
<link rel="icon" href="<?php echo Config::Config()['site']['path']; ?>/assets/images/icon.png" type="image/png">
<!-- #region Font imports -->
<!-- If you would like to use your own fonts or import this font from your own server then please download it, replace the links below, update main.scss and rebuild it. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
<!-- #endregion -->
<!-- <link rel="stylesheet" type="text/css" href="<?php echo Config::Config()['site']['path']; ?>/assets/css/head.css"/> -->
<link rel="stylesheet" type="text/css" href="<?php echo Config::Config()['site']['path']; ?>/assets/css/main.css"/>
<script>
    var WEB_ROOT = "<?php echo Config::Config()['site']['path']; ?>";
    var ACCENT = "<?php echo Config::Config()['site']['theme']['accent']; ?>";
</script>
<style id="themeColours"></style>
<div id="tooltipContainer"><small id="tooltipText"></small></div>