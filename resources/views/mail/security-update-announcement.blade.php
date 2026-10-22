<x-mail::message>
# An update about a recent service interruption

Hi {{ filled($recipientName) ? \Illuminate\Support\Str::before($recipientName, ' ') : 'there' }},

I'm sorry K4 Parser was unavailable for about eight and a half hours during a recent security update. We took the service offline while applying fixes for two critical PHP vulnerabilities, CVE-2026-109618 and CVE-2026-109619. The updates are in place, and the service is back online.

Our review found no evidence of a server compromise or unauthorized access, and no impact to user accounts or data. You do not need to take any action. If that assessment changes, I will follow up.

### New: Flight Plan Brief

For accounts with access, Flight Plan Brief organizes a supported flight release into a route overview and focused views of the available fuel, weather, and weight &amp; balance information. You can return to your latest release and use the waypoint fuel calculator offline. Please compare extracted details with the source flight release before relying on them.

Schedule Extractor also has clearer reserve duty labels and new flight tracker preferences for schedule links and calendar exports.

<x-mail::button :url="route('welcome')">
Explore K4 Parser
</x-mail::button>

If you have questions about the interruption or the new features, reply to this email.

Dave
</x-mail::message>
