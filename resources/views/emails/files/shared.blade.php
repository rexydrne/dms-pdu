<x-mail::message>
# 📁 A File Has Been Shared With You

Hello {{ $sharedTo->name ?? 'there' }},

{{ $sharedBy->name }} has shared a file with you:
**{{ $file->name }}**

You can view or download the file using the button below:

<x-mail::button :url="$shareLink">
View File
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

