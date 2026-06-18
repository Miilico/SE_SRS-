START TRANSACTION;

ALTER TABLE scholarship
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = open, 0 = manually closed' AFTER start_date;

ALTER TABLE application
    MODIFY COLUMN GRADE DECIMAL(5,2) NULL;

CREATE TABLE IF NOT EXISTS scholarship_fields (
    id INT NOT NULL AUTO_INCREMENT,
    scholarship_id INT NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text', 'number', 'textarea', 'file') NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scholarship_fields_scholarship (scholarship_id, sort_order),
    CONSTRAINT fk_scholarship_fields_scholarship
        FOREIGN KEY (scholarship_id) REFERENCES scholarship(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE scholarship_fields
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER is_required,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER sort_order,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE scholarship_fields
    ADD INDEX IF NOT EXISTS idx_scholarship_fields_scholarship (scholarship_id, sort_order);

CREATE TABLE IF NOT EXISTS application_custom_answers (
    id INT NOT NULL AUTO_INCREMENT,
    application_id INT NOT NULL,
    field_id INT NOT NULL,
    answer_value TEXT NULL,
    file_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_custom_answer_application_field (application_id, field_id),
    KEY idx_custom_answers_field (field_id),
    KEY idx_custom_answers_file (file_id),
    CONSTRAINT fk_custom_answers_application
        FOREIGN KEY (application_id) REFERENCES application(APNO) ON DELETE CASCADE,
    CONSTRAINT fk_custom_answers_field
        FOREIGN KEY (field_id) REFERENCES scholarship_fields(id) ON DELETE CASCADE,
    CONSTRAINT fk_custom_answers_file
        FOREIGN KEY (file_id) REFERENCES application_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE application_custom_answers
    ADD COLUMN IF NOT EXISTS file_id INT NULL AFTER answer_value,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER file_id,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE application_custom_answers
    ADD UNIQUE INDEX IF NOT EXISTS uq_custom_answer_application_field (application_id, field_id),
    ADD INDEX IF NOT EXISTS idx_custom_answers_field (field_id),
    ADD INDEX IF NOT EXISTS idx_custom_answers_file (file_id);

COMMIT;
