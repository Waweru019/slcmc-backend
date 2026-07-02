<?php
// Database connection
$conn = new mysqli("localhost", "root", "Dan123??", "alumniportal");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Tables to create
$tables = [
    // Users table
   "CREATE TABLE   Users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        First_name VARCHAR(100),
        Dob DATE,
        Gender VARCHAR(10),
        Email VARCHAR(100) UNIQUE,
        Phone_number VARCHAR(15),
        id_no VARCHAR(20) UNIQUE,
        photo VARCHAR(255),
        Password VARCHAR(255) NOT NULL,
        Graduation_class VARCHAR(10),
        status VARCHAR(50) DEFAULT 'pending',
        role VARCHAR(50) DEFAULT 'alumni',
        requires_password_change TINYINT(1) NOT NULL DEFAULT 0,
        reset_token VARCHAR(64) DEFAULT NULL,
        reset_token_expires DATETIME DEFAULT NULL  -- <-- Removed the trailing comma here!
    )",

    "CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action_string VARCHAR(255) NOT NULL,
    target_details VARCHAR(255) NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
)",
    "CREATE TABLE  announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    audience VARCHAR(50) DEFAULT 'All',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",

    // Academic Record
    "CREATE TABLE  AcademicRecord (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        programme_name VARCHAR(150),
        year_joined DATE,
        cohort_name VARCHAR(100),
        graduation_year YEAR,
        reg_number VARCHAR(100),
        FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    )",

    // Current Employment
    "CREATE TABLE  CurrentEmployment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        employer_name VARCHAR(150),
        role VARCHAR(100),
        employment_type VARCHAR(50),
        work_county VARCHAR(50),
        start_date DATE,
        FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    )",

    // Employment History
    "CREATE TABLE  EmploymentHistory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        employer_name VARCHAR(150),
        role VARCHAR(100),
        from_date DATE,
        to_date DATE,
        FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    )",

    // Further Education
    "CREATE TABLE FurtherEducation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        institution VARCHAR(150),
        field_of_study VARCHAR(150),
        year_completed YEAR,
        FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    )",

    // Professional Membership
    "CREATE TABLE ProfessionalMembership (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        organization VARCHAR(150),
        membership_id VARCHAR(50),
        FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    )",
   "CREATE TABLE Programmes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    programme VARCHAR(300) NOT NULL
)",
 "CREATE TABLE  alumni_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message_text TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES Users(id) ON DELETE CASCADE
) ",

  " CREATE TABLE Cohort (
    id INT AUTO_INCREMENT PRIMARY KEY,
    programme_id INT NOT NULL, -- 🔗 Links the cohort directly to its specific course
    cohort VARCHAR(300) NOT NULL,
    FOREIGN KEY (programme_id) REFERENCES Programmes(id) ON DELETE CASCADE
)",
    
  "CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    token VARCHAR(64) NOT NULL,
    status ENUM('Active', 'Used', 'Expired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
)",

"CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(150) NOT NULL,
    otp_code VARCHAR(6) NOT NULL, -- Storing a 6-digit code
    status ENUM('Active', 'Used', 'Expired') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
)",

    "CREATE TABLE SocialContact (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        linkedin VARCHAR(150),
        physical_address VARCHAR(255),
        home_county VARCHAR(50),
        FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

    )",
    "CREATE TABLE PrivacySettings (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    show_email BOOLEAN DEFAULT 1,

    show_phone BOOLEAN DEFAULT 0,

    show_employment BOOLEAN DEFAULT 1,

    show_education BOOLEAN DEFAULT 1,
    show_membership BOOLEAN DEFAULT 0,
    show_social  BOOLEAN DEFAULT 0,
    show_contact BOOLEAN DEFAULT 0,


    FOREIGN KEY(user_id)
    REFERENCES Users(id)
    ON DELETE CASCADE

)"
];

// Execute each table creation
foreach ($tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created successfully<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

$conn->close();
?>
