#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Generate original KaamFit interview question packs (~650).
 * Run once: php bin/interview_generate_seeds.php
 */

$root = dirname(__DIR__);
$base = $root . '/data/interview/v1';

$mk = static function (
    string $slug,
    string $question,
    string $type,
    array $extra = []
): array {
    $row = array_merge([
        'slug' => $slug,
        'language' => 'en',
        'question' => $question,
        'question_type' => $type,
        'difficulty' => 'medium',
        'source_type' => 'kaamfit_original',
        'source_name' => 'KaamFit',
        'license' => 'proprietary',
        'status' => 'published',
        'why_asked' => 'Interviewers use this to understand how you think and how you would perform in the role.',
        'strong_answer_covers' => [
            'Clear structure',
            'Concrete example or method',
            'Outcome or learning',
            'Relevance to the role',
        ],
        'answer_framework' => $type === 'behavioral'
            ? ['Situation', 'Task', 'Action', 'Result']
            : ['Context', 'Approach', 'Trade-offs', 'Outcome'],
        'common_mistakes' => 'Being vague, blaming others, or ignoring the role context.',
        'example_answer' => 'Share a concise, role-relevant example with a clear outcome and what you would do next.',
    ], $extra);
    return $row;
};

$write = static function (string $pack, array $questions) use ($base): void {
    $dir = $base . '/' . $pack;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents(
        $dir . '/questions.json',
        json_encode(['questions' => array_values($questions)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    echo $pack . ': ' . count($questions) . "\n";
};

// --- Universal HR (50) ---
$hr = [];
$hrQs = [
    'Tell me about yourself.',
    'Why are you interested in this position?',
    'Why do you want to work for this company?',
    'Why this industry?',
    'Where do you see yourself in three to five years?',
    'What motivates you at work?',
    'What are your greatest strengths?',
    'What is an area you are actively developing?',
    'What is your availability to start?',
    'What are your salary expectations?',
    'How do you prefer to receive feedback?',
    'Describe your ideal working environment.',
    'How do you prioritize when everything feels urgent?',
    'What questions do you have for us?',
    'Why are you leaving your current role or studies?',
    'How do you stay organized across multiple tasks?',
    'Describe a time you had to learn something quickly.',
    'How do you handle ambiguity?',
    'What does good collaboration mean to you?',
    'How do you prepare for a new role in the first 90 days?',
    'What kind of manager helps you do your best work?',
    'How do you balance quality and speed?',
    'Tell us about a goal you set and achieved.',
    'How do you handle repetitive tasks without losing focus?',
    'What values matter most to you at work?',
    'Describe your communication style with stakeholders.',
    'How do you decide when to escalate an issue?',
    'What makes you a reliable teammate?',
    'How do you manage competing deadlines?',
    'Describe a professional success you are proud of.',
    'How do you approach continuous learning?',
    'What role do you usually take in a team?',
    'How do you handle constructive criticism?',
    'What would your colleagues say about working with you?',
    'How do you stay calm under pressure?',
    'Describe how you build trust with new colleagues.',
    'How do you ensure you understand requirements clearly?',
    'What is your approach to documentation?',
    'How do you measure your own performance?',
    'Tell us about a time you supported a colleague.',
    'How do you handle work-life balance during busy periods?',
    'What attracts you to this location or work model?',
    'How do you prepare for meetings?',
    'Describe a time you changed your mind based on new information.',
    'How do you handle confidential information?',
    'What tools help you stay productive?',
    'How do you approach onboarding into a new team?',
    'What is something you wish you had done differently in a past role?',
    'How do you contribute to a positive team culture?',
    'Why should we hire you for this role?',
];
foreach ($hrQs as $i => $q) {
    $hr[] = $mk('hr-' . ($i + 1), $q, 'general', [
        'is_universal' => true,
        'category' => 'general_hr',
        'difficulty' => $i < 10 ? 'easy' : 'medium',
        'skills' => ['communication', 'adaptability'],
        'stages' => ['hr_screen', 'hiring_manager'],
        'levels' => ['internship', 'working_student', 'junior', 'mid', 'senior'],
    ]);
}
$write('universal', array_merge($hr, []));

// Behavioral 50 — append to universal file
$beh = [];
$behQs = [
    ['Describe a time you resolved a conflict in a team.', ['conflict-resolution', 'teamwork']],
    ['Tell me about a time you failed and what you learned.', ['adaptability', 'problem-solving']],
    ['Describe a time you led without formal authority.', ['leadership', 'communication']],
    ['Tell me about a deadline you nearly missed and how you recovered.', ['time-management', 'problem-solving']],
    ['Describe a time you received difficult feedback.', ['adaptability', 'communication']],
    ['Tell me about a time you persuaded someone to change approach.', ['negotiation', 'stakeholder-management']],
    ['Describe working with a difficult stakeholder.', ['stakeholder-management', 'communication']],
    ['Tell me about a time you improved a process.', ['analytical-thinking', 'problem-solving']],
    ['Describe a high-pressure situation and how you stayed effective.', ['adaptability', 'time-management']],
    ['Tell me about a time you had to say no.', ['communication', 'stakeholder-management']],
    ['Describe a time you mentored or coached someone.', ['leadership', 'communication']],
    ['Tell me about collaborating across departments.', ['teamwork', 'stakeholder-management']],
    ['Describe a time you owned a mistake publicly.', ['communication', 'adaptability']],
    ['Tell me about delivering bad news professionally.', ['communication', 'stakeholder-management']],
    ['Describe a time you managed conflicting priorities.', ['time-management', 'problem-solving']],
    ['Tell me about a successful negotiation.', ['negotiation', 'communication']],
    ['Describe adapting to a major change at work or study.', ['adaptability', 'problem-solving']],
    ['Tell me about a time you went beyond your job description.', ['leadership', 'teamwork']],
    ['Describe handling an unhappy customer or client.', ['customer-service', 'problem-solving']],
    ['Tell me about a project that did not go as planned.', ['problem-solving', 'adaptability']],
    ['Describe building rapport with a remote teammate.', ['communication', 'teamwork']],
    ['Tell me about a time you used data to make a decision.', ['analytical-thinking', 'problem-solving']],
    ['Describe a time you protected quality under time pressure.', ['quality-assurance', 'time-management']],
    ['Tell me about resolving ambiguity in requirements.', ['communication', 'analytical-thinking']],
    ['Describe motivating a demotivated teammate.', ['leadership', 'teamwork']],
    ['Tell me about a time you challenged the status quo.', ['leadership', 'analytical-thinking']],
    ['Describe coordinating people with different working styles.', ['teamwork', 'communication']],
    ['Tell me about recovering after a public setback.', ['adaptability', 'communication']],
    ['Describe a time you balanced stakeholder opinions.', ['stakeholder-management', 'negotiation']],
    ['Tell me about learning from a strong mentor.', ['adaptability', 'communication']],
    ['Describe keeping a project on track when resources shrank.', ['project-management', 'problem-solving']],
    ['Tell me about a time you handled confidential conflict.', ['conflict-resolution', 'communication']],
    ['Describe presenting complex information simply.', ['communication', 'analytical-thinking']],
    ['Tell me about taking initiative on a risk.', ['leadership', 'risk-management']],
    ['Describe supporting a teammate through a tough deadline.', ['teamwork', 'time-management']],
    ['Tell me about a time culture or language differences mattered.', ['communication', 'adaptability']],
    ['Describe declining scope without damaging the relationship.', ['negotiation', 'stakeholder-management']],
    ['Tell me about measuring success of your work.', ['analytical-thinking', 'project-management']],
    ['Describe a time you asked for help early enough.', ['communication', 'teamwork']],
    ['Tell me about improving how your team communicated.', ['communication', 'leadership']],
    ['Describe handling unfair criticism.', ['conflict-resolution', 'adaptability']],
    ['Tell me about a time you represented your team externally.', ['communication', 'stakeholder-management']],
    ['Describe switching context between many tasks in one day.', ['time-management', 'adaptability']],
    ['Tell me about documenting a decision for future teams.', ['communication', 'project-management']],
    ['Describe a time you aligned on definition of done.', ['quality-assurance', 'communication']],
    ['Tell me about recovering customer trust.', ['customer-service', 'problem-solving']],
    ['Describe planning your week when priorities shifted daily.', ['time-management', 'adaptability']],
    ['Tell me about a cross-cultural misunderstanding you resolved.', ['communication', 'conflict-resolution']],
    ['Describe celebrating a team win without taking all credit.', ['leadership', 'teamwork']],
    ['Tell me about staying ethical when pressured to cut corners.', ['compliance', 'communication']],
];
foreach ($behQs as $i => [$q, $skills]) {
    $beh[] = $mk('beh-' . ($i + 1), $q, 'behavioral', [
        'is_universal' => true,
        'category' => 'behavioral',
        'skills' => $skills,
        'stages' => ['hr_screen', 'hiring_manager', 'final_interview'],
        'levels' => ['junior', 'mid', 'senior', 'lead', 'manager'],
    ]);
}
$write('universal', array_merge($hr, $beh));

$packs = [
    'business' => [
        'industry' => 'business',
        'occupation' => 'project-manager',
        'skills' => ['project-management', 'stakeholder-management', 'communication'],
        'qs' => [
            ['How do you kick off a new project with unclear requirements?', 'practical'],
            ['How do you track progress without micromanaging?', 'practical'],
            ['What would you do if two stakeholders disagreed on scope?', 'situational'],
            ['How do you run an effective status meeting?', 'practical'],
            ['Describe how you manage project risks.', 'technical'],
            ['How do you prioritize backlog items with limited capacity?', 'practical'],
            ['What metrics do you use to report project health?', 'technical'],
            ['How do you handle a delayed dependency from another team?', 'situational'],
            ['Describe your approach to change requests mid-project.', 'practical'],
            ['How do you document decisions for auditability?', 'practical'],
            ['What makes a RACI useful in your experience?', 'technical'],
            ['How do you onboard a new team member mid-project?', 'practical'],
            ['Describe closing a project and capturing lessons learned.', 'practical'],
            ['How do you communicate bad news to executives?', 'situational'],
            ['What is your approach to stakeholder mapping?', 'technical'],
            ['How do you balance waterfall expectations with agile delivery?', 'technical'],
            ['Describe managing a vendor as part of a project.', 'practical'],
            ['How do you ensure quality gates are respected?', 'practical'],
            ['What would you do if the sponsor changed priorities weekly?', 'situational'],
            ['How do you facilitate conflict in a project team?', 'situational'],
            ['Describe estimating effort with incomplete information.', 'practical'],
            ['How do you protect the team from constant interruptions?', 'leadership'],
            ['What tools have you used for planning and why?', 'portfolio'],
            ['How do you align OKRs with project outcomes?', 'technical'],
            ['Describe a project post-mortem you led.', 'portfolio'],
            ['How do you handle scope creep from a powerful stakeholder?', 'situational'],
            ['What is your approach to resource leveling?', 'technical'],
            ['How do you ensure handover to operations?', 'practical'],
            ['Describe coordinating multiple workstreams.', 'practical'],
            ['How do you set expectations with a new client?', 'practical'],
            ['What would you do if a key person left mid-project?', 'situational'],
            ['How do you measure team velocity meaningfully?', 'technical'],
            ['Describe negotiating a deadline extension.', 'situational'],
            ['How do you keep remote and on-site members aligned?', 'practical'],
            ['What is your definition of project success?', 'general'],
            ['How do you prepare a steering committee pack?', 'practical'],
            ['Describe managing budget variance.', 'technical'],
            ['How do you introduce a new process without resistance?', 'leadership'],
            ['What would you do if quality issues appeared late?', 'situational'],
            ['How do you coach a junior PM?', 'leadership'],
            ['Describe using a RAID log effectively.', 'technical'],
            ['How do you decide between build vs buy?', 'case_study'],
            ['What is your approach to dependency management?', 'technical'],
            ['How do you keep stakeholders engaged between milestones?', 'practical'],
            ['Describe recovering a red project.', 'case_study'],
            ['How do you handle underperformance on your team?', 'leadership'],
            ['What questions do you ask in discovery workshops?', 'practical'],
            ['How do you ensure compliance requirements are tracked?', 'regulatory'],
            ['Describe aligning sales promises with delivery capacity.', 'situational'],
            ['How do you celebrate delivery without losing focus on next work?', 'leadership'],
        ],
    ],
];

// Helper to expand industry packs
$makePack = static function (string $pack, string $industry, string $occupation, array $skills, array $qs, array $extraTags = []) use ($mk, $write): void {
    $out = [];
    foreach ($qs as $i => $item) {
        [$q, $type] = $item;
        $out[] = $mk($pack . '-' . ($i + 1), $q, $type, array_merge([
            'industries' => [$industry],
            'occupations' => [$occupation],
            'skills' => $skills,
            'stages' => ['hiring_manager', 'technical'],
            'levels' => ['junior', 'mid', 'senior'],
            'category' => $pack,
        ], $extraTags));
    }
    $write($pack, $out);
};

foreach ($packs as $name => $cfg) {
    $makePack($name, $cfg['industry'], $cfg['occupation'], $cfg['skills'], $cfg['qs']);
}

// Finance 50
$makePack('finance', 'finance', 'financial-analyst', ['financial-analysis', 'accounting', 'analytical-thinking', 'risk-management'], [
    ['Walk me through a P&L and what you look for first.', 'technical'],
    ['How do you build a simple forecast model?', 'practical'],
    ['What would you do if actuals diverge sharply from budget?', 'situational'],
    ['Explain working capital and why it matters.', 'technical'],
    ['How do you present financial insights to non-finance stakeholders?', 'practical'],
    ['Describe reconciling accounts when numbers do not match.', 'practical'],
    ['What KPIs would you track for a subscription business?', 'case_study'],
    ['How do you ensure data quality in financial reports?', 'practical'],
    ['Explain the difference between cash and accrual accounting.', 'technical'],
    ['How do you prioritize month-end closing tasks?', 'practical'],
    ['What is your approach to variance analysis?', 'technical'],
    ['Describe a time you found an error before it reached leadership.', 'portfolio'],
    ['How do you handle confidential financial information?', 'regulatory'],
    ['What would you do if a business unit refused to share timely data?', 'situational'],
    ['Explain ROI and when it is misleading.', 'technical'],
    ['How do you support pricing decisions with analysis?', 'practical'],
    ['Describe building a dashboard for management.', 'practical'],
    ['What controls help prevent fraud in processes you know?', 'regulatory'],
    ['How do you approach auditing a process end-to-end?', 'technical'],
    ['Explain CAPEX vs OPEX with an example.', 'technical'],
    ['How do you stress-test assumptions in a model?', 'technical'],
    ['What would you do if leadership wanted an optimistic forecast only?', 'situational'],
    ['Describe collaborating with accounting during close.', 'practical'],
    ['How do you document model assumptions?', 'practical'],
    ['Explain contribution margin.', 'technical'],
    ['How do you analyze customer profitability?', 'case_study'],
    ['What is your experience with Excel for finance work?', 'portfolio'],
    ['How do you stay current with accounting standards relevant to your role?', 'regulatory'],
    ['Describe a financial recommendation you made.', 'portfolio'],
    ['How do you handle incomplete data for a board pack?', 'situational'],
    ['Explain break-even analysis.', 'technical'],
    ['How do you partner with sales on pipeline quality?', 'practical'],
    ['What risks appear in rapid growth companies?', 'technical'],
    ['Describe improving a reporting process.', 'portfolio'],
    ['How do you validate third-party financial data?', 'practical'],
    ['What is materiality and how do you apply it?', 'technical'],
    ['How do you prepare for a financial review meeting?', 'practical'],
    ['Describe supporting a due diligence request.', 'practical'],
    ['How do you communicate uncertainty in forecasts?', 'practical'],
    ['What would you do if you spotted an unethical accounting request?', 'situational'],
    ['Explain liquidity vs solvency.', 'technical'],
    ['How do you allocate shared costs fairly?', 'case_study'],
    ['Describe using SAP or similar systems in finance.', 'portfolio'],
    ['How do you prioritize ad-hoc analysis requests?', 'practical'],
    ['What metrics matter for cash flow management?', 'technical'],
    ['How do you train a junior analyst on your team standards?', 'leadership'],
    ['Describe a time deadlines and accuracy conflicted.', 'situational'],
    ['How do you approach FX risk at a high level?', 'technical'],
    ['What questions do you ask before building a new report?', 'practical'],
    ['How do you ensure audit readiness year-round?', 'regulatory'],
]);

// Marketing 50
$makePack('marketing', 'marketing', 'marketing-manager', ['digital-marketing', 'communication', 'analytical-thinking', 'seo'], [
    ['How do you define a target audience for a campaign?', 'practical'],
    ['What would you do if a campaign underperformed in week one?', 'situational'],
    ['How do you measure marketing ROI?', 'technical'],
    ['Describe building a content calendar.', 'practical'],
    ['How do you prioritize channels with a limited budget?', 'case_study'],
    ['Explain brand positioning in simple terms.', 'technical'],
    ['How do you collaborate with sales on leads?', 'practical'],
    ['What is your approach to A/B testing?', 'technical'],
    ['Describe handling negative social media comments.', 'situational'],
    ['How do you brief an agency?', 'practical'],
    ['What KPIs matter for awareness vs conversion?', 'technical'],
    ['How do you ensure brand consistency across teams?', 'practical'],
    ['Describe a campaign you are proud of.', 'portfolio'],
    ['How do you use customer insights in messaging?', 'practical'],
    ['What would you do if legal blocked a creative concept late?', 'situational'],
    ['Explain SEO basics you would apply to a landing page.', 'technical'],
    ['How do you plan a product launch marketing timeline?', 'practical'],
    ['Describe measuring email campaign effectiveness.', 'technical'],
    ['How do you handle conflicting feedback from stakeholders?', 'situational'],
    ['What is your approach to competitor analysis?', 'practical'],
    ['How do you allocate budget across always-on and campaign spend?', 'case_study'],
    ['Describe working with design and product teams.', 'practical'],
    ['How do you protect customer data in marketing tools?', 'regulatory'],
    ['What makes a strong creative brief?', 'practical'],
    ['How do you localize a campaign for a new market?', 'practical'],
    ['Describe recovering from a messaging mistake.', 'situational'],
    ['How do you report marketing results to executives?', 'practical'],
    ['What trends are shaping digital marketing in your view?', 'general'],
    ['How do you test messaging before a big spend?', 'practical'],
    ['Describe influencer or partner collaboration governance.', 'practical'],
    ['How do you balance brand building and short-term performance?', 'case_study'],
    ['What would you do if CAC rose suddenly?', 'situational'],
    ['Explain attribution challenges.', 'technical'],
    ['How do you build a persona that teams actually use?', 'practical'],
    ['Describe optimizing a conversion funnel.', 'practical'],
    ['How do you manage a content backlog?', 'practical'],
    ['What is your approach to crisis communications?', 'situational'],
    ['How do you ensure accessibility in marketing assets?', 'practical'],
    ['Describe setting OKRs for a marketing team.', 'leadership'],
    ['How do you coach a junior marketer?', 'leadership'],
    ['What tools have you used for analytics and why?', 'portfolio'],
    ['How do you align PR and performance marketing?', 'practical'],
    ['Describe a time data changed your creative direction.', 'portfolio'],
    ['How do you handle seasonality in planning?', 'practical'],
    ['What would you do if a key channel policy changed overnight?', 'situational'],
    ['How do you evaluate new marketing technology?', 'practical'],
    ['Describe protecting brand during a partnership.', 'practical'],
    ['How do you structure a quarterly marketing review?', 'practical'],
    ['What ethical lines do you refuse to cross in targeting?', 'regulatory'],
    ['How do you keep learning in a fast-changing field?', 'general'],
]);

// Logistics 50
$makePack('logistics', 'logistics', 'logistics-coordinator', ['logistics', 'supply-chain', 'warehousing', 'procurement', 'scheduling'], [
    ['How do you prioritize outbound shipments on a busy day?', 'practical'],
    ['What would you do if a carrier missed a pickup window?', 'situational'],
    ['Explain safety stock and when you increase it.', 'technical'],
    ['How do you track OTIF and act on it?', 'technical'],
    ['Describe coordinating inbound and outbound to avoid congestion.', 'practical'],
    ['What would you do if inventory counts do not match the system?', 'situational'],
    ['How do you choose between air and ocean freight?', 'case_study'],
    ['Describe communicating a delay to a customer.', 'practical'],
    ['How do you plan warehouse slotting for fast movers?', 'technical'],
    ['What KPIs matter in warehouse operations?', 'technical'],
    ['How do you handle a damaged shipment claim?', 'practical'],
    ['Describe improving a picking process.', 'portfolio'],
    ['What would you do if two urgent orders competed for the same stock?', 'situational'],
    ['How do you collaborate with procurement on lead times?', 'practical'],
    ['Explain Incoterms at a high level and why they matter.', 'technical'],
    ['How do you prepare for peak season?', 'practical'],
    ['Describe using WMS or TMS in your work.', 'portfolio'],
    ['How do you reduce transport costs without harming service?', 'case_study'],
    ['What safety practices do you enforce in a warehouse?', 'safety'],
    ['How do you manage reverse logistics?', 'practical'],
    ['Describe resolving a customs documentation issue.', 'practical'],
    ['How do you escalate chronic supplier delays?', 'situational'],
    ['What is your approach to route planning?', 'technical'],
    ['How do you ensure cold-chain integrity when relevant?', 'safety'],
    ['Describe cross-docking and when it helps.', 'technical'],
    ['How do you onboard a new carrier?', 'practical'],
    ['What would you do if a system outage hit during dispatch?', 'situational'],
    ['How do you balance inventory cost and availability?', 'case_study'],
    ['Describe root-cause analysis after a misshipment.', 'practical'],
    ['How do you communicate SLAs internally?', 'practical'],
    ['What data do you need before promising a delivery date?', 'practical'],
    ['How do you handle hazardous materials compliance at a high level?', 'regulatory'],
    ['Describe coordinating with production planning.', 'practical'],
    ['How do you measure picking accuracy?', 'technical'],
    ['What would you do if a key warehouse employee called in sick on peak day?', 'situational'],
    ['How do you document SOPs so new staff can follow them?', 'practical'],
    ['Describe a cost-saving initiative in logistics.', 'portfolio'],
    ['How do you manage multi-stop deliveries?', 'practical'],
    ['What risks appear in single-sourcing a carrier?', 'technical'],
    ['How do you prepare for an audit of warehouse processes?', 'regulatory'],
    ['Describe using Excel or dashboards for logistics KPIs.', 'portfolio'],
    ['How do you handle last-minute order changes?', 'situational'],
    ['What is your approach to continuous improvement in operations?', 'practical'],
    ['How do you coordinate returns with customer service?', 'practical'],
    ['Describe capacity planning for a distribution center.', 'technical'],
    ['How do you ensure accurate labeling and packaging?', 'practical'],
    ['What would you do if fuel surcharges suddenly rose?', 'situational'],
    ['How do you align logistics goals with sales promotions?', 'practical'],
    ['Describe mentoring a junior coordinator.', 'leadership'],
    ['How do you stay calm when multiple exceptions hit at once?', 'behavioral'],
]);

require $root . '/bin/_interview_generate_seeds_rest.php';
