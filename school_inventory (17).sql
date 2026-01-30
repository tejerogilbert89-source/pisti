-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 28, 2026 at 12:17 AM
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
-- Database: `school_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `Title` varchar(20) NOT NULL,
  `Edition` varchar(20) NOT NULL,
  `status` enum('Available','Borrowed','Defective','Out of Stock') DEFAULT 'Available',
  `volume` int(10) NOT NULL,
  `available` int(10) DEFAULT 0,
  `borrowed` int(10) DEFAULT 0,
  `Author` varchar(20) NOT NULL,
  `Accession_Number` varchar(50) NOT NULL,
  `Imprint` varchar(50) NOT NULL,
  `Publisher` varchar(20) NOT NULL,
  `Shelf_Location` varchar(20) NOT NULL,
  `Call_Number` varchar(50) NOT NULL,
  `Copies` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `Title`, `Edition`, `status`, `volume`, `available`, `borrowed`, `Author`, `Accession_Number`, `Imprint`, `Publisher`, `Shelf_Location`, `Call_Number`, `Copies`) VALUES
(70, 'Sales management ', 'N/A', 'Available', 1, 1, 0, 'Bairan,Bonifacio P.', '10547:10548', 'UNLIMITED BOOKS', '2025', 'filipiniana', '658.8121 B163 2025', 1),
(71, '11111111', 'THIRD', 'Out of Stock', 0, 0, 0, 'lord', '10547:10548', 'UNLIMITED BOOKS', '2025', 'english', '23234342', 0);

-- --------------------------------------------------------

--
-- Table structure for table `borrower`
--

CREATE TABLE `borrower` (
  `borrower_id` int(7) NOT NULL,
  `first_name` varchar(15) NOT NULL,
  `middle_name` varchar(15) NOT NULL,
  `last_name` varchar(15) NOT NULL,
  `borrower_name` varchar(25) NOT NULL,
  `course` varchar(4) NOT NULL,
  `year` int(10) NOT NULL,
  `borrower_type` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrower`
--

INSERT INTO `borrower` (`borrower_id`, `first_name`, `middle_name`, `last_name`, `borrower_name`, `course`, `year`, `borrower_type`) VALUES
(1232123, '', '', '', 'gilbert tejero TEJERO', 'BSIT', 2, 'Student'),
(1324654, 'hep', 'tejero', 'hep', 'hep tejero hep', 'BSIT', 5, 'Student'),
(4567655, '', '', '', 'dsdadsa tejero huray', 'BSIT', 2, 'Student'),
(6545654, 'GILBERT', 'TEJERO', 'GUIMARY', 'GILBERT TEJERO GUIMARY', 'BSIT', 2, 'Student');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `first_name` varchar(15) NOT NULL,
  `middle_name` varchar(15) NOT NULL,
  `last_name` varchar(15) NOT NULL,
  `borrower_name` varchar(30) NOT NULL,
  `borrower_id` int(7) NOT NULL,
  `course` varchar(4) NOT NULL,
  `year` int(10) NOT NULL,
  `book_id` int(20) NOT NULL,
  `reserve_date` date NOT NULL,
  `borrower_type` varchar(10) NOT NULL,
  `reservation_id` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`first_name`, `middle_name`, `last_name`, `borrower_name`, `borrower_id`, `course`, `year`, `book_id`, `reserve_date`, `borrower_type`, `reservation_id`) VALUES
('ds', 'ds', 'ds', 'ds ds ds', 2343423, 'BSIT', 2, 71, '2026-01-27', 'Student', 0);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `borrower_id` int(7) NOT NULL,
  `book_id` int(11) NOT NULL,
  `date_borrowed` date NOT NULL,
  `date_returned` date DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `borrower_type` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `borrower_id`, `book_id`, `date_borrowed`, `date_returned`, `quantity`, `borrower_type`) VALUES
(64, 1324654, 71, '2026-01-27', '2026-01-27', 1, ''),
(65, 1232123, 71, '2026-01-27', '2026-01-27', 1, ''),
(66, 6545654, 71, '2026-01-27', '2026-01-27', 1, ''),
(67, 4567655, 71, '2026-01-27', '2026-01-27', 1, '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password` varchar(60) NOT NULL
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
-- Indexes for table `borrower`
--
ALTER TABLE `borrower`
  ADD PRIMARY KEY (`borrower_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `student_id` (`borrower_id`),
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
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`borrower_id`) REFERENCES `borrower` (`borrower_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
