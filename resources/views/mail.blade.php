{{ __('certificates::messages.mail.greeting', ['name' => $certificate->learner_name]) }}

{{ __('certificates::messages.mail.body', ['course' => $certificate->course_title]) }}

@if ($verifyUrl)
{{ __('certificates::messages.mail.verify') }}
{{ $verifyUrl }}
@endif
