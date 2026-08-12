<x-mail::message>
# Website enquiry

**{{ $fields['name'] }}**@if (! empty($fields['company'])) · {{ $fields['company'] }}@endif

{{ $fields['phone'] }}@if (! empty($fields['email'])) · {{ $fields['email'] }}@endif

<x-mail::panel>
{{ $fields['message'] }}
</x-mail::panel>

Reply to this email to answer them directly.
</x-mail::message>
