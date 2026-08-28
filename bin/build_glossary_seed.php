<?php

declare(strict_types=1);

/** One-off builder for src/glossary/en_de.php — run: php bin/build_glossary_seed.php */

$entries = [];
$add = static function (string $en, string $de, string $cat = '') use (&$entries): void {
    $entries[] = ['en' => $en, 'de' => $de, 'category' => $cat];
};

foreach ([
    ['Experience', 'Berufserfahrung', 'heading'],
    ['Work Experience', 'Berufserfahrung', 'heading'],
    ['Professional Experience', 'Berufserfahrung', 'heading'],
    ['Education', 'Ausbildung', 'heading'],
    ['Skills', 'Kenntnisse', 'heading'],
    ['Summary', 'Profil', 'heading'],
    ['Professional Summary', 'Profil', 'heading'],
    ['Profile', 'Profil', 'heading'],
    ['Certificates', 'Zertifikate', 'heading'],
    ['Certifications', 'Zertifikate', 'heading'],
    ['Languages', 'Sprachen', 'heading'],
    ['Projects', 'Projekte', 'heading'],
    ['References', 'Referenzen', 'heading'],
    ['Interests', 'Interessen', 'heading'],
    ['Volunteer Experience', 'Ehrenamtliche Tätigkeit', 'heading'],
    ['Additional Information', 'Weitere Informationen', 'heading'],
    ['Personal Details', 'Persönliche Angaben', 'heading'],
    ['Contact', 'Kontakt', 'heading'],
] as $r) {
    $add($r[0], $r[1], $r[2]);
}

$cities = [
    'Berlin' => 'Berlin', 'Munich' => 'München', 'Hamburg' => 'Hamburg', 'Cologne' => 'Köln',
    'Frankfurt' => 'Frankfurt', 'Stuttgart' => 'Stuttgart', 'Düsseldorf' => 'Düsseldorf', 'Dortmund' => 'Dortmund',
    'Essen' => 'Essen', 'Leipzig' => 'Leipzig', 'Bremen' => 'Bremen', 'Dresden' => 'Dresden',
    'Hanover' => 'Hannover', 'Nuremberg' => 'Nürnberg', 'Duisburg' => 'Duisburg', 'Bochum' => 'Bochum',
    'Wuppertal' => 'Wuppertal', 'Bielefeld' => 'Bielefeld', 'Bonn' => 'Bonn', 'Münster' => 'Münster',
    'Karlsruhe' => 'Karlsruhe', 'Mannheim' => 'Mannheim', 'Augsburg' => 'Augsburg', 'Wiesbaden' => 'Wiesbaden',
    'Halle (Saale)' => 'Halle (Saale)', 'Rostock' => 'Rostock', 'Freiburg' => 'Freiburg', 'Kiel' => 'Kiel',
    'Mainz' => 'Mainz', 'Magdeburg' => 'Magdeburg', 'Erfurt' => 'Erfurt', 'Lübeck' => 'Lübeck',
    'Chemnitz' => 'Chemnitz', 'Braunschweig' => 'Braunschweig', 'Aachen' => 'Aachen', 'Kassel' => 'Kassel',
    'Saarbrücken' => 'Saarbrücken', 'Potsdam' => 'Potsdam', 'Regensburg' => 'Regensburg', 'Heidelberg' => 'Heidelberg',
    'Ulm' => 'Ulm', 'Ingolstadt' => 'Ingolstadt', 'Wolfsburg' => 'Wolfsburg', 'Göttingen' => 'Göttingen',
    'Schmalkalden' => 'Schmalkalden', 'Erlangen' => 'Erlangen', 'München' => 'München',
];
foreach ($cities as $en => $de) {
    $add($en . ', Germany', $de . ', Deutschland', 'location');
}
$add('Germany', 'Deutschland', 'location');
$add('Pakistan', 'Pakistan', 'location');
$add('Lahore, Punjab, Pakistan', 'Lahore, Punjab, Pakistan', 'location');

$qa = [
    ['Quality Assurance', 'Qualitätssicherung'], ['Software Quality Assurance', 'Software-Qualitätssicherung'],
    ['Software Tester', 'Softwaretester'], ['Software Testing', 'Softwaretests'],
    ['Manual Testing', 'Manuelles Testen'], ['Test Automation', 'Testautomatisierung'],
    ['Automated Testing', 'Automatisiertes Testen'], ['Regression Testing', 'Regressionstests'],
    ['Smoke Testing', 'Smoke-Tests'], ['Sanity Testing', 'Sanity-Tests'],
    ['Exploratory Testing', 'Exploratives Testen'], ['Black Box Testing', 'Black-Box-Tests'],
    ['Functional Testing', 'Funktionstests'], ['Integration Testing', 'Integrationstests'],
    ['System Testing', 'Systemtests'], ['User Acceptance Testing', 'Benutzerakzeptanztests'],
    ['UAT', 'UAT'], ['API Testing', 'API-Tests'], ['Performance Testing', 'Performancetests'],
    ['Load Testing', 'Lasttests'], ['Cross-browser Testing', 'Browserübergreifende Tests'],
    ['Cross-platform Testing', 'Plattformübergreifende Tests'], ['Defect Tracking', 'Fehlerverfolgung'],
    ['Bug Tracking', 'Bug-Tracking'], ['Test Cases', 'Testfälle'], ['Test Scenarios', 'Testszenarien'],
    ['Test Plans', 'Testpläne'], ['Test Documentation', 'Testdokumentation'],
    ['Quality Control', 'Qualitätskontrolle'], ['Process Control', 'Prozesskontrolle'],
    ['Root Cause Analysis', 'Ursachenanalyse'], ['Continuous Integration', 'Kontinuierliche Integration'],
    ['Agile', 'Agile'], ['Scrum', 'Scrum'], ['Sprint Planning', 'Sprint-Planung'],
    ['Jira', 'Jira'], ['Postman', 'Postman'], ['Selenium', 'Selenium'], ['JMeter', 'JMeter'],
    ['Mantis', 'Mantis'], ['ISTQB', 'ISTQB'],
    ['Software Development Life Cycle', 'Softwareentwicklungslebenszyklus'], ['SDLC', 'SDLC'],
    ['DevOps', 'DevOps'], ['Git', 'Git'], ['Version Control', 'Versionskontrolle'],
    ['SQL', 'SQL'], ['Database Testing', 'Datenbanktests'], ['Web Application', 'Webanwendung'],
    ['Mobile Application', 'Mobile Anwendung'], ['Desktop Application', 'Desktop-Anwendung'],
    ['Frontend', 'Frontend'], ['Backend', 'Backend'], ['Full Stack', 'Full Stack'],
    ['PHP', 'PHP'], ['JavaScript', 'JavaScript'], ['Python', 'Python'], ['Java', 'Java'],
    ['WordPress', 'WordPress'], ['HTML', 'HTML'], ['CSS', 'CSS'], ['MySQL', 'MySQL'],
    ['REST API', 'REST-API'], ['JSON', 'JSON'], ['XML', 'XML'], ['Linux', 'Linux'],
    ['Windows', 'Windows'], ['macOS', 'macOS'], ['iOS', 'iOS'], ['Android', 'Android'],
    ['Microsoft Office', 'Microsoft Office'], ['MS Office', 'MS Office'],
    ['Excel', 'Excel'], ['Word', 'Word'], ['Outlook', 'Outlook'], ['PowerPoint', 'PowerPoint'],
    ['Teamwork', 'Teamarbeit'], ['Communication Skills', 'Kommunikationsfähigkeit'],
    ['Problem Solving', 'Problemlösung'], ['Attention to Detail', 'Sorgfalt'],
    ['Analytical Skills', 'Analytische Fähigkeiten'], ['Self-motivated', 'Selbstmotiviert'],
    ['Working Student', 'Werkstudent'], ['Intern', 'Praktikant'], ['Internship', 'Praktikum'],
    ['Trainee', 'Trainee'], ['Junior', 'Junior'], ['Associate', 'Associate'], ['Senior', 'Senior'],
    ['Lead', 'Lead'], ['Manager', 'Manager'], ['Engineer', 'Ingenieur'], ['Developer', 'Entwickler'],
    ['Software Engineer', 'Softwareentwickler'], ['Web Developer', 'Webentwickler'],
    ['Data Analyst', 'Datenanalyst'], ['Business Analyst', 'Business Analyst'],
    ['Project Manager', 'Projektmanager'], ['Product Owner', 'Product Owner'], ['Scrum Master', 'Scrum Master'],
    ['Associate Software Quality Assurance Engineer', 'Associate Software Quality Assurance Engineer'],
    ['Software Quality Assurance (SQA)', 'Software-Qualitätssicherung (SQA)'],
    ['QA Engineer', 'QA-Ingenieur'], ['Test Engineer', 'Testingenieur'],
    ['Release validation', 'Release-Validierung'], ['Bug fixes', 'Fehlerbehebungen'],
    ['Defect life cycle', 'Fehlerlebenszyklus'], ['Test life cycle', 'Testlebenszyklus'],
];
foreach ($qa as $r) {
    $add($r[0], $r[1], 'qa');
}

foreach ([
    ["Bachelor's degree", 'Bachelorabschluss'], ['Bachelor of Science', 'Bachelor of Science'],
    ['Bachelor of Arts', 'Bachelor of Arts'], ["Master's degree", 'Masterabschluss'],
    ['Master of Science', 'Master of Science'], ['M.Sc.', 'M.Sc.'], ['B.Sc.', 'B.Sc.'],
    ['Computer Science', 'Informatik'], ['University of Applied Sciences', 'Fachhochschule'],
    ['Present', 'laufend'], ['Ongoing', 'laufend'], ['Graduated', 'Abschluss'],
    ['Thesis', 'Abschlussarbeit'], ['Bachelor Thesis', 'Bachelorarbeit'], ['Master Thesis', 'Masterarbeit'],
    ['Coursera', 'Coursera'], ['Udemy', 'Udemy'], ['Online Course', 'Online-Kurs'],
    ['Professional Working Proficiency', 'Berufliche Sprachkenntnisse'],
    ['Basic Working Proficiency', 'Grundlegende Sprachkenntnisse'],
    ['Native Speaker', 'Muttersprache'], ['Fluent', 'Fließend'],
    ['English', 'Englisch'], ['German', 'Deutsch'], ['Urdu', 'Urdu'], ['Hindi', 'Hindi'],
    ['Arabic', 'Arabisch'], ['French', 'Französisch'], ['Spanish', 'Spanisch'],
    ['Currently improving', 'wird derzeit verbessert'], ['Gender', 'Geschlecht'],
    ['Female', 'Weiblich'], ['Male', 'Männlich'], ['Date of birth', 'Geburtsdatum'],
    ['LinkedIn', 'LinkedIn'],
] as $r) {
    $add($r[0], $r[1], 'education');
}

foreach ([
    ['Dear Hiring Team', 'Sehr geehrtes Recruiting-Team'], ['Dear Hiring Manager', 'Sehr geehrte Damen und Herren'],
    ['Dear Sir or Madam', 'Sehr geehrte Damen und Herren'], ['Kind regards', 'Mit freundlichen Grüßen'],
    ['Best regards', 'Mit freundlichen Grüßen'], ['Sincerely', 'Mit freundlichen Grüßen'],
    ['Yours faithfully', 'Mit freundlichen Grüßen'], ['I am writing to apply', 'hiermit bewerbe ich mich'],
    ['I am applying for', 'ich bewerbe mich um'],
    ['I would welcome the opportunity', 'über die Gelegenheit würde ich mich freuen'],
    ['Thank you for your consideration', 'vielen Dank für Ihre Berücksichtigung'],
    ['Looking forward to hearing from you', 'ich freue mich auf Ihre Rückmeldung'],
    ['Available from', 'Verfügbar ab'], ['Notice period', 'Kündigungsfrist'],
    ['Salary expectations', 'Gehaltsvorstellung'], ['Cover letter', 'Anschreiben'],
    ['Resume', 'Lebenslauf'], ['Curriculum Vitae', 'Lebenslauf'], ['CV', 'Lebenslauf'],
    ['Application', 'Bewerbung'], ['Job application', 'Stellenbewerbung'],
    ['Job description', 'Stellenbeschreibung'], ['Job posting', 'Stellenanzeige'],
    ['Hiring process', 'Bewerbungsprozess'], ['Interview', 'Vorstellungsgespräch'],
    ['Phone interview', 'Telefoninterview'], ['On-site interview', 'Vor-Ort-Gespräch'],
    ['Remote work', 'Remote-Arbeit'], ['Hybrid work', 'Hybrides Arbeiten'],
    ['Full-time', 'Vollzeit'], ['Part-time', 'Teilzeit'], ['Fixed-term', 'Befristet'],
    ['Permanent position', 'Unbefristete Stelle'], ['Shift work', 'Schichtarbeit'],
    ['Three-shift system', 'Dreischichtbetrieb'],
] as $r) {
    $add($r[0], $r[1], 'cover');
}

foreach ([
    ['Production', 'Produktion'], ['Manufacturing', 'Fertigung'],
    ['Food Technology', 'Lebensmitteltechnologie'], ['Food Production', 'Lebensmittelproduktion'],
    ['Beverage Production', 'Getränkeproduktion'], ['Quality Management', 'Qualitätsmanagement'],
    ['Quality Standards', 'Qualitätsstandards'], ['Inspection', 'Prüfung'], ['Audit', 'Audit'],
    ['Compliance', 'Compliance'], ['Documentation', 'Dokumentation'],
    ['Standard Operating Procedure', 'Standardarbeitsanweisung'], ['SOP', 'SOP'],
    ['Checklist', 'Checkliste'], ['Deviation', 'Abweichung'], ['Non-conformance', 'Nichtkonformität'],
    ['Corrective Action', 'Korrekturmaßnahme'], ['Continuous Improvement', 'Kontinuierliche Verbesserung'],
    ['HACCP', 'HACCP'], ['GMP', 'GMP'], ['ISO 9001', 'ISO 9001'],
    ['Laboratory', 'Labor'], ['Chemical Laboratory', 'Chemielabor'],
    ['Food Safety', 'Lebensmittelsicherheit'], ['Hygiene', 'Hygiene'],
    ['Warehouse', 'Lager'], ['Logistics', 'Logistik'], ['Supply Chain', 'Lieferkette'],
    ['Inventory', 'Bestand'], ['Packaging', 'Verpackung'],
    ['Process parameters', 'Prozessparameter'], ['Product parameters', 'Produktparameter'],
    ['Test plans', 'Prüfpläne'], ['Production support', 'Produktionsbegleitung'],
] as $r) {
    $add($r[0], $r[1], 'production');
}

foreach ([
    ['to Present', 'bis heute'], ['Currently', 'Derzeit'], ['Responsible for', 'Verantwortlich für'],
    ['Collaborated with', 'Zusammenarbeit mit'], ['Developed', 'Entwickelt'], ['Implemented', 'Implementiert'],
    ['Designed', 'Konzipiert'], ['Executed', 'Durchgeführt'], ['Maintained', 'Pflege und Wartung von'],
    ['Improved', 'Verbessert'], ['Supported', 'Unterstützt'], ['Managed', 'Geleitet'], ['Led', 'Geleitet'],
    ['Analyzed', 'Analysiert'], ['Documented', 'Dokumentiert'], ['Tested', 'Getestet'],
    ['Verified', 'Verifiziert'], ['Validated', 'Validiert'], ['Identified', 'Identifiziert'],
    ['Resolved', 'Behoben'], ['Reported', 'Gemeldet'], ['Participated in', 'Teilnahme an'],
    ['Assisted with', 'Unterstützung bei'], ['Ensured', 'Sichergestellt'], ['Monitored', 'Überwacht'],
    ['Performed', 'Durchgeführt'], ['Created', 'Erstellt'], ['Updated', 'Aktualisiert'],
    ['Reviewed', 'Geprüft'], ['Coordinated', 'Koordiniert'],
] as $r) {
    $add($r[0], $r[1], 'phrase');
}

// Skill-line items common on tailored resumes
foreach ([
    ['Quality Assurance (QA)', 'Qualitätssicherung (QS)'],
    ['Process control & documentation', 'Prozesskontrolle & Dokumentation'],
    ['Test plans & checklists', 'Prüfpläne & Checklisten'],
    ['Defect tracking & deviation follow-up', 'Fehlerverfolgung & Abweichungsnachverfolgung'],
    ['Production support', 'Produktionsbegleitung'],
    ['MS Office (Word, Excel, Outlook)', 'MS Office (Word, Excel, Outlook)'],
    ['Cross-functional teamwork', 'Teamübergreifende Zusammenarbeit'],
    ['Structured, independent work style', 'Strukturierte, eigenverantwortliche Arbeitsweise'],
    ['Shift work readiness', 'Bereitschaft zum Schichtbetrieb'],
    ['Detail-oriented', 'Detailorientiert'],
    ['Team-oriented', 'Teamorientiert'],
] as $r) {
    $add($r[0], $r[1], 'skills');
}

$extra = require dirname(__DIR__) . '/src/glossary/en_de_batches.php';
foreach ($extra as $row) {
    if (!is_array($row)) {
        continue;
    }
    $add((string) ($row['en'] ?? ''), (string) ($row['de'] ?? ''), (string) ($row['category'] ?? ''));
}

// Tech / tools (often identical in DE CVs)
foreach ([
    'Bitbucket', 'Confluence', 'Slack', 'Teams', 'Zoom', 'Figma', 'Sketch', 'Notion', 'Trello', 'Asana',
    'Terraform', 'Ansible', 'Puppet', 'Chef', 'Grafana', 'Prometheus', 'Datadog', 'Splunk', 'New Relic',
    'SonarQube', 'Checkmarx', 'Fortify', 'Burp Suite', 'OWASP', 'Penetration Testing', 'Security Testing',
    'Accessibility Testing', 'Usability Testing', 'Localization Testing', 'Compatibility Testing',
    'End-to-End Testing', 'E2E Testing', 'Unit Testing', 'Component Testing', 'Interface Testing',
    'Mutation Testing', 'Stress Testing', 'Volume Testing', 'Scalability Testing', 'Reliability Testing',
    'SoapUI', 'REST Assured', 'Karate', 'WireMock', 'Mockito', 'JUnit 5', 'NUnit', 'xUnit', 'Mocha', 'Chai',
    'Jest', 'Vitest', 'Webpack', 'Vite', 'npm', 'yarn', 'pnpm', 'Composer', 'pip', 'Maven', 'Gradle',
    'IntelliJ IDEA', 'Visual Studio Code', 'Eclipse', 'PyCharm', 'Android Studio', 'Xcode', 'SAP',
    'Salesforce', 'HubSpot', 'Shopify', 'Magento', 'PrestaShop', 'TYPO3', 'Contao', 'Drupal', 'Joomla',
    'Tableau', 'Power BI', 'Looker', 'Snowflake', 'Databricks', 'Spark', 'Hadoop', 'Airflow', 'dbt',
    'Flink', 'Beam', 'Pandas', 'NumPy', 'TensorFlow', 'PyTorch', 'Scikit-learn', 'OpenCV', 'NLP',
    'Blockchain', 'Smart Contracts', 'Solidity', 'Ethereum', 'Web3', 'IoT', 'Embedded Systems', 'RTOS',
    'FPGA', 'VHDL', 'Verilog', 'MATLAB', 'Simulink', 'AutoCAD', 'SolidWorks', 'CATIA', 'PLC', 'SCADA',
] as $tool) {
    $add($tool, $tool, 'tech');
}

// More German cities
foreach ([
    'Flensburg', 'Rostock', 'Neubrandenburg', 'Stralsund', 'Greifswald', 'Schwerin', 'Celle', 'Goslar',
    'Göttingen', 'Detmold', 'Minden', 'Herford', 'Iserlohn', 'Unna', 'Lünen', 'Marl', 'Recklinghausen',
    'Gelsenkirchen', 'Bottrop', 'Gladbeck', 'Castrop-Rauxel', 'Dorsten', 'Velbert', 'Ratingen', 'Viersen',
    'Mönchengladbach', 'Krefeld', 'Düren', 'Aachen', 'Eschweiler', 'Stolberg', 'Bergisch Gladbach',
    'Troisdorf', 'Siegburg', 'Hürth', 'Frechen', 'Pulheim', 'Kerpen', 'Bergheim', 'Euskirchen',
    'Bonn', 'Koblenz', 'Mainz', 'Worms', 'Ludwigshafen', 'Speyer', 'Landau', 'Pirmasens', 'Zweibrücken',
    'Saarbrücken', 'Neunkirchen', 'Homburg', 'Völklingen', 'Freiburg', 'Lörrach', 'Offenburg', 'Rastatt',
    'Pforzheim', 'Heilbronn', 'Crailsheim', 'Schwäbisch Hall', 'Aalen', 'Esslingen', 'Göppingen',
    'Ravensburg', 'Friedrichshafen', 'Konstanz', 'Villingen-Schwenningen', 'Ulm', 'Memmingen', 'Kempten',
    'Rosenheim', 'Landshut', 'Passau', 'Straubing', 'Regensburg', 'Ingolstadt', 'Freising', 'Dachau',
    'Fürth', 'Erlangen', 'Bamberg', 'Coburg', 'Schweinfurt', 'Aschaffenburg', 'Würzburg', 'Bayreuth',
    'Hof', 'Weiden', 'Amberg', 'Ansbach', 'Kempten', 'Rosenheim', 'Traunstein', 'Altötting',
    'Weimar', 'Jena', 'Gera', 'Erfurt', 'Suhl', 'Nordhausen', 'Eisenach', 'Gotha', 'Mühlhausen',
    'Dessau', 'Halle', 'Magdeburg', 'Wittenberg', 'Stendal', 'Salzwedel', 'Gardelegen', 'Halberstadt',
    'Quedlinburg', 'Wernigerode', 'Goslar', 'Braunschweig', 'Wolfsburg', 'Salzgitter', 'Hildesheim',
    'Celle', 'Lüneburg', 'Stade', 'Cuxhaven', 'Wilhelmshaven', 'Emden', 'Aurich', 'Leer', 'Papenburg',
    'Meppen', 'Lingen', 'Rheine', 'Ibbenbüren', 'Bünde', 'Lübbecke', 'Bad Oeynhausen', 'Herford',
    'Bielefeld', 'Gütersloh', 'Paderborn', 'Detmold', 'Lippstadt', 'Soest', 'Arnsberg', 'Siegen',
    'Wetzlar', 'Gießen', 'Marburg', 'Fulda', 'Kassel', 'Bad Hersfeld', 'Eschwege', 'Alsfeld',
] as $city) {
    $add($city . ', Germany', $city . ', Deutschland', 'location');
}

// Resume action verbs / phrases
foreach ([
    ['Conducted', 'Durchgeführt'], ['Delivered', 'Geliefert'], ['Established', 'Etabliert'],
    ['Facilitated', 'Erleichtert'], ['Optimized', 'Optimiert'], ['Streamlined', 'Optimiert'],
    ['Automated', 'Automatisiert'], ['Configured', 'Konfiguriert'], ['Deployed', 'Bereitgestellt'],
    ['Integrated', 'Integriert'], ['Migrated', 'Migriert'], ['Refactored', 'Refaktoriert'],
    ['Debugged', 'Debuggt'], ['Troubleshot', 'Fehler behoben'], ['Investigated', 'Untersucht'],
    ['Recommended', 'Empfohlen'], ['Presented', 'Präsentiert'], ['Trained', 'Geschult'],
    ['Mentored', 'Betreut'], ['Supervised', 'Beaufsichtigt'], ['Scheduled', 'Geplant'],
    ['Prioritized', 'Priorisiert'], ['Estimated', 'Geschätzt'], ['Planned', 'Geplant'],
    ['Organized', 'Organisiert'], ['Prepared', 'Vorbereitet'], ['Processed', 'Verarbeitet'],
    ['Handled', 'Bearbeitet'], ['Resolved customer inquiries', 'Kundenanfragen bearbeitet'],
    ['Met deadlines', 'Termine eingehalten'], ['Exceeded targets', 'Ziele übertroffen'],
    ['Reduced defects', 'Fehler reduziert'], ['Increased efficiency', 'Effizienz gesteigert'],
    ['Improved quality', 'Qualität verbessert'], ['Reduced costs', 'Kosten gesenkt'],
    ['Saved time', 'Zeit gespart'], ['On time delivery', 'Termingerechte Lieferung'],
    ['Stakeholder communication', 'Stakeholder-Kommunikation'], ['Requirements gathering', 'Anforderungserhebung'],
    ['Requirements analysis', 'Anforderungsanalyse'], ['Business requirements', 'Geschäftsanforderungen'],
    ['Functional requirements', 'Funktionale Anforderungen'], ['Technical requirements', 'Technische Anforderungen'],
    ['Change management', 'Änderungsmanagement'], ['Risk management', 'Risikomanagement'],
    ['Incident management', 'Incident Management'], ['Problem management', 'Problem Management'],
    ['Service desk', 'Service Desk'], ['IT support', 'IT-Support'], ['Help desk', 'Helpdesk'],
    ['First level support', 'First-Level-Support'], ['Second level support', 'Second-Level-Support'],
    ['Third level support', 'Third-Level-Support'], ['On-call', 'Bereitschaftsdienst'],
    ['On-site support', 'Vor-Ort-Support'], ['Remote support', 'Remote-Support'],
] as $r) {
    $add($r[0], $r[1], 'phrase');
}

// German job-market / Arbeitsagentur terms
foreach ([
    ['Verkäufer', 'Salesperson', 'job'], ['Verkäuferin', 'Saleswoman', 'job'],
    ['Kassierer', 'Cashier', 'job'], ['Kassiererin', 'Cashier', 'job'],
    ['Lagerist', 'Warehouse worker', 'job'], ['Lageristin', 'Warehouse worker', 'job'],
    ['Kommissionierer', 'Order picker', 'job'], ['Fachkraft für Lagerlogistik', 'Warehouse logistics specialist', 'job'],
    ['Pflegefachkraft', 'Nurse', 'job'], ['Altenpfleger', 'Elderly care nurse', 'job'],
    ['Erzieher', 'Educator', 'job'], ['Erzieherin', 'Educator', 'job'],
    ['Mechatroniker', 'Mechatronics technician', 'job'], ['Elektroniker', 'Electronics technician', 'job'],
    ['Industriemechaniker', 'Industrial mechanic', 'job'], ['Zerspanungsmechaniker', 'Machining mechanic', 'job'],
    ['KFZ-Mechatroniker', 'Automotive mechatronics technician', 'job'],
    ['Fachinformatiker', 'IT specialist', 'job'], ['Fachinformatiker Anwendungsentwicklung', 'Application development IT specialist', 'job'],
    ['Fachinformatiker Systemintegration', 'System integration IT specialist', 'job'],
    ['Medieninformatiker', 'Media informatics specialist', 'job'],
    ['Kaufmann für Büromanagement', 'Office management clerk', 'job'],
    ['Kauffrau für Büromanagement', 'Office management clerk', 'job'],
    ['Industriekaufmann', 'Industrial clerk', 'job'], ['Industriekauffrau', 'Industrial clerk', 'job'],
    ['Bankkaufmann', 'Bank clerk', 'job'], ['Steuerfachangestellter', 'Tax assistant', 'job'],
    ['Sachbearbeiter', 'Clerk', 'job'], ['Sachbearbeiterin', 'Clerk', 'job'],
    ['Teamleiter', 'Team leader', 'job'], ['Teamleiterin', 'Team leader', 'job'],
    ['Schichtleiter', 'Shift supervisor', 'job'], ['Produktionsleiter', 'Production manager', 'job'],
    ['Qualitätsmanager', 'Quality manager', 'job'], ['Qualitätsprüfer', 'Quality inspector', 'job'],
    ['Instandhalter', 'Maintenance worker', 'job'], ['Servicetechniker', 'Service technician', 'job'],
    ['Monteur', 'Fitter', 'job'], ['Anlagenführer', 'Plant operator', 'job'],
    ['Chemielaborant', 'Chemical laboratory technician', 'job'],
    ['Pharmazeutisch-technische Assistentin', 'Pharmaceutical technical assistant', 'job'],
    ['Fachkraft für Lebensmitteltechnik', 'Food technology specialist', 'job'],
    ['Bäcker', 'Baker', 'job'], ['Metzger', 'Butcher', 'job'], ['Koch', 'Chef', 'job'],
    ['Hotelfachmann', 'Hotel specialist', 'job'], ['Restaurantfachmann', 'Restaurant specialist', 'job'],
    ['Friseur', 'Hairdresser', 'job'], ['Kosmetiker', 'Cosmetician', 'job'],
    ['Hausmeister', 'Caretaker', 'job'], ['Gebäudereiniger', 'Building cleaner', 'job'],
    ['Sicherheitsdienst', 'Security service', 'job'], ['Call Center Agent', 'Call center agent', 'job'],
    ['Minijob', 'Mini job', 'job'], ['450-Euro-Job', '450-euro job', 'job'],
    ['Werkstudent', 'Working student', 'job'], ['Praktikum', 'Internship', 'job'],
    ['Ausbildung', 'Apprenticeship', 'job'], ['Duales Studium', 'Dual study program', 'job'],
    ['Tarifgehalt', 'Collective wage', 'job'], ['Weihnachtsgeld', 'Christmas bonus', 'job'],
    ['Urlaubsgeld', 'Holiday pay', 'job'], ['Betriebliche Altersvorsorge', 'Company pension', 'job'],
    ['Arbeitsagentur', 'Federal Employment Agency', 'job'],
    ['Jobcenter', 'Job center', 'job'], ['Arbeitslosengeld', 'Unemployment benefit', 'job'],
    ['Bürgergeld', 'Citizens\' benefit', 'job'], ['Sozialversicherung', 'Social insurance', 'job'],
    ['Krankenversicherung', 'Health insurance', 'job'], ['Rentenversicherung', 'Pension insurance', 'job'],
] as $r) {
    $add($r[1], $r[0], $r[2]); // EN → DE only; seed loader adds DE → EN
}

// Certifications
foreach ([
    ['ISTQB Foundation Level', 'ISTQB Foundation Level'],
    ['ISTQB Advanced Level', 'ISTQB Advanced Level'],
    ['Certified Scrum Master', 'Certified Scrum Master'],
    ['Professional Scrum Master', 'Professional Scrum Master'],
    ['AWS Certified', 'AWS Certified'],
    ['Azure Fundamentals', 'Azure Fundamentals'],
    ['Google IT Support', 'Google IT Support'],
    ['CompTIA Security+', 'CompTIA Security+'],
    ['CEH', 'CEH'],
    ['PMP', 'PMP'],
    ['PRINCE2', 'PRINCE2'],
    ['ITIL Foundation', 'ITIL Foundation'],
    ['Six Sigma Green Belt', 'Six Sigma Green Belt'],
    ['Six Sigma Black Belt', 'Six Sigma Black Belt'],
    ['SAP Certified', 'SAP Certified'],
    ['Oracle Certified', 'Oracle Certified'],
    ['Cisco CCNA', 'Cisco CCNA'],
    ['Microsoft Certified', 'Microsoft Certified'],
] as $r) {
    $add($r[0], $r[1], 'cert');
}

// Cover letter & application phrases
foreach ([
    ['Dear Hiring Team', 'Sehr geehrtes Recruiting-Team'],
    ['Dear Hiring Manager', 'Sehr geehrte Damen und Herren'],
    ['Dear Sir or Madam', 'Sehr geehrte Damen und Herren'],
    ['To Whom It May Concern', 'Sehr geehrte Damen und Herren'],
    ['I am writing to apply for', 'hiermit bewerbe ich mich um'],
    ['I am applying for the position of', 'ich bewerbe mich um die Position als'],
    ['I am excited to apply for', 'mit großem Interesse bewerbe ich mich um'],
    ['I would like to apply for', 'ich möchte mich bewerben um'],
    ['With great interest', 'Mit großem Interesse'],
    ['I believe I am a strong fit', 'Ich bin überzeugt, dass ich gut passe'],
    ['Thank you for considering my application', 'Vielen Dank für die Prüfung meiner Bewerbung'],
    ['Thank you for your time and consideration', 'Vielen Dank für Ihre Zeit und Ihr Interesse'],
    ['I look forward to hearing from you', 'Ich freue mich auf Ihre Rückmeldung'],
    ['I look forward to your reply', 'Ich freue mich auf Ihre Antwort'],
    ['Yours sincerely', 'Mit freundlichen Grüßen'],
    ['Best regards', 'Mit freundlichen Grüßen'],
    ['Kind regards', 'Mit freundlichen Grüßen'],
    ['Sincerely', 'Mit freundlichen Grüßen'],
    ['Available from', 'Verfügbar ab'],
    ['Available immediately', 'Sofort verfügbar'],
    ['Notice period', 'Kündigungsfrist'],
    ['Salary expectations', 'Gehaltsvorstellung'],
    ['Expected salary', 'Gehaltsvorstellung'],
    ['Willing to relocate', 'Umzugsbereit'],
    ['Work permit', 'Arbeitserlaubnis'],
    ['EU work permit', 'EU-Arbeitserlaubnis'],
    ['Blue Card', 'Blue Card'],
    ['Freelance', 'Freiberuflich'],
    ['Part-time', 'Teilzeit'],
    ['Full-time', 'Vollzeit'],
    ['Hybrid work', 'Hybrides Arbeiten'],
    ['On-site', 'Vor Ort'],
    ['Cover letter', 'Anschreiben'],
    ['Application documents', 'Bewerbungsunterlagen'],
    ['Curriculum vitae', 'Lebenslauf'],
    ['Resume', 'Lebenslauf'],
    ['References available upon request', 'Referenzen auf Anfrage'],
    ['Motivation letter', 'Motivationsschreiben'],
] as $r) {
    $add($r[0], $r[1], 'cover');
}

// Education & degrees
foreach ([
    ['Bachelor of Science', 'Bachelor of Science'],
    ['Bachelor of Arts', 'Bachelor of Arts'],
    ['Master of Science', 'Master of Science'],
    ['Master of Arts', 'Master of Arts'],
    ['Master of Business Administration', 'Master of Business Administration'],
    ['MBA', 'MBA'],
    ['PhD', 'Promotion'],
    ['Doctorate', 'Promotion'],
    ['High school diploma', 'Abitur'],
    ['Abitur', 'Abitur'],
    ['Vocational training', 'Berufsausbildung'],
    ['Apprenticeship', 'Ausbildung'],
    ['University', 'Universität'],
    ['Technical university', 'Technische Universität'],
    ['University of Applied Sciences', 'Hochschule'],
    ['Community college', 'Fachhochschule'],
    ['Thesis', 'Abschlussarbeit'],
    ['Bachelor thesis', 'Bachelorarbeit'],
    ['Master thesis', 'Masterarbeit'],
    ['Graduated with honors', 'Mit Auszeichnung abgeschlossen'],
    ['Grade point average', 'Notendurchschnitt'],
    ['Exchange semester', 'Auslandssemester'],
    ['Erasmus', 'Erasmus'],
    ['Distance learning', 'Fernstudium'],
    ['Continuing education', 'Weiterbildung'],
    ['Professional development', 'Berufliche Weiterbildung'],
    ['Native speaker', 'Muttersprache'],
    ['Fluent', 'Fließend'],
    ['Advanced', 'Fortgeschritten'],
    ['Intermediate', 'Mittelstufe'],
    ['Basic', 'Grundkenntnisse'],
    ['Conversational', 'Konversationssicher'],
    ['German', 'Deutsch'],
    ['English', 'Englisch'],
    ['French', 'Französisch'],
    ['Spanish', 'Spanisch'],
    ['Italian', 'Italienisch'],
    ['Turkish', 'Türkisch'],
    ['Arabic', 'Arabisch'],
    ['Urdu', 'Urdu'],
    ['Hindi', 'Hindi'],
    ['Polish', 'Polnisch'],
    ['Russian', 'Russisch'],
    ['Portuguese', 'Portugiesisch'],
    ['Dutch', 'Niederländisch'],
    ['Present', 'heute'],
    ['Current', 'Aktuell'],
    ['Remote', 'Remote'],
    ['Contract', 'Vertrag'],
    ['Permanent position', 'Festanstellung'],
    ['Fixed-term contract', 'Befristeter Vertrag'],
    ['Temporary', 'Zeitarbeit'],
    ['Seasonal work', 'Saisonarbeit'],
    ['Student job', 'Studentenjob'],
    ['Thesis project', 'Abschlussprojekt'],
    ['Capstone project', 'Abschlussprojekt'],
    ['Open source', 'Open Source'],
    ['Side project', 'Nebenprojekt'],
    ['Personal project', 'Eigenes Projekt'],
    ['Hackathon', 'Hackathon'],
    ['Bootcamp', 'Bootcamp'],
    ['Online course', 'Online-Kurs'],
    ['Coursera', 'Coursera'],
    ['Udemy', 'Udemy'],
    ['LinkedIn Learning', 'LinkedIn Learning'],
    ['Volunteer work', 'Ehrenamt'],
    ['Community involvement', 'Ehrenamtliches Engagement'],
    ['Team player', 'Teamfähig'],
    ['Self-motivated', 'Eigeninitiativ'],
    ['Detail-oriented', 'Detailorientiert'],
    ['Analytical thinking', 'Analytisches Denken'],
    ['Problem-solving skills', 'Problemlösungskompetenz'],
    ['Communication skills', 'Kommunikationsfähigkeit'],
    ['Interpersonal skills', 'Soziale Kompetenz'],
    ['Leadership skills', 'Führungskompetenz'],
    ['Time management', 'Zeitmanagement'],
    ['Organizational skills', 'Organisationsfähigkeit'],
    ['Adaptability', 'Anpassungsfähigkeit'],
    ['Creativity', 'Kreativität'],
    ['Critical thinking', 'Kritisches Denken'],
    ['Willingness to learn', 'Lernbereitschaft'],
    ['Fast learner', 'Schnelle Auffassungsgabe'],
    ['Reliable', 'Zuverlässig'],
    ['Punctual', 'Pünktlich'],
    ['Flexible working hours', 'Flexible Arbeitszeiten'],
    ['Shift work', 'Schichtarbeit'],
    ['Night shift', 'Nachtschicht'],
    ['Weekend work', 'Wochenendarbeit'],
    ['Overtime', 'Überstunden'],
] as $r) {
    $add($r[0], $r[1], 'education');
}

// Deduplicate by normalized English phrase (keep first)
$seen = [];
$unique = [];
foreach ($entries as $e) {
    $key = mb_strtolower(trim($e['en']));
    if ($key === '' || isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    $unique[] = $e;
}
$entries = $unique;

$export = var_export($entries, true);
$php = "<?php\n\ndeclare(strict_types=1);\n\n/** EN↔DE glossary seed — loaded by TranslationGlossary::loadFromSeed() */\nreturn " . $export . ";\n";
$path = dirname(__DIR__) . '/src/glossary/en_de.php';
file_put_contents($path, $php);
echo count($entries) . " entries written to {$path}\n";
