<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * @package    Joomla.Site
 *
 * @copyright  (C) 2005 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Define the application's minimum supported PHP version as a constant so it can be referenced within the application.
 */
define('JOOMLA_MINIMUM_PHP', '5.3.10');

if (version_compare(PHP_VERSION, JOOMLA_MINIMUM_PHP, '<')) {
    die('Your host needs to use PHP ' . JOOMLA_MINIMUM_PHP . ' or higher to run this version of Joomla!');
}

// Saves the start time and memory usage.
$startTime = microtime(1);
$startMem  = memory_get_usage();

/**
 * Constant that is checked in included files to prevent direct access.
 * define() is used in the installation folder rather than "const" to not error for PHP 5.2 and lower
 */
define('_JEXEC', 1);

if (file_exists(__DIR__ . '/defines.php')) {
    include_once __DIR__ . '/defines.php';
}

if (!defined('_JDEFINES')) {
    define('JPATH_BASE', __DIR__);
    require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_BASE . '/includes/framework.php';

// Set profiler start time and memory usage and mark afterLoad in the profiler.
JDEBUG ? JProfiler::getInstance('Application')->setStart($startTime, $startMem)->mark('afterLoad') : null;

// Function to create an index of all files (images and PDFs) in the images directory and its subdirectories
function createFileIndex($directory, &$fileIndex) {
    $allowedExtensions = ['jpg', 'jpeg', 'gif', 'png', 'pdf']; // Add 'pdf' to the allowed extensions
    $files = scandir($directory);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $directory . '/' . $file;
            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
            if (is_file($filePath) && in_array($fileExtension, $allowedExtensions)) {
                // Check if the file is in the "images/assets" directory and skip it if true
                if (strpos($filePath, '/images/assets/') !== false) {
                    continue;
                }
                $fileIndex[] = $filePath;
            } elseif (is_dir($filePath)) {
                // Check if the directory is the "images/assets" directory and skip it if true - suggest moving image files like logos / footer images etc here and ignore them
                if (strpos($filePath, '/images/assets/') !== false) {
                    continue;
                }
                createFileIndex($filePath, $fileIndex);
            }
        }
    }
}

// Function to check if an image is used in any Joomla article
function isImageUsedInArticle($imageName) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
        ->select('id')
        ->from('#__content')
        ->where("`images` LIKE '%$imageName%' OR `introtext` LIKE '%$imageName%' OR `fulltext` LIKE '%$imageName%'");

    $db->setQuery($query);
    $articleIds = $db->loadColumn();

    return !empty($articleIds);
}

// Function to check if an image is used in the Event Booking (EB) component and event_end_date is 2 years before today date - if you dont use EB just remove this function.
function isImageUsedInEB($imageName) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
        ->select('id')
        ->from('#__eb_events')
        ->where("`description` LIKE '%$imageName%' OR `thumb` LIKE '%$imageName%'");

    // Add the condition for event_end_date 2 years before today's date
    $twoYearsAgo = date('Y-m-d', strtotime('-2 years'));
    $query->where("event_end_date <= '$twoYearsAgo'");

    $db->setQuery($query);
    $ebIds = $db->loadColumn();

    return !empty($ebIds);
}

// Function to check if a PDF file is used in any Joomla article
function isPDFUsedInArticle($pdfName) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
        ->select('id')
        ->from('#__content')
        ->where("`images` LIKE '%$pdfName%' OR `introtext` LIKE '%$pdfName%' OR `fulltext` LIKE '%$pdfName%'");

    $db->setQuery($query);
    $articleIds = $db->loadColumn();

    return !empty($articleIds);
}

// Function to check if a PDF file is used in the Event Booking (EB) component and event_end_date is 2 years before today date
function isPDFUsedInEB($pdfName) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true)
        ->select('id')
        ->from('#__eb_events')
        ->where("`description` LIKE '%$pdfName%' OR `attachment` LIKE '%$pdfName%'");

    // Add the condition for event_end_date 2 years before today's date
    $twoYearsAgo = date('Y-m-d', strtotime('-2 years'));
    $query->where("event_end_date <= '$twoYearsAgo'");

    $db->setQuery($query);
    $ebIds = $db->loadColumn();

    return !empty($ebIds);
}

// Function to move unused files (images and PDFs) to the unused directory
function moveUnusedFiles($fileIndex, $destinationFolder) {
    $movedFiles = [];

    foreach ($fileIndex as $file) {
        $fileName = basename($file);
        $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

        // Check if the file is an image
        if (in_array($fileExtension, ['jpg', 'jpeg', 'gif', 'png'])) {
            // Check if the image is used in any Joomla article or Event Booking
            $fileUsedInArticle = isImageUsedInArticle($fileName);
            $fileUsedInEB = isImageUsedInEB($fileName);

            if (!$fileUsedInArticle && !$fileUsedInEB) {
                $destinationPath = $destinationFolder . '/' . $fileName;
                if (rename($file, $destinationPath)) {
                    $movedFiles[] = $file;
                    echo "Moved image: $fileName\n";
                    echo "Source path: $file\n";
                    echo "Destination path: $destinationPath\n";
                } else {
                    echo "Failed to move image: $fileName\n";
                    echo "Source path: $file\n";
                    echo "Destination path: $destinationPath\n";
                }
            } else {
                echo "Image already used or file not moved: $fileName\n";
                echo "Source path: $file\n";
            }
        }

        // Check if the file is a PDF
        if ($fileExtension === 'pdf') {
            // Check if the PDF is used in any Joomla article or Event Booking
            $fileUsedInArticle = isPDFUsedInArticle($fileName);
            $fileUsedInEB = isPDFUsedInEB($fileName);

            if (!$fileUsedInArticle && !$fileUsedInEB) {
                $destinationPath = $destinationFolder . '/' . $fileName;
                if (rename($file, $destinationPath)) {
                    $movedFiles[] = $file;
                    echo "Moved PDF: $fileName\n";
                    echo "Source path: $file\n";
                    echo "Destination path: $destinationPath\n";
                } else {
                    echo "Failed to move PDF: $fileName\n";
                    echo "Source path: $file\n";
                    echo "Destination path: $destinationPath\n";
                }
            } else {
                echo "PDF already used or file not moved: $fileName\n";
                echo "Source path: $file\n";
            }
        }
    }

    return $movedFiles;
}

// Main script
function main() {
    $fileIndex = [];

    // Adjust the paths to match the actual directories - you can comment out yootheme if you dont use YTP templates
    createFileIndex(JPATH_BASE . '/templates/yootheme/cache', $fileIndex);
    createFileIndex(JPATH_BASE . '/images', $fileIndex);

    // Specify the destination folder outside the website directory - change this to what you require
    $destinationFolder = '/home/xxxx/xxxx/images/unused';

    if (!is_dir($destinationFolder)) {
        mkdir($destinationFolder, 0755, true);
    }

    $movedFiles = moveUnusedFiles($fileIndex, $destinationFolder);

    // Create a report of moved files (images and PDFs)
    $fileReport = "Moved Files:\n";
    $fileReport .= implode("\n", $movedFiles);

    // Save the file report to a file
    file_put_contents(JPATH_BASE . '/cleanup_file_report.txt', $fileReport);

    // Notify the administrator about the cleanup process and the report.
    $fileSubject = "Unused Files Cleanup Report";
    $adminEmail = "myemail@myemail.com"; // Replace with the actual admin email address
    mail($adminEmail, $fileSubject, $fileReport);
}

// Run the script
main();
