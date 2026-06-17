<?php
http_response_code(410);
header("Content-Type: text/plain; charset=UTF-8");
echo "已提交的推薦信不可撤回或再次編輯。";
