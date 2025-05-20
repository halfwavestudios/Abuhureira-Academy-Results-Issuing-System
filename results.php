<?php
// Enable error reporting and logging for debugging
ini_set('display_errors', 0); // Set to 0 in production after testing
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);

// Log script start
error_log("Starting results.php execution at " . date('Y-m-d H:i:s'));

// Database connection details - VERIFY WITH YOUR HOSTING PROVIDER
$host = "localhost";
$username = "abuhurei_wp6328";
$password = "Sonik9200";
$database = "abuhurei_wp6328";

// Attempt to connect to the database
$connection = mysqli_connect($host, $username, $password, $database);
if (!$connection) {
    error_log("Database connection failed: " . mysqli_connect_error());
    http_response_code(500);
    die('<div class="alert alert-danger">Unable to connect to the database. Please try again later.</div>');
}

// Set charset to utf8mb4
if (!mysqli_set_charset($connection, 'utf8mb4')) {
    error_log("Error setting charset: " . mysqli_error($connection));
}

error_log("Database connection successful");

// Check if tables exist
$required_tables = ['student', 'examone', 'examtwo', 'examthree', 'examfour'];
foreach ($required_tables as $table) {
    $result = mysqli_query($connection, "SHOW TABLES LIKE '$table'");
    if (!$result || mysqli_num_rows($result) == 0) {
        error_log("Table '$table' does not exist in database '$database'");
        http_response_code(500);
        die("<div class='alert alert-danger'>Error: Database table '$table' is missing.</div>");
    }
}
error_log("Required tables checked");

// Define subjects for student table
$subjectsStudent = [
    'Mathematics', 'Kiswahili', 'Ire', 'Integrated_Science',
    'Pre_Technical', 'Agriculture', 'Creative_Arts_Sports', 'Social_Studies'
];

// Dynamically define subjects for exam tables based on existing columns
$subjectsExams = [];
$examTables = ['examone', 'examtwo', 'examthree', 'examfour'];
$commonColumns = null;
$possibleSubjects = [
    'English', 'Mathematics', 'Kiswahili', 'Ire', 'Business', 'History',
    'Chemistry', 'Physics', 'Biology', 'Literature', 'Arabic', 'Computer'
];
foreach ($examTables as $table) {
    $result = mysqli_query($connection, "DESCRIBE $table");
    if ($result) {
        $columns = array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field');
        $subjectColumns = array_intersect($columns, $possibleSubjects);
        if ($commonColumns === null) {
            $commonColumns = $subjectColumns;
        } else {
            $commonColumns = array_intersect($commonColumns, $subjectColumns);
        }
    } else {
        error_log("Failed to describe table '$table': " . mysqli_error($connection));
    }
}
$subjectsExams = array_values($commonColumns);
error_log("Dynamic subjectsExams: " . implode(', ', $subjectsExams));

// Check required columns in student table
$required_columns_student = array_merge(
    ['Name', 'Class', 'Password', 'Grade', 'total_points', 'average', 'examname'],
    $subjectsStudent
);
$result = mysqli_query($connection, "DESCRIBE student");
if ($result) {
    $existing_columns = array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field');
    foreach ($required_columns_student as $column) {
        if (!in_array($column, $existing_columns)) {
            error_log("Column '$column' is missing in table 'student'. Skipping to prevent errors.");
            if (in_array($column, $subjectsStudent)) {
                $subjectsStudent = array_diff($subjectsStudent, [$column]);
            }
        }
    }
} else {
    error_log("Failed to describe table 'student': " . mysqli_error($connection));
    http_response_code(500);
    die("<div class='alert alert-danger'>Error: Unable to verify table structure for 'student'.</div>");
}

// Check required columns in exam tables
$required_columns_exams = array_merge(
    ['Name', 'Class', 'Password', 'Grade', 'total_points', 'Total', 'Average', 'examname'],
    $subjectsExams
);
foreach ($examTables as $table) {
    $result = mysqli_query($connection, "DESCRIBE $table");
    if ($result) {
        $existing_columns = array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field');
        foreach ($required_columns_exams as $column) {
            if (!in_array($column, $existing_columns)) {
                error_log("Column '$column' is missing in table '$table'. Skipping to prevent errors.");
                if (in_array($column, $subjectsExams)) {
                    $subjectsExams = array_diff($subjectsExams, [$column]);
                }
            }
        }
    } else {
        error_log("Failed to describe table '$table': " . mysqli_error($connection));
        http_response_code(500);
        die("<div class='alert alert-danger'>Error: Unable to verify table structure for '$table'.</div>");
    }
}
error_log("All required columns checked");

// Helper function to check if an exam has actual results
function hasResults($examData, $subjects) {
    if (empty($examData)) return false;
    foreach ($subjects as $subject) {
        if (array_key_exists($subject, $examData) && is_numeric($examData[$subject]) && $examData[$subject] >= 0) {
            return true;
        }
    }
    return false;
}

// Function to determine grade based on average and class
function getGrade($average, $class) {
    if (!is_numeric($average) || $average < 0) return '-';
    $isIGCSE = preg_match('/^YEAR/', $class);
    if ($isIGCSE) {
        if ($average >= 90) return 'A*';
        elseif ($average >= 80) return 'A';
        elseif ($average >= 70) return 'B';
        elseif ($average >= 60) return 'C';
        elseif ($average >= 50) return 'D';
        elseif ($average >= 40) return 'E';
        elseif ($average >= 30) return 'F';
        elseif ($average >= 20) return 'G';
        else return 'U';
    } else {
        if ($average >= 80) return 'A';
        elseif ($average >= 75) return 'A-';
        elseif ($average >= 70) return 'B+';
        elseif ($average >= 65) return 'B';
        elseif ($average >= 60) return 'B-';
        elseif ($average >= 55) return 'C+';
        elseif ($average >= 50) return 'C';
        elseif ($average >= 45) return 'C-';
        elseif ($average >= 40) return 'D+';
        elseif ($average >= 35) return 'D';
        elseif ($average >= 30) return 'D-';
        else return 'E';
    }
}

// Initialize variables
$buttonClass = '';
$showResults = false;
$studentData = [];
$examOneData = [];
$examTwoData = [];
$examThreeData = [];
$examFourData = [];
$termSummary = [];
$insightText = '';
$congratulations = false;
$meanGrade = '';
$totalPointsStudent = 0;
$totalPointsExamOne = 0;
$totalPointsExamTwo = 0;
$totalPointsExamThree = 0;
$totalPointsExamFour = 0;
$termTotalPoints = 0;
$termTotal = 0;
$termAverage = 0;
$subjectsWithResults = [];
$pointsPerSubject = 10;
$passingGrades844 = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-'];
$passingGradesIGCSE = ['A*', 'A', 'B', 'C'];
$errorMessage = '';
$subjectsToDisplay = [];
$isGrade9Bor9G = false;
$analysisData = [
    'averages' => [],
    'subjects' => [],
    'grades' => []
];

// Initialize analysis data for all subjects
$allSubjects = array_unique(array_merge($subjectsStudent, $subjectsExams));
foreach ($allSubjects as $subject) {
    $analysisData['subjects'][$subject] = [
        'scores' => [],
        'average' => 0,
        'count' => 0,
        'trend' => []
    ];
}
error_log("Initialized analysis data for subjects: " . implode(', ', $allSubjects));

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    error_log("Form submitted with identifier: " . ($_POST['identifier'] ?? 'none'));
    $identifier = trim($_POST['identifier'] ?? '');
    if (empty($identifier)) {
        $errorMessage = "Please enter a valid student ID or password.";
        error_log("Empty identifier provided");
    } else {
        // Search in `student` table
        $query = "SELECT Name, Class, Grade, examname, " . implode(', ', $subjectsStudent) . ", total_points, average FROM student WHERE Password = ?";
        error_log("Student query: $query with identifier: $identifier");
        $stmt = mysqli_prepare($connection, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $identifier);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                error_log("Student query result rows: " . mysqli_num_rows($result));
                if ($result && mysqli_num_rows($result) > 0) {
                    $studentData = mysqli_fetch_assoc($result);
                    $showResults = true;
                    $subjectsToDisplay = $subjectsStudent;
                    $isGrade9Bor9G = (preg_match('/^YEAR/', $studentData['Class']));
                    error_log("Found student in 'student' table: " . json_encode($studentData));
                }
            } else {
                error_log("Query execution failed for student: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("Query preparation failed for student: " . mysqli_error($connection));
        }

        // Fetch exam data
        $query = "SELECT Name, Class, Grade, examname, " . implode(', ', $subjectsExams) . ", total_points, Total, Average FROM examone WHERE Password = ?";
        $stmt = mysqli_prepare($connection, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $identifier);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($result && mysqli_num_rows($result) > 0) {
                    $examOneData = mysqli_fetch_assoc($result);
                    if (!$showResults) {
                        $showResults = true;
                        $subjectsToDisplay = $subjectsExams;
                        $isGrade9Bor9G = (preg_match('/^YEAR/', $examOneData['Class']));
                    }
                    error_log("Found Exam 1 data: " . json_encode($examOneData));
                }
            } else {
                error_log("Query execution failed for examone: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("Query preparation failed for examone: " . mysqli_error($connection));
        }

        // Fetch Exam 2 results
        $query = "SELECT Name, Class, Grade, examname, " . implode(', ', $subjectsExams) . ", total_points, Total, Average FROM examtwo WHERE Password = ?";
        $stmt = mysqli_prepare($connection, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $identifier);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($result && mysqli_num_rows($result) > 0) {
                    $examTwoData = mysqli_fetch_assoc($result);
                    if (!$showResults) {
                        $showResults = true;
                        $subjectsToDisplay = $subjectsExams;
                        $isGrade9Bor9G = (preg_match('/^YEAR/', $examTwoData['Class']));
                    }
                    error_log("Found Exam 2 data: " . json_encode($examTwoData));
                }
            } else {
                error_log("Query execution failed for examtwo: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("Query preparation failed for examtwo: " . mysqli_error($connection));
        }

        // Fetch Exam 3 results
        $query = "SELECT Name, Class, Grade, examname, " . implode(', ', $subjectsExams) . ", total_points, Total, Average FROM examthree WHERE Password = ?";
        $stmt = mysqli_prepare($connection, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $identifier);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($result && mysqli_num_rows($result) > 0) {
                    $examThreeData = mysqli_fetch_assoc($result);
                    if (!$showResults) {
                        $showResults = true;
                        $subjectsToDisplay = $subjectsExams;
                        $isGrade9Bor9G = (preg_match('/^YEAR/', $examThreeData['Class']));
                    }
                    error_log("Found Exam 3 data: " . json_encode($examThreeData));
                }
            } else {
                error_log("Query execution failed for examthree: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("Query preparation failed for examthree: " . mysqli_error($connection));
        }

        // Fetch Exam 4 results
        $query = "SELECT Name, Class, Grade, examname, " . implode(', ', $subjectsExams) . ", total_points, Total, Average FROM examfour WHERE Password = ?";
        $stmt = mysqli_prepare($connection, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $identifier);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if ($result && mysqli_num_rows($result) > 0) {
                    $examFourData = mysqli_fetch_assoc($result);
                    if (!$showResults) {
                        $showResults = true;
                        $subjectsToDisplay = $subjectsExams;
                        $isGrade9Bor9G = (preg_match('/^YEAR/', $examFourData['Class']));
                    }
                    error_log("Found Exam 4 data: " . json_encode($examFourData));
                }
            } else {
                error_log("Query execution failed for examfour: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            error_log("Query preparation failed for examfour: " . mysqli_error($connection));
        }

        if ($showResults) {
            // Process results for each exam and term summary
            $bestSubjects = [];
            $needsImprovement = [];
            $termSubjects = $isGrade9Bor9G ? $subjectsExams : $subjectsStudent;
            $termSubjectTotals = array_fill_keys($termSubjects, 0);
            $termSubjectCounts = array_fill_keys($termSubjects, 0);
            $studentSubjectCount = 0;
            $examOneSubjectCount = 0;
            $examTwoSubjectCount = 0;
            $examThreeSubjectCount = 0;
            $examFourSubjectCount = 0;

            // Process Student (Grade 9B/9G or FORM classes)
            if (!empty($studentData)) {
                $studentTotal = 0;
                $studentSubjectCount = 0;
                $result = mysqli_query($connection, "DESCRIBE student");
                $studentColumns = $result ? array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field') : [];
                foreach ($subjectsStudent as $subject) {
                    if (!in_array($subject, $studentColumns)) {
                        error_log("Subject column '$subject' does not exist in student table for identifier: $identifier");
                        continue;
                    }
                    if (!array_key_exists($subject, $studentData)) {
                        error_log("Subject '$subject' not present in student data for identifier: $identifier");
                        continue;
                    }
                    $value = $studentData[$subject] ?? null;
                    if (isset($value) && is_numeric($value) && $value >= 0) {
                        $subjectsWithResults[$subject] = true;
                        $score = (float)$value;
                        if ($score >= 60) $bestSubjects[$subject] = true;
                        else $needsImprovement[$subject] = true;
                        $studentTotal += $score;
                        $studentSubjectCount++;
                        $termSubjectTotals[$subject] += $score;
                        $termSubjectCounts[$subject]++;
                        if ($score >= 50) $totalPointsStudent += $pointsPerSubject;
                        $analysisData['subjects'][$subject]['scores']['Student Record'] = $score;
                        $analysisData['subjects'][$subject]['count']++;
                        $analysisData['subjects'][$subject]['trend'][] = $score;
                    } else {
                        error_log("Invalid or missing score for subject '$subject' in student: " . json_encode($value));
                    }
                }
                $studentAverage = $studentSubjectCount > 0 ? $studentTotal / $studentSubjectCount : 0;
                error_log("Student Average: $studentAverage, Total: $studentTotal, Subject Count: $studentSubjectCount");
                $studentData['Total'] = $studentTotal;
                $studentData['Average'] = $studentAverage > 0 ? $studentAverage : 0;
                $studentData['Grade'] = getGrade($studentAverage, $studentData['Class'] ?? '');
                $studentData['total_points'] = $totalPointsStudent;
                $analysisData['averages']['Student Record'] = $studentAverage;
                $analysisData['grades']['Student Record'] = $studentData['Grade'];
                $analysisData['subjects']['Student Record']['average'] = $studentAverage;
                error_log("Student Grade: " . $studentData['Grade']);

                // Update student table
                $updateQuery = "UPDATE student SET total_points = ?, average = ?, Grade = ? WHERE Password = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "idss", $totalPointsStudent, $studentAverage, $studentData['Grade'], $identifier);
                    if (!mysqli_stmt_execute($updateStmt)) {
                        error_log("Failed to update student: " . mysqli_stmt_error($updateStmt));
                    }
                    mysqli_stmt_close($updateStmt);
                } else {
                    error_log("Failed to prepare update statement for student: " . mysqli_error($connection));
                }
            }

            // Process Exam 1
            if (hasResults($examOneData, $subjectsExams)) {
                $examOneTotal = 0;
                $examOneSubjectCount = 0;
                $result = mysqli_query($connection, "DESCRIBE examone");
                $examOneColumns = $result ? array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field') : [];
                foreach ($subjectsExams as $subject) {
                    if (!in_array($subject, $examOneColumns)) {
                        error_log("Subject column '$subject' does not exist in examone table for identifier: $identifier");
                        continue;
                    }
                    if (array_key_exists($subject, $examOneData) && is_numeric($examOneData[$subject]) && $examOneData[$subject] >= 0) {
                        $subjectsWithResults[$subject] = true;
                        $score = (float)$examOneData[$subject];
                        if ($score >= 60) $bestSubjects[$subject] = true;
                        else $needsImprovement[$subject] = true;
                        $examOneTotal += $score;
                        $examOneSubjectCount++;
                        $termSubjectTotals[$subject] += $score;
                        $termSubjectCounts[$subject]++;
                        if ($score >= 50) $totalPointsExamOne += $pointsPerSubject;
                        $analysisData['subjects'][$subject]['scores']['Exam 1'] = $score;
                        $analysisData['subjects'][$subject]['count']++;
                        $analysisData['subjects'][$subject]['trend'][] = $score;
                    } else {
                        error_log("Invalid or missing score for subject '$subject' in examone: " . json_encode($examOneData[$subject] ?? null));
                    }
                }
                $examOneAverage = $examOneSubjectCount > 0 ? $examOneTotal / $examOneSubjectCount : 0;
                error_log("Exam 1 Average: $examOneAverage, Total: $examOneTotal, Subject Count: $examOneSubjectCount");
                $examOneData['Total'] = $examOneTotal;
                $examOneData['Average'] = $examOneAverage > 0 ? $examOneAverage : 0;
                $examOneData['Grade'] = getGrade($examOneAverage, $examOneData['Class'] ?? '');
                $examOneData['total_points'] = $totalPointsExamOne;
                $analysisData['averages']['Exam 1'] = $examOneAverage;
                $analysisData['grades']['Exam 1'] = $examOneData['Grade'];
                $analysisData['subjects']['Exam 1']['average'] = $examOneAverage;
                error_log("Exam 1 Grade: " . $examOneData['Grade']);

                // Update examone table
                $updateQuery = "UPDATE examone SET total_points = ?, Total = ?, Average = ?, Grade = ? WHERE Password = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "iddss", $totalPointsExamOne, $examOneTotal, $examOneAverage, $examOneData['Grade'], $identifier);
                    if (!mysqli_stmt_execute($updateStmt)) {
                        error_log("Failed to update examone: " . mysqli_stmt_error($updateStmt));
                    }
                    mysqli_stmt_close($updateStmt);
                } else {
                    error_log("Failed to prepare update statement for examone: " . mysqli_error($connection));
                }
            }

            // Process Exam 2
            if (hasResults($examTwoData, $subjectsExams)) {
                $examTwoTotal = 0;
                $examTwoSubjectCount = 0;
                $result = mysqli_query($connection, "DESCRIBE examtwo");
                $examTwoColumns = $result ? array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field') : [];
                foreach ($subjectsExams as $subject) {
                    if (!in_array($subject, $examTwoColumns)) {
                        error_log("Subject column '$subject' does not exist in examtwo table for identifier: $identifier");
                        continue;
                    }
                    if (array_key_exists($subject, $examTwoData) && is_numeric($examTwoData[$subject]) && $examTwoData[$subject] >= 0) {
                        $subjectsWithResults[$subject] = true;
                        $score = (float)$examTwoData[$subject];
                        if ($score >= 60) $bestSubjects[$subject] = true;
                        else $needsImprovement[$subject] = true;
                        $examTwoTotal += $score;
                        $examTwoSubjectCount++;
                        $termSubjectTotals[$subject] += $score;
                        $termSubjectCounts[$subject]++;
                        if ($score >= 50) $totalPointsExamTwo += $pointsPerSubject;
                        $analysisData['subjects'][$subject]['scores']['Exam 2'] = $score;
                        $analysisData['subjects'][$subject]['count']++;
                        $analysisData['subjects'][$subject]['trend'][] = $score;
                    } else {
                        error_log("Invalid or missing score for subject '$subject' in examtwo: " . json_encode($examTwoData[$subject] ?? null));
                    }
                }
                $examTwoAverage = $examTwoSubjectCount > 0 ? $examTwoTotal / $examTwoSubjectCount : 0;
                error_log("Exam 2 Average: $examTwoAverage, Total: $examTwoTotal, Subject Count: $examTwoSubjectCount");
                $examTwoData['Total'] = $examTwoTotal;
                $examTwoData['Average'] = $examTwoAverage > 0 ? $examTwoAverage : 0;
                $examTwoData['Grade'] = getGrade($examTwoAverage, $examTwoData['Class'] ?? '');
                $examTwoData['total_points'] = $totalPointsExamTwo;
                $analysisData['averages']['Exam 2'] = $examTwoAverage;
                $analysisData['grades']['Exam 2'] = $examTwoData['Grade'];
                $analysisData['subjects']['Exam 2']['average'] = $examTwoAverage;
                error_log("Exam 2 Grade: " . $examTwoData['Grade']);

                // Update examtwo table
                $updateQuery = "UPDATE examtwo SET total_points = ?, Total = ?, Average = ?, Grade = ? WHERE Password = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "iddss", $totalPointsExamTwo, $examTwoTotal, $examTwoAverage, $examTwoData['Grade'], $identifier);
                    if (!mysqli_stmt_execute($updateStmt)) {
                        error_log("Failed to update examtwo: " . mysqli_stmt_error($updateStmt));
                    }
                    mysqli_stmt_close($updateStmt);
                } else {
                    error_log("Failed to prepare update statement for examtwo: " . mysqli_error($connection));
                }
            }

            // Process Exam 3
            if (hasResults($examThreeData, $subjectsExams)) {
                $examThreeTotal = 0;
                $examThreeSubjectCount = 0;
                $result = mysqli_query($connection, "DESCRIBE examthree");
                $examThreeColumns = $result ? array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field') : [];
                foreach ($subjectsExams as $subject) {
                    if (!in_array($subject, $examThreeColumns)) {
                        error_log("Subject column '$subject' does not exist in examthree table for identifier: $identifier");
                        continue;
                    }
                    if (array_key_exists($subject, $examThreeData) && is_numeric($examThreeData[$subject]) && $examThreeData[$subject] >= 0) {
                        $subjectsWithResults[$subject] = true;
                        $score = (float)$examThreeData[$subject];
                        if ($score >= 60) $bestSubjects[$subject] = true;
                        else $needsImprovement[$subject] = true;
                        $examThreeTotal += $score;
                        $examThreeSubjectCount++;
                        $termSubjectTotals[$subject] += $score;
                        $termSubjectCounts[$subject]++;
                        if ($score >= 50) $totalPointsExamThree += $pointsPerSubject;
                        $analysisData['subjects'][$subject]['scores']['Exam 3'] = $score;
                        $analysisData['subjects'][$subject]['count']++;
                        $analysisData['subjects'][$subject]['trend'][] = $score;
                    } else {
                        error_log("Invalid or missing score for subject '$subject' in examthree: " . json_encode($examThreeData[$subject] ?? null));
                    }
                }
                $examThreeAverage = $examThreeSubjectCount > 0 ? $examThreeTotal / $examThreeSubjectCount : 0;
                error_log("Exam 3 Average: $examThreeAverage, Total: $examThreeTotal, Subject Count: $examThreeSubjectCount");
                $examThreeData['Total'] = $examThreeTotal;
                $examThreeData['Average'] = $examThreeAverage > 0 ? $examThreeAverage : 0;
                $examThreeData['Grade'] = getGrade($examThreeAverage, $examThreeData['Class'] ?? '');
                $examThreeData['total_points'] = $totalPointsExamThree;
                $analysisData['averages']['Exam 3'] = $examThreeAverage;
                $analysisData['grades']['Exam 3'] = $examThreeData['Grade'];
                $analysisData['subjects']['Exam 3']['average'] = $examThreeAverage;
                error_log("Exam 3 Grade: " . $examThreeData['Grade']);

                // Update examthree table
                $updateQuery = "UPDATE examthree SET total_points = ?, Total = ?, Average = ?, Grade = ? WHERE Password = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "iddss", $totalPointsExamThree, $examThreeTotal, $examThreeAverage, $examThreeData['Grade'], $identifier);
                    if (!mysqli_stmt_execute($updateStmt)) {
                        error_log("Failed to update examthree: " . mysqli_stmt_error($updateStmt));
                    }
                    mysqli_stmt_close($updateStmt);
                } else {
                    error_log("Failed to prepare update statement for examthree: " . mysqli_error($connection));
                }
            }

            // Process Exam 4
            if (hasResults($examFourData, $subjectsExams)) {
                $examFourTotal = 0;
                $examFourSubjectCount = 0;
                $result = mysqli_query($connection, "DESCRIBE examfour");
                $examFourColumns = $result ? array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'Field') : [];
                foreach ($subjectsExams as $subject) {
                    if (!in_array($subject, $examFourColumns)) {
                        error_log("Subject column '$subject' does not exist in examfour table for identifier: $identifier");
                        continue;
                    }
                    if (array_key_exists($subject, $examFourData) && is_numeric($examFourData[$subject]) && $examFourData[$subject] >= 0) {
                        $subjectsWithResults[$subject] = true;
                        $score = (float)$examFourData[$subject];
                        if ($score >= 60) $bestSubjects[$subject] = true;
                        else $needsImprovement[$subject] = true;
                        $examFourTotal += $score;
                        $examFourSubjectCount++;
                        $termSubjectTotals[$subject] += $score;
                        $termSubjectCounts[$subject]++;
                        if ($score >= 50) $totalPointsExamFour += $pointsPerSubject;
                        $analysisData['subjects'][$subject]['scores']['Exam 4'] = $score;
                        $analysisData['subjects'][$subject]['count']++;
                        $analysisData['subjects'][$subject]['trend'][] = $score;
                    } else {
                        error_log("Invalid or missing score for subject '$subject' in examfour: " . json_encode($examFourData[$subject] ?? null));
                    }
                }
                $examFourAverage = $examFourSubjectCount > 0 ? $examFourTotal / $examFourSubjectCount : 0;
                error_log("Exam 4 Average: $examFourAverage, Total: $examFourTotal, Subject Count: $examFourSubjectCount");
                $examFourData['Total'] = $examFourTotal;
                $examFourData['Average'] = $examFourAverage > 0 ? $examFourAverage : 0;
                $examFourData['Grade'] = getGrade($examFourAverage, $examFourData['Class'] ?? '');
                $examFourData['total_points'] = $totalPointsExamFour;
                $analysisData['averages']['Exam 4'] = $examFourAverage;
                $analysisData['grades']['Exam 4'] = $examFourData['Grade'];
                $analysisData['subjects']['Exam 4']['average'] = $examFourAverage;
                error_log("Exam 4 Grade: " . $examFourData['Grade']);

                // Update examfour table
                $updateQuery = "UPDATE examfour SET total_points = ?, Total = ?, Average = ?, Grade = ? WHERE Password = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, "iddss", $totalPointsExamFour, $examFourTotal, $examFourAverage, $examFourData['Grade'], $identifier);
                    if (!mysqli_stmt_execute($updateStmt)) {
                        error_log("Failed to update examfour: " . mysqli_stmt_error($updateStmt));
                    }
                    mysqli_stmt_close($updateStmt);
                } else {
                    error_log("Failed to prepare update statement for examfour: " . mysqli_error($connection));
                }
            }

            // Process Term Summary
            if (!empty($studentData) || !empty($examOneData) || !empty($examTwoData) || !empty($examThreeData) || !empty($examFourData)) {
                $termTotal = 0;
                $termSubjectCount = 0;
                foreach ($termSubjects as $subject) {
                    if ($termSubjectCounts[$subject] > 0) {
                        $termSummary[$subject] = $termSubjectTotals[$subject] / $termSubjectCounts[$subject];
                        $termTotal += $termSubjectTotals[$subject];
                        $termSubjectCount += $termSubjectCounts[$subject];
                        if ($termSummary[$subject] >= 50) $termTotalPoints += $pointsPerSubject;
                        $analysisData['subjects'][$subject]['average'] = $termSummary[$subject];
                    } else {
                        $termSummary[$subject] = 0;
                        $analysisData['subjects'][$subject]['average'] = 0;
                    }
                }
                $termAverage = $termSubjectCount > 0 ? $termTotal / $termSubjectCount : 0;
                $termSummary['Total'] = $termTotal;
                $termSummary['Average'] = $termAverage > 0 ? $termAverage : 0;
                $termSummary['Grade'] = getGrade($termAverage, $studentData['Class'] ?? $examOneData['Class'] ?? $examTwoData['Class'] ?? $examThreeData['Class'] ?? $examFourData['Class'] ?? '');
                $termSummary['total_points'] = $termTotalPoints;
                error_log("Term Average: $termAverage, Total: $termTotal, Subject Count: $termSubjectCount, Grade: " . $termSummary['Grade']);

                // Insights and Congratulations
                $bestSubjects = array_keys(array_filter($bestSubjects));
                $needsImprovement = array_keys(array_filter($needsImprovement));
                $insightText = "You are excelling in " . (!empty($bestSubjects) ? implode(", ", $bestSubjects) : "no subjects yet") .
                              ". Focus on improving in " . (!empty($needsImprovement) ? implode(", ", $needsImprovement) : "all subjects") .
                              " to boost your overall performance.";
                if ($termSubjectCount > 0 && $termAverage >= 70) {
                    $congratulations = true;
                    $meanGrade = number_format($termAverage, 2);
                }
                $buttonClass = (preg_match('/^YEAR/', $studentData['Class'] ?? $examOneData['Class'] ?? $examTwoData['Class'] ?? $examThreeData['Class'] ?? $examFourData['Class'] ?? '') ?
                    in_array($termSummary['Grade'], $passingGradesIGCSE) : in_array($termSummary['Grade'], $passingGrades844)) ? 'btn-pass' : 'btn-fail';
            }
        } else {
            error_log("No student found for identifier: $identifier");
            $errorMessage = "Invalid student ID or password. Please check and try again.";
        }
    }
}

// Fetch distinct classes
$allClasses = [];
$allClassesQuery = "SELECT DISTINCT Class FROM student UNION SELECT DISTINCT Class FROM examone UNION SELECT DISTINCT Class FROM examtwo UNION SELECT DISTINCT Class FROM examthree UNION SELECT DISTINCT Class FROM examfour";
$allClassesResult = mysqli_query($connection, $allClassesQuery);
if ($allClassesResult) {
    while ($classRow = mysqli_fetch_assoc($allClassesResult)) {
        $allClasses[] = $classRow['Class'];
    }
    error_log("Distinct classes: " . json_encode($allClasses));
} else {
    error_log("Failed to fetch classes: " . mysqli_error($connection));
}

// Fetch class averages and student rank
$classAverages = [];
$studentRank = null;
$studentClass = $studentData['Class'] ?? $examOneData['Class'] ?? $examTwoData['Class'] ?? $examThreeData['Class'] ?? $examFourData['Class'] ?? null;
$studentAverage = $termSummary['Average'] ?? 0;
$result = mysqli_query($connection, "
    SELECT Class, Average FROM student WHERE Average IS NOT NULL
    UNION
    SELECT Class, Average FROM examone WHERE Average IS NOT NULL
    UNION
    SELECT Class, Average FROM examtwo WHERE Average IS NOT NULL
    UNION
    SELECT Class, Average FROM examthree WHERE Average IS NOT NULL
    UNION
    SELECT Class, Average FROM examfour WHERE Average IS NOT NULL
");
if ($result) {
    $classData = [];
    $classAveragesList = [];
    while ($data = mysqli_fetch_assoc($result)) {
        $average = is_numeric($data['Average']) ? (float)$data['Average'] : 0;
        $classData[$data['Class']][] = $average;
        if ($data['Class'] === $studentClass) {
            $classAveragesList[] = $average;
        }
    }
    foreach ($allClasses as $class) {
        $classAverages[$class] = !empty($classData[$class]) ? array_sum($classData[$class]) / count($classData[$class]) : 0;
    }
    if ($studentClass && !empty($classAveragesList)) {
        rsort($classAveragesList);
        $rank = array_search($studentAverage, $classAveragesList);
        if ($rank !== false) {
            $rank += 1;
        } else {
            $rank = count($classAveragesList) + 1;
        }
        $totalStudents = count($classAveragesList);
        $studentRank = ['rank' => $rank, 'total' => $totalStudents];
    }
    error_log("Class averages: " . json_encode($classAverages));
    error_log("Student rank: " . json_encode($studentRank));
} else {
    error_log("Failed to fetch class averages: " . mysqli_error($connection));
}
arsort($classAverages);

// Debug data in each table
foreach (['student', 'examone', 'examtwo', 'examthree', 'examfour'] as $table) {
    $debugQuery = "SELECT Name, Class, total_points, Average, Grade FROM $table WHERE Password = ?";
    $debugStmt = mysqli_prepare($connection, $debugQuery);
    if ($debugStmt) {
        mysqli_stmt_bind_param($debugStmt, "s", $identifier);
        if (mysqli_stmt_execute($debugStmt)) {
            $debugResult = mysqli_stmt_get_result($debugStmt);
            if ($debugResult && mysqli_num_rows($debugResult) > 0) {
                $debugData = mysqli_fetch_assoc($debugResult);
                error_log("Debug data for $table: " . json_encode($debugData));
            }
        }
        mysqli_stmt_close($debugStmt);
    }
}

// Prepare data for JavaScript
$studentName = $studentData['Name'] ?? $examOneData['Name'] ?? $examTwoData['Name'] ?? $examThreeData['Name'] ?? $examFourData['Name'] ?? 'Student';
$avatar = "https://ui-avatars.com/api/?name=" . urlencode($studentName) . "&background=random&color=fff&size=80";

// Prepare performance data for charts
$performanceData = [];
if (!empty($studentData)) $performanceData['Student Record'] = $studentData['Average'] ?? 0;
if (!empty($examOneData)) $performanceData['Exam 1'] = $examOneData['Average'] ?? 0;
if (!empty($examTwoData)) $performanceData['Exam 2'] = $examTwoData['Average'] ?? 0;
if (!empty($examThreeData)) $performanceData['Exam 3'] = $examThreeData['Average'] ?? 0;
if (!empty($examFourData)) $performanceData['Exam 4'] = $examFourData['Average'] ?? 0;
$performanceData = json_encode($performanceData);

// Prepare analysis data for charts
$analysisDataJson = json_encode($analysisData);

// Close database connection
mysqli_close($connection);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abuhureira Academy - Student Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #2c3e50;
        }
        .navbar {
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .navbar-brand img {
            height: 40px;
        }
        .header-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .control-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }
        .form-container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 500px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: center;
        }
        .input-group {
            margin: 0 auto;
        }
        .form-control {
            border-radius: 5px 0 0 5px;
            border: 1px solid #ced4da;
            padding: 0.5rem 1rem;
            font-size: 1rem;
        }
        .btn-search {
            border-radius: 0 5px 5px 0;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            position: relative;
        }
        .btn-search.loading .spinner-border {
            display: inline-block;
        }
        .btn-search .spinner-border {
            display: none;
            width: 1rem;
            height: 1rem;
            margin-right: 0.5rem;
        }
        .remember-me {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .button-group-top {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        .btn-top {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            color: #ffffff;
            border: none;
            cursor: pointer;
            position: relative;
            padding-left: 2.5rem;
            transition: background-color 0.3s, transform 0.2s;
        }
        .btn-top:hover {
            transform: translateY(-2px);
        }
        .btn-view {
            background-color: #28a745;
            color: #ffffff;
        }
        .btn-practice {
            background-color: #007bff;
            color: #ffffff;
        }
        .btn-print {
            background-color: #007bff;
            color: #ffffff;
        }
        .btn-print::before {
            content: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white' width='18px' height='18px'%3E%3Cpath d='M19 8H5c-1.1 0-2 .9-2 2v6h2v7h14v-7h2v-6c0-1.1-.9-2-2-2zm-2 6H7v-2h10v2zm-8 4v-2h6v2H9z'/%3E%3C/svg%3E");
            position: absolute;
            left: -22px;
            top: 50%;
            transform: translateY(-50%);
        }
        .btn-pass {
            background-color: #28a745;
        }
        .btn-pass:hover {
            background-color: #218838;
        }
        .btn-fail {
            background-color: #dc3545;
        }
        .btn-fail:hover {
            background-color: #c82333;
        }
        .student-card {
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            width: 100%;
            margin: 0 auto 2rem;
            text-align: center;
            position: relative;
            display: flex;
            align-items: center;
            transition: box-shadow 0.3s;
        }
        .student-card:hover {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 2px solid #007bff;
            object-fit: cover;
            margin-right: 1rem;
        }
        .student-tag {
            background-color: #007bff;
            color: #ffffff;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            position: absolute;
            top: -10px;
            left: 10px;
            transform: translateY(-100%);
        }
        .student-details {
            flex-grow: 1;
            text-align: left;
        }
        .student-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.2rem;
        }
        .student-details p {
            font-size: 1rem;
            color: #6c757d;
            margin: 0;
        }
        .quote-card {
            background-color: #e9f7ef;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            text-align: center;
            margin: 0 auto 2rem;
            transition: transform 0.3s;
        }
        .quote-card:hover {
            transform: scale(1.02);
        }
        .quote-text {
            font-style: italic;
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .quote-author {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .section-card {
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 1rem;
        }
        .nav-tabs .nav-link {
            padding: 0.5rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            color: #495057;
            border: none;
            border-radius: 5px 5px 0 0;
            transition: background-color 0.3s, color 0.3s;
        }
        .nav-tabs .nav-link:hover {
            background-color: #e9ecef;
            color: #007bff;
        }
        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: #ffffff;
            border: none;
        }
        .table {
            border-radius: 5px;
            overflow: hidden;
        }
        .table th, .table td {
            padding: 0.75rem;
            vertical-align: middle;
            border: 1px solid #e9ecef;
        }
        .table th {
            background-color: #f1f3f5;
            color: #495057;
            font-weight: 500;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .alert {
            border-radius: 5px;
            font-size: 1rem;
        }
        .insights-text {
            font-size: 1rem;
            color: #495057;
        }
        .leaderboard-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .leaderboard-list li {
            padding: 0.5rem 0;
            font-size: 1rem;
            color: #495057;
            border-bottom: 1px solid #e9ecef;
        }
        .leaderboard-list li:last-child {
            border-bottom: none;
        }
        .print-preview {
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: auto;
            max-width: 800px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .print-logo {
            max-width: 60px;
            margin: 0 auto 15px;
            display: block;
        }
        .print-preview table, th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 14px;
        }
        .print-preview th {
            background: #f0f0f0;
            font-weight: bold;
        }
        .canvas-container {
            max-width: 100%;
            margin-bottom: 1rem;
        }
        .highlight-high {
            background-color: #d4edda;
        }
        .highlight-low {
            background-color: #f8d7da;
        }
        .sparkline {
            width: 50px;
            height: 20px;
            vertical-align: middle;
        }
        .progress-container {
            margin-top: 1rem;
        }
        .progress-bar {
            transition: width 1s ease-in-out;
        }
        /* Grade color coding */
        .grade-a, .grade-a-plus, .grade-a-star {
            color: #28a745;
            font-weight: bold;
        }
        .grade-b, .grade-b-plus, .grade-b-minus {
            color: #17a2b8;
            font-weight: bold;
        }
        .grade-c, .grade-c-plus, .grade-c-minus {
            color: #fd7e14;
            font-weight: bold;
        }
        .grade-d, .grade-d-plus, .grade-d-minus, .grade-e, .grade-f, .grade-g, .grade-u {
            color: #dc3545;
            font-weight: bold;
        }
        /* Print-specific styling */
        @media print {
            body * {
                visibility: hidden;
            }
            #printPreview, #printPreview * {
                visibility: visible;
            }
            #printPreview {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background-color: white;
                color: black;
                font-size: 12pt;
            }
            .print-preview table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            .print-preview th, .print-preview td {
                border: 1px solid #000;
                padding: 8px;
                text-align: left;
            }
            .print-preview th {
                background-color: #f2f2f2;
            }
            .print-logo {
                display: block;
                margin: 0 auto 15px;
            }
            .print-header {
                text-align: center;
                margin-bottom: 20px;
            }
            .print-footer {
                text-align: center;
                margin-top: 30px;
                font-size: 10pt;
                color: #666;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#"><img src="img/logo.png" alt="Logo"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1 class="text-center header-title animate__animated animate__fadeIn">Abuhureira Academy</h1>
        <p class="text-center mb-3 text-muted">Check your exam results by entering your student ID or password below.</p>

        <div class="control-container">
            <div class="form-container">
                <form method="POST" class="w-100 text-center needs-validation" novalidate>
                    <div class="input-group justify-content-center" style="max-width: 300px;">
                        <input type="text" name="identifier" id="identifier" class="form-control" placeholder="Input your password" required minlength="4">
                        <button type="submit" name="search" class="btn btn-search btn-primary">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            Search
                        </button>
                    </div>
                    <div class="invalid-feedback">Please enter a valid password (minimum 4 characters)</div>
                    <div class="remember-me">
                        <input type="checkbox" id="rememberMe" name="rememberMe" class="form-check-input">
                        <label for="rememberMe" class="form-check-label">Remember me</label>
                    </div>
                </form>
            </div>

            <?php if ($showResults): ?>
                <div class="button-group-top">
                    <button class="btn-top btn-view <?php echo htmlspecialchars($buttonClass); ?>" onclick="showPrintPreview()">View My Results</button>
                    <button class="btn-top btn-practice" onclick="window.location.href='game.html'">Practise Here</button>
                    <button class="btn-top btn-print" onclick="window.print()">Print My Results</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <?php if ($showResults): ?>
            <div class="row">
                <div class="col-md-6">
                    <div class="student-card">
                        <div style="position: relative;">
                            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="student-avatar" onerror="this.src='https://via.placeholder.com/80?text=Student';">
                        </div>
                        <div class="student-details">
                            <span class="student-tag">Student Info</span>
                            <h3><?php echo htmlspecialchars($studentName); ?></h3>
                            <p><?php echo htmlspecialchars($studentData['Class'] ?? $examOneData['Class'] ?? $examTwoData['Class'] ?? $examThreeData['Class'] ?? $examFourData['Class'] ?? 'N/A'); ?></p>
                            <p>Abuhureira Academy</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="quote-card animate__animated animate__fadeIn">
                        <div class="performance-snapshot">
                            <h5 class="snapshot-title">Your Performance Snapshot</h5>
                            <div class="progress-indicator">
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, max(0, $termAverage ?? 0)); ?>%;" 
                                         aria-valuenow="<?php echo $termAverage ?? 0; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo number_format($termAverage ?? 0, 1); ?>%
                                    </div>
                                </div>
                            </div>
                            <p class="snapshot-text">
                                <?php 
                                    $examCount = 0;
                                    if (!empty($studentData)) $examCount++;
                                    if (!empty($examOneData)) $examCount++;
                                    if (!empty($examTwoData)) $examCount++;
                                    if (!empty($examThreeData)) $examCount++;
                                    if (!empty($examFourData)) $examCount++;
                                    
                                    echo "Based on your $examCount exam results, we've analyzed your academic journey. ";
                                    
                                    if (!empty($bestSubjects)) {
                                        echo "You're showing particular strength in " . implode(", ", array_slice($bestSubjects, 0, 2));
                                        if (count($bestSubjects) > 2) echo " and other subjects";
                                        echo ". ";
                                    }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <h5 class="section-title"><span>📊</span> Results</h5>
                <?php if ($congratulations): ?>
                    <div class="alert alert-success text-center">
                        Congratulations, <?php echo htmlspecialchars($studentName); ?>! Your mean grade is <?php echo htmlspecialchars($meanGrade); ?>%.
                    </div>
                <?php endif; ?>

                <ul class="nav nav-tabs" id="examTabs" role="tablist">
                    <?php if (!empty($studentData)): ?>
                        <li class="nav-item">
                            <a class="nav-link active" id="student-tab" data-bs-toggle="tab" href="#student" role="tab" aria-controls="student" aria-selected="true">Student Record</a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($examOneData)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo empty($studentData) ? 'active' : ''; ?>" id="exam1-tab" data-bs-toggle="tab" href="#exam1" role="tab" aria-controls="exam1" aria-selected="<?php echo empty($studentData) ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($examOneData['examname'] ?? 'Exam 1'); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($examTwoData)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (empty($studentData) && empty($examOneData)) ? 'active' : ''; ?>" id="exam2-tab" data-bs-toggle="tab" href="#exam2" role="tab" aria-controls="exam2" aria-selected="<?php echo (empty($studentData) && empty($examOneData)) ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($examTwoData['examname'] ?? 'Exam 2'); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($examThreeData)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (empty($studentData) && empty($examOneData) && empty($examTwoData)) ? 'active' : ''; ?>" id="exam3-tab" data-bs-toggle="tab" href="#exam3" role="tab" aria-controls="exam3" aria-selected="<?php echo (empty($studentData) && empty($examOneData) && empty($examTwoData)) ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($examThreeData['examname'] ?? 'Exam 3'); ?></a>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($examFourData)): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (empty($studentData) && empty($examOneData) && empty($examTwoData) && empty($examThreeData)) ? 'active' : ''; ?>" id="exam4-tab" data-bs-toggle="tab" href="#exam4" role="tab" aria-controls="exam4" aria-selected="<?php echo (empty($studentData) && empty($examOneData) && empty($examTwoData) && empty($examThreeData)) ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($examFourData['examname'] ?? 'Exam 4'); ?></a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" id="analysis-tab" data-bs-toggle="tab" href="#analysis" role="tab" aria-controls="analysis" aria-selected="false">Analysis</a>
                    </li>
                </ul>
                <div class="tab-content" id="examTabsContent">
                    <?php if (!empty($studentData)): ?>
                        <div class="tab-pane fade show active" id="student" role="tabpanel" aria-labelledby="student-tab">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subjectScores = [];
                                    foreach ($subjectsToDisplay as $subject) {
                                        if (array_key_exists($subject, $studentData) && is_numeric($studentData[$subject]) && $studentData[$subject] >= 0) {
                                            $subjectScores[$subject] = $studentData[$subject];
                                        }
                                    }
                                    arsort($subjectScores);
                                    foreach ($subjectScores as $subject => $score): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject); ?></td>
                                            <td><?php echo htmlspecialchars($score); ?></td>
                                            <td><?php echo htmlspecialchars(getGrade($score, $studentData['Class'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td><?php echo htmlspecialchars($studentData['Total'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Average</strong></td>
                                        <td><?php echo number_format($studentData['Average'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($studentData['Grade'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Points</strong></td>
                                        <td><?php echo htmlspecialchars($studentData['total_points'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($examOneData)): ?>
                        <div class="tab-pane fade <?php echo empty($studentData) ? 'show active' : ''; ?>" id="exam1" role="tabpanel" aria-labelledby="exam1-tab">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subjectScores = [];
                                    foreach ($subjectsToDisplay as $subject) {
                                        if (array_key_exists($subject, $examOneData) && is_numeric($examOneData[$subject]) && $examOneData[$subject] >= 0) {
                                            $subjectScores[$subject] = $examOneData[$subject];
                                        }
                                    }
                                    arsort($subjectScores);
                                    foreach ($subjectScores as $subject => $score): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject); ?></td>
                                            <td><?php echo htmlspecialchars($score); ?></td>
                                            <td><?php echo htmlspecialchars(getGrade($score, $examOneData['Class'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td><?php echo htmlspecialchars($examOneData['Total'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Average</strong></td>
                                        <td><?php echo number_format($examOneData['Average'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($examOneData['Grade'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Points</strong></td>
                                        <td><?php echo htmlspecialchars($examOneData['total_points'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($examTwoData)): ?>
                        <div class="tab-pane fade <?php echo (empty($studentData) && empty($examOneData)) ? 'show active' : ''; ?>" id="exam2" role="tabpanel" aria-labelledby="exam2-tab">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subjectScores = [];
                                    foreach ($subjectsToDisplay as $subject) {
                                        if (array_key_exists($subject, $examTwoData) && is_numeric($examTwoData[$subject]) && $examTwoData[$subject] >= 0) {
                                            $subjectScores[$subject] = $examTwoData[$subject];
                                        }
                                    }
                                    arsort($subjectScores);
                                    foreach ($subjectScores as $subject => $score): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject); ?></td>
                                            <td><?php echo htmlspecialchars($score); ?></td>
                                            <td><?php echo htmlspecialchars(getGrade($score, $examTwoData['Class'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td><?php echo htmlspecialchars($examTwoData['Total'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Average</strong></td>
                                        <td><?php echo number_format($examTwoData['Average'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($examTwoData['Grade'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Points</strong></td>
                                        <td><?php echo htmlspecialchars($examTwoData['total_points'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($examThreeData)): ?>
                        <div class="tab-pane fade <?php echo (empty($studentData) && empty($examOneData) && empty($examTwoData)) ? 'show active' : ''; ?>" id="exam3" role="tabpanel" aria-labelledby="exam3-tab">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subjectScores = [];
                                    foreach ($subjectsToDisplay as $subject) {
                                        if (array_key_exists($subject, $examThreeData) && is_numeric($examThreeData[$subject]) && $examThreeData[$subject] >= 0) {
                                            $subjectScores[$subject] = $examThreeData[$subject];
                                        }
                                    }
                                    arsort($subjectScores);
                                    foreach ($subjectScores as $subject => $score): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject); ?></td>
                                            <td><?php echo htmlspecialchars($score); ?></td>
                                            <td><?php echo htmlspecialchars(getGrade($score, $examThreeData['Class'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td><?php echo htmlspecialchars($examThreeData['Total'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Average</strong></td>
                                        <td><?php echo number_format($examThreeData['Average'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($examThreeData['Grade'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Points</strong></td>
                                        <td><?php echo htmlspecialchars($examThreeData['total_points'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($examFourData)): ?>
                        <div class="tab-pane fade <?php echo (empty($studentData) && empty($examOneData) && empty($examTwoData) && empty($examThreeData)) ? 'show active' : ''; ?>" id="exam4" role="tabpanel" aria-labelledby="exam4-tab">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $subjectScores = [];
                                    foreach ($subjectsToDisplay as $subject) {
                                        if (array_key_exists($subject, $examFourData) && is_numeric($examFourData[$subject]) && $examFourData[$subject] >= 0) {
                                            $subjectScores[$subject] = $examFourData[$subject];
                                        }
                                    }
                                    arsort($subjectScores);
                                    foreach ($subjectScores as $subject => $score): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($subject); ?></td>
                                            <td><?php echo htmlspecialchars($score); ?></td>
                                            <td><?php echo htmlspecialchars(getGrade($score, $examFourData['Class'] ?? '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td><strong>Total</strong></td>
                                        <td><?php echo htmlspecialchars($examFourData['Total'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Average</strong></td>
                                        <td><?php echo number_format($examFourData['Average'] ?? 0, 2); ?></td>
                                        <td><?php echo htmlspecialchars($examFourData['Grade'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Points</strong></td>
                                        <td><?php echo htmlspecialchars($examFourData['total_points'] ?? '-'); ?></td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <!-- Analysis Tab -->
                    <div class="tab-pane fade" id="analysis" role="tabpanel" aria-labelledby="analysis-tab">
                        <div class="row">
                            <!-- Performance Trends -->
                            <div class="col-12 mb-4">
                                <h6>Performance Trends</h6>
                                <div class="canvas-container">
                                    <canvas id="performanceTrendChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Subject-Wise Analysis Table -->
                            <div class="col-12 mb-4">
                                <h6>Subject-Wise Analysis</h6>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <?php if (!empty($studentData)): ?>
                                                <th><?php echo htmlspecialchars($studentData['examname'] ?? 'Student Record'); ?></th>
                                            <?php endif; ?>
                                            <?php if (!empty($examOneData)): ?>
                                                <th><?php echo htmlspecialchars($examOneData['examname'] ?? 'Exam 1'); ?></th>
                                            <?php endif; ?>
                                            <?php if (!empty($examTwoData)): ?>
                                                <th><?php echo htmlspecialchars($examTwoData['examname'] ?? 'Exam 2'); ?></th>
                                            <?php endif; ?>
                                            <?php if (!empty($examThreeData)): ?>
                                                <th><?php echo htmlspecialchars($examThreeData['examname'] ?? 'Exam 3'); ?></th>
                                            <?php endif; ?>
                                            <?php if (!empty($examFourData)): ?>
                                                <th><?php echo htmlspecialchars($examFourData['examname'] ?? 'Exam 4'); ?></th>
                                            <?php endif; ?>
                                            <th>Average</th>
                                            <th>Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($analysisData['subjects'] as $subject => $data): ?>
                                            <?php if (isset($data['count']) && $data['count'] > 0 && $subject !== 'Student Record' && $subject !== 'Exam 1' && $subject !== 'Exam 2' && $subject !== 'Exam 3' && $subject !== 'Exam 4'): ?>
                                                <?php
                                                // Collect all valid scores for the subject
                                                $scores = [];
                                                if (!empty($studentData) && array_key_exists($subject, $studentData) && is_numeric($studentData[$subject]) && $studentData[$subject] >= 0) {
                                                    $scores[] = (float)$studentData[$subject];
                                                }
                                                if (!empty($examOneData) && array_key_exists($subject, $examOneData) && is_numeric($examOneData[$subject]) && $examOneData[$subject] >= 0) {
                                                    $scores[] = (float)$examOneData[$subject];
                                                }
                                                if (!empty($examTwoData) && array_key_exists($subject, $examTwoData) && is_numeric($examTwoData[$subject]) && $examTwoData[$subject] >= 0) {
                                                    $scores[] = (float)$examTwoData[$subject];
                                                }
                                                if (!empty($examThreeData) && array_key_exists($subject, $examThreeData) && is_numeric($examThreeData[$subject]) && $examThreeData[$subject] >= 0) {
                                                    $scores[] = (float)$examThreeData[$subject];
                                                }
                                                if (!empty($examFourData) && array_key_exists($subject, $examFourData) && is_numeric($examFourData[$subject]) && $examFourData[$subject] >= 0) {
                                                    $scores[] = (float)$examFourData[$subject];
                                                }
                                                
                                                // Calculate average
                                                $avg = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
                                                
                                                // Determine row highlighting
                                                $rowClass = '';
                                                if ($avg >= 70) $rowClass = 'highlight-high';
                                                elseif ($avg < 50 && $avg > 0) $rowClass = 'highlight-low';
                                                ?>
                                                <tr class="<?php echo $rowClass; ?>">
                                                    <td><?php echo htmlspecialchars($subject); ?></td>
                                                    <?php if (!empty($studentData)): ?>
                                                        <td><?php echo (array_key_exists($subject, $studentData) && is_numeric($studentData[$subject]) && $studentData[$subject] >= 0) ? htmlspecialchars($studentData[$subject]) : '-'; ?></td>
                                                    <?php endif; ?>
                                                    <?php if (!empty($examOneData)): ?>
                                                        <td><?php echo (array_key_exists($subject, $examOneData) && is_numeric($examOneData[$subject]) && $examOneData[$subject] >= 0) ? htmlspecialchars($examOneData[$subject]) : '-'; ?></td>
                                                    <?php endif; ?>
                                                    <?php if (!empty($examTwoData)): ?>
                                                        <td><?php echo (array_key_exists($subject, $examTwoData) && is_numeric($examTwoData[$subject]) && $examTwoData[$subject] >= 0) ? htmlspecialchars($examTwoData[$subject]) : '-'; ?></td>
                                                    <?php endif; ?>
                                                    <?php if (!empty($examThreeData)): ?>
                                                        <td><?php echo (array_key_exists($subject, $examThreeData) && is_numeric($examThreeData[$subject]) && $examThreeData[$subject] >= 0) ? htmlspecialchars($examThreeData[$subject]) : '-'; ?></td>
                                                    <?php endif; ?>
                                                    <?php if (!empty($examFourData)): ?>
                                                        <td><?php echo (array_key_exists($subject, $examFourData) && is_numeric($examFourData[$subject]) && $examFourData[$subject] >= 0) ? htmlspecialchars($examFourData[$subject]) : '-'; ?></td>
                                                    <?php endif; ?>
                                                    <td><?php echo number_format($avg, 2); ?></td>
                                                    <td>
                                                        <canvas class="sparkline" data-scores="<?php echo htmlspecialchars(json_encode($scores)); ?>"></canvas>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Preview Section (Hidden by default) -->
            <div id="printPreview" style="display: none;">
                <div class="print-header">
                    <img src="img/logo.png" alt="Logo" class="print-logo" onerror="this.src='https://via.placeholder.com/60?text=Logo';">
                    <h2>Abuhureira Academy</h2>
                    <h3>Student Results</h3>
                </div>
                <div class="student-info">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($studentName); ?></p>
                    <p><strong>Class:</strong> <?php echo htmlspecialchars($studentData['Class'] ?? $examOneData['Class'] ?? $examTwoData['Class'] ?? $examThreeData['Class'] ?? $examFourData['Class'] ?? 'N/A'); ?></p>
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Subjects</th>
                            <th>Total</th>
                            <th>Average</th>
                            <th>Grade</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($studentData)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($studentData['examname'] ?? 'Student Record'); ?></td>
                                <td>
                                    <?php
                                    $subjects = [];
                                    foreach ($subjectsStudent as $subject) {
                                        if (array_key_exists($subject, $studentData) && is_numeric($studentData[$subject]) && $studentData[$subject] >= 0) {
                                            $subjects[] = "$subject: " . $studentData[$subject];
                                        }
                                    }
                                    echo implode(', ', $subjects);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($studentData['Total'] ?? '-'); ?></td>
                                <td><?php echo number_format($studentData['Average'] ?? 0, 2); ?></td>
                                <td><?php echo htmlspecialchars($studentData['Grade'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($studentData['total_points'] ?? '-'); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($examOneData)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($examOneData['examname'] ?? 'Exam 1'); ?></td>
                                <td>
                                    <?php
                                    $subjects = [];
                                    foreach ($subjectsExams as $subject) {
                                        if (array_key_exists($subject, $examOneData) && is_numeric($examOneData[$subject]) && $examOneData[$subject] >= 0) {
                                            $subjects[] = "$subject: " . $examOneData[$subject];
                                        }
                                    }
                                    echo implode(', ', $subjects);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($examOneData['Total'] ?? '-'); ?></td>
                                <td><?php echo number_format($examOneData['Average'] ?? 0, 2); ?></td>
                                <td><?php echo htmlspecialchars($examOneData['Grade'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($examOneData['total_points'] ?? '-'); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($examTwoData)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($examTwoData['examname'] ?? 'Exam 2'); ?></td>
                                <td>
                                    <?php
                                    $subjects = [];
                                    foreach ($subjectsExams as $subject) {
                                        if (array_key_exists($subject, $examTwoData) && is_numeric($examTwoData[$subject]) && $examTwoData[$subject] >= 0) {
                                            $subjects[] = "$subject: " . $examTwoData[$subject];
                                        }
                                    }
                                    echo implode(', ', $subjects);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($examTwoData['Total'] ?? '-'); ?></td>
                                <td><?php echo number_format($examTwoData['Average'] ?? 0, 2); ?></td>
                                <td><?php echo htmlspecialchars($examTwoData['Grade'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($examTwoData['total_points'] ?? '-'); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($examThreeData)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($examThreeData['examname'] ?? 'Exam 3'); ?></td>
                                <td>
                                    <?php
                                    $subjects = [];
                                    foreach ($subjectsExams as $subject) {
                                        if (array_key_exists($subject, $examThreeData) && is_numeric($examThreeData[$subject]) && $examThreeData[$subject] >= 0) {
                                            $subjects[] = "$subject: " . $examThreeData[$subject];
                                        }
                                    }
                                    echo implode(', ', $subjects);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($examThreeData['Total'] ?? '-'); ?></td>
                                <td><?php echo number_format($examThreeData['Average'] ?? 0, 2); ?></td>
                                <td><?php echo htmlspecialchars($examThreeData['Grade'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($examThreeData['total_points'] ?? '-'); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($examFourData)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($examFourData['examname'] ?? 'Exam 4'); ?></td>
                                <td>
                                    <?php
                                    $subjects = [];
                                    foreach ($subjectsExams as $subject) {
                                        if (array_key_exists($subject, $examFourData) && is_numeric($examFourData[$subject]) && $examFourData[$subject] >= 0) {
                                            $subjects[] = "$subject: " . $examFourData[$subject];
                                        }
                                    }
                                    echo implode(', ', $subjects);
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($examFourData['Total'] ?? '-'); ?></td>
                                <td><?php echo number_format($examFourData['Average'] ?? 0, 2); ?></td>
                                <td><?php echo htmlspecialchars($examFourData['Grade'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($examFourData['total_points'] ?? '-'); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Term Summary</strong></td>
                            <td>
                                <?php
                                $subjects = [];
                                foreach ($termSummary as $subject => $score) {
                                    if (in_array($subject, $subjectsToDisplay) && $score > 0) {
                                        $subjects[] = "$subject: " . number_format($score, 2);
                                    }
                                }
                                echo implode(', ', $subjects);
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($termSummary['Total'] ?? '-'); ?></td>
                            <td><?php echo number_format($termSummary['Average'] ?? 0, 2); ?></td>
                            <td><?php echo htmlspecialchars($termSummary['Grade'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($termSummary['total_points'] ?? '-'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <p><strong>Date Printed:</strong> <?php echo date('Y-m-d'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Performance Data
        const performanceData = <?php echo $performanceData; ?>;
        const analysisData = <?php echo $analysisDataJson; ?>;

        // Helper function to get grade class
        function getGradeClass(grade) {
            if (!grade) return '';
            grade = grade.toLowerCase();
            if (grade === 'a' || grade === 'a*' || grade === 'a-') return 'grade-a';
            if (grade === 'b' || grade === 'b+' || grade === 'b-') return 'grade-b';
            if (grade === 'c' || grade === 'c+' || grade === 'c-') return 'grade-c';
            return 'grade-d';
        }

        // Apply grade coloring to all grade cells
        function applyGradeColoring() {
            document.querySelectorAll('table td:nth-child(3)').forEach(cell => {
                const grade = cell.textContent.trim();
                if (grade && grade !== '-') {
                    const gradeClass = getGradeClass(grade);
                    cell.classList.add(gradeClass);
                }
            });
        }

        // Cookie functions for Remember Me
        function setCookie(name, value, days) {
            let expires = "";
            if (days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                expires = "; expires=" + date.toUTCString();
            }
            document.cookie = name + "=" + (value || "") + expires + "; path=/";
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        // Form validation and loading indicator
        document.addEventListener('DOMContentLoaded', function() {
            // Check for saved identifier
            const savedIdentifier = getCookie('studentIdentifier');
            if (savedIdentifier) {
                document.getElementById('identifier').value = savedIdentifier;
                document.getElementById('rememberMe').checked = true;
            }

            // Form validation
            const form = document.querySelector('.needs-validation');
            
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    // Show loading spinner
                    document.querySelector('.btn-search').classList.add('loading');
                    
                    // Save identifier if remember me is checked
                    if (document.getElementById('rememberMe').checked) {
                        setCookie('studentIdentifier', document.getElementById('identifier').value, 30);
                    } else {
                        setCookie('studentIdentifier', '', -1);
                    }
                }
                
                form.classList.add('was-validated');
            });

            // Apply grade coloring
            if (document.querySelector('table')) {
                applyGradeColoring();
            }

            // Initialize Performance Trend Chart
            if (document.getElementById('performanceTrendChart')) {
                initializePerformanceTrendChart();
            }

            // Initialize sparklines
            document.querySelectorAll('.sparkline').forEach(function(canvas) {
                const scores = JSON.parse(canvas.getAttribute('data-scores') || '[]');
                if (scores.length > 0) {
                    drawSparkline(canvas, scores);
                }
            });
        });

        // Draw sparkline chart
        function drawSparkline(canvas, data) {
            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;
            const maxValue = Math.max(...data, 100);
            const minValue = Math.min(...data, 0);
            
            ctx.clearRect(0, 0, width, height);
            
            // Draw line
            ctx.beginPath();
            ctx.moveTo(0, height - (data[0] / maxValue) * height);
            
            for (let i = 1; i < data.length; i++) {
                const x = (i / (data.length - 1)) * width;
                const y = height - (data[i] / maxValue) * height;
                ctx.lineTo(x, y);
            }
            
            ctx.strokeStyle = '#007bff';
            ctx.lineWidth = 2;
            ctx.stroke();
            
            // Draw points
            for (let i = 0; i < data.length; i++) {
                const x = (i / (data.length - 1)) * width;
                const y = height - (data[i] / maxValue) * height;
                
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, Math.PI * 2);
                ctx.fillStyle = data[i] >= 60 ? '#28a745' : (data[i] >= 50 ? '#fd7e14' : '#dc3545');
                ctx.fill();
            }
        }

        // Initialize Performance Trend Chart
        function initializePerformanceTrendChart() {
            const ctx = document.getElementById('performanceTrendChart').getContext('2d');
            
            // Extract data
            const labels = Object.keys(performanceData);
            const data = Object.values(performanceData);
            
            // Create gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(40, 167, 69, 0.8)');
            gradient.addColorStop(1, 'rgba(40, 167, 69, 0.1)');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Average Score',
                        data: data,
                        borderColor: '#28a745',
                        backgroundColor: gradient,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#28a745',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Average Score (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Exams'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Average: ${context.raw.toFixed(2)}%`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Print Preview Function
        function showPrintPreview() {
            const printPreview = document.getElementById('printPreview');
            printPreview.style.display = 'block';
            window.scrollTo(0, printPreview.offsetTop);
            
            // Auto-print after a short delay
            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    printPreview.style.display = 'none';
                }, 1000);
            }, 500);
        }
    </script>
</body>
</html>