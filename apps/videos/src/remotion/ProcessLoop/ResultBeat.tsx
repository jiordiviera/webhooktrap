import { AbsoluteFill, Easing, interpolate, useCurrentFrame } from "remotion";
import { RollingNumber } from "../remocn/rolling-number";

const SIGNAL_GREEN = "oklch(0.55 0.12 145)";

export function ResultBeat({ durationInFrames }: { durationInFrames: number }) {
  const frame = useCurrentFrame();

  const labelOpacity = interpolate(frame, [10, 26], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
    easing: Easing.out(Easing.quad),
  });

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "oklch(0.21 0.04 42)",
        justifyContent: "center",
        alignItems: "center",
      }}
    >
      <div
        style={{
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 12,
        }}
      >
        <p
          style={{
            fontFamily: "ui-monospace, SFMono-Regular, monospace",
            fontSize: 22,
            color: SIGNAL_GREEN,
            opacity: labelOpacity,
            margin: 0,
          }}
        >
          200 OK
        </p>

        <div style={{ position: "relative", height: 110, width: 260 }}>
          <RollingNumber
            from={0}
            to={142}
            fontSize={90}
            color="oklch(0.97 0.01 42)"
            durationInFrames={durationInFrames}
          />
        </div>

        <p
          style={{
            fontFamily: "ui-monospace, SFMono-Regular, monospace",
            fontSize: 20,
            color: "oklch(0.7 0.03 42)",
            opacity: labelOpacity,
            margin: 0,
            letterSpacing: "0.04em",
          }}
        >
          ms round-trip
        </p>
      </div>
    </AbsoluteFill>
  );
}
