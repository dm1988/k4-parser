export default () => ({
    isSubmitting: false,
    statusVisible: false,
    statusState: 'idle',
    statusTitle: 'Ready to extract',
    statusMessage: 'Upload a screenshot or PDF, then extract. Uploads usually finish in under 15 seconds.',
    progressTimers: [],

    init() {
        const handlePageShow = () => {
            this.clearProgressTimers();
            this.isSubmitting = false;
        };

        window.addEventListener('pageshow', handlePageShow);

        this.$cleanup(() => {
            window.removeEventListener('pageshow', handlePageShow);
        });
    },

    get statusPanelClasses() {
        return {
            hidden: !this.statusVisible,
            'bg-[#C5A059]/10 border border-[#C5A059]/20': this.statusState === 'ready',
            'bg-[#1B365D]/10 border border-[#1B365D]/20': this.statusState === 'processing',
            'bg-[#1B365D]/[0.03] border border-transparent': !['ready', 'processing'].includes(this.statusState),
        };
    },

    get statusDotClasses() {
        return {
            'bg-[#C5A059]': this.statusState === 'ready',
            'bg-[#1B365D]': this.statusState === 'processing',
            'bg-emerald-500': !['ready', 'processing'].includes(this.statusState),
        };
    },

    handleFileChange(event) {
        const selectedFile = event.target.files?.[0];

        if (!selectedFile) {
            this.clearProgressTimers();
            this.statusVisible = false;
            this.statusState = 'idle';
            return;
        }

        const sizeString = this.formatFileSize(selectedFile.size);
        const fileDetails = sizeString
            ? `${selectedFile.name} (${sizeString})`
            : selectedFile.name;

        this.setStatus(
            'ready',
            'File ready to upload',
            `${fileDetails} selected. Click Extract to upload and start extracting the schedule.`,
        );
    },

    resetFileInput(event) {
        event.target.value = null;
    },

    handleSubmit() {
        this.clearProgressTimers();
        this.isSubmitting = true;
        
        this.setStatus(
            'processing',
            'Uploading your file...',
            'Your file is on the way. Extraction will start as soon as the upload finishes.',
        );

        this.progressTimers.push(window.setTimeout(() => {
            this.setStatus(
                'processing',
                'Reading and classifying the schedule...',
                'The extractor is reading trip details and building your events now.',
            );
        }, 2500));

        this.progressTimers.push(window.setTimeout(() => {
            this.setStatus(
                'processing',
                'Still working...',
                'Longer schedules can take around 10 to 15 seconds. Keep this tab open while we finish.',
            );
        }, 7000));

        this.progressTimers.push(window.setTimeout(() => {
            this.setStatus(
                'processing',
                'Finalizing your results...',
                'Almost done. We are packaging the extracted events and preparing the page refresh.',
            );
        }, 11000));
    },

    clearProgressTimers() {
        while (this.progressTimers.length > 0) {
            window.clearTimeout(this.progressTimers.pop());
        }
    },

    setStatus(state, title, message) {
        this.statusState = state;
        this.statusTitle = title;
        this.statusMessage = message;
        this.statusVisible = true;
    },

    formatFileSize(size) {
        if (!Number.isFinite(size) || size < 0) {
            return null;
        }
        if (size === 0) {
            return '0 KB';
        }

        const kb = size / 1024;
        if (kb < 1024) {
            return `${kb.toFixed(1)} KB`;
        }

        const mb = kb / 1024;
        return `${mb.toFixed(1)} MB`;
    },
});
