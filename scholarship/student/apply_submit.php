<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";
require_once __DIR__ . "/../file_helpers.php";
require_role(1);

require 'PHPMailer.php'; 
  require 'SMTP.php'; 
  require 'Exception.php'; 
  use PHPMailer\PHPMailer\PHPMailer; 
  use PHPMailer\PHPMailer\Exception; 

/*function back_err(string $msg): void {
  header("Location: /scholarship/student/apply.php?err=" . urlencode($msg));
  exit;
}*/
function back_err($msg) {
  header("Location: /scholarship/student/apply.php?err=" . urlencode($msg));
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  back_err("不合法的請求方式");
}

//$stId = $_SESSION["user"]["id"] ?? "";
$stId = isset($_SESSION["user"]["stid"]) ? $_SESSION["user"]["stid"] : "";
if ($stId === "") back_err("登入狀態失效，請重新登入");

// 表單欄位
/*$method = trim($_POST["METHOD"] ?? "線上");
$schKey = trim($_POST["SCH_KEY"] ?? "");
$grade  = trim($_POST["GRADE"] ?? "");
$rank   = trim($_POST["RANK"] ?? "");

$recName  = trim($_POST["REC_NAME"] ?? "");
$recEmail = trim($_POST["REC_EMAIL"] ?? "");
$recRel   = trim($_POST["REC_REL"] ?? "");*/

//$method = trim(isset($_POST["METHOD"]) ? $_POST["METHOD"] : "線上"); 
$scid = isset($_POST["SCID"]) ? (int)$_POST["SCID"] : 0; 
$grade = trim(isset($_POST["GRADE"]) ? $_POST["GRADE"] : ""); 
$rank = trim(isset($_POST["RANK"]) ? $_POST["RANK"] : ""); 

//$recName = trim(isset($_POST["REC_NAME"]) ? $_POST["REC_NAME"] : ""); 
$recEmail = trim(isset($_POST["REC_EMAIL"]) ? $_POST["REC_EMAIL"] : ""); 
$recRel = trim(isset($_POST["REC_REL"]) ? $_POST["REC_REL"] : "");
$teacher_id = $_POST['teacher_id']; // 教授 ID (從下拉選單) 
$teacher_name = $_POST['teacher_name']; // 教授名字 (前端顯示文字)
$dept_name = null;
//$autobiText = trim(isset($_POST["AUTOBI_TEXT"]) ? $_POST["AUTOBI_TEXT"] : "");

//if ($scid <= 0) back_err("請選擇獎助學金");

// 解析 scholarship: "SCID|SCNAME|AMOUNT"
/*$parts = explode("|", $schKey);
if (count($parts) < 3) back_err("請選擇獎助學金");
$scid   = $parts[0];
$scname = $parts[1];
$amount = $parts[2];*/

// 從 scholarship 表查出 OID 
//$stmt = $pdo->prepare("SELECT OID FROM scholarship WHERE ID=:id"); 
/*$stmt = $pdo->prepare("SELECT provider_id FROM scholarship WHERE ID=:id");
$stmt->execute([":id" => $scid]); $row = $stmt->fetch(PDO::FETCH_ASSOC); 
if (!$row) back_err("獎助學金不存在"); 
$oid = $row["provider_id"]; $scname = $row["NAME"]; $amount = $row["AMOUNT"];*/

// 從 scholarship 表查出 provider_id, NAME, AMOUNT 
$stmt = $pdo->prepare("SELECT provider_id, NAME, AMOUNT FROM scholarship WHERE ID=:id"); 
$stmt->execute([":id" => $scid]); 
$row = $stmt->fetch(PDO::FETCH_ASSOC); 

if (!$row) back_err("獎助學金不存在");

$oid = $row["provider_id"]; $scname = $row["NAME"]; $amount = $row["AMOUNT"];

// 簡單檢查
//if (!in_array($method, ["線上","紙本"], true)) $method = "線上";
if ($recEmail !== "" && !filter_var($recEmail, FILTER_VALIDATE_EMAIL)) {
  back_err("推薦人 Email 格式不正確");
}

ensure_application_files_table($pdo);

/*function save_upload(array $file, string $uploadDirFs, string $uploadDirUrl, string $prefix, array $allowExt): array {
  if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    throw new RuntimeException("檔案上傳失敗（error=" . ($file["error"] ?? -1) . "）");
  }
  $orig = $file["name"] ?? "file";
  $tmp  = $file["tmp_name"] ?? "";

  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  if (!in_array($ext, $allowExt, true)) {
    throw new RuntimeException("不支援的檔案格式：" . htmlspecialchars($ext));
  }

  // 產生安全檔名
  $rand = bin2hex(random_bytes(6));
  $fname = $prefix . "_" . date("Ymd_His") . "_" . $rand . "." . $ext;

  $destFs = $uploadDirFs . $fname;
  if (!move_uploaded_file($tmp, $destFs)) {
    throw new RuntimeException("檔案搬移失敗");
  }

  return [
    "original" => $orig,
    "path_url" => $uploadDirUrl . $fname
  ];
}*/

function save_upload($file, $pdo, $uploaderId, $apno, $scid, $providerId, $fileSubtype, $allowExt) {
  $saved = store_uploaded_file($pdo, $file, 2, $uploaderId, array(
    "application_id" => $apno,
    "scholarship_id" => $scid,
    "scholarship_provider_id" => $providerId,
    "file_subtype" => $fileSubtype,
    "allowed_ext" => $allowExt
  ));

  return array(
    "original" => $saved["original_name"],
    "path_url" => $saved["view_url"],
    "file_id" => $saved["id"]
  );
}

try {
  $pdo->beginTransaction();

  // 1) 建 application 主檔（APNO auto increment，這裡不要插 APNO）
  /*if (!empty($teacher_id)) { 
    stmt = $pdo->prepare("SELECT DNAME FROM scholarship_teachers WHERE ID = ?"); 
    $stmt->execute([$teacher_id]); 
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC); 
    if ($teacher) { $teacher_name = $teacher['DNAME']; 
    } 
  }*/
  if (!empty($teacher_id)) { 
    $stmt = $pdo->prepare(" 
    SELECT u.NAME AS teacher_name, t.DNAME AS dept_name 
    FROM teachers t 
    JOIN users u ON t.ID = u.ID 
    WHERE t.ID = ? 
  "); 
  $stmt->execute([$teacher_id]); 
  $teacher = $stmt->fetch(PDO::FETCH_ASSOC); 
  if ($teacher) { 
    $teacher_name = $teacher['teacher_name']; 
    $dept_name = $teacher['dept_name']; 
    } 
  }
  /*$sql = "
    INSERT INTO application
      (METHOD, AUTOBI, RANK, APDATE, GRADE, AMOUNT, RESULT, STID, OID, SCID, SCNAME)
    VALUES
      (:method, '', :rank, NOW(), :grade, :amount, '審查中', :stid, NULL, :scid, :scname)
  ";*/
  /*$sql = "
    INSERT INTO application 
      (AUTOBI, RANK, APDATE, GRADE, AMOUNT, RESULT, STID, OID, SCID, SCNAME) 
    VALUES 
      (:autobi, :rank, NOW(), :grade, :amount, '審查中', :stid, :oid, :scid, :scname)
  ";*/
  
  $sql = "
    INSERT INTO application 
      (RANK, APDATE, GRADE, AMOUNT, RESULT, STID, OID, SCID, SCNAME) 
    VALUES 
      (:rank, NOW(), :grade, :amount, '審查中', :stid, :oid, :scid, :scname)
  ";
  $stmt = $pdo->prepare($sql);
  $ok = $stmt->execute([
    //":method" => $method,
    //":autobi" => $autobiText,
    ":rank"   => ($rank === "" ? null : $rank),
    ":grade"  => ($grade === "" ? null : $grade),
    ":amount" => $amount,
    ":stid"   => $stId,
    ":oid" => $oid,
    ":scid"   => $scid,
    ":scname" => $scname
  ]);

//if (!$ok) { throw new RuntimeException("申請資料寫入失敗"); }

// 1) 先用 PDO 的 lastInsertId（MySQL 驅動不帶參數） 
/*$apno = (int)$pdo->lastInsertId(); 
// 2) 失敗就用 SQL 的 LAST_INSERT_ID() 
if ($apno <= 0) { 
  $apno = (int)$pdo->query("SELECT LAST_INSERT_ID()")->fetchColumn(); 
} 
if ($apno <= 0) { 
  throw new RuntimeException("取得 APNO 失敗"); 
}*/
//echo "<pre>"; var_dump($ok); var_dump($stmt->errorInfo()); echo "</pre>"; if ($ok) { $apno = (int)$pdo->lastInsertId(); echo "新申請編號: " . $apno; }
if (!$ok) { echo "<pre>"; var_dump($stmt->errorInfo()); echo "</pre>"; exit; } $apno = (int)$pdo->lastInsertId(); echo "新申請編號: " . $apno;

/*$stmt = $pdo->prepare("INSERT INTO application (STID, SCID, APDATE) VALUES (?, ?, ?)");
if (!$stmt->execute([$stId, $schno, $apdate])) {
    throw new RuntimeException("申請資料寫入失敗");
}*/

// 檢查 STID 是否存在於 students
$stmt = $pdo->prepare("SELECT 1 FROM students WHERE ID = :id");
$stmt->execute([":id" => $stId]);
if (!$stmt->fetchColumn()) {
    throw new RuntimeException("學生 ID 不存在，請重新登入或確認資料");
}

// 檢查 SCID 是否存在於 scholarship
$stmt = $pdo->prepare("SELECT 1 FROM scholarship WHERE ID = :id");
$stmt->execute([":id" => $scid]);
if (!$stmt->fetchColumn()) {
    throw new RuntimeException("獎助學金不存在，請重新選擇");
}


  // 2) 自傳（必傳）—存到 application.AUTOBI + application_files(autobi)
  if (empty($_FILES["AUTOBI_FILE"])) throw new RuntimeException("請上傳自傳");
  //if (empty($_FILES["AUTOBI_FILE"])) throw new RuntimeException("請上傳自傳/讀書計畫");
  $autobi = save_upload($_FILES["AUTOBI_FILE"], $pdo, $stId, $apno, $scid, $oid, "autobi", array("pdf","doc","docx"));

  // update application.AUTOBI
  $stmt = $pdo->prepare("UPDATE application SET AUTOBI=:p WHERE APNO=:apno");
  $stmt->execute([":p"=>$autobi["path_url"], ":apno"=>$apno]);

  // update application.AUTOBI
  //tmt = $pdo->prepare("UPDATE application SET AUTOBI=:p WHERE APNO=:apno");
  //stmt->execute([":p"=>$autobiText, ":apno"=>$apno]);

  // 2) 自傳（必填文字）—存到 application.AUTOBI
  //$autobiText = trim($_POST["AUTOBI_TEXT"] ?? "");
  /*utobiText = trim(isset($_POST["AUTOBI_TEXT"]) ? $_POST["AUTOBI_TEXT"] : "");
  if ($autobiText === "") {
      throw new RuntimeException("請輸入自傳");
  }
  $stmt = $pdo->prepare("UPDATE application SET AUTOBI=:p WHERE APNO=:apno"); 
  $stmt->execute([":p"=>$autobiText, ":apno"=>$apno]);*/

  // 3) 其他資料（多檔，可空）
  if (!empty($_FILES["OTHER_FILES"]) && is_array($_FILES["OTHER_FILES"]["name"])) {
    $names = $_FILES["OTHER_FILES"]["name"];
    for ($i=0; $i<count($names); $i++) {
      if ($_FILES["OTHER_FILES"]["error"][$i] === UPLOAD_ERR_NO_FILE) continue;

      $one = [
        "name" => $_FILES["OTHER_FILES"]["name"][$i],
        "type" => $_FILES["OTHER_FILES"]["type"][$i],
        "tmp_name" => $_FILES["OTHER_FILES"]["tmp_name"][$i],
        "error" => $_FILES["OTHER_FILES"]["error"][$i],
        "size" => $_FILES["OTHER_FILES"]["size"][$i],
      ];

      $saved = save_upload($one, $pdo, $stId, $apno, $scid, $oid, "support", array("pdf","doc","docx","jpg","jpeg","png"));
    }
  }

  // 4) 推薦信請求（可空）：有填教授 email 才建立 token
  $recommendLink = "";
  if ($recEmail !== "") {
    $token = md5(uniqid(mt_rand(), true));
    $studentName = isset($_SESSION["user"]["name"]) ? $_SESSION["user"]["name"] : "未知學生";
    $stmt = $pdo->prepare("
      INSERT INTO recommendations (teacher_id, teacher_name, teacher_email, rec_rel, application_id,  token, student_name)
      VALUES (:tid, :rn, :re, :rr, :apno, :tk, :stuName)
    ");
    $stmt->execute([
      //":apno" => $apno,
      //":rn" => ($recName === "" ? "（未填）" : $recName),
      ':tid' => $teacher_id,
      ":rn" => $teacher_name,
      ":re" => $recEmail,
      ":rr" => ($recRel === "" ? null : $recRel),
      ":apno" => $apno,
      ":tk" => $token,
      ":stuName" => $studentName
    ]);

    // Demo 先回傳連結（之後你再換成寄 Gmail）
    $recommendLink = "http://127.0.0.1:5050/scholarship/professor/recommendation.php?token=" . $token;
  }

  // ====== 在這裡加查學生姓名 ====== 
  /*$stmt = $pdo->prepare("SELECT NAME FROM students WHERE ID = :id"); 
  $stmt->execute([":id" => $stId]); 
  $studentRow = $stmt->fetch(PDO::FETCH_ASSOC); 
  $studentName = $studentRow ? $studentRow["NAME"] : "未知學生";*/

  // ====== PHPMailer 寄信 ====== 
  
  $mail = new PHPMailer(true); 

  try { 
    $mail->isSMTP(); 
    $mail->Host = 'smtp.gmail.com'; 
    $mail->SMTPAuth = true; 
    $mail->Username = ''; //信箱
    $mail->Password = ''; //申請的密碼
    $mail->SMTPSecure = 'tls'; 
    $mail->Port = 587; 
    
    $mail->setFrom('', 'Scholarship System'); //信箱
    $mail->addAddress($recEmail, $teacher_name); 
    $mail->isHTML(true); $mail->CharSet = "UTF-8"; 
    $mail->Encoding = "base64"; 
    $mail->Subject = "推薦信填寫邀請 - 學生 {$studentName}"; 
    $mail->Body = "親愛的 {$teacher_name} 教授，<br><br> 
                   學生 <strong>{$studentName}</strong> 已邀請您填寫推薦信，請點擊以下連結完成推薦信：<br> 
                   <a href='{$recommendLink}'>{$recommendLink}</a>
                   <br><br> 
                   感謝您的協助！"; 
                   
    $mail->send(); 
  } catch (Exception $e) { 
    // 可以記錄錯誤，但不要中斷申請流程 
    error_log("推薦信邀請寄送失敗: " . $mail->ErrorInfo); }

  $pdo->commit();

  $q = "msg=" . urlencode("申請已送出（APNO={$apno}），狀態：審查中");
  if ($recommendLink !== "") {
    $q .= "&recommend_link=" . urlencode($recommendLink);
  }
  header("Location: /scholarship/student/apply.php?" . $q);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  back_err("送出失敗：" . $e->getMessage());
}


