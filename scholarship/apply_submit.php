<?php
require_once "config.php";

$STID = trim($_POST["STID"] ?? "");
$METHOD = trim($_POST["METHOD"] ?? "");
$SCH_KEY = $_POST["SCH_KEY"] ?? "";
$GRADE = $_POST["GRADE"] !== "" ? (int)$_POST["GRADE"] : null;

if ($STID === "" || $SCH_KEY === "") die("資料不完整");

[$SCID, $SCNAME] = explode("|", $SCH_KEY, 2);

// 先簡單假設 OID = SCID（因為 scholarship.ID 本身就是 organization.ID）
$OID = $SCID;

// 產生 APNO（char(10)）
$APNO = "A" . date("md") . substr(bin2hex(random_bytes(3)), 0, 5); // 1+4+5=10

// 檔案上傳 → 存到 uploads/，路徑寫到 AUTOBI
if (!isset($_FILES["AUTOBI_FILE"]) || $_FILES["AUTOBI_FILE"]["error"] !== UPLOAD_ERR_OK) {
  die("自傳上傳失敗");
}
$uploadDir = __DIR__ . "/uploads";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$orig = $_FILES["AUTOBI_FILE"]["name"];
$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
$stored = $APNO . "_autobi." . $ext;
$target = $uploadDir . "/" . $stored;

if (!move_uploaded_file($_FILES["AUTOBI_FILE"]["tmp_name"], $target)) {
  die("檔案儲存失敗");
}
$AUTOBI = "uploads/" . $stored;

// INSERT
$sql = "INSERT INTO application
(APNO, METHOD, AUTOBI, APDATE, GRADE, RESULT, STID, OID, SCID, SCNAME)
VALUES
(:APNO, :METHOD, :AUTOBI, CURDATE(), :GRADE, '審查中', :STID, :OID, :SCID, :SCNAME)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ":APNO" => $APNO,
  ":METHOD" => $METHOD !== "" ? $METHOD : null,
  ":AUTOBI" => $AUTOBI,
  ":GRADE" => $GRADE,
  ":STID" => $STID,
  ":OID" => $OID,
  ":SCID" => $SCID,
  ":SCNAME" => $SCNAME,
]);

echo "申請成功！你的申請編號 APNO = " . htmlspecialchars($APNO);
