-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 11, 2026 at 07:58 AM
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
-- Database: `school_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `book_name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `status` enum('Available','Borrowed','Defective','Out of Stock') DEFAULT 'Available',
  `volume` int(11) NOT NULL,
  `available` int(11) DEFAULT 0,
  `borrowed` int(11) DEFAULT 0,
  `ISBN` int(15) NOT NULL,
  `Author` varchar(255) NOT NULL,
  `Accession_Number` int(15) NOT NULL,
  `Copy` varchar(255) NOT NULL,
  `Masterlist` varchar(255) NOT NULL,
  `Book_Year` int(254) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `book_name`, `category`, `status`, `volume`, `available`, `borrowed`, `ISBN`, `Author`, `Accession_Number`, `Copy`, `Masterlist`, `Book_Year`) VALUES
(15, 'The Wonders of World', 'Fantasy', 'Available', 5, 0, 0, 2147483647, '4234', 0, '', '', 0),
(22, 'gilbertgwapo', 'supergwapo', 'Available', 1, 0, 0, 1234567, 'blabla', 0, '', '', 0),
(23, 'lord', 'lord', 'Available', 2, 0, 0, 1234567, 'lord', 1021212, '2', 'fasadas', 2005),
(24, '1', '2', 'Available', 5, 0, 0, 4, '3', 6, '7', '9', 8),
(25, 'honeylove', 'love', 'Available', 1, 0, 0, 123123123, 'gilbert', 0, '', '', 0),
(26, '1111', '1111', '', 1, 0, 0, 1111, '1111', 1234567, '2', '9', 2006),
(27, '2222', '2222', 'Available', 2, 0, 0, 2222, '2222', 0, '', '', 0),
(28, '3333', '3333', 'Available', 1, 0, 0, 3333, '3333', 3333, '3333', '3333', 3333);

-- --------------------------------------------------------

--
-- Table structure for table `book_copies`
--

CREATE TABLE `book_copies` (
  `copy_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `copy_number` varchar(20) NOT NULL,
  `status` enum('Available','Borrowed','Damaged','Lost') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `borrow_book`
--

CREATE TABLE `borrow_book` (
  `Student_ID` int(7) NOT NULL,
  `Student_NAME` varchar(255) NOT NULL,
  `course` varchar(255) NOT NULL,
  `year` int(15) NOT NULL,
  `book_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrow_book`
--

INSERT INTO `borrow_book` (`Student_ID`, `Student_NAME`, `course`, `year`, `book_name`) VALUES
(2340248, 'Zenochie', 'BSIT', 1, ''),
(2340248, 'Zenochie', 'BSIT', 2, ''),
(2340248, 'Zenochie', 'BSIT', 2, ''),
(2340248, 'Zenochie', 'BSIT', 2, '');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `student_name` varchar(254) NOT NULL,
  `student_id` int(7) NOT NULL,
  `course` varchar(255) NOT NULL,
  `year` int(15) NOT NULL,
  `book_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`student_name`, `student_id`, `course`, `year`, `book_id`) VALUES
('', 1234567, '', 0, 28),
('', 1234567, '', 0, 27),
('gilbert', 1234567, 'BSIT', 3, 25),
('gdgd', 1234567, 'BSBA', 2, 24);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(7) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `course` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_name`, `course`, `phone_number`, `year`) VALUES
(1, '1', '1', '', 1),
(1234567, 'gilbert', 'BSIT', '', 2),
(1235234, 'viscara', 'abscbn', '', 5),
(1236433, 'kenji', 'BSHS', '', 4),
(2340248, 'Zenochie', 'BSIT', '09171234567', 2),
(7654321, 'GILBERT', 'BSBA', '', 3),
(24235353, 'no', 'no', '', 23112),
(1234444445, 'jehup', 'BSBA', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `student_id` int(7) NOT NULL,
  `book_id` int(11) NOT NULL,
  `date_borrowed` date NOT NULL,
  `date_returned` date DEFAULT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `student_id`, `book_id`, `date_borrowed`, `date_returned`, `quantity`) VALUES
(4, 2340248, 15, '2026-01-07', '2026-01-07', 1),
(7, 1234567, 15, '2026-01-07', '2026-01-07', 1),
(9, 1234567, 22, '2026-01-07', '2026-01-09', 1),
(13, 1234567, 15, '2026-01-09', NULL, 1),
(15, 1236433, 23, '2026-01-09', '2026-01-09', 1),
(16, 1235234, 22, '2026-01-09', NULL, 1),
(17, 24235353, 23, '2026-01-09', '2026-01-09', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$CIeUHawzrUzuBr9Jc4wuleC.VfkxOLJDp/402zU8XGExihnViHAtG');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`);

--
-- Indexes for table `book_copies`
--
ALTER TABLE `book_copies`
  ADD PRIMARY KEY (`copy_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `book_copies`
--
ALTER TABLE `book_copies`
  MODIFY `copy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `book_copies`
--
ALTER TABLE `book_copies`
  ADD CONSTRAINT `book_copies_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
