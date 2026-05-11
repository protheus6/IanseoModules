<?php
require_once(dirname(__DIR__, 4) . '/config.php');

header('Content-Type: application/javascript');

printf("function SelectSession_JSON(obj) {
    $.getJSON('%s?Ses='+$(obj).val(), function(data) {
        if (data.error==0) {
            $('#x_From').val(data.min);
            $('#x_To').val(data.max);
            $('#x_Coalesce_div').html(data.coalesce);
        }
    });
}", $CFG->ROOT_DIR.'Qualification/SelectSession.php');
