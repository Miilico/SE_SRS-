<?php

function scholarship_departments_by_college()
{
    return array(
        "人文社會科學院" => array(
            "工藝與創意設計學系",
            "西洋語文學系",
            "東亞語文學系",
            "建築學系",
            "創意設計與建築學系",
            "運動健康與休閒學系",
            "運動競技學系",
        ),
        "工學院" => array(
            "土木與環境工程學系",
            "土木與環境工程學系原住民專班",
            "化學工程及材料工程學系",
            "半導體製造智能化技術產業碩士專班",
            "資訊工程學系",
            "電子構裝整合技術產業碩士專班",
            "電機工程學系",
        ),
        "法學院" => array(
            "法律學系",
            "法學院博士班",
            "政治法律學系",
            "財經法律學系",
        ),
        "理學院" => array(
            "生命科學系",
            "統計學研究所",
            "應用化學系",
            "應用物理學系",
            "應用科學碩士學位學程",
            "應用數學系",
        ),
        "管理學院" => array(
            "上海國際高階經營管理碩士在職專班",
            "亞太工商管理學系",
            "財務金融學系",
            "海西國際高階經營管理碩士在職專班",
            "高階法律暨管理碩士在職專班",
            "高階經營管理碩士在職專班",
            "國際高階經營管理泰國境外碩士在職專班",
            "國際商業管理碩士學位學程",
            "越南國際高階經營管理碩士在職專班",
            "資訊管理學系",
            "製商整合服務規劃產業碩士專班",
            "應用經濟學系",
        ),
    );
}

function scholarship_department_values()
{
    $values = array();
    foreach (scholarship_departments_by_college() as $departments) {
        foreach ($departments as $department) {
            $values[] = $department;
        }
    }
    return $values;
}
