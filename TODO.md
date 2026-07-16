# Current Task: Add airport info popover

## 🎯 Goal
  - Expose the view model to the airport lookup client service
  - Look up the departure and arrival airport by IATA code
  - Convert the Airport Data DTO for use in the blade view
	- Add a click-triggered popover on each airport code 
  — it keeps the clean flight route visualization intact while revealing ICAO, IATA, and full name on demand. 
  - A small info badge under the code signals interactivity without cluttering the layout.

## Design choices:

	- A small "info" label with icon sits beneath each code to signal interactivity without cluttering the route visualization
	- The code highlights gold on hover, matching the existing brand accent
	- The popover aligns left for departure and right for arrival so it stays within the card bounds
	- A thin gold gradient strip at the bottom of the popover ties it back to the brand palette
	- Clicking outside dismisses it automatically
    - Departure and arrival popovers align left/right so they stay within the card bounds


## Sample code Typescript:
function CopyButton({ value, label }: { value: string; label: string }) {
  const { copied, copy } = useCopy(value);
  return (
    <button
      type="button"
      onClick={copy}
      title={copied ? "Copied!" : `Copy ${label}`}
      className="inline-flex items-center justify-center rounded-md transition h-8 w-8 shrink-0 border border-[#1B365D]/10 bg-[#F8F9FA] text-[#1B365D] hover:bg-[#eef0f3]"
    >
      {copied
        ? <Check className="h-4 w-4 text-emerald-600" />
        : <Copy className="h-4 w-4" />
      }
      <span className="sr-only">{copied ? "Copied" : `Copy ${label}`}</span>
    </button>
  );
}

interface AirportPopoverProps {
  info: AirportInfo;
  align: "left" | "right";
}

function AirportPopover({ info, align }: AirportPopoverProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener("mousedown", handleClick);
    return () => document.removeEventListener("mousedown", handleClick);
  }, [open]);

  return (
    <div ref={ref} className="relative inline-block">
      <button
        onClick={() => setOpen((v) => !v)}
        className={`group flex flex-col gap-0.5 focus:outline-none ${align === "right" ? "items-end text-right" : "items-start text-left"}`}
        aria-expanded={open}
        aria-label={`Airport info for ${info.iata}`}
      >
        <span className="font-mono text-xl font-bold tracking-[0.04em] text-[#1B365D] sm:text-2xl transition-colors group-hover:text-[#C5A059]">
          {info.iata}
        </span>
        <span className="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-widest text-[#4A5568]/60 transition-colors group-hover:text-[#C5A059]">
          <svg className="h-2.5 w-2.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="8" x2="12" y2="12" />
            <line x1="12" y1="16" x2="12.01" y2="16" />
          </svg>
          info
        </span>
      </button>

      {open && (
        <div className={`absolute top-full mt-2 z-50 w-64 rounded-xl border border-[#1B365D]/12 bg-white shadow-xl shadow-[#1B365D]/10 ring-1 ring-[#1B365D]/5 ${align === "right" ? "right-0" : "left-0"}`}>
          <div className="flex items-center justify-between border-b border-[#1B365D]/10 bg-[#E9F0F8] px-4 py-2.5 rounded-t-xl">
            <span className="font-mono text-xs font-bold uppercase tracking-[0.18em] text-[#1B365D]">Airport Info</span>
            <button
              onClick={() => setOpen(false)}
              className="flex h-5 w-5 items-center justify-center rounded-full text-[#4A5568] hover:bg-[#1B365D]/10 transition-colors"
              aria-label="Close"
            >
              <X className="h-3 w-3" />
            </button>
          </div>

          <div className="px-4 py-3 space-y-2.5">
            <p className="text-[13px] font-semibold leading-snug text-[#0B0E14]">{info.name}</p>

            {/* City / State row */}
            <p className="text-[12px] text-[#4A5568]">
              {info.city}{info.state ? `, ${info.state}` : ""}
            </p>

            <div className="h-px bg-[#1B365D]/8" />

            <div className="flex gap-2">
              {/* IATA chip */}
              <div className="flex-1 rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] px-3 py-2">
                <p className="text-[10px] font-semibold uppercase tracking-widest text-[#4A5568]">IATA</p>
                <div className="mt-0.5 flex items-center justify-between gap-1">
                  <p className="font-mono text-base font-bold text-[#1B365D]">{info.iata}</p>
                  <CopyButton value={info.iata} label="IATA" />
                </div>
              </div>
              {/* ICAO chip */}
              <div className="flex-1 rounded-lg border border-[#1B365D]/10 bg-[#F8F9FA] px-3 py-2">
                <p className="text-[10px] font-semibold uppercase tracking-widest text-[#4A5568]">ICAO</p>
                <div className="mt-0.5 flex items-center justify-between gap-1">
                  <p className="font-mono text-base font-bold text-[#1B365D]">{info.icao}</p>
                  <CopyButton value={info.icao} label="ICAO" />
                </div>
              </div>
            </div>
          </div>

          <div className="h-1 w-full rounded-b-xl bg-gradient-to-r from-[#C5A059]/60 via-[#C5A059] to-[#C5A059]/60" />
        </div>
      )}
    </div>
  );
}

    {/* Departure */}
              <div className="flex flex-col gap-2">
                <AirportPopover info={departure} align="left" />
                <span className="text-base font-semibold text-[#0B0E14] tabular-nums">
                  {times.dep[timeMode]}
                </span>
              </div>

    {/* Arrival */}
              <div className="flex flex-col items-end gap-2 text-right">
                <AirportPopover info={arrival} align="right" />
                <span className="text-base font-semibold text-[#0B0E14] tabular-nums">
                  {times.arr[timeMode]}
                </span>
              </div>
            </div>