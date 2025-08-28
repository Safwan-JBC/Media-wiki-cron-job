<?php
return function () {
    file_put_contents(__DIR__."/../backup.log", "Backup ran at " . date("Y-m-d H:i:s") . "\n", FILE_APPEND);
};
