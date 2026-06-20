<?php

function application_final_statuses()
{
    return array("通過", "不通過", "已獲獎", "未獲獎");
}

function application_status_is_final($status)
{
    return in_array((string)$status, application_final_statuses(), true);
}

function application_status_can_edit($status)
{
    return !application_status_is_final($status);
}
