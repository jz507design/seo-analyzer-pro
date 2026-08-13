<?php

declare(strict_types=1);

/**
 * Database - Historial de auditorias en SQLite.
 * Almacena reportes completos (JSON) por URL + metadatos.
 * NUNCA almacena API keys.
 */
class Database
{
    private PDO $pdo;

    public function __construct(?string $dbFile = null)
    {
        $dbFile ??= dirname(__DIR__) . '/data/auditorias.sqlite';
        $dir = dirname($dbFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->pdo = new PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->initSchema();
    }

    private function initSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS auditorias (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url TEXT NOT NULL,
                score INTEGER NOT NULL,
                critical INTEGER NOT NULL DEFAULT 0,
                warnings INTEGER NOT NULL DEFAULT 0,
                info INTEGER NOT NULL DEFAULT 0,
                report TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_aud_url ON auditorias(url);
            CREATE INDEX IF NOT EXISTS idx_aud_created ON auditorias(created_at);
        ');
    }

    public function save(string $url, array $report): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO auditorias (url, score, critical, warnings, info, report, created_at)
            VALUES (:url, :score, :critical, :warnings, :info, :report, datetime("now"))
        ');
        $stmt->execute([
            'url' => $url,
            'score' => $report['score'] ?? 0,
            'critical' => $report['summary']['critical'] ?? 0,
            'warnings' => $report['summary']['warnings'] ?? 0,
            'info' => $report['summary']['info'] ?? 0,
            'report' => json_encode($report, JSON_UNESCAPED_UNICODE),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** @return array<int, array{id:int,url:string,score:int,critical:int,warnings:int,info:int,created_at:string}> */
    public function recent(int $limit = 20): array
    {
        $stmt = $this->pdo->query("
            SELECT id, url, score, critical, warnings, info, created_at
            FROM auditorias
            ORDER BY id DESC
            LIMIT " . (int)$limit
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT report FROM auditorias WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $report = json_decode($row['report'], true);
        return is_array($report) ? $report : null;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM auditorias WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function count(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM auditorias')->fetchColumn();
    }

    public function deleteOlderThanDays(int $days = 90): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM auditorias WHERE created_at < datetime('now', '-' || :days || ' days')");
        $stmt->execute(['days' => $days]);
        return $stmt->rowCount();
    }
}