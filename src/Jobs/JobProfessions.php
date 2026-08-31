<?php

declare(strict_types=1);

namespace KaamFit\Jobs;

/** Profession presets for the jobs filter (German + English title keywords). */
final class JobProfessions
{
    /** Included with every profession so Werkstudent / Praktikum roles in the field are not missed. */
    private const STUDENT_TERMS = [
        'werkstudent',
        'werkstudentin',
        'studentische hilfskraft',
        'studentische mitarbeit',
        'working student',
        'hiwi',
        'praktikum',
        'praktikant',
        'praktikantin',
        'ausbildung',
        'azubi',
        'duales studium',
        'trainee',
    ];

    /** Slug → other profession slugs whose keywords are merged (umbrella categories). */
    private const UMBRELLA = [
        'it' => ['software', 'qa', 'network', 'cybersecurity', 'data', 'devops', 'product'],
        'engineering' => ['civil_engineer', 'mechanical', 'electrical', 'automotive', 'process', 'architect'],
        'healthcare' => ['doctor', 'nurse', 'medical', 'pharmacy'],
    ];

    /** @var array<string, array<string, string>> group label → slug → display label */
    private const GROUPS = [
        'Technology & IT' => [
            'it' => 'IT / Informatik (all tech)',
            'software' => 'Software Developer',
            'qa' => 'QA / Testing',
            'network' => 'Network / Infrastructure',
            'cybersecurity' => 'Cybersecurity',
            'data' => 'Data / Analytics',
            'devops' => 'DevOps / Cloud',
            'product' => 'Product Management',
        ],
        'Engineering' => [
            'engineering' => 'Engineering (all)',
            'civil_engineer' => 'Civil Engineer',
            'mechanical' => 'Mechanical Engineer',
            'electrical' => 'Electrical Engineer',
            'automotive' => 'Automotive',
            'process' => 'Process Engineer',
            'architect' => 'Architect',
        ],
        'Healthcare' => [
            'healthcare' => 'Healthcare (all)',
            'doctor' => 'Doctor / Physician',
            'nurse' => 'Nurse / Care',
            'medical' => 'Medical / Therapy',
            'pharmacy' => 'Pharmacy',
        ],
        'Education' => [
            'teacher' => 'Teacher',
            'professor' => 'Professor / Academic',
            'social_education' => 'Social / Childcare',
        ],
        'Business & Finance' => [
            'accounting' => 'Accounting / Finance',
            'hr' => 'HR / Recruiting',
            'marketing' => 'Marketing',
            'sales' => 'Sales',
            'consulting' => 'Consulting',
            'project_manager' => 'Project Manager',
            'customer_service' => 'Customer Service',
        ],
        'Creative & Media' => [
            'design' => 'Design / UX',
            'content' => 'Content / Writing',
            'media' => 'Media / Journalism',
        ],
        'Legal & Public' => [
            'legal' => 'Legal',
            'public_sector' => 'Public sector / Verwaltung',
        ],
        'Science & Research' => [
            'science' => 'Science / Research',
            'laboratory' => 'Laboratory',
        ],
        'Trades & Operations' => [
            'electrician' => 'Electrician / Elektriker',
            'construction' => 'Construction / Bau',
            'logistics' => 'Logistics',
            'warehouse' => 'Warehouse',
            'driver' => 'Driver / Fahrer',
            'production' => 'Production / Manufacturing',
            'maintenance' => 'Maintenance / Technik',
        ],
        'Hospitality & Retail' => [
            'chef' => 'Chef / Gastronomy',
            'retail' => 'Retail / Verkauf',
            'hotel' => 'Hotel / Tourism',
        ],
        'Office' => [
            'admin' => 'Office / Administration',
            'secretary' => 'Secretary / Assistant',
        ],
    ];

    /** @var array<string, list<string>> */
    private const KEYWORDS = [
        'it' => [
            'informatik', 'it-consultant', 'it consultant', 'it-support', 'it support', 'it administrator',
            'it leiter', 'it specialist', 'fachinformatiker', 'edv', 'informationstechnik', 'digitalisierung',
        ],
        'software' => [
            'softwareentwickler', 'software developer', 'softwareentwicklung', 'programmierer', 'entwickler',
            'backend', 'frontend', 'fullstack', 'webentwickler', 'web developer', 'app entwickler',
            'java', 'python', 'javascript', 'typescript', 'react', 'angular', 'php', 'kotlin', 'c#',
            'mobile entwickler', 'android', 'ios', 'software engineer',
        ],
        'qa' => [
            'test', 'tester', 'testen', 'testing', 'softwaretest', 'softwaretester', 'software tester',
            'testingenieur', 'test engineer', 'testautomatisierung', 'test automation', 'testautomatisierer',
            'qualitätssicherung', 'qualitaetssicherung', 'quality assurance', 'qa', 'qs', 'istqb',
            'validation', 'verifikation', 'hil-test', 'hil test', 'hil', 'integrationstest', 'systemtest',
            'abnahmetest', 'regressionstest', 'testmanager', 'test manager', 'testkoordinator',
            'manuelles testen', 'testanalyst', 'qualitätsprüfung', 'qualitaetspruefung', 'qualitätsmanagement',
            'test lead', 'sdet', 'automation engineer', 'lasttest', 'funktionstest',
        ],
        'network' => [
            'netzwerk', 'network', 'network engineer', 'netzwerkadministrator', 'systemadministrator',
            'system administrator', 'netzwerktechniker', 'infrastruktur', 'firewall', 'cisco', 'lan', 'wan',
            'vpn', 'routing', 'switching', 'voip', 'telekommunikation',
        ],
        'cybersecurity' => [
            'cybersecurity', 'informationssicherheit', 'it-sicherheit', 'security engineer', 'soc analyst',
            'penetration test', 'ciso', 'security analyst', 'cyber security',
        ],
        'data' => [
            'data analyst', 'data scientist', 'datenanalyst', 'business intelligence', 'bi developer',
            'data engineer', 'machine learning', 'ki-entwickler', 'künstliche intelligenz', 'big data',
            'power bi', 'tableau', 'etl',
        ],
        'devops' => [
            'devops', 'site reliability', 'cloud engineer', 'kubernetes', 'aws', 'azure', 'platform engineer',
            'infrastructure engineer', 'ci/cd', 'terraform', 'docker',
        ],
        'product' => [
            'product manager', 'produktmanager', 'product owner', 'produktowner', 'product management',
        ],
        'engineering' => ['ingenieur', 'engineer', 'techniker', 'engineering'],
        'civil_engineer' => [
            'bauingenieur', 'civil engineer', 'tragwerksplaner', 'bauplaner', 'tiefbau', 'hochbau',
            'verkehrsplaner', 'ingenieur bauwesen', 'bauleiter',
        ],
        'mechanical' => [
            'maschinenbauingenieur', 'mechanical engineer', 'konstrukteur', 'maschinenbau',
            'werkzeugmaschinen', 'fertigungstechnik', 'konstruktionsingenieur',
        ],
        'electrical' => [
            'elektroingenieur', 'electrical engineer', 'elektrotechnik', 'automatisierungstechnik',
            'schaltplan', 'elektronikentwickler', 'elektrotechniker',
        ],
        'automotive' => [
            'automotive', 'automobil', 'fahrzeugtechnik', 'fahrzeugentwicklung', 'automotive engineer', 'kfz',
        ],
        'process' => [
            'verfahrenstechnik', 'process engineer', 'verfahrensingenieur', 'prozessingenieur',
        ],
        'architect' => ['architekt', 'architect', 'stadtplaner', 'innenarchitekt'],
        'healthcare' => ['gesundheit', 'healthcare', 'klinik', 'krankenhaus', 'medizin'],
        'doctor' => [
            'arzt', 'ärztin', 'facharzt', 'assistenzarzt', 'oberarzt', 'physician', 'mediziner',
            'zahnarzt', 'allgemeinmedizin', 'assistenzarzt',
        ],
        'nurse' => [
            'pflegefachkraft', 'krankenpfleger', 'gesundheits- und krankenpfleger', 'altenpfleger',
            'pflegehelfer', 'nurse', 'pflegedienst', 'pflege',
        ],
        'medical' => [
            'physiotherapeut', 'ergotherapeut', 'medizinische fachangestellte', 'mfa', 'radiologie',
            'medizintechnik', 'hebamme', 'logopäde',
        ],
        'pharmacy' => ['pharmazeut', 'pharmazie', 'pta', 'pharmazeutisch-technisch'],
        'teacher' => [
            'lehrer', 'lehrerin', 'grundschullehrer', 'gymnasiallehrer', 'pädagoge', 'erzieher',
            'erzieherin', 'kindergarten', 'schulpädagoge', 'lehrkraft',
        ],
        'professor' => [
            'professor', 'dozent', 'wissenschaftlicher mitarbeiter', 'universität', 'hochschule',
            'forschungsassistenz', 'wissenschaftliche hilfskraft',
        ],
        'social_education' => [
            'sozialpädagoge', 'sozialarbeiter', 'jugendhilfe', 'heilerziehungspfleger', 'kita',
        ],
        'accounting' => [
            'buchhalter', 'accountant', 'controller', 'finanzbuchhalter', 'steuerfachangestellte',
            'bilanzbuchhalter', 'wirtschaftsprüfer', 'finanz',
        ],
        'hr' => [
            'personalreferent', 'hr manager', 'recruiter', 'talent acquisition', 'personalwesen',
            'people operations', 'personal',
        ],
        'marketing' => [
            'marketing manager', 'marketingmanager', 'online marketing', 'seo', 'social media manager',
            'brand manager', 'marketing',
        ],
        'sales' => [
            'vertrieb', 'sales manager', 'account manager', 'key account', 'außendienst', 'verkaufsleiter',
            'sales',
        ],
        'consulting' => [
            'consultant', 'berater', 'unternehmensberater', 'management consultant', 'sap berater',
        ],
        'project_manager' => [
            'projektmanager', 'project manager', 'projektleiter', 'program manager', 'scrum master',
        ],
        'customer_service' => [
            'kundenservice', 'customer service', 'kundenberater', 'call center', 'support mitarbeiter',
        ],
        'design' => [
            'grafikdesigner', 'ux designer', 'ui designer', 'product designer', 'webdesigner',
            'mediengestalter', 'designer',
        ],
        'content' => ['content writer', 'copywriter', 'texter', 'technical writer', 'redakteur'],
        'media' => ['journalist', 'mediengestalter', 'video editor', 'produktionsassistenz', 'broadcast'],
        'legal' => [
            'rechtsanwalt', 'jurist', 'legal counsel', 'paralegal', 'rechtsreferendar', 'notar',
        ],
        'public_sector' => [
            'verwaltung', 'beamter', 'öffentlicher dienst', 'sachbearbeiter', 'kommunalverwaltung',
        ],
        'science' => [
            'wissenschaftler', 'forschung', 'research scientist', 'biologe', 'chemiker', 'physiker', 'promovend',
        ],
        'laboratory' => ['laborant', 'labortechniker', 'laboratory technician', 'labormitarbeiter', 'labor'],
        'electrician' => [
            'elektriker', 'elektroniker', 'electrician', 'elektroinstallateur', 'industrieelektriker',
        ],
        'construction' => [
            'bauleiter', 'bauarbeiter', 'maurer', 'zimmerer', 'tischler', 'construction worker', 'gerüstbauer',
            'bau',
        ],
        'logistics' => [
            'logistik', 'logistics', 'speditionskaufmann', 'supply chain', 'disponent', 'lagerlogistik',
        ],
        'warehouse' => [
            'lagerist', 'warehouse', 'kommissionierer', 'staplerfahrer', 'gabelstapler', 'lagerhelfer',
        ],
        'driver' => [
            'fahrer', 'driver', 'lkw-fahrer', 'berufskraftfahrer', 'kurierfahrer', 'chauffeur',
        ],
        'production' => [
            'produktionsmitarbeiter', 'maschinenbediener', 'fertigung', 'production operator',
            'schichtführer produktion',
        ],
        'maintenance' => [
            'wartungstechniker', 'instandhaltung', 'maintenance technician', 'servicetechniker',
            'anlagenmechaniker',
        ],
        'chef' => [
            'koch', 'chef', 'köchin', 'gastronomie', 'restaurant', 'küchenchef', 'bäcker', 'konditor',
        ],
        'retail' => ['verkäufer', 'einzelhandel', 'retail', 'filialleiter', 'kassierer', 'sales assistant'],
        'hotel' => [
            'hotelfachmann', 'rezeptionist', 'hotel', 'tourismus', 'gästebetreuung', 'housekeeping',
        ],
        'admin' => [
            'sachbearbeiter', 'bürokaufmann', 'office manager', 'verwaltungsfachangestellte', 'administrator',
        ],
        'secretary' => [
            'sekretär', 'assistenz', 'office assistant', 'empfang', 'assistenz der geschäftsführung',
        ],
    ];

    /** @return array<string, array<string, string>> */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function isValid(string $slug): bool
    {
        return isset(self::KEYWORDS[$slug]);
    }

    public static function label(string $slug): string
    {
        foreach (self::GROUPS as $items) {
            if (isset($items[$slug])) {
                return $items[$slug];
            }
        }

        return $slug;
    }

    /** @return list<string> */
    public static function keywords(string $slug): array
    {
        return self::searchKeywords($slug);
    }

    /**
     * Full keyword set for a profession (umbrella merge + student terms + token splits).
     *
     * @return list<string>
     */
    public static function searchKeywords(string $slug): array
    {
        if (!isset(self::KEYWORDS[$slug])) {
            return [];
        }

        $terms = self::KEYWORDS[$slug];
        foreach (self::UMBRELLA[$slug] ?? [] as $child) {
            if (isset(self::KEYWORDS[$child])) {
                $terms = array_merge($terms, self::KEYWORDS[$child]);
            }
        }
        $terms = array_merge($terms, self::STUDENT_TERMS);

        return self::normalizeTerms($terms);
    }

    /**
     * Short tokens for optional SQL hints (top recall terms only).
     *
     * @return list<string>
     */
    public static function sqlHintTokens(string $slug): array
    {
        $terms = self::searchKeywords($slug);
        $hints = [];
        foreach ($terms as $term) {
            $term = mb_strtolower(trim($term));
            if ($term === '' || mb_strlen($term) < 3) {
                continue;
            }
            if (mb_strlen($term) <= 24) {
                $hints[] = $term;
            }
            foreach (preg_split('/[\s\-\/]+/u', $term) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '' && mb_strlen($part) >= 3) {
                    $hints[] = $part;
                }
            }
        }

        return array_slice(array_values(array_unique($hints)), 0, 40);
    }

    /** @param list<string> $terms @return list<string> */
    private static function normalizeTerms(array $terms): array
    {
        $out = [];
        $seen = [];
        foreach ($terms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }
            $key = mb_strtolower($term);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $term;
        }

        return $out;
    }
}
