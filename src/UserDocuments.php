<?php

declare(strict_types=1);

namespace KaamFit;

use App;
use Auth;
use Db;
use PdfExport;
use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * Supporting application documents (certificates, degrees, transcripts, …)
 * with versioned files and per-application attachments.
 */
final class UserDocuments
{
    public const TYPES = [
        'certificate' => ['en' => 'Certificate', 'de' => 'Zertifikat'],
        'degree' => ['en' => 'Degree', 'de' => 'Abschluss'],
        'transcript' => ['en' => 'Transcript', 'de' => 'Transcript'],
        'work_certificate' => ['en' => 'Work Certificate', 'de' => 'Arbeitszeugnis'],
        'reference' => ['en' => 'Reference', 'de' => 'Referenz'],
        'other' => ['en' => 'Other', 'de' => 'Sonstiges'],
    ];

    public const PACKAGE_SLOTS = [
        'resume' => ['en' => 'Resume', 'de' => 'Lebenslauf'],
        'cover' => ['en' => 'Cover Letter', 'de' => 'Anschreiben'],
        'certificate' => ['en' => 'Certificates', 'de' => 'Zertifikate'],
        'degree' => ['en' => 'Degree', 'de' => 'Abschluss'],
        'transcript' => ['en' => 'Transcript', 'de' => 'Transcript'],
        'work_certificate' => ['en' => 'Work Certificates', 'de' => 'Arbeitszeugnisse'],
        'reference' => ['en' => 'References', 'de' => 'Referenzen'],
        'other' => ['en' => 'Other Documents', 'de' => 'Weitere Dokumente'],
    ];

    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        self::$ready = true;
        $pdo = Db::pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_documents (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL,
              name VARCHAR(200) NOT NULL,
              doc_type VARCHAR(40) NOT NULL DEFAULT \'other\',
              description TEXT NULL,
              always_include TINYINT(1) NOT NULL DEFAULT 0,
              current_version_id INT UNSIGNED NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              KEY idx_user (user_id),
              KEY idx_user_type (user_id, doc_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_document_versions (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              document_id INT UNSIGNED NOT NULL,
              user_id INT UNSIGNED NOT NULL,
              version_no INT UNSIGNED NOT NULL DEFAULT 1,
              storage_path VARCHAR(500) NOT NULL,
              original_filename VARCHAR(255) NOT NULL DEFAULT \'\',
              mime_type VARCHAR(120) NOT NULL DEFAULT \'application/pdf\',
              file_size INT UNSIGNED NOT NULL DEFAULT 0,
              checksum CHAR(64) NOT NULL DEFAULT \'\',
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              KEY idx_doc (document_id),
              KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS application_documents (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              application_id INT UNSIGNED NOT NULL,
              user_id INT UNSIGNED NOT NULL,
              document_id INT UNSIGNED NOT NULL,
              document_version_id INT UNSIGNED NOT NULL,
              doc_type VARCHAR(40) NOT NULL DEFAULT \'other\',
              sort_order INT NOT NULL DEFAULT 0,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_app_doc (application_id, document_id),
              KEY idx_app (application_id),
              KEY idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $dir = self::storageRoot();
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }

    public static function typeLabel(string $type, string $lang = 'en'): string
    {
        $type = self::normalizeType($type);
        $isDe = str_starts_with(strtolower($lang), 'de');
        $row = self::TYPES[$type] ?? self::TYPES['other'];

        return $isDe ? $row['de'] : $row['en'];
    }

    public static function slotLabel(string $slot, string $lang = 'en'): string
    {
        $isDe = str_starts_with(strtolower($lang), 'de');
        $row = self::PACKAGE_SLOTS[$slot] ?? ['en' => $slot, 'de' => $slot];

        return $isDe ? $row['de'] : $row['en'];
    }

    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return isset(self::TYPES[$type]) ? $type : 'other';
    }

    public static function ui(string $key, string $lang = 'en'): string
    {
        $isDe = str_starts_with(strtolower($lang), 'de');
        $map = [
            'library' => ['en' => 'Application Documents', 'de' => 'Bewerbungsunterlagen'],
            'upload' => ['en' => 'Upload Document', 'de' => 'Dokument hochladen'],
            'package' => ['en' => 'Application Documents', 'de' => 'Bewerbungsunterlagen'],
            'generate' => ['en' => 'Generate PDF', 'de' => 'PDF erzeugen'],
            'generating' => ['en' => 'Generating application PDF…', 'de' => 'Bewerbungs-PDF wird erzeugt…'],
            'selected' => ['en' => 'Selected', 'de' => 'Ausgewählt'],
            'selected_n' => ['en' => '%d documents selected', 'de' => '%d Dokumente ausgewählt'],
            'reorder_hint' => ['en' => 'Drag to set PDF order', 'de' => 'Reihenfolge per Drag & Drop'],
            'export_package' => ['en' => 'Generate PDF', 'de' => 'PDF erzeugen'],
            'attach' => ['en' => 'Attach selected', 'de' => 'Ausgewählte anhängen'],
            'add' => ['en' => '+ Upload Document', 'de' => '+ Dokument hochladen'],
            'empty' => ['en' => 'No supporting documents yet', 'de' => 'Noch keine Unterlagen'],
            'empty_hint' => [
                'en' => 'Upload certificates, degrees, transcripts or other documents, then select them when preparing an application.',
                'de' => 'Laden Sie Zertifikate, Abschlüsse oder Transcripts hoch und wählen Sie sie bei der Bewerbung aus.',
            ],
            'always' => ['en' => 'Always include', 'de' => 'Immer anhängen'],
            'ready' => ['en' => 'Ready to apply', 'de' => 'Bereit zum Bewerben'],
            'preview' => ['en' => 'Preview', 'de' => 'Vorschau'],
            'download' => ['en' => 'Download', 'de' => 'Download'],
            'replace' => ['en' => 'Replace', 'de' => 'Ersetzen'],
            'delete' => ['en' => 'Delete', 'de' => 'Löschen'],
            'detach' => ['en' => 'Detach', 'de' => 'Entfernen'],
            'filter_all' => ['en' => 'All', 'de' => 'Alle'],
            'filter_certs' => ['en' => 'Certificates', 'de' => 'Zertifikate'],
            'filter_edu' => ['en' => 'Education', 'de' => 'Ausbildung'],
            'filter_work' => ['en' => 'Work', 'de' => 'Arbeit'],
            'filter_other' => ['en' => 'Other', 'de' => 'Sonstiges'],
            'select_hint' => [
                'en' => 'Select documents → Generate PDF',
                'de' => 'Dokumente auswählen → PDF erzeugen',
            ],
            'cancel' => ['en' => 'Cancel', 'de' => 'Abbrechen'],
        ];
        $row = $map[$key] ?? ['en' => $key, 'de' => $key];

        return $isDe ? $row['de'] : $row['en'];
    }

    /** Master / Main resume version id for the signed-in user. */
    public static function baseResumeId(): ?int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id FROM resume_versions WHERE user_id = ? AND is_base = 1 ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([self::uid()]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /** Master / Main cover letter id for the signed-in user. */
    public static function baseCoverId(): ?int
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id FROM cover_letters WHERE user_id = ? AND is_base = 1 ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([self::uid()]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Filter group for UI chips: certificates | education | work | other
     */
    public static function filterGroup(string $docType): string
    {
        return match (self::normalizeType($docType)) {
            'certificate' => 'certificates',
            'degree', 'transcript' => 'education',
            'work_certificate' => 'work',
            default => 'other',
        };
    }

    private static function uid(): int
    {
        $id = Auth::id();
        if ($id <= 0) {
            throw new RuntimeException('Not signed in.');
        }

        return $id;
    }

    public static function storageRoot(): string
    {
        return dirname(__DIR__) . '/storage/user_docs';
    }

    public static function userDir(int $userId): string
    {
        $dir = self::storageRoot() . '/' . $userId;
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        return $dir;
    }

    /** Absolute path on disk for a relative storage_path. */
    public static function absolutePath(string $storagePath): string
    {
        $storagePath = ltrim(str_replace('\\', '/', $storagePath), '/');
        if (!preg_match('#^\d+/[A-Za-z0-9._-]+$#', $storagePath)) {
            throw new InvalidArgumentException('Invalid storage path.');
        }
        $full = self::storageRoot() . '/' . $storagePath;
        $realRoot = realpath(self::storageRoot());
        $realFile = realpath($full);
        if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('File not found.');
        }

        return $realFile;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listDocuments(
        ?string $type = null,
        string $q = '',
        string $sort = 'newest'
    ): array {
        self::ensureSchema();
        $uid = self::uid();
        $sql = 'SELECT d.*, v.mime_type, v.file_size, v.original_filename, v.version_no, v.storage_path, v.id AS version_id
                FROM user_documents d
                LEFT JOIN user_document_versions v ON v.id = d.current_version_id
                WHERE d.user_id = ?';
        $params = [$uid];
        if ($type !== null && $type !== '' && $type !== 'all') {
            $sql .= ' AND d.doc_type = ?';
            $params[] = self::normalizeType($type);
        }
        $q = trim($q);
        if ($q !== '') {
            $sql .= ' AND (d.name LIKE ? OR d.description LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= match ($sort) {
            'oldest' => ' ORDER BY d.created_at ASC, d.id ASC',
            'name' => ' ORDER BY d.name ASC, d.id DESC',
            'type' => ' ORDER BY d.doc_type ASC, d.name ASC',
            default => ' ORDER BY d.updated_at DESC, d.id DESC',
        };
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function get(int $documentId): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT d.*, v.mime_type, v.file_size, v.original_filename, v.version_no, v.storage_path, v.id AS version_id, v.created_at AS version_created_at
             FROM user_documents d
             LEFT JOIN user_document_versions v ON v.id = d.current_version_id
             WHERE d.id = ? AND d.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$documentId, self::uid()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function getVersion(int $versionId): ?array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM user_document_versions WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$versionId, self::uid()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int,type?:string} $file
     */
    public static function upload(
        string $name,
        string $type,
        array $file,
        string $description = '',
        bool $alwaysInclude = false,
        ?int $replaceDocumentId = null
    ): int {
        self::ensureSchema();
        $uid = self::uid();
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Document name is required.');
        }
        $type = self::normalizeType($type);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload failed.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Invalid upload.');
        }
        if ($size <= 0 || $size > 12 * 1024 * 1024) {
            throw new InvalidArgumentException('File must be under 12MB.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmp) ?: '');
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Only PDF, JPG, or PNG files are allowed.');
        }
        $ext = $allowed[$mime];
        $original = basename((string) ($file['name'] ?? ('document.' . $ext)));
        $checksum = hash_file('sha256', $tmp) ?: '';

        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $documentId = $replaceDocumentId;
            $versionNo = 1;
            if ($documentId !== null && $documentId > 0) {
                $existing = self::get($documentId);
                if ($existing === null) {
                    throw new InvalidArgumentException('Document not found.');
                }
                $versionNo = ((int) ($existing['version_no'] ?? 0)) + 1;
                $pdo->prepare(
                    'UPDATE user_documents SET name = ?, doc_type = ?, description = ?, always_include = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ? AND user_id = ?'
                )->execute([
                    $name,
                    $type,
                    $description !== '' ? $description : null,
                    $alwaysInclude ? 1 : 0,
                    $documentId,
                    $uid,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO user_documents (user_id, name, doc_type, description, always_include)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $uid,
                    $name,
                    $type,
                    $description !== '' ? $description : null,
                    $alwaysInclude ? 1 : 0,
                ]);
                $documentId = (int) $pdo->lastInsertId();
            }

            $stored = 'doc_' . $documentId . '_v' . $versionNo . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $rel = $uid . '/' . $stored;
            $dest = self::userDir($uid) . '/' . $stored;
            if (!move_uploaded_file($tmp, $dest)) {
                throw new RuntimeException('Could not store uploaded file.');
            }
            @chmod($dest, 0660);

            // Convert images to PDF when possible so packages stay PDF-only.
            if ($ext !== 'pdf') {
                $pdfRel = self::convertImageToPdf($dest, $uid, $documentId, $versionNo);
                if ($pdfRel !== null) {
                    @unlink($dest);
                    $rel = $pdfRel;
                    $mime = 'application/pdf';
                    $dest = self::absolutePath($rel);
                    $size = (int) filesize($dest);
                    $checksum = hash_file('sha256', $dest) ?: $checksum;
                }
            }

            $pdo->prepare(
                'INSERT INTO user_document_versions
                 (document_id, user_id, version_no, storage_path, original_filename, mime_type, file_size, checksum)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $documentId,
                $uid,
                $versionNo,
                $rel,
                $original,
                $mime,
                $size,
                $checksum,
            ]);
            $versionId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'UPDATE user_documents SET current_version_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?'
            )->execute([$versionId, $documentId, $uid]);
            $pdo->commit();

            return $documentId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return string|null relative storage path of new PDF */
    private static function convertImageToPdf(string $imagePath, int $userId, int $documentId, int $versionNo): ?string
    {
        $gs = trim((string) shell_exec('command -v gs 2>/dev/null'));
        if ($gs === '') {
            return null;
        }
        $stored = 'doc_' . $documentId . '_v' . $versionNo . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $rel = $userId . '/' . $stored;
        $out = self::userDir($userId) . '/' . $stored;
        $cmd = sprintf(
            '%s -dBATCH -dNOPAUSE -dSAFER -q -sDEVICE=pdfwrite -sOutputFile=%s %s 2>/dev/null',
            escapeshellcmd($gs),
            escapeshellarg($out),
            escapeshellarg($imagePath)
        );
        // Ghostscript does not reliably ingest JPEG as PDF pages; use ImageMagick convert if present,
        // else keep original image (download still works; package merge may skip).
        $convert = trim((string) shell_exec('command -v convert 2>/dev/null'));
        if ($convert !== '') {
            $cmd = sprintf(
                '%s %s -auto-orient -quality 90 %s 2>/dev/null',
                escapeshellcmd($convert),
                escapeshellarg($imagePath),
                escapeshellarg($out)
            );
            exec($cmd, $o, $code);
            if ($code === 0 && is_file($out) && filesize($out) > 0) {
                @chmod($out, 0660);

                return $rel;
            }
        }

        return null;
    }

    public static function updateMeta(int $documentId, string $name, string $type, string $description = '', bool $alwaysInclude = false): void
    {
        self::ensureSchema();
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Document name is required.');
        }
        $stmt = Db::pdo()->prepare(
            'UPDATE user_documents SET name = ?, doc_type = ?, description = ?, always_include = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $name,
            self::normalizeType($type),
            $description !== '' ? $description : null,
            $alwaysInclude ? 1 : 0,
            $documentId,
            self::uid(),
        ]);
        if ($stmt->rowCount() === 0 && self::get($documentId) === null) {
            throw new InvalidArgumentException('Document not found.');
        }
    }

    public static function delete(int $documentId): void
    {
        self::ensureSchema();
        $uid = self::uid();
        $doc = self::get($documentId);
        if ($doc === null) {
            return;
        }
        // Keep files referenced by historical applications; only detach library entry when unused.
        $inUse = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM application_documents WHERE document_id = ? AND user_id = ?'
        );
        $inUse->execute([$documentId, $uid]);
        if ((int) $inUse->fetchColumn() > 0) {
            // Soft-hide from library by renaming? Prefer hard block.
            throw new InvalidArgumentException('Document is attached to applications. Detach it first, or keep it for history.');
        }
        $versions = Db::pdo()->prepare(
            'SELECT storage_path FROM user_document_versions WHERE document_id = ? AND user_id = ?'
        );
        $versions->execute([$documentId, $uid]);
        foreach ($versions->fetchAll(PDO::FETCH_ASSOC) as $v) {
            try {
                $path = self::absolutePath((string) $v['storage_path']);
                @unlink($path);
            } catch (\Throwable) {
            }
        }
        Db::pdo()->prepare('DELETE FROM user_document_versions WHERE document_id = ? AND user_id = ?')
            ->execute([$documentId, $uid]);
        Db::pdo()->prepare('DELETE FROM user_documents WHERE id = ? AND user_id = ?')
            ->execute([$documentId, $uid]);
    }

    public static function attach(int $applicationId, int $documentId, ?int $sortOrder = null): void
    {
        self::ensureSchema();
        $uid = self::uid();
        $app = Db::pdo()->prepare('SELECT id FROM applications WHERE id = ? AND user_id = ? LIMIT 1');
        $app->execute([$applicationId, $uid]);
        if ($app->fetch() === false) {
            throw new InvalidArgumentException('Application not found.');
        }
        $doc = self::get($documentId);
        if ($doc === null || (int) ($doc['version_id'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Document not found.');
        }
        if ($sortOrder === null) {
            $max = Db::pdo()->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM application_documents WHERE application_id = ? AND user_id = ?'
            );
            $max->execute([$applicationId, $uid]);
            $sortOrder = (int) $max->fetchColumn() + 10;
        }
        Db::pdo()->prepare(
            'INSERT INTO application_documents (application_id, user_id, document_id, document_version_id, doc_type, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE document_version_id = VALUES(document_version_id), doc_type = VALUES(doc_type), sort_order = VALUES(sort_order)'
        )->execute([
            $applicationId,
            $uid,
            $documentId,
            (int) $doc['version_id'],
            (string) $doc['doc_type'],
            $sortOrder,
        ]);
    }

    public static function detach(int $applicationId, int $documentId): void
    {
        self::ensureSchema();
        Db::pdo()->prepare(
            'DELETE FROM application_documents WHERE application_id = ? AND document_id = ? AND user_id = ?'
        )->execute([$applicationId, $documentId, self::uid()]);
    }

    /**
     * @param list<int> $orderedDocumentIds
     */
    public static function setOrder(int $applicationId, array $orderedDocumentIds): void
    {
        self::ensureSchema();
        $uid = self::uid();
        $order = 10;
        $stmt = Db::pdo()->prepare(
            'UPDATE application_documents SET sort_order = ? WHERE application_id = ? AND document_id = ? AND user_id = ?'
        );
        foreach ($orderedDocumentIds as $docId) {
            $docId = (int) $docId;
            if ($docId <= 0) {
                continue;
            }
            $stmt->execute([$order, $applicationId, $docId, $uid]);
            $order += 10;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forApplication(int $applicationId): array
    {
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'SELECT ad.*, d.name, d.description, d.always_include,
                    v.mime_type, v.file_size, v.original_filename, v.version_no, v.storage_path
             FROM application_documents ad
             INNER JOIN user_documents d ON d.id = ad.document_id AND d.user_id = ad.user_id
             INNER JOIN user_document_versions v ON v.id = ad.document_version_id AND v.user_id = ad.user_id
             WHERE ad.application_id = ? AND ad.user_id = ?
             ORDER BY ad.sort_order ASC, ad.id ASC'
        );
        $stmt->execute([$applicationId, self::uid()]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Attach always_include docs that are not yet attached. */
    public static function attachAlwaysInclude(int $applicationId): void
    {
        foreach (self::listDocuments() as $doc) {
            if ((int) ($doc['always_include'] ?? 0) !== 1) {
                continue;
            }
            try {
                self::attach($applicationId, (int) $doc['id']);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Freeze current versions on the application (re-pin to current library versions).
     * Call when marking applied so history stays stable even if attach used current.
     */
    public static function freezeForApplication(int $applicationId): void
    {
        self::ensureSchema();
        $uid = self::uid();
        $rows = self::forApplication($applicationId);
        $upd = Db::pdo()->prepare(
            'UPDATE application_documents SET document_version_id = ?
             WHERE application_id = ? AND document_id = ? AND user_id = ?'
        );
        foreach ($rows as $row) {
            $doc = self::get((int) $row['document_id']);
            if ($doc === null) {
                continue;
            }
            // Keep already-pinned version (do not upgrade on freeze).
            // Freeze means: ensure version_id is set and immutable going forward.
            if ((int) ($row['document_version_id'] ?? 0) > 0) {
                continue;
            }
            $upd->execute([(int) $doc['version_id'], $applicationId, (int) $row['document_id'], $uid]);
        }
    }

    /**
     * @return array{resume: bool, cover: bool, attachments: int, ready: bool, missing: list<string>}
     */
    public static function packageStatus(array $application): array
    {
        $resume = (int) ($application['resume_version_id'] ?? 0) > 0;
        $cover = (int) ($application['cover_letter_id'] ?? 0) > 0;
        $appId = (int) ($application['id'] ?? 0);
        $attachments = $appId > 0 ? count(self::forApplication($appId)) : 0;
        $missing = [];
        if (!$resume) {
            $missing[] = 'Resume';
        }
        if (!$cover) {
            $missing[] = 'Cover Letter';
        }

        return [
            'resume' => $resume,
            'cover' => $cover,
            'attachments' => $attachments,
            'ready' => $resume && $cover,
            'missing' => $missing,
        ];
    }

    /**
     * Replace application attachments with the selected library documents (ordered).
     * Pins current versions for history when the application is later marked applied.
     *
     * @param list<int> $documentIdsInOrder
     */
    public static function syncApplicationAttachments(int $applicationId, array $documentIdsInOrder): void
    {
        self::ensureSchema();
        $uid = self::uid();
        $stmt = Db::pdo()->prepare('SELECT id FROM applications WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$applicationId, $uid]);
        if ($stmt->fetchColumn() === false) {
            throw new InvalidArgumentException('Application not found.');
        }

        $wanted = [];
        foreach ($documentIdsInOrder as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $wanted[$id] = true;
            }
        }
        $wantedIds = array_keys($wanted);

        $existing = self::forApplication($applicationId);
        foreach ($existing as $row) {
            $did = (int) $row['document_id'];
            if (!isset($wanted[$did])) {
                self::detach($applicationId, $did);
            }
        }

        $order = 10;
        foreach ($wantedIds as $docId) {
            self::attach($applicationId, $docId, $order);
            $order += 10;
        }
    }

    /**
     * Build a combined PDF from an explicit selection (order preserved).
     *
     * $orderKeys entries: "resume", "cover", or "doc:{id}"
     *
     * @param list<string> $orderKeys
     * @param array{resume_version_id?:int,cover_letter_id?:int,application_id?:int,company?:string} $context
     */
    public static function exportSelection(array $orderKeys, array $context = []): string
    {
        self::ensureSchema();
        $uid = self::uid();
        $resumeId = (int) ($context['resume_version_id'] ?? 0);
        $coverId = (int) ($context['cover_letter_id'] ?? 0);
        $applicationId = (int) ($context['application_id'] ?? 0);

        if ($resumeId > 0) {
            $chk = Db::pdo()->prepare('SELECT id FROM resume_versions WHERE id = ? AND user_id = ? LIMIT 1');
            $chk->execute([$resumeId, $uid]);
            if ($chk->fetchColumn() === false) {
                throw new InvalidArgumentException('Resume not found.');
            }
        }
        if ($coverId > 0) {
            $chk = Db::pdo()->prepare('SELECT id FROM cover_letters WHERE id = ? AND user_id = ? LIMIT 1');
            $chk->execute([$coverId, $uid]);
            if ($chk->fetchColumn() === false) {
                throw new InvalidArgumentException('Cover letter not found.');
            }
        }

        $docIds = [];
        foreach ($orderKeys as $key) {
            $key = trim((string) $key);
            if (preg_match('/^doc:(\d+)$/', $key, $m)) {
                $docIds[] = (int) $m[1];
            }
        }
        if ($applicationId > 0 && !empty($context['sync_attachments'])) {
            self::syncApplicationAttachments($applicationId, $docIds);
        }

        $parts = [];
        $tmpdir = sys_get_temp_dir() . '/mnk-pkg-' . bin2hex(random_bytes(4));
        mkdir($tmpdir, 0700, true);

        try {
            foreach ($orderKeys as $key) {
                $key = trim((string) $key);
                if ($key === 'resume') {
                    if ($resumeId <= 0) {
                        throw new InvalidArgumentException('Resume was selected but no resume version is linked.');
                    }
                    $parts[] = PdfExport::generate('resume', [
                        'version' => $resumeId,
                        'theme' => App::resolveTheme(null),
                        'lang' => App::resolveDocumentLang(),
                    ]);
                    continue;
                }
                if ($key === 'cover') {
                    if ($coverId <= 0) {
                        throw new InvalidArgumentException('Cover letter was selected but none is linked.');
                    }
                    $parts[] = PdfExport::generate('cover', [
                        'id' => $coverId,
                        'theme' => App::resolveTheme(null),
                        'lang' => App::resolveDocumentLang(),
                    ]);
                    continue;
                }
                if (preg_match('/^docver:(\d+)$/', $key, $m)) {
                    $ver = self::getVersion((int) $m[1]);
                    if ($ver === null) {
                        throw new InvalidArgumentException('Document version not found.');
                    }
                    $path = self::absolutePath((string) $ver['storage_path']);
                    $mime = (string) ($ver['mime_type'] ?? '');
                    if ($mime !== 'application/pdf' && !str_ends_with(strtolower($path), '.pdf')) {
                        throw new RuntimeException('PDF conversion unavailable for a selected document. Replace with a PDF.');
                    }
                    $parts[] = $path;
                    continue;
                }
                if (preg_match('/^doc:(\d+)$/', $key, $m)) {
                    $doc = self::get((int) $m[1]);
                    if ($doc === null) {
                        throw new InvalidArgumentException('Document not found.');
                    }
                    $path = self::absolutePath((string) $doc['storage_path']);
                    $mime = (string) ($doc['mime_type'] ?? '');
                    if ($mime !== 'application/pdf' && !str_ends_with(strtolower($path), '.pdf')) {
                        throw new RuntimeException(
                            'PDF conversion unavailable for “' . (string) $doc['name'] . '”. Replace with a PDF.'
                        );
                    }
                    $parts[] = $path;
                }
            }

            if ($parts === []) {
                throw new InvalidArgumentException('Select at least one document.');
            }

            $tag = $applicationId > 0 ? (string) $applicationId : 'sel';
            $out = dirname(__DIR__) . '/storage/pdfs/package-' . $tag . '-' . bin2hex(random_bytes(4)) . '.pdf';
            self::mergePdfs($parts, $out);

            return $out;
        } finally {
            foreach (glob($tmpdir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpdir);
        }
    }

    /**
     * Build combined application package PDF from saved attachments + linked resume/cover.
     * Prefer exportSelection() for the select → Generate PDF UI.
     */
    public static function exportPackage(int $applicationId): string
    {
        self::ensureSchema();
        $uid = self::uid();
        $stmt = Db::pdo()->prepare('SELECT * FROM applications WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$applicationId, $uid]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($app === false) {
            throw new InvalidArgumentException('Application not found.');
        }

        $order = [];
        if ((int) ($app['resume_version_id'] ?? 0) > 0) {
            $order[] = 'resume';
        }
        if ((int) ($app['cover_letter_id'] ?? 0) > 0) {
            $order[] = 'cover';
        }
        foreach (self::forApplication($applicationId) as $att) {
            $order[] = 'docver:' . (int) $att['document_version_id'];
        }

        return self::exportSelection($order, [
            'resume_version_id' => (int) ($app['resume_version_id'] ?? 0),
            'cover_letter_id' => (int) ($app['cover_letter_id'] ?? 0),
            'application_id' => $applicationId,
        ]);
    }

    public static function packageFilename(array $application, string $personName): string
    {
        $slug = PdfExport::personSlug($personName);
        $company = trim((string) ($application['company'] ?? ''));
        if ($company !== '') {
            $c = PdfExport::personSlug($company);

            return $slug . '_Application_' . $c . '.pdf';
        }

        return $slug . '_Application.pdf';
    }

    /** @param list<string> $absolutePdfPaths */
    public static function mergePdfs(array $absolutePdfPaths, string $outfile): void
    {
        $paths = [];
        foreach ($absolutePdfPaths as $p) {
            if (!is_file($p)) {
                throw new RuntimeException('Missing PDF part: ' . basename($p));
            }
            $paths[] = $p;
        }
        if (count($paths) === 1) {
            if (!copy($paths[0], $outfile)) {
                throw new RuntimeException('Could not write package PDF.');
            }

            return;
        }

        $pdfunite = trim((string) shell_exec('command -v pdfunite 2>/dev/null'));
        if ($pdfunite !== '') {
            $cmd = escapeshellcmd($pdfunite);
            foreach ($paths as $p) {
                $cmd .= ' ' . escapeshellarg($p);
            }
            $cmd .= ' ' . escapeshellarg($outfile) . ' 2>&1';
            exec($cmd, $out, $code);
            if ($code === 0 && is_file($outfile) && filesize($outfile) > 0) {
                return;
            }
        }

        $gs = trim((string) shell_exec('command -v gs 2>/dev/null'));
        if ($gs === '') {
            throw new RuntimeException('No PDF merge tool available (pdfunite/gs).');
        }
        $cmd = sprintf(
            '%s -dBATCH -dNOPAUSE -dSAFER -q -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=%s',
            escapeshellcmd($gs),
            escapeshellarg($outfile)
        );
        foreach ($paths as $p) {
            $cmd .= ' ' . escapeshellarg($p);
        }
        $cmd .= ' 2>&1';
        exec($cmd, $out, $code);
        if ($code !== 0 || !is_file($outfile) || filesize($outfile) <= 0) {
            throw new RuntimeException('PDF merge failed: ' . implode(' ', $out));
        }
    }

    public static function downloadUrl(int $documentId, bool $inline = false): string
    {
        $q = ['id' => $documentId];
        if ($inline) {
            $q['inline'] = '1';
        }

        return '/app-doc-file?' . http_build_query($q);
    }

    public static function versionDownloadUrl(int $versionId, bool $inline = false): string
    {
        $q = ['version' => $versionId];
        if ($inline) {
            $q['inline'] = '1';
        }

        return '/app-doc-file?' . http_build_query($q);
    }
}
