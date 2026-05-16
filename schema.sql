-- Fantasy Football AI - Database Schema
-- Creates the `fantasy` database and every table. Safe to run on a fresh MySQL server.

CREATE DATABASE IF NOT EXISTS fantasy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE fantasy;

-- Users: links a centralized-auth account to its Yahoo Fantasy OAuth tokens
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    name VARCHAR(255) NULL,
    yahoo_user_id VARCHAR(255) UNIQUE,
    access_token TEXT,
    refresh_token TEXT,
    token_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_id (user_id)
);

-- Scoring types lookup (lookup tables are used instead of ENUMs)
CREATE TABLE scoring_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);

INSERT INTO scoring_types (name, description) VALUES
('standard', 'Standard scoring'),
('ppr', 'Points per reception'),
('half_ppr', 'Half point per reception'),
('custom', 'Custom scoring system');

-- Analysis status lookup
CREATE TABLE analysis_status_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);

INSERT INTO analysis_status_types (name, description) VALUES
('pending', 'Analysis request pending'),
('processing', 'Analysis in progress'),
('completed', 'Analysis completed successfully'),
('failed', 'Analysis failed');

-- Leagues a user has connected
CREATE TABLE leagues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    yahoo_league_id VARCHAR(255),
    league_name VARCHAR(255),
    scoring_type_id INT,
    roster_positions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (scoring_type_id) REFERENCES scoring_types(id)
);

-- AI analysis requests and their results
CREATE TABLE analysis_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    league_id INT,
    status_id INT DEFAULT 1,
    roster_data JSON,
    available_players_data JSON,
    recommendations JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (league_id) REFERENCES leagues(id),
    FOREIGN KEY (status_id) REFERENCES analysis_status_types(id)
);

-- Direct Yahoo add-player links surfaced alongside recommendations
CREATE TABLE player_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    league_id INT,
    player_name VARCHAR(255),
    yahoo_add_url TEXT,
    yahoo_player_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (league_id) REFERENCES leagues(id)
);

-- API usage log for Yahoo / AI rate-limit tracking
CREATE TABLE api_usage_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    params TEXT,
    http_status INT,
    cost_calls INT DEFAULT 1,
    response_ms INT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_date (provider, requested_at)
);
