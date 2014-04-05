<?php


function password($length = 16)
{

    $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    return substr(str_shuffle(str_repeat($pool, 5)), 0, $length);
}


function prd($elem = '')
{
    pr($elem);
    die;
}

function pr($elem = '')
{
    print_r($elem);
    echo "\n";
}
