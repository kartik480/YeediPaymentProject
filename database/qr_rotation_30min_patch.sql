SET NAMES utf8mb4;
ALTER TABLE bank_profiles MODIFY rotation_minutes INT NOT NULL DEFAULT 30;
UPDATE bank_profiles SET rotation_minutes=30 WHERE enabled=1;
INSERT INTO system_config(id,rotation_enabled,rotation_started_at,manual_active_bank_id,updated_at)
SELECT 1,0,NULL,NULL,UTC_TIMESTAMP()
WHERE NOT EXISTS(SELECT 1 FROM system_config WHERE id=1);
UPDATE system_config
SET rotation_enabled=CASE WHEN(SELECT COUNT(*) FROM bank_profiles WHERE enabled=1)>=2 THEN 1 ELSE rotation_enabled END,
    rotation_started_at=CASE WHEN(SELECT COUNT(*) FROM bank_profiles WHERE enabled=1)>=2 THEN COALESCE(rotation_started_at,UTC_TIMESTAMP()) ELSE rotation_started_at END,
    updated_at=UTC_TIMESTAMP()
WHERE id=1;
