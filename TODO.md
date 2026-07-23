# Codex Usage Rules

Follow these rules for every remaining task:
4. Work on one numbered task at a time.
5. Run only focused tests while implementing a task.
6. Run Pint only when PHP files change.
7. Larastan once at the final integration checkpoint, not after every small edit.
9. Preserve unrelated working-tree changes.
10. Update this file with outcomes instead of adding another plan or duplicate checklist.

## 1. Current focus: Streamline the Upload Card Structure
Outcome: Completed. The upload flow is now a single flat, centered tool without separate hero or form cards. It includes a large drag-and-drop target, selected filename and file size feedback, a full-width stateful “Extract Schedule” button, and an accessible workflow-guide link.

- The file upload input and the "Parse" button feel somewhat detached because they are aligned horizontally with wide gaps.

- Fix: Consider stacking the input elements.
- Transform the upload zone into a larger, centered drag-and-drop target box with an icon, placing a full-width or cleanly aligned "Parse" button directly beneath it.
- Ensure workflow guide remains accessable
- Centered design:
- Jeppesen Crew Access in gold at top
- `Schedule Extractor` title
- Subtext: `Upload a roster screenshot or trip PDF to instantly convert your schedule into calendar-ready events.`
- Large dropzone: `Drop your schedule here` title
- Dropzone subtitle: `Supports PDF and all image formats. Or click to browse your files.`
- Large `Extract Schedule` button. Initially gray until file is ready, then gold
- File size in dropzone area. Hover styles
- `Click to change` with pending file

- Reference code from figma make:
  return (
    <form onSubmit={handleParse} className="space-y-6">
      <div
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
        className={`
      relative group cursor-pointer
      flex flex-col items-center justify-center
      aspect-[2/1] w-full max-w-md mx-auto
      rounded-3xl border-2 border-dashed transition-all duration-300
      ${
        isDragging
          ? "border-[#C5A059] bg-[#C5A059]/5 scale-[1.01] shadow-xl shadow-[#C5A059]/10"
          : "border-[#1B365D]/20 bg-white hover:border-[#C5A059]/50 hover:bg-[#F8F9FA] hover:shadow-lg"
      }
    `}
      >
        <input
          type="file"
          ref={fileInputRef}
          onChange={handleFileChange}
          className="hidden"
          accept="application/pdf,image/*"
        />
        <div className="flex flex-col items-center text-center p-6">
          <div
            className={`mb-4 p-4 rounded-2xl transition-colors duration-300 ${isDragging ? "bg-[#C5A059] text-white" : "bg-[#1B365D] text-[#F8F9FA]"}`}
          >
            <svg
              className="w-8 h-8"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={1.5}
                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
              />
            </svg>
          </div>
          <h3 className="text-xl font-bold mb-2">
            {file ? file.name : "Drop your schedule here"}
          </h3>
          <p className="text-sm text-[#4A5568] max-w-xs">
            {file
              ? `${(file.size / 1024 / 1024).toFixed(2)} MB • Click to change`
              : "Supports PDF and all image formats. Or click to browse your files."}
          </p>
        </div>
        {isDragging && (
          <div className="absolute inset-4 border border-[#C5A059]/20 rounded-2xl pointer-events-none animate-pulse" />
        )}
      </div>

      <div className="max-w-2xl mx-auto w-full space-y-4">
        <button
          type="submit"
          disabled={!file || isSubmitting}
          className={`
        w-full py-5 rounded-2xl font-bold text-lg shadow-lg transition-all duration-300
        flex items-center justify-center gap-3
        ${
          !file || isSubmitting
            ? "bg-[#1B365D]/10 text-[#1B365D]/40 cursor-not-allowed shadow-none"
            : "bg-[#C5A059] text-[#0B0E14] hover:bg-[#D4AF37] hover:scale-[1.02] active:scale-[0.98] shadow-[#C5A059]/20 hover:shadow-[#C5A059]/40"
        }
      `}
        >
          {isSubmitting ? (
            <>
              <svg
                className="animate-spin h-5 w-5"
                viewBox="0 0 24 24"
              >
                <circle
                  className="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  strokeWidth="4"
                  fill="none"
                />
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
              <span>Extracting Schedule...</span>
            </>
          ) : (
            <>
              <span>Extract Schedule</span>
              <svg
                className="w-5 h-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2.5}
                  d="M14 5l7 7m0 0l-7 7m7-7H3"
                />
              </svg>
            </>
          )}
        </button>

        <div className="flex flex-col sm:flex-row items-center justify-between pt-4 text-xs font-medium text-[#4A5568] gap-4">
          <div className="flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
            <span>System online: Ready to process</span>
          </div>
          <div className="grid grid-cols-2 gap-3 w-full sm:w-auto sm:flex sm:items-center sm:gap-4 mt-2 sm:mt-0">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                className="w-4 h-4 rounded border-[#1B365D]/20 text-[#C5A059] focus:ring-[#C5A059]"
              />
              <span>Flights only</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                className="w-4 h-4 rounded border-[#1B365D]/20 text-[#C5A059] focus:ring-[#C5A059]"
              />
              <span>Duties only</span>
            </label>
          </div>
        </div>
      </div>
    </form>

## 2. Product and UI Polish

* Improve the upload target and button alignment.
* Refine the hero and upload-card hierarchy, spacing, and visual balance.
* Improve filter accordion alignment.
* Add more vertical padding to the navbar.
* Shorten upload status copy.
* Continue spelling out “Jeppesen Crew Access” before introducing the “JCA” abbreviation.



## 3. Naming and Architecture

* Clean up parser-to-extract naming.
* Find all parser references
* Decide how to hanlde to prefered extract verbage
* Rename 2 dtos: ParsedEventDTO, ParserResultData
* Rename Enum, Exceptions
* Rename ParserEventType Enum to ScheduleEventType.php
* Rename controller, policy
* Rename model
* Explore renaming DB table

## 4. Deferred Architecture Ideas

These are proposals, not approved work:

- Rename parser-centric types only as part of a separately scoped refactor.
- Any reorganization must preserve behavior, update namespaces atomically, and be covered by focused and full tests.
- Multi photo uploads feature

## 5. Refine Card Margins and Navbar Typography

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.


## 6. Use a descriptive footer disclaimer in Parse schedule tool:
- To safely clarify your tool's relationship to the platform, add a small, subtle line of text at the very bottom of your application page layout:

Disclaimer: This tool is an independent utility built for crew convenience and is not affiliated with, authorized, or endorsed by Jeppesen or Boeing.

## 7. One line status message? Can leave as title, sub title message
- In schedule extractor, shorten status codes
- System online: Ready to process
- 0.3 MB image selected, ready to upload

## 8. CKS flight codes strings should be extracted to env file and not in the codebase
