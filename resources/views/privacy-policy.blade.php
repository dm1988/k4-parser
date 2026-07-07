<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950 text-slate-100 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy | Crew Compass</title>
    <!-- Include Tailwind CSS via CDN if not compiled locally -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

</head>
<body class="min-h-full bg-slate-950 font-sans text-slate-300 selection:bg-indigo-500/30 selection:text-indigo-200">
    <main class="mx-auto max-w-3xl px-6 py-20 sm:py-24">

        <!-- Navigation Link -->
        <div class="mb-10">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-400 transition hover:text-indigo-300 group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 transition-transform group-hover:-translate-x-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to home
            </a>
        </div>

        <!-- Header -->
        <header class="border-b border-slate-800/80 pb-8">
            <h1 class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">Privacy Policy</h1>
            <p class="mt-3 text-sm text-slate-500">Last updated: 7 July 2026</p>
        </header>

        <!-- Content Policy Sections -->
        <div class="mt-12 space-y-12 text-base leading-relaxed">

            <section class="space-y-3">
                <h2 class="text-xl font-semibold text-white tracking-tight">1. Who we are</h2>
                <p>Crew Compass is a service that helps users parse and organize schedule information from uploaded documents or pasted text. This privacy policy explains how we collect, use, store, and protect your information.</p>
            </section>

            <section class="space-y-3 border-t border-slate-900 pt-10">
                <h2 class="text-xl font-semibold text-white tracking-tight">2. Information we collect</h2>
                <p>When you register and use the service, we may collect:</p>
                <ul class="list-disc space-y-3 pl-5 text-slate-400">
                    <li><span class="text-slate-300 font-medium">Account Details:</span> Your name and email address for account creation and authentication.</li>
                    <li><span class="text-slate-300 font-medium">User Content:</span> Uploaded files, pasted text, and parsed schedule data submitted for processing.</li>
                    <li><span class="text-slate-300 font-medium">Metadata:</span> Technical details such as file size, file hash, parse status, timestamps, and error information.</li>
                    <li><span class="text-slate-300 font-medium">Usage Data:</span> Basic analytics needed to maintain security and service reliability.</li>
                </ul>
            </section>

            <section class="space-y-3 border-t border-slate-900 pt-10">
                <h2 class="text-xl font-semibold text-white tracking-tight">3. Why we use your information</h2>
                <p>We use your information to provide the parser service, process uploaded schedules, generate calendar exports, maintain account security, improve reliability, and respond to support requests.</p>
            </section>

            <section class="space-y-3 border-t border-slate-900 pt-10">
                <h2 class="text-xl font-semibold text-white tracking-tight">4. Data retention</h2>
                <p>We retain account information, parse records, and related processing metadata only as long as necessary to operate the service, comply with legal obligations, or resolve disputes. If you delete your account, we will remove account-related data where feasible and stop using it for service operations.</p>
            </section>

            <section class="space-y-3 border-t border-slate-900 pt-10">
                <h2 class="text-xl font-semibold text-white tracking-tight">5. Sharing of information</h2>
                <p>We do not sell your personal data. We may share information only with trusted service providers that help us operate the platform, such as hosting, email delivery, or analytics providers, and only where required for those services.</p>
            </section>

            <section class="space-y-3 border-t border-slate-900 pt-10">
                <h2 class="text-xl font-semibold text-white tracking-tight">6. Your rights</h2>
                <p>Depending on your location, you may have the right to access, correct, delete, or restrict the processing of your personal data. You may also object to certain processing activities or request a copy of your data. To exercise these rights, contact us at <a href="mailto:crewcompasscc@gmail.com" class="text-indigo-400 underline underline-offset-4 decoration-indigo-400/30 transition hover:text-indigo-300 hover:decoration-indigo-300">crewcompasscc@gmail.com</a>.</p>
            </section>

            <section class="space-y-3 border-t border-slate-900 pt-10">
                <h2 class="text-xl font-semibold text-white tracking-tight">7. Security</h2>
                <p>We take reasonable steps to protect your data against unauthorized access, loss, or misuse. However, no internet-based service can be guaranteed to be completely secure.</p>
            </section>

            <section class="space-y-3 border-t border-slate-900 pt-10">
                <h2 class="text-xl font-semibold text-white tracking-tight">8. Contact us</h2>
                <p>If you have any questions about this privacy policy or your data, please contact us at <a href="mailto:crewcompasscc@gmail.com" class="text-indigo-400 underline underline-offset-4 decoration-indigo-400/30 transition hover:text-indigo-300 hover:decoration-indigo-300">crewcompasscc@gmail.com</a>.</p>
            </section>

        </div>
    </main>
</body>
</html>
