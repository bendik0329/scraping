/*
 Navicat Premium Data Transfer

 Source Server         : bendik
 Source Server Type    : MySQL
 Source Server Version : 80034
 Source Host           : 89.40.72.53:3306
 Source Schema         : zillows

 Target Server Type    : MySQL
 Target Server Version : 80034
 File Encoding         : 65001

 Date: 26/08/2023 20:18:26
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for images
-- ----------------------------
DROP TABLE IF EXISTS `images`;
CREATE TABLE `images`  (
  `id` int(0) NOT NULL AUTO_INCREMENT,
  `zpid` int(0) NULL DEFAULT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL,
  `createdAt` datetime(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for properties
-- ----------------------------
DROP TABLE IF EXISTS `properties`;
CREATE TABLE `properties`  (
  `id` int(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `zpid` int(0) NULL DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `price` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `beds` int(0) NULL DEFAULT NULL,
  `baths` int(0) NULL DEFAULT NULL,
  `images` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  `createdAt` datetime(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `zpid unique`(`zpid`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
