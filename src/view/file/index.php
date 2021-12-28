<?php
    //Modified from: https://github.com/kOFReadie/Cloud/blob/main/src/files/view/index.php
    require_once __DIR__ . '/../../assets/php/main.php';
    require_once __DIR__ . '/../../_assets/configuration/config.php';
    $overrideDefaults = true;
    require_once __DIR__ . '/../../api/v1/request.php';

    $phpData = new stdClass();

    //I'm not a fan of this internal request method but it works for now and I don't want to have to copy the code from the api. I am hoping though that because this is an internal request it should still be fast-ish assuming the dns is resolved internally.
    $path = array_slice(Request::URLStrippedRoot(), 2); //2 for the .../view/file/ prefix.
    $pathImploded = implode('/', $path);
    $queryString = '?' . http_build_query(array(
        'uid' => Request::Cookie()['wfm_uid']??'',
        'token' => Request::Cookie()['wfm_token']??''
    ));
    $filePath = Config::Config()['site']['path'] . '/api/v1/file/' . $pathImploded . $queryString;

    //https://www.php.net/curl
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, Request::Server()['SERVER_NAME'] . $filePath . '&details');
    curl_setopt($ch, CURLOPT_POST, false); // Use GET
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //Follow redirects
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Return the response as a string
    $fileDataResponse = curl_exec($ch);
    curl_close($ch);

    if ($fileDataResponse === false)
    {
        $phpData->error = ErrorMessages::UNKNOWN_ERROR;
    }
    else
    {
        $fileDataResponse = json_decode($fileDataResponse, true);

        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200)
        {
            $phpData->error = $fileDataResponse['error']??ErrorMessages::UNKNOWN_ERROR;
        }
        else
        {
            $phpData->data = $fileDataResponse;
            $phpData->data['url'] = $filePath;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" type="text/css" href="<?php echo Config::Config()['site']['path']; ?>/view/file/file.css"/>
    <script src="<?php echo Config::Config()['site']['path']; ?>/view/file/file.js" type="module" defer></script>
    <?php
        if (!isset($phpData->error))
        {
            $mimeTypeExploded = explode('/', $phpData->data['mimeType']);
            
            //Values set here to be used in head.php
            $title = $phpData->data['name'] . ($phpData->data['extension'] ? '.' . $phpData->data['extension'] : '');
            $ogType = $mimeTypeExploded[0] . '.' . $mimeTypeExploded[1];

            switch ($mimeTypeExploded[0])
            {
                case 'video':
                    $thumbnailPath = Config::Config()['site']['path'] . '/api/v1/file/' . $pathImploded . '.thumbnail.png' . $queryString;

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, Request::Server()['SERVER_NAME'] . $thumbnailPath . '&details');
                    curl_setopt($ch, CURLOPT_POST, false); // Use GET
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //Follow redirects
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Return the response as a string
                    $thumbnailDataResponse = curl_exec($ch);
                    curl_close($ch);

                    $thumbnailData = new stdClass();
                    if ($thumbnailDataResponse === false)
                    {
                        $thumbnailData->error = ErrorMessages::UNKNOWN_ERROR;
                    }
                    else
                    {
                        $thumbnailDataResponse = json_decode($thumbnailDataResponse, true);

                        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200 || explode('/', $thumbnailDataResponse['mimeType'])[0] !== 'image')
                        {
                            $thumbnailData->error = $thumbnailDataResponse['error']??ErrorMessages::UNKNOWN_ERROR;
                        }
                        else
                        {
                            $thumbnailData->data = $thumbnailDataResponse;
                            $phpData->data['thumbnail'] = $thumbnailData->data;
                        }
                    }

                    if (!isset($thumbnailData->error))
                    {
                        ?>
                            <meta property="og:image" content="<?php echo $thumbnailPath; ?>">
                            <meta property="og:image:secure_url" content="<?php echo $thumbnailPath; ?>">
                            <meta property="og:image:type" content="<?php echo $thumbnailData->data['mimeType']; ?>">
                            <meta property="og:image:width" content="<?php echo $thumbnailData->data['width']; ?>">
                            <meta property="og:image:height" content="<?php echo $thumbnailData->data['height']; ?>">
                            <meta name="twitter:image" content="<?php echo $thumbnailPath; ?>">
                        <?php
                    }
                    ?>
                        <meta property="og:updated_time" content="<?php echo gmdate("Y-m-d\TH:i:s\Z", $phpData->data['lastModified']); ?>">
                        <meta property="og:video" content="<?php echo $filePath; ?>">
                        <meta property="og:video:url" content="<?php echo $filePath; ?>">
                        <meta property="og:video:secure_url" content="<?php echo $filePath; ?>">
                        <meta property="og:video:type" content="<?php echo $phpData->data['mimeType']; ?>">
                        <meta property="og:video:width" content="<?php echo $phpData->data['width']; ?>">
                        <meta property="og:video:height" content="<?php echo $phpData->data['height']; ?>">
                        <meta name="twitter:card" content="player">
                        <meta name="twitter:player:width" content="<?php echo $phpData->data['width']; ?>">
                        <meta name="twitter:player:height" content="<?php echo $phpData->data['height']; ?>">
                        <meta name="twitter:player" content="<?php echo $filePath; ?>">
                    <?php
                    break;
                case 'image':
                    ?>
                        <meta property="og:image" content="<?php echo $filePath; ?>">
                        <meta property="og:image:secure_url" content="<?php echo $filePath; ?>">
                        <meta property="og:image:type" content="<?php echo $phpData->data['mimeType']; ?>">
                        <meta property="og:image:width" content="<?php echo $phpData->data['width']; ?>">
                        <meta property="og:image:height" content="<?php echo $phpData->data['height']; ?>">
                        <meta property="og:updated_time" content="<?php echo gmdate("Y-m-d\TH:i:s\Z", $phpData->data['lastModified']); ?>">
                        <meta name="twitter:image" content="<?php echo $filePath; ?>">
                        <meta property="twitter:image:width" content="<?php echo $phpData->data['width']; ?>">
                        <meta property="twitter:image:height" content="<?php echo $phpData->data['height']; ?>">
                        <!--This preview type seems to not work in telegram but does fix the preview in discord, I will need to find a way to get it to work for both. -->
                        <meta name="twitter:card" content="summary_large_image">
                    <?php
                    break;
                /*case 'audio':
                    ?>
                    
                    <?php
                    break;*/
                /*case 'text':
                    ?>
                    <?php
                    break;*/
                default:
                    break;
            }
        }
    ?>
    <script>var PHP_DATA = '<?php echo json_encode($phpData); ?>';</script>
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../../assets/php/head.php'); ?>
</head>
<header id="header">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../../assets/php/header.php'); ?>
</header>
<body>
    <?php
        if (!isset($phpData->error))
        {
            $mimeTypeExploded = explode('/', $phpData->data['mimeType']);
            ?>
                <section id="pageTitleContainer">
                    <div class="leftRight">
                        <h4 id="fileName"><?php echo $phpData->data['name'] . ($phpData->data['extension'] != null ? '.' . $phpData->data['extension'] : ''); ?></h4>
                        <a class="asButton" href="<?php echo $filePath . '&download'; ?>" target="_blank">Download</a>
                    </div>
                    <hr>
                    <br>
                </section>
                <span id="contentContainer"></span>
                <h4 id="notice" class="center">Please make sure JavaScript is enabled.</h3>
            <?php
        }
        else
        {
            ?>
                <h4 class="center">Unable to get file.</h4>
            <?php
        }
    ?>
</body>
<footer id="footer">
    <?php echo Main::ExecuteAndRead(__DIR__ . '/../../assets/php/footer.php'); ?>
</footer>