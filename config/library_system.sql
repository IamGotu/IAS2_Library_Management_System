-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 27, 2025 at 06:01 PM
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

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `user_role`, `full_name`, `action`, `timestamp`) VALUES
(99, 1, 'employee', 'Employee Admin', 'Added new employee: Mark John Jopia', '2025-06-27 23:51:02'),
(100, 1, 'employee', 'Employee Admin', 'Added new customer: Mark John Jopia', '2025-06-27 23:51:35'),
(101, 1, 'employee', 'Employee Admin', 'Edited Employee with ID: 2', '2025-06-27 23:52:01'),
(102, 1, 'employee', 'Employee Admin', 'Updated book genre #9452: Book Genre Program 9452', '2025-06-27 23:52:11'),
(103, 1, 'employee', 'Employee Admin', 'Updated tag collection #8742: Tag Collection 8742', '2025-06-27 23:52:17'),
(104, 1, 'employee', 'Employee Admin', 'Added new book: Book Title 9112', '2025-06-27 23:52:27'),
(105, 1, 'employee', 'Employee Admin', 'Added new digital media: Digital Media Title 1848', '2025-06-27 23:52:30'),
(106, 1, 'employee', 'Employee Admin', 'Added new archival material: Archival Title 9051', '2025-06-27 23:52:32'),
(107, 1, 'employee', 'Employee Admin', 'Edited material ID 3 in material_books to \'Edited Material 4392\'', '2025-06-27 23:52:41'),
(108, 1, 'employee', 'Employee Admin', 'Edited material ID 4 in material_books to \'Edited Material 5931\'', '2025-06-27 23:52:46'),
(109, 1, 'employee', 'Employee Admin', 'Deleted material from material_books: Edited Material 4392 (ID: 3)', '2025-06-27 23:52:49'),
(110, 1, 'employee', 'Employee Admin', 'Edited material ID 3 in material_digital_media to \'Edited Material 8715\'', '2025-06-27 23:52:51'),
(111, 1, 'employee', 'Employee Admin', 'Deleted material from material_books: Edited Material 5931 (ID: 4)', '2025-06-27 23:52:53'),
(112, 1, 'employee', 'Employee Admin', 'Edited material ID 3 in material_research to \'Edited Material 4712\'', '2025-06-27 23:52:55'),
(113, 1, 'employee', 'Employee Admin', 'Edited material ID 5 in material_books to \'Edited Material 2281\'', '2025-06-27 23:52:57'),
(114, 1, 'employee', 'Employee Admin', 'Deleted material from material_books: Edited Material 2281 (ID: 5)', '2025-06-27 23:53:00'),
(115, 1, 'employee', 'Employee Admin', 'Reserved material ID 7 for customer ID 12', '2025-06-27 23:53:07'),
(116, 1, 'employee', 'Employee Admin', 'Borrowed material ID 7 for customer ID 12', '2025-06-27 23:53:11'),
(117, 1, 'employee', 'Employee Admin', 'Reserved material ID 7 for customer ID 12', '2025-06-27 23:53:14'),
(118, 1, 'employee', 'Employee Admin', 'Cancelled reservation of material ID 7 by customer ID 12', '2025-06-27 23:53:17'),
(119, 1, 'employee', 'Employee Admin', 'Returned material ID 7 by customer ID 12', '2025-06-27 23:53:20'),
(120, 1, 'employee', 'Employee Admin', 'Marked late fee as paid (Simulated Transaction ID: 4594 for William Williams - Amount: $95.00)', '2025-06-27 23:53:34'),
(121, 1, 'employee', 'Employee Admin', 'Waived late fee (Simulated Transaction ID: 6916 for Lisa Smith - Amount: $65.00)', '2025-06-27 23:53:36'),
(122, 1, 'employee', 'Employee Admin', 'Generated late fee report: Current outstanding fees', '2025-06-27 23:53:40'),
(123, 1, 'employee', 'Employee Admin', 'Generated returned materials report: All', '2025-06-27 23:53:53'),
(124, 1, 'employee', 'Employee Admin', 'Sent bulk email reminders for 9 loans due in 3 days', '2025-06-27 23:54:22'),
(125, 1, 'employee', 'Employee Admin', 'Renewed loan #4875 for \'The Hobbit\' (Customer: Emily Jones). New due date: 2025-08-07', '2025-06-27 23:54:32'),
(126, 1, 'employee', 'Employee Admin', 'Updated event #5349: Author Visit Event 4', '2025-06-27 23:54:42'),
(127, 1, 'employee', 'Employee Admin', 'Deleted event #1738: Workshop Event 6', '2025-06-27 23:54:50'),
(128, 1, 'employee', 'Employee Admin', 'Cancelled event #4509: Book Club Event 6', '2025-06-27 23:54:53'),
(129, 1, 'employee', 'Employee Admin', 'Submitted review for \'The Great Gatsby\' (Rating: 5/5)', '2025-06-27 23:55:10'),
(130, 1, 'employee', 'Employee Admin', 'Deleted backup: BK-2899', '2025-06-27 23:55:26'),
(131, 1, 'employee', 'Employee Admin', 'Restored backup: BK-6827', '2025-06-27 23:55:28'),
(132, 1, 'employee', 'Employee Admin', 'Initiated Full Database backup', '2025-06-27 23:55:30'),
(133, 1, 'employee', 'Employee Admin', 'Ran maintenance task: Database Optimization', '2025-06-27 23:55:32');

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
  `status` enum('Available','Archived') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_books`
--

INSERT INTO `material_books` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `quantity`, `available`, `status`) VALUES
(7, 'Book Title 9112', 'Book Author 9112', 'ISBN9112', 'Book Publisher 9112', '2025', 5, 5, 'Available');

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
(3, 'Edited Material 8715', 'Digital Media Author 8605', 'ISBN8605', 'Digital Media Publisher 8605', '2025', 'eBook', NULL, 'Available'),
(4, 'Digital Media Title 4970', 'Digital Media Author 4970', 'ISBN4970', 'Digital Media Publisher 4970', '2025', 'eBook', NULL, 'Available'),
(5, 'Digital Media Title 1848', 'Digital Media Author 1848', 'ISBN1848', 'Digital Media Publisher 1848', '2025', 'eBook', NULL, 'Available');

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
(3, 'Edited Material 4712', 'Archival Author 4794', 'ISBN4794', 'Archival Publisher 4794', '2025', 'Archived material description for Archival Title 4794', 'Available'),
(4, 'Archival Title 4689', 'Archival Author 4689', 'ISBN4689', 'Archival Publisher 4689', '2025', 'Archived material description for Archival Title 4689', 'Available'),
(5, 'Archival Title 9051', 'Archival Author 9051', 'ISBN9051', 'Archival Publisher 9051', '2025', 'Archived material description for Archival Title 9051', 'Available');

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
(17, 'book', 3, 12, 'Reserve', 'Reserved', '2025-06-27 20:57:20'),
(18, 'book', 3, 12, 'Borrow', 'Reserved', '2025-06-27 20:57:33'),
(19, 'book', 3, 12, 'Return', 'Reserved', '2025-06-27 20:57:39'),
(21, 'book', 7, 12, 'Borrow', 'Reserved', '2025-06-27 23:53:10'),
(22, 'book', 7, 12, 'Reserve', 'Reserved', '2025-06-27 23:53:14'),
(23, 'book', 7, 12, 'Return', 'Reserved', '2025-06-27 23:53:20');

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
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `material_digital_media`
--
ALTER TABLE `material_digital_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `material_research`
--
ALTER TABLE `material_research`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `material_transactions`
--
ALTER TABLE `material_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

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
