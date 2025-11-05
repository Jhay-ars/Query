-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2025 at 04:59 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `school_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` enum('Written','Performance','Exam') NOT NULL,
  `spreadsheet_id` int(11) NOT NULL,
  `max_score` float DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `name`, `category`, `spreadsheet_id`, `max_score`, `created_at`) VALUES
(1, 'act1', 'Written', 1, 50, '2025-05-07 16:01:43'),
(2, 'act2', 'Written', 1, 50, '2025-05-07 16:01:49'),
(3, 'act3', 'Written', 1, 50, '2025-05-07 16:01:54'),
(4, 'act4', 'Written', 1, 50, '2025-05-07 16:02:00'),
(5, 'perf1', 'Performance', 1, 50, '2025-05-07 16:02:07'),
(6, 'perf2', 'Performance', 1, 50, '2025-05-07 16:02:18'),
(7, 'exam1', 'Exam', 1, 100, '2025-05-07 16:02:30'),
(8, 'act1', 'Written', 4, 50, '2025-05-08 01:02:27'),
(9, 'act1', 'Written', 2, 10, '2025-05-13 20:48:17'),
(10, 'act3', 'Written', 2, 15, '2025-05-13 20:48:35'),
(11, 'act4', 'Written', 2, 20, '2025-05-13 20:48:42'),
(12, 'perf1', 'Written', 2, 50, '2025-05-13 20:48:53'),
(13, 'exam', 'Exam', 2, 100, '2025-05-13 20:49:03'),
(14, 'act1', 'Written', 8, 50, '2025-05-14 12:27:12'),
(15, 'act2', 'Written', 8, 25, '2025-05-14 12:27:18'),
(16, 'act3', 'Written', 8, 10, '2025-05-14 12:27:24'),
(17, 'perf1', 'Performance', 8, 50, '2025-05-14 12:27:31'),
(18, 'perf2', 'Performance', 8, 25, '2025-05-14 12:27:38'),
(19, 'exam1', 'Exam', 8, 100, '2025-05-14 12:27:48'),
(20, 'act1', 'Written', 12, 25, '2025-05-17 12:19:39'),
(21, 'act2', 'Written', 12, 10, '2025-05-17 12:19:47'),
(22, 'act3', 'Written', 12, 15, '2025-05-17 12:19:55'),
(23, 'perf1', 'Performance', 12, 50, '2025-05-17 12:20:05'),
(25, 'exam', 'Exam', 12, 100, '2025-05-17 12:20:19'),
(26, 'perf2', 'Performance', 12, 25, '2025-05-17 12:20:32'),
(27, 'act1', 'Written', 15, 25, '2025-05-18 13:24:41'),
(28, 'act2', 'Written', 15, 25, '2025-05-18 13:24:47'),
(29, 'act3', 'Written', 15, 30, '2025-05-18 13:24:55'),
(30, 'perf1', 'Performance', 15, 50, '2025-05-18 13:25:04'),
(31, 'perf2', 'Performance', 15, 50, '2025-05-18 13:25:12'),
(32, 'exam1', 'Exam', 15, 100, '2025-05-18 13:25:18');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `spreadsheet_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','late','absent','excused') NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `spreadsheet_id`, `date`, `status`, `notes`, `created_at`) VALUES
(5, 9, 2, '2025-05-08', 'present', 'goodjob', '2025-05-07 22:56:12'),
(7, 9, 2, '2025-05-09', 'present', '', '2025-05-07 22:56:29'),
(9, 9, 2, '2025-05-11', 'excused', 'test', '2025-05-07 23:28:26'),
(11, 9, 2, '2025-05-10', 'present', '', '2025-05-07 23:44:27'),
(13, 9, 2, '2025-05-12', 'present', 'try try', '2025-05-07 23:44:38'),
(15, 11, 4, '2025-05-08', 'present', 'mamamo', '2025-05-08 01:02:47'),
(16, 14, 8, '2025-05-15', 'absent', 'may sakit', '2025-05-14 12:35:55'),
(17, 13, 8, '2025-05-15', 'present', '', '2025-05-14 12:35:55'),
(18, 14, 8, '2025-05-16', 'present', '', '2025-05-14 12:35:57'),
(19, 13, 8, '2025-05-16', 'present', '', '2025-05-14 12:35:57'),
(20, 14, 8, '2025-05-17', 'present', '', '2025-05-14 12:36:05'),
(21, 13, 8, '2025-05-17', 'excused', 'may performanc', '2025-05-14 12:36:05'),
(22, 14, 8, '2025-05-18', 'present', '', '2025-05-14 12:36:08'),
(23, 13, 8, '2025-05-18', 'present', '', '2025-05-14 12:36:08'),
(24, 14, 8, '2025-05-19', 'present', '', '2025-05-14 12:36:10'),
(25, 13, 8, '2025-05-19', 'present', '', '2025-05-14 12:36:10'),
(26, 14, 8, '2025-05-26', 'absent', 'sakit', '2025-05-14 12:37:50'),
(27, 13, 8, '2025-05-26', 'present', '', '2025-05-14 12:37:50'),
(28, 14, 8, '2025-06-01', 'present', '', '2025-05-14 12:37:57'),
(29, 13, 8, '2025-06-01', 'present', '', '2025-05-14 12:37:57'),
(30, 14, 8, '2025-05-20', 'present', '', '2025-05-18 12:47:44'),
(31, 13, 8, '2025-05-20', 'present', '', '2025-05-18 12:47:44'),
(32, 14, 8, '2025-05-21', 'present', '', '2025-05-18 12:47:46'),
(33, 13, 8, '2025-05-21', 'present', '', '2025-05-18 12:47:46'),
(34, 14, 8, '2025-05-22', 'present', '', '2025-05-18 12:47:51'),
(35, 13, 8, '2025-05-22', 'late', 'tanga', '2025-05-18 12:47:51'),
(36, 14, 8, '2025-05-23', 'excused', 'bobo', '2025-05-18 12:47:59'),
(37, 13, 8, '2025-05-23', 'excused', 'bobo2', '2025-05-18 12:47:59'),
(38, 14, 8, '2025-05-24', 'absent', 'tatanga', '2025-05-18 12:48:07'),
(39, 13, 8, '2025-05-24', 'absent', 'tanga', '2025-05-18 12:48:07'),
(40, 23, 15, '2025-05-18', 'present', '', '2025-05-18 13:29:51'),
(41, 23, 15, '2025-05-19', 'absent', '', '2025-05-18 13:29:55'),
(42, 23, 15, '2025-05-20', 'late', 'naur', '2025-05-18 13:30:01'),
(43, 23, 15, '2025-05-21', 'excused', 'teast excused', '2025-05-18 13:30:08'),
(44, 23, 15, '2025-05-22', 'present', '', '2025-05-18 13:30:10'),
(45, 23, 15, '2025-05-23', 'present', '', '2025-05-18 13:30:12'),
(46, 23, 15, '2025-05-24', 'late', 'nawala', '2025-05-18 13:30:17'),
(48, 23, 15, '2025-05-26', 'present', '', '2025-05-18 13:34:32');

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `folders`
--

INSERT INTO `folders` (`id`, `teacher_id`, `folder_name`, `created_at`) VALUES
(1, 1, 'raph_math1', '2025-05-07 15:38:59'),
(2, 5, 'Mabuti', '2025-05-07 16:45:20'),
(4, 6, 'trial1_filipino', '2025-05-07 17:01:51'),
(5, 8, 'math', '2025-05-08 01:00:51'),
(13, 5, 'maginoo', '2025-05-14 11:13:22'),
(14, 5, 'mabango', '2025-05-14 12:20:54'),
(15, 17, 'May Baho', '2025-05-18 12:42:29');

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `spreadsheet_id` int(11) NOT NULL,
  `raw_grade` float NOT NULL,
  `transmuted_grade` float NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grades`
--

INSERT INTO `grades` (`id`, `student_id`, `spreadsheet_id`, `raw_grade`, `transmuted_grade`, `created_at`, `updated_at`) VALUES
(1, 23, 15, 73.75, 83, '2025-05-18 13:25:36', '2025-05-18 13:28:30');

-- --------------------------------------------------------

--
-- Table structure for table `invitation_tokens`
--

CREATE TABLE `invitation_tokens` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `message` text DEFAULT NULL,
  `declined` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `score` float DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scores`
--

INSERT INTO `scores` (`id`, `student_id`, `activity_id`, `score`, `created_at`) VALUES
(1, 2, 1, 50, '2025-05-07 16:02:53'),
(2, 2, 2, 28, '2025-05-07 16:02:53'),
(3, 2, 3, 43, '2025-05-07 16:02:53'),
(4, 2, 4, 44, '2025-05-07 16:02:53'),
(5, 2, 5, 50, '2025-05-07 16:02:53'),
(6, 2, 6, 40, '2025-05-07 16:02:53'),
(7, 2, 7, 81, '2025-05-07 16:02:53'),
(8, 11, 8, 50, '2025-05-08 01:02:33'),
(9, 9, 9, 10, '2025-05-13 20:48:23'),
(10, 10, 9, 0, '2025-05-13 20:48:23'),
(11, 9, 10, 10, '2025-05-13 20:49:22'),
(12, 9, 11, 20, '2025-05-13 20:49:22'),
(13, 9, 12, 45, '2025-05-13 20:49:22'),
(14, 9, 13, 88, '2025-05-13 20:49:22'),
(15, 10, 10, 0, '2025-05-13 20:49:22'),
(16, 10, 11, 0, '2025-05-13 20:49:22'),
(17, 10, 12, 0, '2025-05-13 20:49:22'),
(18, 10, 13, 0, '2025-05-13 20:49:22'),
(19, 14, 14, 50, '2025-05-14 12:28:26'),
(20, 14, 15, 20, '2025-05-14 12:28:26'),
(21, 14, 16, 9, '2025-05-14 12:28:26'),
(22, 14, 17, 45, '2025-05-14 12:28:26'),
(23, 14, 18, 15, '2025-05-14 12:28:26'),
(24, 14, 19, 86, '2025-05-14 12:28:26'),
(25, 13, 14, 30, '2025-05-14 12:28:26'),
(26, 13, 15, 21, '2025-05-14 12:28:26'),
(27, 13, 16, 8, '2025-05-14 12:28:26'),
(28, 13, 17, 50, '2025-05-14 12:28:26'),
(29, 13, 18, 20, '2025-05-14 12:28:26'),
(30, 13, 19, 78, '2025-05-14 12:28:26'),
(37, 20, 20, 15, '2025-05-17 12:21:13'),
(38, 20, 21, 9, '2025-05-17 12:21:13'),
(39, 20, 22, 10, '2025-05-17 12:21:13'),
(40, 20, 23, 42, '2025-05-17 12:21:13'),
(41, 20, 26, 22, '2025-05-17 12:21:13'),
(42, 20, 25, 76, '2025-05-17 12:21:13'),
(43, 23, 27, 25, '2025-05-18 13:25:36'),
(44, 23, 28, 25, '2025-05-18 13:25:36'),
(45, 23, 29, 0, '2025-05-18 13:25:36'),
(46, 23, 30, 50, '2025-05-18 13:25:36'),
(47, 23, 31, 50, '2025-05-18 13:25:36'),
(48, 23, 32, 25, '2025-05-18 13:25:36');

-- --------------------------------------------------------

--
-- Table structure for table `spreadsheets`
--

CREATE TABLE `spreadsheets` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spreadsheets`
--

INSERT INTO `spreadsheets` (`id`, `name`, `folder_id`, `created_at`) VALUES
(1, 'firstquarter', 1, '2025-05-07 15:39:05'),
(2, 'MAMAMO', 2, '2025-05-07 16:45:29'),
(4, 'firstquarter', 5, '2025-05-08 01:01:05'),
(5, 'uno', 2, '2025-05-13 20:12:42'),
(6, 'firstquarter', 13, '2025-05-14 11:13:29'),
(7, 'second Quarter', 13, '2025-05-14 11:13:34'),
(8, 'first quarter', 14, '2025-05-14 12:21:22'),
(12, 'second quarter', 14, '2025-05-17 11:30:11'),
(15, 'first quarter', 15, '2025-05-18 12:45:37');

-- --------------------------------------------------------

--
-- Table structure for table `spreadsheet_settings`
--

CREATE TABLE `spreadsheet_settings` (
  `id` int(11) NOT NULL,
  `spreadsheet_id` int(11) NOT NULL,
  `written_percentage` float NOT NULL,
  `performance_percentage` float NOT NULL,
  `exam_percentage` float NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spreadsheet_settings`
--

INSERT INTO `spreadsheet_settings` (`id`, `spreadsheet_id`, `written_percentage`, `performance_percentage`, `exam_percentage`, `is_locked`, `created_at`, `updated_at`) VALUES
(1, 8, 20, 60, 20, 1, '2025-05-17 15:20:56', '2025-05-17 15:20:56'),
(2, 12, 20, 60, 20, 1, '2025-05-17 15:21:46', '2025-05-17 15:21:46'),
(4, 15, 30, 50, 20, 1, '2025-05-18 12:46:50', '2025-05-18 12:46:50');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `spreadsheet_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `name`, `spreadsheet_id`, `created_at`) VALUES
(2, '20207093', 'student1', 1, '2025-05-07 15:42:37'),
(3, '10101010', 'student2', 1, '2025-05-07 16:18:53'),
(9, '10101010', 'student2', 2, '2025-05-07 22:48:17'),
(10, '123456', 'student3', 2, '2025-05-08 00:42:19'),
(11, '10101010', 'student2', 4, '2025-05-08 01:02:20'),
(13, '10101010', 'student2', 8, '2025-05-14 12:24:54'),
(14, '123456', 'joseph', 8, '2025-05-14 12:26:33'),
(20, '10101010', 'student2', 12, '2025-05-17 11:30:11'),
(23, '987654321', 'pota', 15, '2025-05-18 12:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `test_table`
--

CREATE TABLE `test_table` (
  `name` varchar(100) NOT NULL,
  `school` varchar(100) NOT NULL,
  `age` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `user_type` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_question1` varchar(255) NOT NULL,
  `reset_answer1` varchar(255) NOT NULL,
  `reset_question2` varchar(255) NOT NULL,
  `reset_answer2` varchar(255) NOT NULL,
  `student_lrn` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `user_type`, `created_at`, `reset_question1`, `reset_answer1`, `reset_question2`, `reset_answer2`, `student_lrn`) VALUES
(1, 'Teacher 1', '$2y$10$LTeKWFz1/3bLBsyt9R7guONvNi/sqYOfHojeWpYmst.hHOP5AdScO', 'Teacher1@gmail.com', 'teacher', '2025-05-07 15:37:22', 'jowa', 'jose', 'mama', 'rai', NULL),
(2, 'student1', '$2y$10$BpRFoLLeWPPipuazP1d6feuzb1dGgzgEtjcADfRL/L4UXJTPqYNIy', 'student1@gmail.com', 'student', '2025-05-07 15:38:18', 'grade', '7', 'dog', 'peanut', NULL),
(4, 'student2', '$2y$10$4QN.ijEBNPx0mcgvOU8pjOdzEwKVVIKb140y/uO.E5WrHgOVbfAqa', 'student2@gmail.com', 'student', '2025-05-07 16:18:36', 'What was the make of your favourite subject?', 'math', 'What was the name of your first teacher?', 'benny', '10101010'),
(5, 'Teacher Raph', '$2y$10$FBD.2khgVPBk0X74PD4SXupREn17iHBvu/qPR1UYNOw3UepCc74xK', 'Raphjgarcia@gmail.com', 'teacher', '2025-05-07 16:44:50', 'Jowa', 'jose', 'mama name', 'rai', NULL),
(6, 'Teacher 2', '$2y$10$7HZGwmUpSSTtEEim71lM/.bxatICQZd1c2Ez7svAen0DHA9LsDkKa', 'teacher2@gmail.com', 'teacher', '2025-05-07 16:51:24', '1', '1', '2', '2', NULL),
(7, 'student3', '$2y$10$GVznaI4XRsj6lF9KuzX70uRMEYsmsUiwHFDHFYfml3ZogCrY/Nf2K', 'student3@gmail.com', 'student', '2025-05-07 22:35:24', '1', '1', '2', '2', '123456'),
(8, 'Teacher tech1', '$2y$10$MOmpKpYoYoOY7SSlYpc86eJGSqQhD1b3IX7L9pQzBxFtPF/iufJfa', 'tech1@gmail.com', 'teacher', '2025-05-08 01:00:02', '1', '1', '2', '2', NULL),
(9, 'joshua', '$2y$10$PaA/wA4l7rrWh5wvmmLUGOwm8OYo8NoxgxI3TrR70exwiT2fIroGO', 'joshua@gmail.com', 'student', '2025-05-08 01:00:32', '1', '1', '2', '2', '123456789'),
(10, 'teacher_sample', '$2y$10$1SCnDulS/hnpRn5iz0VYN./f0aPd7O/3Fen74IeVUacH.z5Ty0Xea', 'teacher@example.com', 'teacher', '2025-05-09 12:20:05', 'What was your first pet\'s name?', 'Spot', 'What is your favorite color?', 'Blue', NULL),
(11, 'student_sample', '$2y$10$2hgg.Ed7WuIZ1MtJvMtabuWzvoFSDtnAgxaEJl1uH/dpseLp0BA8O', 'student@example.com', 'student', '2025-05-09 12:20:05', 'What was your first pet\'s name?', 'Fluffy', 'What is your favorite color?', 'Green', '12345678'),
(13, 'admin', '$2y$10$qCe0AV6Yb9zuBKJR/zhfWefw7yfQh6akKZNy7DHuM520LvfxYg0fW', 'admin@schoolportal.com', 'admin', '2025-05-09 12:57:33', 'Security question 1', 'answer1', 'Security question 2', 'answer2', NULL),
(16, 'pota', '$2y$10$giR.SExGttu55DU85e1L1uyr5nIT/HRYsQGy.YEfvuVM9q6iww0Hy', 'otep@gmail.com', 'student', '2025-05-18 12:38:35', 'What was your first pet\'s name?', '1', 'In what city were you born?', '2', '987654321'),
(17, 'teacher otep', '$2y$10$gqMQ.eCAd1BIvpHJ0i/8qO3Nr6qi5dOTA2FV/UhLA8a4Ifz8a4rwy', 'otept@gmail.com', 'teacher', '2025-05-18 12:41:19', '1', '1', '2', '2', NULL),
(18, 'otepa', '$2y$10$G3TSsS81Ndf3PoDJAb.Ds.mWCw/CEU2JDZzw/0VJMgW6fKPtwIL56', 'otepa@gmail.com', 'admin', '2025-05-18 12:41:53', '1', '1', '2', '2', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activities_spreadsheet_id` (`spreadsheet_id`),
  ADD KEY `idx_activities_category` (`category`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spreadsheet_id` (`spreadsheet_id`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_attendance_student_id` (`student_id`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_folders_teacher_id` (`teacher_id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_spreadsheet` (`student_id`,`spreadsheet_id`),
  ADD KEY `spreadsheet_id` (`spreadsheet_id`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scores_student_id` (`student_id`),
  ADD KEY `idx_scores_activity_id` (`activity_id`);

--
-- Indexes for table `spreadsheets`
--
ALTER TABLE `spreadsheets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_spreadsheets_folder_id` (`folder_id`);

--
-- Indexes for table `spreadsheet_settings`
--
ALTER TABLE `spreadsheet_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_spreadsheet` (`spreadsheet_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spreadsheet_id` (`spreadsheet_id`),
  ADD KEY `idx_students_student_id` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_student_lrn` (`student_lrn`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `spreadsheets`
--
ALTER TABLE `spreadsheets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `spreadsheet_settings`
--
ALTER TABLE `spreadsheet_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`spreadsheet_id`) REFERENCES `spreadsheets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`spreadsheet_id`) REFERENCES `spreadsheets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`spreadsheet_id`) REFERENCES `spreadsheets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scores_ibfk_2` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spreadsheets`
--
ALTER TABLE `spreadsheets`
  ADD CONSTRAINT `spreadsheets_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `spreadsheet_settings`
--
ALTER TABLE `spreadsheet_settings`
  ADD CONSTRAINT `spreadsheet_settings_ibfk_1` FOREIGN KEY (`spreadsheet_id`) REFERENCES `spreadsheets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`spreadsheet_id`) REFERENCES `spreadsheets` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
