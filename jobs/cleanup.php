<?php
return function () {
    file_put_contents(__DIR__."/../cleanup.log", "Cleanup done at " . date("Y-m-d H:i:s") . "\n", FILE_APPEND);
};
