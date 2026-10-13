<?php

declare(strict_types=1);

namespace App\Legal;

/**
 * English version of the legal texts (12/10, client request: the English site
 * displayed the French texts).
 *
 * Faithful translations of InitialLegalTexts (privacy policy, terms of sale,
 * cookie policy) and of the terms of use / legal notice shown on the French
 * site (StaticCorporate). The French version prevails in case of discrepancy,
 * as stated at the top of each text. Same caveats as the French texts: they
 * must be reviewed by a lawyer, and the publisher's identity is still the
 * placeholder one.
 */
final class InitialLegalTextsEn
{
    private const PUBLISHER = 'TrouveMoi, a simplified joint-stock company (SAS) with a share capital of €100,000, '
        .'whose registered office is located at 28 rue de la Paix, 75002 Paris, France, registered with the Paris Trade and Companies Register '
        .'under number 123 456 789';

    private const PREVAILS = '<p><em>This is a translation provided for your convenience. In case of discrepancy, the French version prevails.</em></p>';

    public static function privacyPolicy(): string
    {
        $c = InitialLegalTexts::CONTACT;
        $t = InitialLegalTexts::TELEPHONE;

        return self::assemble([
            [
                'Who is responsible for your data',
                [
                    self::PREVAILS,
                    '<p>The data controller is '.self::PUBLISHER.' (hereinafter “TrouveMoi”, “we”), publisher of the trouvemoi.eu platform.</p>',
                    '<p>For any question about your personal data, you can write to us at <a href="mailto:'.$c.'">'.$c.'</a> or call us on '.$t.'.</p>',
                    '<p>No data protection officer has been appointed to date; this page will be updated if an appointment becomes necessary.</p>',
                ],
            ],
            [
                'The data we collect',
                [
                    '<p>We only collect the data needed to run the services you use. Depending on how you use the platform, this may include:</p>',
                    '<ul>'
                    .'<li><strong>Account</strong>: last name, first name, email address, password (stored encrypted and never readable by our teams), phone number and profile picture if you provide them;</li>'
                    .'<li><strong>Sign-in with Google, Facebook or Apple</strong>: identifier provided by the chosen service, associated name and email address. We receive neither your password, nor your contact list, nor your posts;</li>'
                    .'<li><strong>Bookings and payments</strong>: booked services, dates, number of participants, amounts and payment status. <strong>Your bank details never go through our servers</strong>: they are entered directly with our payment provider;</li>'
                    .'<li><strong>Addresses</strong>: billing address when the service requires it;</li>'
                    .'<li><strong>Activity on the platform</strong>: favourites, favourite lists, published reviews, events created or joined, groups, photo albums, messages exchanged with other members or with a provider, notification preferences;</li>'
                    .'<li><strong>Applications and requests</strong>: information sent through the contact form or the “Become a partner” application, including your company identification data;</li>'
                    .'<li><strong>Technical data</strong>: IP address, date and time of connections, pages viewed, browser type. They are used for the security of the service and to diagnose failures.</li>'
                    .'</ul>',
                ],
            ],
            [
                'Why we use them, and on what legal basis',
                [
                    '<p>The General Data Protection Regulation requires us to state, for each use, the legal basis that allows it. Ours are as follows:</p>',
                    '<table>'
                    .'<thead><tr><th>Purpose</th><th>Legal basis</th></tr></thead>'
                    .'<tbody>'
                    .'<tr><td>Creating and managing your account, authenticating you</td><td>Performance of the contract</td></tr>'
                    .'<tr><td>Processing your bookings and payments, issuing receipts</td><td>Performance of the contract</td></tr>'
                    .'<tr><td>Putting you in touch with providers and other participants</td><td>Performance of the contract</td></tr>'
                    .'<tr><td>Answering your requests and providing support</td><td>Legitimate interest in answering the people who contact us</td></tr>'
                    .'<tr><td>Publishing and moderating reviews</td><td>Legitimate interest in ensuring reliable reviews</td></tr>'
                    .'<tr><td>Sending you marketing information</td><td>Your consent, which you can withdraw at any time</td></tr>'
                    .'<tr><td>Measuring the site’s audience and improving our services</td><td>Your consent, collected through the cookie banner</td></tr>'
                    .'<tr><td>Preventing fraud and securing the platform</td><td>Legitimate interest in protecting the service and its users</td></tr>'
                    .'<tr><td>Keeping accounting records and answering requests from the authorities</td><td>Legal obligation</td></tr>'
                    .'</tbody></table>',
                    '<p>When processing is based on your consent, refusing has no consequence on access to the service: you can book an activity without accepting audience measurement or marketing communications.</p>',
                ],
            ],
            [
                'How long we keep them',
                [
                    '<ul>'
                    .'<li><strong>Account</strong>: for the whole life of the account, then three years after your last activity, after which it is deleted or anonymised;</li>'
                    .'<li><strong>Bookings and accounting records</strong>: ten years from the end of the financial year, in accordance with Article L123-22 of the French Commercial Code;</li>'
                    .'<li><strong>Published reviews</strong>: as long as they are online, then three years;</li>'
                    .'<li><strong>Messages</strong>: three years after the last exchange;</li>'
                    .'<li><strong>Proof of your acceptance of the terms</strong>: five years from the end of the relationship, so that we can establish what was accepted and when;</li>'
                    .'<li><strong>Technical logs</strong>: twelve months;</li>'
                    .'<li><strong>Your cookie choice</strong>: thirteen months, after which you are asked again.</li>'
                    .'</ul>',
                ],
            ],
            [
                'Who has access to them',
                [
                    '<p>Your data is never sold, rented or exchanged. Only the following have access to it:</p>',
                    '<ul>'
                    .'<li>TrouveMoi’s teams, to the extent required by their duties;</li>'
                    .'<li><strong>the provider whose activity you book</strong>, who receives the information needed to deliver the service — your name, the number of participants and the date. The provider is then the data controller for what it does with it;</li>'
                    .'<li><strong>the participants of an event or group</strong> you join, who see the name and picture you chose to display;</li>'
                    .'<li>our technical processors, listed below, bound by contract and with no right to use your data for themselves;</li>'
                    .'<li>administrative or judicial authorities, upon lawful request.</li>'
                    .'</ul>',
                    '<p>Our processors are: <strong>OVHcloud</strong> (hosting, France); <strong>Stripe</strong> (payment); <strong>Google</strong>, <strong>Facebook</strong> and <strong>Apple</strong>, only if you choose to sign in through them; <strong>Google Analytics</strong> (audience measurement), only if you accept it through the cookie banner; and our email delivery service.</p>',
                ],
            ],
            [
                'Transfers outside the European Union',
                [
                    '<p>Your data is hosted in France. Some of our processors, in particular for payment and third-party sign-in, may process data from the United States. These transfers are governed by the European Commission’s standard contractual clauses or by an adequacy decision.</p>',
                ],
            ],
            [
                'Your rights',
                [
                    '<p>You have the following rights over your data at any time:</p>',
                    '<ul>'
                    .'<li><strong>access</strong>: obtain confirmation that we process your data, and receive a copy of it;</li>'
                    .'<li><strong>rectification</strong>: have inaccurate information corrected;</li>'
                    .'<li><strong>erasure</strong>: request the deletion of your data, subject to our accounting and evidential obligations;</li>'
                    .'<li><strong>restriction</strong>: request that processing be frozen while a check is carried out;</li>'
                    .'<li><strong>objection</strong>: object to processing based on our legitimate interest, and to direct marketing, without having to give a reason;</li>'
                    .'<li><strong>portability</strong>: receive the data you provided to us in a machine-readable format;</li>'
                    .'<li><strong>post-mortem instructions</strong>: decide what happens to your data after your death.</li>'
                    .'</ul>',
                    '<p>To exercise them, write to <a href="mailto:'.$c.'">'.$c.'</a>. We answer within one month. Proof of identity may be requested in case of serious doubt about your identity; it is destroyed as soon as we have answered.</p>',
                    '<p>If you are not satisfied with our answer, you can lodge a complaint with the French data protection authority: CNIL, 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07, or <a href="https://www.cnil.fr" title="CNIL website">www.cnil.fr</a>.</p>',
                ],
            ],
            [
                'Security',
                [
                    '<p>Exchanges with the site are encrypted. Passwords are stored as hashes computed by a modern hashing function and cannot be recovered, not even by us. Access to data is limited to the people who need it.</p>',
                    '<p>In the event of a data breach likely to result in a high risk to your rights, you would be informed as soon as possible, in accordance with Article 34 of the Regulation.</p>',
                ],
            ],
            [
                'Minors',
                [
                    '<p>The platform is not intended for people under fifteen. If an account were created in the name of a minor under fifteen without the consent of the holder of parental authority, it would be deleted upon simple notice to <a href="mailto:'.$c.'">'.$c.'</a>.</p>',
                ],
            ],
            [
                'Cookies',
                [
                    '<p>Details of the trackers we use, their purpose and how to change your choice are set out in our <a href="/en/cookie-policy">cookie policy</a>.</p>',
                ],
            ],
            [
                'Changes to this policy',
                [
                    '<p>This policy may change with the services offered or with regulations. Each version is dated and numbered at the top of this page. In the event of a substantial change in the use of your data, you would be informed and, where the law requires it, asked for your consent again.</p>',
                ],
            ],
        ]);
    }

    public static function termsOfSale(): string
    {
        $c = InitialLegalTexts::CONTACT;
        $t = InitialLegalTexts::TELEPHONE;

        return self::assemble([
            [
                'Purpose',
                [
                    self::PREVAILS,
                    '<p>These general terms of sale govern the booking and payment, on the trouvemoi.eu platform, of leisure activities and services offered by professional providers, as well as the sale of gift vouchers.</p>',
                    '<p>They apply to any order placed on the platform. Placing an order implies unreserved acceptance of these terms, in the version in force on the day of the order.</p>',
                ],
            ],
            [
                'TrouveMoi’s role',
                [
                    '<p><strong>TrouveMoi is an intermediation platform.</strong> Unless explicitly stated otherwise, the service contract is concluded between you and the provider offering the activity, who alone is responsible for its performance, safety and compliance with the published description.</p>',
                    '<p>TrouveMoi provides the platform, collects the price on behalf of the provider and sends you the order confirmation. This determines whom to contact: a question about how the activity takes place is for the provider; a question about the order or payment is for us.</p>',
                ],
            ],
            [
                'Prices',
                [
                    '<p>Prices are shown in euros, all taxes included. They cover the service described on the activity page, excluding anything not expressly mentioned, in particular transport to the meeting point.</p>',
                    '<p>The applicable price is the one displayed when the order is confirmed. A later change in the price of an activity has no effect on orders already confirmed.</p>',
                ],
            ],
            [
                'Placing an order',
                [
                    '<p>An order follows these steps: choice of the activity, date and number of participants; review of the summary, which can be corrected until confirmation; acceptance of these terms; payment; confirmation.</p>',
                    '<p>The sale is only concluded upon receipt of payment and the sending of the confirmation by email. This confirmation summarises the service, the date, the number of participants and the amount paid; it serves as proof of the order.</p>',
                ],
            ],
            [
                'Payment',
                [
                    '<p>Payment is made online by bank card when ordering. It is processed by our provider <strong>Stripe</strong>: <strong>your bank details do not go through our servers and are never stored on them</strong>.</p>',
                    '<p>The order is deemed paid upon actual collection. If the bank refuses payment, the order is automatically cancelled.</p>',
                ],
            ],
            [
                'Gift vouchers',
                [
                    '<p>Gift vouchers are personal, valid for twelve months from their date of issue and usable on the eligible activities indicated at the time of purchase. They cannot be taken back, exchanged or refunded, including in case of loss, and no change is given if the price of the chosen service is lower than the amount of the voucher.</p>',
                    '<p>A voucher can be used several times until its amount is used up, within its period of validity.</p>',
                ],
            ],
            [
                'Right of withdrawal',
                [
                    '<p><strong>Leisure services booked for a specific date or period are not subject to the fourteen-day right of withdrawal.</strong> This exclusion is provided for in point 12 of Article L221-28 of the French Consumer Code, which covers leisure services provided on a specific date or at a specific frequency. You are reminded of it before confirming your order.</p>',
                    '<p>The fourteen-day right of withdrawal does, however, apply to the purchase of a gift voucher not assigned to a date. To exercise it, send an unambiguous statement to <a href="mailto:'.$c.'">'.$c.'</a> within fourteen days of purchase; you will be refunded within fourteen days of receipt of your request, using the same means of payment.</p>',
                ],
            ],
            [
                'Cancellation and changes',
                [
                    '<p>The cancellation terms specific to each activity are shown on its page and reminded to you before payment. Unless stated otherwise, a cancellation request sent more than forty-eight hours before the start of the service gives rise to a full refund; after that, the service remains payable.</p>',
                    '<p>Failure to show up without prior cancellation gives rise to no refund.</p>',
                    '<p>If the provider cancels the service, for whatever reason, you are refunded in full without delay, without prejudice to any other claim you may have against the provider.</p>',
                ],
            ],
            [
                'Participant’s obligations',
                [
                    '<p>You must show up at the place and time indicated, follow the provider’s safety instructions and declare that you are able to take part in the chosen activity. The provider may refuse the participation of a person who is clearly unfit or whose behaviour endangers the group, without refund.</p>',
                ],
            ],
            [
                'Liability',
                [
                    '<p>TrouveMoi is responsible for the proper functioning of the platform and for the accurate transcription of the information provided by providers. Responsibility for performing the service lies with the provider.</p>',
                    '<p>Neither party can be held liable for a failure due to force majeure within the meaning of Article 1218 of the French Civil Code.</p>',
                ],
            ],
            [
                'Complaints and mediation',
                [
                    '<p>Any complaint can be sent to <a href="mailto:'.$c.'">'.$c.'</a> or by phone on '.$t.'. We acknowledge receipt within forty-eight working hours.</p>',
                    '<p>In accordance with Article L612-1 of the French Consumer Code, you may use a consumer mediator free of charge to settle a dispute amicably, after trying to resolve it directly with us. <em>The contact details of the mediator chosen by the publisher will be published here as soon as it is appointed.</em></p>',
                    '<p>The European online dispute resolution platform is also available at <a href="https://ec.europa.eu/consumers/odr" title="Online dispute resolution">ec.europa.eu/consumers/odr</a>.</p>',
                ],
            ],
            [
                'Governing law',
                [
                    '<p>These terms are governed by French law. In the event of a dispute, the French courts have jurisdiction under the conditions laid down by the Code of Civil Procedure and the Consumer Code. These provisions do not deprive consumers of the mandatory protection granted to them by the law of their habitual residence.</p>',
                ],
            ],
        ]);
    }

    public static function cookiePolicy(): string
    {
        return self::assemble([
            [
                'What a cookie is',
                [
                    self::PREVAILS,
                    '<p>A cookie is a small file placed on your device when you visit a website. It makes it possible to recognise you from one page to the next, remember a choice or measure traffic.</p>',
                    '<p>The term here covers all trackers, whatever their technique: cookies as such, browser local storage or equivalent identifiers.</p>',
                ],
            ],
            [
                'The trackers we use',
                [
                    '<p><strong>Strictly necessary — set without your consent, because the service cannot work without them.</strong> They are not used for any advertising tracking:</p>',
                    '<ul>'
                    .'<li>a session cookie, which keeps you signed in from one page to the next;</li>'
                    .'<li>a security token, which prevents another site from submitting a form on your behalf;</li>'
                    .'<li>the <code>tm_consent</code> cookie, which stores your cookie choice. Setting it is the only way not to ask you again on every page;</li>'
                    .'<li>your language choice.</li>'
                    .'</ul>',
                    '<p><strong>Preferences</strong> — subject to your consent: remembering your search filters and display mode.</p>',
                    '<p><strong>Audience measurement</strong> — subject to your consent: <strong>Google Analytics</strong> (Google Ireland Limited) measures site traffic, the pages viewed and the bookings made, in order to improve our services. Its cookies (<code>_ga</code>, <code>_ga_*</code>) are only set after you agree and are kept for thirteen months at most. Google Analytics 4 does not store IP addresses.</p>',
                    '<p><strong>Advertising and social networks</strong> — subject to your consent. <em>No advertising tracker is used to date.</em></p>',
                ],
            ],
            [
                'Third-party trackers',
                [
                    '<p>Some features rely on external services, which set their own trackers:</p>',
                    '<ul>'
                    .'<li><strong>Stripe</strong>, during payment, to secure the transaction and prevent fraud;</li>'
                    .'<li><strong>Google</strong>, <strong>Facebook</strong> or <strong>Apple</strong>, only if you choose to sign in through them.</li>'
                    .'</ul>',
                    '<p>These services act under their own policies, over which we have no control.</p>',
                ],
            ],
            [
                'Your choice, and how to change it',
                [
                    '<p>On your first visit, a banner lets you accept or refuse optional trackers, all at once or category by category. <strong>Refusing is as easy as accepting</strong> and does not prevent you from using the site. Until you make a choice, no optional tracker is set.</p>',
                    '<p>Your choice is kept for <strong>thirteen months</strong>, the period recommended by the CNIL, after which you are asked again. You can change it at any time with the <strong>“Manage cookies”</strong> link at the bottom of every page.</p>',
                    '<p>You can also set your browser to refuse cookies or delete those already set. This is a more radical option: it may prevent you from staying signed in and from completing a payment.</p>',
                ],
            ],
            [
                'Your rights',
                [
                    '<p>The data associated with these trackers is covered by our <a href="/en/privacy-policy">privacy policy</a>, which details your rights of access, rectification, erasure and objection, and the possibility of lodging a complaint with the CNIL.</p>',
                ],
            ],
        ]);
    }

    /**
     * Terms of use — translation of StaticCorporate::cguSections().
     *
     * @return list<array{title: string, intro: string, items: list<string>, paragraphs: list<string>}>
     */
    public static function termsOfServiceSections(): array
    {
        $texts = [
            'Purpose' => 'These terms of use define the conditions of access to and use of the TrouveMoi Plaisirs & Loisirs platform, operated by TrouveMoi, as well as the rights and obligations of users. This is a translation provided for your convenience; in case of discrepancy, the French version prevails.',
            'Access to the service' => 'The platform is freely accessible to any user with Internet access. Some services may require creating a user account. Users undertake to provide accurate and up-to-date information when signing up.',
            'Use of the platform' => 'You undertake to use the platform in accordance with the law and these terms. Using the platform for illegal or fraudulent purposes, or in a way that infringes the rights of third parties, is prohibited.',
            'User accounts' => 'You are responsible for keeping your login details confidential and for all activity carried out from your account. You must inform us immediately of any unauthorised use of your account.',
            'Content and activities' => 'The information, descriptions, photos and reviews on the platform are provided by users or by our partners. TrouveMoi Plaisirs & Loisirs cannot be held responsible for the accuracy or completeness of this information.',
            'Bookings and payments' => 'Bookings made through the platform are subject to the conditions of the partner providers. Payments are secure and processed by our approved payment partners. Cancellation and refund conditions are specified before each booking.',
            'Liability' => 'TrouveMoi Plaisirs & Loisirs does its utmost to ensure the availability and reliability of the platform, but does not guarantee the absence of errors or interruptions. We cannot be held liable for any indirect damage related to the use of the platform.',
            'Personal data' => 'Your personal data is collected and processed in accordance with our Privacy Policy, available on our site.',
            'Changes to the terms' => 'TrouveMoi reserves the right to change these terms at any time. Users will be informed of any substantial change.',
            'Governing law and disputes' => 'These terms are governed by French law. In the event of a dispute, an amicable solution will be sought before any legal action.',
        ];

        $out = [];
        foreach ($texts as $title => $text) {
            $out[] = ['title' => $title, 'intro' => '', 'items' => [], 'paragraphs' => [$text]];
        }

        return $out;
    }

    /**
     * Legal notice — translation of StaticCorporate::legalSections().
     *
     * @return list<array{title: string, intro: string, items: list<string>, paragraphs: list<string>}>
     */
    public static function legalNoticeSections(): array
    {
        return [
            [
                'title' => 'Site publisher',
                'intro' => 'The TrouveMoi Plaisirs & Loisirs website is published by:',
                'items' => [
                    'TrouveMoi',
                    'Simplified joint-stock company (SAS) with a share capital of €100,000',
                    '28 rue de la Paix, 75002 Paris, France',
                    'Paris Trade and Companies Register (RCS) 123 456 789',
                    'Intra-community VAT number: FR12 123 456 789',
                    'Email: contact@trouvemoi.com',
                    'Phone: +33 7 45 15 54 51',
                ],
                'paragraphs' => [],
            ],
            [
                'title' => 'Hosting',
                'intro' => 'The website is hosted by:',
                'items' => [
                    'OVHcloud',
                    '2 rue Kellermann | 59100 Roubaix, France',
                    'Phone: +33 9 72 10 10 07',
                    'Website: www.ovhcloud.com',
                ],
                'paragraphs' => [],
            ],
            [
                'title' => 'Intellectual property',
                'intro' => '',
                'items' => [],
                'paragraphs' => ['All content on this site (texts, images, logos, icons, graphics, etc.) is the exclusive property of TrouveMoi, unless otherwise stated, and is protected by the intellectual property laws in force. Any reproduction, representation, modification, publication or adaptation of all or part of the site, by any means or process whatsoever, is prohibited without TrouveMoi’s prior written permission.'],
            ],
            [
                'title' => 'Liability',
                'intro' => '',
                'items' => [],
                'paragraphs' => ['TrouveMoi strives to provide information on the site that is as accurate as possible. However, it cannot be held responsible for omissions, inaccuracies or failures to update. Users use the information and content available on the site under their sole responsibility.'],
            ],
            [
                'title' => 'Hyperlinks',
                'intro' => '',
                'items' => [],
                'paragraphs' => ['The site may contain hyperlinks to other websites. TrouveMoi has no control over these sites and accepts no responsibility for their content.'],
            ],
            [
                'title' => 'Governing law',
                'intro' => '',
                'items' => [],
                'paragraphs' => ['This legal notice is governed by French law. In the event of a dispute, an amicable solution will be sought before any legal action. Failing that, the competent courts of Paris will have jurisdiction. This is a translation; in case of discrepancy, the French version prevails.'],
            ],
        ];
    }

    /**
     * @param list<array{0: string, 1: list<string>}> $articles
     */
    private static function assemble(array $articles): string
    {
        $parts = [];
        foreach ($articles as [$title, $paragraphs]) {
            $parts[] = '<h2>'.$title.'</h2>'.implode('', $paragraphs);
        }

        return implode("\n", $parts);
    }
}
