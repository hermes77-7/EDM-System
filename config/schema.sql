CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    password VARCHAR(255),
    role ENUM('viewer', 'teacher', 'admin') DEFAULT 'viewer',
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    file_path VARCHAR(255),
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE folders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_folders_parent
        FOREIGN KEY (parent_id) REFERENCES folders(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_folders_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

ALTER TABLE documents
ADD COLUMN folder_id INT NULL,
ADD COLUMN original_name VARCHAR(255) NULL,
ADD COLUMN mime_type VARCHAR(100) NULL,
ADD COLUMN file_size BIGINT NULL,
ADD COLUMN description TEXT NULL,
ADD COLUMN keywords VARCHAR(500) NULL,
ADD COLUMN document_type VARCHAR(100) NULL,
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE documents
ADD CONSTRAINT fk_documents_folder
    FOREIGN KEY (folder_id) REFERENCES folders(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

ALTER TABLE documents
ADD CONSTRAINT fk_documents_uploaded_by
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

ALTER TABLE folders 
ADD visibility ENUM('public','teacher','admin') DEFAULT 'public';

ALTER TABLE documents 
ADD visibility ENUM('public','teacher','admin') DEFAULT 'public';

-- Check Database name in db.php