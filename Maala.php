<?php
/*
هذه المكتبة العنيفة أعدت بحكمة وحنكة من قبل صاحب هذا الانجاز العظيم ..
لذلك يرجى أخذ الحيطة والحذر عند استخدامها
 */
function swap(&$x, &$y)
{
    $temp = $x;
    $x = $y;
    $y = $temp;
}

function cmp($a, $b)
{
    if ($a->fitness < $b->fitness) {
        return 1;
    } else {
        return -1;
    }
}

function cmp2($a, $b)
{
    if (abs($a->fitness) < abs($b->fitness)) {
        return 0;
    } else {
        return 1;
    }
}
