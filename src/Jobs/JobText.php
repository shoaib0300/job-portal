<?php

declare(strict_types=1);

namespace KaamMilo\Jobs;

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
    private const FOREIGN_LOCATION_RE = '/\b(spain|españa|spanish\s+market|madrid|barcelona|valencia|seville|sevilla|malaga|france|french\s+market|paris|lyon|marseille|italy|italia|italian\s+market|rome|roma|milan|milano|portugal|lisbon|lisboa|netherlands|holland|amsterdam|rotterdam|belgium|brussels|bruxelles|poland|warsaw|warszawa|krakow|austria|österreich|wien|vienna|switzerland|schweiz|zürich|zurich|geneva|uk\b|united kingdom|london|manchester|ireland|dublin|usa|united states|new york|san francisco|toronto|canada|india|bangalore|bengaluru|hyderabad|singapore|dubai|uae|czech|prague|praha|sweden|stockholm|denmark|copenhagen|norway|oslo|finland|helsinki|hungary|budapest|romania|bucharest|greece|athens|turkey|istanbul)\b/u';

    private const GERMANY_PLACE_RE = '/\b(germany|deutschland|federal republic of germany|bayern|baden-württemberg|nordrhein-westfalen|nrw|niedersachsen|hessen|sachsen|rheinland-pfalz|schleswig-holstein|thüringen|brandenburg|mecklenburg-vorpommern|saarland|bremen|hamburg|berlin|münchen|munich|köln|cologne|frankfurt|stuttgart|düsseldorf|dortmund|essen|leipzig|dresden|hannover|nürnberg|nuremberg|duisburg|bochum|wuppertal|bielefeld|bonn|münster|karlsruhe|mannheim|augsburg|wiesbaden|braunschweig|chemnitz|kiel|aachen|halle|magdeburg|freiburg|krefeld|lübeck|erfurt|mainz|rostock|kassel|saarbrücken|potsdam|ludwigshafen|oldenburg|osnabrück|leverkusen|heidelberg|darmstadt|regensburg|würzburg|ingolstadt|ulm|heilbronn|paderborn|jena|wolfsburg|göttingen|reutlingen|koblenz|trier|passau|bamberg|bayreuth|konstanz|flensburg|schweinfurt)\b/u';

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
        if ($postedAt === null || trim($postedAt) === '') {
            return '';
        }
        $ts = strtotime($postedAt);
        if ($ts === false) {
            return trim($postedAt);
        }
        return date('j M Y', $ts);
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
        if ($job->postedAt === null || $job->postedAt === '') {
            $parsed = self::parsePostedDate($job->title . "\n" . $job->description);
            if ($parsed !== null) {
                $job->postedAt = $parsed;
            }
        }
        return $job;
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
        if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $t, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d{1,2})\.(\d{1,2})\.(20\d{2})\b/', $t, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
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
}
