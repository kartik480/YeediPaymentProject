<?php
declare(strict_types=1);

final class KybService {
    public const TYPES = [
        'GST' => 'GST',
        'PAN' => 'PAN',
        'COI' => 'COI',
        'MOA' => 'MOA',
        'AOA' => 'AOA',
        'BOARD_RESOLUTION' => 'BOARD_RESOLUTION',
        'CANCELLED_CHEQUE' => 'CANCELLED_CHEQUE',
        'OTHER' => 'OTHER',
    ];

    public static function ensureSchema(PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS kyb_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            doc_type VARCHAR(40) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_orig VARCHAR(180) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'PENDING',
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_kyb_cust(customer_id),
            INDEX idx_kyb_status(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function all(PDO $db): array {
        self::ensureSchema($db);
        return $db->query("SELECT k.*, c.code customer_code, c.name customer_name
            FROM kyb_documents k
            JOIN customers c ON c.id=k.customer_id
            ORDER BY k.id DESC LIMIT 500")->fetchAll();
    }

    public static function get(PDO $db, int $id): ?array {
        self::ensureSchema($db);
        $st = $db->prepare("SELECT k.*, c.code customer_code, c.name customer_name FROM kyb_documents k JOIN customers c ON c.id=k.customer_id WHERE k.id=?");
        $st->execute([$id]);
        $r = $st->fetch();
        return $r ?: null;
    }

    public static function add(PDO $db, array $in, array $file): int {
        self::ensureSchema($db);
        $code = normalize_code((string)($in['company'] ?? ''));
        $c = TransactionService::customerByCode($db, $code);
        if (!$c) throw new RuntimeException('Select a company');
        $type = strtoupper(trim((string)($in['doc_type'] ?? '')));
        if (!isset(self::TYPES[$type])) throw new RuntimeException('Select a document type');
        [$path, $orig] = self::storeFile($file);
        $st = $db->prepare("INSERT INTO kyb_documents(customer_id,doc_type,file_path,file_orig,status,created_at) VALUES(?,?,?,?, 'PENDING', UTC_TIMESTAMP())");
        $st->execute([(int)$c['id'], $type, $path, $orig]);
        return (int)$db->lastInsertId();
    }

    public static function review(PDO $db, int $id, string $decision): void {
        self::ensureSchema($db);
        $decision = strtoupper(trim($decision));
        if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) throw new RuntimeException('Invalid review');
        $row = self::get($db, $id);
        if (!$row) throw new RuntimeException('Document not found');
        $db->prepare("UPDATE kyb_documents SET status=?, reviewed_at=UTC_TIMESTAMP() WHERE id=?")->execute([$decision, $id]);
    }

    public static function stream(array $row): never {
        $abs = self::absPath($row);
        if (!$abs) {
            http_response_code(404);
            exit('File not found');
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($abs);
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) $mime = 'application/octet-stream';
        $name = (string)($row['file_orig'] ?? 'kyb-document');
        header('Content-Type: '.$mime);
        header('Content-Length: '.(string)filesize($abs));
        header('Content-Disposition: inline; filename="'.str_replace(['"', "\r", "\n"], '', $name).'"');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    private static function absPath(array $row): ?string {
        $rel = trim((string)($row['file_path'] ?? ''));
        if ($rel === '') return null;
        $root = realpath(YEEDIPAY_ROOT) ?: YEEDIPAY_ROOT;
        $abs = realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel));
        $dir = realpath($root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'kyb_documents');
        if (!$abs || !$dir || !str_starts_with($abs, $dir.DIRECTORY_SEPARATOR) || !is_file($abs)) {
            return null;
        }
        return $abs;
    }

    /** @return array{0:string,1:string} */
    private static function storeFile(array $file): array {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
            throw new RuntimeException('Upload a KYB document');
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || (int)($file['size'] ?? 0) > 8 * 1024 * 1024) {
            throw new RuntimeException('File is too large (max 8 MB)');
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new RuntimeException('Upload failed. Try again.');
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? '';
        if ($ext === '') throw new RuntimeException('File must be PDF, JPG, PNG, or WEBP');
        $dir = YEEDIPAY_ROOT.'/storage/kyb_documents';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not save the document');
        }
        $name = date('YmdHis').'_'.bin2hex(random_bytes(8)).'.'.$ext;
        if (!move_uploaded_file((string)$file['tmp_name'], $dir.'/'.$name)) {
            throw new RuntimeException('Could not save the document');
        }
        $orig = basename((string)($file['name'] ?? 'document.'.$ext));
        $orig = substr(preg_replace('/[^A-Za-z0-9._-]/', '_', $orig) ?? 'document', 0, 180);
        return ['storage/kyb_documents/'.$name, $orig];
    }
}
