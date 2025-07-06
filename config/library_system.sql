-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 06, 2025 at 08:10 AM
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
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('employee','customer') NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `action` text NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `book_genres`
--

CREATE TABLE `book_genres` (
  `genre_id` int(11) NOT NULL,
  `genre_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_genres`
--

INSERT INTO `book_genres` (`genre_id`, `genre_name`, `description`) VALUES
(1, 'Science Fiction', NULL);

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
(1, 'Employee', '', 'Admin', '', NULL, 'Sinawal', 'General Santos City', '9500', '09514810123', '2001-06-14', 'admin@gmail.com', '$2y$10$xAe.E9rbbJFq..f9FfTbH.TLMNg8zLoVbKQwLUiiXSg7gQ6ZVih0O', 1, 'Active'),
(2, 'Libby', NULL, 'Librarian', '1', 'Main St', 'Lagao', 'General Santos City', '9500', '09171234567', '1990-05-10', 'librarian@gmail.com', '$2y$10$a9p28CsJApzts/HnMKJa6u6k9B3vqhCplpGp3AXm87ECdszlodv3u', 2, 'Active'),
(3, 'Asher', NULL, 'Librarian', '2', 'Second St', 'Bula', 'General Santos City', '9500', '09181234567', '1992-07-15', 'assistantlibrarian@gmail.com', '$2y$10$kFOUahup1XzoXlhcH0jSDOF1Z9l4rSu4GjARVyoHWEMmdiXSXhRnK', 3, 'Active'),
(4, 'Vince', NULL, 'Volunteer', '3', 'Volunteer Ave', 'Baluan', 'General Santos City', '9500', '09191234567', '1995-08-20', 'volunteer@gmail.com', '$2y$10$WPuKWudiuZ8km.yWu/vjyuzF3ZBlIUTtMifbkoHa5eEBDMZF329ve', 4, 'Active'),
(5, 'Cora', NULL, 'Curator', '4', 'Curate St', 'San Isidro', 'General Santos City', '9500', '09201234567', '1988-03-05', 'contentcurator@gmail.com', '$2y$10$gGFeqddnjBnFQ07Sty8iWOCwsvPZ9s9MUkTboXRTgUatqZudNpshG', 5, 'Active'),
(6, 'Ivan', NULL, 'Tech', '5', 'IT Lane', 'Conel', 'General Santos City', '9500', '09211234567', '1993-12-25', 'itpersonnel@gmail.com', '$2y$10$2olH3zhdByowU4Ij46DEpObqNVQ9/BcBvOvOx6qMLtshm7zlXt0sW', 6, 'Active');

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
  `status` enum('Available','Archived') DEFAULT 'Available',
  `genre` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_books`
--

INSERT INTO `material_books` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `quantity`, `available`, `status`, `genre`) VALUES
(10, 'Sample book', 'Sample book', 'Sample book', 'Sample book', '2001', 10, 10, 'Available', 'Science Fiction'),
(11, 'book', 'book', 'book', 'book', '2002', 5, 5, 'Available', 'Science Fiction');

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
  `status` enum('Available','Archived') DEFAULT 'Available',
  `tags` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_digital_media`
--

INSERT INTO `material_digital_media` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `media_type`, `file_path`, `status`, `tags`) VALUES
(7, 'Sample media', 'Sample media', 'Sample media', 'Sample media', '2001', 'eBook', NULL, 'Available', NULL);

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
  `status` enum('Available','Archived') DEFAULT 'Available',
  `collection` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_research`
--

INSERT INTO `material_research` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `description`, `status`, `collection`) VALUES
(14, 'Sample archive', 'Sample archive', 'Sample archive', 'Sample archive', '2001', 'Sample archive', 'Available', 'Sample archive');

-- --------------------------------------------------------

--
-- Table structure for table `material_tags`
--

CREATE TABLE `material_tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `material_transactions`
--

CREATE TABLE `material_transactions` (
  `transaction_id` int(11) NOT NULL,
  `material_type` enum('book','digital','research') NOT NULL,
  `material_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `action` enum('Reserve','Borrow','Return','Request Access','Approve Access','Grant Access') NOT NULL,
  `status` enum('Reserved','Borrowed','Returned','Cancelled') DEFAULT 'Reserved',
  `action_date` datetime DEFAULT current_timestamp(),
  `due_date` datetime DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `late_fee` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `book_genres`
--
ALTER TABLE `book_genres`
  ADD PRIMARY KEY (`genre_id`),
  ADD UNIQUE KEY `genre_name` (`genre_name`);

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
-- Indexes for table `material_tags`
--
ALTER TABLE `material_tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`);

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=259;

--
-- AUTO_INCREMENT for table `book_genres`
--
ALTER TABLE `book_genres`
  MODIFY `genre_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `material_books`
--
ALTER TABLE `material_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `material_digital_media`
--
ALTER TABLE `material_digital_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `material_research`
--
ALTER TABLE `material_research`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `material_tags`
--
ALTER TABLE `material_tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `material_transactions`
--
ALTER TABLE `material_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

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
