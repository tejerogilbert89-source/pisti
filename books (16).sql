-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 17, 2026 at 09:38 AM
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
  `Call_Number` varchar(25) NOT NULL,
  `Title` varchar(20) NOT NULL,
  `Author` varchar(20) NOT NULL,
  `Edition` varchar(20) NOT NULL,
  `Imprint` varchar(50) NOT NULL,
  `Accession_Number` varchar(50) NOT NULL,
  `Copies` int(10) NOT NULL,
  `Shelf_Location` varchar(20) NOT NULL,
  `status` enum('Available','Borrowed','Defective','Out of Stock') NOT NULL,
  `Publisher` varchar(20) NOT NULL,
  `volume` int(11) NOT NULL,
  `available` int(11) NOT NULL,
  `borrowed` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `Call_Number`, `Title`, `Author`, `Edition`, `Imprint`, `Accession_Number`, `Copies`, `Shelf_Location`, `status`, `Publisher`, `volume`, `available`, `borrowed`) VALUES
(0, '658.8121 B163 2025', 'The Wonders of World', 'Bairan,Bonifacio P.', 'N/A', 'UNLIMITED BOOKS', '10547:10548', 0, 'filipiniana', 'Out of Stock', '2025', 1, 0, 0);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
