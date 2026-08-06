<x-mail::message>
@if ($signerName)
# Hello {{ $signerName }},
@else
# Hello,
@endif

**{{ $requesterEmail }}** has asked you to sign **{{ $documentName }}**.

Open the secure link below to review the PDF and add your signature. You do not need an account.

<x-mail::button :url="$url">
Review &amp; sign
</x-mail::button>

@if ($expiresAt)
This link expires on {{ $expiresAt->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
