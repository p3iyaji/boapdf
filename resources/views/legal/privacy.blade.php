@extends('layouts.app')

@section('title', 'Privacy Policy - '.config('app.name'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
    <header class="mb-10">
        <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-2 text-sm font-medium text-brand-800/70 transition hover:text-brand-950">
            <x-boa-theme::mark size="sm" class="h-7 w-7" />
            {{ config('app.name') }}
        </a>
        <h1 class="font-display text-3xl font-bold tracking-tight text-brand-950 sm:text-4xl">Privacy Policy</h1>
        <p class="mt-2 text-sm text-brand-800/70">
            Effective date:
            <time datetime="{{ $effectiveDate }}">{{ \Illuminate\Support\Carbon::parse($effectiveDate)->toFormattedDateString() }}</time>
        </p>
        <p class="mt-4 rounded-lg border border-brand-900/10 bg-white/80 px-4 py-3 text-sm leading-relaxed text-brand-900/80">
            This Privacy Policy explains how {{ config('app.name') }} (“we”, “us”) collects, uses, stores, and shares information when you use our document Service. The Service is currently free for students and other users. Please also read our
            <a href="{{ route('legal.terms') }}" class="font-medium text-accent-700 underline decoration-accent-400/40 underline-offset-2 hover:text-accent-600">Terms of Use</a>.
        </p>
    </header>

    <article class="space-y-8 text-sm leading-relaxed text-brand-950/90 sm:text-[0.9375rem]">
        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">1. Who is responsible</h2>
            <p>
                The operator of {{ config('app.name') }}@if (filled($operatorName)) ({{ $operatorName }})@endif is the controller of personal data processed through the Service, unless a school, employer, or other organization directs you to use an instance they operate—in which case that organization may be the controller and this Policy describes how the software typically handles data.
            </p>
            <p>
                Privacy contact:
                <a href="mailto:{{ $contactEmail }}" class="font-medium text-accent-700 underline decoration-accent-400/40 underline-offset-2 hover:text-accent-600">{{ $contactEmail }}</a>.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">2. Information we collect</h2>
            <h3 class="font-semibold text-brand-950">Account information</h3>
            <p>When you register we collect your name, email address, and a hashed password. We also store email verification status and whether the account is active or an administrator.</p>

            <h3 class="font-semibold text-brand-950">Documents and signatures</h3>
            <p>
                We store the PDF files and derived outputs you create (merged, compressed, converted, edited, or signed documents), related metadata (such as page counts, operation type, and signature placement), and optional signature images you draw, type, or upload. Upload size limits apply (currently about {{ number_format($maxFileSizeKb / 1024, 0) }}&nbsp;MB per file unless configured otherwise).
            </p>

            <h3 class="font-semibold text-brand-950">Signature invitations</h3>
            <p>
                If you invite someone to sign, we process the invitee’s email address, the invitation token/link, and whether the request was completed. Invitees who open a link may submit a signature without creating a full account.
            </p>

            <h3 class="font-semibold text-brand-950">Technical and security data</h3>
            <p>
                We process session cookies necessary to keep you signed in and protect forms (CSRF). We may log IP address, user agent, timestamps, and similar request metadata for security, abuse prevention, and operational auditing. Administrators may review audit records of significant account and document actions.
            </p>

            <h3 class="font-semibold text-brand-950">Communications</h3>
            <p>
                We send transactional email such as email verification, password reset, and signature invitation messages. We do not sell personal information.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">3. How we use information</h2>
            <ul class="list-disc space-y-2 pl-5 text-brand-900/85">
                <li>Provide, maintain, and improve the Service features you request.</li>
                <li>Authenticate users, verify email addresses, and secure accounts.</li>
                <li>Send service-related notices (verification, resets, signature invites).</li>
                <li>Enforce the Terms of Use, prevent abuse, and investigate incidents.</li>
                <li>Comply with law and respond to lawful requests.</li>
                <li>Understand reliability of PDF operations through conversion and similar operational logs.</li>
            </ul>
            <p>
                Where privacy laws require a “legal basis,” we typically rely on: performance of a contract (providing the Service), legitimate interests (security, product integrity, limited auditing), consent (where we ask for it, such as accepting these policies at registration), and legal obligation when applicable.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">4. How we share information</h2>
            <p>We share personal data only as needed to run the Service, for example:</p>
            <ul class="list-disc space-y-2 pl-5 text-brand-900/85">
                <li><strong class="font-semibold">Infrastructure providers</strong> that host the application, database, file storage, or email delivery, under confidentiality and security obligations.</li>
                <li><strong class="font-semibold">Other users you involve</strong>, such as people you invite to sign a document (they receive the invitation and related document access needed to complete signing).</li>
                <li><strong class="font-semibold">Legal and safety</strong> disclosures when required by law or to protect rights, safety, and the integrity of the Service.</li>
            </ul>
            <p>We do not sell your personal information or your documents.</p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">5. Retention</h2>
            @if ($retentionDays > 0)
                <p>
                    Document library files are generally retained for approximately <strong class="font-semibold">{{ $retentionDays }} days</strong> from creation, after which they may be automatically deleted along with associated storage. Temporary processing files may be removed sooner (on the order of {{ $tempCleanupHours }} hours for some artifacts).
                </p>
            @else
                <p>
                    Automatic library pruning may be disabled by configuration. Temporary processing files may still be removed on a shorter schedule (on the order of {{ $tempCleanupHours }} hours for some artifacts).
                </p>
            @endif
            <p>
                Account profile data is kept while your account remains open. If you delete your account, we remove or anonymize personal identifiers and delete associated personal documents, while retaining a limited anonymized audit record of the deletion for accountability (for example that an account was closed, without keeping your original email as an active login identity).
            </p>
            <p>Security and operational logs are retained only as long as reasonably needed for security, debugging, and compliance.</p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">6. Security</h2>
            <p>
                We use industry-standard measures appropriate to a web application of this type, including hashed passwords, session-based authentication, CSRF protection, ownership checks on documents, and private storage for uploaded files served through application controls rather than public links by default. No method of transmission or storage is perfectly secure; you should avoid uploading highly sensitive material if your risk tolerance requires stronger contractual or compliance guarantees than this free Service provides.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">7. Your choices and rights</h2>
            <p>Depending on where you live, you may have rights to access, correct, delete, or export personal data, or to object to or restrict certain processing. You can:</p>
            <ul class="list-disc space-y-2 pl-5 text-brand-900/85">
                <li>Update your name and email from your profile.</li>
                <li>Change your password.</li>
                <li>Delete individual documents from your library.</li>
                <li>Delete your account from your profile (subject to limited safeguards, such as preventing removal of the last active administrator).</li>
                <li>Contact us at <a href="mailto:{{ $contactEmail }}" class="font-medium text-accent-700 underline decoration-accent-400/40 underline-offset-2 hover:text-accent-600">{{ $contactEmail }}</a> for other privacy requests.</li>
            </ul>
            <p>If an organization (such as a school) provides your access, you may also need to contact that organization.</p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">8. Cookies and similar technologies</h2>
            <p>
                We use essential cookies and session storage to authenticate you, remember your session, and protect forms. We do not use advertising trackers as part of the core Service. Third-party CDNs used for frontend libraries may set their own technical cookies according to their policies.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">9. International users and students</h2>
            <p>
                The Service may be hosted in a country different from yours. By using the Service you understand that your information may be processed in the location where our servers and providers operate, with safeguards appropriate to the transfer where required by law.
            </p>
            <p>
                The Service is intended for a broad audience, including students. We do not knowingly collect personal information from children in a way that violates applicable children’s privacy laws. If you believe a child has provided personal data without required consent, contact us and we will take appropriate steps to delete it.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">10. Changes to this Policy</h2>
            <p>
                We may update this Privacy Policy as the Service evolves (including if free plans later become paid). We will revise the effective date and, for material changes, provide notice through the Service or email when practical.
            </p>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-xl font-semibold text-brand-950">11. Contact</h2>
            <p>
                Privacy questions or requests:
                <a href="mailto:{{ $contactEmail }}" class="font-medium text-accent-700 underline decoration-accent-400/40 underline-offset-2 hover:text-accent-600">{{ $contactEmail }}</a>.
            </p>
            <p class="text-brand-800/70">
                This Policy describes our practices for {{ config('app.name') }}. It is not legal advice. If you need jurisdiction-specific compliance (GDPR DPIAs, HIPAA, FERPA, etc.), consult qualified counsel before relying on the Service for regulated data.
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
