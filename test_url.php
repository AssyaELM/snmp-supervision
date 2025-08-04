<?php
if (function_exists('curl_version')) {
    echo "cURL est activé ! Version : " . curl_version()['version'];
} else {
    echo "cURL n'est pas activé.";
}
?>