-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 06, 2025 at 03:37 PM
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
-- Table structure for table `backup_history`
--

CREATE TABLE `backup_history` (
  `id` int(11) NOT NULL,
  `backup_id` varchar(50) NOT NULL,
  `backup_type` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Completed',
  `initiated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_history`
--

INSERT INTO `backup_history` (`id`, `backup_id`, `backup_type`, `file_path`, `file_size`, `status`, `initiated_by`, `created_at`) VALUES
(4, 'BK-686A43AECA50D', 'Full Database', '../../backups/library_system_2025-07-06_11-36-46.sql.gz', 0.00, 'Completed', 1, '2025-07-06 09:36:46');

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
-- Table structure for table `book_reviews`
--

CREATE TABLE `book_reviews` (
  `review_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('employee','customer') NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `review_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_reviews`
--

INSERT INTO `book_reviews` (`review_id`, `book_id`, `user_id`, `user_role`, `rating`, `review_text`, `review_date`) VALUES
(1, 11, 1, 'employee', 5, '', '2025-07-06 16:57:46');

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
(17, 'Member', '', 'Youth', '', '', 'Sinawal', 'General Santos City', '9500', '09514810123', '2010-06-16', 'memberyouth@gmail.com', '$2y$10$T9lZDrp3JGqeCjHBKrBn6.XtIjw7FX05Wg5uEddJA6.D.okZq2rzW', 7, 'Active'),
(18, 'Member', '', 'Adult', '', '', 'Sinawal', 'General Santos City', '9500', '09514810124', '2001-06-16', 'memberadult@gmail.com', '$2y$10$O0efnkInQESn7ummpqu9JOGuRB5q09BT12jsCD7uxrKWkz11tnMZS', 8, 'Active'),
(19, 'Member', '', 'Senior', '', '', 'Sinawal', 'General Santos City', '9500', '09514810125', '1965-06-14', 'membersenior@gmail.com', '$2y$10$xSjbKaTZEm/ZrypFuY9/Y.hBOjN9Qj5xR5g/fUoQGg3NRhI8IaawO', 9, 'Active'),
(20, 'Member', '', 'Researcher', '', '', 'Sinawal', 'General Santos City', '9500', '09514810126', '2001-06-14', 'researcher@gmail.com', '$2y$10$/D5MMAPH8XTKXew8K.HIaOVeNowHxDMHLbQc45wr0L8gnmrf1wAAS', 10, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `digital_media_reviews`
--

CREATE TABLE `digital_media_reviews` (
  `review_id` int(11) NOT NULL,
  `media_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('employee','customer') NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `review_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `location` varchar(100) NOT NULL,
  `max_capacity` int(11) NOT NULL,
  `registered_count` int(11) DEFAULT 0,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
  `organizer` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `description`, `event_type`, `start_datetime`, `end_datetime`, `location`, `max_capacity`, `registered_count`, `status`, `organizer`, `created_at`, `updated_at`) VALUES
(8, 'asda', 'asd', 'Book Reading', '2025-07-06 12:00:00', '2025-07-06 12:00:00', 'Main Hall', 20, 0, 'Upcoming', 'asd', '2025-07-06 16:46:57', '2025-07-06 16:46:57');

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
  `genre` varchar(255) DEFAULT NULL,
  `average_rating` decimal(3,1) DEFAULT 0.0,
  `review_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_books`
--

INSERT INTO `material_books` (`id`, `title`, `author`, `isbn`, `publisher`, `year_published`, `quantity`, `available`, `status`, `genre`, `average_rating`, `review_count`) VALUES
(10, 'Sample book', 'Sample book', 'Sample book', 'Sample book', '2001', 10, 9, 'Available', 'Science Fiction', 0.0, 0),
(11, 'book', 'book', 'book', 'book', '2002', 5, 5, 'Available', 'Science Fiction', 0.0, 0);

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
  `status` enum('Reserved','Borrowed','Returned','Cancelled','Overdue') DEFAULT 'Reserved',
  `action_date` datetime DEFAULT current_timestamp(),
  `due_date` datetime DEFAULT NULL,
  `return_date` datetime DEFAULT NULL,
  `late_fee` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `material_transactions`
--

INSERT INTO `material_transactions` (`transaction_id`, `material_type`, `material_id`, `customer_id`, `action`, `status`, `action_date`, `due_date`, `return_date`, `late_fee`) VALUES
(87, 'digital', 7, 17, 'Request Access', 'Reserved', '2025-07-06 19:55:16', NULL, NULL, 0.00),
(89, 'book', 10, 17, 'Borrow', 'Reserved', '2025-07-06 20:05:27', '2025-07-13 14:05:27', NULL, 0.00),
(91, 'book', 10, 18, 'Borrow', 'Returned', '2025-07-06 20:08:47', '2025-07-13 14:08:47', '2025-07-06 14:30:52', 0.00),
(93, 'book', 11, 17, 'Borrow', 'Returned', '2025-06-15 20:18:53', '2025-06-22 14:18:53', '2025-07-06 14:27:42', 700.00);

-- --------------------------------------------------------

--
-- Table structure for table `payment_receipts`
--

CREATE TABLE `payment_receipts` (
  `receipt_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `receipt_number` varchar(20) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `receipt_content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_receipts`
--

INSERT INTO `payment_receipts` (`receipt_id`, `transaction_id`, `receipt_number`, `payment_amount`, `payment_method`, `receipt_content`, `created_at`) VALUES
(1, 77, 'RCPT-000077', 50.00, 'cash', '\n        <h3>Library Payment Receipt</h3>\n        <p>Receipt #: RCPT-000077</p>\n        <p>Date: 2025-07-06 09:53:38</p>\n        <hr>\n        <p><strong>Customer:</strong> Member Adult</p>\n        <p><strong>Material:</strong> Sample book (book)</p>\n        <p><strong>Transaction ID:</strong> 77</p>\n        <hr>\n        <p><strong>Payment Method:</strong> cash</p>\n        <p><strong>Amount Paid:</strong> ₱100</p>\n        <p><strong>Late Fee:</strong> ₱50.00</p>\n        <hr>\n        <p>Thank you for your payment!</p>\n        <p>Library System</p>\n    ', '2025-07-06 15:53:38'),
(2, 79, 'RCPT-000079', 350.00, 'cash', '\r\n        <h3>Library Payment Receipt</h3>\r\n        <p>Receipt #: RCPT-000079</p>\r\n        <p>Date: 2025-07-06 10:08:17</p>\r\n        <hr>\r\n        <p><strong>Customer:</strong> Member Senior</p>\r\n        <p><strong>Material:</strong> Sample book (book)</p>\r\n        <p><strong>Transaction ID:</strong> 79</p>\r\n        <hr>\r\n        <p><strong>Payment Method:</strong> cash</p>\r\n        <p><strong>Amount Paid:</strong> ₱354</p>\r\n        <p><strong>Late Fee:</strong> ₱350.00</p>\r\n        <hr>\r\n        <p>Thank you for your payment!</p>\r\n        <p>Library System</p>\r\n    ', '2025-07-06 16:08:17'),
(3, 93, 'RCPT-000093', 700.00, 'cash', '\r\n        <h3>Library Payment Receipt</h3>\r\n        <p>Receipt #: RCPT-000093</p>\r\n        <p>Date: 2025-07-06 14:27:42</p>\r\n        <hr>\r\n        <p><strong>Customer:</strong> Member Youth</p>\r\n        <p><strong>Material:</strong> book (book)</p>\r\n        <p><strong>Transaction ID:</strong> 93</p>\r\n        <hr>\r\n        <p><strong>Payment Method:</strong> cash</p>\r\n        <p><strong>Amount Paid:</strong> ₱700</p>\r\n        <p><strong>Late Fee:</strong> ₱700.00</p>\r\n        <hr>\r\n        <p>Thank you for your payment!</p>\r\n        <p>Library System</p>\r\n    ', '2025-07-06 20:27:42');

-- --------------------------------------------------------

--
-- Table structure for table `research_material_reviews`
--

CREATE TABLE `research_material_reviews` (
  `review_id` int(11) NOT NULL,
  `research_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_role` enum('employee','customer') NOT NULL,
  `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_text` text DEFAULT NULL,
  `review_date` datetime DEFAULT current_timestamp()
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
-- Indexes for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `initiated_by` (`initiated_by`);

--
-- Indexes for table `book_genres`
--
ALTER TABLE `book_genres`
  ADD PRIMARY KEY (`genre_id`),
  ADD UNIQUE KEY `genre_name` (`genre_name`);

--
-- Indexes for table `book_reviews`
--
ALTER TABLE `book_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone_num` (`phone_num`),
  ADD KEY `customer_role` (`role_id`);

--
-- Indexes for table `digital_media_reviews`
--
ALTER TABLE `digital_media_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `media_id` (`media_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone_num` (`phone_num`),
  ADD KEY `employee_role` (`role_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

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
-- Indexes for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD PRIMARY KEY (`receipt_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `research_material_reviews`
--
ALTER TABLE `research_material_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `research_id` (`research_id`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=349;

--
-- AUTO_INCREMENT for table `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `book_genres`
--
ALTER TABLE `book_genres`
  MODIFY `genre_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `book_reviews`
--
ALTER TABLE `book_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `digital_media_reviews`
--
ALTER TABLE `digital_media_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  MODIFY `receipt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `research_material_reviews`
--
ALTER TABLE `research_material_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `backup_history`
--
ALTER TABLE `backup_history`
  ADD CONSTRAINT `backup_history_ibfk_1` FOREIGN KEY (`initiated_by`) REFERENCES `employees` (`employee_id`);

--
-- Constraints for table `book_reviews`
--
ALTER TABLE `book_reviews`
  ADD CONSTRAINT `book_reviews_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `material_books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `customer_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `digital_media_reviews`
--
ALTER TABLE `digital_media_reviews`
  ADD CONSTRAINT `digital_media_reviews_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `material_digital_media` (`id`) ON DELETE CASCADE;

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

--
-- Constraints for table `research_material_reviews`
--
ALTER TABLE `research_material_reviews`
  ADD CONSTRAINT `research_material_reviews_ibfk_1` FOREIGN KEY (`research_id`) REFERENCES `material_research` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
