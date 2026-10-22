<?php
declare(strict_types=1);

final class ReportService {
    /** @return list<array{date:string,total:int,success:int,pending:int,failed:int,success_amount:float}> */
    public static function dailyCollectionSummary(PDO $db, int $days = 90): array {
        TransactionService::ensureSchema($db);
        $days = max(7, min(365, $days));
        $tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Kolkata'));
        $utc = new DateTimeZone('UTC');
        $endLocal = (new DateTimeImmutable('now', $tz))->setTime(0, 0, 0)->modify('+1 day');
        $startLocal = $endLocal->modify('-'.$days.' days');
        $st = $db->prepare('SELECT created_at, status, requested_amount, confirmed_amount FROM transactions WHERE created_at>=? AND created_at<? ORDER BY created_at ASC');
        $st->execute([
            $startLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
            $endLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
        ]);
        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $startLocal->modify('+'.$i.' days')->format('Y-m-d');
            $buckets[$day] = [
                'date' => $day,
                'total' => 0,
                'success' => 0,
                'pending' => 0,
                'failed' => 0,
                'success_amount' => 0.0,
            ];
        }
        foreach ($st->fetchAll() as $row) {
            $created = (string)($row['created_at'] ?? '');
            if ($created === '') continue;
            $day = (new DateTimeImmutable($created.' UTC'))->setTimezone($tz)->format('Y-m-d');
            if (!isset($buckets[$day])) continue;
            $buckets[$day]['total']++;
            $status = strtoupper((string)($row['status'] ?? ''));
            if ($status === 'SUCCESS') {
                $buckets[$day]['success']++;
                $buckets[$day]['success_amount'] += (float)($row['confirmed_amount'] ?? $row['requested_amount'] ?? 0);
            } elseif ($status === 'PENDING') {
                $buckets[$day]['pending']++;
            } elseif ($status === 'FAILED') {
                $buckets[$day]['failed']++;
            }
        }
        $rows = array_values($buckets);
        usort($rows, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
        return $rows;
    }
}
