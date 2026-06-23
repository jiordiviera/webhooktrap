export function SignalField() {
  return (
    <div
      className="pointer-events-none absolute inset-0 overflow-hidden"
      aria-hidden="true"
    >
      <svg
        className="absolute -top-8 right-0 h-[420px] w-[min(90vw,720px)] opacity-40"
        viewBox="0 0 720 420"
        fill="none"
      >
        <path
          d="M0 280 C120 280, 180 180, 300 180 S480 80, 620 120 S720 200, 720 200"
          stroke="oklch(0.42 0.075 46 / 0.25)"
          strokeWidth="1.5"
        />
        <path
          d="M0 320 C140 320, 220 240, 360 240 S520 160, 680 200"
          stroke="oklch(0.55 0.12 145 / 0.3)"
          strokeWidth="1"
        />
        <path
          d="M40 360 C160 300, 280 340, 400 280 S560 220, 700 260"
          stroke="oklch(0.42 0.075 46 / 0.15)"
          strokeWidth="1"
          strokeDasharray="6 10"
        />
      </svg>

      <div className="landing-signal-orb absolute top-[18%] right-[12%] size-32 rounded-full bg-primary/10 blur-3xl" />
      <div className="landing-signal-orb-delayed absolute top-[42%] right-[28%] size-48 rounded-full bg-signal/10 blur-3xl" />
      <div className="landing-signal-orb absolute bottom-[20%] left-[8%] size-40 rounded-full bg-accent blur-3xl" />
    </div>
  )
}