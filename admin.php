<?php
require "vendor/autoload.php";

// Place this at the absolute top of your PHP script file
header("Access-Control-Allow-Origin: *");

use Firebase\JWT\JWT;
$secretKey = "MY_SECRET_KEY";

// 1. Define allowed domains
$allowed_origins = [
    "http://192.168.0.122",
    "http://localhost:4200",
    "https://unbragging-conchita-superadjacent.ngrok-free.dev"
];

// 1. Approve the client domain dynamically
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}

// 2. Set the CORS credentials and methods headers BEFORE checking the request method
header("Access-Control-Allow-Credentials: true"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Content-Type: application/json");

// 3. Handle browser preflight checks immediately and include those headers!
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0); // Exit smoothly *after* sending the headers above
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 2. Include Composer's autoloader

// 4. Start your session now
session_start();


// ==========================================
// 🌟 MOVE THIS HERE: Establish DB Connection Safely 
// ==========================================
$mysqli = new mysqli("localhost", "root", "Dan123??", "alumniportal");

if ($mysqli->connect_error) {
    http_response_code(500);
    die(json_encode(["error" => "Connection failed: " . $mysqli->connect_error]));
}

// 6. Parse the input data GLOBALLY
$input = [];
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true) ?? [];
    }
} else if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $input = $_GET;
}

// 7. Get the action query parameter safely
$action = $_GET['action'] ?? $input['action'] ?? '';

switch ($action) {
  
case 'stats':
    // 1. Get standard analytics counts
    $totalUsers = $mysqli->query("SELECT COUNT(*) AS cnt FROM Users WHERE LOWER(role) = 'alumni'")->fetch_assoc()['cnt'];
    $graduateYearsCount = $mysqli->query("SELECT COUNT(DISTINCT Graduation_class) AS cnt FROM Users")->fetch_assoc()['cnt'];
   $activeEmployees = $mysqli->query("SELECT COUNT(DISTINCT ce.user_id) AS cnt FROM CurrentEmployment ce INNER JOIN Users u ON ce.user_id = u.id WHERE u.role != 'admin' AND u.status = 'Verified'")?->fetch_assoc()['cnt'] ?? 0;
   $verifiedCount = $mysqli->query("SELECT COUNT(*) AS cnt FROM Users WHERE status = 'Verified' AND role != 'admin'")->fetch_assoc()['cnt'];
    $pendingCount = $mysqli->query("SELECT COUNT(*) AS cnt FROM Users WHERE status = 'Pending' OR status IS NULL OR status = ''")->fetch_assoc()['cnt'];
    $revokedCount = $mysqli->query("SELECT COUNT(*) AS cnt FROM Users WHERE status = 'Revoked'")->fetch_assoc()['cnt'];
    $programmes = $mysqli->query("SELECT COUNT(*) AS cnt FROM Programmes")->fetch_assoc()['cnt'];
    $cohort = $mysqli->query("SELECT COUNT(*) AS cnt FROM Cohort")->fetch_assoc()['cnt'];
    
    // 2. FETCH ALL USERS
    $usersQuery = "SELECT id, First_name, Email, Phone_number, Graduation_class, photo, Dob, Gender, status FROM Users WHERE role != 'admin'";
    $users = $mysqli->query($usersQuery)->fetch_all(MYSQLI_ASSOC);
    
    // 3. Fetch ALL sub-tables to feed the Angular Excel exporter correctly
    $employmentQuery = "SELECT id, user_id, employer_name, role, employment_type, work_county, start_date FROM CurrentEmployment";
    $currentEmployment = $mysqli->query($employmentQuery)->fetch_all(MYSQLI_ASSOC);
    

    $academicQuery = "SELECT id, user_id, programme_name, cohort_name, reg_number FROM AcademicRecord";
    $academicRecords = $mysqli->query($academicQuery)->fetch_all(MYSQLI_ASSOC);

    $socialQuery = "SELECT id, user_id, linkedin, home_county, physical_address FROM SocialContact";
    $socialContact = $mysqli->query($socialQuery)->fetch_all(MYSQLI_ASSOC);

    $membershipQuery = "SELECT id, user_id, organization, membership_id FROM ProfessionalMembership";
    $professionalMembership = $mysqli->query($membershipQuery)->fetch_all(MYSQLI_ASSOC);

    $educationQuery = "SELECT id, user_id, institution, field_of_study, year_completed FROM FurtherEducation";
    $furtherEducation = $mysqli->query($educationQuery)->fetch_all(MYSQLI_ASSOC);
   
    $EmploymentHistoryQuery = "SELECT id, user_id, employer_name, role, from_date, to_date FROM EmploymentHistory";
    $EmploymentHistory = $mysqli->query($EmploymentHistoryQuery)->fetch_all(MYSQLI_ASSOC);

    $cohortDistribution = $mysqli->query("SELECT Graduation_class AS cohort_name, COUNT(*) AS cnt FROM Users WHERE Graduation_class IS NOT NULL AND Graduation_class != '' GROUP BY Graduation_class ORDER BY Graduation_class DESC")->fetch_all(MYSQLI_ASSOC);
    
    // 4. Send back clean, populated JSON payload
    echo json_encode([
        "totalUsers" => (int)$totalUsers,
        "graduateYearsCount" => (int)$graduateYearsCount,
        "activeEmployees" => (int)$activeEmployees,
        "verifiedUsers" => (int)$verifiedCount, 
        "revokedUsers" => (int)$revokedCount,
        "pendingUsers" => (int)$pendingCount,
        "programmes_count" => (int)$programmes,
        "cohorts_count" => (int)$cohort,
        "users" => $users, 
        "cohortDistribution" => $cohortDistribution,
        "CurrentEmployment" => $currentEmployment,
        "academicRecords" => $academicRecords,
        "socialContact" => $socialContact, 
        "EmploymentHistory" => $EmploymentHistory,           // 🛠️ FIXED: Trailing space removed!
        "ProfessionalMembership" => $professionalMembership, 
        "FurtherEducation" => $furtherEducation             
    ]);
    break; 

case 'getAcademicRecord':
    $user_id = intval($_GET['user_id'] ?? 0);
    $result = $mysqli->query("SELECT * FROM AcademicRecord WHERE user_id=$user_id");
    if ($result && $result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(null);
    }
    break;


case 'getCurrentEmployment':
    $user_id = intval($_GET['user_id'] ?? 0);
    $result = $mysqli->query("SELECT * FROM CurrentEmployment WHERE user_id=$user_id");
    if ($result && $result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(null);
    }
    break;

case 'getFurtherEducation':
    $user_id = intval($_GET['user_id'] ?? 0);
    $result = $mysqli->query("SELECT * FROM FurtherEducation WHERE user_id=$user_id");
    if ($result && $result->num_rows > 0) {
        echo json_encode($result->fetch_assoc());
    } else {
        echo json_encode(null);
    }
    break;

case 'history':
    $user_id = intval($_GET['user_id'] ?? 0);
    // Fetching array list safely matching profile
    $history = $mysqli->query("SELECT id, user_id, employer_name, role, from_date, to_date FROM EmploymentHistory WHERE user_id = $user_id")->fetch_all(MYSQLI_ASSOC);
    echo json_encode($history);
    break;

case 'social':
    $user_id = intval($_GET['user_id'] ?? 0);
    $social = $mysqli->query("SELECT * FROM SocialContact WHERE user_id = $user_id")->fetch_assoc();
    echo json_encode($social ? $social : null);
    break;

case 'proffesional': 
    $user_id = intval($_GET['user_id'] ?? 0);
    $proffesional = $mysqli->query("SELECT * FROM ProfessionalMembership WHERE user_id = $user_id")->fetch_assoc();
    echo json_encode($proffesional ? $proffesional : null);
    break;
  
case 'verify':
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : 0; 
    $new_status = isset($data['status']) ? $data['status'] : 'Verified';

    if ($user_id <= 0 || $adminId <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid User ID or Admin context identity missing."]);
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE Users SET status = ?, verified_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);

    if ($stmt->execute()) {
        $stmt->close(); // Close immediately to free up the resource

        // Fetch user information
        $detailsQuery = $mysqli->query("SELECT First_name, Email FROM Users WHERE id = $user_id");
        $target = $detailsQuery->fetch_assoc();
        
        $firstName = $target['First_name'] ?? 'Alumni';
        $userEmail = $target['Email'] ?? '';
        $targetDetails = "Name: " . $firstName . ", Email: " . ($userEmail ?? 'Unknown');

        // Log to Audit Trail
        $actionDescription = "Approved & Verified Alumni Account";
        $logStmt = $mysqli->prepare("INSERT INTO audit_logs (admin_id, action_string, target_details) VALUES (?, ?, ?)");
        $logStmt->bind_param("iss", $adminId, $actionDescription, $targetDetails);
        $logStmt->execute();
        $logStmt->close();

        // Send the Approval Email via PHPMailer
        $emailStatusMessage = "";
        if (!empty($userEmail)) {
            // Uses your defined imports (use PHPMailer\PHPMailer\PHPMailer;)
            $mail = new PHPMailer(true); 

            try {
                // Server settings matching your forgotPassword config
                $mail->isSMTP();                                            
                $mail->Host       = 'smtp.gmail.com';                       
                $mail->SMTPAuth   = true;                                   
                $mail->Username   = 'wawerumwangidan@gmail.com';         
                $mail->Password   = 'phjiitkagwttwyvc';           
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
                $mail->Port       = 587; 

                // Recipients
                $mail->setFrom('wawerumwangidan@gmail.com', 'SLCMC Portal Security');
                $mail->addAddress($userEmail, $firstName);     

                // Content
                $mail->isHTML(true);                                        
                $mail->Subject = 'SLCMC Alumni Portal Account Approved';
                
                // Beautifully designed HTML Email Template matching your branding
                $mail->Body = "
                    <div style='font-family: sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 500px;'>
                        <h2 style='color: #0891b2;'>Account Approved!</h2>
                        <p>Hello " . htmlspecialchars($firstName) . ",</p>
                        <p>Great news! Your details have been successfully reviewed and your registration on the <strong>Sister Leonella Consolata Medical College Alumni Portal</strong> has been approved.</p>
                        <p>You can now sign in to your dashboard to connect with fellow alumni and explore portal features.</p>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='https://yourdomain.com/login' style='background-color: #0891b2; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Log In to Portal</a>
                        </div>
                        
                        <p style='font-size: 13px; color: #64748b;'>If you didn't expect this, or if you encounter issues logging in, please contact the campus portal support team.</p>
                        <hr style='border: 0; border-top: 1px solid #e2e8f0; margin-top: 25px;'>
                        <p style='font-size: 12px; color: #94a3b8;'>Sister Leonella Consolata Medical College Alumni Team</p>
                    </div>
                ";
                
                $mail->AltBody = "Hello " . $firstName . ",\n\nGreat news! Your details have been approved on the Sister Leonella Consolata Medical College Alumni Portal. You can now log in to your dashboard.";

                $mail->send();
                $emailStatusMessage = " and approval email dispatched";
            } catch (Exception $e) {
                // Log the mail error silently so the user record still finishes updating successfully
                error_log("Approval email structural failure: " . $mail->ErrorInfo);
                $emailStatusMessage = " but email transmission failed";
            }
        }

        echo json_encode([
            "status" => "success", 
            "message" => "User status successfully updated to $new_status, logged" . $emailStatusMessage . "."
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database execution failure: " . $stmt->error]);
        $stmt->close();
    }
    break;
      
case 'revoke':
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : 0; 

    if ($user_id <= 0 || $adminId <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid User ID or Admin context identity missing."]);
        exit;
    }

    $stmt = $mysqli->prepare("UPDATE Users SET status = 'Revoked' WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $detailsQuery = $mysqli->query("SELECT First_name, Email FROM Users WHERE id = $user_id");
        $target = $detailsQuery->fetch_assoc();
        $targetDetails = "Name: " . ($target['First_name'] ?? 'Unknown') . ", Email: " . ($target['Email'] ?? 'Unknown');

        $actionDescription = "Revoked Alumni Account Access";
        $logStmt = $mysqli->prepare("INSERT INTO audit_logs (admin_id, action_string, target_details) VALUES (?, ?, ?)");
        $logStmt->bind_param("iss", $adminId, $actionDescription, $targetDetails);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["status" => "success", "message" => "User credentials successfully revoked and logged."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database execution failure: " . $stmt->error]);
    }
    
    $stmt->close();
    break;
   case 'editUser':
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : 0;

    if ($user_id <= 0 || $adminId <= 0) {
        echo json_encode(["status" => "error", "message" => "Context parameters missing."]);
        exit;
    }

    // Begin standard sequential update statements
    // 1. Core Users Table
    $stmt1 = $mysqli->prepare("UPDATE Users SET First_name = ?, Email = ?, Phone_number = ?, Gender = ?, Graduation_class = ? WHERE id = ?");
    $stmt1->bind_param("sssssi", $data['First_name'], $data['email'], $data['Phone_number'], $data['Gender'], $data['Graduation_class'], $user_id);
    $stmt1->execute();
    $stmt1->close();

    // 2. Academic Records
    $stmt2 = $mysqli->prepare("UPDATE academic_records SET programme_name = ?, cohort_name = ?, graduation_year = ?, reg_number = ? WHERE user_id = ?");
    $stmt2->bind_param("ssssi", $data['programme_name'], $data['cohort_name'], $data['graduation_year'], $data['reg_number'], $user_id);
    $stmt2->execute();
    $stmt2->close();

    // 3. Current Employment Records
    $stmt3 = $mysqli->prepare("UPDATE employment_records SET employer_name = ?, role = ?, employment_type = ?, work_county = ? WHERE user_id = ?");
    $stmt3->bind_param("ssssi", $data['employer_name'], $data['role'], $data['employment_type'], $data['work_county'], $user_id);
    $stmt3->execute();
    $stmt3->close();

    // 4. Social Contacts
    $stmt4 = $mysqli->prepare("UPDATE social_contacts SET linkedin = ?, physical_address = ? WHERE user_id = ?");
    $stmt4->bind_param("ssi", $data['linkedin'], $data['physical_address'], $user_id);
    $stmt4->execute();
    $stmt4->close();

    // Add Audit Log
    $logStmt = $mysqli->prepare("INSERT INTO audit_logs (admin_id, action_string, target_details) VALUES (?, 'Fully Updated Comprehensive Profile Information Map', ?)");
    $tgt = "ID: " . $user_id . " Name: " . $data['First_name'];
    $logStmt->bind_param("is", $adminId, $tgt);
    $logStmt->execute();
    $logStmt->close();

    echo json_encode(["status" => "success", "message" => "All selected profile sections updated successfully."]);
    break;
 
case 'deleteUser':
    $data = json_decode(file_get_contents("php://input"), true);
   $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $adminId = isset($data['admin_id']) ? (int)$data['admin_id'] : 0;

    if ($user_id <= 0 || $adminId <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid User ID or Admin context identity missing."]);
        exit;
    }

    // Fetch user details first for tracking history logs before destruction
    $detailsQuery = $mysqli->query("SELECT First_name, Email FROM Users WHERE id = $user_id");
    $target = $detailsQuery->fetch_assoc();
    $targetDetails = "Name: " . ($target['First_name'] ?? 'Unknown') . ", Email: " . ($target['Email'] ?? 'Unknown');

    // Perform hard removal from the main directory table
    $stmt = $mysqli->prepare("DELETE FROM Users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        // Log to Audit Trail
        $actionDescription = "Permanently Deleted User Account Profile";
        
        $logStmt = $mysqli->prepare("INSERT INTO audit_logs (admin_id, action_string, target_details) VALUES (?, ?, ?)");
        $logStmt->bind_param("iss", $adminId, $actionDescription, $targetDetails);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["status" => "success", "message" => "User record completely dropped from registry tables and action logged."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database execution failure: " . $stmt->error]);
    }
    $stmt->close();
    break;
    
case 'addProgramme':
    $programmeName = isset($_POST['programme']) ? trim($_POST['programme']) : '';
    $programmeName = $mysqli->real_escape_string($programmeName);

    if (!empty($programmeName)) {
        $query = "INSERT INTO Programmes (programme) VALUES ('$programmeName')";
        if ($mysqli->query($query)) {
            echo json_encode(["success" => true, "message" => "Programme saved"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Database failure: " . $mysqli->error]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid name input parameters", "received" => $_POST]);
    }
    break;

case 'addCohort':
    $cohortName = isset($_POST['cohort']) ? trim($_POST['cohort']) : '';
    $cohortName = $mysqli->real_escape_string($cohortName);
    $programmeId = isset($_POST['programme_id']) ? (int)$_POST['programme_id'] : 0;

    if (!empty($cohortName) && $programmeId > 0) {
        $query = "INSERT INTO Cohort (programme_id, cohort) VALUES ($programmeId, '$cohortName')";
        if ($mysqli->query($query)) {
            echo json_encode(["success" => true, "message" => "Cohort linked successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Database failure: " . $mysqli->error]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Missing mapping identity fields", "received" => $_POST]);
    }
    break;

case 'getProgrammes':
    $result = $mysqli->query("SELECT id, programme FROM Programmes ORDER BY id DESC");
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    break;

case 'getcohort':
    $query = "SELECT c.id, c.programme_id, c.cohort, p.programme AS programme_name 
              FROM Cohort c
              INNER JOIN Programmes p ON c.programme_id = p.id 
              ORDER BY c.id DESC";
              
    $result = $mysqli->query($query);
    if ($result) {
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Database failure: " . $mysqli->error]);
    }
    break;

case 'createUser':
    $data = json_decode(file_get_contents("php://input"), true);
    $adminId   = isset($data['admin_id']) ? (int)$data['admin_id'] : 0;
    $firstName = isset($data['First_name']) ? trim($data['First_name']) : '';
    $email     = isset($data['email'])      ? trim($data['email'])      : '';
    $role      = isset($data['role'])       ? trim($data['role'])       : 'Alumni'; 
    $password  = isset($data['password'])   ? trim($data['password'])   : 'SLCMC123'; 

    if ($adminId <= 0) {
        echo json_encode(["status" => "error", "message" => "Unauthorized action: Admin context identity required."]);
        exit;
    }

    if (empty($firstName) || empty($email)) {
        echo json_encode(["status" => "error", "message" => "First name and email are mandatory fields."]);
        exit;
    }

    $checkStmt = $mysqli->prepare("SELECT id FROM Users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "An account with this email already exists."]);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $defaultStatus = (strtolower($role) === 'admin') ? 'Verified' : 'Pending';

    $insertUserStmt = $mysqli->prepare("INSERT INTO Users (First_name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    $insertUserStmt->bind_param("sssss", $firstName, $email, $hashedPassword, $role, $defaultStatus);

    if ($insertUserStmt->execute()) {
        $actionDescription = "Created New Account profile ($role)";
        $targetDetails = "Name: $firstName , Email: $email";

        $logStmt = $mysqli->prepare("INSERT INTO audit_logs (admin_id, action_string, target_details) VALUES (?, ?, ?)");
        $logStmt->bind_param("iss", $adminId, $actionDescription, $targetDetails);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["status" => "success", "message" => "User profile created and action logged successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database execution runtime failure: " . $insertUserStmt->error]);
    }
    
    $insertUserStmt->close();
    break;

case 'forgotPassword':
    $data = json_decode(file_get_contents("php://input"), true);
    $email = isset($data['email']) ? trim($data['email']) : '';

    if (empty($email)) {
        echo json_encode(["status" => "error", "message" => "Email address is required."]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT id, First_name FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(["status" => "success", "message" => "If the account exists, a verification token has been dispatched."]);
        $stmt->close();
        exit;
    }
    
    $user = $result->fetch_assoc();
    $userId = $user['id'];
    $firstName = $user['First_name'];
    $stmt->close();

    $pool = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $token = '';
    $poolLength = strlen($pool);
    for ($i = 0; $i < 6; $i++) {
        $token .= $pool[random_int(0, $poolLength - 1)];
    }

    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $updateStmt = $mysqli->prepare("UPDATE Users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
    $updateStmt->bind_param("ssi", $token, $expires, $userId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();

        $logStmt = $mysqli->prepare("INSERT INTO password_resets (user_id, email, token, status, expires_at) VALUES (?, ?, ?, 'Active', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $logStmt->bind_param("iss", $userId, $email, $token); 
       
        $logStmt->execute();
        $logStmt->close();
        
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.gmail.com';                       
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'wawerumwangidan@gmail.com';         
            $mail->Password   = 'awwgslnijnaaaqbg';           
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->Port       = 587; 

            $mail->setFrom('wawerumwangidan@gmail.com', 'SLCMC Portal Security');
            $mail->addAddress($email, $firstName);                      

            $mail->isHTML(true);                                        
            $mail->Subject = 'SLCMC Portal Account Password Recovery Code';
            
            $mail->Body    = "
                <div style='font-family: sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 500px;'>
                    <h2 style='color: #0891b2;'>Password Reset Request</h2>
                    <p>Hello " . htmlspecialchars($firstName) . ",</p>
                    <p>We received a request to change the account password linked to this email address on the Sister Leonella Consolata Medical College Alumni Portal.</p>
                    <p>Copy and enter the 6-digit secure recovery code below into the verification panel form:</p>
                    <div style='background-color: #f1f5f9; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 24px; font-weight: bold; text-align: center; color: #0f172a; margin: 20px 0; letter-spacing: 4px;'>
                        " . $token . "
                    </div>
                    <p style='font-size: 13px; color: #64748b;'>This code is confidential and expires in 15 Minutes. If you did not trigger this action, please ignore this email.</p>
                    <hr style='border: 0; border-top: 1px solid #e2e8f0; margin-top: 25px;'>
                   
                </div>
            ";
            
            $mail->AltBody = "Hello " . $firstName . ",\n\nYour account recovery verification code is: " . $token . "\n\nThis code will expire in 1 hour.";

            $mail->send();
            
            echo json_encode([
                "status" => "success", 
                "message" => "A 6-digit verification code has been successfully sent to your email address."
            ]);

        } catch (Exception $e) {
            error_log("Mail delivery structural failure: " . $mail->ErrorInfo);
            echo json_encode([
                "status" => "error", 
                "message" => "Unable to transmit code via email subsystem interface."
            ]);
        }

    } else {
        echo json_encode(["status" => "error", "message" => "Database failure generating token."]);
        $updateStmt->close();
    }
    break;

case 'resetPassword':
    $data = json_decode(file_get_contents("php://input"), true);
    $token = isset($data['token']) ? strtoupper(trim($data['token'])) : '';
    $newPassword = isset($data['new_password']) ? trim($data['new_password']) : '';

    if (empty($token) || empty($newPassword)) {
        echo json_encode(["status" => "error", "message" => "Invalid token or empty password payload."]);
        exit;
    }

    $stmt = $mysqli->prepare("SELECT user_id FROM password_resets WHERE token = ? AND status = 'Active' AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "The reset code is invalid or has expired."]);
        $stmt->close();
        exit;
    }

    $user = $result->fetch_assoc();
    $userId = $user['user_id']; 
    $stmt->close();

    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    $updateStmt = $mysqli->prepare("UPDATE Users SET password = ?, requires_password_change = 0 WHERE id = ?");
    $updateStmt->bind_param("si", $hashedPassword, $userId);

    if ($updateStmt->execute()) {
        $updateStmt->close();

        $updateLogStmt = $mysqli->prepare("UPDATE password_resets SET status = 'Used' WHERE token = ? AND user_id = ?");
        $updateLogStmt->bind_param("si", $token, $userId);
        $updateLogStmt->execute();
        $updateLogStmt->close();

        $actionDescription = "Recovered Account via Password Reset Template Form";
        $logStmt = $mysqli->prepare("INSERT INTO audit_logs (admin_id, action_string, target_details) VALUES (?, ?, ?)");
        $logStmt->bind_param("iss", $userId, $actionDescription, $actionDescription);
        $logStmt->execute();
        $logStmt->close();

        echo json_encode(["status" => "success", "message" => "Your new password has been set successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Runtime failure updating credentials."]);
        $updateStmt->close();
    }
    break;

case 'createAnnouncement':
    $inputData = json_decode(file_get_contents("php://input"), true);
    $title   = isset($inputData['title']) ? trim($inputData['title']) : '';
    $content = isset($inputData['content']) ? trim($inputData['content']) : '';
    
    if (empty($title) || empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'Both Title and Content are required fields.']);
        exit;
    }

    $audience = "All"; 
    $created_by = 1; 

    $query = "INSERT INTO announcements (title, content, audience, created_by) VALUES (?, ?, ?, ?)";
    
    if ($stmt = $mysqli->prepare($query)) {
        $stmt->bind_param("sssi", $title, $content, $audience, $created_by);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Announcement published successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database execution failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database initialization failed: ' . $mysqli->error]);
    }
    break;

case 'getAnnouncements':
    $query = "SELECT id, title, content, audience, created_by, created_at FROM announcements ORDER BY created_at DESC";
    
    if ($result = $mysqli->query($query)) {
        $announcements = [];
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $announcements]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve announcements: ' . $mysqli->error]);
    }
    break;

case 'getAnnouncementCount':
    $query = "SELECT COUNT(*) as total FROM announcements";
    if ($result = $mysqli->query($query)) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'count' => (int)$row['total']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve announcement count: ' . $mysqli->error]);
    }
    break;

case 'getAuditLogs':
    $query = "SELECT a.id, a.action_string, a.target_details, a.performed_at, u.First_name
              FROM audit_logs a
              JOIN Users u ON a.admin_id = u.id
              ORDER BY a.performed_at DESC LIMIT 100";

    $result = $mysqli->query($query);
    if ($result) {
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to retrieve logs: " . $mysqli->error]);
    }
    break;
    case 'saveMediaAsset':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(["status" => "error", "message" => "Invalid request methodology format"]);
            break;
        }

        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $alt_text = isset($_POST['alt_text']) ? trim($_POST['alt_text']) : '';

        if (empty($title) || empty($alt_text) || !isset($_FILES['media_file'])) {
            echo json_encode(["status" => "error", "message" => "Required descriptive field metrics missing"]);
            break;
        }

        // Processing Directory Target Management
        $target_dir = "uploads/media/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES["media_file"]["name"], PATHINFO_EXTENSION));
        $generated_name = time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $generated_name;

        // Verify image structure before proceeding
        $check = getimagesize($_FILES["media_file"]["tmp_name"]);
        if ($check === false) {
            echo json_encode(["status" => "error", "message" => "Selected binary payload target is not a valid image format asset"]);
            break;
        }

        if (move_uploaded_file($_FILES["media_file"]["tmp_name"], $target_file)) {
            // Write down file configurations to SQL Engine Matrix
            $stmt = $mysqli->prepare("INSERT INTO media (title, alt_text, image_path) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $alt_text, $target_file);
            
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Asset log cataloged successfully"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to write structural records to table logs"]);
            }
            $stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "File writing IO fault occurred within server space"]);
        }
        break;

        case 'getMediaAssets':
        $query = "SELECT id, title, alt_text, image_path FROM media ORDER BY id DESC";
        $result = $mysqli->query($query);
        
        $collection = [];
        while ($row = $result->fetch_assoc()) {
            $collection[] = $row;
        }
        
        echo json_encode(["status" => "success", "data" => $collection]);
        break;

   

case 'logout':
    session_unset();
    session_destroy();
    echo json_encode(["status" => "success", "message" => "Logged out"]);
    break;



   
            
 // ========================= 
  // registration logic    //
//  registration logic   //
  // ========================= 

               case 'register':
    $First_name       = $input['First_name'] ?? null;
    $Dob              = $input['Dob'] ?? null;
    $Gender           = $input['Gender'] ?? null;
    $Email            = $input['Email'] ?? null;
    $Phone_number     = $input['Phone_number'] ?? null;
    $id_no            = $input['id_no'] ?? null;
    $Graduation_class = $input['Graduation_class'] ?? null;
    $Password         = isset($input['Password']) ? password_hash($input['Password'], PASSWORD_BCRYPT) : null;

    // Simple validation safeguard
    if (empty($First_name) || empty($Email) || empty($Password)) {
        echo json_encode(["status" => "error", "message" => "Missing required registration fields."]);
        $mysqli->close();
        exit();
    }

    // 1. Insert the user into the database
    $sql = "INSERT INTO Users (First_name, Dob, Gender, Email, Phone_number, id_no, Password, Graduation_class) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ssssssss", $First_name, $Dob, $Gender, $Email, $Phone_number, $id_no, $Password, $Graduation_class);

        if ($stmt->execute()) {
            // Get the newly created user's ID
            $user_id = $mysqli->insert_id; 

            // 2. Generate a secure 6-digit OTP
            $otp_code = (string)random_int(100000, 999999); 
            
            // Set expiration time (10 minutes from now)
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // 3. Save the OTP to the email_verifications table
            $otp_sql = "INSERT INTO email_verifications (user_id, email, otp_code, expires_at) 
            VALUES (?, ?, ?, NOW() + INTERVAL 10 MINUTE)";
            $otp_stmt = $mysqli->prepare($otp_sql);

            if ($otp_stmt) {
                $otp_stmt->bind_param("iss", $user_id, $Email, $otp_code,);
                
                if ($otp_stmt->execute()) {
                    
                    // 4. Send the verification email via PHPMailer
                    // Make sure you have included/required PHPMailer classes at the top of your script!
                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();                                            
                        $mail->Host       = 'smtp.gmail.com';                        
                        $mail->SMTPAuth   = true;                                    
                        $mail->Username   = 'wawerumwangidan@gmail.com';          
                        $mail->Password   = 'awwgslnijnaaaqbg';            
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;          
                        $mail->Port       = 587; 

                        $mail->setFrom('wawerumwangidan@gmail.com', 'SLCMC Portal Security');
                        $mail->addAddress($Email, $First_name);                        

                        $mail->isHTML(true);                                        
                        $mail->Subject = 'SLCMC Portal Account Verification Code';
                        
                        $mail->Body    = "
                            <div style='font-family: sans-serif; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; max-width: 500px;'>
                                <h2 style='color: #0891b2;'>Welcome to SLCMC Alumni Portal</h2>
                                <p>Hello " . htmlspecialchars($First_name) . ",</p>
                                <p>Thank you for registering on the Sister Leonella Consolata Medical College Alumni Portal. To complete your registration setup, please verify your email address.</p>
                                <p>Copy and enter the 6-digit secure activation code below into the registration panel verification screen:</p>
                                <div style='background-color: #f1f5f9; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 24px; font-weight: bold; text-align: center; color: #0f172a; margin: 20px 0; letter-spacing: 4px;'>
                                    " . $otp_code . "
                                </div>
                                <p style='font-size: 13px; color : #64748b;'>This code is confidential and expires in 10 Minutes. If you did not create this account, please disregard this email.</p>
                                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin-top: 25px;'>
                            </div>
                        ";
                        
                        $mail->AltBody = "Hello " . $First_name . ",\n\nWelcome to SLCMC Portal! Your account verification code is: " . $otp_code . "\n\nThis code will expire in 10 minutes.";

                        $mail->send();
                        
                        echo json_encode([
                            "status" => "success", 
                            "message" => "Registration successful! A 6-digit activation code has been sent to your email address.",
                            "user_id" => $user_id
                        ]);

                    } catch (Exception $e) {
                        error_log("Mail delivery structural failure: " . $mail->ErrorInfo);
                        echo json_encode([
                            "status" => "error", 
                            "message" => "Registration saved, but unable to transmit verification code via email subsystem interface."
                        ]);
                    }

                } else {
                    echo json_encode(["status" => "error", "message" => "User created, but failed to generate verification code."]);
                }
                $otp_stmt->close();
            } else {
                echo json_encode(["status" => "error", "message" => "OTP statement preparation failed: " . $mysqli->error]);
            }

        } else {
            echo json_encode(["status" => "error", "message" => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => $mysqli->error]);
    }
    break;

    case 'verify_otp':
    $user_id  = $input['user_id'] ?? null;
    $otp_code = $input['otp_code'] ?? null; 

    if (empty($user_id) || empty($otp_code)) {
        echo json_encode(["status" => "error", "message" => "User ID and Verification Code are required."]);
        exit();
    }

    // 1. Fetch the status and expiration time of this specific token entry
    $sql = "SELECT status, expires_at FROM email_verifications 
            WHERE user_id = ? AND otp_code = ? 
            ORDER BY created_at DESC LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("is", $user_id, $otp_code);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            // Check if it's already used or manually expired
            if ($result['status'] !== 'Active') {
                echo json_encode(["status" => "error", "message" => "This code has already been used or invalidated."]);
                exit();
            }

            // Check against your database's current time zone clock
            $time_check = $mysqli->query("SELECT NOW() as current_db_time")->fetch_assoc();
            if ($result['expires_at'] <= $time_check['current_db_time']) {
                echo json_encode(["status" => "error", "message" => "This verification code has expired. Please request a new one."]);
                exit();
            }

            // 2. Success! Mark the OTP as used
            $update_otp = "UPDATE email_verifications SET status = 'Used' WHERE user_id = ? AND otp_code = ?";
            $u_stmt = $mysqli->prepare($update_otp);
            $u_stmt->bind_param("is", $user_id, $otp_code);
            $u_stmt->execute();
            $u_stmt->close();

            // 3. Flip your user table status to verified
           $update_user = "UPDATE Users SET verified_at = NOW() WHERE id = ?";
            $usr_stmt = $mysqli->prepare($update_user);
            $usr_stmt->bind_param("i", $user_id);
            $usr_stmt->execute();
            $usr_stmt->close();

            echo json_encode(["status" => "success", "message" => "Account successfully verified! You can now log in."]);
        } else {
            // No record matched this combination
            echo json_encode(["status" => "error", "message" => "Incorrect verification code."]);
        }
        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Database check failed."]);
    }
    break;
 case 'resend_otp':
        $user_id = $input['user_id'] ?? null;

        if (empty($user_id)) {
            echo json_encode(["status" => "error", "message" => "User reference parameter missing."]);
            exit();
        }

        $res = $mysqli->prepare("SELECT First_name, Email FROM Users WHERE id = ?");
        $res->bind_param("i", $user_id);
        $res->execute();
        $result = $res->get_result()->fetch_assoc();

        if ($result) {
            // Expire any existing active codes for this user
            $expire_old = $mysqli->prepare("UPDATE email_verifications SET status = 'Expired' WHERE user_id = ? AND status = 'Active'");
            $expire_old->bind_param("i", $user_id);
            $expire_old->execute();

            $new_otp = (string)random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $ins = $mysqli->prepare("INSERT INTO email_verifications (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
            $ins->bind_param("isss", $user_id, $result['Email'], $new_otp, $expires);
            
            if ($ins->execute()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'wawerumwangidan@gmail.com';
                    $mail->Password   = 'awwgslnijnaaaqbg';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('wawerumwangidan@gmail.com', 'SLCMC Portal Security');
                    $mail->addAddress($result['Email'], $result['First_name']);
                    $mail->isHTML(true);
                    $mail->Subject = 'New SLCMC Portal Account Verification Code';
                    $mail->Body    = "<div style='font-family: sans-serif; padding: 20px;'>Your new code is: <b>" . $new_otp . "</b></div>";
                    $mail->send();

                    echo json_encode(["status" => "success", "message" => "New token transmitted successfully."]);
                } catch (Exception $e) {
                    echo json_encode(["status" => "error", "message" => "Failed dispatching new verification mail."]);
                }
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Account reference identifier not found."]);
        }
        break;

   case 'fetch_profile':
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid or missing User ID."]);
            break;
        }

        $data = [];
        $tables = [
            'user'       => "SELECT id, First_name, Email, Phone_number, Dob, Gender, Graduation_class, photo FROM Users WHERE id = ?",
            'academic'   => "SELECT * FROM AcademicRecord WHERE user_id = ?",
            'current'    => "SELECT * FROM CurrentEmployment WHERE user_id = ?",
            'education'  => "SELECT * FROM FurtherEducation WHERE user_id = ?",
            'membership' => "SELECT * FROM ProfessionalMembership WHERE user_id = ?",
            'social'     => "SELECT * FROM SocialContact WHERE user_id = ?"
        ];

        // Execute single-row associative fetches safely
        foreach ($tables as $key => $sql) {
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $data[$key] = $result->fetch_assoc() ?: null;
                $stmt->close();
            } else {
                $data[$key] = null;
            }
        }

        // Fetch arrays/lists (Employment History)
        $data['history'] = [];
        $history_sql = "SELECT * FROM EmploymentHistory WHERE user_id = ?";
        $stmt = $mysqli->prepare($history_sql);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $data['history'] = $result->fetch_all(MYSQLI_ASSOC);
            }
            $stmt->close();
        }

        // =========================================================================
        // ON-THE-FLY BASE64 IMAGE CONVERSION (Bypasses Frontend CORS Restrictions)
        // =========================================================================
        $data['user']['photo_base64'] = null; // Default to null if no image exists

// FIX APPLIED HERE: Added ?? '' inside trim() to safely handle null values
if ($data['user'] && !empty(trim($data['user']['photo'] ?? ''))) {
    $photo_filename = trim($data['user']['photo']); 
    
    // Build absolute file path to your server's upload storage directory.
    $photo_path = __DIR__ . '/' . $photo_filename; 
    
    if (file_exists($photo_path) && is_file($photo_path)) {
        $file_type = strtolower(pathinfo($photo_path, PATHINFO_EXTENSION));
        
        // Read binary file stream directly from local storage disk
        $file_data = @file_get_contents($photo_path);
        
        if ($file_data !== false) {
            // Normalize standard extension string headers for the browser layout engine
            if ($file_type === 'jpg' || $file_type === 'jpeg') {
                $mime_type = 'image/jpeg';
            } elseif ($file_type === 'png') {
                $mime_type = 'image/png';
            } else {
                $mime_type = 'image/' . $file_type;
            }
            
            // Construct full clean Base64 Data URI string template
            $data['user']['photo_base64'] = 'data:' . $mime_type . ';base64,' . base64_encode($file_data);
        }
    }
}

        // Return the compiled response back to Angular
        echo json_encode([
            "status"     => "success",
            "user"       => $data['user'],
            "academic"   => $data['academic'],
            "current"    => $data['current'],
            "history"    => $data['history'],
            "education"  => $data['education'],
            "membership" => $data['membership'],
            "social"     => $data['social']
        ]);
        break;
    // ========================= 
  // login   logic   //
//  logig logic  //
  // ========================= 

         case 'login':
    // Read from the globally parsed $input variable
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode([
            "status" => "error",
            "message" => "Email and password are required"
        ]);
        exit;
    }

    $sql = "SELECT id, First_name, Password, status, role FROM Users WHERE Email = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['Password'])) {
            
            if ($user['status'] === 'Revoked') {
                echo json_encode([
                    "status" => "error",
                    "message" => "Your account has been deactivated. No proof you are SLCMC alumni."
                ]);
                $stmt->close();
                $mysqli->close();
                exit;
            }

            // Update last seen
            $updateSql = "UPDATE Users SET last_seen = NOW() WHERE id = ?";
            $updateStmt = $mysqli->prepare($updateSql);
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();

            // CRUCIAL MATCH FIX: Use 'logged_in_user_id' to match your dashboard check!
            $_SESSION['logged_in_user_id'] = $user['id']; 
            $_SESSION['user_id'] = $user['id']; // Keeping this too just in case other files use it
            $_SESSION['first_name'] = $user['First_name'];
            $_SESSION['role'] = $user['role']; 
            $_SESSION['is_admin'] = ($user['role'] === 'admin'); // Sets true if they are an admin

            echo json_encode([
                "account_status" => $user['status'],
                "status" => "success",
                "message" => "Login successful",
                "user_id" => $user['id'],
                "first_name" => $user['First_name'],
                "role" => $user['role']
            ]);

        } else {
            echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
    }
    
    $stmt->close();
    break;

       // ========================= 
  // alumni dashboard getting details   //
    // alumni dashboard getting details   //
  // ========================= 
  case 'getdetailsAlumni':
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['logged_in_user_id'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized."]);
        exit();
    }
        session_write_close();

    $request_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $logged_in_id = intval($_SESSION['logged_in_user_id']);
    $is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true; // Or however you track admins

    // Authorization Rule: If you're not an admin, you can ONLY request your own ID
    if (!$is_admin && $request_id !== $logged_in_id) {
        http_response_code(403); // Forbidden
        echo json_encode(["status" => "error", "message" => "Forbidden: You cannot access this profile."]);
        $mysqli->close();
        exit();
    }

    $user_id = $request_id;
    
    // ... Proceed with your database queries ...
// 5. Securely fetch data using Prepared Statements
            $data = [];
            $tables = [
    'user'       => "SELECT id, First_name, Email, Phone_number, Dob, Gender, Graduation_class, photo FROM Users WHERE id = ?",
    'academic'   => "SELECT * FROM AcademicRecord WHERE user_id = ?",
    'current'    => "SELECT * FROM CurrentEmployment WHERE user_id = ?",
    'education'  => "SELECT * FROM FurtherEducation WHERE user_id = ?",
    'membership' => "SELECT * FROM ProfessionalMembership WHERE user_id = ?",
    'social'     => "SELECT * FROM SocialContact WHERE user_id = ?"
];

// Execute single-row associative fetches safely
foreach ($tables as $key => $sql) {
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data[$key] = $result->fetch_assoc() ?: null; // Fallback to null if no row exists
        $stmt->close();
    } else {
        $data[$key] = null;
    }
}

// 6. Special case: Fetch arrays/lists (Employment History)
$data['history'] = [];
$history_sql = "SELECT * FROM EmploymentHistory WHERE user_id = ?";
$stmt = $mysqli->prepare($history_sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $data['history'] = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// 7. Return the compiled, clean JSON response back to Angular
echo json_encode([
    "status"     => "success",
    "user"       => $data['user'],
    "academic"   => $data['academic'],
    "current"    => $data['current'],
    "history"    => $data['history'],
    "education"  => $data['education'],
    "membership" => $data['membership'],
    "social"     => $data['social']
]);
        break;

      // ========================= 
  // privacySettingsAlumni   //
    //privacySettingsAlumni   //
  // ========================= 

        case 'privacySettingsAlumni':
            
// 3. Parse incoming JSON data
$data = json_decode(file_get_contents("php://input"), true);

// Extract user_id safely
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : null;

// Validation: Stop execution if user_id is missing
if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit;
}

// Safely extract privacy toggles using columns from your actual SQL schema
$show_email       = isset($data['show_email']) && $data['show_email'] ? 1 : 0;
$show_phone       = isset($data['show_phone']) && $data['show_phone'] ? 1 : 0;
$show_employment  = isset($data['show_employment']) && $data['show_employment'] ? 1 : 0;
$show_education   = isset($data['show_education']) && $data['show_education'] ? 1 : 0;
$show_membership  = isset($data['show_membership']) && $data['show_membership'] ? 1 : 0;
$show_social      = isset($data['show_social']) && $data['show_social'] ? 1 : 0;
$show_contact     = isset($data['show_contact']) && $data['show_contact'] ? 1 : 0;

// 4. Check if settings already exist for this user
$check = $mysqli->prepare("SELECT id FROM PrivacySettings WHERE user_id = ?");
$check->bind_param("i", $user_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    // Record exists -> UPDATE
    $stmt = $mysqli->prepare("
        UPDATE PrivacySettings SET
            show_email = ?,
            show_phone = ?,
            show_employment = ?,
            show_education = ?,
            show_membership = ?,
            show_social = ?,
            show_contact = ?
        WHERE user_id = ?
    ");

    $stmt->bind_param(
        "iiiiiiii",
        $show_email,
        $show_phone,
        $show_employment,
        $show_education,
        $show_membership,
        $show_social,
        $show_contact,
        $user_id
    );

} else {
    // Record does not exist -> INSERT
    $stmt = $mysqli->prepare("
        INSERT INTO PrivacySettings (
            user_id,
            show_email,
            show_phone,
            show_employment,
            show_education,
            show_membership,
            show_social,
            show_contact
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iiiiiiii",
        $user_id,
        $show_email,
        $show_phone,
        $show_employment,
        $show_education,
        $show_membership,
        $show_social,
        $show_contact
    );
}

// 5. Execute and send JSON response
if ($stmt && $stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => $stmt ? $stmt->error : $conn->error
    ]);
}
            break;


case 'sendMessage':
        $sender_id = intval($_POST['sender_id'] ?? 0);
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $message_text = trim($_POST['message_text'] ?? '');
        
        $file_path = null;
        $message_type = 'text';

        // Check if a file was attached to the request payload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['attachment']['tmp_name'];
            $file_name = time() . '_' . basename($_FILES['attachment']['name']);
            
            // Create an attachments storage directory if it doesn't exist yet
            $upload_dir = 'uploads/attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($file_tmp, $target_file)) {
                $file_path = $target_file;
                
                // Determine file classification type
                $file_extension = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $message_type = 'image';
                } else {
                    $message_type = 'document'; // Covers pdf, docx, zip, etc.
                }
            }
        }

        if (empty($message_text) && empty($file_path)) {
            echo json_encode(["status" => "error", "message" => "Cannot transmit an empty chat message."]);
            exit;
        }

        $stmt = $mysqli->prepare("INSERT INTO alumni_messages (sender_id, receiver_id, message_text, file_path, message_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $sender_id, $receiver_id, $message_text, $file_path, $message_type);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Message transmitted successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to save data record."]);
        }
        $stmt->close();
        break;

  case 'getChatHistory':
        $user_id = intval($_GET['user_id']);
        $recipient_id = intval($_GET['recipient_id']);
        //  Check if the frontend already has messages up to a certain time
        $last_timestamp = isset($_GET['last_timestamp']) ? $_GET['last_timestamp'] : null;

        if ($last_timestamp) {
            // Fetch ONLY new messages
            $query = "SELECT id, sender_id, receiver_id, message_text, file_path, message_type, is_read, created_at 
                      FROM alumni_messages 
                      WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                        AND created_at > ?
                      ORDER BY created_at ASC";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("iiiis", $user_id, $recipient_id, $recipient_id, $user_id, $last_timestamp);
        } else {
            // Normal fallback: Fetch everything if cache is empty
            $query = "SELECT id, sender_id, receiver_id, message_text, file_path, message_type, is_read, created_at 
                      FROM alumni_messages 
                      WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                      ORDER BY created_at ASC";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("iiii", $user_id, $recipient_id, $recipient_id, $user_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            // Extract the filename if a file path exists
            if (!empty($row['file_path'])) {
                $row['file_name'] = basename($row['file_path']); 
                
                // Optional: Strip out the time() prefix (e.g., "1719651234_") for a cleaner look
                $row['file_name'] = preg_replace('/^\d+_\n?/', '', $row['file_name']);
            } else {
                $row['file_name'] = null;
            }
            
            $messages[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $messages]);
        $stmt->close();
        break;
 case 'getAlumniDirectory':
        $current_user = intval($_GET['exclude_id']);
        
        $query = "SELECT DISTINCT u.id, u.First_name, u.photo, u.last_seen,
                    (SELECT COUNT(*) 
                     FROM alumni_messages m2 
                     WHERE m2.sender_id = u.id 
                       AND m2.receiver_id = ? 
                       AND m2.is_read = 0) as unread_count,
                    
                    (SELECT 
                        CASE 
                            WHEN m3.message_text IS NOT NULL AND TRIM(m3.message_text) != '' THEN m3.message_text
                            WHEN m3.message_type = 'image' THEN '📷 Photo'
                            WHEN m3.message_type = 'document' THEN '📄 Document'
                            ELSE 'No messages yet'
                        END
                     FROM alumni_messages m3 
                     WHERE (m3.sender_id = u.id AND m3.receiver_id = ?) 
                        OR (m3.sender_id = ? AND m3.receiver_id = u.id)
                     ORDER BY m3.created_at DESC 
                     LIMIT 1) as last_message,

                    (SELECT m3.sender_id 
                     FROM alumni_messages m3 
                     WHERE (m3.sender_id = u.id AND m3.receiver_id = ?) 
                        OR (m3.sender_id = ? AND m3.receiver_id = u.id)
                     ORDER BY m3.created_at DESC 
                     LIMIT 1) as last_sender_id,

                    (SELECT m3.is_read 
                     FROM alumni_messages m3 
                     WHERE (m3.sender_id = u.id AND m3.receiver_id = ?) 
                        OR (m3.sender_id = ? AND m3.receiver_id = u.id)
                     ORDER BY m3.created_at DESC 
                     LIMIT 1) as last_is_read,
                     
                    /* 🌟 NEW: Pull the raw timestamp of the absolute latest message to use for ordering */
                    (SELECT m3.created_at 
                     FROM alumni_messages m3 
                     WHERE (m3.sender_id = u.id AND m3.receiver_id = ?) 
                        OR (m3.sender_id = ? AND m3.receiver_id = u.id)
                     ORDER BY m3.created_at DESC 
                     LIMIT 1) as latest_message_time

                  FROM Users u
                  INNER JOIN alumni_messages m 
                  ON (u.id = m.sender_id AND m.receiver_id = ?) 
                  OR (u.id = m.receiver_id AND m.sender_id = ?)
                  WHERE u.id != ?
                  
                  /* 🌟 NEW ORDER BY clause: Orders by timestamp descending (newest first). 
                     '1970-01-01' acts as a fallback so empty chats fall to the very bottom. */
                  ORDER BY COALESCE(latest_message_time, '1970-01-01 00:00:00') DESC";
                  
        $stmt = $mysqli->prepare($query);
        
        // 🌟 UPDATED BIND STRINGS: 12 total parameters now because we added the timestamp subquery!
        $stmt->bind_param(
            "iiiiiiiiiiii", 
            $current_user, // m2.receiver_id
            $current_user, $current_user, // m3 text
            $current_user, $current_user, // m3 last_sender_id
            $current_user, $current_user, // m3 last_is_read
            $current_user, $current_user, // 🌟 NEW: m3 latest_message_time parameters
            $current_user, $current_user, // m join parameters
            $current_user  // u.id exclusion
        );
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $directory = [];
        while ($row = $result->fetch_assoc()) {
            $row['unread_count'] = intval($row['unread_count']);
            $row['last_sender_id'] = $row['last_sender_id'] !== null ? intval($row['last_sender_id']) : null;
            $row['last_is_read'] = $row['last_is_read'] !== null ? intval($row['last_is_read']) : 0;
            $directory[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $directory]);
        $stmt->close();
        break;


        case 'getTotalUnreadCount':
    $current_user = intval($_GET['user_id'] ?? 0);

    $sql = "SELECT COUNT(*) as total_unread 
            FROM alumni_messages 
            WHERE receiver_id = ? AND is_read = 0";
            
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $current_user);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "total_unread" => intval($result['total_unread'] ?? 0)
    ]);
    $stmt->close();
    exit;


        case 'markAsRead':
        $data = json_decode(file_get_contents("php://input"), true);
        $user_id = intval($data['user_id']);       // You (the receiver)
        $sender_id = intval($data['sender_id']);   // The classmate who texted you

        if (!$user_id || !$sender_id) {
            echo json_encode(["status" => "error", "message" => "Missing IDs"]);
            exit;
        }

        // Flip all unread messages from this sender to read
        $stmt = $mysqli->prepare("UPDATE alumni_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->bind_param("ii", $sender_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error"]);
        }
        $stmt->close();
        break;

        case 'deleteMessages':
        $data = json_decode(file_get_contents("php://input"), true);
        $user_id = intval($data['user_id'] ?? 0);
        $message_ids = $data['message_ids'] ?? []; // Array of IDs to delete

        if (!$user_id || empty($message_ids)) {
            echo json_encode(["status" => "error", "message" => "Invalid payload parameters."]);
            exit;
        }

        // Convert array to comma-separated sanitized integers
        $ids_string = implode(',', array_map('intval', $message_ids));
        
        // Ensure a user can only delete messages they sent or received
        $query = "DELETE FROM alumni_messages WHERE id IN ($ids_string) AND (sender_id = ? OR receiver_id = ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ii", $user_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Messages deleted."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Deletion failed."]);
        }
        $stmt->close();
        break;

    case 'clearChat':
        $data = json_decode(file_get_contents("php://input"), true);
        $user_id = intval($data['user_id'] ?? 0);
        $recipient_id = intval($data['recipient_id'] ?? 0);

        if (!$user_id || !$recipient_id) {
            echo json_encode(["status" => "error", "message" => "Missing credentials."]);
            exit;
        }

        // Wipe out the entire conversation between these two users
        $query = "DELETE FROM alumni_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("iiii", $user_id, $recipient_id, $recipient_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Conversation cleared entirely."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to clear log history."]);
        }
        $stmt->close();
        break;



 case 'getAlumniinfo':
    
$exclude_id = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : 0;

// 📄 Get pagination query parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 8; // Default 8 items per page
if ($page < 1) $page = 1;
if ($limit < 1) $limit = 8;
$offset = ($page - 1) * $limit;

//  1. Count total records first
$count_sql = "SELECT COUNT(*) as total FROM Users WHERE id != ?";
$count_stmt = $mysqli->prepare($count_sql);
$total_records = 0;

if ($count_stmt) {
    $count_stmt->bind_param("i", $exclude_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result()->fetch_assoc();
    $total_records = intval($count_result['total']);
    $count_stmt->close();
}

// 2. Fetch paginated data
$sql = "
SELECT 
    u.id,
    u.First_name,
    u.photo,
    CASE WHEN IFNULL(p.show_education, 0) = 1 THEN ar.programme_name ELSE NULL END AS Programme,
    CASE WHEN IFNULL(p.show_education, 0) = 1 THEN ar.cohort_name ELSE NULL END AS Cohort,
    CASE WHEN IFNULL(p.show_education, 0) = 1 THEN ar.graduation_year ELSE NULL END AS GraduationYear,
    CASE WHEN IFNULL(p.show_education, 0) = 1 THEN ar.reg_number ELSE NULL END AS RegNumber,
    CASE WHEN IFNULL(p.show_education, 0) = 1 THEN ar.year_joined ELSE NULL END AS YearJoined,
    CASE WHEN IFNULL(p.show_email, 0) = 1 THEN u.Email ELSE NULL END AS Email,
    CASE WHEN IFNULL(p.show_phone, 0) = 1 THEN u.Phone_number ELSE NULL END AS Phone_number,
    CASE WHEN IFNULL(p.show_employment, 0) = 1 THEN ce.employer_name ELSE NULL END AS Employer,
    CASE WHEN IFNULL(p.show_employment, 0) = 1 THEN ce.role ELSE NULL END AS Role,
    CASE WHEN IFNULL(p.show_social, 0) = 1 THEN sc.linkedin ELSE NULL END AS LinkedIn,
    CASE WHEN IFNULL(p.show_membership, 0) = 1 THEN pm.organization ELSE NULL END AS MembershipOrg,
    CASE WHEN IFNULL(p.show_membership, 0) = 1 THEN pm.membership_id ELSE NULL END AS MembershipID,
    CASE WHEN IFNULL(p.show_contact, 0) = 1 THEN sc.physical_address ELSE NULL END AS Address,
    sc.home_county
FROM Users u
LEFT JOIN PrivacySettings p ON u.id = p.user_id
LEFT JOIN AcademicRecord ar ON u.id = ar.user_id
LEFT JOIN CurrentEmployment ce ON u.id = ce.user_id
LEFT JOIN ProfessionalMembership pm ON u.id = pm.user_id
LEFT JOIN SocialContact sc ON u.id = sc.user_id
WHERE u.id != ?
ORDER BY u.First_name ASC
LIMIT ? OFFSET ?;
";

$stmt = $mysqli->prepare($sql);
if ($stmt) {
    // Bind parameters: exclude_id (i), limit (i), offset (i)
    $stmt->bind_param("iii", $exclude_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alumni = [];
    while ($row = $result->fetch_assoc()) {
        $alumni[] = $row;
    }
    
    // Return payload alongside meta information
    echo json_encode([
        "status" => "success", 
        "data" => $alumni,
        "meta" => [
            "total" => $total_records,
            "page" => $page,
            "limit" => $limit,
            "totalPages" => ceil($total_records / $limit)
        ]
    ]);
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "SQL engine statement error"]);
}
    break;
     // ========================= 
  //alumni information post methods   //
//  alumni information post methods   //
  // ========================= 


     case "addPersonalDetails":
    // Syncing case-sensitivity to match the explicit mapping we put in your Angular submitStep1()
    $First_name       = $input['first_name'] ?? $input['First_name'] ?? '';
    $Dob              = $input['dob'] ?? $input['Dob'] ?? '';
    $Gender           = $input['gender'] ?? $input['Gender'] ?? '';
    $Email            = $input['Email'] ?? ''; 
    $Phone_number     = $input['phone_number'] ?? $input['Phone_number'] ?? '';
    $Graduation_class = $input['graduation_class'] ?? $input['Graduation_class'] ?? '';
    $password         = $input['Password'] ?? $input['password'] ?? '';
    $user_id          = !empty($input['user_id']) ? intval($input['user_id']) : null;

    //  THE FIX: Safely parse id_no to ensure blank submissions become a real database NULL
    $id_no = isset($input['id_no']) ? trim($input['id_no']) : '';
    if ($id_no === '') {
        $id_no = null; 
    }

    // Handle Photo upload binary from $_FILES
    $photoPath = "";
    $uploaded = false;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $targetDir = __DIR__ . "/uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $photoPath = "uploads/" . basename($_FILES["photo"]["name"]);
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetDir . basename($_FILES["photo"]["name"]))) {
            $uploaded = true;
        }
    }

    if (!empty($user_id) && $user_id > 0) {
        // UPDATE Mode
        if ($uploaded) {
            $stmt = $mysqli->prepare("UPDATE Users SET First_name=?, Dob=?, Gender=?, Email=?, Phone_number=?, id_no=?, photo=?, Graduation_class=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $First_name, $Dob, $Gender, $Email, $Phone_number, $id_no, $photoPath, $Graduation_class, $user_id);
        } else {
            $stmt = $mysqli->prepare("UPDATE Users SET First_name=?, Dob=?, Gender=?, Email=?, Phone_number=?, id_no=?, Graduation_class=? WHERE id=?");
            $stmt->bind_param("sssssssi", $First_name, $Dob, $Gender, $Email, $Phone_number, $id_no, $Graduation_class, $user_id);
        }
        
        if ($stmt && $stmt->execute()) {
            $response = ["status" => "success", "message" => "Profile updated", "user_id" => $user_id];
        } else {
            $response = ["status" => "error", "message" => $stmt ? $stmt->error : $mysqli->error];
        }
    } else {
        // INSERT Mode
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $mysqli->prepare("INSERT INTO Users (First_name, Dob, Gender, Email, Phone_number, id_no, photo, Password, Graduation_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $First_name, $Dob, $Gender, $Email, $Phone_number, $id_no, $photoPath, $hashedPassword, $Graduation_class);
        
        if ($stmt && $stmt->execute()) {
            $response = ["status" => "success", "message" => "User created", "user_id" => $stmt->insert_id];
        } else {
            $response = ["status" => "error", "message" => $stmt ? $stmt->error : $mysqli->error];
        }
    }
    if ($stmt) $stmt->close();
    header('Content-Type: application/json');
            echo json_encode($response);
            exit();

  case "addacademicrecord":
            $user_id         = intval($input['user_id'] ?? 0);
            $programme_name  = $input['programme_name'] ?? '';
            $year_joined     = $input['year_joined'] ?? '';
            $cohort_name     = $input['cohort_name'] ?? '';
            $graduation_year = intval($input['graduation_year'] ?? 0);
            $reg_number      = $input['reg_number'] ?? '';

            // Check if record exists
            $check = $mysqli->prepare("SELECT user_id FROM AcademicRecord WHERE user_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $stmt = $mysqli->prepare("UPDATE AcademicRecord SET programme_name=?, year_joined=?, cohort_name=?, graduation_year=?, reg_number=? WHERE user_id=?");
                $stmt->bind_param("sssisi", $programme_name, $year_joined, $cohort_name, $graduation_year, $reg_number, $user_id);
            } else {
                $stmt = $mysqli->prepare("INSERT INTO AcademicRecord (user_id, programme_name, year_joined, cohort_name, graduation_year, reg_number) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssis", $user_id, $programme_name, $year_joined, $cohort_name, $graduation_year, $reg_number);
            }
            $check->close();

            if ($stmt && $stmt->execute()) {
                $response = ["status" => "success", "message" => "Academic record saved"];
            } else {
                $response = ["status" => "error", "message" => $stmt ? $stmt->error : $mysqli->error];
            }
            if ($stmt) $stmt->close();

            // ==========================================
            // FIX: Echo the response back to Angular here!
            // ==========================================
            header('Content-Type: application/json');
            echo json_encode($response);
            exit(); // Stop execution so nothing else interferes

            //current employement posting and updating info//

            //                                        //
            case "addCurrentEmployment":
            $user_id         = intval($input['user_id'] ?? 0);
            $employer_name   = $input['employer_name'] ?? '';
            $role            = $input['role'] ?? '';
            $employment_type = $input['employment_type'] ?? '';
            $work_county     = $input['work_county'] ?? '';
            $start_date      = $input['start_date'] ?? '';

            $check = $mysqli->prepare("SELECT user_id FROM CurrentEmployment WHERE user_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $stmt = $mysqli->prepare("UPDATE CurrentEmployment SET employer_name=?, role=?, employment_type=?, work_county=?, start_date=? WHERE user_id=?");
                $stmt->bind_param("sssssi", $employer_name, $role, $employment_type, $work_county, $start_date, $user_id);
            } else {
                $stmt = $mysqli->prepare("INSERT INTO CurrentEmployment (user_id, employer_name, role, employment_type, work_county, start_date) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $user_id, $employer_name, $role, $employment_type, $work_county, $start_date);
            }
            $check->close();

            if ($stmt && $stmt->execute()) {
                $response = ["status" => "success", "message" => "Current Employment saved"];
            } else {
                $response = ["status" => "error", "message" => $stmt ? $stmt->error : $mysqli->error];
            }
            if ($stmt) $stmt->close();
             header('Content-Type: application/json');
            echo json_encode($response);
            exit();

             //  Employment History                                  //
            //  Employment History                                  //
      case "addEmploymentHistory": 
            $user_id = intval($input['user_id'] ?? 0);
            
            $deleteStmt = $mysqli->prepare("DELETE FROM EmploymentHistory WHERE user_id = ?");
            $deleteStmt->bind_param("i", $user_id);
            $deleteStmt->execute();
            $deleteStmt->close();

            $success = true;
            if (!empty($input['employers']) && is_array($input['employers'])) {
                foreach ($input['employers'] as $job) {
                    $stmt = $mysqli->prepare("INSERT INTO EmploymentHistory (user_id, employer_name, role, from_date, to_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issss", $user_id, $job['employer_name'], $job['role'], $job['from_date'], $job['to_date']);
                    if (!$stmt->execute()) {
                        $success = false;
                    }
                    $stmt->close();
                }
            }

            if ($success) {
                $response = ["status" => "success", "message" => "Employment history updated"];
            } else {
                $response = ["status" => "error", "message" => "Some history entries failed to save"];
            }
             header('Content-Type: application/json');
            echo json_encode($response);
            exit();
     //  Further Education//
     // Further Education //
        case "addFurtherEducation":
            $user_id          = intval($input['user_id'] ?? 0);
            $institution      = $input['institution'] ?? '';
            $field_of_study   = $input['field_of_study'] ?? '';
            $year_completed   = intval($input['year_completed'] ?? 0);

            $check = $mysqli->prepare("SELECT user_id FROM FurtherEducation WHERE user_id = ?");
            $check->bind_param("i", $user_id);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $stmt = $mysqli->prepare("UPDATE FurtherEducation SET institution=?, field_of_study=?, year_completed=? WHERE user_id=?");
                $stmt->bind_param("ssii", $institution, $field_of_study, $year_completed, $user_id);
             } else 
             {
            $stmt = $mysqli->prepare("INSERT INTO FurtherEducation (user_id, institution, field_of_study, year_completed) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $user_id, $institution, $field_of_study, $year_completed);}$check->close();
          if ($stmt && $stmt->execute()) 
        {$response = ["status" => "success", "message" => "Further education saved"];
                 } else {
        $response = ["status" => "error", "message" => $stmt ? $stmt->error : $mysqli->error];
        }if 
        ($stmt)
         $stmt->close();
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();

        // ProfessionalMembership 
             case "addProfessionalMembership":
             $user_id = intval($input['user_id'] ?? 0);
             $organization  = $input['organization'] ?? '';
             $membership_id = $input['membership_id'] ?? '';
             $check = $mysqli->prepare("SELECT user_id FROM ProfessionalMembership WHERE user_id = ?");
             $check->bind_param("i", $user_id);$check->execute();
             $check->store_result();if ($check->num_rows > 0) 
             {$stmt = $mysqli->prepare("UPDATE ProfessionalMembership SET organization=?, membership_id=? WHERE user_id=?");
             $stmt->bind_param("ssi", $organization, $membership_id, $user_id);
             } else {
                $stmt = $mysqli->prepare("INSERT INTO ProfessionalMembership (user_id, organization, membership_id) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $user_id, $organization, $membership_id);
                }$check->close();
                if ($stmt && $stmt->execute()) {$response = ["status" => "success", "message" => "Professional membership saved"];
                } else {
                $response = ["status" => "error", "message" => $stmt ? $stmt->error : $mysqli->error];
                }if ($stmt) $stmt->close();
                header('Content-Type: application/json');
            echo json_encode($response);
            exit();

                //SocialContact

             case "addSocialContact":
                $user_id = intval($input['user_id'] ?? 0);
                $linkedin = $input['linkedin'] ?? '';
                $physical_address = $input['physical_address'] ?? '';
                $home_county = $input['home_county'] ?? '';
                $check = $mysqli->prepare("SELECT user_id FROM SocialContact WHERE user_id = ?");
                $check->bind_param("i", $user_id);$check->execute();
                $check->store_result();if ($check->num_rows > 0) 
                {$stmt = $mysqli->prepare("UPDATE SocialContact SET linkedin=?, physical_address=?, home_county=? WHERE user_id=?");
                $stmt->bind_param("sssi", $linkedin, $physical_address, $home_county, $user_id);
                } else {$stmt = $mysqli->prepare("INSERT INTO SocialContact (user_id, linkedin, physical_address, home_county) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $user_id, $linkedin, $physical_address, $home_county);
                }$check->close();
                if ($stmt && $stmt->execute()) {$response = ["status" => "success", "message" => "Profile completed successfully"];
                } else {$response = ["status" => "error", "message" => $stmt ? $stmt->error : $mysqli->error];
                }if ($stmt) $stmt->close();
               header('Content-Type: application/json');
            echo json_encode($response);
            exit();

             default:
    echo json_encode(["error" => "Invalid action"]);
}

$mysqli->close();
?>