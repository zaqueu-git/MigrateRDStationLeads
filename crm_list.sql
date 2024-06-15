SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for crm_list
-- ----------------------------
DROP TABLE IF EXISTS `crm_list`;
CREATE TABLE `crm_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `created_at_period` varchar(255) DEFAULT NULL,
  `id_user` varchar(255) DEFAULT NULL,
  `id_deal` varchar(255) DEFAULT NULL,
  `json` longtext,
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `status` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4518 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
