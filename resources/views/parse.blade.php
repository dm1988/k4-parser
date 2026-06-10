<!doctype html>
<html>
<head>
    <title>K4 Parser</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 p-8">
    <main class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow">
        <h1 class="text-2xl font-bold mb-4">K4 Parser</h1>

        <form method="POST" action="{{ route('parse.roster') }}" class="mb-6">
            @csrf
            <h2 class="font-semibold mb-2">Parse Roster Text</h2>
            <textarea
                name="text"
                class="w-full border rounded p-3 h-56"
                placeholder="Paste OCR text from a Jeppesen roster image..."
            >{{ old('text') }}</textarea>
            @error('text')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <button class="mt-2 px-4 py-2 bg-black text-white rounded">Parse Roster</button>
        </form>

        <div class="grid gap-6 md:grid-cols-2">
            <form method="POST" action="{{ route('parse.flight') }}">
                @csrf
                <h2 class="font-semibold mb-2">Flights Only</h2>
                <textarea name="text" class="w-full border rounded p-3 h-32"></textarea>
                <button class="mt-2 px-4 py-2 bg-slate-800 text-white rounded">Parse Flights</button>
            </form>

            <form method="POST" action="{{ route('parse.hotel') }}">
                @csrf
                <h2 class="font-semibold mb-2">Layovers Only</h2>
                <textarea name="text" class="w-full border rounded p-3 h-32"></textarea>
                <button class="mt-2 px-4 py-2 bg-slate-800 text-white rounded">Parse Layovers</button>
            </form>
        </div>

        @if(session('result'))
            <pre class="mt-6 bg-slate-900 text-green-300 p-4 rounded overflow-auto">{{ json_encode(session('result'), JSON_PRETTY_PRINT) }}</pre>
        @endif
    </main>
</body>
</html>
