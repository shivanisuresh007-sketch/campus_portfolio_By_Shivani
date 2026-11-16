# 🎓 Campus Achievement Portfolio Management System

## 📌 Project Overview

The **Campus Achievement Portfolio Management System** is a web-based application developed to manage and maintain students' academic and extracurricular achievements digitally.

The system provides a centralized platform where students can register, log in, add their achievements, upload certificates, and track their achievement status. The administrator can view submitted achievements and approve or reject them.

This project helps colleges maintain student achievement records in an organized, secure, and efficient manner.

## 🎯 Objectives

- To maintain student achievements digitally.
- To reduce manual record keeping.
- To provide a centralized achievement management platform.
- To allow students to upload achievement certificates.
- To help administrators verify and manage student achievements.
- To provide easy access to achievement records.

## ✨ Features

### Student Module
- Student Registration and Login.
- Personal Student Dashboard.
- Add New Achievements.
- Select Achievement Categories.
- Upload Certificates.
- View Submitted Achievements.
- Track Approval Status.

### Admin Module
- Secure Administrator Login.
- Admin Dashboard.
- View Student Achievements.
- Approve Submitted Achievements.
- Reject Submitted Achievements.
- Manage Achievement Records.

### Achievement Categories

- Academic
- Sports
- Cultural
- Technical
- Research
- Other

## 🛠️ Technologies Used

| Technology | Purpose |
|---|---|
| HTML5 | Structure of the website |
| CSS3 | Website styling and responsive design |
| PHP | Server-side programming |
| MySQL | Database management |
| PDO | Database connectivity |
| XAMPP | Local server environment |
| JavaScript | Client-side interactions |

## 💻 Software Requirements

- Operating System: Windows 10/11
- Local Server: XAMPP
- Programming Language: PHP
- Database: MySQL
- Code Editor: Visual Studio Code
- Browser: Google Chrome or Microsoft Edge

## ⚙️ Installation and Setup

Follow these steps to run the project on your computer.

**Step 1: Download the Project**

Clone the repository using:

```bash
git clone https://github.com/shivanisuresh007-sketch/campus_portfolio_By_Shivani.git
```

**Step 2: Move the Project**

Copy the downloaded project folder into:

```text
C:\xampp\htdocs\
```

**Step 3: Start XAMPP**

- Open XAMPP Control Panel.
- Start Apache.
- Start MySQL.

**Step 4: Create the Database**

- Open your browser.
- Visit `http://localhost/phpmyadmin/`.
- Create a database named `campus_portfolio`.

**Step 5: Run the Project**

Open your browser and enter:

```text
http://localhost/campus_portfolio_By_Shivani/
```

The application automatically creates the required database tables and default achievement categories when it connects successfully to the database.

## 🔐 Default Admin Login

The current source code initializes the administrator account with the following credentials:

| Field | Details |
|---|---|
| Email | admin@gmail.com |
| Password | Admin@123 |

**Note:** Change the default administrator password before using the application in a real environment.

## 🗄️ Database Tables

The application uses the following MySQL tables:

1. **users** – Stores student and administrator account information.
2. **categories** – Stores achievement categories.
3. **achievements** – Stores student achievement details, certificate paths, and approval status.

## 🔄 Working Process

1. Students register using their name, email, and password.
2. Registered students log in to the application.
3. Students access their personal dashboard.
4. Students add achievements with descriptions, dates, and certificates.
5. Submitted achievements are stored in the database with Pending status.
6. The administrator reviews the submitted achievements.
7. The administrator approves or rejects the achievements.
8. Students can view their achievement records and updated status.

## 🔒 Security Features

- Password hashing.
- Session-based authentication.
- Separate student and administrator access.
- Prepared SQL statements.
- Input validation.
- File type and file size validation.
- Restricted administrator pages.

## 🚀 Future Enhancements

- Download student achievement reports.
- Generate achievement certificates.
- Add advanced search and filtering.
- Provide PDF portfolio generation.
- Add email notifications.
- Improve dashboard analytics.

## 👩‍💻 Developer

**Developed by:** Shivani S H

**Project Title:** Campus Achievement Portfolio Management System

**Project Type:** Web Development Mini Project

## 📂 GitHub Repository

[Campus Achievement Portfolio – GitHub](https://github.com/shivanisuresh007-sketch/campus_portfolio_By_Shivani)

## 📝 Conclusion

The Campus Achievement Portfolio Management System provides an effective digital solution for recording, storing, and managing student achievements. It reduces paperwork, improves record management, and makes achievement tracking easier for students and administrators. The project demonstrates the practical application of web development technologies such as HTML, CSS, PHP, and MySQL.
