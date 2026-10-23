@props(['address'])

@if (filled($address))
    <p {{ $attributes->class(['text-sm text-[#4A5568] dark:text-slate-300']) }}>
        If this looks like a bug, email <a href="mailto:{{ $address }}?subject=Upload%20bug%20report" class="font-medium text-[#1B365D] underline underline-offset-2 hover:text-[#C5A059] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C5A059] dark:text-[#C5A059] dark:hover:text-[#F8F9FA]">{{ $address }}</a> to report it.
    </p>
@endif
