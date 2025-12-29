-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 13, 2025 at 10:31 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quiz_wednesday`
--

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE `options` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `option_text` varchar(255) NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `option_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `options`
--

INSERT INTO `options` (`id`, `question_id`, `option_text`, `is_correct`, `option_order`) VALUES
(1, 1, 'Melepaskan piranha', 1, 1),
(2, 1, 'Membakar gedung', 0, 2),
(3, 1, 'Racun makanan', 0, 3),
(4, 1, 'Ritual arwah', 0, 4),
(5, 2, 'Enid Sinclair', 1, 1),
(6, 2, 'Bianca Barclay', 0, 2),
(7, 2, 'Yoko Tanaka', 0, 3),
(8, 2, 'Divina', 0, 4),
(9, 3, 'Ophelia Hall', 1, 1),
(10, 3, 'Raven Hall', 0, 2),
(11, 3, 'Poe Hall', 0, 3),
(12, 3, 'Addams Hall', 0, 4),
(13, 4, 'Hyde', 1, 1),
(14, 4, 'Werewolf', 0, 2),
(15, 4, 'Vampire', 0, 3),
(16, 4, 'Gorgon', 0, 4),
(17, 5, 'Shapeshifting', 1, 1),
(18, 5, 'Telekinesis', 0, 2),
(19, 5, 'Pyrokinesis', 0, 3),
(20, 5, 'Petrification', 0, 4),
(21, 6, 'Thing', 1, 1),
(22, 6, 'It', 0, 2),
(23, 6, 'Handy', 0, 3),
(24, 6, 'Fingers', 0, 4),
(25, 7, 'Cello', 1, 1),
(26, 7, 'Biola', 0, 2),
(27, 7, 'Piano', 0, 3),
(28, 7, 'Harp', 0, 4),
(29, 8, 'Uncle Fester', 1, 1),
(30, 8, 'Grandmama', 0, 2),
(31, 8, 'Cousin Itt', 0, 3),
(32, 8, 'Lurch', 0, 4),
(33, 9, 'Weathervane Coffee', 1, 1),
(34, 9, 'Perpustakaan', 0, 2),
(35, 9, 'Kantin Sekolah', 0, 3),
(36, 9, 'Toko Bunga', 0, 4),
(37, 10, 'Sugesti Suara', 1, 1),
(38, 10, 'Baca Pikiran', 0, 2),
(39, 10, 'Kekuatan Fisik', 0, 3),
(40, 10, 'Menghilang', 0, 4),
(41, 11, 'Joseph Crackstone', 1, 1),
(42, 11, 'Nathaniel Faulkner', 0, 2),
(43, 11, 'Ansel Gates', 0, 3),
(44, 11, 'Mayor Walker', 0, 4),
(45, 12, 'Laurel Gates', 1, 1),
(46, 12, 'Goody Addams', 0, 2),
(47, 12, 'Valerie Kinbott', 0, 3),
(48, 12, 'Dr. Kinbott', 0, 4),
(49, 13, 'The Black Cats', 1, 1),
(50, 13, 'The Gold Bugs', 0, 2),
(51, 13, 'The Ravens', 0, 3),
(52, 13, 'The Jokers', 0, 4),
(53, 14, 'Goo Goo Muck', 1, 1),
(54, 14, 'Bloody Mary', 0, 2),
(55, 14, 'Paint It Black', 0, 3),
(56, 14, 'Monster Mash', 0, 4),
(57, 15, 'Jadi Werewolf', 1, 1),
(58, 15, 'Hilang kekuatan', 0, 2),
(59, 15, 'Pingsan', 0, 3),
(60, 15, 'Jadi Hyde', 0, 4);

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','short_answer') DEFAULT 'multiple_choice',
  `points` int(11) DEFAULT 10,
  `image_url` varchar(255) DEFAULT NULL,
  `explanation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `quiz_id`, `question_text`, `question_type`, `points`, `image_url`, `explanation`, `created_at`) VALUES
(1, 1, 'Apa alasan Wednesday dikeluarkan dari sekolah lamanya?', 'multiple_choice', 10, 'q1_piranha.jpg', 'Melepaskan piranha.', '2025-12-13 05:48:19'),
(2, 1, 'Siapa teman sekamar Wednesday?', 'multiple_choice', 10, 'q2_enid.jpg', 'Enid Sinclair.', '2025-12-13 05:48:19'),
(3, 1, 'Apa nama asrama tempat Wednesday tinggal?', 'multiple_choice', 10, 'q3_dorm.jpg', 'Ophelia Hall.', '2025-12-13 05:48:19'),
(4, 1, 'Monster jenis apa yang meneror Jericho?', 'multiple_choice', 10, 'q4_monster.jpg', 'Hyde.', '2025-12-13 05:48:19'),
(5, 1, 'Apa kemampuan Principal Weems?', 'multiple_choice', 10, 'q5_weems.jpg', 'Shapeshifting.', '2025-12-13 05:48:19'),
(6, 1, 'Siapa nama tangan yang mengawasi Wednesday?', 'multiple_choice', 10, 'q6_thing.jpg', 'Thing.', '2025-12-13 05:48:19'),
(7, 1, 'Alat musik apa yang dimainkan Wednesday?', 'multiple_choice', 10, 'q7_cello.jpg', 'Cello.', '2025-12-13 05:48:19'),
(8, 1, 'Siapa paman yang datang berkunjung?', 'multiple_choice', 10, 'q8_fester.jpg', 'Uncle Fester.', '2025-12-13 05:48:19'),
(9, 1, 'Di mana Tyler bekerja?', 'multiple_choice', 10, 'q9_coffee.jpg', 'Weathervane Coffee.', '2025-12-13 05:48:19'),
(10, 1, 'Apa kekuatan Bianca Barclay?', 'multiple_choice', 10, 'q10_bianca.jpg', 'Sugesti suara (Siren).', '2025-12-13 05:48:19'),
(11, 1, 'Siapa leluhur yang dibangkitkan kembali?', 'multiple_choice', 10, 'q11_crackstone.jpg', 'Joseph Crackstone.', '2025-12-13 05:48:19'),
(12, 1, 'Siapa identitas asli Marilyn Thornhill?', 'multiple_choice', 10, 'q12_thornhill.jpg', 'Laurel Gates.', '2025-12-13 05:48:19'),
(13, 1, 'Apa nama tim perahu Wednesday di Poe Cup?', 'multiple_choice', 10, 'q13_poecup.jpg', 'The Black Cats.', '2025-12-13 05:48:19'),
(14, 1, 'Lagu apa yang mengiringi tarian Wednesday?', 'multiple_choice', 10, 'q14_dance.jpg', 'Goo Goo Muck.', '2025-12-13 05:48:19'),
(15, 1, 'Apa yang terjadi pada Enid saat Blood Moon?', 'multiple_choice', 10, 'q15_wolf.jpg', 'Berubah jadi Werewolf.', '2025-12-13 05:48:19');

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` enum('easy','medium','hard') DEFAULT 'medium',
  `total_questions` int(11) DEFAULT 0,
  `time_limit` int(11) DEFAULT 0,
  `max_score` int(11) DEFAULT 100,
  `created_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`id`, `title`, `description`, `difficulty`, `total_questions`, `time_limit`, `max_score`, `created_by`, `is_active`, `created_at`) VALUES
(1, 'Wednesday Ultimate Trivia', 'Uji pengetahuanmu tentang seluruh Season 1 Wednesday!', 'hard', 15, 30, 150, 1, 1, '2025-12-13 05:48:19');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_results`
--

CREATE TABLE `quiz_results` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `total_score` int(11) DEFAULT 0,
  `max_score` int(11) DEFAULT 0,
  `correct_answers` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `time_taken` int(11) DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rank_position` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_results`
--

INSERT INTO `quiz_results` (`id`, `user_id`, `quiz_id`, `total_score`, `max_score`, `correct_answers`, `total_questions`, `time_taken`, `completed_at`, `rank_position`) VALUES
(1, 1, 1, 150, 150, 15, 15, 15, '2025-12-13 05:48:19', 1),
(2, 3, 1, 140, 150, 14, 15, 20, '2025-12-13 05:48:19', 2),
(3, 6, 1, 140, 150, 14, 15, 18, '2025-12-13 05:48:19', 3),
(4, 2, 1, 130, 150, 13, 15, 25, '2025-12-13 05:48:19', 4),
(5, 9, 1, 120, 150, 12, 15, 10, '2025-12-13 05:48:19', 5),
(6, 10, 1, 110, 150, 11, 15, 12, '2025-12-13 05:48:19', 6),
(7, 4, 1, 100, 150, 10, 15, 22, '2025-12-13 05:48:19', 7),
(8, 7, 1, 80, 150, 8, 15, 30, '2025-12-13 05:48:19', 8),
(9, 5, 1, 70, 150, 7, 15, 14, '2025-12-13 05:48:19', 9),
(10, 8, 1, 50, 150, 5, 15, 28, '2025-12-13 05:48:19', 10);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `total_score` int(11) DEFAULT 0,
  `quiz_completed` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_played` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `profile_picture`, `total_score`, `quiz_completed`, `created_at`, `last_played`) VALUES
(1, 'wednesday_fan', 'fan@nevermore.edu', 'pass1', 'Wednesday Fan', 'avatar1.jpg', 150, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(2, 'thing_lover', 'hand@addams.com', 'pass2', 'Thing T. Thing', 'avatar2.jpg', 130, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(3, 'enid_colors', 'wolf@sinclair.com', 'pass3', 'Enid Sinclair', 'avatar3.jpg', 140, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(4, 'xavier_art', 'artist@thorpe.com', 'pass4', 'Xavier Thorpe', 'avatar4.jpg', 100, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(5, 'fester_electric', 'shock@addams.com', 'pass5', 'Uncle Fester', 'avatar5.jpg', 70, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(6, 'bianca_siren', 'siren@nevermore.edu', 'pass6', 'Bianca Barclay', 'avatar6.jpg', 140, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(7, 'eugene_bees', 'bees@nevermore.edu', 'pass7', 'Eugene Ottinger', 'avatar7.jpg', 80, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(8, 'ajax_stone', 'gorgon@nevermore.edu', 'pass8', 'Ajax Petropolus', 'avatar8.jpg', 50, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(9, 'tyler_coffee', 'latte@weathervane.com', 'pass9', 'Tyler Galpin', 'avatar9.jpg', 120, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(10, 'weems_principal', 'headmistress@nevermore.edu', 'pass10', 'Larissa Weems', 'avatar10.jpg', 110, 1, '2025-12-13 05:48:19', '2025-12-13 05:48:19'),
(11, 'naira', 'naira@gmail.com', '$2y$10$ThQg/2jzwLn3KJDrW.mGN.x0IuESztZb7p8TXWR1J5WLRl4rV1AVW', 'naira', NULL, 0, 0, '2025-12-13 03:19:12', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_answers`
--

CREATE TABLE `user_answers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option_id` int(11) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_answers`
--

INSERT INTO `user_answers` (`id`, `user_id`, `quiz_id`, `question_id`, `selected_option_id`, `answer_text`, `is_correct`, `points_earned`, `answered_at`) VALUES
(1, 1, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(2, 1, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(3, 1, 1, 3, 9, '', 1, 10, '2025-12-13 05:48:19'),
(4, 1, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(5, 1, 1, 5, 17, '', 1, 10, '2025-12-13 05:48:19'),
(6, 1, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(7, 1, 1, 7, 25, '', 1, 10, '2025-12-13 05:48:19'),
(8, 1, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(9, 1, 1, 9, 33, '', 1, 10, '2025-12-13 05:48:19'),
(10, 1, 1, 10, 37, '', 1, 10, '2025-12-13 05:48:19'),
(11, 1, 1, 11, 41, '', 1, 10, '2025-12-13 05:48:19'),
(12, 1, 1, 12, 45, '', 1, 10, '2025-12-13 05:48:19'),
(13, 1, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(14, 1, 1, 14, 53, '', 1, 10, '2025-12-13 05:48:19'),
(15, 1, 1, 15, 57, '', 1, 10, '2025-12-13 05:48:19'),
(16, 2, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(17, 2, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(18, 2, 1, 3, 9, '', 1, 10, '2025-12-13 05:48:19'),
(19, 2, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(20, 2, 1, 5, 17, '', 1, 10, '2025-12-13 05:48:19'),
(21, 2, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(22, 2, 1, 7, 25, '', 1, 10, '2025-12-13 05:48:19'),
(23, 2, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(24, 2, 1, 9, 34, '', 0, 0, '2025-12-13 05:48:19'),
(25, 2, 1, 10, 37, '', 1, 10, '2025-12-13 05:48:19'),
(26, 2, 1, 11, 41, '', 1, 10, '2025-12-13 05:48:19'),
(27, 2, 1, 12, 45, '', 1, 10, '2025-12-13 05:48:19'),
(28, 2, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(29, 2, 1, 14, 53, '', 1, 10, '2025-12-13 05:48:19'),
(30, 2, 1, 15, 60, '', 0, 0, '2025-12-13 05:48:19'),
(31, 3, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(32, 3, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(33, 3, 1, 3, 9, '', 1, 10, '2025-12-13 05:48:19'),
(34, 3, 1, 4, 15, '', 0, 0, '2025-12-13 05:48:19'),
(35, 3, 1, 5, 17, '', 1, 10, '2025-12-13 05:48:19'),
(36, 3, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(37, 3, 1, 7, 25, '', 1, 10, '2025-12-13 05:48:19'),
(38, 3, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(39, 3, 1, 9, 33, '', 1, 10, '2025-12-13 05:48:19'),
(40, 3, 1, 10, 37, '', 1, 10, '2025-12-13 05:48:19'),
(41, 3, 1, 11, 41, '', 1, 10, '2025-12-13 05:48:19'),
(42, 3, 1, 12, 45, '', 1, 10, '2025-12-13 05:48:19'),
(43, 3, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(44, 3, 1, 14, 53, '', 1, 10, '2025-12-13 05:48:19'),
(45, 3, 1, 15, 57, '', 1, 10, '2025-12-13 05:48:19'),
(46, 4, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(47, 4, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(48, 4, 1, 3, 10, '', 0, 0, '2025-12-13 05:48:19'),
(49, 4, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(50, 4, 1, 5, 17, '', 1, 10, '2025-12-13 05:48:19'),
(51, 4, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(52, 4, 1, 7, 27, '', 0, 0, '2025-12-13 05:48:19'),
(53, 4, 1, 8, 30, '', 0, 0, '2025-12-13 05:48:19'),
(54, 4, 1, 9, 33, '', 1, 10, '2025-12-13 05:48:19'),
(55, 4, 1, 10, 37, '', 1, 10, '2025-12-13 05:48:19'),
(56, 4, 1, 11, 41, '', 1, 10, '2025-12-13 05:48:19'),
(57, 4, 1, 12, 46, '', 0, 0, '2025-12-13 05:48:19'),
(58, 4, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(59, 4, 1, 14, 53, '', 1, 10, '2025-12-13 05:48:19'),
(60, 4, 1, 15, 60, '', 0, 0, '2025-12-13 05:48:19'),
(61, 5, 1, 1, 2, '', 0, 0, '2025-12-13 05:48:19'),
(62, 5, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(63, 5, 1, 3, 9, '', 1, 10, '2025-12-13 05:48:19'),
(64, 5, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(65, 5, 1, 5, 19, '', 0, 0, '2025-12-13 05:48:19'),
(66, 5, 1, 6, 22, '', 0, 0, '2025-12-13 05:48:19'),
(67, 5, 1, 7, 25, '', 1, 10, '2025-12-13 05:48:19'),
(68, 5, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(69, 5, 1, 9, 35, '', 0, 0, '2025-12-13 05:48:19'),
(70, 5, 1, 10, 37, '', 1, 10, '2025-12-13 05:48:19'),
(71, 5, 1, 11, 42, '', 0, 0, '2025-12-13 05:48:19'),
(72, 5, 1, 12, 47, '', 0, 0, '2025-12-13 05:48:19'),
(73, 5, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(74, 5, 1, 14, 54, '', 0, 0, '2025-12-13 05:48:19'),
(75, 5, 1, 15, 58, '', 0, 0, '2025-12-13 05:48:19'),
(76, 6, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(77, 6, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(78, 6, 1, 3, 9, '', 1, 10, '2025-12-13 05:48:19'),
(79, 6, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(80, 6, 1, 5, 17, '', 1, 10, '2025-12-13 05:48:19'),
(81, 6, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(82, 6, 1, 7, 26, '', 0, 0, '2025-12-13 05:48:19'),
(83, 6, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(84, 6, 1, 9, 33, '', 1, 10, '2025-12-13 05:48:19'),
(85, 6, 1, 10, 37, '', 1, 10, '2025-12-13 05:48:19'),
(86, 6, 1, 11, 41, '', 1, 10, '2025-12-13 05:48:19'),
(87, 6, 1, 12, 45, '', 1, 10, '2025-12-13 05:48:19'),
(88, 6, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(89, 6, 1, 14, 53, '', 1, 10, '2025-12-13 05:48:19'),
(90, 6, 1, 15, 57, '', 1, 10, '2025-12-13 05:48:19'),
(91, 7, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(92, 7, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(93, 7, 1, 3, 10, '', 0, 0, '2025-12-13 05:48:19'),
(94, 7, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(95, 7, 1, 5, 18, '', 0, 0, '2025-12-13 05:48:19'),
(96, 7, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(97, 7, 1, 7, 26, '', 0, 0, '2025-12-13 05:48:19'),
(98, 7, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(99, 7, 1, 9, 33, '', 1, 10, '2025-12-13 05:48:19'),
(100, 7, 1, 10, 38, '', 0, 0, '2025-12-13 05:48:19'),
(101, 7, 1, 11, 41, '', 1, 10, '2025-12-13 05:48:19'),
(102, 7, 1, 12, 46, '', 0, 0, '2025-12-13 05:48:19'),
(103, 7, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(104, 7, 1, 14, 55, '', 0, 0, '2025-12-13 05:48:19'),
(105, 7, 1, 15, 59, '', 0, 0, '2025-12-13 05:48:19'),
(106, 8, 1, 1, 3, '', 0, 0, '2025-12-13 05:48:19'),
(107, 8, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(108, 8, 1, 3, 12, '', 0, 0, '2025-12-13 05:48:19'),
(109, 8, 1, 4, 14, '', 0, 0, '2025-12-13 05:48:19'),
(110, 8, 1, 5, 20, '', 0, 0, '2025-12-13 05:48:19'),
(111, 8, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(112, 8, 1, 7, 25, '', 1, 10, '2025-12-13 05:48:19'),
(113, 8, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(114, 8, 1, 9, 36, '', 0, 0, '2025-12-13 05:48:19'),
(115, 8, 1, 10, 40, '', 0, 0, '2025-12-13 05:48:19'),
(116, 8, 1, 11, 44, '', 0, 0, '2025-12-13 05:48:19'),
(117, 8, 1, 12, 48, '', 0, 0, '2025-12-13 05:48:19'),
(118, 8, 1, 13, 51, '', 0, 0, '2025-12-13 05:48:19'),
(119, 8, 1, 14, 53, '', 1, 10, '2025-12-13 05:48:19'),
(120, 8, 1, 15, 59, '', 0, 0, '2025-12-13 05:48:19'),
(121, 9, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(122, 9, 1, 2, 5, '', 1, 10, '2025-12-13 05:48:19'),
(123, 9, 1, 3, 9, '', 1, 10, '2025-12-13 05:48:19'),
(124, 9, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(125, 9, 1, 5, 17, '', 1, 10, '2025-12-13 05:48:19'),
(126, 9, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(127, 9, 1, 7, 25, '', 1, 10, '2025-12-13 05:48:19'),
(128, 9, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(129, 9, 1, 9, 33, '', 1, 10, '2025-12-13 05:48:19'),
(130, 9, 1, 10, 39, '', 0, 0, '2025-12-13 05:48:19'),
(131, 9, 1, 11, 43, '', 0, 0, '2025-12-13 05:48:19'),
(132, 9, 1, 12, 45, '', 1, 10, '2025-12-13 05:48:19'),
(133, 9, 1, 13, 49, '', 1, 10, '2025-12-13 05:48:19'),
(134, 9, 1, 14, 53, '', 1, 10, '2025-12-13 05:48:19'),
(135, 9, 1, 15, 60, '', 0, 0, '2025-12-13 05:48:19'),
(136, 10, 1, 1, 1, '', 1, 10, '2025-12-13 05:48:19'),
(137, 10, 1, 2, 7, '', 0, 0, '2025-12-13 05:48:19'),
(138, 10, 1, 3, 9, '', 1, 10, '2025-12-13 05:48:19'),
(139, 10, 1, 4, 13, '', 1, 10, '2025-12-13 05:48:19'),
(140, 10, 1, 5, 17, '', 1, 10, '2025-12-13 05:48:19'),
(141, 10, 1, 6, 21, '', 1, 10, '2025-12-13 05:48:19'),
(142, 10, 1, 7, 25, '', 1, 10, '2025-12-13 05:48:19'),
(143, 10, 1, 8, 29, '', 1, 10, '2025-12-13 05:48:19'),
(144, 10, 1, 9, 33, '', 1, 10, '2025-12-13 05:48:19'),
(145, 10, 1, 10, 37, '', 1, 10, '2025-12-13 05:48:19'),
(146, 10, 1, 11, 41, '', 1, 10, '2025-12-13 05:48:19'),
(147, 10, 1, 12, 45, '', 1, 10, '2025-12-13 05:48:19'),
(148, 10, 1, 13, 50, '', 0, 0, '2025-12-13 05:48:19'),
(149, 10, 1, 14, 55, '', 0, 0, '2025-12-13 05:48:19'),
(150, 10, 1, 15, 60, '', 0, 0, '2025-12-13 05:48:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `options`
--
ALTER TABLE `options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `quiz_id` (`quiz_id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `options`
--
ALTER TABLE `options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `quiz_results`
--
ALTER TABLE `quiz_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_answers`
--
ALTER TABLE `user_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `options`
--
ALTER TABLE `options`
  ADD CONSTRAINT `options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD CONSTRAINT `quizzes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `quiz_results`
--
ALTER TABLE `quiz_results`
  ADD CONSTRAINT `quiz_results_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `quiz_results_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`);

--
-- Constraints for table `user_answers`
--
ALTER TABLE `user_answers`
  ADD CONSTRAINT `user_answers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_answers_ibfk_2` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`),
  ADD CONSTRAINT `user_answers_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`),
  ADD CONSTRAINT `user_answers_ibfk_4` FOREIGN KEY (`selected_option_id`) REFERENCES `options` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
