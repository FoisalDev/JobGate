-- JobGate Application Database Schema (MySQL)
-- Designed for Applicant Screening based on Skill Assessment and Course Completion.
-- Normalization Level: 3NF (Third Normal Form)

-- ========================================================================
-- প্রাথমিক টেবিল: ইউজার এবং প্রোফাইল
-- ========================================================================

-- 1. ইউজার এবং অথেন্টিকেশন (Users and Authentication)
CREATE TABLE Users (
    user_id CHAR(36) PRIMARY KEY,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash CHAR(64) NOT NULL, -- পাসওয়ার্ড SHA2-256 দিয়ে এনক্রিপ্ট করা হবে
    full_name VARCHAR(150) NOT NULL,
    user_type ENUM('applicant', 'recruiter', 'admin') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT 'Contains all system users (Applicant, Recruiter, Admin)';

-- 2. আবেদনকারীর প্রোফাইল (Applicant Profile - 1:1 সম্পর্ক Users টেবিলের সাথে)
CREATE TABLE Applicants (
    applicant_id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) UNIQUE NOT NULL,
    headline VARCHAR(255),
    phone_number VARCHAR(20),
    address VARCHAR(255),
    about_me TEXT,
    profile_completion_pct TINYINT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) COMMENT 'Detailed profile information for job applicants';

-- 3. রিক্রুটারের তথ্য (Recruiter/Company Information - 1:1 সম্পর্ক Users টেবিলের সাথে)
CREATE TABLE Recruiters (
    recruiter_id CHAR(36) PRIMARY KEY,
    user_id CHAR(36) UNIQUE NOT NULL,
    company_name VARCHAR(150) NOT NULL,
    company_logo_url VARCHAR(255),
    industry VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES Users(user_id) ON DELETE CASCADE
) COMMENT 'Information about recruiters and the companies they represent';

-- 13. প্রোফাইল কম্পোনেন্ট (Education, Experience, Links)
CREATE TABLE Education (
    edu_id CHAR(36) PRIMARY KEY,
    applicant_id CHAR(36) NOT NULL,
    institution VARCHAR(150),
    degree VARCHAR(100),
    start_date YEAR,
    end_date YEAR,
    FOREIGN KEY (applicant_id) REFERENCES Applicants(applicant_id) ON DELETE CASCADE
) COMMENT 'Applicant education history';

CREATE TABLE Experience (
    exp_id CHAR(36) PRIMARY KEY,
    applicant_id CHAR(36) NOT NULL,
    title VARCHAR(100),
    company VARCHAR(100),
    start_date DATE,
    end_date DATE,
    description TEXT,
    FOREIGN KEY (applicant_id) REFERENCES Applicants(applicant_id) ON DELETE CASCADE
) COMMENT 'Applicant work experience';

CREATE TABLE ProfileLinks (
    link_id CHAR(36) PRIMARY KEY,
    applicant_id CHAR(36) NOT NULL,
    link_text VARCHAR(100),
    link_url VARCHAR(255),
    FOREIGN KEY (applicant_id) REFERENCES Applicants(applicant_id) ON DELETE CASCADE
) COMMENT 'Applicant portfolio and social links';

-- 14. দক্ষতার তালিকা (Skills List)
CREATE TABLE ApplicantSkills (
    skill_id CHAR(36) PRIMARY KEY,
    applicant_id CHAR(36) NOT NULL,
    skill_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (applicant_id) REFERENCES Applicants(applicant_id) ON DELETE CASCADE,
    UNIQUE KEY unique_skill_per_applicant (applicant_id, skill_name)
) COMMENT 'Applicant skills (e.g., Python, React, Photoshop)';

-- ========================================================================
-- জব সেক্টর, জব এবং ইভেন্টস
-- ========================================================================

CREATE TABLE JobSectors (
    sector_id CHAR(36) PRIMARY KEY,
    sector_name VARCHAR(100) UNIQUE NOT NULL -- যেমন: 'Information Technology', 'Finance'
) COMMENT 'Broad industry categories for job postings, assessments, and courses';

-- 4. চাকরির পদ (Job Postings)
CREATE TABLE Jobs (
    job_id CHAR(36) PRIMARY KEY,
    recruiter_id CHAR(36) NOT NULL,
    sector_id CHAR(36) NOT NULL,
    title VARCHAR(150) NOT NULL,
    job_role VARCHAR(100),
    location VARCHAR(100),
    type ENUM('Full-time', 'Part-time', 'Contract', 'Internship') NOT NULL,
    salary_min INT,
    salary_max INT,
    description TEXT NOT NULL,
    requirements TEXT,
    application_deadline DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recruiter_id) REFERENCES Recruiters(recruiter_id) ON DELETE CASCADE,
    FOREIGN KEY (sector_id) REFERENCES JobSectors(sector_id) ON DELETE RESTRICT
) COMMENT 'List of all job postings in the system';

-- জব ইভেন্টস (Job Events)
CREATE TABLE JobEvents (
    event_id CHAR(36) PRIMARY KEY,
    organizer_recruiter_id CHAR(36),
    title VARCHAR(150) NOT NULL,
    event_type ENUM('Job Fair', 'Workshop', 'Seminar', 'Competition') NOT NULL,
    date_start DATE NOT NULL,
    date_end DATE,
    location VARCHAR(150),
    description TEXT,
    application_link VARCHAR(255),
    FOREIGN KEY (organizer_recruiter_id) REFERENCES Recruiters(recruiter_id) ON DELETE SET NULL
) COMMENT 'List of career events, job fairs, and workshops';


-- ========================================================================
-- অ্যাসেসমেন্ট, কোর্স এবং ট্র্যাকিং (কোর গেট লজিক)
-- ========================================================================

-- 5. কোর্সের তালিকা (Courses - Coursera থেকে ম্যানুয়াল ইনপুট)
CREATE TABLE Courses (
    course_id CHAR(36) PRIMARY KEY,
    title VARCHAR(150) UNIQUE NOT NULL,
    sector_id CHAR(36),
    platform VARCHAR(50) DEFAULT 'Coursera',
    link_url VARCHAR(255) NOT NULL,
    duration_hours INT,
    description TEXT,
    FOREIGN KEY (sector_id) REFERENCES JobSectors(sector_id) ON DELETE SET NULL
) COMMENT 'List of recommended courses for upskilling';

-- 6. স্কিল অ্যাসেসমেন্ট (Skill Assessment - পরীক্ষার প্রশ্ন)
-- Allowed Attempts: 2 - (প্রথম প্রচেষ্টা + কোর্স করে দ্বিতীয় ও শেষ প্রচেষ্টা)
CREATE TABLE Assessments (
    assessment_id CHAR(36) PRIMARY KEY,
    title VARCHAR(150) UNIQUE NOT NULL,
    sector_id CHAR(36) NOT NULL,
    job_role VARCHAR(100),
    pass_score_percent TINYINT NOT NULL DEFAULT 80,
    duration_minutes INT NOT NULL,
    total_questions INT NOT NULL,
    allowed_attempts TINYINT DEFAULT 2, -- **এখানে 2 সেট করা হয়েছে**
    FOREIGN KEY (sector_id) REFERENCES JobSectors(sector_id) ON DELETE RESTRICT
) COMMENT 'Configuration for all skill assessments';

-- 7. প্রশ্ন ব্যাংক (Question Bank - Assessment-এর জন্য প্রশ্ন)
CREATE TABLE Questions (
    question_id CHAR(36) PRIMARY KEY,
    assessment_id CHAR(36) NOT NULL,
    question_text TEXT NOT NULL,
    correct_option_index TINYINT NOT NULL,
    FOREIGN KEY (assessment_id) REFERENCES Assessments(assessment_id) ON DELETE CASCADE
) COMMENT 'Multiple Choice Questions for assessments';

-- 8. প্রশ্নের অপশন (Question Options - প্রতিটি প্রশ্নের জন্য অপশন)
CREATE TABLE QuestionOptions (
    option_id CHAR(36) PRIMARY KEY,
    question_id CHAR(36) NOT NULL,
    option_index TINYINT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    FOREIGN KEY (question_id) REFERENCES Questions(question_id) ON DELETE CASCADE,
    UNIQUE KEY unique_option_per_question (question_id, option_index)
) COMMENT 'Options for MCQ questions';

-- 9. পরীক্ষার ফল (Assessment Results - Applicant-দের পরীক্ষার ফল)
CREATE TABLE AssessmentResults (
    result_id CHAR(36) PRIMARY KEY,
    applicant_id CHAR(36) NOT NULL,
    assessment_id CHAR(36) NOT NULL,
    score_obtained DECIMAL(5, 2) NOT NULL,
    score_percent TINYINT NOT NULL,
    attempt_number TINYINT NOT NULL,
    is_passed BOOLEAN NOT NULL,
    exam_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES Applicants(applicant_id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES Assessments(assessment_id) ON DELETE CASCADE
) COMMENT 'Records of applicant assessment attempts and results';

-- অ্যাপ্লিক্যান্টের উত্তরপত্র (Applicant Answers)
CREATE TABLE ApplicantAnswers (
    answer_id CHAR(36) PRIMARY KEY,
    result_id CHAR(36) NOT NULL, -- কোন অ্যাটেম্পট (AssessmentResult) এর অংশ
    question_id CHAR(36) NOT NULL,
    selected_option_index TINYINT NOT NULL,
    is_correct BOOLEAN NOT NULL,
    FOREIGN KEY (result_id) REFERENCES AssessmentResults(result_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES Questions(question_id) ON DELETE CASCADE
) COMMENT 'Stores the specific answers given by an applicant for each question in an attempt';

-- 11. কোর্স ট্র্যাকিং (Course Tracking - বাধ্যতামূলক কোর্স সম্পূর্ণতার জন্য)
-- এটি ট্র্যাক করে যে কোন ব্যর্থ পরীক্ষার জন্য কোন কোর্সটি করতে বলা হয়েছে।
CREATE TABLE CourseTracking (
    tracking_id CHAR(36) PRIMARY KEY,
    applicant_id CHAR(36) NOT NULL,
    course_id CHAR(36) NOT NULL,
    assessment_id CHAR(36) NOT NULL,
    required_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completion_date DATE,
    is_completed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (applicant_id) REFERENCES Applicants(applicant_id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES Courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES Assessments(assessment_id) ON DELETE CASCADE
) COMMENT 'Tracks mandatory course completion after failing an assessment';

-- 12. অ্যাপ্লিকেশনের প্রয়োজনীয় দক্ষতা (Job-Skill-Assessment Mapping)
CREATE TABLE JobAssessmentRequirements (
    job_id CHAR(36) NOT NULL,
    assessment_id CHAR(36) NOT NULL,
    PRIMARY KEY (job_id, assessment_id),
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (assessment_id) REFERENCES Assessments(assessment_id) ON DELETE CASCADE
) COMMENT 'Maps specific jobs to their required skill assessment';

-- 10. চাকরির আবেদন (Job Applications)
-- আবেদন সফল হতে হলে Applicant-কে অবশ্যই একটি বৈধ `passed_assessment_id` থাকতে হবে।
CREATE TABLE Applications (
    application_id CHAR(36) PRIMARY KEY,
    job_id CHAR(36) NOT NULL,
    applicant_id CHAR(36) NOT NULL,
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'In Review', 'Hired', 'Rejected') NOT NULL DEFAULT 'Pending',
    passed_assessment_id CHAR(36), -- যে Assessment-এ পাস করে আবেদন করেছে
    FOREIGN KEY (job_id) REFERENCES Jobs(job_id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES Applicants(applicant_id) ON DELETE CASCADE,
    FOREIGN KEY (passed_assessment_id) REFERENCES Assessments(assessment_id) ON DELETE SET NULL
) COMMENT 'Records of successful job applications (only allowed after passing the assessment)';


-- ========================================================================
-- প্রাথমিক ডেটা সন্নিবেশ (Initial Data Insertion)
-- ========================================================================

-- ইউজার টাইপ: Admin (স্থির পাসওয়ার্ড এবং আইডি)
INSERT INTO Users (user_id, email, password_hash, full_name, user_type) VALUES
('Admin_1', 'admin1@jobgate.com', SHA2('admin123', 256), 'JobGate Admin 1', 'admin'),
('Admin_2', 'admin2@jobgate.com', SHA2('admin456', 256), 'JobGate Admin 2', 'admin');

-- ইউজার টাইপ: Recruiter (উদাহরণ)
INSERT INTO Users (user_id, email, password_hash, full_name, user_type) VALUES
('Recruiter_1', 'hr@techsolutions.com', SHA2('recruiterpass', 256), 'Jane Smith (TechSolutions)', 'recruiter');

INSERT INTO Recruiters (recruiter_id, user_id, company_name, industry) VALUES
('Recruiter_1', 'Recruiter_1', 'Loreem Tech Solutions', 'IT & Software');

-- ইউজার টাইপ: Applicant (উদাহরণ)
INSERT INTO Users (user_id, email, password_hash, full_name, user_type) VALUES
('Applicant_1', 'ali@example.com', SHA2('applicantpass', 256), 'Md. Ali Arefin', 'applicant');

INSERT INTO Applicants (applicant_id, user_id, headline) VALUES
('Applicant_1', 'Applicant_1', 'Junior Web Developer | Looking for entry-level role');

-- জব সেক্টর
INSERT INTO JobSectors (sector_id, sector_name) VALUES
('SEC_IT', 'Information Technology'),
('SEC_FIN', 'Finance'),
('SEC_MKT', 'Marketing'),
('SEC_QA', 'Quality Assurance');

-- পরীক্ষার ডেটা (Assessment Data)
INSERT INTO Assessments (assessment_id, title, sector_id, job_role, pass_score_percent, duration_minutes, total_questions, allowed_attempts) VALUES
('ASM_JS', 'JavaScript Fundamentals', 'SEC_IT', 'Web Developer', 80, 30, 5, 2),
('ASM_QA', 'Software Testing Basics', 'SEC_QA', 'QA Engineer', 80, 30, 5, 2),
('ASM_DB', 'SQL Database Queries', 'SEC_IT', 'Data Analyst', 80, 30, 5, 2);

INSERT INTO Questions (question_id, assessment_id, question_text, correct_option_index) VALUES
('Q_JS_1', 'ASM_JS', 'What is the correct way to declare a variable in modern JavaScript?', 0),
('Q_JS_2', 'ASM_JS', 'Which loop is used to iterate over the properties of an object?', 2);

INSERT INTO QuestionOptions (option_id, question_id, option_index, option_text) VALUES
(UUID(), 'Q_JS_1', 0, 'let x = 5;'),
(UUID(), 'Q_JS_1', 1, 'var x = 5;'),
(UUID(), 'Q_JS_1', 2, 'const x = 5;'),
(UUID(), 'Q_JS_1', 3, 'variable x = 5;'),

(UUID(), 'Q_JS_2', 0, 'for (i=0; i<10; i++)'),
(UUID(), 'Q_JS_2', 1, 'for (let item of array)'),
(UUID(), 'Q_JS_2', 2, 'for (let key in object)'),
(UUID(), 'Q_JS_2', 3, 'while (condition)');

-- চাকরির ডেটা (Job Data)
INSERT INTO Jobs (job_id, recruiter_id, sector_id, title, job_role, location, type, salary_min, salary_max, description, requirements, application_deadline) VALUES
('JOB_SE', 'Recruiter_1', 'SEC_IT', 'Junior Software Engineer', 'Software Engineer', 'Dhaka, Hybrid', 'Full-time', 30000, 50000, 'We are looking for a passionate JSE...', 'Strong grasp of Data Structures and Algorithms.', '2026-03-31'),
('JOB_QA', 'Recruiter_1', 'SEC_QA', 'Entry-Level QA Analyst', 'QA Engineer', 'Chattogram, Remote', 'Full-time', 20000, 30000, 'Test our mobile applications and APIs.', 'Knowledge of manual testing and bug tracking.', '2026-04-15');

INSERT INTO JobAssessmentRequirements (job_id, assessment_id) VALUES
('JOB_SE', 'ASM_JS'),
('JOB_QA', 'ASM_QA');

-- কোর্সের ডেটা (Course Data)
INSERT INTO Courses (course_id, title, sector_id, link_url, duration_hours, description) VALUES
('C_JS_1', 'Introduction to JavaScript Programming', 'SEC_IT', 'https://www.coursera.org/learn/javascript-intro', 40, 'A comprehensive course for JavaScript fundamentals.'),
('C_QA_1', 'Basics of Software Testing', 'SEC_QA', 'https://www.coursera.org/learn/software-testing-basics', 30, 'Learn key concepts of SDLC and STLC.');

-- জব ইভেন্ট (Job Event Data)
INSERT INTO JobEvents (event_id, organizer_recruiter_id, title, event_type, date_start, date_end, location, description, application_link) VALUES
('EVT_1', 'Recruiter_1', 'Dhaka Tech Job Fair 2026', 'Job Fair', '2026-03-10', '2026-03-12', 'International Convention City Bashundhara (ICCB)', 'Meet the top tech companies in Bangladesh.', 'https://jobgate-events.com/techfair');


-- ========================================================================
-- ট্রানজ্যাকশন শেষ (End Transaction)
-- ========================================================================
COMMIT;
