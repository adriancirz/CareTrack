<?php

declare(strict_types=1);

final class DatabaseSessionHandler implements SessionUpdateTimestampHandlerInterface
{
    private ?string $lockName = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        if ($this->lockName === null) {
            return true;
        }

        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([$this->lockName]);
        $released = $statement->fetchColumn();
        $this->lockName = null;

        return (int) $released === 1;
    }

    public function read(string $id): string|false
    {
        $this->acquireLock($id);

        $statement = $this->pdo->prepare(
            'SELECT session_data FROM app_sessions WHERE session_id = ?'
        );
        $statement->execute([$id]);
        $data = $statement->fetchColumn();

        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO app_sessions (session_id, session_data, last_activity)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                session_data = VALUES(session_data),
                last_activity = VALUES(last_activity)'
        );

        return $statement->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM app_sessions WHERE session_id = ?'
        );

        return $statement->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM app_sessions WHERE last_activity < ?'
        );
        $statement->execute([time() - $max_lifetime]);

        return $statement->rowCount();
    }

    public function validateId(string $id): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM app_sessions WHERE session_id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    private function acquireLock(string $id): void
    {
        $this->lockName = 'ct_sess_' . substr(hash('sha256', $id), 0, 56);

        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, 10)');
        $statement->execute([$this->lockName]);

        if ((int) $statement->fetchColumn() !== 1) {
            $this->lockName = null;
            throw new RuntimeException('The session lock could not be acquired.');
        }
    }
}
