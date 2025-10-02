/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-12.0.2-MariaDB, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: municihelp
-- ------------------------------------------------------
-- Server version	12.0.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(13) NOT NULL COMMENT 'Format: +639XXXXXXXXX',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `admins` VALUES
(1,'Mayor','$2y$12$tu1p2CIKd.y3yx1qRr7ShOZOenAreooqkX.0HhYH8F8OHOk1J14wa',1,'2025-07-02 06:56:17','+639999999990'),
(2,'Vice Mayor','$2y$12$Md6YjX79XjGfG4t9OgXC8uCM3yEZp6fqL8BrR1N5dcpuPP0aJv0bq',7,'2025-07-23 13:04:43','+639999999991'),
(3,'MSWD Admin','$2y$12$XZlT9IbMf14qoplfhGn7sOHGVHeR664ZMpE0CsmmS.tjOIVuIvSm2',2,'2025-08-02 10:36:51','+639999999992'),
(4,'Mayor Admin','$2y$12$M6hl4FV0w1SogTPUFoV7eO.reDGi4vO1N1PB2zmOpTBKnuqyM1BGy',3,'2025-08-02 10:37:16','+639999999993'),
(5,'PWD Admin','$2y$12$FyLvm0izzlvqho4d0h3q7OGfyOslewfa4wXHgSX4WI4yJPgqPFQJa',4,'2025-08-02 10:39:38','+639999999994'),
(6,'Animal Admin','$2y$12$Rxnw4fPgii7aG4pdnApYG.UqGpckBFyMhptBEnqsi1wm90VKaKFOm',5,'2025-08-02 10:39:56','+639999999995'),
(7,'Pound Admin','$2y$12$HrbN5eoSjWkMIZQXoCz8eu5rUREIiFwgU1DiZd57HL9mVmGxcPAt2',6,'2025-08-02 10:55:52','+639999999996'),
(8,'Assistance Admin','$2y$12$HVgubbFTIUR3INfRSAHNvOvNDn4Vctvt4oc1k8bBU9KmeBtvXlzOS',8,'2025-08-02 10:56:05','+639999999997'),
(9,'Barangay 1','$2y$12$rOrth6uCVimMLv5q/w.I8uAHbWlWSVZ490oqMTbxD4cZgHEn/kDFi',17,'2025-08-04 07:49:03','+639019999999'),
(10,'Barangay 2','$2y$12$VYtwGlpuBcrD6w7hT.cvd.oFHU4kta7OO0xHQcVjSkwUqHjK0Fcoe',18,'2025-08-04 07:50:28','+639029999999'),
(11,'Barangay 3','$2y$12$oYNjH.TQaccMlB9K3G8kduEWdcpuVCccq0QWXREyt1BufRmdI0Ppq',19,'2025-08-04 07:50:39','+639039999999'),
(12,'Barangay 4','$2y$12$UIR8oeiQXDYxJYTIWBPmZeWNjf51JNBQZfeFK88Kc/MlyNeNZK33C',20,'2025-08-04 07:50:50','+639049999999'),
(13,'Barangay 5','$2y$12$LWKG5nKmZ/7ZSAZUnrge5OS0KvTZ.184U1QMtaoG8LNDxmelxu9y.',21,'2025-08-04 07:51:00','+639059999999'),
(14,'Barangay 6','$2y$12$/0tWW4GQvP/J45l3OIHb0.TQdHrquyJ3.uUGS.W/oVMoEoOGkPv96',22,'2025-08-04 07:51:12','+639069999999'),
(15,'Barangay 7','$2y$12$SpsUodJ4sdrnG/aN0qSkOeqQODvzR3IV//A5AzHf5vLO3FEYEoAj.',23,'2025-08-04 07:51:25','+639079999999'),
(16,'Barangay 8','$2y$12$WA8TzUj2eVjTdQM51gXWGup.giMSX4ABgkGK2QnqA9QYw/X8E8NFi',24,'2025-08-04 07:51:42','+639089999999'),
(17,'Barangay 9','$2y$12$Z3m5.RBEgHaOPNhhqTotueTRfieOX5Z3Q5Te8bKljVnLDUCYxyLmO',25,'2025-08-04 07:51:52','+639099999999'),
(18,'Barangay 10','$2y$12$gjNBrNFDZSJMK0IrEgRnqeTmKuXLCLkYs1FQX5v3eHUEnz4NNnCJC',26,'2025-08-04 07:52:02','+639109999999'),
(19,'Barangay 11','$2y$12$qkTTkb9yNexMwh8bHBJqtObAFPi.16Ga77YAy1jxDK9SNonlu7/RG',27,'2025-08-04 07:52:12','+639119999999'),
(20,'Barangay 12','$2y$12$QY.HxQX.8OPwr5.N.0X4m.C1MTm31pr07YjswKyuXX8ui4.UGZ8ZS',28,'2025-08-04 07:53:11','+639129999999'),
(21,'Barangay 13','$2y$12$EbhS84MpiLK6UN/xiAf/peA0QPipn.phkl1nxwccqs3Lphdx9d8qm',29,'2025-08-04 07:53:23','+639139999999'),
(22,'Barangay 14','$2y$12$uHCLWJmBeCTxZp60CA2jd.FYIWycBp3YlFLX8iRwbsrvs/Oowla9C',30,'2025-08-04 07:53:37','+639149999999'),
(23,'Barangay 15','$2y$12$32iokZM64Ma9TTxAJTGL7.BfyBN1bs.vdkQTXpfVOFlUYtfRpjvA.',31,'2025-08-04 07:54:01','+639159999999'),
(24,'Barangay 16','$2y$12$vdTHYtPmA.eBROcLgw7xD./JIiKubdacyVpk.IdFQZ1OeNSHYUUFq',32,'2025-08-04 07:54:16','+639169999999'),
(25,'Barangay 17','$2y$12$2wKyUDcMpOVyqLHFaL2EqOJf4jktI6f4r5UxPuiX3JNtd8TWbD3be',33,'2025-08-04 07:54:27','+639179999999'),
(26,'Barangay 18','$2y$12$BMpvG1Mq7UlL106krgDnA.9hrXGRdxEii39Nr1dEcEJD7WxnqDEKC',34,'2025-08-04 07:54:36','+639189999999'),
(27,'Barangay 19','$2y$12$CI.xB0eUV1XnFLctm0.gW.zEDHp8vHMads/ugGis4/.sXnxnofRta',35,'2025-08-04 07:54:46','+639199999999');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
commit;

--
-- Table structure for table `ambulance_requests`
--

DROP TABLE IF EXISTS `ambulance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ambulance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `complete_address` text NOT NULL,
  `precint_number` varchar(10) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `family_relation_id` int(11) NOT NULL,
  `pickup_date` date NOT NULL,
  `pickup_time` time NOT NULL,
  `pickup_location` text NOT NULL,
  `destination` text NOT NULL,
  `companions` tinyint(1) NOT NULL DEFAULT 1,
  `purpose` text NOT NULL,
  `patient_id_path` varchar(255) DEFAULT NULL,
  `requester_id_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','scheduled','completed','declined','cancelled') NOT NULL DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `approvedby_admin_id` int(11) DEFAULT NULL,
  `completedby_admin_id` int(11) DEFAULT NULL,
  `declinedby_admin_id` int(11) DEFAULT NULL,
  `cancelledby_admin_id` int(11) DEFAULT NULL,
  `walkin_admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `barangay_id` (`barangay_id`),
  KEY `family_relation_id` (`family_relation_id`),
  CONSTRAINT `ambulance_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ambulance_requests_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  CONSTRAINT `ambulance_requests_ibfk_3` FOREIGN KEY (`family_relation_id`) REFERENCES `family_relations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ambulance_requests`
--

/*!40000 ALTER TABLE `ambulance_requests` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `ambulance_requests` ENABLE KEYS */;
commit;

--
-- Table structure for table `assistance_requests`
--

DROP TABLE IF EXISTS `assistance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assistance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date DEFAULT NULL,
  `assistance_id` int(11) NOT NULL,
  `assistance_name` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `specific_request_path` varchar(255) DEFAULT NULL,
  `indigency_cert_path` varchar(255) DEFAULT NULL,
  `id_copy_path` varchar(255) DEFAULT NULL,
  `id_copy_path_2` varchar(255) DEFAULT NULL,
  `request_letter_path` varchar(255) DEFAULT NULL,
  `complete_address` text NOT NULL,
  `relation_id` int(11) DEFAULT NULL,
  `precint_number` varchar(10) DEFAULT NULL,
  `status` enum('pending','approved','completed','declined','cancelled') NOT NULL DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `queue_date` date DEFAULT NULL,
  `released_date` date DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `relation_to_recipient` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `reschedule_count` int(11) DEFAULT 0,
  `is_walkin` tinyint(1) NOT NULL DEFAULT 0,
  `walkin_admin_id` int(11) DEFAULT NULL,
  `approvedby_admin_id` int(11) DEFAULT NULL,
  `completedby_admin_id` int(11) DEFAULT NULL,
  `declinedby_admin_id` int(11) DEFAULT NULL,
  `rescheduledby_admin_id` int(11) DEFAULT NULL,
  `cancelledby_admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `assistance_id` (`assistance_id`),
  KEY `fk_assistance_requests_barangay` (`barangay_id`),
  KEY `fk_relation` (`relation_id`),
  CONSTRAINT `assistance_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assistance_requests_ibfk_2` FOREIGN KEY (`assistance_id`) REFERENCES `assistance_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assistance_requests_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  CONSTRAINT `fk_relation` FOREIGN KEY (`relation_id`) REFERENCES `family_relations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assistance_requests`
--

/*!40000 ALTER TABLE `assistance_requests` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `assistance_requests` ENABLE KEYS */;
commit;

--
-- Table structure for table `assistance_types`
--

DROP TABLE IF EXISTS `assistance_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `assistance_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `specific_requirement` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_assistance_types_parent` (`parent_id`),
  CONSTRAINT `fk_assistance_types_parent` FOREIGN KEY (`parent_id`) REFERENCES `assistance_types` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assistance_types`
--

/*!40000 ALTER TABLE `assistance_types` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `assistance_types` VALUES
(1,'Glucometer Request','Request for a glucometer device.','Glucometer Request galing sa Doctor',NULL),
(2,'Nebulizer Request','Request for a nebulizer device.','Nebulizer Request galing sa Doctor',NULL),
(3,'Burial Assistance','Financial aid for burial expenses.','Death certificate at Statement of Account',NULL),
(4,'Financial Assistance','Financial aid for educational expenses.','COE or Statement of Account',NULL),
(5,'Laboratory Assistance','Financial aid for laboratory tests.','Laboratory Request',NULL),
(6,'Medical Assistance','Financial aid for medical expenses.','Reseta o Hospital Bill',NULL),
(7,'Wheelchair Request','Request for a wheelchair.','Whole picture ng pasyente',NULL),
(8,'Equipment','Medical equipment assistance','Reseta o Hospital Bill',6),
(9,'Maintenance','Medical maintenance assistance','Reseta o Hospital Bill',6),
(10,'Vitamins','Vitamin assistance','Reseta o Hospital Bill',6),
(11,'Educational','Educational financial assistance','COE or Statement of Account',4),
(12,'Sponsorship','Sponsorship financial assistance','COE or Statement of Account',4);
/*!40000 ALTER TABLE `assistance_types` ENABLE KEYS */;
commit;

--
-- Table structure for table `barangays`
--

DROP TABLE IF EXISTS `barangays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `barangays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barangays`
--

/*!40000 ALTER TABLE `barangays` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `barangays` VALUES
(1,'Balatong A'),
(2,'Balatong B'),
(3,'Cutcot'),
(4,'Dampol 1st'),
(5,'Dampol 2nd A'),
(6,'Dampol 2nd B'),
(7,'Dulong Malabon'),
(8,'Inaon'),
(9,'Longos'),
(10,'Lumbac'),
(11,'Paltao'),
(12,'Penabatan'),
(13,'Poblacion'),
(14,'Santa Peregrina'),
(15,'Santo Cristo'),
(16,'Taal'),
(17,'Tabon'),
(18,'Tibag'),
(19,'Tinejero');
/*!40000 ALTER TABLE `barangays` ENABLE KEYS */;
commit;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_department_parent` (`parent_id`),
  CONSTRAINT `fk_department_parent` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `departments` VALUES
(1,'Mayor Superadmin',1,'mayor/superadmin/management.php','2025-07-02 06:54:41'),
(2,'MSWD',1,'mayor/mswd/admin/dashboard.php','2025-07-02 06:54:41'),
(3,'Mayor',1,'mayor/mswd/mayor_admin/dashboard.php','2025-07-02 06:54:41'),
(4,'PWD',1,'mayor/pwd/admin/inquiries.php','2025-07-19 17:11:11'),
(5,'Animal',1,'mayor/animal/admin/dashboard.php','2025-07-19 17:11:11'),
(6,'Pound',1,'mayor/animal/pound_admin/dashboard.php','2025-07-19 17:11:11'),
(7,'Vice Mayor Superadmin',7,'vice_mayor/superadmin/management.php','2025-07-19 17:11:12'),
(8,'Assistance',7,'vice_mayor/assistance/admin/dashboard.php','2025-07-21 02:39:04'),
(9,'Ambulance',7,'vice_mayor/ambulance/admin/dashboard.php','2025-07-23 08:51:25'),
(17,'Balatong A',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(18,'Balatong B',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(19,'Cutcot',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(20,'Dampol 1st',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(21,'Dampol 2nd A',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(22,'Dampol 2nd B',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(23,'Dulong Malabon',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(24,'Inaon',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(25,'Longos',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(26,'Lumbac',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(27,'Paltao',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(28,'Penabatan',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(29,'Poblacion',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(30,'Sta Peregrina',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(31,'Sto Cristo',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(32,'Taal',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(33,'Tabon',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(34,'Tibag',1,'mayor/barangay/index.php','2025-08-04 07:44:21'),
(35,'Tinejero',1,'mayor/barangay/index.php','2025-08-04 07:44:21');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
commit;

--
-- Table structure for table `dog_adoptions`
--

DROP TABLE IF EXISTS `dog_adoptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dog_adoptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dog_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `complete_address` text NOT NULL,
  `phone` varchar(13) NOT NULL,
  `adoption_reason` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('pending','approved','declined','completed','cancelled') DEFAULT 'pending',
  `approvedby_admin_id` int(11) DEFAULT NULL,
  `declinedby_admin_id` int(11) DEFAULT NULL,
  `completedby_admin_id` int(11) DEFAULT NULL,
  `cancelledby_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `reason` text DEFAULT NULL,
  `handover_photo_path` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dog_id` (`dog_id`),
  KEY `user_id` (`user_id`),
  KEY `approvedby_admin_id` (`approvedby_admin_id`),
  KEY `declinedby_admin_id` (`declinedby_admin_id`),
  KEY `completedby_admin_id` (`completedby_admin_id`),
  KEY `cancelledby_admin_id` (`cancelledby_admin_id`),
  CONSTRAINT `dog_adoptions_ibfk_1` FOREIGN KEY (`dog_id`) REFERENCES `dogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dog_adoptions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dog_adoptions_ibfk_4` FOREIGN KEY (`approvedby_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `dog_adoptions_ibfk_5` FOREIGN KEY (`declinedby_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `dog_adoptions_ibfk_6` FOREIGN KEY (`completedby_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `dog_adoptions_ibfk_7` FOREIGN KEY (`cancelledby_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dog_adoptions`
--

/*!40000 ALTER TABLE `dog_adoptions` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `dog_adoptions` ENABLE KEYS */;
commit;

--
-- Table structure for table `dog_claimers`
--

DROP TABLE IF EXISTS `dog_claimers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dog_claimers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `total_claims` int(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dog_claimers`
--

/*!40000 ALTER TABLE `dog_claimers` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `dog_claimers` ENABLE KEYS */;
commit;

--
-- Table structure for table `dog_claims`
--

DROP TABLE IF EXISTS `dog_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dog_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dog_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `complete_address` text NOT NULL,
  `phone` varchar(13) NOT NULL,
  `name_of_dog` varchar(100) DEFAULT NULL,
  `age_of_dog` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('pending','approved','declined','completed','cancelled') DEFAULT 'pending',
  `approvedby_admin_id` int(11) DEFAULT NULL,
  `declinedby_admin_id` int(11) DEFAULT NULL,
  `completedby_admin_id` int(11) DEFAULT NULL,
  `cancelledby_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `reason` text DEFAULT NULL,
  `handover_photo_path` text DEFAULT NULL,
  `receipt_photo_path` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dog_id` (`dog_id`),
  KEY `user_id` (`user_id`),
  KEY `approvedby_admin_id` (`approvedby_admin_id`),
  KEY `declinedby_admin_id` (`declinedby_admin_id`),
  KEY `completedby_admin_id` (`completedby_admin_id`),
  KEY `cancelledby_admin_id` (`cancelledby_admin_id`),
  CONSTRAINT `dog_claims_ibfk_1` FOREIGN KEY (`dog_id`) REFERENCES `dogs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dog_claims_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dog_claims_ibfk_4` FOREIGN KEY (`approvedby_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `dog_claims_ibfk_5` FOREIGN KEY (`declinedby_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `dog_claims_ibfk_6` FOREIGN KEY (`completedby_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `dog_claims_ibfk_7` FOREIGN KEY (`cancelledby_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dog_claims`
--

/*!40000 ALTER TABLE `dog_claims` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `dog_claims` ENABLE KEYS */;
commit;

--
-- Table structure for table `dogs`
--

DROP TABLE IF EXISTS `dogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `breed` varchar(100) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `size` enum('small','medium','large') DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `location_found` varchar(255) NOT NULL,
  `date_caught` datetime NOT NULL DEFAULT current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('for_claiming','claimed','for_adoption','adopted','euthanized') NOT NULL,
  `createdby_admin_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `createdby_admin_id` (`createdby_admin_id`),
  CONSTRAINT `dogs_ibfk_1` FOREIGN KEY (`createdby_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dogs`
--

/*!40000 ALTER TABLE `dogs` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `dogs` VALUES
(1,'Shih Tzu','White & Brown','small','female','riendly little fluffball with a pink bow, loves belly rubs and follows people around like a shadow.','White & Brown','2025-07-01 13:02:00','uploads/mayor/animal/dog_1754136806.jpg','for_adoption',3,'2025-08-02 12:13:26','2025-08-02 12:19:18'),
(2,'Aspin','Black','medium','male','Street-smart and alert, this good boy hangs near the sari-sari store guarding like a boss.\\r\\n','Cutcot','2025-08-02 20:13:00','uploads/mayor/animal/dog_1754136830.jpg','for_adoption',3,'2025-08-02 12:13:50','2025-08-09 11:38:50'),
(3,'Golden Retriever','Golden Retriever','large','female','Gentle and super clingy, this dog looks like it escaped from a cozy home. Very chill with kids.','Inaon','2025-05-06 12:13:00','uploads/mayor/animal/dog_1754136857.jpg','for_claiming',3,'2025-08-02 12:14:17','2025-08-11 10:11:14'),
(4,'Chihuahua','Chihuahua','small','male','Feisty little guy with a loud bark. Looks like he’s compensating for something.','Lumbac','2025-08-02 20:14:00','uploads/mayor/animal/dog_1754136886.jpg','for_adoption',3,'2025-08-02 12:14:46','2025-08-02 12:19:06'),
(5,'Siberian Husky','Gray & White','large','female','Blue-eyed beauty that howls like it’s in a telenovela. Might’ve escaped an airconned home.','Paltao','2025-06-12 20:14:00','uploads/mayor/animal/dog_1754136926.jpg','for_adoption',3,'2025-08-02 12:15:26','2025-08-09 11:52:15'),
(6,'Pomeranian','Cream','small','female','Fluffy diva vibes. Keeps looking at her reflection in puddles.','Dampol 2nd-A','2025-06-18 20:15:00','uploads/mayor/animal/dog_1754136956.jpg','claimed',3,'2025-08-02 12:15:56','2025-09-03 09:04:57'),
(7,'Labrador Retriever','Chocolate','large','male','Playful big guy who tries to sit on laps like he’s still a puppy. Probably loves fetch.','Dulong Malabon','2025-08-02 20:15:00','uploads/mayor/animal/dog_1754136983.jpg','claimed',3,'2025-08-02 12:16:23','2025-09-03 09:03:39'),
(8,'Pug','Fawn with Black Mask','small','male','Snorts when he breathes, waddles when he walks—walking ball of cuteness.','Sta. Peregrina','2025-07-21 20:16:00','uploads/mayor/animal/dog_1754137009.jpg','claimed',3,'2025-08-02 12:16:49','2025-08-08 10:57:57'),
(9,'Beagle','Tri-color (Black, White, Brown)','medium','male','Nose always to the ground, follows scents like a true detective. Possibly looking for snacks.','Tabon','2025-07-29 20:16:00','uploads/mayor/animal/dog_1754137067.jpg','claimed',3,'2025-08-02 12:17:47','2025-09-03 09:04:44'),
(10,'Dachshund','Red','small','male','Low-rider sausage dog with attitude. Thinks he’s a Doberman.','Tibag','2025-05-05 20:17:00','uploads/mayor/animal/dog_1754137107.jpg','claimed',3,'2025-08-02 12:18:27','2025-08-09 10:51:57');
/*!40000 ALTER TABLE `dogs` ENABLE KEYS */;
commit;

--
-- Table structure for table `equipment_inventory`
--

DROP TABLE IF EXISTS `equipment_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_type_id` int(11) NOT NULL,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `equipment_type_id` (`equipment_type_id`),
  CONSTRAINT `equipment_inventory_ibfk_1` FOREIGN KEY (`equipment_type_id`) REFERENCES `mswd_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_inventory`
--

/*!40000 ALTER TABLE `equipment_inventory` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `equipment_inventory` VALUES
(1,9,9),
(2,10,0),
(3,11,4),
(4,12,30);
/*!40000 ALTER TABLE `equipment_inventory` ENABLE KEYS */;
commit;

--
-- Table structure for table `family_relations`
--

DROP TABLE IF EXISTS `family_relations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `family_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `english_term` varchar(50) NOT NULL,
  `filipino_term` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `family_relations`
--

/*!40000 ALTER TABLE `family_relations` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `family_relations` VALUES
(1,'Self','Ikaw'),
(2,'Parent','Magulang'),
(3,'Child','Anak'),
(4,'Sibling','Kapatid'),
(5,'Spouse','Asawa'),
(6,'Pibling','Tito/Tita'),
(7,'Nibbling','Pamangkin'),
(8,'Grandparent','Lolo/Lola'),
(9,'Grandchild','Apo');
/*!40000 ALTER TABLE `family_relations` ENABLE KEYS */;
commit;

--
-- Table structure for table `inquiries`
--

DROP TABLE IF EXISTS `inquiries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text DEFAULT NULL,
  `status` enum('pending','answered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `answeredby_admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inquiries`
--

/*!40000 ALTER TABLE `inquiries` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `inquiries` ENABLE KEYS */;
commit;

--
-- Table structure for table `mswd_requests`
--

DROP TABLE IF EXISTS `mswd_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mswd_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `assistance_id` int(11) NOT NULL,
  `assistance_name` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `requirement_path_1` varchar(255) DEFAULT NULL,
  `requirement_path_2` varchar(255) DEFAULT NULL,
  `requirement_path_3` varchar(255) DEFAULT NULL,
  `requirement_path_4` varchar(255) DEFAULT NULL,
  `requirement_path_5` varchar(255) DEFAULT NULL,
  `requirement_path_6` varchar(255) DEFAULT NULL,
  `requirement_path_7` varchar(255) DEFAULT NULL,
  `requirement_path_8` varchar(255) DEFAULT NULL,
  `complete_address` text NOT NULL,
  `relation_id` int(11) DEFAULT NULL,
  `precint_number` varchar(10) DEFAULT NULL,
  `status` enum('pending','mayor_approved','mswd_approved','completed','declined','cancelled') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `queue_date` date DEFAULT NULL,
  `queue_no` varchar(20) DEFAULT NULL,
  `released_date` date DEFAULT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `relation_to_recipient` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `reschedule_count` int(11) DEFAULT 0,
  `is_walkin` tinyint(1) NOT NULL DEFAULT 0,
  `walkin_admin_id` int(11) DEFAULT NULL,
  `approvedby_admin_id` int(11) DEFAULT NULL COMMENT 'MSWD approver',
  `approved2by_admin_id` int(11) DEFAULT NULL COMMENT 'Mayor approver',
  `completedby_admin_id` int(11) DEFAULT NULL,
  `declinedby_admin_id` int(11) DEFAULT NULL,
  `rescheduledby_admin_id` int(11) DEFAULT NULL,
  `cancelledby_admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `assistance_id` (`assistance_id`),
  KEY `fk_mswd_requests_barangay` (`barangay_id`),
  KEY `fk_mswd_requests_relation` (`relation_id`),
  CONSTRAINT `fk_mswd_requests_assistance` FOREIGN KEY (`assistance_id`) REFERENCES `mswd_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mswd_requests_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  CONSTRAINT `fk_mswd_requests_relation` FOREIGN KEY (`relation_id`) REFERENCES `family_relations` (`id`),
  CONSTRAINT `fk_mswd_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mswd_requests`
--

/*!40000 ALTER TABLE `mswd_requests` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `mswd_requests` ENABLE KEYS */;
commit;

--
-- Table structure for table `mswd_types`
--

DROP TABLE IF EXISTS `mswd_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mswd_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=walkin, 1=online',
  PRIMARY KEY (`id`),
  KEY `fk_mswd_types_parent` (`parent_id`),
  CONSTRAINT `fk_mswd_types_parent` FOREIGN KEY (`parent_id`) REFERENCES `mswd_types` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mswd_types`
--

/*!40000 ALTER TABLE `mswd_types` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `mswd_types` VALUES
(1,'Medical Assistance',NULL,1),
(2,'Financial Assistance',NULL,1),
(3,'Medicine',1,1),
(4,'Medical Procedures: Laboratory Tests, Diagnostic Procedures',1,1),
(5,'Hospital Bill',1,1),
(6,'Educational Assistance',2,1),
(7,'Burial Assistance',2,1),
(8,'Equipment',NULL,1),
(9,'Glucometer',8,1),
(10,'Nebulizer',8,1),
(11,'Wheelchair',8,1),
(12,'Tungkod/Saklay',8,1),
(13,'ID',NULL,1),
(14,'PWD ID',13,1),
(15,'Senior ID',13,1),
(16,'Others',NULL,1),
(17,'Certificate of Indigency',NULL,0),
(18,'SEA',NULL,0),
(19,'Solo Parent ID',NULL,0),
(20,'Code 1 - Birth of a Child as a Consequence of Rape (falling under Section 4(a)(1) of the Solo Parent Act)',19,0),
(21,'Code 2 - Widow/Widower',19,0),
(22,'Code 3 - Imprisonment of the Spouse',19,0),
(23,'Code 4 - Physical or Mental Disability of Spouse (PWD)',19,0),
(24,'Code 5 - Due to De Facto Separation',19,0),
(25,'Code 6 - Due to Nullity of Marriage',19,0),
(26,'Code 7 - Abandoned',19,0),
(27,'Code 8 - Spouse of the OFW',19,0),
(28,'Code 9 - Relative of the OFW',19,0),
(29,'Code 10 - Unmarried mother or father who keeps and rears his/her child or children',19,0),
(30,'Code 11 - Legal guardian, adoptive or foster parent who solely provides parental care and support to a child or children',19,0),
(31,'Code 12 - Any relative within the fourth (4th) civil degree of consanguinity or affinity',19,0),
(32,'Code 13 - A pregnant woman who provides sole parental care and support to her unborn child or children',19,0),
(33,'Sulong Dulong (Every Month)',2,1),
(34,'Sulong Dulong (Per Sem)',2,1);
/*!40000 ALTER TABLE `mswd_types` ENABLE KEYS */;
commit;

--
-- Table structure for table `mswd_types_requirements`
--

DROP TABLE IF EXISTS `mswd_types_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mswd_types_requirements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `mswd_types_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mswd_types_requirements_type` (`mswd_types_id`),
  CONSTRAINT `fk_mswd_types_requirements_type` FOREIGN KEY (`mswd_types_id`) REFERENCES `mswd_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=322 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mswd_types_requirements`
--

/*!40000 ALTER TABLE `mswd_types_requirements` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `mswd_types_requirements` VALUES
(1,'Barangay Indigency',3),
(2,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',3),
(3,'Valid ID',3),
(5,'Reseta ng gamot (up to date o latest, may presyo mula sa botika)',3),
(6,'Barangay Indigency',4),
(7,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',4),
(8,'Valid ID',4),
(10,'Quotation',4),
(11,'Medical/Clinical Abstract',4),
(12,'Barangay Indigency',5),
(13,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',5),
(14,'Valid ID',5),
(16,'Hospital Bill (final bill)',5),
(17,'Promissory note',5),
(25,'Barangay Indigency',6),
(26,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',6),
(28,'Student ID',6),
(29,'Certificate of Enrollment',6),
(30,'Statement of Account',6),
(31,'Barangay Indigency',7),
(32,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',7),
(35,'Death Certificate',7),
(36,'Funeral contract of service',7),
(37,'Promissory note',7),
(38,'Barangay Indigency',9),
(39,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',9),
(40,'Valid ID',9),
(42,'Picture ng pasyente',9),
(43,'Medical certificate',9),
(44,'Barangay Indigency',10),
(45,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',10),
(46,'Valid ID',10),
(48,'Picture ng pasyente',10),
(49,'Medical certificate',10),
(50,'Barangay Indigency',11),
(51,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',11),
(52,'Valid ID',11),
(54,'Picture ng pasyente',11),
(55,'Medical certificate',11),
(58,'Barangay Indigency',12),
(59,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',12),
(60,'Valid ID',12),
(62,'Picture ng pasyente',12),
(63,'Medical certificate',12),
(84,'Barangay Indigency',16),
(85,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',16),
(86,'Valid ID',16),
(88,'Litrato ng nag papatunay (e.g. Picture ng ticket ng bus)',16),
(89,'Barangay Indigency',14),
(90,'Valid ID or Birth Certificate',14),
(92,'Barangay Indigency',15),
(93,'Valid ID or Birth Certificate',15),
(140,'Need seminar',20),
(141,'Certificate of attendance',20),
(142,'Need verify by the solo parent president of the barangay',20),
(143,'(1) Birth certificates of the child or children',20),
(144,'(2) Complaint affidavit',20),
(145,'(3) Medical record on the incident of rape',20),
(146,'(4) Sworn affidavit declaring that the solo parent has the sole parental care and support of the child or children at the time of the execution of affidavit: Provided, That for purposes of issuance of subsequent SPIC and booklet, only the sworn affidavit shall be submitted every year',20),
(147,'(5) Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',20),
(149,'Need seminar',21),
(150,'Certificate of attendance',21),
(151,'Need verify by the solo parent president of the barangay',21),
(152,'(1) Birth certificates of the child or children',21),
(153,'(2) Marriage certificate',21),
(154,'(3) Death certificate of the spouse',21),
(155,'(4) Sworn affidavit declaring that the solo parent is not cohabiting with a partner or co-parent, and has the sole parental care and support of the child or children: Provided, That for purposes of issuance of subsequent SPIC and booklet, only the sworn affidavit shall be submitted every year',21),
(156,'(5) Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',21),
(158,'Need seminar',22),
(159,'Certificate of attendance',22),
(160,'Need verify by the solo parent president of the barangay',22),
(161,'(1) Birth certificate/s of the child or children',22),
(162,'(2) Marriage certificate',22),
(163,'(3) Certificate of detention or a certification that the spouse is serving sentence for at least three (3) months issued by the law-enforcement agency having actual custody of the detained spouse or commitment order by the court pursuant to a conviction of the spouse',22),
(164,'(4) Sworn affidavit declaring that the solo parent is not cohabiting with a partner or co-parent, and has sole parental care and support of the child or children: Provided, That for purposes of issuance of subsequent SPIC and booklet, requirement numbers (3) and (4) under this paragraph shall be submitted every year',22),
(165,'(5) Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',22),
(176,'Need seminar',23),
(177,'Certificate of attendance',23),
(178,'Need verify by the solo parent president of the barangay',23),
(179,'(1) Birth certificate/s of the child or children',23),
(180,'(2) Marriage certificate or affidavit of cohabitation',23),
(181,'(3) Medical records, medical abstract, or a certificate of confinement in the National Center for Mental Health or any medical hospital or facility as a result of the spouse\'s physical or mental incapacity, which record, medical abstract or certificate of confinement of the incapacitated spouse should have been issued not more than three (3) months before the submission, or a valid Person With Disability ID Issued pursuant to Republic Act No 10754 and Republic Act No. 7277, or the Magna Carta for Disabled Persons',23),
(182,'(4) Sworn affidavit that the solo parent is not cohabiting with a partner or co-parent and has sole parental care and support of the child or children: Provided, That for purposes of issuance of subsequent SPIC and booklet, requirement numbers (3) and (4) under this paragraph shall be submitted every year',23),
(183,'(5) Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',23),
(185,'Need seminar',24),
(186,'Certificate of attendance',24),
(187,'Need verify by the solo parent president of the barangay',24),
(188,'(1) Birth certificate/s of the child or children',24),
(189,'(2) Marriage certificate',24),
(190,'(3) Judicial decree of legal separation of the spouses or, in the case of de facto separation, an affidavit of two (2) disinterested persons attesting to the fact of separation of the spouses',24),
(191,'(4) Sworn affidavit declaring that the solo parent is not cohabiting with a partner or co-parent, and has sole parental care and support of the child or children: Provided, That for purposes of issuance of subsequent SPIC and booklet, requirement numbers (3) and (4) under this paragraph shall be submitted every year',24),
(192,'(5) Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',24),
(194,'Need seminar',25),
(195,'Certificate of attendance',25),
(196,'Need verify by the solo parent president of the barangay',25),
(197,'(1) Birth certificate/s of the child or children',25),
(198,'(2) Marriage certificate, annotated with the fact of declaration of nullity of marriage or annulment of marriage',25),
(199,'(3) Judicial decree of nullity or annulment of marriage or Judicial recognition of foreign divorce',25),
(200,'(4) Sworn affidavit declaring that the solo parent is not cohabiting with a partner or co-parent and has sole parental care and support of the child or children: Provided, That for purposes of Issuance of subsequent SPIC and booklet, only the sworn affidavit shall be submitted every year',25),
(201,'(5) Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',25),
(203,'Need seminar',26),
(204,'Certificate of attendance',26),
(205,'Need verify by the solo parent president of the barangay',26),
(206,'(1) Birth certificate/s of the child or children',26),
(207,'(2) Marriage certificate or affidavit of the applicant solo parent',26),
(208,'(3) Affidavit of two(2)disinterested persons attesting to the fact of abandonment of the spouse',26),
(209,'(4) Police or barangay record of the fact of abandonment',26),
(210,'(5) Sworn affidavit declaring that the solo parent is not cohabiting with a partner or co-parent, and has sole parental care and support of the child or children: Provided, That for purposes of issuance of subsequent SPIC and booklet, only sworn affidavit shall be submitted every year',26),
(211,'(6) Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',26),
(213,'Need seminar',27),
(214,'Certificate of attendance',27),
(215,'Need verify by solo parent president of barangay',27),
(216,'Birth certificate/s of dependents',27),
(217,'Marriage certificate, if the applicant is the spouse of the OFW, or birth certificate or the other competent proof of the relationship between the applicant and the OFW, if the applicant is a family member of the OFW',27),
(218,'Philippine Overseas Employment Administration Standard Employment Contract (POEA-SEC) or its equivalent document',27),
(219,'Photocopy of the OFW\'s passport with stamps showing continuous twelve (12) months of overseas work, or a certification from Bureau of Immigration',27),
(220,'Proof of OFW\'s spouse or family member',27),
(221,'Sworn affidavit declaring that the solo parent has sole parental care and support of that child or children: Provided, That for the purposes of issuance of subsequent SPIC and booklet, requirement numbers (3), (4), (5) and (6) under this paragraph shall be submitted every year',27),
(222,'Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',27),
(224,'Need seminar',28),
(225,'Certificate of attendance',28),
(226,'Need verify by solo parent president of barangay',28),
(227,'Birth certificate/s of dependents',28),
(228,'Marriage certificate, if the applicant is the spouse of the OFW, or birth certificate or the other competent proof of the relationship between the applicant and the OFW, if the applicant is a family member of the OFW',28),
(229,'Philippine Overseas Employment Administration Standard Employment Contract (POEA-SEC) or its equivalent document',28),
(230,'Photocopy of the OFW\'s passport with stamps showing continuous twelve (12) months of overseas work, or a certification from Bureau of Immigration',28),
(231,'Proof of OFW\'s spouse or family member',28),
(232,'Sworn affidavit declaring that the solo parent has sole parental care and support of that child or children: Provided, That for the purposes of issuance of subsequent SPIC and booklet, requirement numbers (3), (4), (5) and (6) under this paragraph shall be submitted every year',28),
(233,'Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',28),
(235,'Need seminar',29),
(236,'Certificate of attendance',29),
(237,'Need verify by the solo parent president of the barangay',29),
(238,'Birth certificate/s of the child or children',29),
(239,'Certificate of No Marriage (CENOMAR)',29),
(240,'Sworn affidavit declaring that the solo parent has sole parental care and support of that child or children: Provided, That for the purposes of issuance of subsequent SPIC and booklet, requirement numbers (3), (4), (5) and (6) under this paragraph shall be submitted every year',29),
(241,'Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',29),
(243,'Need seminar',30),
(244,'Certificate of attendance',30),
(245,'Need verify by the solo parent president of the barangay',30),
(246,'Birth certificate/s of the child or children',30),
(247,'Proof of guardianship issued by a court; proof of adaption, such as the decree of adoption issued by a court, or order of Adaption Issued by the DSWD or the National Authority on Child Care (NACC); proof of foster care such as the Foster Parent License issued by the DSWD or NACC',30),
(249,'Need seminar',31),
(250,'Certificate of attendance',31),
(251,'Need verify by the solo parent president of the barangay',31),
(252,'Birth certificate/s of dependents',31),
(253,'Death certificate, certificates of incapacity, or judicial declaration of absence or presumptive death of the parents or legal guardians: police or barangay records evidencing the fact of disappearance or absence of the parent or legal guardian for at least (6) months',31),
(254,'Proof of relationship of the relative to the parents or legal guardian, such as birth certificate, marriage certificate, family records, or other similar analogous proof of relationship',31),
(255,'Sworn affidavit declaring that the solo parent has sole parental care and support of that child or children: Provided, That for the purposes of issuance of subsequent SPIC and booklet, requirement numbers (3) and (4) under this paragraph shall be submitted every year',31),
(256,'Affidavit of a barangay official attesting that the solo parent is a resident of the barangay and that the child or children is/are under the parental care and support of the solo parent',31),
(258,'Need seminar',32),
(259,'Certificate of attendance',32),
(260,'Need verify by the solo parent president of the barangay',32),
(261,'Birth certificate/s of dependents',32),
(262,'Medical record of her pregnancy',32),
(263,'Affidavit of barangay official attesting that the solo parent is a resident of the barangay',32),
(264,'Sworn affidavit that the solo parent is not cohabiting with a partner or co-parent who is providing support to the pregnant woman',32),
(265,'Barangay Indigency (Client)',18),
(266,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',18),
(267,'Photocopy of Valid ID',18),
(268,'Sketch ng location ng bahay',18),
(269,'Lists ng mga paninda (w/price &total)',18),
(270,'Barangay Indigency (Client)',17),
(272,'Valid ID',17),
(278,'Picture ng tindahan at vendor',18),
(279,'Medical Certificate',14),
(280,'Centenarians',15),
(281,'Barangay Indigency',33),
(282,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',33),
(283,'Parent ID',33),
(284,'Certificate of Enrollment',33),
(285,'Barangay Indigency',34),
(286,'Sulat Kahilingan (Ilahad ang kahilingan na tulong na idudulog kay Mayor)',34),
(287,'Parent ID',34),
(288,'Certificate of Enrollment',34);
/*!40000 ALTER TABLE `mswd_types_requirements` ENABLE KEYS */;
commit;

--
-- Table structure for table `pwd_birthday_members`
--

DROP TABLE IF EXISTS `pwd_birthday_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pwd_birthday_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date NOT NULL,
  `barangay_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `barangay_id` (`barangay_id`),
  KEY `birthday` (`birthday`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pwd_birthday_members`
--

/*!40000 ALTER TABLE `pwd_birthday_members` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `pwd_birthday_members` VALUES
(2,'Maria','Santos','Reyes','2005-07-12',2),
(3,'Pedro','Lopez','Garcia','2003-11-20',3),
(4,'Ana','Martinez','Torres','2004-01-15',4),
(5,'Ramon','Diaz','Navarro','2005-05-05',5),
(6,'Luz','Ortega','Velasco','2004-12-09',6),
(7,'Carlos','Ramos','Pineda','2003-09-17',7),
(8,'Elena','Torres','Santos','2004-06-23',8),
(9,'Miguel','Reyes','Lopez','2005-08-22',9),
(10,'Isabel','Garcia','Diaz','2004-04-14',10),
(11,'Jose','Cabrera','Mendoza','2004-02-18',11),
(12,'Rosa','Lopez','Santos','2005-03-22',12),
(13,'Antonio','Delgado','Reyes','2003-07-29',13),
(14,'Patricia','Garcia','Torres','2004-05-19',14),
(16,'Carmen','Ramos','Navarro','2004-08-06',16),
(17,'Miguel','Ortega','Velasco','2003-12-25',17),
(18,'Gloria','Santos','Pineda','2004-10-03',18),
(19,'Francisco','Lopez','Torres','2005-01-28',19),
(20,'das','dsa','dsa','2003-03-03',2),
(21,'Juan','Dela','Cruz','2004-08-21',3),
(22,'Maria','Santos','Reyes','2005-07-12',2),
(23,'Pedro','Lopez','Garcia','2003-11-20',3),
(24,'Ana','Martinez','Torres','2004-01-15',4),
(25,'Ramon','Diaz','Navarro','2005-05-05',5),
(26,'Luz','Ortega','Velasco','2004-12-09',6),
(28,'Elena','Torres','Santos','2004-06-23',8),
(29,'Miguel','Reyes','Lopez','2005-08-30',9),
(30,'Isabel','Garcia','Diaz','2004-04-14',10),
(31,'Jose','Cabrera','Mendoza','2004-02-18',11),
(32,'Rosa','Lopez','Santos','2005-03-22',12),
(33,'Antonio','Delgado','Reyes','2003-07-29',13),
(34,'Patricia','Garcia','Torres','2004-05-19',14),
(35,'Luis','Martinez','Diaz','2005-09-11',15),
(36,'Carmen','Ramos','Navarro','2004-08-06',16),
(37,'Miguel','Ortega','Velasco','2003-12-25',17),
(38,'Gloria','Santos','Pineda','2004-10-03',18),
(39,'Francisco','Lopez','Torres','2005-01-28',19),
(40,'First Name','Middle Name','Last Name','2002-02-02',2),
(41,'First Name','First Name','Last Name','2003-08-20',3),
(42,'ads','ad','das','2003-03-03',1),
(43,'Namea','Name','Name','2001-08-20',3);
/*!40000 ALTER TABLE `pwd_birthday_members` ENABLE KEYS */;
commit;

--
-- Table structure for table `rabid_reports`
--

DROP TABLE IF EXISTS `rabid_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rabid_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `birthday` date NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `complete_address` text NOT NULL,
  `location` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `description` text NOT NULL,
  `proof_path` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','false_report','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `verifiedby_admin_id` int(11) DEFAULT NULL,
  `cancelledby_admin_id` int(11) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `verified_by` (`verifiedby_admin_id`),
  KEY `rabid_reports_ibfk_3` (`cancelledby_admin_id`),
  CONSTRAINT `rabid_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rabid_reports_ibfk_2` FOREIGN KEY (`verifiedby_admin_id`) REFERENCES `admins` (`id`),
  CONSTRAINT `rabid_reports_ibfk_3` FOREIGN KEY (`cancelledby_admin_id`) REFERENCES `admins` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rabid_reports`
--

/*!40000 ALTER TABLE `rabid_reports` DISABLE KEYS */;
set autocommit=0;
/*!40000 ALTER TABLE `rabid_reports` ENABLE KEYS */;
commit;

--
-- Table structure for table `sulong_dulong_beneficiaries`
--

DROP TABLE IF EXISTS `sulong_dulong_beneficiaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sulong_dulong_beneficiaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthday` date NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `duration` enum('Every Month','Per Sem') NOT NULL,
  `status` enum('Active','Blocked') NOT NULL DEFAULT 'Active',
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `barangay_id` (`barangay_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sulong_dulong_beneficiaries`
--

/*!40000 ALTER TABLE `sulong_dulong_beneficiaries` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `sulong_dulong_beneficiaries` VALUES
(27,'Name','','Name','2000-01-01',18,'Per Sem','Active',NULL),
(28,'2222222222','','2222222222','2003-03-03',18,'Every Month','Blocked','ads'),
(29,'asddd','','asddd','1985-11-22',7,'Every Month','Active',NULL),
(30,'2222222222','','2222222222','2003-03-03',12,'Per Sem','Blocked',NULL),
(31,'First Name','First Name','First Name','2003-03-03',18,'Every Month','Active',NULL),
(32,'Juan','','Dela Cruz','1999-12-12',3,'Per Sem','Blocked',NULL),
(33,'Ana','','Cruz','1999-12-12',1,'Every Month','Blocked',NULL),
(37,'Ana','','Cruz1','1999-12-12',1,'Every Month','Active',NULL),
(38,'das','asd','das','2003-02-02',3,'Every Month','Active',NULL),
(39,'a','aa','aa','2001-01-01',6,'Per Sem','Active',NULL);
/*!40000 ALTER TABLE `sulong_dulong_beneficiaries` ENABLE KEYS */;
commit;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(13) NOT NULL COMMENT 'Format: +639XXXXXXXXX',
  `name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `birthday` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `barangay_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `createdby_admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
set autocommit=0;
INSERT INTO `users` VALUES
(1,'+639944853547','Aries','','Bautista','2004-03-01','Dyan lang','$2y$12$XDkzsp6N16CGMbegWwjR/.H1OCibMeWkmgvUpBPSUp1gVT1mB4/Ym','$2y$12$6NvsRyga6kvyMYzMLhHbr.MmRcUgZp/SlUJ1fHIzD4iKQ5q/AfyVu','2025-09-02 11:02:13',1,'2025-08-02 19:40:13',19,35,27);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
commit;

--
-- Dumping routines for database 'municihelp'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-09-03 18:53:09
