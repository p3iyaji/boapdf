<x-mail::message>
@if ($ownerName)
# Hello {{ $ownerName }},
@else
# Hello,
@endif

@if ($allComplete)
**{{ $signerLabel }}** has signed **{{ $documentName }}**. All requested signatures are now complete.
@else
**{{ $signerLabel }}** ({{ $signerEmail }}) has signed **{{ $documentName }}**.
@endif

**Progress:** {{ $signedCount }} of {{ $totalSigners }} signer{{ $totalSigners === 1 ? '' : 's' }} complete

@if ($signedAt)
Signed on {{ $signedAt->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.
@endif

<x-mail::button :url="$documentUrl">
View document
</x-mail::button>

You can also review signing progress on the [signing page]({{ $signingUrl }}).

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
