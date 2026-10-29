@extends('layouts.app')

@section('title', 'Terms of Use - '.config('app.name'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
    <header class="mb-10">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-brand-800/70 transition hover:text-brand-950">
            <x-boa-theme::mark size="sm" class="h-7 w-7" />
            {{ config('app.name') }}
        </a>
        <h1 class="font-display text-3xl font-bold tracking-tight text-brand-950 sm:text-4xl">Terms of Use</h1>
        <p class="mt-2 text-sm text-brand-800/70">
            Effective date:
            <time datetime="{{ $effectiveDate }}">{{ \Illuminate\Support\Carbon::parse($effectiveDate)->toFormattedDateString() }}</time>
        </p>
        <p class="mt-4 rounded-lg border border-brand-900/10 bg-white/80 px-4 py-3 text-sm leading-relaxed text-brand-900/80">
            These Terms of Use govern access to and use of {{ config('app.name') }} (the “Service”), a document workspace for uploading, storing, merging, compressing, converting, and signing PDF files. The Service is currently offered <strong class="font-semibold text-brand-950">free of charge</strong> to students and the general public. Pricing or plan limits may change in the future with reasonable notice.
        </p>
    </header>

    <article class="space-y-8 text-sm leading-relaxed text-brand-950/90 sm:text-[0.9375rem]">
        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">1. Acceptance of these Terms</h2>
            <p>
                By creating an account, accessing the Service, or using a guest signature link, you agree to these Terms and our
                <a href="{{ route('legal.privacy') }}" class="font-medium text-accent-700 underline decoration-accent-400/40 underline-offset-2 hover:text-accent-600">Privacy Policy</a>.
                If you do not agree, do not use the Service.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">2. Who may use the Service</h2>
            <p>
                The Service is available to individuals for personal, educational, research, and general productivity use, including students and educators, as well as other lawful users.
            </p>
            <ul class="list-disc space-y-2 pl-5 text-brand-900/85">
                <li>You must provide accurate registration information and keep it up to date.</li>
                <li>If you are under the age of majority where you live, you may use the Service only with the consent and supervision of a parent or legal guardian who agrees to these Terms on your behalf.</li>
                <li>You may not use the Service if you are barred from doing so under applicable law.</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">3. Free access and future changes</h2>
            <p>
                At present, core features of the Service are provided without charge. We may later introduce paid plans, usage limits, fair-use controls, or feature gating. If we do, we will update these Terms and give reasonable advance notice where practical (for example via the Service or email). Continued use after the effective date of updated Terms constitutes acceptance.
            </p>
            <p>
                Free access does not guarantee uninterrupted availability, unlimited storage, unlimited processing capacity, or permanent retention of files.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">4. Your account</h2>
            <p>
                You are responsible for safeguarding your login credentials and for activity under your account. Notify us promptly at
                <a href="mailto:{{ $contactEmail }}" class="font-medium text-accent-700 underline decoration-accent-400/40 underline-offset-2 hover:text-accent-600">{{ $contactEmail }}</a>
                if you suspect unauthorized access. We may suspend or deactivate accounts that violate these Terms, pose a security risk, or remain inactive for extended periods.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">5. Your content and documents</h2>
            <p>
                You retain ownership of the documents and other materials you upload or create in the Service (“User Content”). You grant us a limited, non-exclusive license to host, process, display, transmit, and create derived versions of User Content solely as needed to operate the features you request (for example merge, compress, convert, edit, sign, invite others to sign, download, or stream).
            </p>
            <p>
                You represent that you have all rights necessary to upload and process User Content, and that your use will not infringe others’ rights or applicable law. Do not upload content you are not authorized to handle.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">6. Electronic signatures</h2>
            <p>
                The Service provides tools to apply drawn, typed, or uploaded signature images and to invite others to sign via a link. These tools are convenience features. They do <strong class="font-semibold">not</strong> constitute legal advice, and we do not warrant that a signed document will be enforceable, admissible, or compliant with any particular statute, court, regulator, or industry requirement (including electronic signature laws in your jurisdiction).
            </p>
            <p>
                You are solely responsible for determining whether electronic signatures are appropriate for your documents and for keeping any records your laws or counterparties require.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">7. Acceptable use</h2>
            <p>You agree not to:</p>
            <ul class="list-disc space-y-2 pl-5 text-brand-900/85">
                <li>Use the Service for unlawful, harmful, deceptive, or abusive purposes.</li>
                <li>Upload malware, password-protected exploits, or files intended to compromise systems.</li>
                <li>Attempt to access other users’ accounts or documents, probe vulnerabilities, or disrupt the Service.</li>
                <li>Reverse engineer, scrape at abusive rates, or overload the Service beyond normal interactive use.</li>
                <li>Misrepresent your identity when inviting or completing signatures.</li>
                <li>Use the Service to process highly regulated data in ways that violate sector-specific laws unless you have independently confirmed that your configuration and practices are lawful (for example certain health or financial records).</li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">8. Retention, backups, and deletion</h2>
            @if ($retentionDays > 0)
                <p>
                    Library documents may be automatically removed after approximately <strong class="font-semibold">{{ $retentionDays }} days</strong>, including the stored files, unless retention settings change. Temporary processing artifacts may be cleaned up on a shorter schedule. You should keep your own copies of important files.
                </p>
            @else
                <p>
                    Document retention is configured by the operator. Temporary processing artifacts may still be cleaned up on a schedule. You should keep your own copies of important files.
                </p>
            @endif
            <p>
                You may delete individual documents and may close your account from your profile, which deletes personal documents associated with the account subject to limited audit records described in the Privacy Policy.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">9. Intellectual property of the Service</h2>
            <p>
                The Service software, branding, design, and documentation are owned by {{ $operatorName ?: config('app.name') }} or its licensors. These Terms do not grant you any right to copy, modify, or redistribute the Service except as needed for normal use.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">10. Third-party tools and dependencies</h2>
            <p>
                Some features rely on server-side tools (for example PDF processing utilities) and may fail or degrade if those tools are unavailable or if a particular file is incompatible. Conversion, compression, and editing quality vary by file. The Service is provided on an “as available” basis.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">11. Disclaimers</h2>
            <p>
                TO THE MAXIMUM EXTENT PERMITTED BY LAW, THE SERVICE IS PROVIDED “AS IS” AND “AS AVAILABLE,” WITHOUT WARRANTIES OF ANY KIND, WHETHER EXPRESS, IMPLIED, OR STATUTORY, INCLUDING MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE, AND NON-INFRINGEMENT. WE DO NOT WARRANT THAT THE SERVICE WILL BE ERROR-FREE, SECURE, OR UNINTERRUPTED, OR THAT DOCUMENTS WILL BE PRESERVED WITHOUT LOSS.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">12. Limitation of liability</h2>
            <p>
                TO THE MAXIMUM EXTENT PERMITTED BY LAW, {{ $operatorName ?: config('app.name') }} AND ITS OPERATORS, CONTRIBUTORS, AND SUPPLIERS WILL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, EXEMPLARY, OR PUNITIVE DAMAGES, OR FOR ANY LOSS OF DATA, PROFITS, OR BUSINESS, ARISING FROM YOUR USE OF THE SERVICE. OUR TOTAL LIABILITY FOR ANY CLAIM RELATING TO THE SERVICE WILL NOT EXCEED THE GREATER OF (A) THE AMOUNTS YOU PAID US FOR THE SERVICE IN THE TWELVE MONTHS BEFORE THE CLAIM (CURRENTLY ZERO WHILE THE SERVICE IS FREE) OR (B) ONE HUNDRED US DOLLARS (US $100) OR THE EQUIVALENT IN LOCAL CURRENCY.
            </p>
            <p>
                Some jurisdictions do not allow certain limitations; in those places, our liability is limited to the fullest extent allowed by law. Nothing in these Terms excludes liability that cannot be excluded by law (for example for fraud or death/personal injury caused by negligence where such exclusion is prohibited).
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">13. Indemnity</h2>
            <p>
                You agree to defend and indemnify {{ $operatorName ?: config('app.name') }} and its operators against claims, damages, and expenses (including reasonable legal fees) arising from your User Content, your misuse of the Service, or your violation of these Terms or applicable law.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">14. Suspension and termination</h2>
            <p>
                You may stop using the Service at any time and may delete your account where that feature is available. We may suspend or terminate access for breach of these Terms, legal risk, or operational necessity. Provisions that by nature should survive (including ownership, disclaimers, limitations, and indemnity) will survive termination.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">15. Changes to these Terms</h2>
            <p>
                We may update these Terms from time to time. The “Effective date” at the top will change when we do. Material changes will be highlighted in the Service or communicated by email when practical. Your continued use after changes take effect means you accept the updated Terms.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">16. Governing law</h2>
            @if (filled($governingLaw))
                <p>
                    These Terms are governed by the laws of {{ $governingLaw }}, without regard to conflict-of-law principles, except where mandatory consumer protections in your place of residence apply.
                </p>
            @else
                <p>
                    These Terms are governed by the laws applicable to the operator of the Service, without regard to conflict-of-law principles, except where mandatory consumer protections in your place of residence apply. Contact us if you need the operator’s current jurisdiction details.
                </p>
            @endif
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">17. Contact</h2>
            <p>
                Questions about these Terms:
                <a href="mailto:{{ $contactEmail }}" class="font-medium text-accent-700 underline decoration-accent-400/40 underline-offset-2 hover:text-accent-600">{{ $contactEmail }}</a>@if (filled($operatorName))
                    ({{ $operatorName }})
                @endif.
            </p>
            <p class="text-brand-800/70">
                These Terms are a general template tailored to how {{ config('app.name') }} works. They are not a substitute for advice from a qualified attorney in your jurisdiction.
            </p>
        </section>
    </article>

    <footer class="mt-12 border-t border-brand-900/10 pt-6 text-sm text-brand-800/65">
        @include('partials.legal-links')
        <span class="mx-1.5 opacity-40" aria-hidden="true">·</span>
        <a href="{{ route('home') }}" class="underline decoration-brand-400/40 underline-offset-2 transition hover:text-accent-600">Home</a>
    </footer>
</div>
@endsection
