-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 17, 2025 at 01:23 AM
-- Server version: 5.7.23-23
-- PHP Version: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mxbttmmy_statappdep`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_super_admin` int(11) NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_deactivated` tinyint(1) DEFAULT '0',
  `is_email_verified` tinyint(1) DEFAULT '0',
  `email_verification_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_token_expiry` datetime DEFAULT NULL,
  `reset_password_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `account_status` enum('pending','active','suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `email`, `password_hash`, `is_super_admin`, `username`, `is_deactivated`, `is_email_verified`, `email_verification_token`, `email_token_expiry`, `reset_password_token`, `reset_token_expiry`, `last_login`, `account_status`, `created_at`, `updated_at`) VALUES
(2, 'fidelgmontoya2@gmail.com', '$2y$12$qxm6xowYL.l8o1/so0bud.7Ht0iRmONd4xrTyv4xrHtahf8tMHyiy', 1, 'FidelGames4U', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-08 06:57:55', '2025-04-19 19:28:09'),
(3, 'f.montoya@gmail.com', '$2y$12$3qNHt5UItr7fMUIJwe0msuJ1a/m1EQnxwuGhBEBXUwWBWXGnYAgpu', 0, 'Snoopy_too', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-08 07:45:14', '2025-04-08 07:46:54'),
(4, 'kosuke808080@gmail.com', '$2y$12$zpmq/1Y3aIaPL5ZnYFv4xejIAQaQxX.MKNqAD5bTSDCFS4Pv4Wrta', 0, 'ヒラコ', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-10 06:54:02', '2025-04-10 06:54:17'),
(5, 'fidel@montoyahome23.com', '$2y$10$5Gmh2Eu1GWDxVN9043.OAOt.mLnZZUWVwbDrIpWfQsjBh1pAKvxK6', 0, 'Dawg_Snoop', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-13 11:11:33', '2025-04-19 20:02:41'),
(6, 'cody@montoyahome.com', '$2y$12$nKC75HLZP2z96XydRF80juz8xqTmmBswWNBAydIHgiKU8IXs6F4k.', 0, 'codybaldwin', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-13 21:01:20', '2025-04-14 10:01:25'),
(7, 'richard@montoyahome.com', '$2y$12$CYnIA2T4XjKlfN8DrJrSDOnnb.lfXEQQ8Aq3x.cbe9iL6X5geoCkO', 0, 'Telf', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-14 01:43:40', '2025-04-14 10:01:05'),
(8, 'josh@montoyahome.com', '$2y$12$N0icrKYvzeuBij5zwbdqy.Xj0jnCqzA1jQIeiNSFmnD8wP1oJ07JO', 0, '&amp;lt;img src=&amp;quot;https://upload.wikimedia.org/wikipedia/en/d/dc/GroundskeeperWillie.png&amp', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-14 04:59:27', '2025-04-14 10:00:44'),
(10, 'fidel@montoyahome.com', '$2y$12$RrZ6v690mDvvfMjDq27exuU3s7DozmCbTCrtptKiRGxyCBRs45166', 0, 'Charlie_Brown', 0, 0, '2109b75f404a098a727254cce41a411c020831cd262d6d393e30931d46f872a2', '2025-04-15 09:31:30', NULL, NULL, NULL, 'pending', '2025-04-14 09:31:30', '2025-04-14 09:31:30'),
(11, 'fidelgmontoya@gmail.com', '$2y$12$tkhHqfS46e6uFLND1tW5fuZJXgiCIywcB8e98WOrlg6hpicz1vUxy', 0, 'some_guy', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-14 10:08:15', '2025-04-14 10:08:36'),
(12, 'joshnishikawa@gmail.com', '$2y$12$qRS.eOVGkQQa0XrjFOsmde8ZIWBJHmtL6vhB70uQYik.rnD8eBrf6', 0, 'groundskeeperwillie', 0, 1, NULL, NULL, NULL, NULL, NULL, 'active', '2025-04-15 21:45:14', '2025-04-15 21:45:32');

-- --------------------------------------------------------

--
-- Table structure for table `champions`
--

CREATE TABLE `champions` (
  `ID` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `champ_comments` text,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `champions`
--

INSERT INTO `champions` (`ID`, `club_id`, `member_id`, `champ_comments`, `date`) VALUES
(4, 1022, 2021, 'Bloody great!', '2025-04-14 06:00:00'),
(5, 1027, 2024, 'Whatever.', '2025-04-06 06:00:00'),
(6, 1027, 2025, 'Wonderful to have my new friend on the mantle overlooking my customers!', '2025-03-02 07:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `club_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `club_name` varchar(100) NOT NULL,
  `logo_image` varchar(255) DEFAULT NULL,
  `champ_image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`club_id`, `admin_id`, `club_name`, `logo_image`, `champ_image`, `created_at`) VALUES
(1001, 3, 'Joes Board Game Club2', NULL, 'images/trophies/trophy_1001_1743533130.PNG', '2025-04-01 15:36:25'),
(1011, 2, 'Burger Club', 'club_1011_1744125774.jpg', NULL, '2025-04-08 06:57:56'),
(1012, 2, 'Popcorn Crazy', 'club_1012_1744125795.PNG', NULL, '2025-04-08 07:01:42'),
(1013, 2, 'Tatsuno Watuknow', 'club_1013_1744125831.PNG', NULL, '2025-04-08 07:02:27'),
(1014, 3, 'Snoopy_too\'s Club', NULL, 'images/trophies/trophy_1014_1745067401.jpg', '2025-04-08 07:45:14'),
(1015, 4, 'Koro', NULL, NULL, '2025-04-10 06:54:02'),
(1016, 5, 'Classic Game Club', NULL, NULL, '2025-04-13 11:11:33'),
(1017, 6, 'The Pieces of Shit', 'club_1017_1744600469.jpg', 'images/trophies/trophy_1017_1744600559.png', '2025-04-13 21:01:20'),
(1018, 6, 'The Fucktards', NULL, NULL, '2025-04-13 21:02:35'),
(1019, 7, 'Tefler', NULL, NULL, '2025-04-14 01:43:40'),
(1020, 7, 'I Love Telf', 'club_1020_1744617462.jpg', NULL, '2025-04-14 01:46:43'),
(1021, 7, 'Telfy Telfy Tutu', NULL, NULL, '2025-04-14 01:47:01'),
(1022, 7, 'Telferingus', NULL, 'images/trophies/trophy_1022_1744617379.jpg', '2025-04-14 01:47:14'),
(1023, 8, '&lt;img src=&quot;https://upload.wikimedia.org/wikipedia/en/d/dc/GroundskeeperWillie.png&quot;&gt;\'s', NULL, NULL, '2025-04-14 04:59:27'),
(1025, 10, 'Charlie_Brown\'s Club', NULL, NULL, '2025-04-14 09:31:30'),
(1026, 11, 'some_guy\'s Club', NULL, NULL, '2025-04-14 10:08:15'),
(1027, 3, 'The Flying Dutchmen', 'club_1027_1745067216.PNG', 'images/trophies/trophy_1027_1745070452.jpg', '2025-04-15 00:02:24'),
(1028, 12, 'groundskeeperwillie\'s Club', 'club_1028_1744780252.jpg', NULL, '2025-04-15 21:45:14');

-- --------------------------------------------------------

--
-- Table structure for table `csrf_tokens`
--

CREATE TABLE `csrf_tokens` (
  `token_id` int(11) NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `csrf_tokens`
--

INSERT INTO `csrf_tokens` (`token_id`, `token`, `session_id`, `created_at`, `expires_at`) VALUES
(101, '7b1001af3ba4781a6511fcd4183a072a71e7242ef4f4c96fedc298ef680f99c0', 'ebbb17ec7873de0d753cf72f369e500e', '2025-05-02 20:04:13', '2025-05-02 22:04:13');

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `game_name` varchar(100) NOT NULL,
  `min_players` int(11) NOT NULL,
  `max_players` int(11) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`game_id`, `club_id`, `game_name`, `min_players`, `max_players`, `created_at`) VALUES
(3006, 1014, 'Monopoly', 2, 6, '2025-04-08 08:25:40'),
(3007, 1013, 'Sushi Go', 2, 8, '2025-04-08 09:25:29'),
(3008, 1014, 'Chess', 2, 2, '2025-04-10 06:45:34'),
(3009, 1015, 'トランプ', 2, 10, '2025-04-10 06:56:13'),
(3010, 1016, 'Settlers of Catan', 2, 6, '2025-04-13 11:43:03'),
(3011, 1016, 'Monopoly', 2, 8, '2025-04-13 11:43:30'),
(3012, 1017, 'Pandemic', 2, 6, '2025-04-13 21:08:08'),
(3013, 1017, 'Azul', 2, 4, '2025-04-13 21:08:19'),
(3014, 1017, 'Puerto Rico', 2, 4, '2025-04-13 21:08:37'),
(3015, 1022, 'Nuff', 1, 12, '2025-04-14 01:53:32'),
(3016, 1021, 'Naff', 2, 23, '2025-04-14 01:53:59'),
(3017, 1020, 'Noff', 3, 33, '2025-04-14 01:54:18'),
(3018, 1019, 'Niff', 4, 6, '2025-04-14 01:54:38'),
(3019, 1027, 'Wingspan', 2, 6, '2025-04-15 00:04:32'),
(3020, 1027, 'Settlers of Catan', 3, 6, '2025-04-15 00:11:22'),
(3021, 1027, 'Whist', 2, 4, '2025-04-15 00:11:57'),
(3022, 1027, 'Kred', 3, 4, '2025-04-15 00:12:09'),
(3023, 1027, 'San Juan', 2, 4, '2025-04-15 00:12:40'),
(3024, 1027, 'Power Grid', 2, 6, '2025-04-15 00:13:15'),
(3025, 1027, 'War Chest', 2, 4, '2025-04-15 00:13:33'),
(3026, 1027, 'Stranger Things', 2, 4, '2025-04-15 00:13:50'),
(3027, 1027, 'Ticket to Ride', 2, 6, '2025-04-15 00:14:33'),
(3028, 1027, 'Puerto Rico', 2, 6, '2025-04-15 00:14:47'),
(3029, 1027, 'Fluxx', 2, 6, '2025-04-15 00:15:12'),
(3030, 1027, 'Catan Cards', 2, 6, '2025-04-15 00:15:33'),
(3031, 1027, 'Burgle Bros.', 2, 4, '2025-04-15 00:15:51'),
(3033, 1027, 'Dixit', 2, 6, '2025-04-15 00:16:37'),
(3034, 1027, 'Scotland Yard', 3, 6, '2025-04-15 00:17:01'),
(3035, 1027, 'Ocean Labyrinth', 2, 6, '2025-04-15 00:17:26'),
(3036, 1027, 'Escape from Colditz', 2, 6, '2025-04-15 00:18:03'),
(3037, 1027, 'In a Pickle', 2, 6, '2025-04-15 00:18:18'),
(3038, 1027, 'Sushi Go', 2, 6, '2025-04-15 00:18:27'),
(3039, 1028, 'Power Grid', 3, 6, '2025-04-15 22:10:33'),
(3040, 1027, 'Vicious Gardens', 1, 6, '2025-05-02 20:45:55'),
(3041, 1027, 'Saboteur', 3, 10, '2025-05-03 12:01:03');

-- --------------------------------------------------------

--
-- Table structure for table `game_results`
--

CREATE TABLE `game_results` (
  `result_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `member_id` int(11) NOT NULL,
  `position` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `winner` int(11) DEFAULT NULL,
  `place_2` int(11) DEFAULT NULL,
  `place_3` int(11) DEFAULT NULL,
  `place_4` int(11) DEFAULT NULL,
  `place_5` int(11) DEFAULT NULL,
  `place_6` int(11) DEFAULT NULL,
  `place_7` int(11) DEFAULT NULL,
  `place_8` int(11) DEFAULT NULL,
  `num_players` int(11) DEFAULT NULL,
  `played_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `duration` int(11) DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `game_results`
--

INSERT INTO `game_results` (`result_id`, `game_id`, `session_id`, `member_id`, `position`, `score`, `winner`, `place_2`, `place_3`, `place_4`, `place_5`, `place_6`, `place_7`, `place_8`, `num_players`, `played_at`, `duration`, `notes`) VALUES
(26, 3006, 'game_67f534850e3356.85048327', 2005, 1, NULL, 2005, 2006, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-05 23:36:00', 130, 'First entry for testing.'),
(27, 3008, 'game_67f7bda5ac3a65.77266069', 2009, 1, NULL, 2009, 2005, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-10 21:45:00', 140, 'Tiger_6969 cheated!!!'),
(28, 3010, 'game_67fbf7eeaf21f6.45998554', 2013, 1, NULL, 2013, 2012, 2010, 2011, NULL, NULL, NULL, NULL, 4, '2025-04-12 17:43:00', 191, 'Crazy game, it was.'),
(29, 3013, 'game_67fc7e346f30f0.80700265', 2014, 1, NULL, 2014, 2015, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-14 03:16:00', 90, 'Bombastic excrement'),
(30, 3014, 'game_67fc7e9bbae0d3.16862427', 2017, 1, NULL, 2017, 2016, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-14 03:17:00', 120, 'Sloppies all around'),
(31, 3012, 'game_67fc7ef17ac3a3.59616799', 2015, 1, NULL, 2015, 2014, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-14 03:19:00', 120, 'We cut some brownies'),
(32, 3019, 'game_67fdfbebf3c2d9.65955551', 2025, 1, NULL, 2025, 2023, 2022, NULL, NULL, NULL, NULL, NULL, 3, '2024-02-11 13:24:00', 93, 'Cody was a late scratch as he had to go back the States for family matters.'),
(33, 3019, 'game_67fdfc61cc0c96.31478191', 2022, 1, NULL, 2022, 2025, 2023, 2024, NULL, NULL, NULL, NULL, 4, '2024-06-23 15:26:00', 240, 'Scout 88, Chef 84, Skull, 82, Coaster 69'),
(34, 3019, 'game_67fdfcbd5ec0e9.09635593', 2023, 1, NULL, 2023, 2025, 2024, 2022, NULL, NULL, NULL, NULL, 4, '2024-11-24 16:27:00', 240, 'Europe Expansion version. Some OP cards, interesting dynamics and less eggs played until the last round.'),
(35, 3020, 'game_67fdfd0c878c73.93151170', 2025, 1, NULL, 2025, 2023, 2022, NULL, NULL, NULL, NULL, NULL, 3, '2024-02-11 14:29:00', 120, 'Cody was a late scratch as he had to go back to the States for family matters.'),
(36, 3020, 'game_67fdfd508d0787.61204432', 2023, 1, NULL, 2023, 2025, 2022, NULL, NULL, NULL, NULL, NULL, 3, '2024-02-11 16:30:00', 120, 'Josh had the idea to play with 4 & 8 sided dice. The robber moved considerably less than in the standard game. (Cody was a late scratch as he had to go back to the States for family matters.)'),
(37, 3020, 'game_67fdfda5aa5f10.58163443', 2022, 1, NULL, 2022, 2023, 2025, 2024, NULL, NULL, NULL, NULL, 4, '2024-04-28 15:31:00', 240, 'D4+D8 / expansion'),
(38, 3021, 'game_67fdfe051404a1.77610668', 2023, 1, NULL, 2023, 2025, 2024, 2022, NULL, NULL, NULL, NULL, 4, '2024-01-03 10:33:00', 120, ''),
(39, 3021, 'game_67fdfe3e33d323.19108525', 2023, 1, NULL, 2023, 2025, 2022, NULL, NULL, NULL, NULL, NULL, 3, '2024-02-11 10:00:00', 120, 'Cody was a late scratch as he had to go back to the States for family matters. Scores: 138, 118 and 117'),
(40, 3021, 'game_67fdfe7c1c63d9.92313878', 2023, 1, NULL, 2023, 2022, 2024, 2025, NULL, NULL, NULL, NULL, 4, '2024-04-28 10:35:00', 60, '1st 36 2nd 35 3rd 32 4th 26'),
(41, 3021, 'game_67fdfecadf81f4.62909161', 2024, 1, NULL, 2024, 2023, 2022, 2025, NULL, NULL, NULL, NULL, 4, '2024-05-26 10:36:00', 90, 'We talked about “rants,” Josh had a car accident story, yesterday was Peron in Aioi.'),
(42, 3021, 'game_67fdff071fba64.59520948', 2023, 1, NULL, 2023, 2024, 2025, 2022, NULL, NULL, NULL, NULL, 4, '2024-06-23 10:38:00', 50, '7 rounds. Theme: Sex BGM:Prince - The Gold Experience'),
(43, 3021, 'game_67fdff405e3ee4.50006718', 2024, 1, NULL, 2024, 2023, 2022, 2025, NULL, NULL, NULL, NULL, 4, '2024-07-14 10:39:00', 90, 'Coaster sweeps! Barely won by one point after 3 overtime’s from ties with Telf!'),
(44, 3021, 'game_67fdff85164688.76660026', 2025, 1, NULL, 2025, 2022, 2023, 2024, NULL, NULL, NULL, NULL, 4, '2024-10-06 10:40:00', 90, 'The sleepless guys lost as expected.'),
(45, 3021, 'game_67fdffcbeb4b98.31287335', 2025, 1, NULL, 2025, 2023, 2024, 2022, NULL, NULL, NULL, NULL, 4, '2024-11-24 10:00:00', 45, 'Josh had math issues again. Richard made an analogy between this game and golf. Lol.'),
(46, 3021, 'game_67fe000c309110.67276229', 2024, 1, NULL, 2024, 2023, 2022, 2025, NULL, NULL, NULL, NULL, 4, '2024-12-29 10:00:00', 60, 'Extra points for calling the trump card. Cody got the nod via base rules.'),
(47, 3021, 'game_67fe005c406b57.85477031', 2023, 1, NULL, 2023, 2022, 2025, 2024, NULL, NULL, NULL, NULL, 4, '2025-01-26 10:00:00', 60, '8 rounds. For fun we had, but did not use, bonus points.'),
(48, 3021, 'game_67fe009e5a7ea4.34345541', 2025, 1, NULL, 2025, 2022, 2023, NULL, NULL, NULL, NULL, NULL, 3, '2025-02-09 10:00:00', 60, ' ********* Cody absence *********'),
(49, 3021, 'game_67fe00edc29651.91951265', 2023, 1, NULL, 2023, 2022, 2024, 2025, NULL, NULL, NULL, NULL, 4, '2025-03-02 10:00:00', 60, 'We ditched the bonus rule.'),
(50, 3021, 'game_67fe0123749034.15981630', 2024, 1, NULL, 2024, 2023, 2022, 2025, NULL, NULL, NULL, NULL, 4, '2025-04-06 10:00:00', 65, '“I’m not fucking interested in coming second” – Skull'),
(51, 3023, 'game_67fe026179ed63.31157212', 2024, 1, NULL, 2024, 2025, 2023, 2022, NULL, NULL, NULL, NULL, 4, '2024-01-03 15:52:00', 90, 'Chef 35, Coaster 41, Sniff 35, Scout 34'),
(52, 3023, 'game_67fe029f5f28c3.24180463', 2024, 1, NULL, 2024, 2023, 2022, 2025, NULL, NULL, NULL, NULL, 4, '2024-04-28 15:53:00', 180, 'Cody murdered it, 51 top score to two at 40 for second place. Richard says he carried Josh, so he gets second place. Taxes won it for sure, Fidel said, “That’s a cheat,” when Cody played the Customs House card.'),
(53, 3024, 'game_67fe02fdb0dbd7.67554165', 2023, 1, NULL, 2023, 2024, 2022, 2025, NULL, NULL, NULL, NULL, 4, '2024-01-03 00:00:00', 120, 'Expansion: South Africa // Not sure of the 2nd thru 4th place players but Richard definitely won this game.'),
(54, 3024, 'game_67fe0380e843f7.66669246', 2022, 1, NULL, 2022, 2023, 2025, 2024, NULL, NULL, NULL, NULL, 4, '2024-05-26 16:56:00', 300, 'Expansion: Middle East // 90 minutes overtime.'),
(55, 3024, 'game_67fe03d5356ce6.81774736', 2024, 1, NULL, 2024, 2023, 2025, 2022, NULL, NULL, NULL, NULL, 4, '2024-07-04 15:58:00', 165, 'Expansion: Central Europe // Anticlimactic, was kinda in the lead the whole time. Fidel went for Wein, with the cheap trash strategy.'),
(56, 3024, 'game_67fe0417d64bc1.75835770', 2024, 1, NULL, 2024, 2022, 2023, 2025, NULL, NULL, NULL, NULL, 4, '2024-12-29 00:00:00', 240, 'Expansion: Italy // A $140 bid on a $38 card. Won by Richard.'),
(57, 3025, 'game_67fe05c0eb8ea9.20061900', 2022, 1, NULL, 2022, 2023, 2025, NULL, NULL, NULL, NULL, NULL, 3, '2025-02-09 16:00:00', 240, ''),
(58, 3027, 'game_67fe073ea32765.38936176', 2025, 1, NULL, 2025, 2024, 2022, 2023, NULL, NULL, NULL, NULL, 4, '2024-06-23 16:00:00', 120, 'Version: U.S. //  Fidel 119, Cody 118, Josh 117, Richard 91'),
(59, 3028, 'game_67fe078aefea50.87007324', 2024, 1, NULL, 2024, 2025, 2022, 2023, NULL, NULL, NULL, NULL, 4, '2024-07-14 16:14:00', 240, 'Colonoscopy victorious! Don’t forget to change the settlers plantations after search settler turn. Don’t forget to load the settlers ship properly. Very close! Skull: 62 Chef: 73 (56 were VP) Scout: 66 Coaster: 75'),
(60, 3028, 'game_67fe07b62a4dd9.86856122', 2022, 1, NULL, 2022, 2024, 2023, 2025, NULL, NULL, NULL, NULL, 4, '2025-01-26 16:15:00', 180, ''),
(61, 3020, 'game_67fe081c2e20f1.72813708', 2024, 1, NULL, 2024, 2022, 2023, 2025, NULL, NULL, NULL, NULL, 4, '2024-07-14 16:16:00', 30, 'Version: Real-Time Catan // First time playing. Fidel got cut off, unfortunately, so maybe stick with the expansion with four players or use a map with no desert.'),
(62, 3029, 'game_67fe0854d4dbd5.24069139', 2025, 1, NULL, 2025, 2022, 2024, 2023, NULL, NULL, NULL, NULL, 4, '2024-10-06 15:18:00', 45, 'New game. Sleepless team lost again.'),
(63, 3029, 'game_67fe08921c1b62.67204485', 2024, 1, NULL, 2024, 2022, 2025, 2023, NULL, NULL, NULL, NULL, 4, '2024-12-29 07:18:00', 40, ''),
(64, 3029, 'game_67fe08b5733bc2.80794154', 2024, 1, NULL, 2024, 2025, 2022, 2023, NULL, NULL, NULL, NULL, 4, '2024-12-29 11:19:00', 25, ''),
(65, 3033, 'game_67fe09b0a0cf16.26895897', 2023, 1, NULL, 2023, 2025, 2022, 2024, NULL, NULL, NULL, NULL, 4, '2024-12-29 23:02:00', 60, ''),
(66, 3035, 'game_67fe0ab3586402.83663513', 2025, 1, NULL, 2025, 2023, 2022, NULL, NULL, NULL, NULL, NULL, 3, '2025-02-09 00:00:00', 45, '*********Cody absence.'),
(67, 3037, 'game_67fe2981909d33.57088851', 2023, 1, NULL, 2023, 2022, 2025, NULL, NULL, NULL, NULL, NULL, 3, '2025-02-09 15:00:00', 40, '•••••••Cody absence'),
(68, 3038, 'game_67fe29b80cf351.05015822', 2025, 1, NULL, 2025, 2022, 2023, NULL, NULL, NULL, NULL, NULL, 3, '2025-02-09 18:00:00', 40, '******Cody absence'),
(69, 3039, 'game_67ff2dfa8a5936.43750225', 2030, 1, NULL, 2030, 2027, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-06 04:10:00', 240, 'I win!'),
(70, 3039, 'game_67ff2ea13c6cf4.19604433', 2030, 1, NULL, 2030, 2027, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-16 04:14:00', 0, ''),
(71, 3039, 'game_67ff2ec9c54528.77401136', 2030, 1, NULL, 2030, 2029, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-16 04:14:00', 0, '<img src=\"https://upload.wikimedia.org/wikipedia/en/d/dc/GroundskeeperWillie.png\">'),
(72, 3039, 'game_67ff396fbf6053.65916069', 2028, 1, NULL, 2028, 2030, 2029, 2027, NULL, NULL, NULL, NULL, 3, '2025-04-16 00:00:00', 90, ''),
(73, 3039, 'game_67ff3b19681b71.12363101', 2030, 1, NULL, 2030, 2029, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-02 05:07:00', 0, ''),
(74, 3030, 'game_6800adf19e4a36.42224185', 2025, 1, NULL, 2025, 2023, 2024, 2022, NULL, NULL, NULL, NULL, 4, '2024-10-06 16:29:00', 120, 'First time game. Cody said it was better than cities and knights.'),
(75, 3024, 'game_68021bb3c52158.55313114', 2022, 1, NULL, 2022, 2024, 2023, 2025, NULL, NULL, NULL, NULL, 4, '2025-04-06 00:00:00', 180, 'Expansion: Canada //Finished this game pretty late.'),
(76, 3021, 'game_6815831000fbc7.85000079', 2023, 1, NULL, 2023, 2025, 2022, 2024, NULL, NULL, NULL, NULL, 4, '2025-05-03 11:43:00', 120, 'A game of bold statements!'),
(77, 3040, 'game_6815a4bf3cfbf1.17715327', 2022, 1, NULL, 2022, 2024, 2025, 2023, NULL, NULL, NULL, NULL, 4, '2025-05-03 14:06:00', 60, 'A bountiful harvest for Josh.'),
(78, 3020, 'game_68160638952c41.07426160', 2023, 1, NULL, 2023, 2024, 2022, 2025, NULL, NULL, NULL, NULL, 4, '2025-05-03 00:00:00', 300, 'Cities and nights. Victory points via progression cards are untouchable as a note for next time. No spies on victory point cards. We played vanilla version. No longest roads and no biggest army.');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_successful` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `member_name` varchar(100) NOT NULL,
  `nickname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`member_id`, `admin_id`, `club_id`, `member_name`, `nickname`, `email`, `status`, `created_at`) VALUES
(2005, 3, 1014, 'Davey Markus', 'Tiger_6969', 'f.montoya@gmail.com', 'active', '2025-04-08 08:26:16'),
(2006, 3, 1014, 'Bud Selig', 'whiskey0791', 'fidelgmontoya@gmail.com', 'active', '2025-04-08 08:26:36'),
(2007, 3, 1014, 'Luka Doncic', 'LukaMagic_0445', 'luka@himself.com', 'active', '2025-04-08 08:39:02'),
(2008, 3, 1014, 'LeBron James', 'KingJames25', 'kingjames@montoyahome.com', 'active', '2025-04-08 08:39:41'),
(2009, 3, 1014, 'Kosuke HIrai', 'T-Rex', 'kingjames@gmail.com', 'active', '2025-04-10 06:44:54'),
(2010, 5, 1016, 'Avery Linwood', 'ShadowQuill', 'a.linwood92@gamemail.net', 'active', '2025-04-13 11:40:24'),
(2011, 5, 1016, 'Marcus “M.J.” Trenholm', 'VantaStrike', 'mjtrenholm@arcadeverse.com', 'active', '2025-04-13 11:41:12'),
(2012, 5, 1016, 'Delilah Reyes', 'HexaNova', 'delireyes_89@firebyte.gg', 'active', '2025-04-13 11:41:42'),
(2013, 5, 1016, 'Theo Granger', 'FrostPulse', 'theo.g_7@xnetarena.org', 'active', '2025-04-13 11:42:16'),
(2014, 6, 1017, 'Cody Baldwin', 'diarrhea', 'codybaldwin@gmail.com', 'active', '2025-04-13 21:05:43'),
(2015, 6, 1017, 'Josh Nishikawa', 'Rabbit poop', 'josh@josh.josh', 'active', '2025-04-13 21:06:03'),
(2016, 6, 1017, 'Richard', 'Stool', 'telf@telf.telf', 'active', '2025-04-13 21:06:26'),
(2017, 6, 1017, 'Fidel', 'skid mark', 'skid@mark.com', 'active', '2025-04-13 21:07:06'),
(2018, 7, 1020, 'Dick', 'Dick', 'telferrichard19@gmail.com', 'active', '2025-04-14 01:48:50'),
(2019, 7, 1021, 'Dock', 'Dock', 'telferrichard19@gmail.com', 'active', '2025-04-14 01:49:33'),
(2020, 7, 1019, 'Duck', 'Duck', 'telferrichard19@gmail.com', 'active', '2025-04-14 01:49:54'),
(2021, 7, 1022, 'Deck', 'Deck', 'telferrichard19@gmail.com', 'active', '2025-04-14 01:50:15'),
(2022, 3, 1027, 'Josh Nishikawa', 'Scout', 'joshnishikawa@gmail.com', 'active', '2025-04-15 00:20:44'),
(2023, 3, 1027, 'Richard Telfer', 'Skull', 'telferrichard19@gmail.com', 'active', '2025-04-15 00:22:37'),
(2024, 3, 1027, 'Cody Baldwin', 'Coaster', 'codybaldwin@gmail.com', 'active', '2025-04-15 00:23:20'),
(2025, 3, 1027, 'Fidel Montoya', 'Chef', 'f.montoya@gmail.com', 'active', '2025-04-15 00:23:36'),
(2026, 3, 1027, 'Ghost Ship', 'GhostShip', 'fidel@montoyahome.com', 'active', '2025-04-15 01:10:26'),
(2027, 12, 1028, 'Cody Baldwin', 'Coaster', 'sheeeit@idontknow', 'active', '2025-04-15 21:52:46'),
(2028, 12, 1028, 'Richard Telfer', 'Skull', 'sheeeit@idontknow', 'active', '2025-04-15 21:57:14'),
(2029, 12, 1028, 'Fidel Montoya', 'Chef', 'sheeeit@idontknow', 'active', '2025-04-15 21:57:44'),
(2030, 12, 1028, 'Josh Nishikawa', 'Scout', 'joshnishikawa@gmail.com', 'active', '2025-04-15 21:58:40');

-- --------------------------------------------------------

--
-- Table structure for table `registration_attempts`
--

CREATE TABLE `registration_attempts` (
  `attempt_id` int(11) NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_successful` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_attempts`
--

INSERT INTO `registration_attempts` (`attempt_id`, `ip_address`, `attempt_time`, `is_successful`) VALUES
(30, '126.22.49.247', '2025-04-14 09:31:30', 1),
(33, '126.22.49.247', '2025-04-14 10:08:15', 1),
(34, '126.253.115.213', '2025-04-15 21:45:14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `team_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `team_name` varchar(100) NOT NULL,
  `member1_id` int(11) DEFAULT NULL,
  `member2_id` int(11) DEFAULT NULL,
  `member3_id` int(11) DEFAULT NULL,
  `member4_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`team_id`, `club_id`, `team_name`, `member1_id`, `member2_id`, `member3_id`, `member4_id`, `created_at`) VALUES
(4, 1014, 'LakeShow', 2008, 2007, NULL, NULL, '2025-04-08 08:40:14'),
(5, 1014, 'MLBLive', 2006, 2005, NULL, NULL, '2025-04-08 08:40:42'),
(6, 1017, 'The Pieces of Shit', 2014, 2017, 2015, 2016, '2025-04-13 21:20:50'),
(7, 1022, 'Bling', 2021, NULL, NULL, NULL, '2025-04-14 01:51:04'),
(8, 1021, 'Blong', 2019, NULL, NULL, NULL, '2025-04-14 01:51:52'),
(9, 1020, 'Blung', 2018, NULL, NULL, NULL, '2025-04-14 01:52:09'),
(10, 1019, 'Blang', 2020, NULL, NULL, NULL, '2025-04-14 01:52:28'),
(11, 1027, 'DeckSwabbers', 2024, 2022, 2023, NULL, '2025-04-15 00:50:38'),
(12, 1027, 'Chef', 2025, NULL, NULL, NULL, '2025-04-15 00:51:20'),
(13, 1027, 'Salty Dogs', 2025, 2024, NULL, NULL, '2025-04-15 01:02:36'),
(14, 1027, 'Watyuno', 2022, 2023, NULL, NULL, '2025-04-15 01:03:00'),
(15, 1027, 'Vultures', 2025, 2023, NULL, NULL, '2025-04-15 01:03:29'),
(16, 1027, 'Stark', 2024, 2022, NULL, NULL, '2025-04-15 01:03:46'),
(17, 1027, 'The Flying Dutchmen', 2024, 2025, 2022, 2023, '2025-04-15 01:08:57'),
(18, 1027, 'The S.S.', 2026, NULL, NULL, NULL, '2025-04-15 01:10:51'),
(19, 1027, 'Scout', 2022, NULL, NULL, NULL, '2025-04-15 01:25:40'),
(20, 1027, 'Skull', 2023, NULL, NULL, NULL, '2025-04-15 03:25:04'),
(21, 1027, 'The Peelers', 2022, 2025, NULL, NULL, '2025-04-15 03:26:35'),
(22, 1027, 'The Fruits', 2024, 2023, NULL, NULL, '2025-04-15 03:38:12'),
(23, 1028, 'Salty Dogs', 2027, 2029, NULL, NULL, '2025-04-15 22:07:33'),
(25, 1028, 'Watyuno', 2030, 2028, NULL, NULL, '2025-04-15 22:10:03'),
(26, 1027, 'The Lushes', 2024, 2025, 2023, NULL, '2025-05-03 10:08:00'),
(27, 1027, 'Fidel', 2025, NULL, NULL, NULL, '2025-05-03 12:04:53');

-- --------------------------------------------------------

--
-- Table structure for table `team_game_results`
--

CREATE TABLE `team_game_results` (
  `result_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `team_id` int(11) NOT NULL,
  `position` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `winner` int(11) DEFAULT NULL,
  `place_2` int(11) DEFAULT NULL,
  `place_3` int(11) DEFAULT NULL,
  `place_4` int(11) DEFAULT NULL,
  `place_5` int(11) DEFAULT NULL,
  `place_6` int(11) DEFAULT NULL,
  `place_7` int(11) DEFAULT NULL,
  `place_8` int(11) DEFAULT NULL,
  `num_teams` int(11) DEFAULT NULL,
  `played_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `duration` int(11) DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `team_game_results`
--

INSERT INTO `team_game_results` (`result_id`, `game_id`, `session_id`, `team_id`, `position`, `score`, `winner`, `place_2`, `place_3`, `place_4`, `place_5`, `place_6`, `place_7`, `place_8`, `num_teams`, `played_at`, `duration`, `notes`) VALUES
(1, 3006, 'team_game_67f5359df00668.17823912', 4, 1, NULL, 4, 5, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-07 14:41:00', 185, 'Another test.'),
(2, 3022, 'team_game_67fe021a50bd12.52148204', 12, 1, NULL, 12, 11, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2024-01-03 00:00:00', 150, 'I think we\'re getting the hang of this game.'),
(3, 3025, 'team_game_67fe0528c43691.82785927', 13, 1, NULL, 13, 14, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2024-04-28 16:00:00', 120, 'Great game! Ebb and flow.'),
(4, 3025, 'team_game_67fe055d463a13.05160327', 13, 1, NULL, 13, 14, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2024-10-06 16:05:00', 180, 'Expansion: Siege'),
(5, 3025, 'team_game_67fe058a9fb1b4.02241926', 15, 1, NULL, 15, 16, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-01-26 13:00:00', 120, ''),
(6, 3026, 'team_game_67fe06ac8ff482.46711112', 18, 1, NULL, 18, 17, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2024-04-28 16:00:00', 180, 'Hazard icons indicate additional scene cards NOT automatic fear.'),
(7, 3026, 'team_game_67fe06d8376844.61459346', 18, 1, NULL, 18, 17, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2024-05-26 16:11:00', 300, 'We can\'t win!!!'),
(8, 3031, 'team_game_67fe08f4b772c5.52792056', 17, 1, NULL, 17, 18, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2024-11-24 16:20:00', 240, 'New favorite cooperative game.'),
(9, 3031, 'team_game_67fe09219b2938.10567300', 17, 1, NULL, 17, 18, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2024-12-29 07:21:00', 150, ''),
(10, 3031, 'team_game_67fe093d5ec512.86921858', 17, 1, NULL, 17, 18, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-04-06 07:22:00', 240, 'We interrupted this game several times to chat.'),
(11, 3034, 'team_game_67fe0a3ccf9a60.24184743', 19, 1, NULL, 19, 15, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-01-26 00:00:00', 60, ''),
(12, 3036, 'team_game_67fe270e8a5156.35607632', 21, 1, NULL, 21, 20, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-02-09 12:29:00', 180, 'Three player game, 50 rounds, two total escapes.'),
(13, 3036, 'team_game_67fe293f4d8a01.41472811', 22, 1, NULL, 22, 21, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-03-02 16:00:00', 150, 'Next game day note: 36 rounds and 3 total escapes or 2 by the same player.'),
(14, 3022, 'team_game_68163ff8c69098.31645362', 19, 1, NULL, 19, 26, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2025-05-03 16:08:00', 240, 'We learned some new tactics. Some that are counterintuitive. Different strategies came to light.'),
(15, 3041, 'team_game_68165b89041291.22084000', 19, 1, NULL, 19, 22, 27, NULL, NULL, NULL, NULL, NULL, 3, '2025-05-03 18:06:00', 90, 'The winner of the Davie decided by this game.');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `idx_email` (`email`),
  ADD KEY `idx_email_verification` (`email_verification_token`),
  ADD KEY `idx_reset_password` (`reset_password_token`);

--
-- Indexes for table `champions`
--
ALTER TABLE `champions`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `club_id` (`club_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`club_id`);

--
-- Indexes for table `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `idx_token` (`token`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_expiry` (`expires_at`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `game_results`
--
ALTER TABLE `game_results`
  ADD PRIMARY KEY (`result_id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_ip_email` (`ip_address`,`email`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `registration_attempts`
--
ALTER TABLE `registration_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_attempt_time` (`attempt_time`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`team_id`),
  ADD KEY `member1_id` (`member1_id`),
  ADD KEY `member2_id` (`member2_id`),
  ADD KEY `member3_id` (`member3_id`),
  ADD KEY `member4_id` (`member4_id`);

--
-- Indexes for table `team_game_results`
--
ALTER TABLE `team_game_results`
  ADD PRIMARY KEY (`result_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `champions`
--
ALTER TABLE `champions`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `club_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1029;

--
-- AUTO_INCREMENT for table `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3042;

--
-- AUTO_INCREMENT for table `game_results`
--
ALTER TABLE `game_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2031;

--
-- AUTO_INCREMENT for table `registration_attempts`
--
ALTER TABLE `registration_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `team_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `team_game_results`
--
ALTER TABLE `team_game_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `champions`
--
ALTER TABLE `champions`
  ADD CONSTRAINT `champions_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`club_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `champions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE;

--
-- Constraints for table `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `games_ibfk_1` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`club_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
