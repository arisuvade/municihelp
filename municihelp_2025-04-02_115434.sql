-- MySQL dump 10.13  Distrib 8.0.33, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: municihelp
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `password` varchar(255) NOT NULL,
  `section` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `phone` varchar(13) NOT NULL COMMENT 'Format: +639XXXXXXXXX',
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'a','Assistance','2025-03-28 02:35:24','+639999999991'),(2,'r','Request','2025-03-31 13:37:52','+639999999992');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;

--
-- Table structure for table `assistance_requests`
--

DROP TABLE IF EXISTS `assistance_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assistance_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `assistance_id` int(11) NOT NULL,
  `specific_request_path` varchar(255) NOT NULL,
  `indigency_cert_path` varchar(255) NOT NULL,
  `id_copy_path` varchar(255) NOT NULL,
  `request_letter_path` varchar(255) NOT NULL,
  `complete_address` text NOT NULL,
  `status` enum('pending','approved','completed','declined','cancelled') NOT NULL DEFAULT 'pending',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `queue_number` int(11) DEFAULT NULL,
  `queue_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `assistance_id` (`assistance_id`),
  KEY `fk_assistance_requests_barangay` (`barangay_id`),
  CONSTRAINT `assistance_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assistance_requests_ibfk_2` FOREIGN KEY (`assistance_id`) REFERENCES `assistance_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assistance_requests_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assistance_requests`
--

/*!40000 ALTER TABLE `assistance_requests` DISABLE KEYS */;
INSERT INTO `assistance_requests` VALUES (76,14,5,'Juan','Dela','Cruz',1,'/uploads/gluco1.pdf','/uploads/indigency1.pdf','/uploads/id1.pdf','/uploads/letter1.pdf','123 Main Street, Balatong B','completed',NULL,'2025-03-05 01:15:22','2025-04-01 15:56:00',17,'2025-04-01'),(77,14,8,'Maria','Santos','Reyes',1,'/uploads/gluco2.pdf','/uploads/indigency2.pdf','/uploads/id2.pdf','/uploads/letter2.pdf','456 Oak Avenue, Inaon','pending',NULL,'2025-03-10 06:30:45',NULL,NULL,NULL),(78,14,12,'Pedro','Gonzales','Bautista',1,'/uploads/gluco3.pdf','/uploads/indigency3.pdf','/uploads/id3.pdf','/uploads/letter3.pdf','789 Pine Road, Penabatan','declined',NULL,'2025-03-15 03:45:10','2025-03-25 00:05:33',NULL,NULL),(79,14,3,'Ana','Lopez','Mendoza',1,'/uploads/gluco4.pdf','/uploads/indigency4.pdf','/uploads/id4.pdf','/uploads/letter4.pdf','321 Elm Street, Cutcot','approved',NULL,'2025-03-20 08:20:33','2025-03-25 00:05:33',7,'2025-04-01'),(80,14,15,'Luis','Tan','Sy',1,'/uploads/gluco5.pdf','/uploads/indigency5.pdf','/uploads/id5.pdf','/uploads/letter5.pdf','654 Maple Lane, Santo Cristo','completed',NULL,'2025-03-25 02:10:18','2025-04-01 10:50:39',22,'2025-04-01'),(81,14,7,'Sofia','Lim','Ong',2,'/uploads/nebulizer1.pdf','/uploads/indigency6.pdf','/uploads/id6.pdf','/uploads/letter6.pdf','111 Orchid St, Dulong Malabon','completed','','2025-03-06 00:12:33','2025-04-02 03:17:37',NULL,NULL),(82,14,9,'Carlos','Reyes','Dizon',2,'/uploads/nebulizer2.pdf','/uploads/indigency7.pdf','/uploads/id7.pdf','/uploads/letter7.pdf','222 Bamboo Ave, Longos','completed','','2025-03-11 05:45:21','2025-04-02 03:47:50',NULL,NULL),(83,14,4,'Elena','Martinez','Garcia',2,'/uploads/nebulizer3.pdf','/uploads/indigency8.pdf','/uploads/id8.pdf','/uploads/letter8.pdf','333 Cedar Rd, Dampol 1st','cancelled',NULL,'2025-03-16 02:30:15',NULL,NULL,NULL),(84,14,11,'Miguel','Sanchez','Torres',2,'/uploads/nebulizer4.pdf','/uploads/indigency9.pdf','/uploads/id9.pdf','/uploads/letter9.pdf','444 Palm Dr, Paltao','declined','Ewan','2025-03-21 07:22:44','2025-04-02 03:35:37',NULL,NULL),(85,14,17,'Isabel','Chua','Uy',2,'/uploads/nebulizer5.pdf','/uploads/indigency10.pdf','/uploads/id10.pdf','/uploads/letter10.pdf','555 Acacia Ln, Taal','completed','Congrats po!','2025-03-26 01:05:37','2025-04-02 03:51:07',NULL,NULL),(86,14,2,'Ricardo','Villanueva','Lopez',3,'/uploads/burial1.pdf','/uploads/indigency11.pdf','/uploads/id11.pdf','/uploads/letter11.pdf','666 Narra St, Balatong A','approved','','2025-03-07 03:33:12','2025-04-01 15:58:50',1,'2025-04-08'),(87,14,6,'Carmen','Gutierrez','Romero',3,'/uploads/burial2.pdf','/uploads/indigency12.pdf','/uploads/id12.pdf','/uploads/letter12.pdf','777 Mahogany Ave, Dampol 2nd-B','pending',NULL,'2025-03-12 06:55:43',NULL,NULL,NULL),(88,14,10,'Fernando','Castillo','Navarro',3,'/uploads/burial3.pdf','/uploads/indigency13.pdf','/uploads/id13.pdf','/uploads/letter13.pdf','888 Yakal Rd, Lumbac','declined',NULL,'2025-03-17 08:40:28','2025-03-25 00:05:33',NULL,NULL),(89,14,13,'Beatriz','Rivera','Morales',3,'/uploads/burial4.pdf','/uploads/indigency14.pdf','/uploads/id14.pdf','/uploads/letter14.pdf','999 Molave Dr, Poblacion','completed','','2025-03-22 02:15:19','2025-04-01 16:00:22',NULL,NULL),(90,14,18,'Arturo','Dizon','Salazar',3,'/uploads/burial5.pdf','/uploads/indigency15.pdf','/uploads/id15.pdf','/uploads/letter15.pdf','1010 Ipil Ln, Tibag','completed',NULL,'2025-03-27 00:25:51','2025-04-01 15:56:08',23,'2025-04-01'),(91,14,1,'Patricia','Alvarez','Jimenez',4,'/uploads/educ1.pdf','/uploads/indigency16.pdf','/uploads/id16.pdf','/uploads/letter16.pdf','1212 Pineapple St, Balatong A','approved',NULL,'2025-03-08 01:45:37','2025-03-25 00:05:33',8,'2025-04-01'),(92,14,5,'Ramon','Bautista','Castro',4,'/uploads/educ2.pdf','/uploads/indigency17.pdf','/uploads/id17.pdf','/uploads/letter17.pdf','1313 Banana Ave, Balatong B','declined','Malabo yung mga picture.','2025-03-13 04:30:22','2025-04-01 15:58:32',NULL,NULL),(93,14,9,'Lourdes','Domingo','Esteban',4,'/uploads/educ3.pdf','/uploads/indigency18.pdf','/uploads/id18.pdf','/uploads/letter18.pdf','1414 Mango Rd, Longos','cancelled',NULL,'2025-03-18 07:20:14',NULL,NULL,NULL),(94,14,14,'Sergio','Espinoza','Fuentes',4,'/uploads/educ4.pdf','/uploads/indigency19.pdf','/uploads/id19.pdf','/uploads/letter19.pdf','1515 Guava Dr, Santa Peregrina','completed',NULL,'2025-03-23 03:10:45','2025-04-01 10:53:00',18,'2025-04-01'),(95,14,16,'Teresita','Hernandez','Iglesia',4,'/uploads/educ5.pdf','/uploads/indigency20.pdf','/uploads/id20.pdf','/uploads/letter20.pdf','1616 Papaya Ln, Tabon','approved',NULL,'2025-03-28 06:05:33','2025-03-25 00:05:33',4,'2025-04-01'),(96,14,3,'Alfredo','Imperial','Javier',5,'/uploads/lab1.pdf','/uploads/indigency21.pdf','/uploads/id21.pdf','/uploads/letter21.pdf','1717 Durian St, Cutcot','pending',NULL,'2025-03-09 02:20:18','2025-04-01 15:56:36',11,'2025-04-01'),(97,14,7,'Gloria','Kalaw','Lumbao',5,'/uploads/lab2.pdf','/uploads/indigency22.pdf','/uploads/id22.pdf','/uploads/letter22.pdf','1818 Lansones Ave, Dulong Malabon','approved','','2025-03-14 05:15:42','2025-04-01 15:59:45',2,'2025-04-08'),(98,14,11,'Benjamin','Magno','Nunez',5,'/uploads/lab3.pdf','/uploads/indigency23.pdf','/uploads/id23.pdf','/uploads/letter23.pdf','1919 Rambutan Rd, Paltao','declined',NULL,'2025-03-19 08:05:27','2025-03-25 00:05:33',NULL,NULL),(99,14,15,'Corazon','Ocampo','Panganiban',5,'/uploads/lab4.pdf','/uploads/indigency24.pdf','/uploads/id24.pdf','/uploads/letter24.pdf','2020 Santol Dr, Santo Cristo','completed',NULL,'2025-03-24 01:30:11','2025-04-01 10:50:39',20,'2025-04-01'),(100,14,19,'Daniel','Quizon','Ramos',5,'/uploads/lab5.pdf','/uploads/indigency25.pdf','/uploads/id25.pdf','/uploads/letter25.pdf','2121 Tamarind Ln, Tenejeros','approved',NULL,'2025-03-29 00:15:49','2025-03-25 00:05:33',6,'2025-04-01'),(101,14,2,'Eduardo','Soriano','Tolentino',6,'/uploads/med1.pdf','/uploads/indigency26.pdf','/uploads/id26.pdf','/uploads/letter26.pdf','2222 Ube St, Balatong A','pending',NULL,'2025-03-02 00:30:15','2025-04-01 15:56:36',12,'2025-04-01'),(102,14,6,'Felicia','Ubaldo','Valdez',6,'/uploads/med2.pdf','/uploads/indigency27.pdf','/uploads/id27.pdf','/uploads/letter27.pdf','2323 Vanilla Ave, Dampol 2nd-B','pending',NULL,'2025-03-17 03:25:33',NULL,NULL,NULL),(103,14,10,'Gregorio','Wenceslao','Xavier',6,'/uploads/med3.pdf','/uploads/indigency28.pdf','/uploads/id28.pdf','/uploads/letter28.pdf','2424 Walnut Rd, Lumbac','cancelled',NULL,'2025-03-22 06:15:47',NULL,NULL,NULL),(104,14,14,'Hilda','Yap','Zamora',6,'/uploads/med4.pdf','/uploads/indigency29.pdf','/uploads/id29.pdf','/uploads/letter29.pdf','2525 Xylophone Dr, Santa Peregrina','approved',NULL,'2025-03-27 02:05:22','2025-03-25 00:05:33',2,'2025-04-01'),(105,14,18,'Ignacio','Aquino','Beltran',6,'/uploads/med5.pdf','/uploads/indigency30.pdf','/uploads/id30.pdf','/uploads/letter30.pdf','2625 Zinnia Ln, Tibag','completed',NULL,'2025-03-31 23:50:38','2025-04-01 10:50:39',19,'2025-04-01'),(106,14,4,'Julieta','Cabrera','Dela Rosa',7,'/uploads/wheel1.pdf','/uploads/indigency31.pdf','/uploads/id31.pdf','/uploads/letter31.pdf','2727 Apple St, Dampol 1st','approved',NULL,'2025-03-04 01:40:25','2025-03-25 00:05:33',10,'2025-04-01'),(107,14,8,'Kristoffer','Evangelista','Flores',7,'/uploads/wheel2.pdf','/uploads/indigency32.pdf','/uploads/id32.pdf','/uploads/letter32.pdf','2828 Berry Ave, Inaon','approved','','2025-03-19 04:35:41','2025-04-02 03:28:40',3,'2025-04-08'),(108,14,12,'Leonora','Gutierrez','Herrera',7,'/uploads/wheel3.pdf','/uploads/indigency33.pdf','/uploads/id33.pdf','/uploads/letter33.pdf','2929 Cherry Rd, Penabatan','declined',NULL,'2025-03-24 07:25:16','2025-03-25 00:05:33',NULL,NULL),(109,14,18,'Manuel','','Javier',7,'/uploads/wheel4.pdf','/uploads/indigency34.pdf','/uploads/id34.pdf','/uploads/letter34.pdf','3030 Date Dr, Tenejeros','declined','malabo','2025-03-29 03:15:39','2025-04-02 03:25:29',NULL,NULL),(110,14,1,'Nora','Kintanar','Llamas',7,'/uploads/wheel5.pdf','/uploads/indigency35.pdf','/uploads/id35.pdf','/uploads/letter35.pdf','3131 Elderberry Ln, Balatong A','completed','','2025-04-01 00:05:44','2025-04-02 03:44:34',NULL,NULL);
/*!40000 ALTER TABLE `assistance_requests` ENABLE KEYS */;

--
-- Table structure for table `assistance_types`
--

DROP TABLE IF EXISTS `assistance_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assistance_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `specific_requirement` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assistance_types`
--

/*!40000 ALTER TABLE `assistance_types` DISABLE KEYS */;
INSERT INTO `assistance_types` VALUES (1,'Glucometer Request','Request for a glucometer device.','Glucometer request galing sa doctor'),(2,'Nebulizer Request','Request for a nebulizer device.','Nebulizer request galing sa doctor'),(3,'Burial Assistance','Financial aid for burial expenses.','Death certificate and statement of account'),(4,'Educational Assistance','Financial aid for educational expenses.','COE or statement of account'),(5,'Laboratory Assistance','Financial aid for laboratory tests.','Laboratory request'),(6,'Medical Assistance','Financial aid for medical expenses.','Reseta or hospital bill'),(7,'Wheelchair Request','Request for a wheelchair.','Whole picture ng pasyente');
/*!40000 ALTER TABLE `assistance_types` ENABLE KEYS */;

--
-- Table structure for table `barangays`
--

DROP TABLE IF EXISTS `barangays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
INSERT INTO `barangays` VALUES (1,'Balatong A'),(2,'Balatong B'),(3,'Cutcot'),(4,'Dampol 1st'),(5,'Dampol 2nd - A'),(6,'Dampol 2nd - B'),(7,'Dulong Malabon'),(8,'Inaon'),(9,'Longos'),(10,'Lumbac'),(11,'Paltao'),(12,'Penabatan'),(13,'Poblacion'),(14,'Santa Peregrina'),(15,'Santo Cristo'),(16,'Taal'),(17,'Tabon'),(18,'Tenejeros'),(19,'Tibag');
/*!40000 ALTER TABLE `barangays` ENABLE KEYS */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(13) NOT NULL COMMENT 'Format: +639XXXXXXXXX',
  `password_hash` varchar(255) NOT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `phone_2` (`phone`),
  UNIQUE KEY `phone_3` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'+639201293938','$2y$10$UBn5zyLAn20/9BnuH5Lw2uxuDwpMM/CXpvT5xcq80LBnhiUz0WX0S','$2y$10$Scc4HaMBBFBJS.WEmrXJEOrZgtBBQi/5Suux2HSv9IRN8KVg.sW6a','2025-03-29 12:27:29',1,'2025-03-27 08:43:41'),(2,'+639167089470','$2y$10$JKYoUAmDFMnAqegzsOas4O3Ehqmfo4Ou7FuSm/Vufi6BUFdcBPQyq','$2y$10$SQD6rwCyWg01tvuOfMnQcOatEwhXUSDWTWVtoj9BlT59JuoskGEUC','2025-03-28 03:27:03',1,'2025-03-27 13:34:31'),(5,'+639231565567','$2y$10$4rNCt74pPJ5PPElOoB/iNeKkgqdggpfFjKetLJu6cjvFB7MLmPu8m','$2y$10$s/PqYhFg3gte2V0MxDKOwu4UnomIgH9F1yEsepHzTsNv.rxY.iTqK','2025-03-29 12:06:46',1,'2025-03-29 11:01:46'),(6,'+639656559457','$2y$10$lF3HZPKIxAktNxKGNCHDDuO2UVGD7lhKc2lB6s6k3SrDvXBTfYFRS','$2y$10$2B/hUW2uVJXqv9PqSbIId.heGXxJzjz8jUmnzxJeiKQBPalcAGFO6','2025-03-31 06:05:21',1,'2025-03-29 12:43:44'),(13,'+639920561830','$2y$10$joXq4yQ1ouZ9oHA7ummFcOHdT5Tr6EEvRhu34LbRLP/LsgCG.LjDK','$2y$10$UmY0axe68Xqi/h1naAtSrOAu/4Udo97Iu/0uJkgsAQ8FrsGWSIUua','2025-03-31 06:14:10',1,'2025-03-29 13:15:20'),(14,'+639944853547','$2y$10$hxEjmhRGsH4jqThllyGh5uMBCMY8oW/xVyEm71u8o92xs3WxMFYqO','$2y$10$kLMiE.3md8R6Vj205gY4KOZqjEBpgqD7Aj9ds4AVSwP6Bg1r99fuS','2025-04-02 04:45:46',1,'2025-03-31 09:46:39'),(16,'+639898888888','$2y$10$pPccKoLV5COwU5THwwmgxeVzPMd8RFDEkWiVKWpsyAVrawzIpdgAq','$2y$10$cpm8abIwN9eFq311AYh3/eg.7hPsS5qQWvJSHeZ1vWqse3wbBCMou','2025-03-31 14:45:53',1,'2025-03-31 12:40:53');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

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
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-04-02 11:54:38
