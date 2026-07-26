# Codex Usage Rules

Follow these rules for every remaining task:
1. Work on one task at a time marked by ##.
2. Run only focused tests while implementing a task.
3. Run Pint after PHP files change.
4. Larastan once at the final integration checkpoint, not after every small edit.
5. Preserve unrelated working-tree changes.
6. Update this file with outcomes instead of adding another plan or duplicate checklist.

## Completed: Add OB - Observer to crew roles

## Current focus: Multi photo uploads feature
* Only allow multiple images, not multiple PDFs

### Public property state
* Change public ?TemporaryUploadedFile $file to public array $files = []
* Update updated hook to updatedFiles() and pass the array through validation.
* Iterate and combine results sorted by date

### Validation rules
* if an image is uploaded, PDFs or text cannot be accepted

### Backend execution handling
* JcaScheduleProcessor needs to accept array<UploadedFile>
* loop through each $file
* perform extraction
* merge/deduplicate the resulting calendar_events
* HandleExtractExecution::handle() - Update type hinting to accept array|UploadedFile|null $file

### Blade view updates
* <input 
    type="file" 
    wire:model="files" 
    multiple 
    accept="image/png,image/jpeg,image/webp,application/pdf" 
/>
* <!-- Multi-file preview list (Optional) -->
@if ($files)
    <ul>
        @foreach ($files as $file)
            <li>{{ $file->getClientOriginalName() }}</li>
        @endforeach
    </ul>
@endif

<!-- Validation Errors -->
@error('files') <span class="error">{{ $message }}</span> @enderror
@error('files.*') <span class="error">{{ $message }}</span> @enderror

## CKS flight codes strings should be extracted to env file and not in the codebase
- add to user preferences

## Airport Lookup Client address should be in .env with a fallback
- migrate localhost value into .env and config

## Flight accordion: 
* Convert FLIGHT TIMES (LOCAL) and DUTY TIMES (LOCAL) into standard 2-column grid cards just like the rest, then group them under a distinct sub-heading so the layout pattern stays consistent.

## Remove Observer from operating crew
* Change Crew List Parser Test assertion

## Release 1
- All tests pass

## API

## Flight plan extractor improvements
* Extract ETOPs EENT, EEXP and ETP