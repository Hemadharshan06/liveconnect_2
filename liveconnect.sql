CREATE DATABASE IF NOT EXISTS liveconnect
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE liveconnect;

-- =====================================================
-- WEBINARS
-- =====================================================

CREATE TABLE IF NOT EXISTS webinars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    host_name VARCHAR(255) NOT NULL,
    join_code VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(50) NOT NULL DEFAULT 'live',

    INDEX idx_join_code (join_code),
    INDEX idx_status (status)
) ENGINE=InnoDB;


-- =====================================================
-- PARTICIPANTS
-- =====================================================

CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    webinar_id INT NOT NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_participant_webinar (webinar_id),

    CONSTRAINT fk_participant_webinar
        FOREIGN KEY (webinar_id)
        REFERENCES webinars(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- REACTIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    webinar_id INT NOT NULL,
    reaction VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_reaction_webinar (webinar_id),
    INDEX idx_reaction_participant (participant_id),

    CONSTRAINT fk_reaction_webinar
        FOREIGN KEY (webinar_id)
        REFERENCES webinars(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_reaction_participant
        FOREIGN KEY (participant_id)
        REFERENCES participants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- MESSAGES / CHAT
-- =====================================================

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    participant_id INT NOT NULL,
    participant_name VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_message_webinar (webinar_id),
    INDEX idx_message_created (created_at),

    CONSTRAINT fk_message_webinar
        FOREIGN KEY (webinar_id)
        REFERENCES webinars(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_message_participant
        FOREIGN KEY (participant_id)
        REFERENCES participants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- POLLS
-- =====================================================

CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    question TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_poll_webinar (webinar_id),
    INDEX idx_poll_active (is_active),

    CONSTRAINT fk_poll_webinar
        FOREIGN KEY (webinar_id)
        REFERENCES webinars(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- POLL OPTIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,

    INDEX idx_poll_option_poll (poll_id),

    CONSTRAINT fk_poll_option_poll
        FOREIGN KEY (poll_id)
        REFERENCES polls(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- POLL VOTES
-- =====================================================

CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_id INT NOT NULL,
    participant_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_vote_poll (poll_id),
    INDEX idx_vote_option (option_id),
    INDEX idx_vote_participant (participant_id),

    CONSTRAINT fk_vote_poll
        FOREIGN KEY (poll_id)
        REFERENCES polls(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_vote_option
        FOREIGN KEY (option_id)
        REFERENCES poll_options(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_vote_participant
        FOREIGN KEY (participant_id)
        REFERENCES participants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================
-- QUESTIONS / Q&A
-- =====================================================

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    participant_id INT NOT NULL,
    participant_name VARCHAR(255) NOT NULL,
    question TEXT NOT NULL,
    is_answered TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_question_webinar (webinar_id),
    INDEX idx_question_answered (is_answered),

    CONSTRAINT fk_question_webinar
        FOREIGN KEY (webinar_id)
        REFERENCES webinars(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_question_participant
        FOREIGN KEY (participant_id)
        REFERENCES participants(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;
