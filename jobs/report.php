<?php
return function () {
    file_put_contents(__DIR__."/../report.log", "Report generated at " . date("Y-m-d H:i:s") . "\n", FILE_APPEND);
};
