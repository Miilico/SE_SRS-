ALTER TABLE scholarship_fields
    ADD COLUMN IF NOT EXISTS field_note VARCHAR(500) NULL AFTER field_label;
