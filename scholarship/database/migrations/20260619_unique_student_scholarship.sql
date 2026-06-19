-- 執行前先確認目前是否已有重複申請：
-- SELECT STID, SCID, COUNT(*) AS total
-- FROM application
-- GROUP BY STID, SCID
-- HAVING COUNT(*) > 1;
--
-- 若上方查詢有結果，請先由管理員判斷要保留哪一筆，不要直接刪除正式申請資料。

ALTER TABLE application
    ADD UNIQUE KEY uq_application_student_scholarship (STID, SCID);
