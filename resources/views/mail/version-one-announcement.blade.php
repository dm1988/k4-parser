<x-mail::message>
# K4 Parser Version 1 is live

Hi {{ filled($user->name) ? \Illuminate\Support\Str::before($user->name, ' ') : 'there' }},

K4 Parser Version 1 is officially live!

The **Schedule Extractor** takes the schedule information you already receive through Jeppesen Crew Access and converts it into organized, calendar-ready events—without rebuilding your trip one leg at a time.

### What it can do

- Upload a JCA roster screenshot, multiple screenshots, or a trip PDF
- Recognize flights, deadheads, duties, and layovers
- Extract routes, flight times, local times, aircraft, tail numbers, block times, hotels, and crew details
- Filter which types of events you want to include
- Review the extracted schedule before adding it to your calendar
- Download your complete schedule as an `.ics` calendar file
- Export individual events separately

<x-mail::button :url="route('parse.index')">
Open the Schedule Extractor
</x-mail::button>

This is the first official release, so I’d love your feedback. If the extractor misses something or interprets part of your schedule incorrectly, reply to this email and let me know.

Please continue to compare extracted information against your official schedule before relying on it.

Thanks for helping test and improve K4 Parser!

Dave
</x-mail::message>
