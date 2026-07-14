<form method="POST" action="{{ route('parse.roster') }}" enctype="multipart/form-data" class="cc-card" id="parserForm">
    @csrf

    <div class="border-b border-[#1B365D]/10 p-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="flex-1">
                <label for="file" class="mb-2 block text-sm font-semibold text-[#1B365D]">Roster screenshot or trip
                    PDF</label>
                <input id="file" type="file" name="file" accept="application/pdf,image/*"
                    class="block w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] file:mr-4 file:rounded-md file:border-0 file:bg-[#1B365D] file:px-4 file:py-2 file:font-semibold file:text-[#F8F9FA]">
                @error('file')
                <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button
                id="parseBtn"
                type="submit"
                data-parse-submit
                class="inline-flex items-center justify-center gap-2 rounded-md bg-[#C5A059] px-6 py-3 font-bold text-[#0B0E14] shadow-sm transition hover:bg-[#b6914b] disabled:cursor-not-allowed disabled:bg-[#1B365D]/55 disabled:text-[#F8F9FA]/80 disabled:shadow-none">
                <span data-submit-label>Parse</span>
                <span data-submit-spinner class="hidden text-base leading-none" aria-hidden="true">⏳</span>
            </button>
        </div>

        <div
            id="parserStatus"
            class="mt-4 hidden rounded-lg border border-[#1B365D]/10 px-4 py-3"
            role="status"
            aria-live="polite"
            data-state="idle">
            <div class="flex items-start gap-3">
                <span
                    id="parserStatusDot"
                    class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"
                    aria-hidden="true"></span>
                <div class="space-y-1">
                    <p id="parserStatusTitle" class="text-sm font-semibold text-[#1B365D]">Ready to parse</p>
                    <p id="parserStatusMessage" class="text-sm text-[#4A5568]">Upload a screenshot or PDF, then parse. Uploads usually finish in under 15 seconds.</p>
                </div>
            </div>
        </div>
    </div>

    <fieldset class="p-5">
        <legend class="text-sm font-semibold text-[#1B365D]">Filters</legend>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach ($model->filterOptions as $option)
                <label
                    class="flex items-center gap-3 rounded-md border border-[#1B365D]/15 bg-[#F8F9FA] px-4 py-3 text-sm font-medium text-[#0B0E14]">
                    <input type="checkbox" name="event_types[]" value="{{ $option['value'] }}" class="h-4 w-4 accent-[#C5A059]"
                        @checked(in_array($option['value'], $model->selectedTypes, true))>
                    <span>
                        <span class="block">{{ $option['label'] }}</span>
                        <span class="block text-xs font-normal text-[#4A5568]">{{ $option['description'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <p class="mt-2 text-sm text-[#4A5568]">Leave all unchecked to include duties, flights, and layovers.</p>
        @error('event_types')
        <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
        @error('event_types.*')
        <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
    </fieldset>

    <details class="border-t border-[#1B365D]/10 p-5">
        <summary class="cursor-pointer font-semibold text-[#1B365D]">Paste extracted text instead</summary>
        <textarea name="text"
            class="mt-3 h-40 w-full rounded-md border border-[#4A5568]/30 bg-[#F8F9FA] p-3 text-sm text-[#0B0E14] outline-none focus:border-[#C5A059]"
            placeholder="Paste OCR text if you already have it...">{{ $model->text }}</textarea>
        @error('text')
        <p class="mt-2 text-sm font-medium text-red-700">{{ $message }}</p>
        @enderror
        <p class="mt-3">
            <button
                class="cc-btn-cta disabled:cursor-not-allowed disabled:bg-[#1B365D]/55 disabled:text-[#F8F9FA]/80"
                type="submit"
                data-parse-submit>
                <span data-submit-label>Parse</span>
                <span data-submit-spinner class="hidden" aria-hidden="true"> ⏳</span>
            </button>
        </p>
    </details>
    <script>
        const parserForm = document.getElementById('parserForm');
        const fileInput = document.getElementById('file');
        const submitButtons = Array.from(document.querySelectorAll('[data-parse-submit]'));
        const statusPanel = document.getElementById('parserStatus');
        const statusDot = document.getElementById('parserStatusDot');
        const statusTitle = document.getElementById('parserStatusTitle');
        const statusMessage = document.getElementById('parserStatusMessage');
        const progressTimers = [];

        const clearProgressTimers = () => {
            while (progressTimers.length > 0) {
                window.clearTimeout(progressTimers.pop());
            }
        };

        const setStatus = (state, title, message) => {
            if (! statusPanel || ! statusDot || ! statusTitle || ! statusMessage) {
                return;
            }

            statusPanel.dataset.state = state;
            statusTitle.textContent = title;
            statusMessage.textContent = message;
            statusPanel.classList.remove('hidden');

            statusPanel.classList.remove('bg-[#1B365D]/[0.03]', 'bg-[#C5A059]/10', 'bg-[#1B365D]/10');
            statusDot.classList.remove('bg-emerald-500', 'bg-[#C5A059]', 'bg-[#1B365D]');

            if (state === 'ready') {
                statusPanel.classList.add('bg-[#C5A059]/10');
                statusDot.classList.add('bg-[#C5A059]');
                return;
            }

            if (state === 'processing') {
                statusPanel.classList.add('bg-[#1B365D]/10');
                statusDot.classList.add('bg-[#1B365D]');
                return;
            }

            statusPanel.classList.add('bg-[#1B365D]/[0.03]');
            statusDot.classList.add('bg-emerald-500');
        };

        const setSubmittingState = (isSubmitting) => {
            submitButtons.forEach((button) => {
                button.disabled = isSubmitting;

                const label = button.querySelector('[data-submit-label]');
                const spinner = button.querySelector('[data-submit-spinner]');

                if (label) {
                    label.textContent = isSubmitting ? 'Parsing...' : 'Parse';
                }

                if (spinner) {
                    spinner.classList.toggle('hidden', ! isSubmitting);
                }
            });
        };

        const formatFileSize = (size) => {
            if (! Number.isFinite(size) || size <= 0) {
                return null;
            }

            if (size < 1024 * 1024) {
                return `${(size / 1024).toFixed(1)} KB`;
            }

            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        };

        if (parserForm && statusPanel) {
            if (fileInput) {
                fileInput.addEventListener('change', () => {
                    const [selectedFile] = fileInput.files ?? [];

                    if (! selectedFile) {
                        clearProgressTimers();
                        statusPanel.classList.add('hidden');
                        statusPanel.dataset.state = 'idle';
                        return;
                    }

                    const fileDetails = fileSize ? `${selectedFile.name}` : selectedFile.name;

                    setStatus('ready', 'File ready to upload', `${fileDetails} selected. Click Parse to upload and start extracting the schedule.`);
                });
            }

            parserForm.addEventListener('submit', () => {
                clearProgressTimers();
                setSubmittingState(true);
                setStatus('processing', 'Uploading your file...', 'Your file is on the way. Parsing will start as soon as the upload finishes.');

                progressTimers.push(window.setTimeout(() => {
                    setStatus('processing', 'Reading and classifying the schedule...', 'The parser is extracting trip details and building your events now.');
                }, 2500));

                progressTimers.push(window.setTimeout(() => {
                    setStatus('processing', 'Still working...', 'Longer schedules can take around 10 to 15 seconds. Keep this tab open while we finish.');
                }, 7000));

                progressTimers.push(window.setTimeout(() => {
                    setStatus('processing', 'Finalizing your results...', 'Almost done. We are packaging the parsed events and preparing the page refresh.');
                }, 11000));
            });

            window.addEventListener('pageshow', () => {
                clearProgressTimers();
                setSubmittingState(false);
            });
        }
    </script>
</form>
