<?php
// Enable error reporting and logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);

// Log script start
error_log("Starting csv_upload.php execution at " . date('Y-m-d H:i:s'));

// Database connection details
$host = "localhost";
$username = "abuhurei_wp6328";
$password = "Sonik9200";
$database = "abuhurei_wp6328";

// Attempt to connect to the database
$connection = mysqli_connect($host, $username, $password, $database);
if (!$connection) {
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    echo '<div class="alert alert-danger">Unable to connect to the database. Please try again later.</div>';
    exit;
}

// Set charset to utf8mb4
if (!mysqli_set_charset($connection, 'utf8mb4')) {
    error_log("Error setting charset: " . mysqli_error($connection));
}

error_log("Database connection successful");

// Define expected subjects for each table
$subjectsExamTables = [
    'English', 'Mathematics', 'Kiswahili', 'Ire', 'Business', 'History',
    'Chemistry', 'Physics', 'Biology', 'Literature', 'Arabic', 'Computer'
];

// Define expected columns for each table (excluding auto-incremented `id`)
$expectedColumnsExamTables = array_merge(
    ['Password', 'Name', 'Class', 'examname', 'Grade', 'total_points', 'Total', 'Average'],
    $subjectsExamTables
);

// Initialize variables for feedback
$message = '';
$successCount = 0;
$skipCount = 0;
$failCount = 0;
$totalRows = 0;

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && isset($_POST['table']) && isset($_POST['examname']) && isset($_POST['delimiter'])) {
    $targetTable = $_POST['table'];
    $examName = trim($_POST['examname']);
    $delimiter = $_POST['delimiter'];

    // Map delimiter selection to actual character
    $delimiterMap = [
        'comma' => ',',
        'semicolon' => ';',
        'tab' => "\t"
    ];
    $delimiter = $delimiterMap[$delimiter] ?? ',';

    // Validate target table
    $validTables = ['examone', 'examtwo', 'examthree', 'examfour'];
    if (!in_array($targetTable, $validTables)) {
        $message = "Invalid table selected.";
        error_log("Invalid table selected: $targetTable");
    } elseif (empty($examName)) {
        $message = "Please provide an exam name.";
        error_log("Exam name is empty");
    } else {
        // Check if table exists
        $result = mysqli_query($connection, "SHOW TABLES LIKE '$targetTable'");
        if (!$result || mysqli_num_rows($result) == 0) {
            $message = "Table '$targetTable' does not exist in the database.";
            error_log("Table '$targetTable' does not exist");
        } else {
            // Validate file
            $file = $_FILES['csv_file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $message = "Error uploading file: " . $file['error'];
                error_log("File upload error: " . $file['error']);
            } elseif ($file['size'] == 0) {
                $message = "Uploaded file is empty.";
                error_log("Uploaded file is empty");
            } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
                $message = "Please upload a CSV file.";
                error_log("Invalid file type: " . $file['name']);
            } else {
                // Open the CSV file
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle === false) {
                    $message = "Unable to open the CSV file.";
                    error_log("Unable to open CSV file: " . $file['tmp_name']);
                } else {
                    // Count total rows for logging (excluding header)
                    $rowCount = 0;
                    $tempHandle = fopen($file['tmp_name'], 'r');
                    while (fgetcsv($tempHandle, 1000, $delimiter) !== false) {
                        $rowCount++;
                    }
                    fclose($tempHandle);
                    $totalRows = $rowCount - 1; // Exclude header
                    error_log("Total rows in CSV (excluding header): $totalRows");

                    // Read the header row
                    $header = fgetcsv($handle, 1000, $delimiter);
                    if ($header === false) {
                        $message = "CSV file is empty or cannot be read.";
                        error_log("CSV file is empty or cannot be read");
                    } else {
                        // Normalize header (trim and convert to lowercase for comparison)
                        $header = array_map('trim', $header);
                        $headerLower = array_map('strtolower', $header);
                        error_log("CSV header: " . json_encode($header));

                        // Define expected columns based on the target table
                        $expectedColumns = $expectedColumnsExamTables;
                        $subjects = $subjectsExamTables;

                        // Validate header by mapping CSV columns to database columns
                        $expectedHeaderLower = array_map('strtolower', array_diff($expectedColumns, ['examname', 'total_points', 'Total', 'Average']));
                        $columnMapping = [];
                        foreach ($expectedHeaderLower as $expected) {
                            $index = array_search($expected, $headerLower);
                            if ($index !== false) {
                                $columnMapping[$expected] = $index;
                            }
                        }

                        $missingColumns = array_diff($expectedHeaderLower, $headerLower);
                        if (!empty($missingColumns)) {
                            $message = "CSV header is missing required columns: " . implode(', ', $missingColumns);
                            error_log("CSV header missing columns: " . implode(', ', $missingColumns));
                        } else {
                            // Start a transaction
                            mysqli_begin_transaction($connection);

                            try {
                                // Prepare SQL statement
                                $columns = array_merge(
                                    ['Password', 'Name', 'Class', 'examname'],
                                    $subjects,
                                    ['Grade', 'total_points', 'Total', 'Average']
                                );
                                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                                $query = "INSERT INTO `$targetTable` (" . implode(', ', $columns) . ") VALUES ($placeholders)";
                                $stmt = mysqli_prepare($connection, $query);
                                if (!$stmt) {
                                    throw new Exception("Failed to prepare SQL statement: " . mysqli_error($connection));
                                }

                                // Process each row
                                $rowNumber = 1; // Start after header
                                while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                                    $rowNumber++;
                                    // Skip empty rows
                                    if (empty(array_filter($row, 'trim'))) {
                                        $skipCount++;
                                        error_log("Row $rowNumber: Skipped empty row");
                                        continue;
                                    }

                                    // Map CSV row to columns
                                    $rowData = [];
                                    foreach ($columnMapping as $column => $index) {
                                        $value = $row[$index] ?? null;
                                        $rowData[$column] = trim($value);
                                    }

                                    // Handle required fields with defaults
                                    $requiredFields = ['password', 'name', 'class'];
                                    foreach ($requiredFields as $field) {
                                        if (empty($rowData[$field])) {
                                            $rowData[$field] = 'Unknown'; // Default value for NOT NULL columns
                                            error_log("Row $rowNumber: Set default value 'Unknown' for missing field '$field'");
                                        }
                                    }

                                    // Prepare data for insertion
                                    $params = [];
                                    $types = '';
                                    foreach ($columns as $column) {
                                        $columnLower = strtolower($column);
                                        if ($column === 'examname') {
                                            $params[] = $examName;
                                            $types .= 's';
                                        } elseif ($column === 'total_points') {
                                            $params[] = 0; // Will be calculated in index.php
                                            $types .= 'i';
                                        } elseif ($column === 'Total') {
                                            $params[] = '0'; // TEXT column, set as string
                                            $types .= 's';
                                        } elseif ($column === 'Average') {
                                            $params[] = 0.0; // FLOAT column
                                            $types .= 'd';
                                        } elseif (in_array($column, $subjects)) {
                                            $value = $rowData[$columnLower] ?? null;
                                            $params[] = $value ?: null; // TEXT column
                                            $types .= 's';
                                        } else {
                                            $value = $rowData[$columnLower] ?? null;
                                            $params[] = $value ?: null;
                                            $types .= 's';
                                        }
                                    }

                                    // Bind parameters and execute
                                    $bindParams = array_merge([$types], array_map(function($param) {
                                        return is_null($param) ? null : $param;
                                    }, $params));
                                    $bindSuccess = call_user_func_array([$stmt, 'bind_param'], array_merge([$bindParams[0]], array_map(function($param) {
                                        return $param;
                                    }, array_slice($bindParams, 1))));
                                    if (!$bindSuccess) {
                                        $failCount++;
                                        error_log("Row $rowNumber: Failed to bind parameters - Error: " . mysqli_stmt_error($stmt) . " - Row data: " . json_encode($row));
                                        continue;
                                    }

                                    if (mysqli_stmt_execute($stmt)) {
                                        $successCount++;
                                    } else {
                                        $failCount++;
                                        error_log("Row $rowNumber: Failed to insert row - Error: " . mysqli_stmt_error($stmt) . " - Row data: " . json_encode($row));
                                    }
                                }

                                mysqli_stmt_close($stmt);

                                // Commit transaction
                                mysqli_commit($connection);
                                $message = "Import completed: $successCount rows imported, $skipCount rows skipped, $failCount rows failed out of $totalRows total rows.";
                                error_log($message);

                                // Verify row count
                                $processedRows = $successCount + $skipCount + $failCount;
                                if ($processedRows != $totalRows) {
                                    $message .= " Warning: Processed $processedRows rows, but expected $totalRows rows. Some rows may have been missed.";
                                    error_log("Row count mismatch: Processed $processedRows rows, expected $totalRows rows");
                                }
                            } catch (Exception $e) {
                                // Rollback transaction on error
                                mysqli_rollback($connection);
                                $message = "Import failed: " . $e->getMessage();
                                error_log("Import failed: " . $e->getMessage());
                            }
                        }
                    }
                    fclose($handle);
                }
            }
        }
    }
}

// Close database connection
mysqli_close($connection);
error_log("Database connection closed");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload CSV - Abuhureira Academy</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; color: #333; }
        .container { max-width: 600px; margin-top: 50px; }
        .form-group { margin-bottom: 20px; }
        .form-control { padding: 10px; font-size: 16px; }
        .btn-upload { background: #007bff; color: white; padding: 10px 20px; font-size: 16px; border: none; border-radius: 5px; }
        .btn-upload:hover { background: #0056b3; }
        .alert { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Upload CSV File</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="table">Select Table:</label>
                <select name="table" id="table" class="form-control" required>
                    <option value="examone">Exam One</option>
                    <option value="examtwo">Exam Two</option>
                    <option value="examthree">Exam Three</option>
                    <option value="examfour">Exam Four</option>
                </select>
            </div>
            <div class="form-group">
                <label for="examname">Exam Name:</label>
                <input type="text" name="examname" id="examname" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="delimiter">CSV Delimiter:</label>
                <select name="delimiter" id="delimiter" class="form-control" required>
                    <option value="comma">Comma (,)</option>
                    <option value="semicolon">Semicolon (;)</option>
                    <option value="tab">Tab</option>
                </select>
            </div>
            <div class="form-group">
                <label for="csv_file">Upload CSV File:</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn-upload">Upload</button>
        </form>
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>