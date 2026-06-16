/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.10-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: scholarship
-- ------------------------------------------------------
-- Server version	10.11.10-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `administrator`
--

DROP TABLE IF EXISTS `administrator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administrator` (
  `ID` char(10) NOT NULL,
  PRIMARY KEY (`ID`),
  CONSTRAINT `administrator_ibfk_1` FOREIGN KEY (`ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administrator`
--

LOCK TABLES `administrator` WRITE;
/*!40000 ALTER TABLE `administrator` DISABLE KEYS */;
INSERT INTO `administrator` VALUES
('Z0000000');
/*!40000 ALTER TABLE `administrator` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `announcement`
--

DROP TABLE IF EXISTS `announcement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `ADATE` date DEFAULT NULL,
  `ATIME` time DEFAULT NULL,
  `CONTENT` varchar(1000) DEFAULT NULL,
  `AID` char(10) NOT NULL,
  `CATEGORY` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `AID` (`AID`),
  CONSTRAINT `announcement_ibfk_1` FOREIGN KEY (`AID`) REFERENCES `administrator` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcement`
--

LOCK TABLES `announcement` WRITE;
/*!40000 ALTER TABLE `announcement` DISABLE KEYS */;
INSERT INTO `announcement` VALUES
(15,'轉知財團法人平安菁英教育基金會114學年度第2學期平安獎學金申請資訊','2026-01-12','03:19:34','為鼓勵高中職及大專校院應屆畢業生於在校期間努力向學，提升\r\n個人能力，未來投入產業後能順利就業並協助企業發展，故設立\r\n本獎學金。','Z0000000',0),
(16,'轉知財團法人紀念尹珣若先生教育基金會114年度獎助學金申請資訊','2026-01-12','03:22:25','財團法人紀念尹珣若先生教育基金會114年度獎助學金申請資訊如下','Z0000000',0);
/*!40000 ALTER TABLE `announcement` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application`
--

DROP TABLE IF EXISTS `application`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application` (
  `APNO` int(10) NOT NULL AUTO_INCREMENT,
  `AUTOBI` varchar(1000) DEFAULT NULL,
  `RANK` varchar(11) DEFAULT NULL,
  `APDATE` date DEFAULT NULL,
  `GRADE` int(11) DEFAULT NULL,
  `AMOUNT` int(11) DEFAULT NULL,
  `RESULT` char(3) DEFAULT '審查中',
  `STID` char(10) NOT NULL,
  `OID` char(10) NOT NULL,
  `SCID` int(10) NOT NULL,
  `SCNAME` varchar(100) NOT NULL,
  `IS_POSTED` int(11) DEFAULT 0,
  PRIMARY KEY (`APNO`),
  KEY `STID` (`STID`),
  KEY `OID` (`OID`),
  KEY `SCID` (`SCID`),
  CONSTRAINT `application_ibfk_1` FOREIGN KEY (`STID`) REFERENCES `students` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `application_ibfk_2` FOREIGN KEY (`OID`) REFERENCES `organization` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `application_ibfk_3` FOREIGN KEY (`SCID`) REFERENCES `scholarship` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application`
--

LOCK TABLES `application` WRITE;
/*!40000 ALTER TABLE `application` DISABLE KEYS */;
/*!40000 ALTER TABLE `application` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `application_files`
--

DROP TABLE IF EXISTS `application_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `application_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `apno` int(11) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `apno` (`apno`),
  CONSTRAINT `application_files_ibfk_1` FOREIGN KEY (`apno`) REFERENCES `application` (`APNO`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `application_files`
--

LOCK TABLES `application_files` WRITE;
/*!40000 ALTER TABLE `application_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `application_files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ophone`
--

DROP TABLE IF EXISTS `ophone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ophone` (
  `ID` char(10) NOT NULL,
  `TEL` varchar(10) NOT NULL,
  PRIMARY KEY (`ID`,`TEL`),
  CONSTRAINT `ophone_ibfk_1` FOREIGN KEY (`ID`) REFERENCES `organization` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ophone`
--

LOCK TABLES `ophone` WRITE;
/*!40000 ALTER TABLE `ophone` DISABLE KEYS */;
INSERT INTO `ophone` VALUES
('S1111111','11111111'),
('S2222222','22222222'),
('S3333333','33333333'),
('S4444444','44444444'),
('S8888888','0988888888'),
('S8888888','88888888'),
('S8888888','8888888888'),
('S9999999','0999999999'),
('S9999999','99999999');
/*!40000 ALTER TABLE `ophone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `organization`
--

DROP TABLE IF EXISTS `organization`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `organization` (
  `ID` char(10) NOT NULL,
  `ONAME` varchar(20) NOT NULL,
  `CONTACT` varchar(10) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ONAME` (`ONAME`),
  CONSTRAINT `organization_ibfk_1` FOREIGN KEY (`ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `organization`
--

LOCK TABLES `organization` WRITE;
/*!40000 ALTER TABLE `organization` DISABLE KEYS */;
INSERT INTO `organization` VALUES
('S1111111','S111','S111聯絡人'),
('S2222222','S222','S222聯絡人'),
('S3333333','S333','S333聯絡人'),
('S4444444','S444','S444聯絡人'),
('S8888888','S888','S888聯絡人'),
('S9999999','S999','S999聯絡人');
/*!40000 ALTER TABLE `organization` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recommendations`
--

DROP TABLE IF EXISTS `recommendations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recommendations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text NOT NULL,
  `teacher_id` char(10) DEFAULT NULL,
  `teacher_name` varchar(100) NOT NULL,
  `teacher_email` varchar(255) NOT NULL,
  `rec_rel` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `application_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `recommendations_ibfk_2` (`application_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `recommendations_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `application` (`APNO`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `recommendations_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recommendations`
--

LOCK TABLES `recommendations` WRITE;
/*!40000 ALTER TABLE `recommendations` DISABLE KEYS */;
/*!40000 ALTER TABLE `recommendations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholarship`
--

DROP TABLE IF EXISTS `scholarship`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarship` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `NAME` varchar(65) NOT NULL,
  `provider_id` char(10) NOT NULL,
  `DEADLINE` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `CONDI` varchar(1000) NOT NULL,
  `AMOUNT` int(11) NOT NULL,
  `start_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `provider_id` (`provider_id`),
  CONSTRAINT `scholarship_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholarship`
--

LOCK TABLES `scholarship` WRITE;
/*!40000 ALTER TABLE `scholarship` DISABLE KEYS */;
INSERT INTO `scholarship` VALUES
(34,'財團法人紀念尹珣若先生教育基金會114年度獎助學金','S1111111','2026-01-30 16:00:00','申請者學業成績如有任一科不及格者或大專日夜間部選\r\n讀、旁聽及公私立機關委託職校代招之學員不得申請。',11111,'2026-01-13 16:00:00'),
(35,'外交部115年（114學年度）「外交獎學金」','S1111111','2026-01-22 16:00:00','在校期間持有本獎學金各獎項指定之認證或鑑定合格證書，且於 110 年 1 月 1 日至 115 年 1 月 31 日間取得。',5000,'2026-01-05 16:00:00'),
(36,'財團法人賑災基金會(以下稱賑災基金會)樺加沙颱風災害之各項賑助','S1111111','2025-12-30 16:00:00','113學年度上下學期之智育成績(學期總成績)均須達70分以\r\n上。',2000,'2025-12-08 16:00:00'),
(37,'財團法人綠產業發展教育基金會「急難救助暨優秀學生獎學金」','S1111111','2026-01-14 16:00:00','凡就讀國內公私立大專校院、高中職校之應屆畢業生(民國 115\r\n年畢業者)',7777,'2026-01-05 16:00:00'),
(38,'2025年TOEIC公益獎學金','S2222222','2026-02-03 16:00:00','（一）急難救助、低收入戶或身心障礙學子均以發放每名每學年 12,000 元(每學期 6,000 元)，退學或畢業取消資格。',6000,'2026-01-22 16:00:00'),
(39,'昌益慈善基金會「助學獎學金」','S2222222','2025-12-31 16:00:00','符合資格但已向各級政府申請並已領取獎學金者排除，以將社會資源作最\r\n有效運用，本會將商請縣市政府等相關單位協助排除，是以已申報過之學生可以\r\n相同文件向本會申請。',5555,'2025-12-29 16:00:00'),
(40,'財團法人愛盲基金會-113學年度第1學期清寒學生助學金','S2222222','2026-02-06 16:00:00','除身障者或患有疾病者等不可抗力因素外，領獎人務必出席參加獎學金頒發\r\n典禮，如發現家長代為申請，獎金納為家用將取消原獲獎資格，由其他優秀申\r\n請者替補。',6666,'2026-01-28 16:00:00'),
(41,'台灣國際造船股份有限公司獎學金(限理工科','S9999999','2026-01-29 16:00:00','1. 對象:以就讀於國立(科技大學之理工相關科系(以造船、輪機、電機、機械、\r\n海洋工程尤佳)大學三年級(含)以上、碩士班及博士班在學學生(須具中\r\n華民國國籍且不得兼具外國國籍)為主。\r\n2. 惟任何在職進修或專班以及本公司現職或留資停薪人員均不得申請。',10000,'2025-12-30 16:00:00'),
(42,'115年度「獎勵農漁民子女就學金作業計畫','S8888888','2026-01-09 16:00:00','本部各類學雜費減免或同性質之助學措施(簡稱本部就學\r\n補助)多訂有「已獲政府其他相關學雜費減免、補助或與\r\n補助學雜費性質相當之給付者,不得重複申領」之規\r\n定, 除法規另有規定外,學生應擇一申領。',8888,'2025-12-27 16:00:00'),
(43,'高雄市模範父親協會在學優秀青年','S8888888','2026-02-03 16:00:00','凡就讀國內公私立大專校院、高中職校之應屆畢業生(民國 115\r\n年畢業者)，並符合以下條件者皆可申請。\r\n一、 113學年度上下學期之智育成績(學期總成績)均須達70分以\r\n上。\r\n二、 在校期間持有本獎學金各獎項指定之認證或鑑定合格證\r\n書，且於 110 年 1 月 1 日至 115 年 1 月 31 日間取得。',7777,'2026-01-21 16:00:00'),
(44,'114學年度財團法人中華民國電腦技能基金','S8888888','2026-01-21 16:00:00','凡就讀國內公私立大專校院、高中職校之應屆畢業生(民國 115\r\n年畢業者)，並符合以下條件者皆可申請。\r\n一、 113學年度上下學期之智育成績(學期總成績)均須達70分以\r\n上。\r\n二、 在校期間持有本獎學金各獎項指定之認證或鑑定合格證\r\n書，且於 110 年 1 月 1 日至 115 年 1 月 31 日間取得。\r\n(報考或申請換補發證照之核發作業需時一個月，有意申請\r\n獎學金者，請注意需在獎學金申請截止時間前取得證書)\r\n三、 申請 TQC 項目者，一般證照：成績需達 80 分以上；輸入類：\r\n中文輸入、英文輸入、日文輸入及數字輸入皆須達專業級以\r\n上才可申請。TQC 專業人事人員別中文輸入需達到進階級\r\n得以申請。',10000,'2026-01-13 16:00:00');
/*!40000 ALTER TABLE `scholarship` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `students` (
  `ID` char(10) NOT NULL,
  `SID` char(8) NOT NULL,
  `DNAME` varchar(10) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `SID` (`SID`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES
('A1111111','A1111111','應化系'),
('A1112222','A1112222','A1112222'),
('a1115555','a1115555','CSIE'),
('A2222222','A2222222','應物系'),
('A3333333','A3333333','資工系'),
('A5555555','A5555551','法律系');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teachers` (
  `ID` char(10) NOT NULL,
  `DNAME` varchar(10) NOT NULL,
  PRIMARY KEY (`ID`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teachers`
--

LOCK TABLES `teachers` WRITE;
/*!40000 ALTER TABLE `teachers` DISABLE KEYS */;
INSERT INTO `teachers` VALUES
('T1111111','財法系'),
('T2222222','東語系'),
('T3333333','化材系');
/*!40000 ALTER TABLE `teachers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_messages`
--

DROP TABLE IF EXISTS `ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ticket_messages` (
  `MESSAGE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `TICKET_ID` int(11) NOT NULL,
  `SENDER_ID` char(10) NOT NULL,
  `MESSAGE` text NOT NULL,
  `CREATED_AT` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`MESSAGE_ID`),
  KEY `fk_ticket_messages_ticket` (`TICKET_ID`),
  KEY `fk_ticket_messages_sender` (`SENDER_ID`),
  CONSTRAINT `fk_ticket_messages_sender` FOREIGN KEY (`SENDER_ID`) REFERENCES `users` (`ID`),
  CONSTRAINT `fk_ticket_messages_ticket` FOREIGN KEY (`TICKET_ID`) REFERENCES `tickets` (`TICKET_ID`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_messages`
--

LOCK TABLES `ticket_messages` WRITE;
/*!40000 ALTER TABLE `ticket_messages` DISABLE KEYS */;
INSERT INTO `ticket_messages` VALUES
(1,1,'Z0000000','111','2026-06-16 21:55:03'),
(2,1,'Z0000000','22222','2026-06-16 21:55:20'),
(3,2,'A1112222','aaaaaaaa','2026-06-16 22:01:57'),
(4,2,'Z0000000','yyyyyy','2026-06-16 22:02:09'),
(5,2,'A1112222','1111','2026-06-16 22:02:13');
/*!40000 ALTER TABLE `ticket_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tickets` (
  `TICKET_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_ID` char(10) NOT NULL,
  `ADMIN_ID` char(10) DEFAULT NULL,
  `TITLE` varchar(255) NOT NULL,
  `STATUS` enum('open','pending','closed') DEFAULT 'open',
  `CREATED_AT` datetime DEFAULT current_timestamp(),
  `UPDATED_AT` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`TICKET_ID`),
  KEY `fk_tickets_user` (`USER_ID`),
  KEY `fk_tickets_admin` (`ADMIN_ID`),
  CONSTRAINT `fk_tickets_admin` FOREIGN KEY (`ADMIN_ID`) REFERENCES `users` (`ID`),
  CONSTRAINT `fk_tickets_user` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES
(1,'Z0000000','Z0000000','111','closed','2026-06-16 21:55:03','2026-06-16 21:55:03'),
(2,'A1112222','Z0000000','測試','open','2026-06-16 22:01:57','2026-06-16 22:02:13');
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `ID` char(10) NOT NULL,
  `ROLE` int(11) NOT NULL,
  `NAME` varchar(20) NOT NULL,
  `EMAIL` varchar(100) DEFAULT NULL,
  `TEL` varchar(10) DEFAULT NULL,
  `PWD` varchar(100) NOT NULL,
  `status` enum('pending','active','','') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `NAME` (`NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
('A1111111',1,'學生1','A1111111@mail.nuk.edu.tw','0911111111','$2y$10$tFXL8LVLDt8oJVhl2pdR.ete.HSJxpYn.9Q4XIFgrcXHY/gicpY2m','active','2026-01-04 20:28:06'),
('A1112222',1,'A1112222','A1112222@mail.nuk.edu.tw','0911222222','$2y$10$8FZSdFwso3HmyBIODHc8jO9sczjE1tWXw5U8qoCrK3eTomnUr.atu','active','2026-06-16 14:01:30'),
('a1115555',1,'張雪梅','a1115532@mail.nuk.edu.tw','0922666333','$2y$10$7nGFtTx8SzgjhVNaoOPqGeuhmGzV8vAB3iAIBge8Uk/WQjX/o3V1m','active','2026-06-11 17:05:30'),
('A2222222',1,'學生2','A2222222@mail.nuk.edu.tw','0922222222','$2y$10$Jp49FbL7EtH47Ff36nSMseXaI7Dbgz.SAERLmsEkIs1xDdhZ7vip2','active','2025-12-31 18:06:13'),
('A3333333',1,'學生3','A3333333@mail.nuk.edu.tw','0933333333','$2y$10$tnFvJvi7vtp2ruRncq2.Cuc87pSy/2FmmESkyhJTkDmOCoAnO4TMC','pending','2026-01-11 18:51:04'),
('A5555555',1,'學生5','A5555555@mail.nuk.edu.tw','0955555551','$2y$10$BlAJpG/usQtUI9ypXJe4r.XorMF93GIOgchmCeqZ8AbMaRr3FtWHi','active','2025-10-05 18:12:06'),
('S1111111',4,'S111','S1111111@gmail.com','11111111','$2y$10$qirkHzwKMnshSMxVfVPusuKKdXgaIWTh5UfP.UjDbd8NAU7pWfJXW','active','2026-01-11 11:17:32'),
('S2222222',4,'S222','S2222222@gmail.com','22222222','$2y$10$tW.fCY93JDdyRgTxGoWIcebcaBtZaOobfieuqQpd6hodD.VDu9Pk.','active','2026-01-11 11:16:23'),
('S3333333',4,'S333','S3333333@gmail.com','33333333','$2y$10$eruG0GBHbtSml6VXbTqVOu3Sz3D/r8S5t0/bI.Elj2gEMKDlMJnhy','active','2026-01-11 11:26:08'),
('S4444444',4,'S444','S4444444@gmail.com','44444444','$2y$10$u3WB7XkzY3ewGWhF/Tgod.LWkmZ8gKc1QfTQvNoXSmy5kREcHWuNu','active','2026-01-11 11:26:59'),
('S8888888',4,'S888','S88888888@gmail.com','88888888','$2y$10$WTqcO.wYQTBEhLEfjLVCJe2w7kvVUyXrn/hKDp2Knez4/1iwDtX/m','active','2026-01-11 07:09:39'),
('S9999999',4,'S999','S9999999@gmail.com','99999999','$2y$10$CsONXdPoXH/yDmAkQhXBhuJT2ciWjSq2DCpofkGBD1JTMY9owX8.u','active','2026-01-11 07:06:42'),
('T1111111',2,'老師1','T1111111@mail.nuk.edu.tw','0900000001','$2y$10$EB2Tp6wbHIAC82TFSz0afOaKkCPWwlAlOE9bM7o3W02r7rQorBfi6','active','2026-01-10 18:06:03'),
('T2222222',2,'老師2','T2222222@mail.nuk.edu.tw','0900000002','$2y$10$qWBRJGj9YNFIsmWopfJ7geT6tCY2bmsFmzuKSSei5nvTz7vGQC7Da','active','2026-01-06 20:02:10'),
('T3333333',2,'老師3','T3333333@mail.nuk.edu.tw','0900000003','$2y$10$j4gQYGAaMTgcZu3pMykIt.VljeGoxK5FQqGR14N4rcOVYxb.SSs5C','pending','2026-01-11 18:52:46'),
('Z0000000',3,'管理員0','Z0000000@mail.nuk.edu.tw','0900000000','$2y$10$7nGFtTx8SzgjhVNaoOPqGeuhmGzV8vAB3iAIBge8Uk/WQjX/o3V1m','active','2025-12-31 19:17:10');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'scholarship'
--

--
-- Dumping routines for database 'scholarship'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-16 22:10:07
