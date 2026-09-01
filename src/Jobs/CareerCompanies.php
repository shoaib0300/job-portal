<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use App;
use Auth;
use Db;


/**
 * Company career boards for Jobs → Company career pages source.
 * Types: greenhouse, personio, smartrecruiters, successfactors, site, sitemap.
 */
final class CareerCompanies
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        self::$ready = true;

        $pdo = Db::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS career_companies (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL,
              name VARCHAR(160) NOT NULL,
              board_type VARCHAR(32) NOT NULL,
              board_key VARCHAR(190) NOT NULL,
              careers_url VARCHAR(500) NOT NULL DEFAULT \'\',
              enabled TINYINT(1) NOT NULL DEFAULT 1,
              sort_order INT NOT NULL DEFAULT 0,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY uq_user_board (user_id, board_type, board_key),
              KEY idx_user_enabled (user_id, enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        self::seedGlobalIfEmpty();
    }

    /** Insert catalog rows that are not yet in the shared list (e.g. new Nordex board). */
    public static function syncMissingCatalogEntries(int $userId = 0): int
    {
        $pdo = Db::pdo();
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO career_companies (user_id, name, board_type, board_key, careers_url, enabled, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $n = 0;
        foreach (self::catalog() as $i => $row) {
            $ins->execute([
                $userId,
                $row['name'],
                $row['type'],
                $row['key'],
                $row['url'],
                $i,
            ]);
            $n += $ins->rowCount() > 0 ? 1 : 0;
        }
        return $n;
    }

    /** Shared catalog managed by super-admin (user_id = 0). */
    public static function seedGlobalIfEmpty(): void
    {
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) FROM career_companies WHERE user_id = 0');
        $stmt->execute();
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }
        self::seedDefaults(0);
    }

    public static function seedIfEmpty(int $userId): void
    {
        // Personal accounts no longer get a full catalog copy; they use the global list.
        self::seedGlobalIfEmpty();
    }

    public static function seedDefaults(int $userId): int
    {
        $pdo = Db::pdo();
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO career_companies (user_id, name, board_type, board_key, careers_url, enabled, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $n = 0;
        foreach (self::catalog() as $i => $row) {
            $ins->execute([
                $userId,
                $row['name'],
                $row['type'],
                $row['key'],
                $row['url'],
                $i,
            ]);
            $n += $ins->rowCount() > 0 ? 1 : 0;
        }
        return $n;
    }

    private static function importLegacySetting(int $userId): void
    {
        $extra = trim((string) (App::setting('job_ats_boards', '') ?: ''));
        if ($extra === '') {
            return;
        }
        $ins = Db::pdo()->prepare(
            'INSERT IGNORE INTO career_companies (user_id, name, board_type, board_key, careers_url, enabled, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, 5000)'
        );
        foreach (preg_split('/\R/', $extra) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode(':', $line, 3));
            if (count($parts) < 2) {
                continue;
            }
            $type = strtolower($parts[0]);
            if (!in_array($type, ['personio', 'greenhouse', 'smartrecruiters', 'successfactors', 'site', 'sitemap'], true)) {
                continue;
            }
            $key = $parts[1];
            $name = $parts[2] ?? $key;
            $url = $type === 'site' ? (str_starts_with($key, 'http') ? $key : 'https://' . $key) : '';
            $ins->execute([$userId, $name, $type, $key, $url]);
        }
    }

    /** @return list<array{id:int,user_id:int,name:string,board_type:string,board_key:string,careers_url:string,enabled:int,sort_order:int}> */
    public static function forUser(int $userId, bool $enabledOnly = false): array
    {
        self::ensureSchema();
        $sql = 'SELECT * FROM career_companies WHERE user_id = ?';
        if ($enabledOnly) {
            $sql .= ' AND enabled = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'name' => (string) $row['name'],
                'board_type' => (string) $row['board_type'],
                'board_key' => (string) $row['board_key'],
                'careers_url' => (string) $row['careers_url'],
                'enabled' => (int) $row['enabled'],
                'sort_order' => (int) $row['sort_order'],
            ];
        }
        return $out;
    }

    /**
     * Boards ready for AtsBoardSource: enabled global + enabled personal.
     * Optional $filterKeys is a list of "type:slug" keys; empty = all.
     *
     * @param list<string> $filterKeys
     * @return list<array{type:string,slug:string,label:string,url:string,id:int,scope:string}>
     */
    public static function enabledBoards(int $userId, array $filterKeys = []): array
    {
        self::ensureSchema();
        $out = [];
        $seen = [];
        $rows = self::forUser(0, true);
        if ($userId > 0) {
            $rows = array_merge($rows, self::forUser($userId, true));
        }
        foreach ($rows as $row) {
            $key = $row['board_type'] . ':' . $row['board_key'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($filterKeys !== [] && !in_array($key, $filterKeys, true) && !in_array($row['board_key'], $filterKeys, true)) {
                continue;
            }
            $out[] = [
                'type' => $row['board_type'],
                'slug' => $row['board_key'],
                'label' => $row['name'],
                'url' => $row['careers_url'],
                'id' => $row['id'],
                'scope' => $row['user_id'] === 0 ? 'global' : 'personal',
            ];
        }
        return $out;
    }

    /**
     * Select options for Jobs filter (global enabled + personal enabled extras only).
     *
     * @return list<array{key:string,label:string,scope:string,accent:string,type:string}>
     */
    public static function filterOptions(int $userId): array
    {
        $out = [];
        $sharedKeys = [];
        foreach (self::forUser(0, true) as $row) {
            $key = $row['board_type'] . ':' . $row['board_key'];
            $sharedKeys[$key] = true;
            $out[] = [
                'key' => $key,
                'label' => $row['name'],
                'scope' => 'shared',
                'type' => $row['board_type'],
                'accent' => self::accentFor($key, $row['name']),
            ];
        }
        if ($userId > 0) {
            foreach (self::forUser($userId, true) as $row) {
                $key = $row['board_type'] . ':' . $row['board_key'];
                if (isset($sharedKeys[$key])) {
                    continue;
                }
                $out[] = [
                    'key' => $key,
                    'label' => $row['name'] . ' (mine)',
                    'scope' => 'personal',
                    'type' => $row['board_type'],
                    'accent' => self::accentFor($key, $row['name']),
                ];
            }
        }
        return $out;
    }

    /** Stable brand accent for company chips (Nordex = wind green). */
    public static function accentFor(string $key, string $label): string
    {
        $hay = mb_strtolower($key . ' ' . $label);
        $known = [
            'nordex' => '#0B8F4E',
            'n26' => '#36A18B',
            'celonis' => '#1B4DFF',
            'trade-republic' => '#0D0D0D',
            'personio' => '#0B6BCB',
            'deliveryhero' => '#D70F64',
            'hellofresh' => '#91C11E',
            'flix' => '#FFE16A',
            'siemens' => '#009999',
            'bmw' => '#1C69D4',
            'mercedes' => '#333333',
            'zalando' => '#FF6900',
            'sap' => '#0FAAFF',
            'rossmann' => '#C8102E',
            'meine-karriere-im-handel' => '#005192',
            'citti' => '#005192',
        ];
        foreach ($known as $needle => $color) {
            if (str_contains($hay, $needle)) {
                return $color;
            }
        }
        $h = (int) sprintf('%u', crc32($key)) % 360;
        return sprintf('hsl(%d 48%% 40%%)', $h);
    }

    /**
     * Personal boards that are not already in the shared catalog.
     *
     * @return list<array{id:int,user_id:int,name:string,board_type:string,board_key:string,careers_url:string,enabled:int,sort_order:int}>
     */
    public static function personalExtras(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $sharedKeys = [];
        foreach (self::forUser(0, false) as $row) {
            $sharedKeys[$row['board_type'] . ':' . $row['board_key']] = true;
        }
        $out = [];
        foreach (self::forUser($userId, false) as $row) {
            $key = $row['board_type'] . ':' . $row['board_key'];
            if (isset($sharedKeys[$key])) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Remove old per-user catalog copies that duplicate the shared (user_id=0) list.
     * Returns number of rows deleted.
     */
    public static function purgePersonalDuplicates(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        self::ensureSchema();
        $stmt = Db::pdo()->prepare(
            'DELETE p FROM career_companies p
             INNER JOIN career_companies g
               ON g.user_id = 0
              AND g.board_type = p.board_type
              AND g.board_key = p.board_key
             WHERE p.user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    public static function add(int $userId, string $name, string $type, string $key, string $url = ''): int
    {
        self::ensureSchema();
        $name = trim($name);
        $type = strtolower(trim($type));
        $key = trim($key);
        $url = trim($url);
        if ($name === '' || $key === '' || !in_array($type, ['greenhouse', 'personio', 'smartrecruiters', 'successfactors', 'site', 'sitemap', 'portal'], true)) {
            throw new InvalidArgumentException('Need company name, type (greenhouse/personio/smartrecruiters/successfactors/site/sitemap/portal), and key.');
        }
        if ($type === 'site' || $type === 'sitemap' || $type === 'successfactors' || $type === 'portal') {
            $host = self::hostFromUrl($url !== '' ? $url : $key);
            if ($host === '') {
                throw new InvalidArgumentException('Site/sitemap/SuccessFactors boards need a careers URL like https://jobs.example.com/');
            }
            $key = $host;
            if ($url === '') {
                $url = 'https://' . $host . '/';
            }
        }
        if ($userId > 0) {
            $dup = Db::pdo()->prepare(
                'SELECT id FROM career_companies WHERE user_id = 0 AND board_type = ? AND board_key = ? LIMIT 1'
            );
            $dup->execute([$type, $key]);
            if ($dup->fetch()) {
                throw new InvalidArgumentException('That company is already in the shared catalog.');
            }
        }
        $stmt = Db::pdo()->prepare(
            'INSERT INTO career_companies (user_id, name, board_type, board_key, careers_url, enabled, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, 9000)
             ON DUPLICATE KEY UPDATE name = VALUES(name), careers_url = VALUES(careers_url), enabled = 1'
        );
        $stmt->execute([$userId, $name, $type, $key, $url]);
        return (int) Db::pdo()->lastInsertId();
    }

    public static function setEnabled(int $userId, int $id, bool $enabled): void
    {
        Db::pdo()->prepare('UPDATE career_companies SET enabled = ? WHERE id = ? AND user_id = ?')
            ->execute([$enabled ? 1 : 0, $id, $userId]);
    }

    public static function delete(int $userId, int $id): void
    {
        Db::pdo()->prepare('DELETE FROM career_companies WHERE id = ? AND user_id = ?')
            ->execute([$id, $userId]);
    }

    public static function hostFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? mb_strtolower($host) : '';
    }

    /**
     * ~100 common German / DACH employers with public career feeds or sites.
     *
     * @return list<array{name:string,type:string,key:string,url:string}>
     */
    public static function catalog(): array
    {
        return [
            // Greenhouse
            ['name' => 'N26', 'type' => 'greenhouse', 'key' => 'n26', 'url' => 'https://n26.com/en/careers'],
            ['name' => 'Celonis', 'type' => 'greenhouse', 'key' => 'celonis', 'url' => 'https://www.celonis.com/careers'],
            ['name' => 'Trade Republic', 'type' => 'greenhouse', 'key' => 'trade-republic', 'url' => 'https://traderepublic.com/careers'],
            ['name' => 'Contentful', 'type' => 'greenhouse', 'key' => 'contentful', 'url' => 'https://www.contentful.com/careers'],
            ['name' => 'Flix', 'type' => 'greenhouse', 'key' => 'flix', 'url' => 'https://www.flix.com/careers'],
            ['name' => 'Delivery Hero', 'type' => 'greenhouse', 'key' => 'deliveryhero', 'url' => 'https://careers.deliveryhero.com'],
            ['name' => 'HelloFresh', 'type' => 'greenhouse', 'key' => 'hellofresh', 'url' => 'https://careers.hellofresh.com'],
            ['name' => 'SumUp', 'type' => 'greenhouse', 'key' => 'sumup', 'url' => 'https://www.sumup.com/careers'],
            ['name' => 'Adjust', 'type' => 'greenhouse', 'key' => 'adjust', 'url' => 'https://www.adjust.com/company/careers'],
            ['name' => 'Auto1 Group', 'type' => 'greenhouse', 'key' => 'auto1-group', 'url' => 'https://www.auto1-group.com/jobs'],
            ['name' => 'About You', 'type' => 'greenhouse', 'key' => 'aboutyou', 'url' => 'https://corporate.aboutyou.de/en/career'],
            ['name' => 'Omio', 'type' => 'greenhouse', 'key' => 'omio', 'url' => 'https://www.omio.com/jobs'],
            ['name' => 'Tier Mobility', 'type' => 'greenhouse', 'key' => 'tier', 'url' => 'https://www.tier.app/en/careers'],
            ['name' => 'Raisin', 'type' => 'greenhouse', 'key' => 'raisin', 'url' => 'https://www.raisin.com/en-de/careers'],
            ['name' => 'Solaris', 'type' => 'greenhouse', 'key' => 'solarisbank', 'url' => 'https://www.solarisgroup.com/careers'],
            ['name' => 'Taxfix', 'type' => 'greenhouse', 'key' => 'taxfix', 'url' => 'https://www.taxfix.de/karriere'],
            ['name' => 'Zenjob', 'type' => 'greenhouse', 'key' => 'zenjob', 'url' => 'https://www.zenjob.com/careers'],
            ['name' => 'Wunder Mobility', 'type' => 'greenhouse', 'key' => 'wundermobility', 'url' => 'https://www.wundermobility.com/careers'],
            ['name' => 'Forto', 'type' => 'greenhouse', 'key' => 'forto', 'url' => 'https://forto.com/en/careers'],
            ['name' => 'Contentful Berlin', 'type' => 'greenhouse', 'key' => 'contentful', 'url' => 'https://www.contentful.com/careers'],

            // SuccessFactors (public career site HTML — no Bright Data)
            ['name' => 'Nordex SE', 'type' => 'successfactors', 'key' => 'jobs.nordex-online.com', 'url' => 'https://jobs.nordex-online.com/search'],

            // Personio
            ['name' => 'Personio', 'type' => 'personio', 'key' => 'personio', 'url' => 'https://www.personio.com/about-personio/careers'],
            ['name' => 'GetYourGuide', 'type' => 'personio', 'key' => 'getyourguide', 'url' => 'https://www.getyourguide.com/careers'],
            ['name' => 'Pitch', 'type' => 'personio', 'key' => 'pitch', 'url' => 'https://pitch.com/jobs'],
            ['name' => 'Mambu', 'type' => 'personio', 'key' => 'mambu', 'url' => 'https://www.mambu.com/careers'],
            ['name' => 'Staffbase', 'type' => 'personio', 'key' => 'staffbase', 'url' => 'https://staffbase.com/en/careers'],
            ['name' => 'CoachHub', 'type' => 'personio', 'key' => 'coachhub', 'url' => 'https://www.coachhub.com/careers'],
            ['name' => 'Signavio', 'type' => 'personio', 'key' => 'signavio', 'url' => 'https://www.signavio.com/careers'],
            ['name' => 'TourRadar', 'type' => 'personio', 'key' => 'tourradar', 'url' => 'https://www.tourradar.com/careers'],
            ['name' => 'Homeday', 'type' => 'personio', 'key' => 'homeday', 'url' => 'https://www.homeday.de/de/karriere'],
            ['name' => 'Lemonaid', 'type' => 'personio', 'key' => 'lemonaid', 'url' => 'https://www.lemonaid.de/karriere'],
            ['name' => 'Circ', 'type' => 'personio', 'key' => 'circ', 'url' => 'https://www.circ.com/careers'],
            ['name' => 'Blinkist', 'type' => 'personio', 'key' => 'blinkist', 'url' => 'https://www.blinkist.com/jobs'],
            ['name' => 'Kenjo', 'type' => 'personio', 'key' => 'kenjo', 'url' => 'https://www.kenjo.io/careers'],
            ['name' => 'Usercentrics', 'type' => 'personio', 'key' => 'usercentrics', 'url' => 'https://usercentrics.com/careers'],
            ['name' => 'DeepL', 'type' => 'personio', 'key' => 'deepl', 'url' => 'https://jobs.deepl.com'],
            ['name' => 'Sennder', 'type' => 'personio', 'key' => 'sennder', 'url' => 'https://www.sennder.com/careers'],
            ['name' => 'Infarm', 'type' => 'personio', 'key' => 'infarm', 'url' => 'https://www.infarm.com/careers'],
            ['name' => 'Grover', 'type' => 'personio', 'key' => 'grover', 'url' => 'https://www.grover.com/de-de/careers'],
            ['name' => 'Wolt', 'type' => 'personio', 'key' => 'wolt', 'url' => 'https://careers.wolt.com'],
            ['name' => 'Klarx', 'type' => 'personio', 'key' => 'klarx', 'url' => 'https://www.klarx.de/karriere'],

            // SmartRecruiters
            ['name' => 'Siemens', 'type' => 'smartrecruiters', 'key' => 'Siemens', 'url' => 'https://jobs.siemens.com'],
            ['name' => 'Siemens Energy', 'type' => 'smartrecruiters', 'key' => 'SiemensEnergy', 'url' => 'https://jobs.siemens-energy.com'],
            ['name' => 'Deutsche Telekom', 'type' => 'smartrecruiters', 'key' => 'DeutscheTelekomAG', 'url' => 'https://www.telekom.com/en/careers'],
            ['name' => 'Vodafone Germany', 'type' => 'smartrecruiters', 'key' => 'Vodafone', 'url' => 'https://jobs.vodafone.com'],
            ['name' => 'IKEA Germany', 'type' => 'smartrecruiters', 'key' => 'IKEA', 'url' => 'https://ikea.jobs.cz'],
            ['name' => 'adidas', 'type' => 'smartrecruiters', 'key' => 'adidas', 'url' => 'https://careers.adidas.com'],
            ['name' => 'Puma', 'type' => 'smartrecruiters', 'key' => 'PUMA', 'url' => 'https://about.puma.com/en/careers'],
            ['name' => 'Infineon', 'type' => 'smartrecruiters', 'key' => 'InfineonTechnologies', 'url' => 'https://www.infineon.com/cms/en/careers'],

            // Major German career sites (site: search)
            ['name' => 'Mercedes-Benz', 'type' => 'site', 'key' => 'jobs.mercedes-benz.com', 'url' => 'https://jobs.mercedes-benz.com/'],
            ['name' => 'BMW Group', 'type' => 'site', 'key' => 'www.bmwgroup.jobs', 'url' => 'https://www.bmwgroup.jobs/de/de.html'],
            ['name' => 'Audi', 'type' => 'site', 'key' => 'www.audi.com', 'url' => 'https://www.audi.com/careers'],
            ['name' => 'Volkswagen', 'type' => 'site', 'key' => 'www.volkswagen-group.com', 'url' => 'https://www.volkswagen-group.com/en/careers'],
            ['name' => 'Porsche', 'type' => 'site', 'key' => 'www.porsche.com', 'url' => 'https://www.porsche.com/career'],
            ['name' => 'Bosch', 'type' => 'site', 'key' => 'www.bosch.de', 'url' => 'https://www.bosch.de/karriere'],
            ['name' => 'SAP', 'type' => 'site', 'key' => 'jobs.sap.com', 'url' => 'https://jobs.sap.com/'],
            ['name' => 'Deutsche Bahn', 'type' => 'site', 'key' => 'karriere.deutschebahn.com', 'url' => 'https://karriere.deutschebahn.com/'],
            ['name' => 'Lufthansa', 'type' => 'site', 'key' => 'www.be-lufthansa.com', 'url' => 'https://www.be-lufthansa.com/'],
            ['name' => 'Allianz', 'type' => 'site', 'key' => 'careers.allianz.com', 'url' => 'https://careers.allianz.com/'],
            ['name' => 'Munich Re', 'type' => 'site', 'key' => 'www.munichre.com', 'url' => 'https://www.munichre.com/careers'],
            ['name' => 'Deutsche Bank', 'type' => 'site', 'key' => 'careers.db.com', 'url' => 'https://careers.db.com/'],
            ['name' => 'Commerzbank', 'type' => 'site', 'key' => 'www.commerzbank.de', 'url' => 'https://www.commerzbank.de/karriere'],
            ['name' => 'Zalando', 'type' => 'site', 'key' => 'jobs.zalando.com', 'url' => 'https://jobs.zalando.com/'],
            ['name' => 'Otto Group', 'type' => 'site', 'key' => 'www.ottogroup.com', 'url' => 'https://www.ottogroup.com/karriere'],
            ['name' => 'Amazon Germany', 'type' => 'site', 'key' => 'www.amazon.jobs', 'url' => 'https://www.amazon.jobs/en/locations/germany'],
            ['name' => 'Google Germany', 'type' => 'site', 'key' => 'careers.google.com', 'url' => 'https://careers.google.com/locations/germany'],
            ['name' => 'Microsoft Germany', 'type' => 'site', 'key' => 'careers.microsoft.com', 'url' => 'https://careers.microsoft.com/'],
            ['name' => 'Apple Germany', 'type' => 'site', 'key' => 'jobs.apple.com', 'url' => 'https://jobs.apple.com/'],
            ['name' => 'IBM Germany', 'type' => 'site', 'key' => 'www.ibm.com', 'url' => 'https://www.ibm.com/careers'],
            ['name' => 'Intel Germany', 'type' => 'site', 'key' => 'jobs.intel.com', 'url' => 'https://jobs.intel.com/'],
            ['name' => 'Continental', 'type' => 'site', 'key' => 'www.continental.com', 'url' => 'https://www.continental.com/careers'],
            ['name' => 'ZF Friedrichshafen', 'type' => 'site', 'key' => 'www.zf.com', 'url' => 'https://www.zf.com/careers'],
            ['name' => 'Schaeffler', 'type' => 'site', 'key' => 'www.schaeffler.com', 'url' => 'https://www.schaeffler.com/careers'],
            ['name' => 'BASF', 'type' => 'site', 'key' => 'www.basf.com', 'url' => 'https://www.basf.com/global/en/careers'],
            ['name' => 'Bayer', 'type' => 'site', 'key' => 'career.bayer.com', 'url' => 'https://career.bayer.com/'],
            ['name' => 'Merck KGaA', 'type' => 'site', 'key' => 'www.merckgroup.com', 'url' => 'https://www.merckgroup.com/careers'],
            ['name' => 'Fresenius', 'type' => 'site', 'key' => 'www.fresenius.com', 'url' => 'https://www.fresenius.com/careers'],
            ['name' => 'Henkel', 'type' => 'site', 'key' => 'www.henkel.com', 'url' => 'https://www.henkel.com/careers'],
            ['name' => 'Beiersdorf', 'type' => 'site', 'key' => 'www.beiersdorf.com', 'url' => 'https://www.beiersdorf.com/career'],
            ['name' => 'Thyssenkrupp', 'type' => 'site', 'key' => 'www.thyssenkrupp.com', 'url' => 'https://www.thyssenkrupp.com/en/careers'],
            ['name' => 'RWE', 'type' => 'site', 'key' => 'www.rwe.com', 'url' => 'https://www.rwe.com/en/career'],
            ['name' => 'E.ON', 'type' => 'site', 'key' => 'www.eon.com', 'url' => 'https://www.eon.com/en/about-us/careers.html'],
            ['name' => 'EnBW', 'type' => 'site', 'key' => 'www.enbw.com', 'url' => 'https://www.enbw.com/karriere'],
            ['name' => 'Deutsche Post DHL', 'type' => 'site', 'key' => 'careers.dhl.com', 'url' => 'https://careers.dhl.com/'],
            ['name' => 'TUI', 'type' => 'site', 'key' => 'careers.tuigroup.com', 'url' => 'https://careers.tuigroup.com/'],
            ['name' => 'Ryanair / Lauda', 'type' => 'site', 'key' => 'careers.ryanair.com', 'url' => 'https://careers.ryanair.com/'],
            ['name' => 'CHECK24', 'type' => 'site', 'key' => 'jobs.check24.de', 'url' => 'https://jobs.check24.de/'],
            ['name' => 'Otto', 'type' => 'site', 'key' => 'www.otto.de', 'url' => 'https://www.otto.de/unternehmen/karriere'],
            ['name' => 'MediaMarktSaturn', 'type' => 'site', 'key' => 'www.mediasaturn.com', 'url' => 'https://www.mediasaturn.com/karriere'],
            ['name' => 'Lidl', 'type' => 'site', 'key' => 'karriere.lidl.de', 'url' => 'https://karriere.lidl.de/'],
            ['name' => 'Aldi Süd', 'type' => 'site', 'key' => 'unternehmen.aldi-sued.de', 'url' => 'https://unternehmen.aldi-sued.de/karriere'],
            ['name' => 'Aldi Nord', 'type' => 'site', 'key' => 'www.aldi-nord.de', 'url' => 'https://www.aldi-nord.de/unternehmen/karriere.html'],
            ['name' => 'REWE', 'type' => 'site', 'key' => 'karriere.rewe.de', 'url' => 'https://karriere.rewe.de/'],
            ['name' => 'Edeka', 'type' => 'site', 'key' => 'verbund.edeka', 'url' => 'https://verbund.edeka/karriere'],
            ['name' => 'dm-drogerie', 'type' => 'site', 'key' => 'www.dm.de', 'url' => 'https://www.dm.de/unternehmen/karriere'],
            ['name' => 'Rossmann', 'type' => 'sitemap', 'key' => 'jobs.rossmann.de', 'url' => 'https://jobs.rossmann.de/'],
            ['name' => 'Meine Karriere im Handel (CITTI)', 'type' => 'portal', 'key' => 'www.meine-karriere-im-handel.de', 'url' => 'https://www.meine-karriere-im-handel.de/jobsuche'],
            ['name' => 'DIS AG', 'type' => 'sitemap', 'key' => 'jobs.de.dis-ag.com', 'url' => 'https://jobs.de.dis-ag.com/'],
            ['name' => 'DATEV', 'type' => 'site', 'key' => 'www.datev.de', 'url' => 'https://www.datev.de/web/de/karriere'],
            ['name' => 'TeamViewer', 'type' => 'site', 'key' => 'www.teamviewer.com', 'url' => 'https://www.teamviewer.com/en/company/careers'],
            ['name' => 'TeamViewer DE', 'type' => 'site', 'key' => 'www.teamviewer.com', 'url' => 'https://www.teamviewer.com/de/unternehmen/karriere'],
            ['name' => 'Software AG', 'type' => 'site', 'key' => 'www.softwareag.com', 'url' => 'https://www.softwareag.com/en_corporate/company/careers.html'],
            ['name' => 'TeamBank / easyCredit', 'type' => 'site', 'key' => 'www.teambank.de', 'url' => 'https://www.teambank.de/karriere'],
            ['name' => '1&1', 'type' => 'site', 'key' => 'karriere.1und1.de', 'url' => 'https://karriere.1und1.de/'],
            ['name' => 'United Internet', 'type' => 'site', 'key' => 'www.united-internet.de', 'url' => 'https://www.united-internet.de/karriere.html'],
            ['name' => 'Ströer', 'type' => 'site', 'key' => 'www.stroeer.de', 'url' => 'https://www.stroeer.de/karriere'],
            ['name' => 'ProSiebenSat.1', 'type' => 'site', 'key' => 'www.prosiebensat1.com', 'url' => 'https://www.prosiebensat1.com/karriere'],
            ['name' => 'RTL Deutschland', 'type' => 'site', 'key' => 'karriere.rtl.de', 'url' => 'https://karriere.rtl.de/'],
            ['name' => 'Springer Nature', 'type' => 'site', 'key' => 'groups.springernature.com', 'url' => 'https://groups.springernature.com/gp/group/careers'],
            ['name' => 'Axel Springer', 'type' => 'site', 'key' => 'www.axelspringer.com', 'url' => 'https://www.axelspringer.com/en/career'],
            ['name' => 'Hapag-Lloyd', 'type' => 'site', 'key' => 'www.hapag-lloyd.com', 'url' => 'https://www.hapag-lloyd.com/en/company/career.html'],
            ['name' => 'Kühne+Nagel', 'type' => 'site', 'key' => 'home.kuehne-nagel.com', 'url' => 'https://home.kuehne-nagel.com/career'],
            ['name' => 'DB Schenker', 'type' => 'site', 'key' => 'www.dbschenker.com', 'url' => 'https://www.dbschenker.com/global/about/career'],
            ['name' => 'FERCHAU', 'type' => 'site', 'key' => 'www.ferchau.com', 'url' => 'https://www.ferchau.com/de/de/karriere'],
            ['name' => 'Hays Germany', 'type' => 'site', 'key' => 'www.hays.de', 'url' => 'https://www.hays.de/jobsuche'],
            ['name' => 'Randstad Germany', 'type' => 'site', 'key' => 'www.randstad.de', 'url' => 'https://www.randstad.de/jobs'],
            ['name' => 'StepStone careers', 'type' => 'site', 'key' => 'www.stepstone.de', 'url' => 'https://www.stepstone.de/'],
            ['name' => 'Xing Jobs hub', 'type' => 'site', 'key' => 'www.xing.com', 'url' => 'https://www.xing.com/jobs'],
            ['name' => 'CARIAD', 'type' => 'site', 'key' => 'cariad.technology', 'url' => 'https://cariad.technology/de/de/careers.html'],
            ['name' => 'MBition', 'type' => 'site', 'key' => 'mbition.io', 'url' => 'https://mbition.io/careers'],
            ['name' => 'MAN Truck & Bus', 'type' => 'site', 'key' => 'www.man.eu', 'url' => 'https://www.man.eu/de/de/karriere.html'],
            ['name' => 'Rheinmetall', 'type' => 'site', 'key' => 'www.rheinmetall.com', 'url' => 'https://www.rheinmetall.com/en/career'],
            ['name' => 'Airbus Germany', 'type' => 'site', 'key' => 'www.airbus.com', 'url' => 'https://www.airbus.com/en/careers'],
            ['name' => 'MTU Aero Engines', 'type' => 'site', 'key' => 'www.mtu.de', 'url' => 'https://www.mtu.de/careers'],
            ['name' => 'Jenoptik', 'type' => 'site', 'key' => 'www.jenoptik.com', 'url' => 'https://www.jenoptik.com/careers'],
            ['name' => 'Zeiss', 'type' => 'site', 'key' => 'www.zeiss.com', 'url' => 'https://www.zeiss.com/corporate/en/careers.html'],
            ['name' => 'Trumpf', 'type' => 'site', 'key' => 'www.trumpf.com', 'url' => 'https://www.trumpf.com/en_INT/careers'],
            ['name' => 'Festo', 'type' => 'site', 'key' => 'www.festo.com', 'url' => 'https://www.festo.com/careers'],
            ['name' => 'Kärcher', 'type' => 'site', 'key' => 'www.kaercher.com', 'url' => 'https://www.kaercher.com/int/inside-kaercher/career.html'],
            ['name' => 'Miele', 'type' => 'site', 'key' => 'www.miele.com', 'url' => 'https://www.miele.com/en/m/career-2303.htm'],
            ['name' => 'Vorwerk', 'type' => 'site', 'key' => 'corporate.vorwerk.com', 'url' => 'https://corporate.vorwerk.com/en/career'],
        ];
    }
}
