-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 27, 2025 at 02:13 PM
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
-- Database: `library_system`
--
CREATE DATABASE IF NOT EXISTS `library_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `library_system`;

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `purok` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `phone_num` varchar(225) NOT NULL,
  `birthdate` date NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`customer_id`, `first_name`, `middle_name`, `last_name`, `purok`, `street`, `barangay`, `city`, `postal_code`, `phone_num`, `birthdate`, `email`, `password`, `role_id`, `status`) VALUES
(12, 'Member', '', 'Youth', '', '', 'Sinawal', 'General Santos City', '9500', '09514810123', '2015-06-14', 'memberyouth@gmail.com', '$2y$10$fw9BGEVqlxQ8Qt3ooX3xj.lOiWxZ0Pj80sTuCmqRjC/NrP49LCTSC', 7, 'Active'),
(13, 'Member', '', 'Adult', '', '', 'Sinawal', 'General Santos City', '9500', '09514810124', '2001-06-14', 'memberadult@gmail.com', '$2y$10$0ArR231.1duc2WGrhyiYNu.2.lm5k.eQ6AMf8vsTRB2ylwh0kOoqS', 8, 'Active'),
(14, 'Member', '', 'Senior', '', '', 'Sinawal', 'General Santos City', '9500', '09514810125', '1960-06-14', 'membersenior@gmail.com', '$2y$10$O3v63n6mVZbYS561wf0SieSa5i.gDuKyO/VI1htNHAJpsZ8ncMMz.', 9, 'Active'),
(15, 'Member', '', 'Researcher', '', '', 'Sinawal', 'General Santos City', '9500', '09514810126', '2001-06-14', 'researcher@gmail.com', '$2y$10$d/2KorfA32oTbDVd9lNzje.8j.KOauaLkFGEAAQGjzu9Z3k9CKCyq', 10, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `purok` varchar(255) DEFAULT NULL,
  `street` varchar(255) DEFAULT NULL,
  `barangay` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `phone_num` varchar(255) NOT NULL,
  `birthdate` date NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `first_name`, `middle_name`, `last_name`, `purok`, `street`, `barangay`, `city`, `postal_code`, `phone_num`, `birthdate`, `email`, `password`, `role_id`, `status`) VALUES
(1, 'Employee', '', 'Admin', '', NULL, 'Sinawal', 'General Santos City', '9500', '09514810123', '2001-06-14', 'admin@gmail.com', '$2y$10$xAe.E9rbbJFq..f9FfTbH.TLMNg8zLoVbKQwLUiiXSg7gQ6ZVih0O', 1, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `material_books`
--

CREATE TABLE `material_books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `year_published` year(4) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `available` int(11) DEFAULT 1,
  `status` enum('Available','Archived') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_books`
--

INSERT INTO `material_books` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `quantity`, `available`, `status`) VALUES
(3, 'Book Title 1722', 'Book Author 1722', 'ISBN1722', 'Book Publisher 1722', '2025', 5, 5, 'Available'),
(4, 'Book Title 4387', 'Book Author 4387', 'ISBN4387', 'Book Publisher 4387', '2025', 5, 5, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `material_digital_media`
--

CREATE TABLE `material_digital_media` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `year_published` year(4) DEFAULT NULL,
  `media_type` enum('eBook','Audiobook') DEFAULT 'eBook',
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('Available','Archived') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_digital_media`
--

INSERT INTO `material_digital_media` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `media_type`, `file_path`, `status`) VALUES
(3, 'Digital Media Title 8605', 'Digital Media Author 8605', 'ISBN8605', 'Digital Media Publisher 8605', '2025', 'eBook', NULL, 'Available'),
(4, 'Digital Media Title 4970', 'Digital Media Author 4970', 'ISBN4970', 'Digital Media Publisher 4970', '2025', 'eBook', NULL, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `material_research`
--

CREATE TABLE `material_research` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `year_published` year(4) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Available','Archived') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_research`
--

INSERT INTO `material_research` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `description`, `status`) VALUES
(3, 'Archival Title 4794', 'Archival Author 4794', 'ISBN4794', 'Archival Publisher 4794', '2025', 'Archived material description for Archival Title 4794', 'Available'),
(4, 'Archival Title 4689', 'Archival Author 4689', 'ISBN4689', 'Archival Publisher 4689', '2025', 'Archived material description for Archival Title 4689', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `material_transactions`
--

CREATE TABLE `material_transactions` (
  `transaction_id` int(11) NOT NULL,
  `material_type` enum('book','digital','research') NOT NULL,
  `material_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action` enum('Reserve','Borrow','Return') NOT NULL,
  `status` enum('Reserved','Borrowed','Returned','Cancelled') DEFAULT 'Reserved',
  `action_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_transactions`
--

INSERT INTO `material_transactions` (`transaction_id`, `material_type`, `material_id`, `customer_id`, `action`, `status`, `action_date`) VALUES
(8, 'book', 3, 12, 'Borrow', 'Returned', '2025-06-27 19:32:01'),
(10, 'book', 3, 12, 'Borrow', 'Reserved', '2025-06-27 19:48:05'),
(11, 'book', 3, 12, 'Return', 'Reserved', '2025-06-27 19:58:52');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'Library Admin', 'Employee'),
(2, 'Librarian', 'Employee'),
(3, 'Assistant Librarian', 'Employee'),
(4, 'Volunteer', 'Employee'),
(5, 'Content Curator', 'Employee'),
(6, 'IT Personnel', 'Employee'),
(7, 'Member (Youth)', 'Customer'),
(8, 'Member (Adult)', 'Customer'),
(9, 'Member (Senior)', 'Customer'),
(10, 'Researcher', 'Customer');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone_num` (`phone_num`),
  ADD KEY `customer_role` (`role_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone_num` (`phone_num`),
  ADD KEY `employee_role` (`role_id`);

--
-- Indexes for table `material_books`
--
ALTER TABLE `material_books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- Indexes for table `material_digital_media`
--
ALTER TABLE `material_digital_media`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- Indexes for table `material_research`
--
ALTER TABLE `material_research`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- Indexes for table `material_transactions`
--
ALTER TABLE `material_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `material_books`
--
ALTER TABLE `material_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `material_digital_media`
--
ALTER TABLE `material_digital_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `material_research`
--
ALTER TABLE `material_research`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `material_transactions`
--
ALTER TABLE `material_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `customer_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employee_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `material_transactions`
--
ALTER TABLE `material_transactions`
  ADD CONSTRAINT `material_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
