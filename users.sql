-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 08, 2025 at 04:40 AM
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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
