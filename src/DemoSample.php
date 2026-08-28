<?php

declare(strict_types=1);

/** Static sample data for the public /demo page (no database). */
final class DemoSample
{
    /** Demo job cards use BA-style listings only (no other board names). */
    public const JOB_SOURCE_LABEL = 'Bundesagentur für Arbeit';

    public static function persona(): array
    {
        return [
            'name' => 'Aisha Khan',
            'title' => 'Working Student | IT · QA · Hamburg',
            'email' => 'aisha.khan@example.com',
        ];
    }

    /** @return array<string, int> */
    public static function applicationCounts(): array
    {
        return [
            'all' => 6,
            'applied' => 3,
            'interview' => 1,
            'offer' => 1,
            'rejected' => 1,
            'custom' => 0,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function applications(): array
    {
        return [
            [
                'company' => 'Wind Energy Solutions GmbH',
                'role' => 'Working student IT Strategic Sourcing',
                'location' => 'Hamburg, Germany',
                'status' => 'interview',
                'applied_date' => '2026-08-20',
                'resume_id' => 12,
                'cover_id' => 12,
            ],
            [
                'company' => 'Software Partner Berlin GmbH',
                'role' => 'Software Quality Engineer',
                'location' => 'Hamburg, Germany',
                'status' => 'applied',
                'applied_date' => '2026-08-22',
                'resume_id' => 11,
                'cover_id' => 11,
            ],
            [
                'company' => 'Retail IT Nord GmbH',
                'role' => 'Werkstudent IT Support',
                'location' => 'Burgwedel, Germany',
                'status' => 'applied',
                'applied_date' => '2026-08-24',
                'resume_id' => 10,
                'cover_id' => 10,
            ],
            [
                'company' => 'Health Tech GmbH',
                'role' => 'Quality Assurance Specialist',
                'location' => 'Munich, Germany',
                'status' => 'offer',
                'applied_date' => '2026-08-10',
                'resume_id' => 8,
                'cover_id' => 8,
            ],
            [
                'company' => 'Personaldienst IT AG',
                'role' => '1st & 2nd Level IT Support',
                'location' => 'Hamburg, Germany',
                'status' => 'rejected',
                'applied_date' => '2026-08-05',
                'resume_id' => 7,
                'cover_id' => 7,
            ],
            [
                'company' => 'Bau & Technik SE',
                'role' => 'Testingenieur Automation & Manual Testing',
                'location' => 'Cologne, Germany',
                'status' => 'applied',
                'applied_date' => '2026-08-26',
                'resume_id' => 13,
                'cover_id' => 13,
            ],
        ];
    }

    /** @return list<array<string, string>> */
    public static function jobs(): array
    {
        $src = self::JOB_SOURCE_LABEL;
        return [
            [
                'title' => 'Working student (m/f/d) IT Strategic Sourcing',
                'company' => 'Wind Energy Solutions GmbH',
                'city' => 'Hamburg',
                'source' => $src,
                'work_mode' => 'hybrid',
                'fit' => 'Good fit',
                'posted' => '2 days ago',
                'student' => '1',
                'city_filter' => 'hamburg',
                'match' => '1',
            ],
            [
                'title' => 'Software Test Automation Engineer (d/f/m)',
                'company' => 'Optik Systems AG',
                'city' => 'Wetzlar',
                'source' => $src,
                'work_mode' => 'onsite',
                'fit' => 'Good fit',
                'posted' => '3 days ago',
                'student' => '0',
                'city_filter' => 'other',
                'match' => '1',
            ],
            [
                'title' => 'Werkstudent QA / Manual Testing',
                'company' => 'Fashion Online GmbH',
                'city' => 'Hamburg',
                'source' => $src,
                'work_mode' => 'hybrid',
                'fit' => 'Strong fit',
                'posted' => '1 day ago',
                'student' => '1',
                'city_filter' => 'hamburg',
                'match' => '1',
            ],
            [
                'title' => 'Minijob Verkäufer (m/w/d)',
                'company' => 'City Fashion KG',
                'city' => 'Berlin',
                'source' => $src,
                'work_mode' => 'onsite',
                'fit' => '',
                'posted' => 'Today',
                'student' => '0',
                'city_filter' => 'berlin',
                'match' => '0',
            ],
            [
                'title' => 'Working Student Global Sourcing',
                'company' => 'Wind Energy Solutions GmbH',
                'city' => 'Rostock',
                'source' => $src,
                'work_mode' => 'onsite',
                'fit' => 'Good fit',
                'posted' => '4 days ago',
                'student' => '1',
                'city_filter' => 'other',
                'match' => '1',
            ],
            [
                'title' => 'Quality Assurance Specialist (m/f/d)',
                'company' => 'Health Tech GmbH',
                'city' => 'Munich',
                'source' => $src,
                'work_mode' => 'remote',
                'fit' => 'Good fit',
                'posted' => '5 days ago',
                'student' => '0',
                'city_filter' => 'other',
                'match' => '1',
            ],
            [
                'title' => 'Werkstudent Product Support',
                'company' => 'PropTech Hamburg GmbH',
                'city' => 'Hamburg',
                'source' => $src,
                'work_mode' => 'hybrid',
                'fit' => 'Fair fit',
                'posted' => '6 days ago',
                'student' => '1',
                'city_filter' => 'hamburg',
                'match' => '1',
            ],
            [
                'title' => 'Junior QA Engineer',
                'company' => 'Software Partner Berlin GmbH',
                'city' => 'Berlin',
                'source' => $src,
                'work_mode' => 'hybrid',
                'fit' => 'Good fit',
                'posted' => '1 week ago',
                'student' => '0',
                'city_filter' => 'berlin',
                'match' => '1',
            ],
        ];
    }

  /** @return array<string, string> */
    public static function tailorDefaults(): array
    {
        return [
            'company' => 'Wind Energy Solutions GmbH',
            'role' => 'Working student IT Strategic Sourcing',
            'location' => 'Hamburg, Germany',
            'jd' => "Working student (m/f/d) IT Strategic Sourcing — Hamburg\n\n"
                . "Support the strategic sourcing team with supplier coordination, process documentation, and data analysis in Excel.\n\n"
                . "Requirements:\n"
                . "• Enrolled student (Business, IT, or related)\n"
                . "• MS Office, especially Excel\n"
                . "• Good English; German B1+\n"
                . "• Structured, detail-oriented working style\n"
                . "• Interest in procurement / IT sourcing",
        ];
    }

    /** @return array{summary: string, skills: string, cover: string} */
    public static function tailorPreview(): array
    {
        return [
            'summary' => 'Computer Science student in Hamburg with hands-on QA and IT support experience. '
                . 'Comfortable with requirements analysis, Jira/Confluence, and structured reporting. '
                . 'Seeking a Working Student role in IT sourcing or quality.',
            'skills' => "MS Excel · MS Office · Jira · Confluence\n"
                . "Manual Testing · API Testing · Defect tracking\n"
                . "Requirements analysis · Process documentation\n"
                . "English — fluent · German — B1",
            'cover' => "Dear Hiring Team,\n\n"
                . "I am writing to apply for the Working student IT Strategic Sourcing role in Hamburg. "
                . "I am studying Computer Science and bring structured documentation habits from QA work, "
                . "plus strong Excel skills for supplier and process tracking.\n\n"
                . "I would welcome the chance to support your sourcing team.\n\n"
                . "Sincerely,\nAisha Khan",
        ];
    }
}
