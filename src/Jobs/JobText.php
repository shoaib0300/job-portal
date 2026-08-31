<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

use App;


final class JobText
{
    public static function haystack(string ...$parts): string
    {
        return mb_strtolower(trim(implode(' ', $parts)));
    }

    public static function workMode(string $text, string $employmentHint = ''): string
    {
        $t = self::haystack($text, $employmentHint);
        $remote = (bool) preg_match('/\b(remote|homeoffice|home.?office|telearbeit|teleheimarbeit|vollständig remote|100\s*%\s*remote)\b/u', $t);
        $hybrid = (bool) preg_match('/\bhybrid\b/u', $t);
        $onsite = (bool) preg_match('/\b(vor ort|on-?site|präsenz|dienstort)\b/u', $t);
        if ($remote && $hybrid) {
            return 'hybrid';
        }
        if ($remote) {
            return 'remote';
        }
        if ($hybrid) {
            return 'hybrid';
        }
        if ($onsite) {
            return 'onsite';
        }
        return 'unknown';
    }

    public static function employment(string $text, string $hint = ''): string
    {
        $t = self::haystack($text, $hint);
        if (preg_match('/\b(minijob|mini-job|mini\s*job|geringfügig(?:e)?(?:\s+beschäftigung)?|geringfuegig)\b/u', $t)) {
            return 'mini';
        }
        if (preg_match('/\b(teilzeit|part[- ]?time|tz\b)\b/u', $t) || $hint === 'tz' || $hint === 'TEILZEIT') {
            return 'parttime';
        }
        if (preg_match('/\b(vollzeit|full[- ]?time|vz\b)\b/u', $t) || $hint === 'vz' || $hint === 'VOLLZEIT') {
            return 'fulltime';
        }
        return 'unknown';
    }

    public static function offerType(string $text, string $hint = ''): string
    {
        $t = self::haystack($text, $hint);
        $hintU = strtoupper($hint);
        if ($hint === '34' || $hintU === 'PRAKTIKUM' || $hintU === 'TRAINEE' || preg_match('/\b(praktikum|internship|intern\b|trainee)\b/u', $t)) {
            return 'internship';
        }
        if ($hint === '4' || $hintU === 'AUSBILDUNG' || preg_match('/\b(ausbildung|duales studium|azubi)\b/u', $t)) {
            return 'training';
        }
        return 'job';
    }

    /** Foreign cities/countries that must never be rescued by a Berlin/Germany mention in the JD. */
    private const FOREIGN_LOCATION_RE = '/\b(spain|españa|spanish\s+market|madrid|barcelona|valencia|seville|sevilla|malaga|france|french\s+market|paris|lyon|marseille|italy|italia|italian\s+market|rome|roma|milan|milano|portugal|lisbon|lisboa|netherlands|holland|amsterdam|rotterdam|belgium|brussels|bruxelles|luxembourg|luxemburg|lëtzebuerg|poland|warsaw|warszawa|krakow|austria|österreich|wien|vienna|switzerland|schweiz|zürich|zurich|geneva|uk\b|united kingdom|london|manchester|ireland|dublin|usa|united states|new york|san francisco|toronto|canada|india|bangalore|bengaluru|hyderabad|singapore|dubai|uae|czech|prague|praha|sweden|stockholm|denmark|copenhagen|norway|oslo|finland|helsinki|hungary|budapest|romania|bucharest|greece|athens|turkey|istanbul)\b/u';

    private const GERMANY_PLACE_RE = '/\b(germany|deutschland|federal republic of germany|bayern|baden-württemberg|nordrhein-westfalen|nrw|niedersachsen|hessen|sachsen|rheinland-pfalz|schleswig-holstein|thüringen|brandenburg|mecklenburg-vorpommern|saarland|bremen|hamburg|berlin|münchen|munich|garching|köln|cologne|frankfurt|stuttgart|düsseldorf|dortmund|essen|leipzig|dresden|hannover|nürnberg|nuremberg|duisburg|bochum|wuppertal|bielefeld|bonn|münster|karlsruhe|mannheim|augsburg|wiesbaden|braunschweig|chemnitz|kiel|aachen|halle|magdeburg|freiburg|krefeld|lübeck|erfurt|mainz|rostock|kassel|saarbrücken|potsdam|ludwigshafen|oldenburg|osnabrück|leverkusen|heidelberg|darmstadt|regensburg|würzburg|ingolstadt|ulm|heilbronn|paderborn|jena|wolfsburg|göttingen|reutlingen|koblenz|trier|passau|bamberg|bayreuth|konstanz|flensburg|schweinfurt|schwerin|greifswald|wismar|stralsund)\b/u';

    /** @var array<string, string> */
    private const BA_REGION_MAP = [
        'BADEN-WUERTTEMBERG' => 'Baden-Württemberg',
        'BAYERN' => 'Bayern',
        'BERLIN' => 'Berlin',
        'BRANDENBURG' => 'Brandenburg',
        'BREMEN' => 'Bremen',
        'HAMBURG' => 'Hamburg',
        'HESSEN' => 'Hessen',
        'MECKLENBURG-VORPOMMERN' => 'Mecklenburg-Vorpommern',
        'NIEDERSACHSEN' => 'Niedersachsen',
        'NORDRHEIN-WESTFALEN' => 'Nordrhein-Westfalen',
        'RHEINLAND-PFALZ' => 'Rheinland-Pfalz',
        'SAARLAND' => 'Saarland',
        'SACHSEN' => 'Sachsen',
        'SACHSEN-ANHALT' => 'Sachsen-Anhalt',
        'SCHLESWIG-HOLSTEIN' => 'Schleswig-Holstein',
        'THUERINGEN' => 'Thüringen',
    ];

    /** @var array<string, string> normalized city token → Bundesland */
    private const CITY_BUNDESLAND = [
        'münchen' => 'Bayern', 'munich' => 'Bayern', 'garching' => 'Bayern', 'augsburg' => 'Bayern',
        'nürnberg' => 'Bayern', 'nuremberg' => 'Bayern', 'ingolstadt' => 'Bayern', 'regensburg' => 'Bayern',
        'würzburg' => 'Bayern', 'passau' => 'Bayern', 'bamberg' => 'Bayern', 'bayreuth' => 'Bayern',
        'freiburg' => 'Baden-Württemberg', 'karlsruhe' => 'Baden-Württemberg', 'mannheim' => 'Baden-Württemberg',
        'heidelberg' => 'Baden-Württemberg', 'ulm' => 'Baden-Württemberg', 'heilbronn' => 'Baden-Württemberg',
        'reutlingen' => 'Baden-Württemberg', 'konstanz' => 'Baden-Württemberg',
        'köln' => 'Nordrhein-Westfalen', 'cologne' => 'Nordrhein-Westfalen', 'düsseldorf' => 'Nordrhein-Westfalen',
        'dortmund' => 'Nordrhein-Westfalen', 'essen' => 'Nordrhein-Westfalen', 'duisburg' => 'Nordrhein-Westfalen',
        'bochum' => 'Nordrhein-Westfalen', 'wuppertal' => 'Nordrhein-Westfalen', 'bielefeld' => 'Nordrhein-Westfalen',
        'bonn' => 'Nordrhein-Westfalen', 'münster' => 'Nordrhein-Westfalen', 'krefeld' => 'Nordrhein-Westfalen',
        'leverkusen' => 'Nordrhein-Westfalen', 'paderborn' => 'Nordrhein-Westfalen',
        'frankfurt' => 'Hessen', 'wiesbaden' => 'Hessen', 'darmstadt' => 'Hessen', 'kassel' => 'Hessen',
        'hannover' => 'Niedersachsen', 'braunschweig' => 'Niedersachsen', 'oldenburg' => 'Niedersachsen',
        'osnabrück' => 'Niedersachsen', 'wolfsburg' => 'Niedersachsen', 'göttingen' => 'Niedersachsen',
        'stuhr' => 'Niedersachsen',
        'berlin' => 'Berlin', 'hamburg' => 'Hamburg', 'bremen' => 'Bremen',
        'leipzig' => 'Sachsen', 'dresden' => 'Sachsen', 'chemnitz' => 'Sachsen',
        'magdeburg' => 'Sachsen-Anhalt', 'halle' => 'Sachsen-Anhalt',
        'rostock' => 'Mecklenburg-Vorpommern', 'schwerin' => 'Mecklenburg-Vorpommern',
        'greifswald' => 'Mecklenburg-Vorpommern', 'wismar' => 'Mecklenburg-Vorpommern', 'stralsund' => 'Mecklenburg-Vorpommern',
        'neubrandenburg' => 'Mecklenburg-Vorpommern', 'güstrow' => 'Mecklenburg-Vorpommern', 'gustrow' => 'Mecklenburg-Vorpommern',
        'anklam' => 'Mecklenburg-Vorpommern', 'waren' => 'Mecklenburg-Vorpommern', 'neustrelitz' => 'Mecklenburg-Vorpommern',
        'kiel' => 'Schleswig-Holstein', 'lübeck' => 'Schleswig-Holstein', 'flensburg' => 'Schleswig-Holstein',
        'erfurt' => 'Thüringen', 'jena' => 'Thüringen',
        'mainz' => 'Rheinland-Pfalz', 'koblenz' => 'Rheinland-Pfalz', 'trier' => 'Rheinland-Pfalz',
        'ludwigshafen' => 'Rheinland-Pfalz', 'saarbrücken' => 'Saarland', 'potsdam' => 'Brandenburg',
    ];

    /**
     * True when city/country/title clearly place the role outside Germany.
     * Description text is ignored — HQ mentions must not override Madrid/Barcelona.
     */
    public static function isForeignPrimaryLocation(string $city = '', string $country = '', string $title = ''): bool
    {
        $primary = self::haystack($city, $country, $title);
        if ($primary === '') {
            return false;
        }
        return (bool) preg_match(self::FOREIGN_LOCATION_RE, $primary);
    }

    /**
     * True when the posting looks based in Germany (career boards often list EU-wide roles).
     * Primary location wins: foreign city/country/title → false even if JD mentions Berlin.
     */
    public static function looksLikeGermany(string $city = '', string $bundesland = '', string $country = '', string $extra = ''): bool
    {
        if (self::isForeignPrimaryLocation($city, $country, '')) {
            return false;
        }
        $primary = self::haystack($city, $bundesland, $country);
        if ($primary !== '' && preg_match(self::GERMANY_PLACE_RE, $primary)) {
            return true;
        }
        if ($primary !== '' && preg_match('/(^|[\s,\/|(])de([\s,\/)|]|$)/u', $primary)) {
            return true;
        }
        // Blank / remote / unknown primary: fall back to body text, but still reject foreign signals there
        // only when no foreign primary was already ruled out above.
        $hay = self::haystack($city, $bundesland, $country, $extra);
        if ($hay === '') {
            return false;
        }
        if (preg_match(self::FOREIGN_LOCATION_RE, $hay) && !preg_match(self::GERMANY_PLACE_RE, self::haystack($city, $bundesland, $country))) {
            // Body mentions Spain/Madrid with no German primary location → not Germany.
            // Dual locations in body alone (Berlin + Madrid) without a German primary stay false.
            return false;
        }
        if (preg_match('/\b(germany|deutschland|federal republic of germany)\b/u', $hay)) {
            return true;
        }
        if (preg_match(self::GERMANY_PLACE_RE, $hay)) {
            return true;
        }
        if (preg_match('/(^|[\s,\/|(])de([\s,\/)|]|$)/u', $hay)) {
            return true;
        }
        return false;
    }

    /**
     * Fold for keyword matching: lower case, umlauts, strip punctuation to spaces.
     */
    public static function foldMatch(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'é' => 'e', 'è' => 'e',
        ]);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? $s;
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /**
     * True if any role keyword chip matches the text (OR).
     * Within a multi-word chip, all tokens should match (AND), with typo/fuzzy tolerance.
     *
     * @param list<string> $keywords
     */
    public static function matchesAnyKeyword(string $text, array $keywords): bool
    {
        if ($keywords === []) {
            return true;
        }
        $hay = self::foldMatch($text);
        if ($hay === '') {
            return false;
        }
        foreach ($keywords as $kw) {
            if (self::keywordChipMatches($hay, (string) $kw)) {
                return true;
            }
        }
        return false;
    }

    private static function keywordChipMatches(string $hay, string $kw): bool
    {
        $chip = self::foldMatch($kw);
        if ($chip === '') {
            return false;
        }
        if (str_contains($hay, $chip)) {
            return true;
        }
        $tokens = preg_split('/\s+/u', $chip) ?: [];
        $tokens = array_values(array_filter(
            $tokens,
            static fn(string $t): bool => $t !== '' && mb_strlen($t) >= 2
        ));
        if ($tokens === []) {
            return false;
        }
        // Single token (e.g. "2nd", "IT", typo "suport"): fuzzy OR exact.
        if (count($tokens) === 1) {
            return self::tokenInHay($hay, $tokens[0]);
        }
        // Multi-word phrase: every token must match (fuzzy OK).
        foreach ($tokens as $tok) {
            if (!self::tokenInHay($hay, $tok)) {
                return false;
            }
        }
        return true;
    }

    private static function tokenInHay(string $hay, string $tok): bool
    {
        if ($tok === '') {
            return false;
        }
        if (str_contains($hay, $tok)) {
            return true;
        }
        // Short tokens (it, qa, 2nd): exact substring only.
        if (mb_strlen($tok) < 3) {
            return false;
        }
        $words = preg_split('/\s+/u', $hay) ?: [];
        $maxDist = mb_strlen($tok) <= 5 ? 1 : 2;
        foreach ($words as $w) {
            if ($w === '') {
                continue;
            }
            if (str_starts_with($w, $tok) && mb_strlen($tok) >= 3) {
                return true;
            }
            if (abs(mb_strlen($w) - mb_strlen($tok)) > $maxDist) {
                continue;
            }
            if (function_exists('levenshtein') && levenshtein($tok, $w) <= $maxDist) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> */
    public static function seniorityTags(string $text): array
    {
        $t = self::haystack($text);
        $tags = [];
        if (preg_match('/\b(werkstudent|working student|studentische|hiwi|werkstudentin|studierende)\b/u', $t)) {
            $tags[] = 'student';
        }
        if (preg_match('/\b(junior|einsteiger|berufseinsteiger|entry[- ]level)\b/u', $t)) {
            $tags[] = 'junior';
        }
        if (preg_match('/\b(absolvent|graduate|hochschulabsolvent)\b/u', $t)) {
            $tags[] = 'graduate';
        }
        if (preg_match('/\b(keine berufserfahrung|ohne berufserfahrung|no experience|ohne vorkenntnisse|berufseinsteiger)\b/u', $t)) {
            $tags[] = 'no_experience';
        }
        if (preg_match('/\b(minijob|mini[- ]?job|geringfügig|midi[- ]?job)\b/u', $t)) {
            $tags[] = 'minijob';
        }
        return $tags;
    }

    /** @return list<string> */
    public static function languages(string $text): array
    {
        $t = self::haystack($text);
        $out = [];
        if (preg_match('/\b(english|englisch|englischsprachig)\b/u', $t)) {
            $out[] = 'en';
        }
        if (preg_match('/\b(deutsch|german|deutschkenntnisse)\b/u', $t)) {
            $out[] = 'de';
        }
        foreach (['A1', 'A2', 'B1', 'B2', 'C1', 'C2'] as $lvl) {
            if (preg_match('/\b' . $lvl . '\b/i', $text)) {
                $out[] = $lvl;
            }
        }
        return array_values(array_unique($out));
    }

    public static function looksLikeSalary(string $text): bool
    {
        return (bool) preg_match('/(€|eur\b|gehalt|vergütung|tv-?l|tvöd|entgeltgruppe|\d[\d.]*\s*(k|brutto))/iu', $text);
    }

    public static function looksLikeHtml(string $text): bool
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return (bool) preg_match('/<\/?[a-z][a-z0-9]*\b[^>]*>/i', $decoded);
    }

    public static function looksLikeMarkdown(string $text): bool
    {
        return (bool) preg_match(
            '/(^|\n)\s{0,3}(#{1,4}\s+|[-*+]\s+\S|\d+\.\s+\S)|\*\*[^*\n]+\*\*|https?:\/\/\S+/u',
            $text
        );
    }

    /** Plain text for Applications / tailor. Decodes entities, then strips tags. */
    public static function stripHtml(string $html): string
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|li|tr|blockquote)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<(li|h[1-6])\b[^>]*>#i', "\n• ", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        return trim($text);
    }

    /** Safe HTML for the job page: keep lists and emphasis, drop scripts. */
    public static function safeHtml(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = strip_tags($html, '<p><br><ul><ol><li><strong><b><em><i><a><h2><h3><h4><div><span>');
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s(style|class|id)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*(["\'])\s*javascript:[^"\']*\1/i', 'href="#"', $html) ?? $html;
        $html = preg_replace_callback(
            '/<a\b([^>]*)>/i',
            static function (array $m): string {
                $attrs = $m[1];
                $href = '';
                if (preg_match('/\bhref\s*=\s*([\'"])(.*?)\1/i', $attrs, $hm)) {
                    $href = mb_strtolower($hm[2]);
                }
                $isContact = str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:');
                if (!$isContact && !preg_match('/\btarget\s*=/i', $attrs)) {
                    $attrs .= ' target="_blank"';
                }
                if (!$isContact && !preg_match('/\brel\s*=/i', $attrs)) {
                    $attrs .= ' rel="noopener noreferrer"';
                }
                return '<a' . $attrs . '>';
            },
            $html
        ) ?? $html;
        return self::linkifyContacts($html);
    }

    /** Lightweight Markdown → HTML (headings, lists, bold, links). Escapes first. */
    public static function markdownToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $lines = explode("\n", $text);
        $out = [];
        $listType = null; // 'ul' | 'ol' | null

        $closeList = static function () use (&$out, &$listType): void {
            if ($listType !== null) {
                $out[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            $trimmed = rtrim($line);

            if (preg_match('/^\s*[-*+]\s+(.+)$/u', $trimmed, $m)) {
                if ($listType !== 'ul') {
                    $closeList();
                    $out[] = '<ul>';
                    $listType = 'ul';
                }
                $out[] = '<li>' . self::inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+\.\s+(.+)$/u', $trimmed, $m)) {
                if ($listType !== 'ol') {
                    $closeList();
                    $out[] = '<ol>';
                    $listType = 'ol';
                }
                $out[] = '<li>' . self::inlineMarkdown($m[1]) . '</li>';
                continue;
            }

            $closeList();

            if (trim($trimmed) === '') {
                continue;
            }

            if (preg_match('/^\s{0,3}(#{1,4})\s+(.+)$/u', $trimmed, $m)) {
                $level = min(4, max(2, strlen($m[1]) + 1)); // # → h2, ## → h3, ### → h4
                $out[] = '<h' . $level . '>' . self::inlineMarkdown(trim($m[2])) . '</h' . $level . '>';
                continue;
            }

            $out[] = '<p>' . self::inlineMarkdown($trimmed) . '</p>';
        }

        $closeList();
        return implode("\n", $out);
    }

    private static function inlineMarkdown(string $text): string
    {
        $s = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/us', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/__(.+?)__/us', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/us', '<em>$1</em>', $s) ?? $s;
        // Markdown links [label](url)
        $s = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/iu',
            '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
            $s
        ) ?? $s;
        // Autolink bare URLs (skip ones already inside href=")
        $s = preg_replace_callback(
            '/(?<!href="|href=\')(https?:\/\/[^\s<]+)/iu',
            static function (array $m): string {
                $url = rtrim($m[1], '.,);]');
                $trail = substr($m[1], strlen($url));
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $trail;
            },
            $s
        ) ?? $s;
        return $s;
    }

    /** Human-readable posted date for cards / detail (ISO → “24 Aug 2026”). */
    public static function formatPosted(?string $postedAt): string
    {
        if (!self::isPlausiblePostedDate($postedAt)) {
            return '';
        }
        $ts = strtotime(substr((string) $postedAt, 0, 10));
        if ($ts === false) {
            return trim((string) $postedAt);
        }

        return date('j M Y', $ts);
    }

    /** True when postedAt is a real past/today listing date (not a job start date in the future). */
    public static function isPlausiblePostedDate(?string $postedAt): bool
    {
        if ($postedAt === null || trim($postedAt) === '') {
            return false;
        }
        $ts = strtotime(substr(trim($postedAt), 0, 10));
        if ($ts === false) {
            return false;
        }

        return $ts <= strtotime('tomorrow');
    }

    /** Drop future or invalid posted_at values (e.g. start dates stored by mistake). */
    public static function sanitizePostedAt(?string $postedAt): ?string
    {
        if ($postedAt === null || trim($postedAt) === '') {
            return null;
        }
        $ymd = substr(trim($postedAt), 0, 10);

        return self::isPlausiblePostedDate($ymd) ? $ymd : null;
    }

    public static function displayHtml(string $text): string
    {
        if (self::looksLikeHtml($text)) {
            return self::safeHtml($text);
        }
        if (self::looksLikeMarkdown($text)) {
            return self::safeHtml(self::markdownToHtml($text));
        }
        return self::linkifyContacts(App::nl2p($text));
    }

    /** Make emails, phones, URLs, and postal addresses bold + clickable. */
    public static function linkifyContacts(string $html): string
    {
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }
        $inA = 0;
        $out = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, '<')) {
                if (preg_match('/^<a\b/i', $part)) {
                    $inA++;
                } elseif (preg_match('/^<\/a>/i', $part)) {
                    $inA = max(0, $inA - 1);
                }
                $out[] = $part;
                continue;
            }
            $out[] = $inA > 0 ? $part : self::linkifyPlain($part);
        }
        return implode('', $out);
    }

    private static function linkifyPlain(string $text): string
    {
        $slots = [];
        $hold = static function (string $html) use (&$slots): string {
            $slots[] = $html;
            return "\x00" . (count($slots) - 1) . "\x00";
        };

        $text = preg_replace_callback(
            '/(?<![\w.+-])([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})(?![\w.-])/iu',
            static function (array $m) use ($hold): string {
                $email = $m[1];
                return $hold(
                    '<a class="job-contact" href="mailto:' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                    . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</a>'
                );
            },
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '/(?<!href="|href=\')((?:https?:\/\/|www\.)[^\s<]+)/iu',
            static function (array $m) use ($hold): string {
                $raw = rtrim($m[1], '.,);]');
                $trail = substr($m[1], strlen($raw));
                $href = preg_match('#^https?://#i', $raw) ? $raw : 'https://' . $raw;
                return $hold(
                    '<a class="job-contact" href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '" target="_blank" rel="noopener noreferrer">'
                    . htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</a>'
                ) . $trail;
            },
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '/(?<!\d)(\+49[\s.\-\/()]*\d(?:[\s.\-\/()]*\d){7,16}|0\d{1,5}(?:[\s.\-\/]\d{2,}){1,4})(?!\d)/u',
            static function (array $m) use ($hold): string {
                $shown = trim($m[1]);
                if (preg_match('/^\d{1,2}\.\d{1,2}\.\d{2,4}$/', $shown)) {
                    return $shown;
                }
                $digits = preg_replace('/[^\d+]/', '', $shown) ?? $shown;
                if (strlen(preg_replace('/\D/', '', $digits) ?? '') < 8) {
                    return $shown;
                }
                $tel = str_starts_with($digits, '+')
                    ? $digits
                    : (str_starts_with($digits, '0') ? '+49' . substr($digits, 1) : '+49' . $digits);
                return $hold(
                    '<a class="job-contact" href="tel:' . htmlspecialchars($tel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                    . htmlspecialchars($shown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</a>'
                );
            },
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '/\b(Bewerbungsanschrift|Postanschrift|Anschrift|Adresse)\s*:\s*([^\r\n<]{12,220})/iu',
            static function (array $m) use ($hold): string {
                $label = $m[1];
                $addr = trim($m[2], " \t.,;");
                $maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr);
                return $hold(
                    '<strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong> '
                    . '<a class="job-contact" href="' . htmlspecialchars($maps, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '" target="_blank" rel="noopener noreferrer">'
                    . htmlspecialchars($addr, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</a>'
                );
            },
            $text
        ) ?? $text;

        $text = preg_replace(
            '/\b(E-?Mail|Telefon|Tel\.|Mobil(?:funk)?|Phone)\s*:/iu',
            '<strong>$1:</strong>',
            $text
        ) ?? $text;

        return preg_replace_callback(
            '/\x00(\d+)\x00/',
            static function (array $m) use ($slots): string {
                return $slots[(int) $m[1]] ?? $m[0];
            },
            $text
        ) ?? $text;
    }

    public static function enrich(JobListing $job): JobListing
    {
        $blob = self::haystack($job->title, $job->company, $job->description, $job->salaryText);
        if ($job->workMode === 'unknown') {
            $job->workMode = self::workMode($blob);
        }
        if ($job->employment === 'unknown') {
            $job->employment = self::employment($blob);
        }
        if ($job->offerType === 'unknown') {
            $job->offerType = self::offerType($blob);
        }
        if ($job->seniorityTags === []) {
            $job->seniorityTags = self::seniorityTags($blob);
        }
        if ($job->languages === []) {
            $job->languages = self::languages($job->title . "\n" . $job->description);
        }
        if ($job->salaryText === '' && self::looksLikeSalary($job->description)) {
            if (preg_match('/([€]|EUR).{0,40}/u', $job->description, $m)) {
                $job->salaryText = trim($m[0]);
            }
        }
        $job->postedAt = self::sanitizePostedAt($job->postedAt);
        if ($job->postedAt === null || $job->postedAt === '') {
            $parsed = self::parsePostedDate($job->description);
            if ($parsed === null && trim($job->title) !== '') {
                $parsed = self::parsePostedDate($job->title);
            }
            if ($parsed !== null) {
                $job->postedAt = $parsed;
            }
        }

        return self::normalizeLocation($job);
    }

    public static function matchesBundesland(string $jobBundesland, string $filterBundesland): bool
    {
        $jobBundesland = self::prettyBundesland($jobBundesland);
        $filterBundesland = trim($filterBundesland);
        if ($filterBundesland === '') {
            return true;
        }
        if ($jobBundesland === '') {
            return false;
        }

        return mb_stripos($jobBundesland, $filterBundesland) !== false
            || mb_stripos($filterBundesland, $jobBundesland) !== false;
    }

    /** City / Bundesland filter after normalizeLocation(). Unknown or wrong region is dropped when a state is selected. */
    public static function matchesLocationFilter(JobListing $job, string $filterCity, string $filterBundesland): bool
    {
        if ($filterCity !== '' && $job->city !== '') {
            if (mb_stripos($job->city, $filterCity) === false) {
                return false;
            }
        }

        if ($filterBundesland === '') {
            return true;
        }

        $jobBl = self::prettyBundesland($job->bundesland);
        if ($jobBl !== '') {
            return self::matchesBundesland($jobBl, $filterBundesland);
        }

        if ($job->city !== '') {
            $inferred = self::bundeslandForCity($job->city);
            if ($inferred !== '') {
                return self::matchesBundesland($inferred, $filterBundesland);
            }
        }

        return false;
    }

    /** @return list<string> normalized city tokens likely in this Bundesland */
    public static function citiesForBundesland(string $bundesland): array
    {
        $out = [];
        foreach (self::CITY_BUNDESLAND as $city => $bl) {
            if (self::matchesBundesland($bl, $bundesland)) {
                $out[] = $city;
            }
        }

        return $out;
    }

    /** Higher = better location match when user picked a Bundesland. */
    public static function locationRelevance(JobListing $job, string $filterBundesland): int
    {
        if ($filterBundesland === '') {
            return 0;
        }
        if ($job->city !== '') {
            $inferred = self::bundeslandForCity($job->city);
            if ($inferred !== '' && self::matchesBundesland($inferred, $filterBundesland)) {
                return 2;
            }
        }
        $jobBl = self::prettyBundesland($job->bundesland);
        if ($jobBl !== '' && self::matchesBundesland($jobBl, $filterBundesland)) {
            return 1;
        }

        return 0;
    }

    /** @return list<string> LIKE patterns for bundesland column when filtering by state */
    public static function bundeslandSqlLikePatterns(string $filterBundesland): array
    {
        $filterBundesland = trim($filterBundesland);
        if ($filterBundesland === '') {
            return [];
        }

        $patterns = ['%' . $filterBundesland . '%'];
        $extras = [
            'Mecklenburg-Vorpommern' => ['%Mecklenburg%', '%MECKLENBURG%', '%Pomerania%', '%MV%'],
            'Bayern' => ['%Bayern%', '%Bavaria%', '%BAYERN%'],
            'Baden-Württemberg' => ['%Baden%', '%Württemberg%', '%Wuerttemberg%'],
            'Nordrhein-Westfalen' => ['%Nordrhein%', '%Westfalen%', '%NRW%'],
            'Rheinland-Pfalz' => ['%Rheinland%', '%Pfalz%'],
            'Sachsen-Anhalt' => ['%Sachsen-Anhalt%', '%Sachsen Anhalt%'],
            'Schleswig-Holstein' => ['%Schleswig%', '%Holstein%'],
        ];
        if (isset($extras[$filterBundesland])) {
            $patterns = array_merge($patterns, $extras[$filterBundesland]);
        }
        foreach (self::BA_REGION_MAP as $raw => $canonical) {
            if (self::matchesBundesland($canonical, $filterBundesland)) {
                $patterns[] = '%' . str_replace('_', '%', $raw) . '%';
            }
        }

        return array_values(array_unique($patterns));
    }

    /** City tokens safe for SQL LIKE (skip short tokens that false-match other towns). */
    public static function citiesForBundeslandSql(string $bundesland): array
    {
        $skip = ['waren'];

        return array_values(array_filter(
            self::citiesForBundesland($bundesland),
            static fn(string $city): bool => !in_array(mb_strtolower($city), $skip, true),
        ));
    }

    public static function normalizeLocation(JobListing $job): JobListing
    {
        $job->bundesland = self::prettyBundesland($job->bundesland);
        $job->country = self::prettyCountry($job->country);

        if (preg_match('/\bluxemburg|luxembourg|lëtzebuerg\b/ui', $job->city)
            || preg_match('/\bluxemburg|luxembourg|lëtzebuerg\b/ui', $job->bundesland)) {
            if ($job->city === '' && $job->bundesland !== '') {
                $job->city = $job->bundesland;
            }
            $job->country = 'Luxembourg';
        }

        if ($job->city === '') {
            $fromUrl = self::cityFromJobUrl($job->applyUrl);
            if ($fromUrl === '') {
                $fromUrl = self::cityFromJobUrl($job->url);
            }
            if ($fromUrl !== '') {
                $job->city = $fromUrl;
            }
        }

        if ($job->city === '') {
            $fromText = self::cityFromText($job->title . "\n" . $job->description);
            if ($fromText !== '') {
                $job->city = $fromText;
            }
        }

        if ($job->bundesland === '' && $job->city !== '') {
            $job->bundesland = self::bundeslandForCity($job->city);
        }

        if ($job->fingerprint === '' || str_ends_with($job->fingerprint, '|')) {
            $job->fingerprint = JobListing::makeFingerprint($job->company, $job->title, $job->city);
        }

        return $job;
    }

    public static function prettyBundesland(string $region): string
    {
        $region = trim($region);
        if ($region === '') {
            return '';
        }
        $key = strtoupper(str_replace(['ü', 'ä', 'ö', 'ß'], ['UE', 'AE', 'OE', 'SS'], $region));
        $key = preg_replace('/[\s_]+/', '-', $key) ?? $key;
        if (isset(self::BA_REGION_MAP[$key])) {
            return self::BA_REGION_MAP[$key];
        }
        if (preg_match('/mecklenburg.*pomeran/i', $region)) {
            return 'Mecklenburg-Vorpommern';
        }
        foreach (JobQuery::BUNDESLAENDER as $bl) {
            if (mb_strtolower($bl) === mb_strtolower($region)) {
                return $bl;
            }
        }

        return $region;
    }

    private static function prettyCountry(string $land): string
    {
        $land = trim($land);
        if ($land === '' || strcasecmp($land, 'DEUTSCHLAND') === 0 || strcasecmp($land, 'Germany') === 0) {
            return 'Germany';
        }

        return $land;
    }

    private static function bundeslandForCity(string $city): string
    {
        $norm = mb_strtolower(trim($city));
        if ($norm === '') {
            return '';
        }
        if (isset(self::CITY_BUNDESLAND[$norm])) {
            return self::CITY_BUNDESLAND[$norm];
        }
        if (preg_match('/^([^,]+?)(?:\s+bei\s+|\s*,)/u', $norm, $m)) {
            $base = trim($m[1]);
            if (isset(self::CITY_BUNDESLAND[$base])) {
                return self::CITY_BUNDESLAND[$base];
            }
        }
        foreach (preg_split('/\s+bei\s+/u', $norm) ?: [] as $part) {
            $part = trim($part);
            if (isset(self::CITY_BUNDESLAND[$part])) {
                return self::CITY_BUNDESLAND[$part];
            }
        }
        foreach (JobQuery::BUNDESLAENDER as $bl) {
            if (mb_strtolower($bl) === $norm) {
                return $bl;
            }
        }

        return '';
    }

    private static function cityFromJobUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $path = mb_strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if ($path === '') {
            return '';
        }
        if (preg_match('/-(\d{4,6})-([a-z0-9-]+)--\d+\.html$/', $path, $m)) {
            return self::slugToCityName((string) $m[2]);
        }
        if (preg_match('#/job/(\d+)/#', $path, $m)) {
            return '';
        }
        if (preg_match('#/jobs?/([^/]+)/#', $path, $m)) {
            $slug = (string) $m[1];
            if (!preg_match('/^\d+$/', $slug)) {
                return self::slugToCityName($slug);
            }
        }

        return '';
    }

    private static function slugToCityName(string $slug): string
    {
        $slug = trim(mb_strtolower($slug));
        if ($slug === '') {
            return '';
        }
        $slug = preg_replace('/-(munich|berlin|hamburg|frankfurt|stuttgart|dusseldorf|koeln|cologne|nuernberg|nuremberg)$/', '', $slug) ?? $slug;
        $replacements = [
            'muenchen' => 'München', 'munchen' => 'München', 'koeln' => 'Köln', 'nuernberg' => 'Nürnberg',
            'dusseldorf' => 'Düsseldorf', 'goettingen' => 'Göttingen', 'wuerzburg' => 'Würzburg',
            'luebeck' => 'Lübeck', 'saarbruecken' => 'Saarbrücken', 'luebeck' => 'Lübeck',
        ];
        $words = preg_split('/[\s-]+/u', $slug) ?: [];
        $out = [];
        foreach ($words as $word) {
            if ($word === '' || $word === 'bei') {
                if ($word === 'bei') {
                    $out[] = 'bei';
                }
                continue;
            }
            $out[] = $replacements[$word] ?? mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        }

        return trim(implode(' ', $out));
    }

    private static function cityFromText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (preg_match('/\b([A-ZÄÖÜ][a-zäöüß]+(?:\s+bei\s+[A-ZÄÖÜ][a-zäöüß]+(?:\s*\([^)]+\))?)?)\s*(?:\n|$)/u', $text, $m)) {
            $city = trim($m[1]);
            if (!preg_match('/\b(company|apply|date|we help|at sap)\b/ui', $city)) {
                return $city;
            }
        }
        if (preg_match('/\b(?:standort|location|ort|arbeitsort|einsatzort)\s*[:]\s*([^\n,;]+)/ui', $text, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * Best-effort posted date from snippet/text (ISO date or relative EN/DE).
     * Returns Y-m-d or null.
     */
    public static function parsePostedDate(string $text): ?string
    {
        $t = trim($text);
        if ($t === '') {
            return null;
        }

        $relative = self::parseRelativePostedDate($t);
        if ($relative !== null) {
            return $relative;
        }

        return self::parseAbsolutePostedDate($t);
    }

    private static function parseRelativePostedDate(string $t): ?string
    {
        $lower = mb_strtolower($t);
        if (preg_match('/\b(heute|today|just posted|gerade (veröffentlicht|online))\b/u', $lower)) {
            return date('Y-m-d');
        }
        if (preg_match('/\b(gestern|yesterday)\b/u', $lower)) {
            return date('Y-m-d', time() - 86400);
        }
        if (preg_match('/\b(?:vor|posted)?\s*(\d+)\s*(?:tagen?|days?|d)\b/u', $lower, $m)
            || preg_match('/\b(\d+)\s*(?:tagen?|days?)\s*ago\b/u', $lower, $m)) {
            $n = (int) $m[1];
            if ($n >= 0 && $n <= 60) {
                return date('Y-m-d', time() - ($n * 86400));
            }
        }
        if (preg_match('/\b(?:vor|posted)?\s*(\d+)\s*(?:stunden?|hours?|h)\b/u', $lower)
            || preg_match('/\b(\d+)\s*(?:stunden?|hours?)\s*ago\b/u', $lower)) {
            return date('Y-m-d');
        }

        return null;
    }

    private static function parseAbsolutePostedDate(string $t): ?string
    {
        /** @var list<array{ymd: string, offset: int, score: int}> $candidates */
        $candidates = [];

        if (preg_match_all('/\b(20\d{2}-\d{2}-\d{2})\b/', $t, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $m) {
                $ymd = (string) $m[0];
                $offset = (int) $m[1];
                if (self::isStartDateMarkerBefore($t, $offset)) {
                    continue;
                }
                $candidates[] = [
                    'ymd' => $ymd,
                    'offset' => $offset,
                    'score' => self::isPostingContextBefore($t, $offset) ? 10 : 0,
                ];
            }
        }

        if (preg_match_all('/\b(\d{1,2})\.(\d{1,2})\.(20\d{2})\b/u', $t, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $full) {
                $offset = (int) $full[1];
                if (self::isStartDateMarkerBefore($t, $offset)) {
                    continue;
                }
                $ymd = sprintf(
                    '%04d-%02d-%02d',
                    (int) $matches[3][$i][0],
                    (int) $matches[2][$i][0],
                    (int) $matches[1][$i][0]
                );
                $candidates[] = [
                    'ymd' => $ymd,
                    'offset' => $offset,
                    'score' => self::isPostingContextBefore($t, $offset) ? 10 : 0,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return $a['offset'] <=> $b['offset'];
        });

        foreach ($candidates as $candidate) {
            if (self::isPlausiblePostedDate($candidate['ymd'])) {
                return $candidate['ymd'];
            }
        }

        return null;
    }

    private static function isStartDateMarkerBefore(string $text, int $offset): bool
    {
        $before = mb_substr($text, max(0, $offset - 48), min(48, $offset));
        $lower = mb_strtolower($before);

        return (bool) preg_match(
            '/(?:\bstart\b|\bab\b|\bbeginn\b|\beintritt(?:sdatum)?\b|\bstellenantritt\b|'
            . '\bverfügbar\b|\bverfuegbar\b|\bjobstart\b|\bstartdatum\b|\bas of\b|\bstarting\b)\s*$/iu',
            $lower
        );
    }

    private static function isPostingContextBefore(string $text, int $offset): bool
    {
        $before = mb_substr($text, max(0, $offset - 56), min(56, $offset));
        $lower = mb_strtolower($before);

        return (bool) preg_match(
            '/(?:veröffentlicht|veroeffentlicht|published|posted|online|seit|erschienen|aktualisiert|updated)\s*$/iu',
            $lower
        ) || (bool) preg_match('/\b(am|on|vom|from)\s*$/iu', $lower);
    }
}
