<?php

/*
 | Legal document copy. Available placeholders (LegalController substitutes them
 | with the values from config/legal.php): :operator, :email, :jurisdiction,
 | :date.
 |
 | Must keep the same keys as lang/es/legal.php — enforced by
 | tests/Unit/I18nDictionaryTest.php.
 */

return [

    'terms' => [
        'title' => 'Terms and conditions',
        'subtitle' => 'Conditions of use for this Spentz Trackr instance.',
        'updated' => 'Effective from :date',
        'intro' => 'This document governs the use of this Spentz Trackr instance, operated by :operator. By creating an account or using the service you accept these conditions. If you disagree with any of them, do not use the service.',
        'sections' => [

            'service' => [
                'heading' => '1. What this service is',
                'body' => [
                    'Spentz Trackr is an application for recording personal expenses and income in several currencies (USD, bolívares and USDT). It is for noting down and reviewing the movements you enter yourself.',
                    'It is a record-keeping tool, not a financial service: it does not move money, does not connect to your bank accounts, does not execute payments and takes no part in any real transaction.',
                    'The software is self-hostable. This particular instance is maintained by :operator, who is the party to contact about anything concerning the service.',
                ],
            ],

            'account' => [
                'heading' => '2. Your account',
                'body' => [
                    'You need an account to use the service. You must provide a valid email address and keep your profile information up to date.',
                    'You are responsible for keeping your password confidential and for all activity under your account. If you suspect unauthorised access, change your password immediately: doing so automatically closes every other session and revokes all issued API tokens.',
                    'You can enable two-factor authentication or passkeys from the security settings. We recommend you do.',
                    'You may not transfer your account to anyone else, nor use someone else\'s account.',
                ],
            ],

            'acceptable_use' => [
                'heading' => '3. Acceptable use',
                'body' => [
                    'You agree not to use the service for unlawful activity, nor to attempt to access other accounts\' data, disrupt the service, circumvent usage limits, or extract data in bulk by automated means.',
                    'Receipts you upload must be images of your own supporting documents. Do not upload third-party content without permission, or illegal material.',
                    ':operator may suspend or delete an account that breaches these conditions, or that puts the service or other users at risk.',
                ],
            ],

            'rates' => [
                'heading' => '4. Exchange rates',
                'body' => [
                    'The service reads exchange rates published by an external public source (ve.dolarapi.com) and stores them to convert the amounts you record.',
                    'Those rates are indicative. Their accuracy and availability are not guaranteed, nor that they match the rate applied by your bank or exchange house. You can enter a manual rate at any time.',
                    'Each movement stores the rate in force at the moment you recorded it and is never recalculated afterwards. Historical reports reflect what it was worth then, not what it would be worth today.',
                ],
            ],

            'no_advice' => [
                'heading' => '5. Not financial advice',
                'body' => [
                    'Nothing the application displays — totals, reports, budgets, savings goals or conversions — constitutes financial, tax, accounting or investment advice.',
                    'Decisions you make based on this information are your own. For any tax or accounting matter, consult a professional.',
                ],
            ],

            'availability' => [
                'heading' => '6. Availability and backups',
                'body' => [
                    'The service is provided "as is" and "as available". There may be outages due to maintenance, technical faults or causes beyond :operator\'s control.',
                    'Although :operator may take backups, recovery of lost data is not guaranteed. You can export your movements as CSV from the reports section at any time, and we recommend doing so regularly.',
                ],
            ],

            'liability' => [
                'heading' => '7. Warranties and liability',
                'body' => [
                    'The service is offered without warranties of any kind, express or implied, including merchantability, fitness for a particular purpose and freedom from error.',
                    'To the extent permitted by applicable law, :operator is not liable for indirect damages, loss of profit, loss of data, or harm arising from decisions taken on the basis of the information displayed.',
                    'Nothing in these conditions limits liabilities that cannot lawfully be excluded.',
                ],
            ],

            'termination' => [
                'heading' => '8. Termination',
                'body' => [
                    'You can delete your account at any time from the profile settings. Doing so erases your movements, categories, goals and your receipt files.',
                    'Deletion is immediate and cannot be undone. Export anything you want to keep before deleting.',
                ],
            ],

            'changes' => [
                'heading' => '9. Changes to these conditions',
                'body' => [
                    'These conditions may be updated. The effective date shown above identifies the published version.',
                    'If a change is substantial, :operator will endeavour to give reasonable notice. Continuing to use the service after a change means accepting it.',
                ],
            ],

            'law' => [
                'heading' => '10. Governing law and contact',
                'body' => [
                    'These conditions are governed by the laws of :jurisdiction.',
                    'For any question about this document, write to :email.',
                ],
            ],

        ],
    ],

    'privacy' => [
        'title' => 'Data policy',
        'subtitle' => 'What this instance stores, what for, and what you can do about it.',
        'updated' => 'Effective from :date',
        'intro' => 'This policy explains how your personal data is handled on this Spentz Trackr instance. The data controller is :operator, not the authors of the software: every instance is deployed and administered separately.',
        'sections' => [

            'what' => [
                'heading' => '1. What is stored',
                'body' => [
                    'Account data: your name, your email address, the cryptographic hash of your password — never the password itself —, your preferred language and, if you enable them, your two-factor settings and passkeys.',
                    'Financial data you enter: expenses and income with their amount, currency, date, description, notes, category, payment source, commissions, the applied exchange rate and its USD and USDT equivalents. Also your savings goals, recurring payments and monthly budget.',
                    'Receipts: the images you upload against a movement.',
                    'Technical data: a session cookie to keep you signed in, and server logs, which may include your IP address, the date and the page requested.',
                ],
            ],

            'what_not' => [
                'heading' => '2. What is NOT done with your data',
                'body' => [
                    'It is not sold, rented or handed to third parties for commercial purposes.',
                    'There is no advertising, no third-party trackers and no external analytics tools.',
                    'No profiling and no automated decisions are made about you.',
                    'Your bank accounts are never accessed: every movement is written by you.',
                ],
            ],

            'why' => [
                'heading' => '3. What it is used for',
                'body' => [
                    'To provide the service: identify you, store your movements and show you your reports.',
                    'For account security: detect and limit improper sign-in attempts, and let you close sessions.',
                    'For technical operation: diagnose errors and keep the instance running.',
                    'The legal basis is performance of the service you request when signing up, and the legitimate interest in keeping it secure.',
                ],
            ],

            'third_parties' => [
                'heading' => '4. Third parties',
                'body' => [
                    'The application makes a single outbound call: it reads the exchange rates published at ve.dolarapi.com. That request contains none of your data — it only asks for the price of the dollar.',
                    'The rest depends on how :operator deployed the instance: the hosting provider and, if configured, the mail service that sends verification and password-recovery messages will process data on the operator\'s behalf.',
                ],
            ],

            'security' => [
                'heading' => '5. How it is protected',
                'body' => [
                    'Passwords are stored with bcrypt; nobody, not even the administrator, can read them.',
                    'Each account can only see its own data: queries are scoped per user and checked by access policies.',
                    'Receipts are stored outside the server\'s public directory and are only served after checking that whoever asks owns the movement.',
                    'Changing your password closes every other session and revokes API tokens.',
                    'No system is invulnerable. Use a unique password and enable two-factor authentication.',
                ],
            ],

            'retention' => [
                'heading' => '6. How long it is kept',
                'body' => [
                    'Your data is kept for as long as the account exists.',
                    'Deleting the account erases your movements, categories, sources, goals, recurring payments and your receipt files. The operation is immediate and irreversible.',
                    'API tokens expire after 90 days. Server logs are kept for whatever period :operator has configured on their hosting.',
                ],
            ],

            'rights' => [
                'heading' => '7. Your rights',
                'body' => [
                    'Access and portability: you can see all your data in the application and export your movements as CSV from the reports section.',
                    'Rectification: you can edit or delete any movement, and change your name and email from the profile settings.',
                    'Erasure: you can delete your account and all its content from the profile settings.',
                    'To exercise any other right, or if any of the above does not work for you, write to :email.',
                ],
            ],

            'cookies' => [
                'heading' => '8. Cookies',
                'body' => [
                    'One session cookie is used, essential to keep you signed in, and one technical cookie that remembers whether you prefer the light or dark theme.',
                    'There are no advertising or tracking cookies, so no consent is requested for them.',
                ],
            ],

            'minors' => [
                'heading' => '9. Minors',
                'body' => [
                    'The service is not aimed at minors. If :operator finds an account belonging to a minor without their legal guardians\' authorisation, it will be deleted.',
                ],
            ],

            'changes' => [
                'heading' => '10. Changes and contact',
                'body' => [
                    'This policy may be updated; the effective date identifies the published version.',
                    'For any question about your data, write to :email.',
                ],
            ],

        ],
    ],

];
