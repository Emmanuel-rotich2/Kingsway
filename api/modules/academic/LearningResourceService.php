<?php

namespace App\API\Modules\academic;

use App\API\Services\ReadReplicaService;
use App\API\Services\StaffRecordsService;
use App\API\Services\TeacherCurriculumScopeService;
use PDO;
use RuntimeException;

/**
 * Teaching materials and past papers (revision papers with marking schemes).
 *
 *  Upload: teachers (for their own learning areas) and academic leadership
 *          add a file (documents, presentations, spreadsheets, text, images,
 *          video, audio, zip) or a link (YouTube, Drive, a website).
 *  Publish: the School Administrator publishes each item as
 *          internal  -> every staff user can open it, or
 *          public    -> also shown on the parent portal for that grade,
 *          or rejects it with a note, or unpublishes it again.
 *  See:    staff see published items plus their own uploads; the School
 *          Administrator sees everything; parents see published public items.
 * Files live under UPLOAD_PATH/learning_resources and are served only through
 * short-lived signed links after the viewer is authorised.
 */
final class LearningResourceService
{
    public const PUBLISHER_ROLES = [4];
    private const UPLOADER_ROLES = [3, 4, 5, 6, 7, 8, 9];
    private const LEADERSHIP_ROLES = [3, 4, 5, 6];
    private const MAX_BYTES = 40 * 1024 * 1024;

    /** extension => [media kind, accepted MIME types] */
    private const TYPES = [
        'pdf' => ['document', ['application/pdf']],
        'doc' => ['document', ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream']],
        'docx' => ['document', ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream']],
        'odt' => ['document', ['application/vnd.oasis.opendocument.text', 'application/zip']],
        'rtf' => ['document', ['text/rtf', 'application/rtf', 'text/plain']],
        'ppt' => ['presentation', ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream']],
        'pptx' => ['presentation', ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream']],
        'odp' => ['presentation', ['application/vnd.oasis.opendocument.presentation', 'application/zip']],
        'xls' => ['spreadsheet', ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream']],
        'xlsx' => ['spreadsheet', ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream']],
        'ods' => ['spreadsheet', ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip']],
        'csv' => ['spreadsheet', ['text/csv', 'text/plain', 'application/csv']],
        'txt' => ['text', ['text/plain']],
        'md' => ['text', ['text/plain', 'text/markdown']],
        'jpg' => ['image', ['image/jpeg']], 'jpeg' => ['image', ['image/jpeg']],
        'png' => ['image', ['image/png']], 'gif' => ['image', ['image/gif']], 'webp' => ['image', ['image/webp']],
        'mp4' => ['video', ['video/mp4', 'application/mp4']], 'm4v' => ['video', ['video/mp4', 'video/x-m4v']],
        'webm' => ['video', ['video/webm', 'audio/webm']], 'mov' => ['video', ['video/quicktime']],
        'mp3' => ['audio', ['audio/mpeg', 'audio/mp3', 'application/octet-stream']],
        'wav' => ['audio', ['audio/wav', 'audio/x-wav', 'audio/vnd.wave']],
        'm4a' => ['audio', ['audio/mp4', 'audio/x-m4a', 'video/mp4']],
        'ogg' => ['audio', ['audio/ogg', 'video/ogg', 'application/ogg']],
        'aac' => ['audio', ['audio/aac', 'audio/x-aac', 'audio/x-hx-aac-adts']],
        'zip' => ['archive', ['application/zip', 'application/x-zip-compressed']],
    ];
    private const EXAM_TYPES = ['opener', 'mid_term', 'end_term', 'mock', 'internal', 'kpsea', 'kjsea', 'keya', 'other'];

    public function __construct(private PDO $db) {}

    public static function acceptedExtensions(): array
    {
        return array_keys(self::TYPES);
    }

    // ------------------------------------------------------------------ list
    public function list(string $kind, array $viewer, array $f): array
    {
        [$table, $statusCol, $published] = $this->tableFor($kind);
        $where = [];
        $params = [];
        $isPublisher = $this->isPublisher($viewer);
        if (!$isPublisher) {
            $where[] = "({$statusCol} = ? OR uploaded_by = ?)";
            $params[] = $published;
            $params[] = (int) $viewer['user_id'];
        }
        $status = (string) ($f['status'] ?? '');
        if ($status === 'mine') { $where[] = 'uploaded_by = ?'; $params[] = (int) $viewer['user_id']; }
        elseif ($status === 'published') { $where[] = "{$statusCol} = ?"; $params[] = $published; }
        elseif ($status === 'pending') { $where[] = "{$statusCol} = 'pending'"; }
        elseif ($status === 'rejected') { $where[] = "{$statusCol} = 'rejected'"; }
        if (in_array($f['access_scope'] ?? '', ['internal', 'public'], true)) { $where[] = 'access_scope = ?'; $params[] = $f['access_scope']; }
        if (!empty($f['learning_area_id'])) { $where[] = 'learning_area_id = ?'; $params[] = (int) $f['learning_area_id']; }
        if (!empty($f['grade_level'])) { $where[] = 'grade_level = ?'; $params[] = (string) $f['grade_level']; }
        if (!empty($f['academic_year_id'])) { $where[] = 'academic_year_id = ?'; $params[] = (int) $f['academic_year_id']; }
        if ($kind === 'material' && !empty($f['academic_year_term_id'])) { $where[] = 'academic_year_term_id = ?'; $params[] = (int) $f['academic_year_term_id']; }
        if ($kind === 'past_paper') {
            if (!empty($f['exam_year'])) { $where[] = 'exam_year = ?'; $params[] = (int) $f['exam_year']; }
            if (!empty($f['exam_type'])) { $where[] = 'exam_type = ?'; $params[] = (string) $f['exam_type']; }
            if (!empty($f['term_number'])) { $where[] = 'term_id = ?'; $params[] = (int) $f['term_number']; }
        }
        if (!empty($f['media_kind'])) {
            if ($kind === 'material') { $where[] = 'media_kind = ?'; $params[] = (string) $f['media_kind']; }
        }
        if (!empty($f['q'])) { $where[] = '(title LIKE ? OR description LIKE ?)'; $params[] = '%' . $f['q'] . '%'; $params[] = '%' . $f['q'] . '%'; }
        $sql = "SELECT * FROM {$table}" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 500';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->decorate($kind, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $viewer);
    }

    /** Published public items for a parent, for one grade (or every grade). */
    public function listPublic(?string $gradeLevel): array
    {
        $out = [];
        foreach (['material', 'past_paper'] as $kind) {
            [$table, $statusCol, $published] = $this->tableFor($kind);
            $sql = "SELECT * FROM {$table} WHERE {$statusCol} = ? AND access_scope = 'public'";
            $params = [$published];
            if ($gradeLevel) { $sql .= ' AND (grade_level IS NULL OR grade_level = \'\' OR grade_level = ?)'; $params[] = $gradeLevel; }
            $stmt = $this->db->prepare($sql . ' ORDER BY created_at DESC LIMIT 300');
            $stmt->execute($params);
            foreach ($this->decorate($kind, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], null) as $row) {
                $out[] = array_intersect_key($row, array_flip([
                    'id', 'kind', 'title', 'description', 'media_kind', 'source_kind', 'learning_area_name', 'grade_level',
                    'exam_year', 'exam_type', 'term_number', 'has_marking_scheme', 'file_name', 'file_size', 'published_at',
                ]));
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------- create
    public function create(string $kind, array $viewer, array $data, ?array $file, ?array $scheme = null): array
    {
        if (!array_intersect(self::UPLOADER_ROLES, $viewer['role_ids'])) {
            throw new RuntimeException('Your role cannot add learning resources.', 403);
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) throw new RuntimeException('Give the resource a title (up to 255 characters).', 400);
        $areaId = (int) ($data['learning_area_id'] ?? 0);
        $this->assertAreaInScope($viewer, $areaId);
        $grade = mb_substr(trim((string) ($data['grade_level'] ?? '')), 0, 40) ?: null;

        $link = trim((string) ($data['external_url'] ?? ''));
        $stored = null;
        if ($link !== '') {
            $link = $this->validLink($link);
        } else {
            $stored = $this->storeUpload($file);
        }
        $staffId = StaffRecordsService::staffIdForUserId($this->db, (int) $viewer['user_id']) ?: null;
        $yearId = (int) ($data['academic_year_id'] ?? 0) ?: null;
        $description = mb_substr(trim((string) ($data['description'] ?? '')), 0, 5000);

        if ($kind === 'material') {
            $stmt = $this->db->prepare(
                "INSERT INTO teaching_materials
                    (title, description, learning_area_id, strand_id, sub_strand_id, grade_level, teacher_id,
                     academic_year_id, academic_year_term_id, resource_type, source_kind, external_url, media_kind,
                     file_path, file_name, file_type, file_size, access_scope, status, uploaded_by, uploaded_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'internal', 'pending', ?, NOW())"
            );
            $stmt->execute([
                $title, $description, $areaId ?: null, (int) ($data['strand_id'] ?? 0) ?: null, (int) ($data['sub_strand_id'] ?? 0) ?: null,
                $grade, $staffId, $yearId, (int) ($data['academic_year_term_id'] ?? 0) ?: null,
                mb_substr(trim((string) ($data['resource_type'] ?? 'notes')), 0, 50),
                $link !== '' ? 'link' : 'file', $link !== '' ? $link : null, $link !== '' ? 'link' : $stored['media_kind'],
                $stored['path'] ?? null, $stored['name'] ?? null, $stored['mime'] ?? null, $stored['size'] ?? null,
                (int) $viewer['user_id'],
            ]);
        } else {
            $examYear = (int) ($data['exam_year'] ?? date('Y'));
            if ($examYear < 2000 || $examYear > (int) date('Y') + 1) throw new RuntimeException('Enter the year the paper was set.', 400);
            $type = (string) ($data['exam_type'] ?? 'end_term');
            if (!in_array($type, self::EXAM_TYPES, true)) $type = 'other';
            $schemeStored = ($scheme && ($scheme['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $this->storeUpload($scheme) : null;
            $stmt = $this->db->prepare(
                "INSERT INTO past_papers
                    (title, description, subject_id, learning_area_id, grade_level, exam_year, exam_type, term_id, academic_year_id,
                     file_path, file_name, file_type, file_size, source_kind, external_url, marking_scheme_path, marking_scheme_name,
                     uploaded_by, teacher_id, status, publish_status, access_scope)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'pending', 'internal')"
            );
            $stmt->execute([
                $title, $description, $areaId ?: null, $areaId ?: null, $grade, $examYear, $type,
                (int) ($data['term_number'] ?? 0) ?: null, $yearId,
                $stored['path'] ?? null, $stored['name'] ?? null, $stored['mime'] ?? null, $stored['size'] ?? null,
                $link !== '' ? 'link' : 'file', $link !== '' ? $link : null,
                $schemeStored['path'] ?? null, $schemeStored['name'] ?? null,
                (int) $viewer['user_id'], $staffId,
            ]);
        }
        return ['id' => (int) $this->db->lastInsertId(), 'kind' => $kind];
    }

    // ---------------------------------------------------------------- update
    public function update(string $kind, int $id, array $viewer, array $data): void
    {
        $row = $this->row($kind, $id);
        [, $statusCol, $published] = $this->tableFor($kind);
        if (!$this->isPublisher($viewer) && ((int) $row['uploaded_by'] !== (int) $viewer['user_id'] || $row[$statusCol] === $published)) {
            throw new RuntimeException('Only the uploader can edit an unpublished resource; the School Administrator can edit any.', 403);
        }
        $fields = ['title' => 255, 'description' => 5000, 'grade_level' => 40];
        $sets = [];
        $params = [];
        foreach ($fields as $col => $max) {
            if (array_key_exists($col, $data)) { $sets[] = "{$col} = ?"; $params[] = mb_substr(trim((string) $data[$col]), 0, $max) ?: null; }
        }
        if (array_key_exists('learning_area_id', $data)) {
            $this->assertAreaInScope($viewer, (int) $data['learning_area_id']);
            $sets[] = 'learning_area_id = ?'; $params[] = (int) $data['learning_area_id'] ?: null;
        }
        if ($kind === 'past_paper') {
            if (isset($data['exam_year'])) { $sets[] = 'exam_year = ?'; $params[] = (int) $data['exam_year']; }
            if (isset($data['exam_type']) && in_array($data['exam_type'], self::EXAM_TYPES, true)) { $sets[] = 'exam_type = ?'; $params[] = $data['exam_type']; }
            if (array_key_exists('term_number', $data)) { $sets[] = 'term_id = ?'; $params[] = (int) $data['term_number'] ?: null; }
        }
        if (!$sets) return;
        $params[] = $id;
        [$table] = $this->tableFor($kind);
        $this->db->prepare("UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    // --------------------------------------------------------------- publish
    /** action: publish (scope internal|public) | unpublish | reject */
    public function decide(string $kind, int $id, array $viewer, string $action, string $scope = 'internal', string $note = ''): void
    {
        if (!$this->isPublisher($viewer)) throw new RuntimeException('Only the School Administrator publishes learning resources.', 403);
        $this->row($kind, $id);
        [$table, $statusCol, $published] = $this->tableFor($kind);
        $note = mb_substr(trim($note), 0, 500) ?: null;
        if ($action === 'publish') {
            $scope = $scope === 'public' ? 'public' : 'internal';
            $this->db->prepare("UPDATE {$table} SET {$statusCol} = ?, access_scope = ?, published_by = ?, published_at = NOW(), review_note = ? WHERE id = ?")
                ->execute([$published, $scope, (int) $viewer['user_id'], $note, $id]);
        } elseif ($action === 'unpublish') {
            $this->db->prepare("UPDATE {$table} SET {$statusCol} = 'pending', published_at = NULL, review_note = ? WHERE id = ?")->execute([$note, $id]);
        } elseif ($action === 'reject') {
            $this->db->prepare("UPDATE {$table} SET {$statusCol} = 'rejected', published_at = NULL, review_note = ? WHERE id = ?")->execute([$note, $id]);
        } else {
            throw new RuntimeException('Unknown action.', 400);
        }
    }

    public function delete(string $kind, int $id, array $viewer): void
    {
        $row = $this->row($kind, $id);
        [$table, $statusCol, $published] = $this->tableFor($kind);
        if (!$this->isPublisher($viewer) && ((int) $row['uploaded_by'] !== (int) $viewer['user_id'] || $row[$statusCol] === $published)) {
            throw new RuntimeException('Only the uploader can delete an unpublished resource; the School Administrator can delete any.', 403);
        }
        $this->db->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
        foreach (['file_path', 'marking_scheme_path'] as $col) {
            $abs = $this->absolute((string) ($row[$col] ?? ''));
            if ($abs && is_file($abs)) @unlink($abs);
        }
    }

    // ---------------------------------------------------------------- access
    /**
     * The file or link behind a resource, after checking the viewer may open
     * it. $viewer null = a parent (published public items only).
     * @return array{external_url?:string, path?:string, name?:string, mime?:string}
     */
    public function openable(string $kind, int $id, ?array $viewer, bool $markingScheme = false): array
    {
        $row = $this->row($kind, $id);
        [$table, $statusCol, $published] = $this->tableFor($kind);
        $isPublished = $row[$statusCol] === $published;
        $allowed = $viewer === null
            ? ($isPublished && $row['access_scope'] === 'public')
            : ($isPublished || $this->isPublisher($viewer) || (int) $row['uploaded_by'] === (int) $viewer['user_id']);
        if (!$allowed) throw new RuntimeException('This resource is not available to you.', 403);

        $this->db->prepare("UPDATE {$table} SET download_count = COALESCE(download_count, 0) + 1 WHERE id = ?")->execute([$id]);
        if ($markingScheme) {
            if ($kind !== 'past_paper' || empty($row['marking_scheme_path'])) throw new RuntimeException('This paper has no marking scheme.', 404);
            return ['path' => $this->existing((string) $row['marking_scheme_path']), 'name' => (string) ($row['marking_scheme_name'] ?: 'marking_scheme')];
        }
        if (($row['source_kind'] ?? 'file') === 'link') return ['external_url' => (string) $row['external_url']];
        return ['path' => $this->existing((string) $row['file_path']), 'name' => (string) ($row['file_name'] ?: 'resource'), 'mime' => (string) ($row['file_type'] ?? '')];
    }

    // --------------------------------------------------------------- helpers
    private function tableFor(string $kind): array
    {
        return $kind === 'past_paper'
            ? ['past_papers', 'publish_status', 'published']
            : ['teaching_materials', 'status', 'approved'];
    }

    private function row(string $kind, int $id): array
    {
        [$table] = $this->tableFor($kind);
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Resource not found.', 404);
        return $row;
    }

    private function isPublisher(array $viewer): bool
    {
        return (bool) array_intersect(self::PUBLISHER_ROLES, $viewer['role_ids'] ?? []);
    }

    /** Teachers tag only their own learning areas; leadership any. */
    private function assertAreaInScope(array $viewer, int $areaId): void
    {
        if ($areaId < 1 || array_intersect(self::LEADERSHIP_ROLES, $viewer['role_ids'])) return;
        $scope = (new TeacherCurriculumScopeService($this->db))->resolve((int) $viewer['user_id'], $viewer['role_ids']);
        if (!empty($scope['restricted']) && !in_array($areaId, array_map('intval', $scope['learning_area_ids'] ?? []), true)) {
            throw new RuntimeException('Choose one of the learning areas you teach.', 403);
        }
    }

    private function validLink(string $url): string
    {
        if (mb_strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('Enter a full web link starting with http:// or https://.', 400);
        }
        return $url;
    }

    private function root(): string
    {
        $base = defined('UPLOAD_PATH') ? (string) UPLOAD_PATH : dirname(__DIR__, 3) . '/uploads';
        return rtrim($base, '/') . '/learning_resources';
    }

    /** @return array{path:string,name:string,mime:string,size:int,media_kind:string} */
    private function storeUpload(?array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (!$file || $error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a file to upload, or give a link instead.', 400);
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || (int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('The file is larger than 40 MB. For long videos, upload to YouTube or Google Drive and add the link instead.', 400);
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) throw new RuntimeException('The upload did not complete. Please try again.', 400);
        $name = basename((string) $file['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext])) {
            throw new RuntimeException('That file type is not accepted. Use PDF, Word, PowerPoint, Excel, text, images, video, audio or zip.', 400);
        }
        // Check the bytes too, so a script renamed to .pdf/.jpg is refused.
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, self::TYPES[$ext][1], true)) throw new RuntimeException('The file content does not match its type.', 400);
        $dir = $this->root();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('The file could not be stored.', 500);
        $safe = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $safe)) throw new RuntimeException('The file could not be stored.', 500);
        // Stored relative to the uploads root, never as a full URL.
        return ['path' => 'learning_resources/' . $safe, 'name' => mb_substr($name, 0, 255), 'mime' => $mime, 'size' => (int) $file['size'], 'media_kind' => self::TYPES[$ext][0]];
    }

    private function absolute(string $relative): ?string
    {
        if ($relative === '') return null;
        $relative = preg_replace('#^uploads/#', '', ltrim($relative, '/'));
        $base = defined('UPLOAD_PATH') ? rtrim((string) UPLOAD_PATH, '/') : dirname(__DIR__, 3) . '/uploads';
        return $base . '/' . $relative;
    }

    private function existing(string $relative): string
    {
        $abs = $this->absolute($relative);
        if (!$abs || !is_file($abs)) throw new RuntimeException('The file is missing on the server.', 404);
        return $abs;
    }

    private function decorate(string $kind, array $rows, ?array $viewer): array
    {
        $areaIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'learning_area_id')))));
        $areas = [];
        if ($areaIds) {
            $in = implode(',', array_fill(0, count($areaIds), '?'));
            $stmt = $this->db->prepare('SELECT id, name FROM ' . ReadReplicaService::qualifiedRef('learning_areas') . " WHERE id IN ({$in})");
            $stmt->execute($areaIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) $areas[(int) $a['id']] = $a['name'];
        }
        $userIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'uploaded_by')))));
        $names = [];
        if ($userIds) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $this->db->prepare('SELECT user_id, full_name FROM ' . ReadReplicaService::qualifiedRef('staff_directory') . " WHERE user_id IN ({$in})");
            $stmt->execute($userIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) $names[(int) $u['user_id']] = $u['full_name'];
        }
        [, $statusCol, $published] = $this->tableFor($kind);
        return array_map(function (array $r) use ($kind, $areas, $names, $statusCol, $published, $viewer): array {
            $status = $r[$statusCol] === $published ? 'published' : (string) $r[$statusCol];
            $mine = $viewer !== null && (int) $r['uploaded_by'] === (int) $viewer['user_id'];
            $publisher = $viewer !== null && $this->isPublisher($viewer);
            return [
                'id' => (int) $r['id'],
                'kind' => $kind,
                'title' => $r['title'],
                'description' => $r['description'],
                'learning_area_id' => $r['learning_area_id'] !== null ? (int) $r['learning_area_id'] : null,
                'learning_area_name' => $areas[(int) $r['learning_area_id']] ?? null,
                'grade_level' => $r['grade_level'] ?? null,
                'media_kind' => $kind === 'past_paper' ? (($r['source_kind'] ?? 'file') === 'link' ? 'link' : 'document') : ($r['media_kind'] ?? 'document'),
                'source_kind' => $r['source_kind'] ?? 'file',
                'external_url' => ($r['source_kind'] ?? 'file') === 'link' ? $r['external_url'] : null,
                'file_name' => $r['file_name'] ?? null,
                'file_size' => $r['file_size'] !== null ? (int) $r['file_size'] : null,
                'resource_type' => $r['resource_type'] ?? null,
                'exam_year' => $kind === 'past_paper' ? (int) $r['exam_year'] : null,
                'exam_type' => $kind === 'past_paper' ? $r['exam_type'] : null,
                'term_number' => $kind === 'past_paper' ? ($r['term_id'] !== null ? (int) $r['term_id'] : null) : null,
                'academic_year_term_id' => $kind === 'material' && $r['academic_year_term_id'] !== null ? (int) $r['academic_year_term_id'] : null,
                'has_marking_scheme' => $kind === 'past_paper' && !empty($r['marking_scheme_path']),
                'status' => $status,
                'access_scope' => $r['access_scope'] ?? 'internal',
                'review_note' => $r['review_note'] ?? null,
                'published_at' => $r['published_at'] ?? null,
                'download_count' => (int) ($r['download_count'] ?? 0),
                'uploaded_by_name' => $names[(int) $r['uploaded_by']] ?? null,
                'created_at' => $r['created_at'],
                'can_edit' => $publisher || ($mine && $status !== 'published'),
                'can_publish' => $publisher,
            ];
        }, $rows);
    }
}
