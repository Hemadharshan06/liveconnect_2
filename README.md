# LiveConnect

LiveConnect is a real-time webinar platform that allows hosts to conduct interactive online sessions with participants.

## Features

- Create and host webinars
- Join webinars using a unique join code
- Real-time video and audio communication using WebRTC
- Real-time chat
- Live reactions
- Live Q&A
- Live polls and voting
- Webinar analytics
- End webinar and view session statistics

## Technologies Used

- HTML
- CSS
- JavaScript
- WebRTC
- PHP
- MySQL
- Apache (XAMPP)

## Requirements

- XAMPP
- Apache
- MySQL
- Modern web browser with WebRTC support

## Setup

1. Install XAMPP.
2. Start Apache and MySQL.
3. Place the project inside the XAMPP `htdocs` directory.
4. Create a MySQL database named `liveconnect`.
5. Import `liveconnect.sql` into the database.
6. Configure the local database connection in `php/db.php`.
7. Open the application:

http://localhost/liveconnect/host.html

## Usage

### Host

1. Open the host page.
2. Create a webinar.
3. Share the generated join code with participants.
4. Conduct the webinar using video, chat, polls, Q&A and reactions.
5. End the webinar to view analytics.

### Participant

1. Open the participant page.
2. Enter your name.
3. Enter the webinar join code.
4. Join the live session.
   
## Project Structure

LiveConnect/
├── frontend/
├── php/
├── liveconnect.sql
├── .gitignore
└── README.md


##Note

This project is configured for local development using XAMPP.

Database credentials are configured locally and should not be committed to the repository.

## Architecture

```text
Browser
   |
   v
Apache
   |
   +-- Frontend (HTML/CSS/JavaScript)
   |
   +-- PHP Backend
          |
          v
        MySQL
